<?php
declare(strict_types=1);

namespace MapStudio\Admin;

/**
 * Renders reusable dashboard sections for the map editor.
 * The meta box depends on this helper to keep admin layout markup consistent.
 */
final class MapDashboardSection {
    public static function open(string $modifier, string $title, string $description = ''): void {
        echo '<section class="map-studio-admin__section is-' . \esc_attr($modifier) . '">';
        echo '<header class="map-studio-admin__section-header">';
        echo '<h3 class="map-studio-admin__section-title">' . \esc_html($title) . '</h3>';

        if ($description !== '') {
            echo '<p class="map-studio-admin__section-description">' . \esc_html($description) . '</p>';
        }

        echo '</header>';
        echo '<div class="map-studio-admin__section-body">';
    }

    public static function close(): void {
        echo '</div>';
        echo '</section>';
    }
}
