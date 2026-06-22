<?php
declare(strict_types=1);

namespace MapStudio\Admin;

/**
 * Adds admin list table affordances for Map Studio records.
 * The shortcode column gives editors the exact shortcode for each map post.
 */
final class MapListTable {
    public function register(): void {
        if (!function_exists('add_filter') || !function_exists('add_action')) {
            return;
        }

        \add_filter('manage_' . MapPostType::POST_TYPE . '_posts_columns', [$this, 'addShortcodeColumn']);
        \add_filter('post_row_actions', [$this, 'addDuplicateAction'], 10, 2);
        \add_action('manage_' . MapPostType::POST_TYPE . '_posts_custom_column', [$this, 'renderShortcodeColumn'], 10, 2);
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function addShortcodeColumn(array $columns): array {
        $updatedColumns = [];
        $inserted = false;

        foreach ($columns as $key => $label) {
            $updatedColumns[$key] = $label;

            if ($key === 'title') {
                $updatedColumns['shortcode'] = __('Shortcode', 'map-studio');
                $inserted = true;
            }
        }

        if (!$inserted) {
            $updatedColumns['shortcode'] = __('Shortcode', 'map-studio');
        }

        return $updatedColumns;
    }

    public function renderShortcodeColumn(string $columnName, int $postId): void {
        if ($columnName !== 'shortcode') {
            return;
        }

        echo \esc_html(sprintf('[map_studio id="%d"]', $postId));
    }

    /**
     * @param array<string, string> $actions
     * @param object $post
     * @return array<string, string>
     */
    public function addDuplicateAction(array $actions, object $post): array {
        $postId = isset($post->ID) ? (int) $post->ID : 0;
        $postTypeObject = function_exists('get_post_type_object')
            ? \get_post_type_object(MapPostType::POST_TYPE)
            : null;
        $createCapability = is_object($postTypeObject)
            && isset($postTypeObject->cap)
            && is_object($postTypeObject->cap)
            && isset($postTypeObject->cap->create_posts)
            && is_string($postTypeObject->cap->create_posts)
                ? $postTypeObject->cap->create_posts
                : 'edit_posts';

        if (
            ($post->post_type ?? '') !== MapPostType::POST_TYPE
            || $postId <= 0
            || !function_exists('current_user_can')
            || !\current_user_can('edit_post', $postId)
            || !\current_user_can($createCapability)
        ) {
            return $actions;
        }

        $url = \admin_url('admin.php?action=map_studio_duplicate&post=' . $postId);
        $url = \wp_nonce_url($url, 'map_studio_duplicate_' . $postId);
        $actions['map_studio_duplicate'] = '<a href="' . \esc_url($url) . '">' . \esc_html__('Duplicate', 'map-studio') . '</a>';

        return $actions;
    }
}
