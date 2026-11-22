<?php
declare(strict_types=1);

/** Linehaul manifests: a truck load of parcels moving between two branches. Open -> Dispatched -> Received. */
final class ManifestModel
{
    public static function find(int $id): ?array
    {
        $s = db()->prepare('SELECT m.*, f.city AS from_city, f.name AS from_name, t.city AS to_city, t.name AS to_name,
            (SELECT COUNT(*) FROM manifest_items i WHERE i.manifest_id = m.id) AS parcels, (SELECT COUNT(*) FROM manifest_items i WHERE i.manifest_id = m.id AND i.received = 1) AS received_count
            FROM manifests m JOIN branches f ON f.id = m.from_branch_id JOIN branches t ON t.id = m.to_branch_id WHERE m.id = :id');
        $s->execute([':id' => $id]); return $s->fetch() ?: null;
    }
    public static function canAccess(array $u, array $m): bool
    {
        return $u['role'] !== 'hub' || (int) $u['branch_id'] === (int) $m['from_branch_id'] || (int) $u['branch_id'] === (int) $m['to_branch_id'];
    }
    public static function list(array $u, int $limit = 40): array
    {
        $w = ''; $p = [];
        if ($u['role'] === 'hub') { $w = 'WHERE m.from_branch_id = :a OR m.to_branch_id = :b'; $p = [':a' => $u['branch_id'], ':b' => $u['branch_id']]; }
        $s = db()->prepare("SELECT m.*, f.city AS from_city, t.city AS to_city,
            (SELECT COUNT(*) FROM manifest_items i WHERE i.manifest_id = m.id) AS parcels, (SELECT COUNT(*) FROM manifest_items i WHERE i.manifest_id = m.id AND i.received = 1) AS received_count
            FROM manifests m JOIN branches f ON f.id = m.from_branch_id JOIN branches t ON t.id = m.to_branch_id $w
            ORDER BY FIELD(m.status,'Open','Dispatched','Received'), m.id DESC LIMIT $limit");
        $s->execute($p); return $s->fetchAll();
    }
    public static function items(int $id): array
    {
        $s = db()->prepare('SELECT i.received, s.id, s.tracking_no, s.service, s.weight_kg, s.receiver_name, s.status, s.cod_amount FROM manifest_items i JOIN shipments s ON s.id = i.shipment_id WHERE i.manifest_id = :id ORDER BY s.tracking_no');
        $s->execute([':id' => $id]); return $s->fetchAll();
    }
    /** Parcels waiting at a branch for linehaul, grouped by destination. */
    public static function waiting(int $branch): array
    {
        $s = db()->prepare("SELECT s.id, s.tracking_no, s.service, s.weight_kg, s.dest_branch_id, d.city AS dest_city, s.receiver_name FROM shipments s JOIN branches d ON d.id = s.dest_branch_id
            WHERE s.status = 'At Origin Hub' AND s.origin_branch_id = :b AND s.id NOT IN (SELECT i.shipment_id FROM manifest_items i JOIN manifests m ON m.id = i.manifest_id WHERE m.status = 'Open')
            ORDER BY d.city, s.tracking_no");
        $s->execute([':b' => $branch]); return $s->fetchAll();
    }
    private static function tx(callable $fn): mixed
    {
        $pdo = db(); $pdo->beginTransaction();
        try { $r = $fn(); $pdo->commit(); return $r; } catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
    }

    /** @return array{0:?int,1:?string} */
    public static function create(array $u, int $from, int $to, array $ids, string $vehicle, string $driver): array
    {
        if (!in_array($u['role'], Auth::OPS, true)) { return [null, 'Not allowed.']; }
        if ($u['role'] === 'hub') { $from = (int) $u['branch_id']; }
        if ($from === $to) { return [null, 'Origin and destination must be different branches.']; }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) { return [null, 'Select at least one parcel for the manifest.']; }
        $vehicle = trim($vehicle); $driver = trim($driver);
        if (mb_strlen($vehicle) > 20 || mb_strlen($driver) > 80) { return [null, 'Vehicle or driver text is too long.']; }
        return self::tx(function () use ($u, $from, $to, $ids, $vehicle, $driver) {
            $b = db()->prepare('SELECT COUNT(*) FROM branches WHERE is_active = 1 AND id IN (:a,:b)'); $b->execute([':a' => $from, ':b' => $to]);
            if ((int) $b->fetchColumn() < 2) { return [null, 'Choose valid branches.']; }
            foreach ($ids as $id) {
                $s = db()->prepare('SELECT * FROM shipments WHERE id = :id FOR UPDATE'); $s->execute([':id' => $id]); $x = $s->fetch();
                if (!$x) { return [null, 'Shipment not found.']; }
                if ($x['status'] !== 'At Origin Hub' || (int) $x['origin_branch_id'] !== $from) { return [null, $x['tracking_no'] . ' is not waiting at the origin hub.']; }
                if ((int) $x['dest_branch_id'] !== $to) { return [null, $x['tracking_no'] . ' is going to ' . db()->query('SELECT city FROM branches WHERE id = ' . (int) $x['dest_branch_id'])->fetchColumn() . ', not this destination.']; }
                $dup = db()->prepare("SELECT 1 FROM manifest_items i JOIN manifests m ON m.id = i.manifest_id WHERE i.shipment_id = :id AND m.status = 'Open'"); $dup->execute([':id' => $id]);
                if ($dup->fetchColumn()) { return [null, $x['tracking_no'] . ' is already on an open manifest.']; }
            }
            $pre = 'MF-' . date('ymd') . '-';
            $m = db()->prepare('SELECT MAX(manifest_no) FROM manifests WHERE manifest_no LIKE :p FOR UPDATE'); $m->execute([':p' => $pre . '%']);
            $max = $m->fetchColumn(); $no = $pre . str_pad((string) ($max ? ((int) substr((string) $max, -2)) + 1 : 1), 2, '0', STR_PAD_LEFT);
            db()->prepare('INSERT INTO manifests (manifest_no, from_branch_id, to_branch_id, vehicle_no, driver_name, created_by) VALUES (:n,:f,:t,:v,:d,:u)')
                ->execute([':n' => $no, ':f' => $from, ':t' => $to, ':v' => $vehicle ?: null, ':d' => $driver ?: null, ':u' => $u['id']]);
            $mid = (int) db()->lastInsertId();
            $ins = db()->prepare('INSERT INTO manifest_items (manifest_id, shipment_id) VALUES (:m,:s)');
            foreach ($ids as $id) { $ins->execute([':m' => $mid, ':s' => $id]); }
            Audit::log('create', 'manifest', $mid, "$no with " . count($ids) . ' parcel(s)');
            return [$mid, null];
        });
    }
    public static function dispatch(int $id, array $u): ?string
    {
        return self::tx(function () use ($id, $u) {
            $s = db()->prepare('SELECT * FROM manifests WHERE id = :id FOR UPDATE'); $s->execute([':id' => $id]); $m = $s->fetch();
            if (!$m || !self::canAccess($u, $m)) { return 'Manifest not found.'; }
            if (!in_array($u['role'], Auth::OPS, true)) { return 'Not allowed.'; }
            if ($m['status'] !== 'Open') { return 'This manifest is already ' . strtolower($m['status']) . '.'; }
            if ($u['role'] === 'hub' && (int) $u['branch_id'] !== (int) $m['from_branch_id']) { return 'Only the origin hub can dispatch this manifest.'; }
            $items = db()->prepare('SELECT s.* FROM manifest_items i JOIN shipments s ON s.id = i.shipment_id WHERE i.manifest_id = :id FOR UPDATE'); $items->execute([':id' => $id]);
            $fc = db()->query('SELECT city FROM branches WHERE id = ' . (int) $m['from_branch_id'])->fetchColumn(); $tc = db()->query('SELECT city FROM branches WHERE id = ' . (int) $m['to_branch_id'])->fetchColumn();
            foreach ($items->fetchAll() as $x) {
                if ($x['status'] !== 'At Origin Hub') { return $x['tracking_no'] . ' is ' . $x['status'] . ' and cannot be dispatched.'; }
                db()->prepare("UPDATE shipments SET status = 'In Transit' WHERE id = :id")->execute([':id' => $x['id']]);
                ShipmentModel::event((int) $x['id'], 'In Transit', "$fc → $tc", 'Dispatched on manifest ' . $m['manifest_no'], (int) $u['id']);
            }
            db()->prepare("UPDATE manifests SET status = 'Dispatched', dispatched_at = NOW() WHERE id = :id")->execute([':id' => $id]);
            Audit::log('dispatch', 'manifest', $id, $m['manifest_no'] . ' dispatched');
            return null;
        });
    }
    public static function cancel(int $id, array $u): ?string
    {
        if (!in_array($u['role'], Auth::OPS, true)) { return 'Not allowed.'; }
        $s = db()->prepare('SELECT * FROM manifests WHERE id = :id'); $s->execute([':id' => $id]); $m = $s->fetch();
        if (!$m || !self::canAccess($u, $m)) { return 'Manifest not found.'; }
        if ($m['status'] !== 'Open') { return 'Only open manifests can be deleted.'; }
        db()->prepare('DELETE FROM manifests WHERE id = :id AND status = \'Open\'')->execute([':id' => $id]);
        Audit::log('delete', 'manifest', $id, $m['manifest_no'] . ' deleted');
        return null;
    }
    /** Scan parcels off the truck at the destination. @return array{0:array,1:?string} */
    public static function receive(int $id, array $tns, array $u): array
    {
        if (!in_array($u['role'], Auth::OPS, true)) { return [[], 'Not allowed.']; }
        return self::tx(function () use ($id, $tns, $u) {
            $s = db()->prepare('SELECT * FROM manifests WHERE id = :id FOR UPDATE'); $s->execute([':id' => $id]); $m = $s->fetch();
            if (!$m || !self::canAccess($u, $m)) { return [[], 'Manifest not found.']; }
            if ($u['role'] === 'hub' && (int) $u['branch_id'] !== (int) $m['to_branch_id']) { return [[], 'Only the destination hub can receive this manifest.']; }
            if ($m['status'] !== 'Dispatched') { return [[], $m['status'] === 'Received' ? 'This manifest was already received.' : 'Dispatch the manifest before receiving it.']; }
            $res = []; $city = db()->query('SELECT name FROM branches WHERE id = ' . (int) $m['to_branch_id'])->fetchColumn();
            foreach (array_slice(array_values(array_unique(array_filter(array_map([ShipmentModel::class, 'normaliseTn'], $tns)))), 0, 200) as $tn) {
                $q = db()->prepare('SELECT s.id, i.received, s.status FROM manifest_items i JOIN shipments s ON s.id = i.shipment_id WHERE i.manifest_id = :m AND s.tracking_no = :t FOR UPDATE'); $q->execute([':m' => $id, ':t' => $tn]); $x = $q->fetch();
                if (!$x) { $res[] = ['tn' => $tn, 'ok' => false, 'message' => 'Not on this manifest']; continue; }
                if ((int) $x['received']) { $res[] = ['tn' => $tn, 'ok' => false, 'message' => 'Already received']; continue; }
                db()->prepare('UPDATE manifest_items SET received = 1 WHERE manifest_id = :m AND shipment_id = :s')->execute([':m' => $id, ':s' => $x['id']]);
                db()->prepare("UPDATE shipments SET status = 'At Destination Hub' WHERE id = :id")->execute([':id' => $x['id']]);
                ShipmentModel::event((int) $x['id'], 'At Destination Hub', (string) $city, 'Received at destination hub (manifest ' . $m['manifest_no'] . ')', (int) $u['id']);
                $res[] = ['tn' => $tn, 'ok' => true, 'message' => 'Received'];
            }
            $left = db()->prepare('SELECT COUNT(*) FROM manifest_items WHERE manifest_id = :m AND received = 0'); $left->execute([':m' => $id]);
            if ((int) $left->fetchColumn() === 0) { db()->prepare("UPDATE manifests SET status = 'Received', received_at = NOW(), received_by = :u WHERE id = :id")->execute([':u' => $u['id'], ':id' => $id]); }
            return [$res, null];
        });
    }
}
