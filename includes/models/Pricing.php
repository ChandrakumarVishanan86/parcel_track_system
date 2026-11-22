<?php
declare(strict_types=1);

/**
 * Shipping charge calculator - the single source of truth for every price.
 *
 *   chargeable kg = max(actual, L x W x H / 5000), rounded UP to the next 0.5 kg (min 0.5)
 *   freight       = base_price + (chargeable - base_weight) x per_extra_kg        (rate card: service x zone)
 *   fuel          = 8 % of freight
 *   fragile fee   = Rs. 150 (if fragile)
 *   COD fee       = max(Rs. 100, 2 % of COD amount)     (only when COD > 0)
 *   insurance     = 1 % of declared value               (only when insured)
 *   VAT 18 %      = on (freight + fuel + fragile + COD fee + insurance)
 */
final class Pricing
{
    public const SERVICES = ['Standard', 'Express', 'Same-day'];

    public static function zone(int $origin, int $dest): ?string
    {
        if ($origin === $dest) { return 'Local'; }
        $s = db()->prepare('SELECT id, region FROM branches WHERE id IN (:a, :b)');
        $s->execute([':a' => $origin, ':b' => $dest]);
        $r = array_column($s->fetchAll(), 'region', 'id');
        if (count($r) < 2) { return null; }
        return $r[$origin] === $r[$dest] ? 'Regional' : 'National';
    }

    private static function money(float $x): float { return round($x + 1e-9, 2); }

    /** @return array{0:?array,1:?string} [quote, error] */
    public static function quote(array $in): array
    {
        $service = (string) ($in['service'] ?? '');
        if (!in_array($service, self::SERVICES, true)) { return [null, 'Choose a service.']; }
        $ob = (int) ($in['origin_branch_id'] ?? 0); $db = (int) ($in['dest_branch_id'] ?? 0);
        $chk = db()->prepare('SELECT COUNT(*) FROM branches WHERE is_active = 1 AND id IN (:a, :b)');
        $chk->execute([':a' => $ob, ':b' => $db]);
        if ((int) $chk->fetchColumn() < ($ob === $db ? 1 : 2)) { return [null, 'Choose valid pickup and delivery cities.']; }
        $zone = self::zone($ob, $db);
        if ($zone === null) { return [null, 'Choose valid pickup and delivery cities.']; }

        $w = $in['weight_kg'] ?? '';
        if (!is_numeric($w) || (float) $w <= 0) { return [null, 'Enter the parcel weight.']; }
        $w = (float) $w;
        if ($w > MAX_WEIGHT_KG) { return [null, 'Maximum weight per shipment is ' . MAX_WEIGHT_KG . ' kg.']; }

        $dims = [];
        foreach (['length_cm', 'width_cm', 'height_cm'] as $k) { $v = $in[$k] ?? ''; if ($v !== '' && $v !== null) { $dims[$k] = $v; } }
        $vol = 0.0;
        if ($dims) {
            if (count($dims) !== 3) { return [null, 'Enter all three dimensions (L × W × H) or leave them empty.']; }
            foreach ($dims as $v) { if (!is_numeric($v) || (float) $v < 1 || (float) $v > 200) { return [null, 'Each dimension must be between 1 and 200 cm.']; } }
            $vol = (float) $dims['length_cm'] * (float) $dims['width_cm'] * (float) $dims['height_cm'] / VOL_DIVISOR;
        }
        $cod = (float) ($in['cod_amount'] ?? 0);
        if ($cod < 0 || $cod > MAX_COD) { return [null, 'COD amount must be between 0 and ' . number_format(MAX_COD) . '.']; }
        $declared = (float) ($in['declared_value'] ?? 0);
        if ($declared < 0 || $declared > 2000000) { return [null, 'Declared value is out of range.']; }
        $insured = !empty($in['insured']) && $in['insured'] !== '0' && $in['insured'] !== 'false';
        if ($insured && $declared <= 0) { return [null, 'Enter the declared value to insure the parcel.']; }
        $fragile = !empty($in['fragile']) && $in['fragile'] !== '0' && $in['fragile'] !== 'false';

        $rc = db()->prepare('SELECT * FROM rate_cards WHERE service = :s AND zone = :z AND is_active = 1');
        $rc->execute([':s' => $service, ':z' => $zone]);
        $rate = $rc->fetch();
        if (!$rate) { return [null, $service . ' service is not available for ' . strtolower($zone) . ' deliveries.']; }

        $ch = max($w, $vol); $ch = max(0.5, ceil($ch * 2 - 1e-9) / 2);
        $extra = max(0.0, $ch - (float) $rate['base_weight']);
        $freight = self::money((float) $rate['base_price'] + $extra * (float) $rate['per_extra_kg']);
        $fuel = self::money($freight * FUEL_PCT / 100);
        $fragFee = $fragile ? (float) FRAGILE_FEE : 0.0;
        $codFee = $cod > 0 ? self::money(max((float) COD_FEE_MIN, $cod * COD_FEE_PCT / 100)) : 0.0;
        $insFee = $insured ? self::money($declared * INSURANCE_PCT / 100) : 0.0;
        $sub = $freight + $fuel + $fragFee + $codFee + $insFee;
        $tax = self::money($sub * TAX_PCT / 100);
        $total = self::money($sub + $tax);
        return [[
            'service' => $service, 'zone' => $zone, 'weight_kg' => $w, 'volumetric_kg' => round($vol, 2), 'chargeable_kg' => $ch,
            'freight' => $freight, 'fuel' => $fuel, 'fragile_fee' => $fragFee, 'cod_fee' => $codFee, 'insurance_fee' => $insFee,
            'tax' => $tax, 'total_charge' => $total, 'transit_days' => (int) $rate['transit_days'],
            'promised_date' => date('Y-m-d', strtotime('+' . (int) $rate['transit_days'] . ' days')),
            'insured' => $insured ? 1 : 0, 'fragile' => $fragile ? 1 : 0, 'cod_amount' => $cod, 'declared_value' => $declared,
        ], null];
    }
}
