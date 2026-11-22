<?php
declare(strict_types=1);

/** Read-only analytics for the Reports page and the dashboards. */
final class ReportModel
{
    /** Native prepares forbid a repeated :name, so every occurrence gets its own numbered placeholder. */
    private static function q(string $sql, array $p = []): array
    {
        $bind = []; $n = 0;
        $sql = preg_replace_callback('/:([a-z_]\w*)/i', static function ($m) use ($p, &$bind, &$n) {
            $k = $m[1] . '_' . (++$n); $bind[':' . $k] = $p[':' . $m[1]] ?? null; return ':' . $k;
        }, $sql);
        $s = db()->prepare($sql); $s->execute($bind); return $s->fetchAll();
    }
    private static function one(string $sql, array $p = []): array { return self::q($sql, $p)[0] ?? []; }

    /** @return array<string,mixed> */
    public static function summary(string $from, string $to): array
    {
        $p = [':f' => $from . ' 00:00:00', ':t' => $to . ' 23:59:59'];
        $w = 's.created_at BETWEEN :f AND :t';
        $tot = self::one("SELECT COUNT(*) AS booked,
            SUM(s.status = 'Delivered') AS delivered, SUM(s.status = 'Cancelled') AS cancelled, SUM(s.status IN ('Returning','Returned')) AS returned,
            COALESCE(SUM(CASE WHEN s.status <> 'Cancelled' THEN s.total_charge END),0) AS revenue,
            COALESCE(SUM(CASE WHEN s.status <> 'Cancelled' THEN s.chargeable_kg END),0) AS kg,
            COALESCE(SUM(CASE WHEN s.status = 'Delivered' THEN s.cod_amount END),0) AS cod_collected,
            SUM(s.status = 'Delivered' AND DATE(s.delivered_at) <= s.promised_date) AS on_time
            FROM shipments s WHERE $w", $p);
        $booked = (int) $tot['booked']; $del = (int) $tot['delivered'];
        $tot['avg_value'] = $booked - (int) $tot['cancelled'] > 0 ? round((float) $tot['revenue'] / ($booked - (int) $tot['cancelled']), 2) : 0;
        $tot['on_time_pct'] = $del > 0 ? round(100 * (int) $tot['on_time'] / $del, 1) : null;
        $tot['success_pct'] = ($del + (int) $tot['returned']) > 0 ? round(100 * $del / ($del + (int) $tot['returned']), 1) : null;
        return [
            'totals'   => $tot,
            'daily'    => self::q("SELECT DATE(s.created_at) AS d, COUNT(*) AS booked, SUM(s.status = 'Delivered') AS delivered, COALESCE(SUM(CASE WHEN s.status <> 'Cancelled' THEN s.total_charge END),0) AS revenue FROM shipments s WHERE $w GROUP BY DATE(s.created_at) ORDER BY d", $p),
            'status'   => self::q("SELECT s.status AS k, COUNT(*) AS n FROM shipments s WHERE $w GROUP BY s.status ORDER BY n DESC", $p),
            'service'  => self::q("SELECT s.service AS k, COUNT(*) AS n, COALESCE(SUM(s.total_charge),0) AS revenue FROM shipments s WHERE $w AND s.status <> 'Cancelled' GROUP BY s.service ORDER BY n DESC", $p),
            'zone'     => self::q("SELECT s.zone AS k, COUNT(*) AS n, COALESCE(SUM(s.total_charge),0) AS revenue FROM shipments s WHERE $w AND s.status <> 'Cancelled' GROUP BY s.zone ORDER BY n DESC", $p),
            'reasons'  => self::q("SELECT s.fail_reason AS k, COUNT(*) AS n FROM shipments s WHERE $w AND s.fail_reason IS NOT NULL GROUP BY s.fail_reason ORDER BY n DESC", $p),
            'branches' => self::q("SELECT b.city, (SELECT COUNT(*) FROM shipments s WHERE s.origin_branch_id = b.id AND $w) AS sent,
                (SELECT COUNT(*) FROM shipments s WHERE s.dest_branch_id = b.id AND s.status = 'Delivered' AND $w) AS delivered,
                (SELECT COALESCE(SUM(s.total_charge),0) FROM shipments s WHERE s.origin_branch_id = b.id AND s.status <> 'Cancelled' AND $w) AS revenue
                FROM branches b WHERE b.is_active = 1 ORDER BY sent DESC", $p),
            'riders'   => self::q("SELECT u.full_name, b.city,
                (SELECT COUNT(*) FROM shipments s WHERE s.pickup_rider_id = u.id AND s.status NOT IN ('Booked','Pickup Assigned','Cancelled') AND $w) AS pickups,
                (SELECT COUNT(*) FROM shipments s WHERE s.delivery_rider_id = u.id AND s.status = 'Delivered' AND $w) AS delivered,
                (SELECT COUNT(*) FROM shipments s WHERE s.delivery_rider_id = u.id AND s.status = 'Delivered' AND DATE(s.delivered_at) <= s.promised_date AND $w) AS on_time,
                (SELECT COUNT(*) FROM shipments s WHERE s.delivery_rider_id = u.id AND s.attempts > 0 AND $w) AS failed
                FROM users u LEFT JOIN branches b ON b.id = u.branch_id WHERE u.role = 'rider' ORDER BY delivered DESC, u.full_name", $p),
        ];
    }

    /** Dataset for CSV export. @return array{0:string[],1:array[]} */
    public static function export(string $type, string $from, string $to): array
    {
        $p = [':f' => $from . ' 00:00:00', ':t' => $to . ' 23:59:59'];
        if ($type === 'cod') {
            return [['Settlement', 'Rider', 'Amount', 'Items', 'Settled by', 'Date'], self::q("SELECT st.settle_no, r.full_name, st.amount, st.items, u.full_name AS by_name, st.created_at FROM cod_settlements st JOIN users r ON r.id = st.rider_id LEFT JOIN users u ON u.id = st.settled_by WHERE st.created_at BETWEEN :f AND :t ORDER BY st.id", $p)];
        }
        return [['Tracking', 'Booked', 'Sender', 'Receiver', 'Origin', 'Destination', 'Service', 'Zone', 'Kg charged', 'COD', 'Freight', 'Fuel', 'Fragile', 'COD fee', 'Insurance', 'VAT', 'Total', 'Payment', 'Status', 'Promised', 'Delivered'],
            self::q("SELECT s.tracking_no, s.created_at, s.sender_name, s.receiver_name, o.city, d.city AS dc, s.service, s.zone, s.chargeable_kg, s.cod_amount, s.freight, s.fuel, s.fragile_fee, s.cod_fee, s.insurance_fee, s.tax, s.total_charge, s.payment_status, s.status, s.promised_date, s.delivered_at
                FROM shipments s JOIN branches o ON o.id = s.origin_branch_id JOIN branches d ON d.id = s.dest_branch_id WHERE s.created_at BETWEEN :f AND :t ORDER BY s.id", $p)];
    }

    /** Operational dashboard for staff. */
    public static function dashboard(): array
    {
        $k = self::one("SELECT
            SUM(DATE(created_at) = CURDATE()) AS booked_today,
            SUM(status = 'Delivered' AND DATE(delivered_at) = CURDATE()) AS delivered_today,
            SUM(status NOT IN ('Delivered','Returned','Cancelled')) AS active,
            SUM(status = 'Out for Delivery') AS ofd,
            SUM(status NOT IN ('Delivered','Returned','Cancelled') AND promised_date < CURDATE()) AS late,
            SUM(status IN ('Delivery Failed','Returning')) AS exceptions,
            COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() AND status <> 'Cancelled' THEN total_charge END),0) AS revenue_today,
            COALESCE(SUM(CASE WHEN created_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01') AND status <> 'Cancelled' THEN total_charge END),0) AS revenue_month
            FROM shipments");
        $k['cod_road'] = (float) db()->query('SELECT COALESCE(SUM(amount),0) FROM rider_cash WHERE settlement_id IS NULL')->fetchColumn();
        $ot = self::one("SELECT COUNT(*) AS n, SUM(DATE(delivered_at) <= promised_date) AS ok FROM shipments WHERE status = 'Delivered' AND delivered_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        $k['on_time_pct'] = (int) $ot['n'] > 0 ? round(100 * (int) $ot['ok'] / (int) $ot['n'], 1) : null;
        return [
            'kpi' => $k,
            'trend' => self::q("SELECT d.day, (SELECT COUNT(*) FROM shipments WHERE DATE(created_at) = d.day) AS booked, (SELECT COUNT(*) FROM shipments WHERE status = 'Delivered' AND DATE(delivered_at) = d.day) AS delivered
                FROM (SELECT DATE_SUB(CURDATE(), INTERVAL n DAY) AS day FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13) x) d ORDER BY d.day"),
            'pipeline' => self::q("SELECT status AS k, COUNT(*) AS n FROM shipments WHERE status NOT IN ('Delivered','Returned','Cancelled') GROUP BY status"),
            'branches' => self::q("SELECT b.city, COUNT(s.id) AS n FROM branches b LEFT JOIN shipments s ON s.dest_branch_id = b.id AND s.status NOT IN ('Delivered','Returned','Cancelled') WHERE b.is_active = 1 GROUP BY b.id, b.city ORDER BY n DESC"),
            'attention' => self::q("SELECT s.id, s.tracking_no, s.status, s.receiver_name, s.promised_date, s.fail_reason, s.attempts, d.city AS dest_city FROM shipments s JOIN branches d ON d.id = s.dest_branch_id
                WHERE s.status IN ('Delivery Failed','Returning') OR (s.status NOT IN ('Delivered','Returned','Cancelled') AND s.promised_date < CURDATE()) ORDER BY s.promised_date LIMIT 8"),
        ];
    }

    /** Customer portal summary. */
    public static function customer(int $cid): array
    {
        $k = self::one("SELECT COUNT(*) AS total, SUM(status NOT IN ('Delivered','Returned','Cancelled')) AS active, SUM(status = 'Delivered') AS delivered,
            SUM(status = 'Out for Delivery') AS ofd, COALESCE(SUM(CASE WHEN payment_status = 'Unpaid' AND status <> 'Cancelled' AND payer = 'Sender' THEN total_charge END),0) AS outstanding
            FROM shipments WHERE customer_id = :c", [':c' => $cid]);
        return ['kpi' => $k, 'recent' => self::q("SELECT s.id, s.tracking_no, s.status, s.receiver_name, s.promised_date, d.city AS dest_city, s.total_charge FROM shipments s JOIN branches d ON d.id = s.dest_branch_id WHERE s.customer_id = :c ORDER BY s.id DESC LIMIT 8", [':c' => $cid])];
    }
}
