<?php

declare(strict_types=1);

namespace App\Release;

final class UpdateChecker
{
    private const RELEASES_URL = 'https://api.github.com/repos/Enoch7768/SmartStudyProFin/releases/latest';
    private const RELEASE_PAGE_PREFIX = 'https://github.com/Enoch7768/SmartStudyProFin/releases/tag/';

    public function __construct(private readonly string $currentVersion)
    {
    }

    public function latest(): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/vnd.github+json\r\nUser-Agent: SmartStudyPro-UpdateChecker\r\n",
                'timeout' => 5,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $response = @file_get_contents(self::RELEASES_URL, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $match)) $status = (int) $match[1];
        if ($response === false || $status !== 200 || strlen($response) > 262144) return ['available' => false, 'error' => 'The latest release could not be checked. Try again later.'];
        $release = json_decode($response, true);
        if (!is_array($release)) return ['available' => false, 'error' => 'The release service returned an invalid response.'];
        $tag = (string) ($release['tag_name'] ?? '');
        $version = ltrim($tag, 'vV');
        if (preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version) !== 1) return ['available' => false, 'error' => 'The latest release tag is not a supported semantic version.'];
        $releaseUrl = (string) ($release['html_url'] ?? '');
        if (!str_starts_with($releaseUrl, self::RELEASE_PAGE_PREFIX)) $releaseUrl = self::RELEASE_PAGE_PREFIX . rawurlencode($tag);
        $body = trim((string) ($release['body'] ?? ''));
        if (strlen($body) > 5000) $body = substr($body, 0, 5000);
        return [
            'available' => true,
            'current_version' => $this->currentVersion,
            'latest_version' => $version,
            'update_available' => version_compare($version, $this->currentVersion, '>'),
            'release_url' => $releaseUrl,
            'published_at' => (string) ($release['published_at'] ?? ''),
            'notes' => $body,
        ];
    }
}
