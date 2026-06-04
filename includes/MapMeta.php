<?php
declare(strict_types=1);

namespace MapStudio;

/**
 * Owns the structured post meta payload for each Map Studio record.
 * WordPress sanitizers are used in production, with a small fallback for CLI contracts.
 */
final class MapMeta {
    public const META_KEY = '_map_studio_payload';

    /**
     * @var array<string, string>
     */
    private const DEFAULT_COLORS = [
        'inactive' => '#d1d5db',
        'active' => '#374151',
        'hover' => '#6b7280',
        'stroke' => '#ffffff',
        'bubbleBackground' => '#ffffff',
        'bubbleText' => '#111827',
    ];

    /**
     * @return array{mapId: string, regions: array<string, string>, colors: array<string, string>}
     */
    public static function defaultPayload(): array {
        return [
            'mapId' => '',
            'regions' => [],
            'colors' => self::DEFAULT_COLORS,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{mapId: string, regions: array<string, string>, colors: array<string, string>}
     */
    public static function sanitizePayload(array $payload, string $lockedMapId = '', ?MapDefinition $mapDefinition = null): array {
        $sanitized = self::defaultPayload();
        $postedMapId = isset($payload['mapId']) && is_scalar($payload['mapId']) ? (string) $payload['mapId'] : '';
        $mapId = self::sanitizeMapId($lockedMapId !== '' ? $lockedMapId : $postedMapId);

        if ($mapDefinition !== null && $mapId === '') {
            $mapId = $mapDefinition->id();
        }

        $sanitized['mapId'] = $mapId;
        $regions = isset($payload['regions']) && is_array($payload['regions']) ? $payload['regions'] : [];

        foreach ($regions as $regionKey => $content) {
            if (!is_string($regionKey) || ($mapDefinition !== null && !$mapDefinition->hasShape($regionKey))) {
                continue;
            }

            if (!is_scalar($content)) {
                continue;
            }

            $trimmedContent = trim((string) $content);

            if ($trimmedContent === '') {
                continue;
            }

            $cleanContent = trim(self::sanitizeEditorHtml($trimmedContent));

            if ($cleanContent !== '') {
                $sanitized['regions'][$regionKey] = $cleanContent;
            }
        }

        $colors = isset($payload['colors']) && is_array($payload['colors']) ? $payload['colors'] : [];

        foreach (self::DEFAULT_COLORS as $key => $default) {
            $value = isset($colors[$key]) && is_scalar($colors[$key]) ? (string) $colors[$key] : $default;
            $sanitized['colors'][$key] = self::sanitizeHexColor($value, $default);
        }

        return $sanitized;
    }

    public static function sanitizeHexColor(string $value, string $fallback): string {
        $normalized = strtolower(trim($value));

        if (preg_match('/^#[0-9a-f]{6}$/', $normalized) === 1) {
            return $normalized;
        }

        $fallback = strtolower(trim($fallback));

        if (preg_match('/^#[0-9a-f]{6}$/', $fallback) === 1) {
            return $fallback;
        }

        return '#000000';
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    public static function activeRegions(array $payload, ?MapDefinition $mapDefinition = null): array {
        $mapId = isset($payload['mapId']) && is_scalar($payload['mapId']) ? (string) $payload['mapId'] : '';

        return self::sanitizePayload($payload, $mapId, $mapDefinition)['regions'];
    }

    /**
     * @return array{mapId: string, regions: array<string, string>, colors: array<string, string>}
     */
    public static function get(int $postId, ?MapDefinition $mapDefinition = null): array {
        if (!function_exists('get_post_meta')) {
            return self::defaultPayload();
        }

        $payload = \get_post_meta($postId, self::META_KEY, true);

        if (is_string($payload)) {
            $decodedPayload = json_decode($payload, true);
            $payload = is_array($decodedPayload) ? $decodedPayload : $payload;
        }

        if (!is_array($payload)) {
            return self::defaultPayload();
        }

        $mapId = isset($payload['mapId']) && is_scalar($payload['mapId']) ? (string) $payload['mapId'] : '';

        return self::sanitizePayload($payload, $mapId, $mapDefinition);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function save(int $postId, array $payload, string $lockedMapId = '', ?MapDefinition $mapDefinition = null): void {
        if (!function_exists('update_post_meta')) {
            return;
        }

        $sanitized = self::sanitizePayload($payload, $lockedMapId, $mapDefinition);

        if ($sanitized === self::defaultPayload() && function_exists('delete_post_meta')) {
            \delete_post_meta($postId, self::META_KEY);
            return;
        }

        \update_post_meta($postId, self::META_KEY, $sanitized);
    }

    private static function sanitizeMapId(string $mapId): string {
        $mapId = strtoupper(trim($mapId));

        return preg_match('/^[A-Z0-9_-]+$/', $mapId) === 1 ? $mapId : '';
    }

    private static function sanitizeEditorHtml(string $content): string {
        if (function_exists('wp_kses_post')) {
            return \wp_kses_post($content);
        }

        $allowedTags = '<p><br><strong><em><b><i><u><a><ul><ol><li><img><figure><figcaption><h1><h2><h3><h4><h5><h6><blockquote>';
        $content = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $content) ?? '';
        $content = strip_tags($content, $allowedTags);
        $content = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $content) ?? '';

        return preg_replace('/\s+(href|src)\s*=\s*([\'"])\s*javascript:[^\2]*\2/i', '', $content) ?? '';
    }
}
