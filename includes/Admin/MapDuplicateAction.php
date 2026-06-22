<?php
declare(strict_types=1);

namespace MapStudio\Admin;

use MapStudio\MapMeta;
use MapStudio\MapRegistry;

/**
 * Handles secure duplication of Map Studio records from the admin list.
 * It depends on WordPress post, capability, nonce, redirect, and notice APIs.
 */
final class MapDuplicateAction {
    private const ACTION = 'map_studio_duplicate';
    private const NOTICE_QUERY = 'map_studio_duplicate_notice';

    public function register(): void {
        if (!function_exists('add_action')) {
            return;
        }

        \add_action('admin_action_' . self::ACTION, [$this, 'handle']);
        \add_action('admin_notices', [$this, 'renderNotice']);
    }

    public function duplicate(int $sourcePostId): int {
        $source = \get_post($sourcePostId);

        if (!is_object($source) || ($source->post_type ?? '') !== MapPostType::POST_TYPE) {
            return 0;
        }

        $registry = new MapRegistry(MAP_STUDIO_PATH . 'assets/maps');
        $sourcePayload = MapMeta::get($sourcePostId);
        $mapDefinition = $sourcePayload['mapId'] !== '' ? $registry->get($sourcePayload['mapId']) : null;

        if ($mapDefinition === null) {
            return 0;
        }

        $sourcePayload = MapMeta::sanitizePayload($sourcePayload, $mapDefinition->id(), $mapDefinition);
        $newPostId = \wp_insert_post(
            [
                'post_type' => MapPostType::POST_TYPE,
                'post_status' => 'draft',
                'post_title' => sprintf(__('Copy of %s', 'map-studio'), (string) ($source->post_title ?? '')),
            ],
            true
        );

        if (\is_wp_error($newPostId) || !is_int($newPostId) || $newPostId <= 0) {
            return 0;
        }

        MapMeta::save($newPostId, $sourcePayload, $mapDefinition->id(), $mapDefinition);

        return $newPostId;
    }

    public function handle(): void {
        $sourcePostId = isset($_GET['post']) && is_scalar($_GET['post'])
            ? \absint($_GET['post'])
            : 0;

        if ($sourcePostId <= 0) {
            $this->redirectWithNotice('error');
        }

        \check_admin_referer(self::ACTION . '_' . $sourcePostId);

        if (!\current_user_can('edit_post', $sourcePostId)) {
            $this->redirectWithNotice('error');
        }

        $postTypeObject = \get_post_type_object(MapPostType::POST_TYPE);
        $createCapability = is_object($postTypeObject)
            && isset($postTypeObject->cap)
            && is_object($postTypeObject->cap)
            && isset($postTypeObject->cap->create_posts)
            && is_string($postTypeObject->cap->create_posts)
                ? $postTypeObject->cap->create_posts
                : 'edit_posts';

        if (!\current_user_can($createCapability)) {
            $this->redirectWithNotice('error');
        }

        $newPostId = $this->duplicate($sourcePostId);
        $this->redirectWithNotice($newPostId > 0 ? 'success' : 'error');
    }

    public function renderNotice(): void {
        if (!function_exists('get_current_screen')) {
            return;
        }

        $screen = \get_current_screen();

        if (!is_object($screen) || ($screen->post_type ?? '') !== MapPostType::POST_TYPE) {
            return;
        }

        $notice = isset($_GET[self::NOTICE_QUERY]) && is_scalar($_GET[self::NOTICE_QUERY])
            ? strtolower(trim((string) $_GET[self::NOTICE_QUERY]))
            : '';

        if (!in_array($notice, ['success', 'error'], true)) {
            return;
        }

        $className = $notice === 'success'
            ? 'notice notice-success is-dismissible'
            : 'notice notice-error is-dismissible';
        $message = $notice === 'success'
            ? __('Map duplicated as a draft.', 'map-studio')
            : __('The map could not be duplicated.', 'map-studio');

        echo '<div class="' . \esc_attr($className) . '"><p>' . \esc_html($message) . '</p></div>';
    }

    private function redirectWithNotice(string $notice): void {
        $url = \admin_url('edit.php?post_type=' . MapPostType::POST_TYPE);
        $url = \add_query_arg(self::NOTICE_QUERY, $notice, $url);
        \wp_safe_redirect($url);
        exit;
    }
}
