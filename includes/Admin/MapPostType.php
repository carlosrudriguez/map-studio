<?php
declare(strict_types=1);

namespace MapStudio\Admin;

/**
 * Registers the admin-only Map Studio record post type.
 * Map content is stored in post meta while the title identifies each shortcode target.
 */
final class MapPostType {
    public const POST_TYPE = 'map_studio_map';

    public function register(): void {
        if (!function_exists('add_action')) {
            return;
        }

        \add_action('init', [$this, 'registerPostType']);
    }

    public function registerPostType(): void {
        \register_post_type(
            self::POST_TYPE,
            [
                'labels' => [
                    'name' => 'Maps',
                    'singular_name' => 'Map',
                ],
                'public' => false,
                'publicly_queryable' => false,
                'show_ui' => true,
                'show_in_menu' => Menu::SLUG,
                'supports' => ['title'],
                'capability_type' => 'post',
                'map_meta_cap' => true,
            ]
        );
    }
}
