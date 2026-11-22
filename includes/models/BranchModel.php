<?php
declare(strict_types=1);

final class BranchModel extends BaseModel
{
    protected static string $table = 'branches';
    protected static array $fields = [
        'code'    => ['label' => 'Code', 'required' => true, 'max' => 3, 'upper' => true, 'unique' => true, 'regex' => '/^[A-Z]{3}$/', 'regexMsg' => '3 capital letters.'],
        'name'    => ['label' => 'Branch name', 'required' => true, 'max' => 80],
        'city'    => ['label' => 'City', 'required' => true, 'max' => 60, 'unique' => true],
        'region'  => ['label' => 'Region', 'required' => true, 'max' => 40],
        'address' => ['label' => 'Address', 'max' => 200],
        'phone'   => ['label' => 'Phone', 'max' => 20, 'regex' => '/^[0-9+\-\s]{9,20}$/', 'regexMsg' => 'Enter a valid phone number.'],
        'is_hub'  => ['label' => 'Sorting hub', 'type' => 'bool'],
        'is_active' => ['label' => 'Active', 'type' => 'bool'],
    ];
    protected static function baseSelect(): string
    {
        return "SELECT t.*, (SELECT COUNT(*) FROM users u WHERE u.branch_id = t.id AND u.role = 'rider' AND u.is_active = 1) AS riders,
            (SELECT COUNT(*) FROM shipments s WHERE (s.origin_branch_id = t.id OR s.dest_branch_id = t.id) AND s.status NOT IN ('Delivered','Returned','Cancelled')) AS active_parcels FROM branches t";
    }
    protected static function orderBy(): string { return 'ORDER BY t.id'; }
    protected static function afterSave(int $id, array $d, bool $isNew, array $raw): void { Audit::log($isNew ? 'create' : 'update', 'branch', $id, $d['name']); }
    public static function beforeDelete(int $id): ?string
    {
        $s = db()->prepare('SELECT (SELECT COUNT(*) FROM shipments WHERE origin_branch_id = :a OR dest_branch_id = :b) + (SELECT COUNT(*) FROM users WHERE branch_id = :c)');
        $s->execute([':a' => $id, ':b' => $id, ':c' => $id]);
        return $s->fetchColumn() > 0 ? 'This branch has parcels or staff. Mark it inactive instead.' : null;
    }
    public static function options(bool $activeOnly = true): array
    {
        return db()->query('SELECT id, code, name, city, region FROM branches' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY city')->fetchAll();
    }
}
