<?php

declare(strict_types=1);

namespace App\Cms;

use PDO;

final class PublicContent
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function courses(): array
    {
        $rows = $this->pdo->query("SELECT * FROM cms_courses WHERE status = 'published' ORDER BY sort_order, id")->fetchAll();
        return array_map(fn (array $row): array => $this->courseShape($row), $rows);
    }

    public function course(string|int|null $identifier): ?array
    {
        if ($identifier === null || $identifier === '') return null;
        $statement = $this->pdo->prepare("SELECT * FROM cms_courses WHERE status = 'published' AND (id = :id_match OR slug = :slug_match) LIMIT 1");
        $statement->execute(['id_match' => ctype_digit((string) $identifier) ? (int) $identifier : -1, 'slug_match' => (string) $identifier]);
        $row = $statement->fetch();
        return $row ? $this->courseShape($row) : null;
    }

    public function products(): array
    {
        $rows = $this->pdo->query("SELECT * FROM cms_products WHERE status = 'published' ORDER BY sort_order, id")->fetchAll();
        return array_map(fn (array $row): array => $this->productShape($row), $rows);
    }

    public function product(string|int|null $identifier): ?array
    {
        if ($identifier === null || $identifier === '') return null;
        $statement = $this->pdo->prepare("SELECT * FROM cms_products WHERE status = 'published' AND (id = :id OR slug = :slug) LIMIT 1");
        $statement->execute(['id' => ctype_digit((string) $identifier) ? (int) $identifier : -1, 'slug' => (string) $identifier]);
        $row = $statement->fetch();
        return $row ? $this->productShape($row) : null;
    }

    public function home(): array
    {
        $statement = $this->pdo->prepare("SELECT * FROM cms_pages WHERE page_key = 'home' AND status = 'published' LIMIT 1");
        $statement->execute();
        $page = $statement->fetch() ?: [];
        return [
            'Hero-Title' => ($page['title'] ?? '') !== '' ? $page['title'] : null,
            'Hero-Subtitle' => ($page['subtitle'] ?? '') !== '' ? $page['subtitle'] : null,
            'Description' => HtmlSanitizer::clean((string) ($page['body_html'] ?? '')),
            'Hero-Image' => $this->image($page['hero_media_id'] ?? null),
            'Title' => ($page['title'] ?? '') !== '' ? $page['title'] : null
        ];
    }

    public function about(): array
    {
        $statement = $this->pdo->prepare("SELECT * FROM cms_pages WHERE page_key = 'about' AND status = 'published' LIMIT 1");
        $statement->execute();
        $page = $statement->fetch() ?: [];
        return [
            'Main-Title' => $page['title'] ?? '',
            'Story-Text' => HtmlSanitizer::clean((string) ($page['body_html'] ?? '')),
            'About-Image' => $this->image($page['hero_media_id'] ?? null)
        ];
    }

    public function contact(): array
    {
        $rows = $this->pdo->query('SELECT setting_key, setting_value FROM cms_settings')->fetchAll();
        $settings = [];
        foreach ($rows as $row) $settings[$row['setting_key']] = $row['setting_value'];
        return [
            'phone' => $settings['contact.phone'] ?? '+256 704 416250',
            'email' => $settings['contact.email'] ?? 'smartstudypro36@gmail.com',
            'address' => $settings['contact.address'] ?? 'Kampala, Uganda'
        ];
    }

    private function courseShape(array $row): array
    {
        return [
            '_id' => (int) $row['id'],
            'Title' => $row['title'],
            'Description' => HtmlSanitizer::clean((string) $row['description_html']),
            'Content' => HtmlSanitizer::clean((string) $row['content_html']),
            'Price' => (int) $row['price_ugx'],
            'Category' => $row['category'],
            'Image' => $this->image($row['image_media_id']),
            'SEO-Title' => $row['seo_title'],
            'SEO-Description' => $row['seo_description'],
            'slug' => $row['slug']
        ];
    }

    private function productShape(array $row): array
    {
        return [
            '_id' => (int) $row['id'],
            'Title' => $row['title'],
            'Description' => $row['description_html'],
            'Price' => (int) $row['price_ugx'],
            'Category' => $row['category'],
            'Image' => $this->image($row['image_media_id']),
            'ProductType' => ucfirst($row['product_type']),
            'ProductFile' => $this->image($row['product_file_media_id']),
            'SEO-Title' => $row['title'] . ' | SmartStudyPro Store',
            'SEO-Description' => substr(strip_tags($row['description_html']), 0, 160),
            'slug' => $row['slug']
        ];
    }

    private function image(mixed $id): ?array
    {
        if (!$id) return null;
        $statement = $this->pdo->prepare("SELECT id, mime_type FROM media_assets WHERE id = :id AND visibility = 'public' LIMIT 1");
        $statement->execute(['id' => (int) $id]);
        $asset = $statement->fetch();
        if (!$asset || !str_starts_with($asset['mime_type'], 'image/')) return null;
        return ['url' => 'media.php?id=' . (int) $asset['id']];
    }
}