<?php

declare(strict_types=1);

namespace App\Cms;

use PDO;
use RuntimeException;

final class MediaManager
{
    private const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/rtf' => 'rtf',
        'text/rtf' => 'rtf',
        'application/vnd.oasis.opendocument.text' => 'odt',
        'video/mp4' => 'mp4',
        'audio/mpeg' => 'mp3'
    ];

    public function __construct(private readonly PDO $pdo, private readonly string $storagePath)
    {
    }

    public function upload(array $file, int $userId, string $altText, string $visibility): int
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) throw new RuntimeException('Choose a valid file to upload.');
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > 52428800) throw new RuntimeException('Files must be between 1 byte and 50 MB.');
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file((string) $file['tmp_name']);
        if (!isset(self::TYPES[$mime])) throw new RuntimeException('This file type is not supported.');
        $year = gmdate('Y');
        $month = gmdate('m');
        $directory = rtrim($this->storagePath, DIRECTORY_SEPARATOR) . '/media/' . $year . '/' . $month;
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('The private media directory could not be created.');
        $name = bin2hex(random_bytes(20)) . '.' . self::TYPES[$mime];
        $destination = $directory . '/' . $name;
        if (!move_uploaded_file((string) $file['tmp_name'], $destination)) throw new RuntimeException('The uploaded file could not be stored.');
        chmod($destination, 0600);
        $relative = 'media/' . $year . '/' . $month . '/' . $name;
        $statement = $this->pdo->prepare('INSERT INTO media_assets (storage_path, original_name, mime_type, file_size, checksum_sha256, alt_text, visibility, uploaded_by, created_at) VALUES (:storage_path, :original_name, :mime_type, :file_size, :checksum_sha256, :alt_text, :visibility, :uploaded_by, :created_at)');
        $statement->execute([
            'storage_path' => $relative,
            'original_name' => basename((string) ($file['name'] ?? 'upload')),
            'mime_type' => $mime,
            'file_size' => $size,
            'checksum_sha256' => hash_file('sha256', $destination),
            'alt_text' => trim($altText),
            'visibility' => $visibility === 'public' && str_starts_with($mime, 'image/') ? 'public' : 'private',
            'uploaded_by' => $userId,
            'created_at' => gmdate('Y-m-d H:i:s')
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateMetadata(int $id, string $altText, string $visibility): void
    {
        $query = $this->pdo->prepare('SELECT mime_type FROM media_assets WHERE id = :id LIMIT 1');
        $query->execute(['id' => $id]);
        $asset = $query->fetch();
        if (!$asset) throw new RuntimeException('This media item no longer exists.');
        if (!in_array($visibility, ['private', 'public'], true)) throw new RuntimeException('Choose a valid visibility setting.');
        if ($visibility === 'public' && !str_starts_with((string) $asset['mime_type'], 'image/')) throw new RuntimeException('Only image files can be made public.');
        $update = $this->pdo->prepare('UPDATE media_assets SET alt_text = :alt_text, visibility = :visibility WHERE id = :id');
        $update->execute(['alt_text' => mb_substr(trim($altText), 0, 240), 'visibility' => $visibility, 'id' => $id]);
    }

    public function all(): array
    {
        return $this->pdo->query('SELECT m.id, m.original_name, m.mime_type, m.file_size, m.alt_text, m.visibility, m.created_at, u.name AS uploader_name FROM media_assets m LEFT JOIN users u ON u.id = m.uploaded_by ORDER BY m.id DESC')->fetchAll();
    }
}