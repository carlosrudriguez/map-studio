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
}
