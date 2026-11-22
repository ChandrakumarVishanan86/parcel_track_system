<?php
declare(strict_types=1);

final class CustomerModel extends BaseModel
{
    protected static string $table = 'customers';
    protected static array $fields = [
        'full_name' => ['label' => 'Contact name', 'required' => true, 'max' => 120, 'regex' => "/^[\p{L}][\p{L} .'\-]*$/u", 'regexMsg' => 'Name may contain letters only.'],
        'company'   => ['label' => 'Company', 'max' => 120],
        'phone'     => ['label' => 'Phone', 'type' => 'phone', 'required' => true, 'max' => 20, 'unique' => true, 'regex' => '/^[0-9+\-\s]{9,20}$/', 'regexMsg' => 'Enter a valid phone number.'],
        'email'     => ['label' => 'Email', 'type' => 'email', 'max' => 120, 'lower' => true],
        'address'   => ['label' => 'Pickup address', 'max' => 255],
        'branch_id' => ['label' => 'Home branch', 'type' => 'ref', 'ref' => 'branches'],
        'is_credit' => ['label' => 'Credit account', 'type' => 'bool'],
        'notes'     => ['label' => 'Notes', 'max' => 255],
        'is_active' => ['label' => 'Active', 'type' => 'bool'],
    ];
    protected static function baseSelect(): string
    {
        return "SELECT t.*, b.city AS branch_city,
            (SELECT COUNT(*) FROM shipments s WHERE s.customer_id = t.id) AS shipments,
            (SELECT COALESCE(SUM(s.total_charge),0) FROM shipments s WHERE s.customer_id = t.id AND s.payment_status = 'Unpaid' AND s.status <> 'Cancelled' AND s.payer = 'Sender') AS outstanding,
            (SELECT COUNT(*) FROM users u WHERE u.customer_id = t.id) AS has_login
            FROM customers t LEFT JOIN branches b ON b.id = t.branch_id";
    }
    protected static function orderBy(): string { return 'ORDER BY t.full_name'; }
    public static function scope(array $user): array
    {
        return $user['role'] === 'customer' ? ['t.id = :cid', [':cid' => (int) $user['customer_id']]] : ['', []];
    }
    public static function canAccess(array $user, array $row): bool { return $user['role'] !== 'customer' || (int) $row['id'] === (int) $user['customer_id']; }
    protected static function extraInsert(array $raw): array
    {
        $n = (int) db()->query('SELECT COALESCE(MAX(id),0) FROM customers')->fetchColumn() + 1;
        return ['code' => sprintf('CUS-%04d', $n)];
    }
    protected static function afterSave(int $id, array $d, bool $isNew, array $raw): void
    {
        $pdo = db();
        $pdo->prepare('UPDATE users SET full_name = :n, email = :e, phone = :p WHERE customer_id = :id')->execute([':n' => $d['full_name'], ':e' => $d['email'], ':p' => $d['phone'], ':id' => $id]);
        if (!empty($raw['create_login'])) {
            $has = $pdo->prepare('SELECT 1 FROM users WHERE customer_id = :id'); $has->execute([':id' => $id]);
            if (!$has->fetchColumn()) {
                $username = 'c' . preg_replace('/\D+/', '', (string) $d['phone']);
                $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, customer_id) VALUES (:u,:p,:n,:e,:ph,'customer',:id)")
                    ->execute([':u' => $username, ':p' => password_hash(DEFAULT_CUSTOMER_PASSWORD, PASSWORD_DEFAULT), ':n' => $d['full_name'], ':e' => $d['email'], ':ph' => $d['phone'], ':id' => $id]);
            }
        }
        Audit::log($isNew ? 'create' : 'update', 'customer', $id, ($isNew ? 'Added ' : 'Updated ') . $d['full_name']);
    }
    public static function beforeDelete(int $id): ?string
    {
        $s = db()->prepare('SELECT COUNT(*) FROM shipments WHERE customer_id = :id'); $s->execute([':id' => $id]);
        return $s->fetchColumn() > 0 ? 'This customer has shipments. Mark the account inactive instead.' : null;
    }
    public static function delete(int $id): bool
    {
        db()->prepare("DELETE FROM users WHERE customer_id = :id AND role = 'customer'")->execute([':id' => $id]);
        return parent::delete($id);
    }
    public static function search(string $term, int $limit = 10): array
    {
        $s = db()->prepare("SELECT id, code, full_name, company, phone, address, branch_id, is_credit FROM customers WHERE is_active = 1 AND (full_name LIKE :a OR company LIKE :b OR phone LIKE :c OR code LIKE :d) ORDER BY full_name LIMIT $limit");
        $l = '%' . $term . '%'; $s->execute([':a' => $l, ':b' => $l, ':c' => $l, ':d' => $l]);
        return $s->fetchAll();
    }
}
