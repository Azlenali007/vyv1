<?php
declare(strict_types=1);

class Service
{
    public static function calculateSellingPrice(float $providerRate, string $marginType, float $marginValue): float
    {
        $providerRate = max(0.0, $providerRate);
        $marginValue  = max(0.0, $marginValue);

        if ($marginType === 'fixed') {
            return round($providerRate + $marginValue, 4);
        }
        return round($providerRate * (1 + ($marginValue / 100)), 4);
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT s.*, c.name AS category_name, c.platform AS category_platform
             FROM services s
             INNER JOIN categories c ON c.id = s.category_id
             WHERE s.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function search(string $query = '', int $categoryId = 0, bool $activeOnly = true): array
    {
        $pdo = Database::getConnection();
        $conditions = [];
        $params = [];

        if ($activeOnly) {
            $conditions[] = "s.status = 'active'";
            $conditions[] = "c.status = 'active'";
        }
        if ($categoryId > 0) {
            $conditions[] = 's.category_id = ?';
            $params[] = $categoryId;
        }
        if (trim($query) !== '') {
            $q = '%' . trim($query) . '%';
            $conditions[] = '(s.name LIKE ? OR s.description LIKE ? OR c.name LIKE ? OR CAST(s.id AS CHAR) = ?)';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = trim($query);
        }

        $whereSql = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
        $sql = "SELECT s.*, c.name AS category_name, c.platform AS category_platform, p.name AS provider_name
                FROM services s
                INNER JOIN categories c ON c.id = s.category_id
                LEFT JOIN providers p ON p.id = s.provider_id
                {$whereSql}
                ORDER BY c.sort_order ASC, c.id ASC, s.sort_order ASC, s.id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function create(array $data): array
    {
        $pdo = Database::getConnection();
        $categoryId = (int)($data['category_id'] ?? 0);
        $name       = clean_text($data['name'] ?? '', 255);
        if ($categoryId <= 0 || $name === '') {
            return ['ok' => false, 'error' => 'Category and service name are required.'];
        }

        $providerId        = !empty($data['provider_id']) ? (int)$data['provider_id'] : null;
        $providerServiceId = clean_text($data['provider_service_id'] ?? '', 80) ?: null;
        $providerRate      = max(0.0, (float)($data['provider_rate'] ?? 0));
        $marginType        = ($data['margin_type'] ?? 'percentage') === 'fixed' ? 'fixed' : 'percentage';
        $marginValue       = max(0.0, (float)($data['margin_value'] ?? 30));

        $sellingRate = !empty($data['manual_rate_override']) && (float)$data['rate_per_1000'] > 0
            ? round((float)$data['rate_per_1000'], 4)
            : self::calculateSellingPrice($providerRate, $marginType, $marginValue);

        if ($sellingRate <= 0 && isset($data['rate_per_1000']) && (float)$data['rate_per_1000'] > 0) {
            $sellingRate = round((float)$data['rate_per_1000'], 4);
        }

        $minQty = max(1, (int)($data['min_qty'] ?? 10));
        $maxQty = max($minQty, (int)($data['max_qty'] ?? 100000));
        $rawType = $data['service_type'] ?? 'default';
        $type    = in_array($rawType, ['default', 'custom_comments', 'package', 'drip_feed'], true)
            ? $rawType : 'default';
        $status = ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        $stmt = $pdo->prepare(
            'INSERT INTO services
             (category_id, provider_id, provider_service_id, name, description, service_type, provider_rate, margin_type, margin_value, rate_per_1000, min_qty, max_qty, refill_supported, cancel_supported, dripfeed_supported, average_time, sort_order, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $categoryId,
            $providerId,
            $providerServiceId,
            $name,
            clean_text($data['description'] ?? '', 3000),
            $type,
            $providerRate,
            $marginType,
            $marginValue,
            $sellingRate,
            $minQty,
            $maxQty,
            !empty($data['refill_supported']) ? 1 : 0,
            !empty($data['cancel_supported']) ? 1 : 0,
            !empty($data['dripfeed_supported']) ? 1 : 0,
            clean_text($data['average_time'] ?? '', 80) ?: null,
            (int)($data['sort_order'] ?? 0),
            $status,
        ]);

        return ['ok' => true, 'id' => (int)$pdo->lastInsertId()];
    }

    public static function update(int $id, array $data): array
    {
        $pdo = Database::getConnection();
        $categoryId = (int)($data['category_id'] ?? 0);
        $name       = clean_text($data['name'] ?? '', 255);
        if ($categoryId <= 0 || $name === '') {
            return ['ok' => false, 'error' => 'Category and service name are required.'];
        }

        $providerId        = !empty($data['provider_id']) ? (int)$data['provider_id'] : null;
        $providerServiceId = clean_text($data['provider_service_id'] ?? '', 80) ?: null;
        $providerRate      = max(0.0, (float)($data['provider_rate'] ?? 0));
        $marginType        = ($data['margin_type'] ?? 'percentage') === 'fixed' ? 'fixed' : 'percentage';
        $marginValue       = max(0.0, (float)($data['margin_value'] ?? 30));

        $sellingRate = self::calculateSellingPrice($providerRate, $marginType, $marginValue);
        if ($providerRate <= 0 && !empty($data['rate_per_1000'])) {
            $sellingRate = max(0.0001, round((float)$data['rate_per_1000'], 4));
        }

        $minQty = max(1, (int)($data['min_qty'] ?? 10));
        $maxQty = max($minQty, (int)($data['max_qty'] ?? 100000));
        $rawType = $data['service_type'] ?? 'default';
        $type    = in_array($rawType, ['default', 'custom_comments', 'package', 'drip_feed'], true)
            ? $rawType : 'default';
        $status = ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        $stmt = $pdo->prepare(
            'UPDATE services SET
             category_id = ?, provider_id = ?, provider_service_id = ?, name = ?, description = ?, service_type = ?,
             provider_rate = ?, margin_type = ?, margin_value = ?, rate_per_1000 = ?, min_qty = ?, max_qty = ?,
             refill_supported = ?, cancel_supported = ?, dripfeed_supported = ?, average_time = ?, sort_order = ?, status = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $categoryId,
            $providerId,
            $providerServiceId,
            $name,
            clean_text($data['description'] ?? '', 3000),
            $type,
            $providerRate,
            $marginType,
            $marginValue,
            $sellingRate,
            $minQty,
            $maxQty,
            !empty($data['refill_supported']) ? 1 : 0,
            !empty($data['cancel_supported']) ? 1 : 0,
            !empty($data['dripfeed_supported']) ? 1 : 0,
            clean_text($data['average_time'] ?? '', 80) ?: null,
            (int)($data['sort_order'] ?? 0),
            $status,
            $id,
        ]);

        return ['ok' => true];
    }

    public static function bulkAction(string $action, array $ids, array $params = []): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        if (!$ids) {
            return ['ok' => false, 'error' => 'Please select at least one service.'];
        }
        $pdo = Database::getConnection();
        $ph = implode(',', array_fill(0, count($ids), '?'));

        switch ($action) {
            case 'enable':
                $stmt = $pdo->prepare("UPDATE services SET status = 'active' WHERE id IN ($ph)");
                $stmt->execute($ids);
                return ['ok' => true, 'affected' => $stmt->rowCount(), 'message' => 'Selected services enabled.'];

            case 'disable':
                $stmt = $pdo->prepare("UPDATE services SET status = 'inactive' WHERE id IN ($ph)");
                $stmt->execute($ids);
                return ['ok' => true, 'affected' => $stmt->rowCount(), 'message' => 'Selected services disabled.'];

            case 'delete':
                $chk = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE service_id IN ($ph)");
                $chk->execute($ids);
                if ((int)$chk->fetchColumn() > 0) {
                    return ['ok' => false, 'error' => 'Some selected services have existing orders and cannot be deleted. Disable them instead.'];
                }
                $stmt = $pdo->prepare("DELETE FROM services WHERE id IN ($ph)");
                $stmt->execute($ids);
                return ['ok' => true, 'affected' => $stmt->rowCount(), 'message' => 'Selected services deleted.'];

            case 'assign_category':
                $catId = (int)($params['category_id'] ?? 0);
                if ($catId <= 0) {
                    return ['ok' => false, 'error' => 'Please choose a target category.'];
                }
                $stmt = $pdo->prepare("UPDATE services SET category_id = ? WHERE id IN ($ph)");
                $stmt->execute(array_merge([$catId], $ids));
                return ['ok' => true, 'affected' => $stmt->rowCount(), 'message' => 'Category assigned to selected services.'];

            case 'assign_provider':
                $provId = !empty($params['provider_id']) ? (int)$params['provider_id'] : null;
                $stmt = $pdo->prepare("UPDATE services SET provider_id = ? WHERE id IN ($ph)");
                $stmt->execute(array_merge([$provId], $ids));
                return ['ok' => true, 'affected' => $stmt->rowCount(), 'message' => 'Provider updated for selected services.'];

            case 'adjust_margin':
                $mType = ($params['margin_type'] ?? 'percentage') === 'fixed' ? 'fixed' : 'percentage';
                $mVal  = max(0.0, (float)($params['margin_value'] ?? 0));
                $sel = $pdo->prepare("SELECT id, provider_rate, rate_per_1000 FROM services WHERE id IN ($ph)");
                $sel->execute($ids);
                $upd = $pdo->prepare('UPDATE services SET margin_type = ?, margin_value = ?, rate_per_1000 = ? WHERE id = ?');
                $count = 0;
                foreach ($sel->fetchAll() as $svc) {
                    $baseRate = (float)$svc['provider_rate'] > 0 ? (float)$svc['provider_rate'] : (float)$svc['rate_per_1000'];
                    $newSell = self::calculateSellingPrice($baseRate, $mType, $mVal);
                    $upd->execute([$mType, $mVal, $newSell, $svc['id']]);
                    $count++;
                }
                return ['ok' => true, 'affected' => $count, 'message' => 'Margin and selling prices updated.'];

            default:
                return ['ok' => false, 'error' => 'Unknown bulk action.'];
        }
    }
}
