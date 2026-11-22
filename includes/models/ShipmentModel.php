<?php
declare(strict_types=1);

/**
 * Shipment lifecycle.
 *   Booked -> Pickup Assigned -> Picked Up -> At Origin Hub -> In Transit -> At Destination Hub
 *          -> Out for Delivery -> Delivered
 *   Out for Delivery -> Delivery Failed -> (Out for Delivery again | Returning -> Returned)
 *   Booked / Pickup Assigned -> Cancelled
 * Every transition writes a shipment_events row (the public tracking timeline) and runs inside a
 * transaction with the shipment row locked, so two users can never move the same parcel twice.
 */
final class ShipmentModel
{
    public const FINAL = ['Delivered', 'Returned', 'Cancelled'];
    public const FAIL_REASONS = ['Receiver not available', 'Wrong / incomplete address', 'Phone unreachable', 'Receiver refused parcel', 'Rescheduled by receiver'];
    private const PHONE = '/^[0-9+\-\s]{9,20}$/';

    // ------------------------------------------------------------------ reads
    private static function select(): string
    {
        return "SELECT s.*, ob.city AS origin_city, ob.name AS origin_name, db.city AS dest_city, db.name AS dest_name,
                pr.full_name AS pickup_rider, dr.full_name AS delivery_rider, c.code AS customer_code, c.is_credit,
                (s.status NOT IN ('Delivered','Returned','Cancelled') AND s.promised_date < CURDATE()) AS is_late
            FROM shipments s
            JOIN branches ob ON ob.id = s.origin_branch_id JOIN branches db ON db.id = s.dest_branch_id
            LEFT JOIN users pr ON pr.id = s.pickup_rider_id LEFT JOIN users dr ON dr.id = s.delivery_rider_id
            LEFT JOIN customers c ON c.id = s.customer_id";
    }
    public static function find(int $id): ?array
    {
        $s = db()->prepare(self::select() . ' WHERE s.id = :id'); $s->execute([':id' => $id]);
        return $s->fetch() ?: null;
    }
    public static function normaliseTn(string $t): string { return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $t) ?? ''); }
    public static function findByTracking(string $tn): ?array
    {
        $s = db()->prepare(self::select() . ' WHERE s.tracking_no = :t'); $s->execute([':t' => self::normaliseTn($tn)]);
        return $s->fetch() ?: null;
    }
    public static function canAccess(array $u, array $s): bool
    {
        return match ($u['role']) {
            'customer' => (int) $s['customer_id'] === (int) $u['customer_id'],
            'rider'    => (int) $s['pickup_rider_id'] === (int) $u['id'] || (int) $s['delivery_rider_id'] === (int) $u['id'],
            'hub'      => (int) $u['branch_id'] === (int) $s['origin_branch_id'] || (int) $u['branch_id'] === (int) $s['dest_branch_id'],
            default    => true,
        };
    }
    public static function events(int $id): array
    {
        $s = db()->prepare('SELECT e.*, u.full_name AS by_name FROM shipment_events e LEFT JOIN users u ON u.id = e.user_id WHERE e.shipment_id = :id ORDER BY e.created_at DESC, e.id DESC');
        $s->execute([':id' => $id]); return $s->fetchAll();
    }
    public static function sms(int $id): array
    {
        $s = db()->prepare('SELECT * FROM sms_log WHERE shipment_id = :id ORDER BY id DESC'); $s->execute([':id' => $id]); return $s->fetchAll();
    }

    /** Filtered list. $f: status ('active' = not finished), q, branch, from, to, source, late */
    public static function list(array $u, array $f = [], int $limit = 800): array
    {
        $w = []; $p = [];
        if ($u['role'] === 'customer') { $w[] = 's.customer_id = :cid'; $p[':cid'] = (int) $u['customer_id']; }
        if ($u['role'] === 'rider') { $w[] = '(s.pickup_rider_id = :rid1 OR s.delivery_rider_id = :rid2)'; $p[':rid1'] = $p[':rid2'] = (int) $u['id']; }
        if ($u['role'] === 'hub') { $w[] = '(s.origin_branch_id = :hb1 OR s.dest_branch_id = :hb2)'; $p[':hb1'] = $p[':hb2'] = (int) $u['branch_id']; }
        $st = (string) ($f['status'] ?? '');
        if ($st === 'active') { $w[] = "s.status NOT IN ('Delivered','Returned','Cancelled')"; }
        elseif ($st === 'late') { $w[] = "s.status NOT IN ('Delivered','Returned','Cancelled') AND s.promised_date < CURDATE()"; }
        elseif ($st === 'exception') { $w[] = "s.status IN ('Delivery Failed','Returning')"; }
        elseif ($st !== '') { $w[] = 's.status = :st'; $p[':st'] = $st; }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') { $w[] = '(s.tracking_no LIKE :q1 OR s.sender_name LIKE :q2 OR s.receiver_name LIKE :q3 OR s.receiver_phone LIKE :q4 OR s.sender_phone LIKE :q5)'; foreach (['q1', 'q2', 'q3', 'q4', 'q5'] as $k) { $p[":$k"] = '%' . $q . '%'; } }
        if (!empty($f['branch'])) { $w[] = '(s.origin_branch_id = :b1 OR s.dest_branch_id = :b2)'; $p[':b1'] = $p[':b2'] = (int) $f['branch']; }
        if (!empty($f['from']) && valid_date((string) $f['from'])) { $w[] = 'DATE(s.created_at) >= :df'; $p[':df'] = $f['from']; }
        if (!empty($f['to']) && valid_date((string) $f['to'])) { $w[] = 'DATE(s.created_at) <= :dt'; $p[':dt'] = $f['to']; }
        if (!empty($f['source']) && in_array($f['source'], ['Counter', 'Online'], true)) { $w[] = 's.source = :src'; $p[':src'] = $f['source']; }
        $sql = self::select() . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY s.id DESC LIMIT ' . (int) $limit;
        $s = db()->prepare($sql); $s->execute($p);
        $rows = $s->fetchAll();
        if ($u['role'] === 'customer') { foreach ($rows as &$r) { if ($r['status'] !== 'Out for Delivery') { $r['delivery_otp'] = null; } } }
        return $rows;
    }

    // --------------------------------------------------------------- helpers
    private static function lock(int $id): ?array
    {
        $s = db()->prepare('SELECT * FROM shipments WHERE id = :id FOR UPDATE'); $s->execute([':id' => $id]);
        return $s->fetch() ?: null;
    }
    public static function event(int $id, string $status, ?string $loc, ?string $note, ?int $uid): void
    {
        db()->prepare('INSERT INTO shipment_events (shipment_id, status, location, note, user_id) VALUES (:s,:st,:l,:n,:u)')
            ->execute([':s' => $id, ':st' => $status, ':l' => $loc, ':n' => $note !== null ? mb_substr($note, 0, 255) : null, ':u' => $uid]);
    }
    public static function textTo(?int $id, string $phone, string $msg): void
    {
        db()->prepare('INSERT INTO sms_log (shipment_id, to_phone, message) VALUES (:s,:p,:m)')->execute([':s' => $id, ':p' => $phone, ':m' => mb_substr($msg, 0, 255)]);
    }
    private static function status(int $id, string $to, array $extra = []): void
    {
        $set = ['status = :st']; $p = [':st' => $to, ':id' => $id]; $i = 0;
        foreach ($extra as $col => $val) { $k = ':x' . $i++; $set[] = "`$col` = $k"; $p[$k] = $val; }
        db()->prepare('UPDATE shipments SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($p);
    }
    private static function branchCity(int $id): string
    {
        $s = db()->prepare('SELECT city FROM branches WHERE id = :id'); $s->execute([':id' => $id]); return (string) $s->fetchColumn();
    }
    private static function branchName(int $id): string
    {
        $s = db()->prepare('SELECT name FROM branches WHERE id = :id'); $s->execute([':id' => $id]); return (string) $s->fetchColumn();
    }
    public static function riderLoad(int $riderId): int
    {
        $s = db()->prepare("SELECT COUNT(*) FROM shipments WHERE (pickup_rider_id = :a AND status = 'Pickup Assigned') OR (delivery_rider_id = :b AND status IN ('Out for Delivery','Returning'))");
        $s->execute([':a' => $riderId, ':b' => $riderId]); return (int) $s->fetchColumn();
    }
    private static function riderOk(int $riderId, int $branchId): ?string
    {
        $s = db()->prepare("SELECT branch_id FROM users WHERE id = :id AND role = 'rider' AND is_active = 1"); $s->execute([':id' => $riderId]);
        $b = $s->fetchColumn();
        if ($b === false) { return 'Choose an active rider.'; }
        if ((int) $b !== $branchId) { return 'That rider belongs to a different branch (' . self::branchCity((int) $b) . ').'; }
        return null;
    }
    private static function tx(callable $fn): mixed
    {
        $pdo = db(); $pdo->beginTransaction();
        try { $r = $fn(); $pdo->commit(); return $r; }
        catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
    }
    private static function nextTracking(): string
    {
        $pre = 'PT' . date('ymd');
        $s = db()->prepare('SELECT MAX(tracking_no) FROM shipments WHERE tracking_no LIKE :p FOR UPDATE'); $s->execute([':p' => $pre . '%']);
        $max = $s->fetchColumn();
        return $pre . str_pad((string) ($max ? ((int) substr((string) $max, -4)) + 1 : 1), 4, '0', STR_PAD_LEFT);
    }
    private static function otp(): string { return (string) random_int(1000, 9999); }

    // ------------------------------------------------------------------ book
    /** @return array{0:?int,1:?string} */
    public static function create(array $u, array $in): array
    {
        $isCust = $u['role'] === 'customer';
        if (!$isCust && !in_array($u['role'], Auth::DESK, true)) { return [null, 'You cannot book parcels.']; }
        $cust = null;
        if ($isCust) {
            $s = db()->prepare('SELECT * FROM customers WHERE id = :id AND is_active = 1'); $s->execute([':id' => (int) $u['customer_id']]); $cust = $s->fetch();
            if (!$cust) { return [null, 'Your customer account is inactive.']; }
        } elseif (!empty($in['customer_id'])) {
            $s = db()->prepare('SELECT * FROM customers WHERE id = :id AND is_active = 1'); $s->execute([':id' => (int) $in['customer_id']]); $cust = $s->fetch() ?: null;
            if (!$cust) { return [null, 'Selected customer was not found.']; }
        }
        $v = static fn(string $k) => trim((string) ($in[$k] ?? ''));
        $sender = ['name' => $v('sender_name'), 'phone' => $v('sender_phone'), 'addr' => $v('sender_address')];
        if ($isCust) { $sender = ['name' => $cust['company'] ?: $cust['full_name'], 'phone' => $cust['phone'], 'addr' => $v('sender_address') ?: (string) $cust['address']]; }
        $rcv = ['name' => $v('receiver_name'), 'phone' => $v('receiver_phone'), 'addr' => $v('receiver_address')];
        foreach (['Sender' => $sender, 'Receiver' => $rcv] as $lbl => $x) {
            if (mb_strlen($x['name']) < 2 || mb_strlen($x['name']) > 120) { return [null, "$lbl name must be 2-120 characters."]; }
            if (!preg_match(self::PHONE, $x['phone'])) { return [null, "Enter a valid $lbl phone number."]; }
            if (mb_strlen($x['addr']) < 5 || mb_strlen($x['addr']) > 255) { return [null, "$lbl address must be 5-255 characters."]; }
        }
        if (preg_replace('/\D+/', '', $sender['phone']) === preg_replace('/\D+/', '', $rcv['phone'])) { return [null, 'Sender and receiver phone numbers must be different.']; }
        $desc = $v('description');
        if (mb_strlen($desc) < 2 || mb_strlen($desc) > 160) { return [null, 'Describe the contents (2-160 characters).']; }
        $pieces = (int) ($in['pieces'] ?? 1);
        if ($pieces < 1 || $pieces > 50) { return [null, 'Pieces must be between 1 and 50.']; }
        $payer = (string) ($in['payer'] ?? 'Sender');
        if (!in_array($payer, ['Sender', 'Receiver'], true)) { return [null, 'Choose who pays the freight.']; }
        $pickupDate = $v('pickup_date') ?: date('Y-m-d');
        if (!valid_date($pickupDate) || $pickupDate < date('Y-m-d') || $pickupDate > date('Y-m-d', strtotime('+7 days'))) { return [null, 'Pickup date must be within the next 7 days.']; }
        [$q, $err] = Pricing::quote($in);
        if ($err) { return [null, $err]; }
        $ob = (int) $in['origin_branch_id']; $db_ = (int) $in['dest_branch_id'];
        if ($isCust) {
            $n = db()->prepare("SELECT COUNT(*) FROM shipments WHERE customer_id = :c AND status IN ('Booked','Pickup Assigned')"); $n->execute([':c' => (int) $u['customer_id']]);
            if ((int) $n->fetchColumn() >= 15) { return [null, 'You already have 15 parcels awaiting pickup. Please wait for them to be collected.']; }
        }
        $method = (string) ($in['pay_method'] ?? '');
        $payNow = $payer === 'Sender' && in_array($method, ['Cash', 'Card', 'Online', 'Bank'], true) && !($cust && $cust['is_credit'] && $method === 'Bank');
        if ($isCust && $payNow && $method !== 'Online') { $payNow = false; }   // customers pay online now, or cash to the rider

        try {
            $id = self::tx(function () use ($u, $in, $q, $cust, $sender, $rcv, $desc, $pieces, $payer, $pickupDate, $ob, $db_, $isCust, $payNow, $method) {
                $tn = self::nextTracking();
                db()->prepare("INSERT INTO shipments (tracking_no, customer_id, source, sender_name, sender_phone, sender_address, receiver_name, receiver_phone, receiver_address,
                    origin_branch_id, dest_branch_id, service, zone, description, pieces, weight_kg, length_cm, width_cm, height_cm, chargeable_kg, declared_value, insured, fragile, cod_amount,
                    freight, fuel, fragile_fee, cod_fee, insurance_fee, tax, total_charge, payer, payment_status, payment_method, paid_at, status, pickup_date, promised_date, notes, booked_by)
                    VALUES (:tn,:c,:src,:sn,:sp,:sa,:rn,:rp,:ra,:ob,:db,:sv,:z,:d,:pc,:w,:l,:wd,:h,:ch,:dv,:ins,:fr,:cod,:f,:fu,:ff,:cf,:if,:tx,:tt,:py,:ps,:pm,:pa,'Booked',:pd,:prom,:nt,:bb)")
                    ->execute([':tn' => $tn, ':c' => $cust['id'] ?? null, ':src' => $isCust ? 'Online' : 'Counter', ':sn' => $sender['name'], ':sp' => $sender['phone'], ':sa' => $sender['addr'],
                        ':rn' => $rcv['name'], ':rp' => $rcv['phone'], ':ra' => $rcv['addr'], ':ob' => $ob, ':db' => $db_, ':sv' => $q['service'], ':z' => $q['zone'], ':d' => $desc, ':pc' => $pieces,
                        ':w' => $q['weight_kg'], ':l' => ($in['length_cm'] ?? '') !== '' ? $in['length_cm'] : null, ':wd' => ($in['width_cm'] ?? '') !== '' ? $in['width_cm'] : null, ':h' => ($in['height_cm'] ?? '') !== '' ? $in['height_cm'] : null,
                        ':ch' => $q['chargeable_kg'], ':dv' => $q['declared_value'], ':ins' => $q['insured'], ':fr' => $q['fragile'], ':cod' => $q['cod_amount'],
                        ':f' => $q['freight'], ':fu' => $q['fuel'], ':ff' => $q['fragile_fee'], ':cf' => $q['cod_fee'], ':if' => $q['insurance_fee'], ':tx' => $q['tax'], ':tt' => $q['total_charge'],
                        ':py' => $payer, ':ps' => $payNow ? 'Paid' : 'Unpaid', ':pm' => $payNow ? $method : null, ':pa' => $payNow ? date('Y-m-d H:i:s') : null,
                        ':pd' => $pickupDate, ':prom' => date('Y-m-d', strtotime($pickupDate . ' +' . $q['transit_days'] . ' days')), ':nt' => trim((string) ($in['notes'] ?? '')) ?: null, ':bb' => $u['id']]);
                $id = (int) db()->lastInsertId();
                self::event($id, 'Booked', self::branchCity($ob), $isCust ? 'Shipment booked online' : 'Shipment booked at counter', (int) $u['id']);
                self::textTo($id, $rcv['phone'], "ParcelTrack: a parcel from {$sender['name']} is on its way to you. Track it with $tn at " . COMPANY_NAME . '.');
                Audit::log('create', 'shipment', $id, "$tn booked ({$q['service']}, {$q['zone']})");
                return $id;
            });
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { return [null, 'Could not issue a tracking number. Please try again.']; }
            throw $e;
        }
        return [(int) $id, null];
    }

    // ----------------------------------------------------- cancel / payment
    public static function cancel(int $id, array $u, string $reason = ''): ?string
    {
        return self::tx(function () use ($id, $u, $reason) {
            $s = self::lock($id);
            if (!$s || !self::canAccess($u, $s) || $u['role'] === 'rider' || $u['role'] === 'hub') { return 'Shipment not found.'; }
            if (!in_array($s['status'], ['Booked', 'Pickup Assigned'], true)) { return 'A parcel can only be cancelled before it is picked up.'; }
            $refund = $s['payment_status'] === 'Paid';
            self::status($id, 'Cancelled', ['payment_status' => $refund ? 'Refunded' : $s['payment_status'], 'pickup_rider_id' => null]);
            self::event($id, 'Cancelled', self::branchCity((int) $s['origin_branch_id']), trim('Cancelled by ' . $u['full_name'] . ($reason !== '' ? ": $reason" : '')) . ($refund ? ' - freight refunded' : ''), (int) $u['id']);
            Audit::log('cancel', 'shipment', $id, $s['tracking_no'] . ' cancelled');
            return null;
        });
    }
    public static function pay(int $id, string $method, array $u): ?string
    {
        if (!in_array($u['role'], Auth::DESK, true)) { return 'Only dispatchers can record payments.'; }
        if (!in_array($method, ['Cash', 'Card', 'Online', 'Bank'], true)) { return 'Choose a payment method.'; }
        return self::tx(function () use ($id, $method, $u) {
            $s = self::lock($id);
            if (!$s) { return 'Shipment not found.'; }
            if ($s['status'] === 'Cancelled') { return 'This shipment was cancelled.'; }
            if ($s['payment_status'] !== 'Unpaid') { return 'This shipment is already ' . strtolower($s['payment_status']) . '.'; }
            if ($s['payer'] === 'Receiver' && !in_array($s['status'], ['Delivered'], true)) { return 'Freight is collected from the receiver by the rider on delivery.'; }
            db()->prepare("UPDATE shipments SET payment_status = 'Paid', payment_method = :m, paid_at = NOW() WHERE id = :id")->execute([':m' => $method, ':id' => $id]);
            self::event($id, $s['status'], null, 'Freight ' . money($s['total_charge']) . " received ($method)", (int) $u['id']);
            Audit::log('payment', 'shipment', $id, $s['tracking_no'] . ' paid ' . money($s['total_charge']) . " by $method");
            return null;
        });
    }

    // --------------------------------------------------------------- pickup
    /** @return array{0:int,1:?string} */
    public static function assignPickup(array $ids, int $riderId, array $u): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!in_array($u['role'], Auth::DESK, true)) { return [0, 'Only dispatchers can assign riders.']; }
        if (!$ids) { return [0, 'Select at least one parcel.']; }
        if (count($ids) > 60) { return [0, 'Select at most 60 parcels at a time.']; }
        return self::tx(function () use ($ids, $riderId, $u) {
            $rn = db()->prepare('SELECT full_name FROM users WHERE id = :id'); $rn->execute([':id' => $riderId]); $name = (string) $rn->fetchColumn();
            $new = 0;
            foreach ($ids as $id) {
                $s = self::lock($id);
                if (!$s) { return [0, 'Shipment not found.']; }
                if (!in_array($s['status'], ['Booked', 'Pickup Assigned'], true)) { return [0, $s['tracking_no'] . ' is ' . $s['status'] . ' and cannot be assigned for pickup.']; }
                if ($e = self::riderOk($riderId, (int) $s['origin_branch_id'])) { return [0, $s['tracking_no'] . ': ' . $e]; }
                if ((int) $s['pickup_rider_id'] !== $riderId) { $new++; }
            }
            if (self::riderLoad($riderId) + $new > RIDER_MAX_TASKS) { return [0, "$name would exceed " . RIDER_MAX_TASKS . ' active tasks.']; }
            foreach ($ids as $id) {
                $s = self::lock($id); $re = $s['status'] === 'Pickup Assigned';
                self::status($id, 'Pickup Assigned', ['pickup_rider_id' => $riderId]);
                self::event($id, 'Pickup Assigned', self::branchCity((int) $s['origin_branch_id']), ($re ? 'Pickup re-assigned to ' : 'Pickup assigned to ') . $name, (int) $u['id']);
            }
            Audit::log('assign', 'shipment', null, count($ids) . " pickup(s) assigned to $name");
            return [count($ids), null];
        });
    }
    public static function pickup(int $id, array $u, bool $collect): ?string
    {
        if ($u['role'] !== 'rider') { return 'Only the assigned rider can confirm a pickup.'; }
        return self::tx(function () use ($id, $u, $collect) {
            $s = self::lock($id);
            if (!$s || (int) $s['pickup_rider_id'] !== (int) $u['id']) { return 'This pickup is not assigned to you.'; }
            if ($s['status'] !== 'Pickup Assigned') { return 'This parcel is ' . $s['status'] . '.'; }
            $note = 'Parcel picked up from sender';
            if ($s['payer'] === 'Sender' && $s['payment_status'] === 'Unpaid') {
                $credit = $s['customer_id'] ? (bool) db()->query('SELECT is_credit FROM customers WHERE id = ' . (int) $s['customer_id'])->fetchColumn() : false;
                if ($credit) { $note .= ' (billed to account)'; }
                elseif ($collect) {
                    db()->prepare("UPDATE shipments SET payment_status = 'Paid', payment_method = 'Cash', paid_at = NOW() WHERE id = :id")->execute([':id' => $id]);
                    db()->prepare("INSERT INTO rider_cash (rider_id, shipment_id, kind, amount) VALUES (:r,:s,'Pickup freight',:a)")->execute([':r' => $u['id'], ':s' => $id, ':a' => $s['total_charge']]);
                    $note .= ' - freight ' . money($s['total_charge']) . ' collected in cash';
                } else { return 'Collect the freight (' . money($s['total_charge']) . ') from the sender before taking the parcel.'; }
            }
            self::status($id, 'Picked Up');
            self::event($id, 'Picked Up', self::branchCity((int) $s['origin_branch_id']), $note, (int) $u['id']);
            self::textTo($id, $s['sender_phone'], 'ParcelTrack: your parcel ' . $s['tracking_no'] . ' has been picked up.');
            return null;
        });
    }

    // ------------------------------------------------------------ hub scan
    /** Scan parcels into the hub at their origin branch. @return array<int,array{tn:string,ok:bool,message:string}> */
    public static function scanIn(array $tns, int $branchId, array $u): array
    {
        $res = [];
        if (!in_array($u['role'], Auth::OPS, true)) { return [['tn' => '', 'ok' => false, 'message' => 'Not allowed.']]; }
        if ($u['role'] === 'hub') { $branchId = (int) $u['branch_id']; }
        foreach (array_slice(array_values(array_unique(array_filter(array_map([self::class, 'normaliseTn'], $tns)))), 0, 100) as $tn) {
            $res[] = self::tx(function () use ($tn, $branchId, $u) {
                $s = db()->prepare('SELECT * FROM shipments WHERE tracking_no = :t FOR UPDATE'); $s->execute([':t' => $tn]); $x = $s->fetch();
                if (!$x) { return ['tn' => $tn, 'ok' => false, 'message' => 'Unknown tracking number']; }
                if ((int) $x['origin_branch_id'] !== $branchId) { return ['tn' => $tn, 'ok' => false, 'message' => 'Belongs to ' . self::branchCity((int) $x['origin_branch_id']) . ' hub, not this one']; }
                if ($x['status'] !== 'Picked Up') { return ['tn' => $tn, 'ok' => false, 'message' => $x['status'] === 'Pickup Assigned' || $x['status'] === 'Booked' ? 'Not picked up yet' : 'Already ' . $x['status']]; }
                $local = (int) $x['dest_branch_id'] === $branchId; $to = $local ? 'At Destination Hub' : 'At Origin Hub';
                self::status((int) $x['id'], $to);
                self::event((int) $x['id'], $to, self::branchName($branchId), $local ? 'Sorted at hub for local delivery' : 'Received and sorted at origin hub', (int) $u['id']);
                return ['tn' => $tn, 'ok' => true, 'message' => $local ? 'Ready for local delivery' : 'Queued for linehaul to ' . self::branchCity((int) $x['dest_branch_id'])];
            });
        }
        return $res;
    }

    // ------------------------------------------------------------ delivery
    public static function assignDelivery(array $ids, int $riderId, array $u): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!in_array($u['role'], Auth::DESK, true)) { return [0, 'Only dispatchers can assign riders.']; }
        if (!$ids) { return [0, 'Select at least one parcel.']; }
        if (count($ids) > 60) { return [0, 'Select at most 60 parcels at a time.']; }
        return self::tx(function () use ($ids, $riderId, $u) {
            $rn = db()->prepare('SELECT full_name FROM users WHERE id = :id'); $rn->execute([':id' => $riderId]); $name = (string) $rn->fetchColumn();
            foreach ($ids as $id) {
                $s = self::lock($id);
                if (!$s) { return [0, 'Shipment not found.']; }
                if (!in_array($s['status'], ['At Destination Hub', 'Delivery Failed', 'Returning'], true)) { return [0, $s['tracking_no'] . ' is ' . $s['status'] . ' and cannot go out for delivery.']; }
                if ($s['status'] === 'Returning' && $s['delivery_rider_id'] && (int) $s['delivery_rider_id'] === $riderId) { return [0, $s['tracking_no'] . ' is already assigned to this rider.']; }
                if ($e = self::riderOk($riderId, (int) $s['dest_branch_id'])) { return [0, $s['tracking_no'] . ': ' . $e]; }
            }
            if (self::riderLoad($riderId) + count($ids) > RIDER_MAX_TASKS) { return [0, "$name would exceed " . RIDER_MAX_TASKS . ' active tasks.']; }
            foreach ($ids as $id) {
                $s = self::lock($id); $city = self::branchCity((int) $s['dest_branch_id']);
                if ($s['status'] === 'Returning') {
                    self::status($id, 'Returning', ['delivery_rider_id' => $riderId]);
                    self::event($id, 'Returning', $city, "Return-to-sender run assigned to $name", (int) $u['id']);
                } else {
                    $otp = self::otp();
                    self::status($id, 'Out for Delivery', ['delivery_rider_id' => $riderId, 'delivery_otp' => $otp, 'otp_fails' => 0]);
                    self::event($id, 'Out for Delivery', $city, "Out for delivery with $name (attempt " . ((int) $s['attempts'] + 1) . ')', (int) $u['id']);
                    self::textTo($id, $s['receiver_phone'], 'ParcelTrack: your parcel ' . $s['tracking_no'] . " is out for delivery today. Delivery code $otp. Share it with the rider only.");
                }
            }
            Audit::log('assign', 'shipment', null, count($ids) . " delivery run item(s) assigned to $name");
            return [count($ids), null];
        });
    }
    public static function deliver(int $id, array $u, string $otp, string $receivedBy): ?string
    {
        if ($u['role'] !== 'rider') { return 'Only the assigned rider can complete a delivery.'; }
        $receivedBy = trim($receivedBy);
        if (mb_strlen($receivedBy) < 2 || mb_strlen($receivedBy) > 120) { return 'Enter the name of the person who received the parcel.'; }
        if (!preg_match('/^\d{4}$/', $otp)) { return 'Enter the 4-digit delivery code.'; }
        $wrong = null;
        $err = self::tx(function () use ($id, $u, $otp, $receivedBy, &$wrong) {
            $s = self::lock($id);
            if (!$s || (int) $s['delivery_rider_id'] !== (int) $u['id']) { return 'This delivery is not assigned to you.'; }
            if ($s['status'] !== 'Out for Delivery') { return 'This parcel is ' . $s['status'] . '.'; }
            if ((int) $s['otp_fails'] >= OTP_MAX_FAILS) { return 'The delivery code is locked after too many wrong entries. Ask the dispatcher to reset it.'; }
            if (!hash_equals((string) $s['delivery_otp'], $otp)) { $wrong = (int) $s['otp_fails'] + 1; return '__wrong'; }
            $cashNote = '';
            if ((float) $s['cod_amount'] > 0) {
                db()->prepare("INSERT INTO rider_cash (rider_id, shipment_id, kind, amount) VALUES (:r,:s,'COD',:a)")->execute([':r' => $u['id'], ':s' => $id, ':a' => $s['cod_amount']]);
                $cashNote .= ' - COD ' . money($s['cod_amount']) . ' collected';
            }
            $extra = ['delivered_at' => date('Y-m-d H:i:s'), 'received_by' => $receivedBy, 'delivery_otp' => null];
            if ($s['payer'] === 'Receiver' && $s['payment_status'] === 'Unpaid') {
                db()->prepare("INSERT INTO rider_cash (rider_id, shipment_id, kind, amount) VALUES (:r,:s,'Delivery freight',:a)")->execute([':r' => $u['id'], ':s' => $id, ':a' => $s['total_charge']]);
                $extra += ['payment_status' => 'Paid', 'payment_method' => 'Cash', 'paid_at' => date('Y-m-d H:i:s')];
                $cashNote .= ' - freight ' . money($s['total_charge']) . ' collected';
            }
            self::status($id, 'Delivered', $extra);
            self::event($id, 'Delivered', self::branchCity((int) $s['dest_branch_id']), "Delivered to $receivedBy (code verified)$cashNote", (int) $u['id']);
            self::textTo($id, $s['sender_phone'], 'ParcelTrack: ' . $s['tracking_no'] . " was delivered to $receivedBy. Thank you!");
            return null;
        });
        if ($err === '__wrong') {
            db()->prepare('UPDATE shipments SET otp_fails = otp_fails + 1 WHERE id = :id AND status = \'Out for Delivery\'')->execute([':id' => $id]);
            $left = OTP_MAX_FAILS - (int) $wrong;
            return $left > 0 ? "Wrong delivery code. $left attempt(s) left." : 'Wrong delivery code. The code is now locked - ask the dispatcher to reset it.';
        }
        return $err;
    }
    public static function fail(int $id, array $u, string $reason): ?string
    {
        if ($u['role'] !== 'rider') { return 'Only the assigned rider can report a failed delivery.'; }
        if (!in_array($reason, self::FAIL_REASONS, true)) { return 'Choose a failure reason.'; }
        return self::tx(function () use ($id, $u, $reason) {
            $s = self::lock($id);
            if (!$s || (int) $s['delivery_rider_id'] !== (int) $u['id']) { return 'This delivery is not assigned to you.'; }
            if ($s['status'] !== 'Out for Delivery') { return 'This parcel is ' . $s['status'] . '.'; }
            $att = (int) $s['attempts'] + 1; $city = self::branchCity((int) $s['dest_branch_id']);
            self::status($id, 'Delivery Failed', ['attempts' => $att, 'fail_reason' => $reason, 'delivery_otp' => null, 'delivery_rider_id' => null]);
            self::event($id, 'Delivery Failed', $city, "Attempt $att failed: $reason", (int) $u['id']);
            self::textTo($id, $s['receiver_phone'], 'ParcelTrack: we could not deliver ' . $s['tracking_no'] . " ($reason). " . ($att < MAX_ATTEMPTS ? 'We will try again.' : 'The parcel will be returned to the sender.'));
            if ($att >= MAX_ATTEMPTS) { self::toReturning($s, (int) $u['id'], 'Maximum ' . MAX_ATTEMPTS . ' attempts reached - returning to sender'); }
            return null;
        });
    }
    /** Move a parcel to Returning; the sender is billed if the receiver was to pay. */
    private static function toReturning(array $s, int $uid, string $note): void
    {
        $extra = ['delivery_rider_id' => null, 'delivery_otp' => null];
        if ($s['payer'] === 'Receiver' && $s['payment_status'] === 'Unpaid') { $extra['payer'] = 'Sender'; $note .= ' (freight billed to sender)'; }
        self::status((int) $s['id'], 'Returning', $extra);
        self::event((int) $s['id'], 'Returning', self::branchCity((int) $s['dest_branch_id']), $note, $uid);
        self::textTo((int) $s['id'], $s['sender_phone'], 'ParcelTrack: ' . $s['tracking_no'] . ' could not be delivered and is being returned to you.');
    }
    public static function returnToSender(int $id, array $u): ?string
    {
        if (!in_array($u['role'], Auth::DESK, true)) { return 'Only dispatchers can return a parcel.'; }
        return self::tx(function () use ($id, $u) {
            $s = self::lock($id);
            if (!$s) { return 'Shipment not found.'; }
            if (!in_array($s['status'], ['Delivery Failed', 'At Destination Hub'], true)) { return 'Only parcels waiting at the destination hub can be returned.'; }
            self::toReturning($s, (int) $u['id'], 'Return to sender authorised by ' . $u['full_name']);
            Audit::log('return', 'shipment', $id, $s['tracking_no'] . ' returned to sender');
            return null;
        });
    }
    public static function returned(int $id, array $u): ?string
    {
        if ($u['role'] !== 'rider') { return 'Only the assigned rider can confirm a return.'; }
        return self::tx(function () use ($id, $u) {
            $s = self::lock($id);
            if (!$s || (int) $s['delivery_rider_id'] !== (int) $u['id']) { return 'This return is not assigned to you.'; }
            if ($s['status'] !== 'Returning') { return 'This parcel is ' . $s['status'] . '.'; }
            self::status($id, 'Returned', ['received_by' => $s['sender_name']]);
            self::event($id, 'Returned', self::branchCity((int) $s['origin_branch_id']), 'Returned to sender ' . $s['sender_name'], (int) $u['id']);
            return null;
        });
    }
    public static function regenOtp(int $id, array $u): ?string
    {
        if (!in_array($u['role'], Auth::DESK, true)) { return 'Only dispatchers can reset a delivery code.'; }
        return self::tx(function () use ($id, $u) {
            $s = self::lock($id);
            if (!$s || $s['status'] !== 'Out for Delivery') { return 'Only parcels out for delivery have a delivery code.'; }
            $otp = self::otp();
            db()->prepare('UPDATE shipments SET delivery_otp = :o, otp_fails = 0 WHERE id = :id')->execute([':o' => $otp, ':id' => $id]);
            self::event($id, 'Out for Delivery', null, 'Delivery code re-issued by ' . $u['full_name'], (int) $u['id']);
            self::textTo($id, $s['receiver_phone'], 'ParcelTrack: new delivery code for ' . $s['tracking_no'] . " is $otp. Share it with the rider only.");
            return null;
        });
    }

    /** Position (0..5) on the public stepper. */
    public static function stage(string $status): int
    {
        return match ($status) {
            'Booked' => 0, 'Pickup Assigned' => 0, 'Picked Up' => 1, 'At Origin Hub' => 2, 'In Transit' => 3, 'At Destination Hub' => 3,
            'Out for Delivery', 'Delivery Failed' => 4, 'Delivered' => 5, 'Returning', 'Returned' => 4, default => 0,
        };
    }
}
