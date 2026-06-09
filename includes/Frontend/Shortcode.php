<?php
declare(strict_types=1);

namespace MapStudio\Frontend;

use MapStudio\Admin\MapPostType;
use MapStudio\MapDefinition;
use MapStudio\MapMeta;
use MapStudio\MapRegistry;
use MapStudio\SvgMap;

/**
 * Renders public Map Studio shortcodes and enqueues frontend assets on demand.
 * Each shortcode instance receives unique SVG IDs and scoped color variables.
 */
final class Shortcode {
    private static int $instanceCounter = 0;

    public function register(): void {
        if (!function_exists('add_shortcode')) {
            return;
        }

        \add_shortcode('map_studio', [$this, 'render']);
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function render(array|string $atts): string {
        $atts = \shortcode_atts(
            ['id' => 0],
            is_array($atts) ? $atts : [],
            'map_studio'
        );
        $mapId = \absint($atts['id'] ?? 0);

        if ($mapId <= 0) {
            return $this->invalidShortcodeOutput();
        }

        $post = \get_post($mapId);

        if (
            !is_object($post) ||
            ($post->post_type ?? '') !== MapPostType::POST_TYPE ||
            ($post->post_status ?? '') !== 'publish'
        ) {
            return $this->invalidShortcodeOutput();
        }

        $registry = new MapRegistry(MAP_STUDIO_PATH . 'assets/maps');
        $payload = MapMeta::get($mapId);
        $mapDefinition = $payload['mapId'] !== '' ? $registry->get($payload['mapId']) : null;

        if ($mapDefinition === null) {
            return $this->invalidShortcodeOutput();
        }

        $payload = MapMeta::sanitizePayload($payload, $mapDefinition->id(), $mapDefinition);
        $activeRegions = MapMeta::activeRegions($payload, $mapDefinition);
        $instanceId = $this->nextInstanceId($mapId);
        $instanceClass = 'map-studio--instance-' . $instanceId;
        $hasRegionList = (bool) $payload['regionListEnabled'] && $activeRegions !== [];
        $classes = ['map-studio', $instanceClass];

        if ($hasRegionList) {
            $classes[] = 'has-region-list';
            $classes[] = 'is-region-list-' . $payload['regionListPosition'];
        }

        $this->enqueueAssets($instanceClass, $payload['colors'], $payload['regionColors']);

        $svg = new SvgMap($mapDefinition);
        $dataJson = \wp_json_encode($activeRegions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        if (!is_string($dataJson)) {
            $dataJson = '{}';
        }

        $markup = '<div class="' . \esc_attr(implode(' ', $classes)) . '" data-map-studio-instance="' . \esc_attr($instanceId) . '">';
        $markup .= '<div class="map-studio__body">';
        $markup .= '<div class="map-studio__viewport">';
        $markup .= $svg->renderForInstance($instanceId, array_keys($activeRegions), $payload['regionColors']);
        $markup .= '<button type="button" class="map-studio__reset" aria-label="' . \esc_attr__('Reset map zoom', 'map-studio') . '" hidden>';
        $markup .= '<svg class="map-studio__reset-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">';
        $markup .= '<path d="M7 4H4v3"></path><path d="M17 4h3v3"></path><path d="M20 17v3h-3"></path><path d="M4 17v3h3"></path>';
        $markup .= '</svg>';
        $markup .= '</button>';
        $markup .= '<div class="map-studio__bubble" role="dialog" aria-hidden="true">';
        $markup .= '<button type="button" class="map-studio__close" aria-label="' . \esc_attr__('Close map information', 'map-studio') . '">&times;</button>';
        $markup .= '<div class="map-studio__bubble-content" tabindex="-1"></div>';
        $markup .= '</div>';
        $markup .= '<script type="application/json" class="map-studio__data">' . $dataJson . '</script>';
        $markup .= '</div>';

        if ($hasRegionList) {
            $markup .= $this->renderRegionList($mapDefinition, $activeRegions);
        }

        $markup .= '</div>';
        $markup .= '</div>';

        return $markup;
    }

    /**
     * @param array<string, string> $activeRegions
     */
    private function renderRegionList(MapDefinition $mapDefinition, array $activeRegions): string {
        $activeRegionKeys = array_fill_keys(array_keys($activeRegions), true);
        $markup = '<aside class="map-studio__region-list" aria-label="' . \esc_attr__('Map regions', 'map-studio') . '">';
        $markup .= '<div class="map-studio__region-list-items" role="list">';

        foreach ($mapDefinition->shapes() as $shape) {
            $regionKey = $shape['key'];

            if (!isset($activeRegionKeys[$regionKey])) {
                continue;
            }

            $markup .= '<button type="button" class="map-studio__region-list-button" data-map-studio-region-key="' . \esc_attr($regionKey) . '" aria-pressed="false">';
            $markup .= \esc_html($shape['label']);
            $markup .= '</button>';
        }

        $markup .= '</div>';
        $markup .= '</aside>';

        return $markup;
    }

    /**
     * @param array<string, string> $colors
     * @param array<string, string> $regionColors
     */
    private function enqueueAssets(string $instanceClass, array $colors, array $regionColors): void {
        \wp_enqueue_style(
            'map-studio-frontend',
            MAP_STUDIO_URL . 'assets/css/frontend.css',
            [],
            MAP_STUDIO_VERSION
        );

        \wp_enqueue_script(
            'map-studio-viewbox-animation',
            MAP_STUDIO_URL . 'assets/js/viewbox-animation.js',
            [],
            MAP_STUDIO_VERSION,
            true
        );

        \wp_enqueue_script(
            'map-studio-frontend',
            MAP_STUDIO_URL . 'assets/js/frontend.js',
            ['map-studio-viewbox-animation'],
            MAP_STUDIO_VERSION,
            true
        );

        $defaults = MapMeta::defaultPayload()['colors'];
        $colors = MapMeta::sanitizePayload(['colors' => $colors])['colors'];
        $css = sprintf(
            ".%s{--map-studio-color-inactive:%s;--map-studio-color-active:%s;--map-studio-color-hover:%s;--map-studio-color-stroke:%s;--map-studio-color-bubble-background:%s;--map-studio-color-bubble-text:%s;}",
            $instanceClass,
            $colors['inactive'] ?? $defaults['inactive'],
            $colors['active'] ?? $defaults['active'],
            $colors['hover'] ?? $defaults['hover'],
            $colors['stroke'] ?? $defaults['stroke'],
            $colors['bubbleBackground'] ?? $defaults['bubbleBackground'],
            $colors['bubbleText'] ?? $defaults['bubbleText']
        );

        foreach ($regionColors as $regionKey => $color) {
            $cleanColor = MapMeta::sanitizeOptionalHexColor($color);

            if ($cleanColor === '') {
                continue;
            }

            $css .= sprintf(
                '.%s .map-studio__region[data-map-studio-region-key="%s"]{--map-studio-region-custom-color:%s;}',
                $instanceClass,
                $this->cssAttributeValue($regionKey),
                $cleanColor
            );
        }

        \wp_add_inline_style('map-studio-frontend', $css);
    }

    private function cssAttributeValue(string $value): string {
        return strtr($value, [
            "\\" => "\\\\",
            '"' => '\\"',
            "\n" => "\\a ",
            "\r" => "\\d ",
            "\f" => "\\c ",
        ]);
    }

    private function invalidShortcodeOutput(): string {
        if (function_exists('current_user_can') && \current_user_can('manage_options')) {
            return '<p class="map-studio__notice">' . \esc_html__('Map Studio shortcode is missing a valid map ID.', 'map-studio') . '</p>';
        }

        return '';
    }

    private function nextInstanceId(int $mapId): string {
        self::$instanceCounter++;

        return 'map-' . $mapId . '-' . self::$instanceCounter;
    }
}
