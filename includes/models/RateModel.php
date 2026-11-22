<?php
declare(strict_types=1);

final class RateModel extends BaseModel
{
    protected static string $table = 'rate_cards';
    protected static array $fields = [
        'service'      => ['label' => 'Service', 'type' => 'enum', 'in' => ['Standard', 'Express', 'Same-day'], 'required' => true],
        'zone'         => ['label' => 'Zone', 'type' => 'enum', 'in' => ['Local', 'Regional', 'National'], 'required' => true],
        'base_price'   => ['label' => 'Base price', 'type' => 'decimal', 'required' => true, 'min' => 1, 'max' => 99999],
        'base_weight'  => ['label' => 'Included weight (kg)', 'type' => 'decimal', 'required' => true, 'min' => 0.5, 'max' => 10],
        'per_extra_kg' => ['label' => 'Per extra kg', 'type' => 'decimal', 'required' => true, 'min' => 0, 'max' => 99999],
        'transit_days' => ['label' => 'Transit days', 'type' => 'int', 'required' => true, 'min' => 0, 'max' => 30],
        'is_active'    => ['label' => 'Active', 'type' => 'bool'],
    ];
    protected static function orderBy(): string { return "ORDER BY FIELD(t.service,'Standard','Express','Same-day'), FIELD(t.zone,'Local','Regional','National')"; }
    public static function validate(array $d, int $id = 0): array
    {
        $err = parent::validate($d, $id);
        if (!$err && isset($d['service'], $d['zone'])) {
            $s = db()->prepare('SELECT 1 FROM rate_cards WHERE service = :s AND zone = :z AND id <> :i'); $s->execute([':s' => $d['service'], ':z' => $d['zone'], ':i' => $id]);
            if ($s->fetchColumn()) { $err['zone'] = 'A rate for this service and zone already exists.'; }
            if ($d['service'] === 'Same-day' && $d['zone'] !== 'Local') { $err['zone'] = 'Same-day is a local service only.'; }
        }
        return $err;
    }
    protected static function afterSave(int $id, array $d, bool $isNew, array $raw): void { Audit::log($isNew ? 'create' : 'update', 'rate', $id, "{$d['service']} / {$d['zone']}"); }
    public static function beforeDelete(int $id): ?string { return 'Rates cannot be deleted because past shipments rely on them. Deactivate instead.'; }
}
