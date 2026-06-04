<?php
declare(strict_types=1);

namespace MapStudio\Admin;

/**
 * Provides the shared Map Studio parent menu for plugin admin screens.
 * The menu uses WordPress admin hooks and stays intentionally minimal.
 */
final class Menu {
    public const SLUG = 'map-studio';

    public function register(): void {
        if (!function_exists('add_action')) {
            return;
        }

        \add_action('admin_menu', [$this, 'registerMenu']);
    }

    public function registerMenu(): void {
        if ($this->parentMenuExists()) {
            return;
        }

        \add_menu_page(
            'Map Studio',
            'Map Studio',
            'edit_posts',
            self::SLUG,
            [$this, 'renderLandingPage'],
            'dashicons-location-alt',
            56
        );
    }

    public function renderLandingPage(): void {
        echo '<div class="wrap">';
        echo '<h1>' . \esc_html__('Map Studio', 'map-studio') . '</h1>';
        echo '</div>';
    }

    private function parentMenuExists(): bool {
        global $menu;

        if (!is_array($menu)) {
            return false;
        }

        foreach ($menu as $menuItem) {
            if (is_array($menuItem) && isset($menuItem[2]) && $menuItem[2] === self::SLUG) {
                return true;
            }
        }

        return false;
    }
}
