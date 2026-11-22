<?php
// Recompute every stored shipment through Pricing::quote and compare. Usage: php tests/check_seed_pricing.php
require_once dirname(__DIR__) . '/includes/functions.php';
$bad = 0; $n = 0;
foreach (db()->query('SELECT * FROM shipments')->fetchAll() as $s) {
    [$q, $e] = Pricing::quote(['service' => $s['service'], 'origin_branch_id' => $s['origin_branch_id'], 'dest_branch_id' => $s['dest_branch_id'], 'weight_kg' => $s['weight_kg'],
        'length_cm' => $s['length_cm'], 'width_cm' => $s['width_cm'], 'height_cm' => $s['height_cm'], 'fragile' => $s['fragile'], 'cod_amount' => $s['cod_amount'], 'declared_value' => $s['declared_value'], 'insured' => $s['insured']]);
    $n++;
    if (!$q) { $bad++; echo "{$s['tracking_no']}: $e\n"; continue; }
    foreach (['freight', 'fuel', 'fragile_fee', 'cod_fee', 'insurance_fee', 'tax', 'total_charge', 'chargeable_kg'] as $k) {
        if (abs((float) $q[$k] - (float) $s[$k]) > 0.004) { $bad++; echo "{$s['tracking_no']} $k: stored {$s[$k]} vs {$q[$k]}\n"; break; }
    }
}
echo "$n shipments checked, $bad mismatches\n"; exit($bad ? 1 : 0);
