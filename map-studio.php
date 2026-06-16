<?php
declare(strict_types=1);

/**
 * Plugin Name: Map Studio
 * Description: Builds configurable interactive SVG maps through a shortcode.
 * Version: 1.0.1
 * Author: Carlos Rodríguez
 * Author URI: https://carlosrodriguez.mx/
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: map-studio
 *
 * Boots the plugin services inside WordPress and defines shared path constants.
 * Class files are loaded explicitly so the plugin remains simple for v1.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MAP_STUDIO_VERSION', '1.0.1');
define('MAP_STUDIO_FILE', __FILE__);
define('MAP_STUDIO_PATH', plugin_dir_path(__FILE__));
define('MAP_STUDIO_URL', plugin_dir_url(__FILE__));

$map_studio_files = [
    'includes/MapDefinition.php',
    'includes/MapRegistry.php',
    'includes/SvgMap.php',
    'includes/MapMeta.php',
    'includes/Plugin.php',
    'includes/Admin/Menu.php',
    'includes/Admin/MapPostType.php',
    'includes/Admin/MapListTable.php',
    'includes/Admin/MapDashboardSection.php',
    'includes/Admin/MapSettingsFields.php',
    'includes/Admin/MapMetaBox.php',
    'includes/Frontend/Shortcode.php',
];

foreach ($map_studio_files as $map_studio_file) {
    $map_studio_path = MAP_STUDIO_PATH . $map_studio_file;

    if (file_exists($map_studio_path)) {
        require_once $map_studio_path;
    }
}

add_action('plugins_loaded', static function (): void {
    $plugin = new MapStudio\Plugin();
    $plugin->register();
});
