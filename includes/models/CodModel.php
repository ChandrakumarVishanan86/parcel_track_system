<?php
declare(strict_types=1);

/** Cash the riders collect (COD + freight) and the settlement that hands it over to the office. */
final class CodModel
{
    public static function riders(): array
    {
        return db()->query("SELECT u.id, u.full_name, u.vehicle, b.city,
            COALESCE(SUM(CASE WHEN c.settlement_id IS NULL THEN c.amount END),0) AS on_hand,
            SUM(c.settlement_id IS NULL) AS items,
            COALESCE(SUM(CASE WHEN c.settlement_id IS NULL AND c.kind = 'COD' THEN c.amount END),0) AS cod,
            MIN(CASE WHEN c.settlement_id IS NULL THEN c.created_at END) AS oldest
            FROM users u LEFT JOIN branches b ON b.id = u.branch_id LEFT JOIN rider_cash c ON c.rider_id = u.id
            WHERE u.role = 'rider' AND u.is_active = 1 GROUP BY u.id, u.full_name, u.vehicle, b.city ORDER BY on_hand DESC, u.full_name")->fetchAll();
    }
    public static function lines(int $riderId): array
    {
        $s = db()->prepare('SELECT c.*, s.tracking_no, s.receiver_name FROM rider_cash c JOIN shipments s ON s.id = c.shipment_id WHERE c.rider_id = :r AND c.settlement_id IS NULL ORDER BY c.created_at');
        $s->execute([':r' => $riderId]); return $s->fetchAll();
    }
    public static function onHand(int $riderId): float
    {
        $s = db()->prepare('SELECT COALESCE(SUM(amount),0) FROM rider_cash WHERE rider_id = :r AND settlement_id IS NULL'); $s->execute([':r' => $riderId]); return (float) $s->fetchColumn();
    }
    public static function history(int $limit = 60): array
    {
        return db()->query("SELECT st.*, r.full_name AS rider, u.full_name AS by_name FROM cod_settlements st JOIN users r ON r.id = st.rider_id LEFT JOIN users u ON u.id = st.settled_by ORDER BY st.id DESC LIMIT $limit")->fetchAll();
    }
    /** Hand over everything the rider is holding. @return array{0:?int,1:?string} */
    public static function settle(int $riderId, float $counted, string $note, array $u): array
    {
        if (!in_array($u['role'], Auth::DESK, true)) { return [null, 'Only dispatchers can settle rider cash.']; }
        $pdo = db(); $pdo->beginTransaction();
        try {
            $r = $pdo->prepare("SELECT id FROM users WHERE id = :id AND role = 'rider' FOR UPDATE"); $r->execute([':id' => $riderId]);
            if (!$r->fetchColumn()) { $pdo->rollBack(); return [null, 'Rider not found.']; }
            $q = $pdo->prepare('SELECT id, amount FROM rider_cash WHERE rider_id = :r AND settlement_id IS NULL FOR UPDATE'); $q->execute([':r' => $riderId]); $rows = $q->fetchAll();
            if (!$rows) { $pdo->rollBack(); return [null, 'This rider is not holding any cash.']; }
            $total = round(array_sum(array_column($rows, 'amount')), 2);
            if (abs($counted - $total) > 0.009) { $pdo->rollBack(); return [null, 'Counted cash ' . money($counted) . ' does not match the amount due ' . money($total) . '. Re-count, or note the shortage with the manager.']; }
            $pre = 'ST-' . date('ymd') . '-';
            $m = $pdo->prepare('SELECT MAX(settle_no) FROM cod_settlements WHERE settle_no LIKE :p FOR UPDATE'); $m->execute([':p' => $pre . '%']); $max = $m->fetchColumn();
            $no = $pre . str_pad((string) ($max ? ((int) substr((string) $max, -2)) + 1 : 1), 2, '0', STR_PAD_LEFT);
            $pdo->prepare('INSERT INTO cod_settlements (settle_no, rider_id, amount, items, note, settled_by) VALUES (:n,:r,:a,:i,:nt,:u)')
                ->execute([':n' => $no, ':r' => $riderId, ':a' => $total, ':i' => count($rows), ':nt' => trim($note) ?: null, ':u' => $u['id']]);
            $sid = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE rider_cash SET settlement_id = :s WHERE rider_id = :r AND settlement_id IS NULL')->execute([':s' => $sid, ':r' => $riderId]);
            $pdo->commit();
            Audit::log('settle', 'rider_cash', $sid, "$no " . money($total));
            return [$sid, null];
        } catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
    }
}
