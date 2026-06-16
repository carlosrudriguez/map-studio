<?php
declare(strict_types=1);

namespace MapStudio\Admin;

use MapStudio\MapDefinition;
use MapStudio\MapMeta;
use MapStudio\MapRegistry;

/**
 * Renders and saves the Map Studio map-content editor in the dashboard.
 * It depends on WordPress editor, nonce, capability, and post-meta APIs.
 */
final class MapMetaBox {
    private const NONCE_ACTION = 'map_studio_save';
    private const NONCE_NAME = 'map_studio_nonce';

    public function register(): void {
        if (!function_exists('add_action')) {
            return;
        }

        \add_action('add_meta_boxes_' . MapPostType::POST_TYPE, [$this, 'addMetaBoxes']);
        \add_action('save_post_' . MapPostType::POST_TYPE, [$this, 'save'], 10, 2);
        \add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMetaBoxes(): void {
        \add_meta_box(
            'map-studio-region-content',
            __('Map Content', 'map-studio'),
            [$this, 'render'],
            MapPostType::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * @param object $post
     */
    public function render(object $post): void {
        $registry = $this->registry();
        $maps = $registry->all();
        $payload = MapMeta::get((int) $post->ID);
        $mapId = $payload['mapId'];
        $mapDefinition = $mapId !== '' ? $registry->get($mapId) : null;

        if ($mapDefinition !== null) {
            $payload = MapMeta::sanitizePayload($payload, $mapDefinition->id(), $mapDefinition);
        }

        $regions = $mapDefinition !== null ? MapMeta::activeRegions($payload, $mapDefinition) : [];
        $regionColors = $mapDefinition !== null ? $payload['regionColors'] : [];
        $shapes = $mapDefinition !== null ? $mapDefinition->shapes() : [];
        $selectedRegionKey = $shapes[0]['key'] ?? '';
        $initialEditorContent = $selectedRegionKey !== '' ? ($regions[$selectedRegionKey] ?? '') : '';
        $regionsJson = $this->jsonEncode($regions, '{}');
        $regionColorsJson = $this->jsonEncode($regionColors, '{}');
        $defaultRegionColor = MapMeta::sanitizeHexColor($payload['colors']['active'] ?? '', MapMeta::defaultPayload()['colors']['active']);

        \wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        echo '<div class="map-studio-admin" data-map-studio-editor-id="map_studio_editor" data-map-studio-initial-region-key="' . \esc_attr($selectedRegionKey) . '" data-map-studio-map-locked="' . \esc_attr($mapDefinition !== null ? 'true' : 'false') . '" data-map-studio-default-region-color="' . \esc_attr($defaultRegionColor) . '">';
        echo '<textarea class="map-studio-admin__region-json" name="map_studio_regions_json" hidden>' . \esc_textarea($regionsJson) . '</textarea>';
        echo '<textarea class="map-studio-admin__region-colors-json" name="map_studio_region_colors_json" hidden>' . \esc_textarea($regionColorsJson) . '</textarea>';
        echo '<script type="application/json" class="map-studio-admin__maps-data">' . $this->mapsJson($maps) . '</script>';

        MapDashboardSection::open('setup', __('Map Setup', 'map-studio'), __('Choose the base map and public region list behavior.', 'map-studio'));
        echo '<div class="map-studio-admin__setup-grid">';
        $this->renderMapSelector($maps, $mapDefinition);
        MapSettingsFields::renderRegionListToggle((bool) $payload['regionListEnabled'], $payload['regionListPosition'], (bool) $payload['regionListHiddenByDefault']);
        echo '</div>';
        MapSettingsFields::renderLegendEditor($payload['legend']);
        MapDashboardSection::close();

        MapDashboardSection::open('content', __('Region Content', 'map-studio'), __('Edit the regions that can be clicked on the public map.', 'map-studio'));
        echo '<p class="map-studio-admin__summary" data-map-studio-summary>' . \esc_html($this->summaryText(count($regions), count($regionColors), count($shapes), $mapDefinition !== null)) . '</p>';
        echo '<div class="map-studio-admin__layout">';
        echo '<div class="map-studio-admin__regions" role="list">';
        $this->renderRegionButtons($shapes, $regions, $regionColors, $selectedRegionKey);
        echo '</div>';
        echo '<div class="map-studio-admin__editor">';
        $this->renderEditor($initialEditorContent);
        echo '</div>';
        echo '</div>';
        MapDashboardSection::close();

        if (function_exists('current_user_can') && \current_user_can('manage_options')) {
            MapDashboardSection::open('appearance', __('Appearance', 'map-studio'), __('Control colors for selected regions and the public map display.', 'map-studio'));
            echo '<div class="map-studio-admin__appearance-grid">';
            $this->renderRegionColorControl($selectedRegionKey, $regionColors, $defaultRegionColor);
            $this->renderColorControls($payload['colors']);
            echo '</div>';
            MapDashboardSection::close();
        }

        echo '</div>';
    }

    /**
     * @param object $post
     */
    public function save(int $postId, object $post): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!isset($_POST[self::NONCE_NAME])) {
            return;
        }

        $nonce = $this->postedString(self::NONCE_NAME);

        if (!function_exists('wp_verify_nonce') || !\wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }

        if (!function_exists('current_user_can') || !\current_user_can('edit_post', $postId)) {
            return;
        }

        $registry = $this->registry();
        $existingPayload = MapMeta::get($postId);
        $lockedMapId = $existingPayload['mapId'];
        $postedMapId = $this->postedString('map_studio_map_id');
        $mapId = $lockedMapId !== '' ? $lockedMapId : $postedMapId;
        $mapDefinition = $mapId !== '' ? $registry->get($mapId) : null;

        if ($mapDefinition === null) {
            return;
        }

        $colors = $existingPayload['colors'];
        $regionColors = $existingPayload['regionColors'];

        if (\current_user_can('manage_options') && isset($_POST['map_studio_colors']) && is_array($_POST['map_studio_colors'])) {
            $colors = $this->postedColors();
        }

        if (\current_user_can('manage_options') && isset($_POST['map_studio_region_colors_json'])) {
            $regionColors = $this->postedRegionColors();
        }

        MapMeta::save(
            $postId,
            [
                'mapId' => $mapDefinition->id(),
                'regions' => $this->postedRegions(),
                'regionColors' => $regionColors,
                'colors' => $colors,
                'regionListEnabled' => isset($_POST['map_studio_region_list_enabled']) ? '1' : '0',
                'regionListPosition' => $this->postedString('map_studio_region_list_position'),
                'regionListHiddenByDefault' => isset($_POST['map_studio_region_list_hidden_by_default']) ? '1' : '0',
                'legend' => $this->postedString('map_studio_legend'),
            ],
            $lockedMapId,
            $mapDefinition
        );
    }

    public function enqueueAssets(): void {
        if (!function_exists('get_current_screen')) {
            return;
        }

        $screen = \get_current_screen();

        if (!is_object($screen) || ($screen->post_type ?? '') !== MapPostType::POST_TYPE) {
            return;
        }

        \wp_enqueue_style(
            'map-studio-admin',
            MAP_STUDIO_URL . 'assets/css/admin.css',
            [],
            MAP_STUDIO_VERSION
        );

        \wp_enqueue_style(
            'map-studio-admin-dashboard',
            MAP_STUDIO_URL . 'assets/css/admin-dashboard.css',
            ['map-studio-admin'],
            MAP_STUDIO_VERSION
        );

        \wp_enqueue_script(
            'map-studio-admin',
            MAP_STUDIO_URL . 'assets/js/admin.js',
            [],
            MAP_STUDIO_VERSION,
            true
        );
    }

    /**
     * @param array<string, MapDefinition> $maps
     */
    private function renderMapSelector(array $maps, ?MapDefinition $mapDefinition): void {
        echo '<div class="map-studio-admin__map-field">';
        echo '<label class="map-studio-admin__map-label" for="map_studio_map_id">' . \esc_html__('Map', 'map-studio') . '</label>';

        if ($mapDefinition !== null) {
            echo '<input type="hidden" id="map_studio_map_id" name="map_studio_map_id" value="' . \esc_attr($mapDefinition->id()) . '">';
            echo '<p class="map-studio-admin__locked-map">' . \esc_html($mapDefinition->label()) . '</p>';
            echo '</div>';
            return;
        }

        echo '<select id="map_studio_map_id" class="map-studio-admin__map-select" name="map_studio_map_id">';
        echo '<option value="">' . \esc_html__('Select a map', 'map-studio') . '</option>';

        foreach ($maps as $map) {
            echo '<option value="' . \esc_attr($map->id()) . '">' . \esc_html($map->label()) . '</option>';
        }

        echo '</select>';
        echo '</div>';
    }

    /**
     * @param array<int, array{key: string, svgId: string, label: string}> $shapes
     * @param array<string, string> $regions
     * @param array<string, string> $regionColors
     */
    private function renderRegionButtons(array $shapes, array $regions, array $regionColors, string $selectedRegionKey): void {
        foreach ($shapes as $shape) {
            $regionKey = $shape['key'];
            $classes = ['map-studio-admin__region-button'];

            if ($regionKey === $selectedRegionKey) {
                $classes[] = 'is-selected';
            }

            if (isset($regions[$regionKey])) {
                $classes[] = 'has-content';
            }

            if (isset($regionColors[$regionKey])) {
                $classes[] = 'has-custom-color';
            }

            echo '<button type="button" class="' . \esc_attr(implode(' ', $classes)) . '" data-map-studio-region-key="' . \esc_attr($regionKey) . '" aria-pressed="' . \esc_attr($regionKey === $selectedRegionKey ? 'true' : 'false') . '">';
            echo '<span class="map-studio-admin__region-label">' . \esc_html($shape['label']) . '</span>';
            echo '<span class="map-studio-admin__status" aria-hidden="true"></span>';
            echo '</button>';
        }
    }

    /**
     * @param array<string, string> $regionColors
     */
    private function renderRegionColorControl(string $selectedRegionKey, array $regionColors, string $defaultRegionColor): void {
        $customColor = $selectedRegionKey !== '' ? ($regionColors[$selectedRegionKey] ?? '') : '';
        $hasCustomColor = $customColor !== '';
        $value = $hasCustomColor ? $customColor : $defaultRegionColor;
        $disabled = $selectedRegionKey === '' ? ' disabled' : '';
        $checked = $hasCustomColor ? ' checked' : '';

        echo '<fieldset class="map-studio-admin__region-color">';
        echo '<legend>' . \esc_html__('Selected Shape Color', 'map-studio') . '</legend>';
        echo '<label class="map-studio-admin__region-color-toggle" for="map_studio_region_color_enabled">';
        echo '<input type="checkbox" id="map_studio_region_color_enabled"' . $checked . $disabled . '>';
        echo '<span>' . \esc_html__('Use custom color', 'map-studio') . '</span>';
        echo '</label>';
        echo '<label class="map-studio-admin__region-color-picker" for="map_studio_region_color">';
        echo '<span>' . \esc_html__('Shape color', 'map-studio') . '</span>';
        echo '<input type="color" id="map_studio_region_color" value="' . \esc_attr($value) . '"' . $disabled . '>';
        echo '</label>';
        echo '</fieldset>';
    }

    private function renderEditor(string $initialEditorContent): void {
        if (function_exists('wp_editor')) {
            \wp_editor(
                $initialEditorContent,
                'map_studio_editor',
                [
                    'media_buttons' => true,
                    'textarea_name' => 'map_studio_editor_content',
                    'textarea_rows' => 12,
                ]
            );
            return;
        }

        echo '<textarea id="map_studio_editor" name="map_studio_editor_content" rows="12">' . \esc_textarea($initialEditorContent) . '</textarea>';
    }

    /**
     * @param array<string, string> $colors
     */
    private function renderColorControls(array $colors): void {
        $labels = [
            'inactive' => __('Inactive region', 'map-studio'),
            'active' => __('Active region', 'map-studio'),
            'hover' => __('Hover region', 'map-studio'),
            'stroke' => __('Region stroke', 'map-studio'),
            'bubbleBackground' => __('Bubble background', 'map-studio'),
            'bubbleText' => __('Bubble text', 'map-studio'),
        ];

        echo '<fieldset class="map-studio-admin__colors">';
        echo '<legend>' . \esc_html__('Map Colors', 'map-studio') . '</legend>';

        foreach ($labels as $key => $label) {
            $value = MapMeta::sanitizeHexColor($colors[$key] ?? '', MapMeta::defaultPayload()['colors'][$key]);

            echo '<label class="map-studio-admin__color-field">';
            echo '<span>' . \esc_html($label) . '</span>';
            echo '<input type="color" name="map_studio_colors[' . \esc_attr($key) . ']" value="' . \esc_attr($value) . '">';
            echo '</label>';
        }

        echo '</fieldset>';
    }

    /**
     * @param array<string, MapDefinition> $maps
     */
    private function mapsJson(array $maps): string {
        $data = [];

        foreach ($maps as $map) {
            $data[$map->id()] = [
                'label' => $map->label(),
                'shapes' => $map->shapes(),
            ];
        }

        return $this->jsonEncode($data, '{}');
    }

    private function summaryText(int $filledCount, int $coloredCount, int $shapeCount, bool $hasMap): string {
        if (!$hasMap) {
            return __('Select a map to begin adding content.', 'map-studio');
        }

        return sprintf(__('%1$d of %2$d regions have content; %3$d have custom colors', 'map-studio'), $filledCount, $shapeCount, $coloredCount);
    }

    /**
     * @return array<string, string>
     */
    private function postedRegions(): array {
        $json = $this->postedString('map_studio_regions_json');
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, string>
     */
    private function postedRegionColors(): array {
        $json = $this->postedString('map_studio_region_colors_json');
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, string>
     */
    private function postedColors(): array {
        $colors = $_POST['map_studio_colors'] ?? [];

        if (function_exists('wp_unslash')) {
            $colors = \wp_unslash($colors);
        }

        return is_array($colors) ? $colors : [];
    }

    private function postedString(string $key): string {
        $value = $_POST[$key] ?? '';

        if (is_array($value)) {
            return '';
        }

        if (function_exists('wp_unslash')) {
            $value = \wp_unslash($value);
        }

        return is_string($value) ? $value : '';
    }

    private function registry(): MapRegistry {
        return new MapRegistry(MAP_STUDIO_PATH . 'assets/maps');
    }

    private function jsonEncode(mixed $value, string $fallback): string {
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $json = function_exists('wp_json_encode') ? \wp_json_encode($value, $flags) : json_encode($value, $flags);

        return is_string($json) ? $json : $fallback;
    }
}
