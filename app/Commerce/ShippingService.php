<?php

declare(strict_types=1);

namespace App\Commerce;

use PDO;
use RuntimeException;

final class ShippingService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function zones(): array
    {
        return $this->pdo->query('SELECT z.*, COUNT(m.id) AS method_count FROM shipping_zones z LEFT JOIN shipping_methods m ON m.zone_id = z.id GROUP BY z.id ORDER BY z.country_code, z.region_name, z.name')->fetchAll();
    }

    public function methods(): array
    {
        return $this->pdo->query('SELECT m.*, z.name AS zone_name, z.country_code, z.region_name FROM shipping_methods m INNER JOIN shipping_zones z ON z.id = m.zone_id ORDER BY z.country_code, z.region_name, m.sort_order, m.id')->fetchAll();
    }

    public function quote(int $methodId, string $countryCode, string $regionName): array
    {
        if ($methodId < 1) throw new RuntimeException('Choose a delivery method.');
        $countryCode = strtoupper(trim($countryCode));
        $regionName = trim($regionName);
        if (!preg_match('/^[A-Z]{2}$/', $countryCode) || $regionName === '' || strlen($regionName) > 100) throw new RuntimeException('Enter a valid delivery region and country.');
        $query = $this->pdo->prepare("SELECT m.id, m.name, m.fee_ugx, m.estimated_days_min, m.estimated_days_max, z.name AS zone_name FROM shipping_methods m INNER JOIN shipping_zones z ON z.id = m.zone_id WHERE m.id = :id AND m.is_active = 1 AND z.is_active = 1 AND z.country_code = :country AND (z.region_name = '' OR lower(z.region_name) = lower(:region)) ORDER BY CASE WHEN z.region_name = '' THEN 1 ELSE 0 END LIMIT 1");
        $query->execute(['id' => $methodId, 'country' => $countryCode, 'region' => $regionName]);
        $method = $query->fetch();
        if (!$method) throw new RuntimeException('That delivery method is not available for the selected region.');
        return $method;
    }

    public function saveZone(?int $id, string $name, string $countryCode, string $regionName, bool $active): int
    {
        $name = trim($name);
        $countryCode = strtoupper(trim($countryCode));
        $regionName = trim($regionName);
        if ($name === '' || strlen($name) > 100 || !preg_match('/^[A-Z]{2}$/', $countryCode) || strlen($regionName) > 100) throw new RuntimeException('Enter a valid shipping zone.');
        $now = gmdate('Y-m-d H:i:s');
        if ($id) {
            $statement = $this->pdo->prepare('UPDATE shipping_zones SET name = :name, country_code = :country, region_name = :region, is_active = :active, updated_at = :updated WHERE id = :id');
            $statement->execute(['name'=>$name,'country'=>$countryCode,'region'=>$regionName,'active'=>$active?1:0,'updated'=>$now,'id'=>$id]);
            if ($statement->rowCount() !== 1) throw new RuntimeException('Shipping zone not found.');
            return $id;
        }
        $statement = $this->pdo->prepare('INSERT INTO shipping_zones (name,country_code,region_name,is_active,created_at,updated_at) VALUES (:name,:country,:region,:active,:created,:updated)');
        $statement->execute(['name'=>$name,'country'=>$countryCode,'region'=>$regionName,'active'=>$active?1:0,'created'=>$now,'updated'=>$now]);
        return (int) $this->pdo->lastInsertId();
    }

    public function saveMethod(?int $id, int $zoneId, string $name, string $description, int $fee, int $daysMin, int $daysMax, bool $active): int
    {
        $name = trim($name);
        $description = trim($description);
        if ($zoneId < 1 || $name === '' || strlen($name) > 100 || strlen($description) > 300 || $fee < 0 || $fee > 100000000 || $daysMin < 0 || $daysMax < $daysMin || $daysMax > 90) throw new RuntimeException('Enter valid delivery method details.');
        $zone = $this->pdo->prepare('SELECT 1 FROM shipping_zones WHERE id = :id');
        $zone->execute(['id'=>$zoneId]);
        if (!$zone->fetchColumn()) throw new RuntimeException('Choose an existing shipping zone.');
        $now = gmdate('Y-m-d H:i:s');
        $values=['zone'=>$zoneId,'name'=>$name,'description'=>$description,'fee'=>$fee,'min'=>$daysMin,'max'=>$daysMax,'active'=>$active?1:0,'updated'=>$now];
        if ($id) {
            $statement=$this->pdo->prepare('UPDATE shipping_methods SET zone_id=:zone,name=:name,description=:description,fee_ugx=:fee,estimated_days_min=:min,estimated_days_max=:max,is_active=:active,updated_at=:updated WHERE id=:id');
            $statement->execute($values+['id'=>$id]);
            if ($statement->rowCount() !== 1) throw new RuntimeException('Delivery method not found.');
            return $id;
        }
        $statement=$this->pdo->prepare('INSERT INTO shipping_methods (zone_id,name,description,fee_ugx,estimated_days_min,estimated_days_max,is_active,created_at,updated_at) VALUES (:zone,:name,:description,:fee,:min,:max,:active,:updated,:updated)');
        $statement->execute($values);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateFulfillment(int $orderId, int $adminId, string $status, string $carrier, string $tracking): void
    {
        if ($orderId < 1 || $adminId < 1 || !in_array($status, ['processing','shipped','delivered','cancelled'], true) || strlen(trim($carrier)) > 100 || strlen(trim($tracking)) > 120) throw new RuntimeException('Enter valid fulfilment details.');
        $query=$this->pdo->prepare("SELECT id, fulfillment_status, status FROM orders WHERE id=:id AND fulfillment_status != 'not_required'");
        $query->execute(['id'=>$orderId]);
        $order=$query->fetch();
        if (!$order || !in_array($order['status'], ['paid','partially_refunded'], true)) throw new RuntimeException('Only paid physical orders can be fulfilled.');
        $allowed = ['pending'=>['processing','cancelled'],'processing'=>['processing','shipped','cancelled'],'shipped'=>['shipped','delivered'],'delivered'=>['delivered'],'cancelled'=>['cancelled']];
        if (!in_array($status, $allowed[$order['fulfillment_status']] ?? [], true)) throw new RuntimeException('That fulfilment transition is not allowed.');
        if ($status === 'shipped' && trim($tracking) === '') throw new RuntimeException('A tracking reference is required before marking an order shipped.');
        $now=gmdate('Y-m-d H:i:s');
        $statement=$this->pdo->prepare('UPDATE orders SET fulfillment_status=:status,carrier_name=:carrier,tracking_reference=:tracking,shipped_at=CASE WHEN :status = \'shipped\' THEN COALESCE(shipped_at,:now) ELSE shipped_at END,delivered_at=CASE WHEN :status = \'delivered\' THEN COALESCE(delivered_at,:now) ELSE delivered_at END,updated_at=:now WHERE id=:id');
        $statement->execute(['status'=>$status,'carrier'=>trim($carrier),'tracking'=>trim($tracking),'now'=>$now,'id'=>$orderId]);
    }
}
