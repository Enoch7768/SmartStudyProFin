<?php

declare(strict_types=1);

namespace App\Cms;

use PDO;
use RuntimeException;

final class ContentRepository
{
    private const TABLES = [
        'courses' => ['cms_courses', ['title', 'slug', 'description_html', 'content_html', 'price_ugx', 'category', 'image_media_id', 'seo_title', 'seo_description', 'status', 'sort_order']],
        'chapters' => ['cms_chapters', ['course_id', 'title', 'sort_order']],
        'lessons' => ['cms_lessons', ['chapter_id', 'title', 'content_html', 'video_url', 'video_media_id', 'resource_media_id', 'resource_title', 'quiz_time_limit_minutes', 'quiz_pass_mark', 'sort_order', 'status']],
        'questions' => ['cms_quiz_questions', ['lesson_id', 'content', 'question', 'options_json', 'correct_answer', 'sort_order']],
        'products' => ['cms_products', ['title', 'slug', 'description_html', 'price_ugx', 'read_price_ugx', 'download_price_ugx', 'stock_quantity', 'category', 'image_media_id', 'product_type', 'product_file_media_id', 'status', 'sort_order']]
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(string $type): array
    {
        [$table] = $this->definition($type);
        $order = match ($type) {
            'chapters' => 'course_id, sort_order, id',
            'lessons' => 'chapter_id, sort_order, id',
            'questions' => 'lesson_id, sort_order, id',
            default => 'sort_order, id'
        };
        return $this->pdo->query("SELECT * FROM {$table} ORDER BY {$order}")->fetchAll();
    }

    public function find(string $type, int $id): ?array
    {
        [$table] = $this->definition($type);
        $statement = $this->pdo->prepare("SELECT * FROM {$table} WHERE id = :id LIMIT 1");
        $statement->execute(['id' => $id]);
        return $statement->fetch() ?: null;
    }

    public function save(string $type, ?int $id, array $input): int
    {
        [$table, $allowed] = $this->definition($type);
        $data = [];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $input)) continue;
            $value = $input[$field];
            if (in_array($field, ['description_html', 'content_html', 'body_html'], true)) $value = HtmlSanitizer::clean((string) $value);
            if (in_array($field, ['price_ugx', 'read_price_ugx', 'download_price_ugx', 'stock_quantity', 'sort_order', 'course_id', 'chapter_id', 'lesson_id', 'image_media_id', 'video_media_id', 'product_file_media_id', 'resource_media_id', 'quiz_time_limit_minutes', 'quiz_pass_mark'], true)) {
                $value = $value === '' || $value === null ? null : filter_var($value, FILTER_VALIDATE_INT);
                if ($value === false || ($value !== null && $value < 0)) throw new RuntimeException('A numeric field contains an invalid value.');
            }
            if (in_array($field, ['title', 'question'], true) && trim((string) $value) === '') throw new RuntimeException('Title and question fields cannot be empty.');
            if ($field === 'slug') $value = $this->slug((string) $value);
            if (in_array($field, ['price_ugx','read_price_ugx','download_price_ugx'], true) && $value === null) $value = 0;
            if ($field === 'sort_order' && $value === null) $value = 0;
            if ($field === 'quiz_time_limit_minutes' && $value === null) $value = 0;
            if ($field === 'quiz_pass_mark' && $value === null) $value = 70;
            if ($field === 'quiz_time_limit_minutes' && $value > 180) throw new RuntimeException('Quiz time limit cannot exceed 180 minutes.');
            if ($field === 'quiz_pass_mark' && ($value < 1 || $value > 100)) throw new RuntimeException('Quiz pass mark must be between 1 and 100.');
            if ($field === 'options_json') {
                $options = is_array($value) ? $value : json_decode((string) $value, true);
                if (!is_array($options) || count($options) < 2 || count($options) > 10) throw new RuntimeException('A quiz question needs between 2 and 10 options.');
                $options = array_values(array_filter(array_map(static fn ($item) => trim((string) $item), $options), static fn ($item) => $item !== ''));
                if (count($options) < 2) throw new RuntimeException('A quiz question needs at least two non-empty options.');
                $value = json_encode($options, JSON_THROW_ON_ERROR);
            }
            $data[$field] = $value;
        }
        if ($type === 'products' && array_key_exists('stock_quantity', $data) && $data['stock_quantity'] !== null && $id !== null) {
            $existingStock = $this->find('products', $id);
            if ($existingStock && (int) ($data['stock_quantity']) < (int) ($existingStock['stock_reserved'] ?? 0)) throw new RuntimeException('Stock quantity cannot be lower than units already reserved for pending orders.');
        }
        if ($type === 'products' && ($data['product_type'] ?? ($id !== null ? ($this->find('products', $id)['product_type'] ?? 'physical') : 'physical')) === 'online') {
            $existingProduct = $id === null ? [] : ($this->find('products', $id) ?? []);
            foreach (['read_price_ugx', 'download_price_ugx'] as $priceField) {
                if (!array_key_exists($priceField, $data)) $data[$priceField] = (int) ($existingProduct[$priceField] ?? $data['price_ugx'] ?? 0);
            }
        }
        if ($type === 'questions') {
            $existing = $id === null ? [] : ($this->find('questions', $id) ?? []);
            $optionsJson = $data['options_json'] ?? $existing['options_json'] ?? null;
            $correctAnswer = trim((string) ($data['correct_answer'] ?? $existing['correct_answer'] ?? ''));
            $options = is_string($optionsJson) ? json_decode($optionsJson, true) : null;
            if (!is_array($options) || count($options) < 2 || $correctAnswer === '' || !in_array($correctAnswer, $options, true)) throw new RuntimeException('The correct answer must match one of the question options exactly.');
        }
        if (array_key_exists('image_media_id', $data) && $data['image_media_id'] !== null) $this->assertPublicImage((int) $data['image_media_id']);
        if (array_key_exists('video_media_id', $data) && $data['video_media_id'] !== null) $this->assertPrivateMedia((int) $data['video_media_id'], 'video/mp4');
        if (array_key_exists('resource_media_id', $data) && $data['resource_media_id'] !== null) $this->assertPrivateMedia((int) $data['resource_media_id'], 'application/pdf');
        if ($type === 'courses' || $type === 'products') {
            $data['slug'] = $this->uniqueSlug($table, (string) ($data['slug'] ?? $data['title'] ?? ''), $id);
        }
        $now = gmdate('Y-m-d H:i:s');
        if ($id !== null) {
            if (!$this->find($type, $id)) throw new RuntimeException('The requested content item does not exist.');
            $data['updated_at'] = $now;
            $sets = implode(', ', array_map(static fn ($field) => "{$field} = :{$field}", array_keys($data)));
            $data['id'] = $id;
            $statement = $this->pdo->prepare("UPDATE {$table} SET {$sets} WHERE id = :id");
            $statement->execute($data);
            return $id;
        }
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        $columns = array_keys($data);
        $statement = $this->pdo->prepare("INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (:" . implode(', :', $columns) . ")");
        $statement->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function delete(string $type, int $id): void
    {
        [$table] = $this->definition($type);
        $statement = $this->pdo->prepare("DELETE FROM {$table} WHERE id = :id");
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() !== 1) throw new RuntimeException('The requested content item was not found.');
    }

    public function page(string $key): array
    {
        if (!in_array($key, ['home', 'about'], true)) throw new RuntimeException('Unknown page.');
        $statement = $this->pdo->prepare('SELECT * FROM cms_pages WHERE page_key = :page_key');
        $statement->execute(['page_key' => $key]);
        return $statement->fetch() ?: [];
    }

    public function savePage(string $key, array $input): void
    {
        if (!in_array($key, ['home', 'about'], true)) throw new RuntimeException('Unknown page.');
        $heroMediaId = ($input['hero_media_id'] ?? '') === '' ? null : filter_var($input['hero_media_id'], FILTER_VALIDATE_INT);
        if ($heroMediaId === false || ($heroMediaId !== null && $heroMediaId < 1)) throw new RuntimeException('Choose a valid public hero image.');
        if ($heroMediaId !== null) $this->assertPublicImage($heroMediaId);
        $statement = $this->pdo->prepare('UPDATE cms_pages SET title = :title, subtitle = :subtitle, body_html = :body_html, hero_media_id = :hero_media_id, status = :status, updated_at = :updated_at WHERE page_key = :page_key');
        $statement->execute([
            'title' => trim((string) ($input['title'] ?? '')),
            'subtitle' => trim((string) ($input['subtitle'] ?? '')),
            'body_html' => HtmlSanitizer::clean((string) ($input['body_html'] ?? '')),
            'hero_media_id' => $heroMediaId,
            'status' => ($input['status'] ?? 'published') === 'draft' ? 'draft' : 'published',
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'page_key' => $key
        ]);
    }

    public function settings(): array
    {
        $rows = $this->pdo->query('SELECT setting_key, setting_value FROM cms_settings')->fetchAll();
        $settings = [];
        foreach ($rows as $row) $settings[$row['setting_key']] = $row['setting_value'];
        return $settings;
    }

    public function saveSettings(array $input): void
    {
        $allowed = ['contact.phone', 'contact.email', 'contact.address', 'site.favicon_media_id'];
        $values = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $input)) continue;
            $value = trim((string) $input[$key]);
            if ($key === 'contact.email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid contact email address.');
            if ($key === 'site.favicon_media_id' && $value !== '') {
                if (!ctype_digit($value) || (int) $value < 1) throw new RuntimeException('Choose a valid public favicon image.');
                $asset = $this->pdo->prepare("SELECT id FROM media_assets WHERE id = :id AND visibility = 'public' AND mime_type IN ('image/jpeg', 'image/png', 'image/webp', 'image/gif') LIMIT 1");
                $asset->execute(['id' => (int) $value]);
                if (!$asset->fetchColumn()) throw new RuntimeException('The favicon must be an uploaded public image.');
            }
            $values[$key] = $value;
        }
        $statement = $this->pdo->prepare('INSERT INTO cms_settings (setting_key, setting_value, updated_at) VALUES (:setting_key, :setting_value, :updated_at) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at');
        $updatedAt = gmdate('Y-m-d H:i:s');
        foreach ($values as $key => $value) $statement->execute(['setting_key' => $key, 'setting_value' => $value, 'updated_at' => $updatedAt]);
    }

    private function assertPublicImage(int $mediaId): void
    {
        $statement = $this->pdo->prepare("SELECT 1 FROM media_assets WHERE id = :id AND visibility = 'public' AND mime_type IN ('image/jpeg', 'image/png', 'image/webp', 'image/gif') LIMIT 1");
        $statement->execute(['id' => $mediaId]);
        if (!$statement->fetchColumn()) throw new RuntimeException('Choose an uploaded public image.');
    }

    private function assertPrivateMedia(int $mediaId, string $mimeType): void
    {
        $statement = $this->pdo->prepare("SELECT 1 FROM media_assets WHERE id = :id AND visibility = 'private' AND mime_type = :mime_type LIMIT 1");
        $statement->execute(['id' => $mediaId, 'mime_type' => $mimeType]);
        if (!$statement->fetchColumn()) throw new RuntimeException('Choose a private lesson file with the required format.');
    }

    private function definition(string $type): array
    {
        if (!isset(self::TABLES[$type])) throw new RuntimeException('Unsupported content type.');
        return self::TABLES[$type];
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-') ?: 'item';
    }

    private function uniqueSlug(string $table, string $slug, ?int $id): string
    {
        $base = $this->slug($slug);
        $candidate = $base;
        $suffix = 2;
        while (true) {
            $sql = "SELECT id FROM {$table} WHERE slug = :slug";
            $params = ['slug' => $candidate];
            if ($id !== null) {
                $sql .= ' AND id != :id';
                $params['id'] = $id;
            }
            $statement = $this->pdo->prepare($sql . ' LIMIT 1');
            $statement->execute($params);
            if (!$statement->fetchColumn()) return $candidate;
            $candidate = $base . '-' . $suffix++;
        }
    }
}