<?php
declare(strict_types=1);

namespace MapStudio\Admin;

/**
 * Renders focused map-level settings controls for the editor meta box.
 * The fields depend on WordPress escaping helpers but keep form markup isolated.
 */
final class MapSettingsFields {
    public static function renderRegionListSettings(
        bool $enabled,
        string $position,
        bool $hiddenByDefault,
        bool $searchEnabled,
        string $searchPlaceholder
    ): void {
        $checked = $enabled ? ' checked' : '';
        $hiddenChecked = $hiddenByDefault ? ' checked' : '';
        $searchChecked = $searchEnabled ? ' checked' : '';
        $position = in_array($position, ['left', 'right'], true) ? $position : 'right';

        echo '<fieldset class="map-studio-admin__settings">';
        echo '<legend>' . \esc_html__('Map Settings', 'map-studio') . '</legend>';
        echo '<div class="map-studio-admin__settings-row">';
        echo '<label class="map-studio-admin__switch" for="map_studio_region_list_enabled">';
        echo '<input type="checkbox" class="map-studio-admin__switch-input" id="map_studio_region_list_enabled" name="map_studio_region_list_enabled" value="1" role="switch" aria-describedby="map_studio_region_list_help"' . $checked . '>';
        echo '<span class="map-studio-admin__switch-control" aria-hidden="true"><span class="map-studio-admin__switch-thumb"></span></span>';
        echo '<span class="map-studio-admin__switch-copy">';
        echo '<span class="map-studio-admin__switch-title">' . \esc_html__('Region list', 'map-studio') . '</span>';
        echo '<span class="map-studio-admin__switch-description" id="map_studio_region_list_help">' . \esc_html__('Show a clickable sidebar with regions that have content.', 'map-studio') . '</span>';
        echo '</span>';
        echo '</label>';
        echo '</div>';
        echo '<div class="map-studio-admin__settings-row">';
        echo '<label class="map-studio-admin__switch" for="map_studio_region_list_hidden_by_default">';
        echo '<input type="checkbox" class="map-studio-admin__switch-input" id="map_studio_region_list_hidden_by_default" name="map_studio_region_list_hidden_by_default" value="1" role="switch" aria-describedby="map_studio_region_list_hidden_help"' . $hiddenChecked . '>';
        echo '<span class="map-studio-admin__switch-control" aria-hidden="true"><span class="map-studio-admin__switch-thumb"></span></span>';
        echo '<span class="map-studio-admin__switch-copy">';
        echo '<span class="map-studio-admin__switch-title">' . \esc_html__('Start sidebar hidden', 'map-studio') . '</span>';
        echo '<span class="map-studio-admin__switch-description" id="map_studio_region_list_hidden_help">' . \esc_html__('Show the sidebar only after visitors use the public action button.', 'map-studio') . '</span>';
        echo '</span>';
        echo '</label>';
        echo '</div>';
        echo '<div class="map-studio-admin__settings-row">';
        echo '<label class="map-studio-admin__switch" for="map_studio_region_search_enabled">';
        echo '<input type="checkbox" class="map-studio-admin__switch-input" id="map_studio_region_search_enabled" name="map_studio_region_search_enabled" value="1" role="switch" aria-describedby="map_studio_region_search_help"' . $searchChecked . '>';
        echo '<span class="map-studio-admin__switch-control" aria-hidden="true"><span class="map-studio-admin__switch-thumb"></span></span>';
        echo '<span class="map-studio-admin__switch-copy">';
        echo '<span class="map-studio-admin__switch-title">' . \esc_html__('Region search', 'map-studio') . '</span>';
        echo '<span class="map-studio-admin__switch-description" id="map_studio_region_search_help">' . \esc_html__('Show a search field above the public region list.', 'map-studio') . '</span>';
        echo '</span>';
        echo '</label>';
        echo '</div>';
        echo '<label class="map-studio-admin__text-field" for="map_studio_region_search_placeholder">';
        echo '<span>' . \esc_html__('Search placeholder', 'map-studio') . '</span>';
        echo '<input type="text" id="map_studio_region_search_placeholder" name="map_studio_region_search_placeholder" value="' . \esc_attr($searchPlaceholder) . '">';
        echo '</label>';
        echo '<fieldset class="map-studio-admin__position" aria-label="' . \esc_attr__('Region list position', 'map-studio') . '">';
        echo '<legend>' . \esc_html__('Sidebar position', 'map-studio') . '</legend>';
        self::renderPositionOption('left', __('Left', 'map-studio'), $position);
        self::renderPositionOption('right', __('Right', 'map-studio'), $position);
        echo '</fieldset>';
        echo '</fieldset>';
    }

    public static function renderLegendEditor(string $legend): void {
        echo '<fieldset class="map-studio-admin__legend">';
        echo '<legend>' . \esc_html__('Map Legend', 'map-studio') . '</legend>';

        if (function_exists('wp_editor')) {
            \wp_editor(
                $legend,
                'map_studio_legend',
                [
                    'media_buttons' => true,
                    'textarea_name' => 'map_studio_legend',
                    'textarea_rows' => 8,
                ]
            );
        } else {
            echo '<textarea id="map_studio_legend" name="map_studio_legend" rows="8">' . \esc_textarea($legend) . '</textarea>';
        }

        echo '</fieldset>';
    }

    private static function renderPositionOption(string $value, string $label, string $selectedPosition): void {
        $checked = $selectedPosition === $value ? ' checked' : '';

        echo '<label class="map-studio-admin__position-option">';
        echo '<input type="radio" name="map_studio_region_list_position" value="' . \esc_attr($value) . '"' . $checked . '>';
        echo '<span>' . \esc_html($label) . '</span>';
        echo '</label>';
    }
}
