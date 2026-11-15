<?php
declare(strict_types=1);

class Category
{
    public static function all(bool $activeOnly = false): array
    {
        $pdo = Database::getConnection();
        $where = $activeOnly ? "WHERE c.status = 'active'" : '';
        $sql = "SELECT c.*, COUNT(s.id) AS service_count
                FROM categories c
                LEFT JOIN services s ON s.category_id = c.id " . ($activeOnly ? "AND s.status = 'active'" : "") . "
                {$where}
                GROUP BY c.id
                ORDER BY c.sort_order ASC, c.id ASC";
        return $pdo->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findOrCreateByName(string $name, string $platform = 'general'): int
    {
        $pdo = Database::getConnection();
        $name = clean_text($name, 120) ?: 'General Services';
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $stmt->execute([$name]);
        if ($id = $stmt->fetchColumn()) {
            return (int)$id;
        }

        $slug = slugify($name);
        $dup = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE slug = ?');
        $dup->execute([$slug]);
        if ((int)$dup->fetchColumn() > 0) {
            $slug .= '-' . bin2hex(random_bytes(2));
        }

        $defType = get_setting('default_margin_type', 'percentage');
        $defVal  = (float)get_setting('default_margin_value', '30');

        $ins = $pdo->prepare(
            'INSERT INTO categories (name, slug, platform, default_margin_type, default_margin_value, status)
             VALUES (?, ?, ?, ?, ?, "active")'
        );
        $ins->execute([$name, $slug, clean_text($platform, 60), $defType, $defVal]);
        return (int)$pdo->lastInsertId();
    }

    public static function create(array $data): array
    {
        $pdo = Database::getConnection();
        $name = clean_text($data['name'] ?? '', 120);
        if ($name === '') {
            return ['ok' => false, 'error' => 'Category name is required.'];
        }
        $slug = slugify($data['slug'] ?? $name);
        $dup = $pdo->prepare('SELECT id FROM categories WHERE slug = ? LIMIT 1');
        $dup->execute([$slug]);
        if ($dup->fetch()) {
            $slug .= '-' . bin2hex(random_bytes(2));
        }

        $marginType = ($data['default_margin_type'] ?? 'percentage') === 'fixed' ? 'fixed' : 'percentage';
        $marginVal  = max(0.0, (float)($data['default_margin_value'] ?? 30));
        $status     = ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        $stmt = $pdo->prepare(
            'INSERT INTO categories (name, slug, platform, description, default_margin_type, default_margin_value, sort_order, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $name,
            $slug,
            clean_text($data['platform'] ?? 'general', 60) ?: 'general',
            clean_text($data['description'] ?? '', 1000),
            $marginType,
            $marginVal,
            (int)($data['sort_order'] ?? 0),
            $status,
        ]);

        return ['ok' => true, 'id' => (int)$pdo->lastInsertId()];
    }

    public static function update(int $id, array $data): array
    {
        $pdo = Database::getConnection();
        $name = clean_text($data['name'] ?? '', 120);
        if ($name === '') {
            return ['ok' => false, 'error' => 'Category name is required.'];
        }
        $marginType = ($data['default_margin_type'] ?? 'percentage') === 'fixed' ? 'fixed' : 'percentage';
        $marginVal  = max(0.0, (float)($data['default_margin_value'] ?? 30));
        $status     = ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        $stmt = $pdo->prepare(
            'UPDATE categories SET name = ?, platform = ?, description = ?, default_margin_type = ?, default_margin_value = ?, sort_order = ?, status = ? WHERE id = ?'
        );
        $stmt->execute([
            $name,
            clean_text($data['platform'] ?? 'general', 60) ?: 'general',
            clean_text($data['description'] ?? '', 1000),
            $marginType,
            $marginVal,
            (int)($data['sort_order'] ?? 0),
            $status,
            $id,
        ]);
        return ['ok' => true];
    }

    public static function bulkDelete(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        if (!$ids) {
            return ['ok' => false, 'error' => 'No categories selected.'];
        }
        $pdo = Database::getConnection();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $check = $pdo->prepare("SELECT COUNT(*) FROM services WHERE category_id IN ($placeholders)");
        $check->execute($ids);
        if ((int)$check->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'Cannot delete categories that still contain services. Move or delete those services first.'];
        }

        $del = $pdo->prepare("DELETE FROM categories WHERE id IN ($placeholders)");
        $del->execute($ids);
        return ['ok' => true, 'affected' => $del->rowCount()];
    }
}
