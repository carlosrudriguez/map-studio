<?php
declare(strict_types=1);

/**
 * Contract checks for the Map Studio plugin.
 * These tests verify SVG discovery, metadata locks, and shortcode rendering without booting WordPress.
 */

$map_studio_files = [
    'includes/MapDefinition.php',
    'includes/MapRegistry.php',
    'includes/SvgMap.php',
    'includes/MapMeta.php',
    'includes/Admin/Menu.php',
    'includes/Admin/MapPostType.php',
    'includes/Admin/MapListTable.php',
    'includes/Admin/MapSettingsFields.php',
    'includes/Frontend/Shortcode.php',
];

foreach ($map_studio_files as $map_studio_file) {
    $map_studio_path = __DIR__ . '/../' . $map_studio_file;

    if (file_exists($map_studio_path)) {
        require_once $map_studio_path;
    }
}

if (!defined('MAP_STUDIO_VERSION')) {
    define('MAP_STUDIO_VERSION', '0.1.0');
}

if (!defined('MAP_STUDIO_PATH')) {
    define('MAP_STUDIO_PATH', dirname(__DIR__) . '/');
}

if (!defined('MAP_STUDIO_URL')) {
    define('MAP_STUDIO_URL', 'https://example.test/wp-content/plugins/map-studio/');
}

function fail_contract(string $message): void {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function assert_contract(bool $condition, string $message): void {
    if (!$condition) {
        fail_contract($message);
    }
}

function get_post_meta(int $post_id, string $key, bool $single = false): mixed {
    return $GLOBALS['map_studio_contract_post_meta'][$key] ?? '';
}

function shortcode_atts(array $pairs, array $atts, string $shortcode = ''): array {
    return array_merge($pairs, $atts);
}

function absint(mixed $value): int {
    return abs((int) $value);
}

function get_post(int $post_id): ?object {
    return $GLOBALS['map_studio_contract_posts'][$post_id] ?? null;
}

function current_user_can(string $capability, mixed ...$args): bool {
    return (bool) ($GLOBALS['map_studio_contract_current_user_can'][$capability] ?? false);
}

function wp_enqueue_style(string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, string $media = 'all'): void {
    $GLOBALS['map_studio_contract_enqueued_styles'][] = $handle;
}

function wp_enqueue_script(string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, array|bool $args = []): void {
    $GLOBALS['map_studio_contract_enqueued_scripts'][] = $handle;
}

function wp_add_inline_style(string $handle, string $data): bool {
    $GLOBALS['map_studio_contract_inline_styles'][$handle][] = $data;
    return true;
}

function wp_json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false {
    return json_encode($value, $flags, $depth);
}

function esc_attr(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function esc_html(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function esc_textarea(string $text): string {
    return htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
}

function esc_attr__(string $text, string $domain = 'default'): string {
    return esc_attr($text);
}

function esc_html__(string $text, string $domain = 'default'): string {
    return esc_html($text);
}

function __(string $text, string $domain = 'default'): string {
    return $text;
}

function add_menu_page(
    string $page_title,
    string $menu_title,
    string $capability,
    string $menu_slug,
    callable|null $callback = null,
    string $icon_url = '',
    int|float|null $position = null
): string {
    $GLOBALS['map_studio_contract_add_menu_page_calls'][] = $menu_slug;
    return $menu_slug;
}

$registry = new \MapStudio\MapRegistry(dirname(__DIR__) . '/assets/maps');
$maps = $registry->all();

assert_contract(count($maps) === 57, 'Expected 57 map SVGs to be discovered.');
assert_contract(isset($maps['MX']), 'Mexico map must be discovered.');
assert_contract(isset($maps['WORLD']), 'World map must be discovered.');
assert_contract($maps['MX']->label() === 'Mexico', 'MX map label mismatch.');
assert_contract($maps['WORLD']->label() === 'World', 'WORLD map label mismatch.');
assert_contract(count($maps['MX']->shapes()) === 32, 'Mexico should expose 32 shapes.');
assert_contract($maps['MX']->shapeLabel('MX-DIF') === 'Ciudad de México', 'MX-DIF label mismatch.');
assert_contract($maps['WORLD']->shapeLabel('FR') === 'France', 'WORLD FR label mismatch.');
assert_contract($maps['ASIA']->shapeLabel('99--northern-cyprus') === 'Northern Cyprus', 'Duplicate -99 shapes need stable keys.');
assert_contract($maps['IE']->shapeLabel('IE-D--fingal') === 'Fingal', 'Duplicate IE-D shapes need stable keys.');

$payload = \MapStudio\MapMeta::sanitizePayload(
    [
        'mapId' => 'MX',
        'regions' => [
            'MX-JAL' => '<p>Jalisco <strong>content</strong></p>',
            'MX-SON' => '   ',
            'US-WA' => '<p>Wrong map</p>',
        ],
        'colors' => [
            'inactive' => '#111111',
            'hover' => 'blue',
        ],
        'regionColors' => [
            'MX-SON' => '#AA5500',
            'US-WA' => '#445566',
            'MX-BCN' => 'orange',
        ],
        'regionListEnabled' => '1',
        'regionListPosition' => 'left',
    ],
    '',
    $maps['MX']
);

assert_contract($payload['mapId'] === 'MX', 'Selected map ID should be saved.');
assert_contract(isset($payload['regions']['MX-JAL']), 'Region content should be saved.');
assert_contract(!isset($payload['regions']['MX-SON']), 'Empty MX-SON content should not be active.');
assert_contract(!isset($payload['regions']['US-WA']), 'Regions from another map should not be saved.');
assert_contract($payload['colors']['inactive'] === '#111111', 'Valid color override missing.');
assert_contract($payload['colors']['hover'] === \MapStudio\MapMeta::defaultPayload()['colors']['hover'], 'Invalid hover color should fall back.');
assert_contract($payload['regionColors']['MX-SON'] === '#aa5500', 'Region color should be saved without content.');
assert_contract(!isset($payload['regionColors']['US-WA']), 'Region colors from another map should not be saved.');
assert_contract(!isset($payload['regionColors']['MX-BCN']), 'Invalid region colors should be discarded.');
assert_contract($payload['regionListEnabled'] === true, 'Region list setting should be saved when enabled.');
assert_contract($payload['regionListPosition'] === 'left', 'Region list position should be saved when valid.');

$defaultSettingsPayload = \MapStudio\MapMeta::sanitizePayload([], '', $maps['MX']);
assert_contract($defaultSettingsPayload['regionListEnabled'] === false, 'Region list setting should default to disabled.');
assert_contract($defaultSettingsPayload['regionListPosition'] === 'right', 'Region list position should default to right.');

$invalidSettingsPayload = \MapStudio\MapMeta::sanitizePayload(['regionListPosition' => 'top'], '', $maps['MX']);
assert_contract($invalidSettingsPayload['regionListPosition'] === 'right', 'Invalid region list position should fall back to right.');

$lockedPayload = \MapStudio\MapMeta::sanitizePayload(
    [
        'mapId' => 'US',
        'regions' => ['US-WA' => '<p>Washington</p>'],
        'regionColors' => ['US-WA' => '#112233'],
    ],
    'MX',
    $maps['MX']
);

assert_contract($lockedPayload['mapId'] === 'MX', 'Existing map ID must remain locked.');
assert_contract($lockedPayload['regions'] === [], 'Regions from a different map must not be saved to a locked map.');
assert_contract($lockedPayload['regionColors'] === [], 'Region colors from a different map must not be saved to a locked map.');

assert_contract(\MapStudio\MapMeta::sanitizeHexColor('#ABCDEF', '#000000') === '#abcdef', 'Uppercase hex should normalize.');
assert_contract(\MapStudio\MapMeta::sanitizeHexColor('red', '#000000') === '#000000', 'Invalid color should fall back.');

$svg = new \MapStudio\SvgMap($maps['MX']);
$renderedSvg = $svg->renderForInstance('contract-map', ['MX-DIF'], ['MX-SON' => '#aa5500']);
assert_contract(strpos($renderedSvg, 'data-map-studio-region-key="MX-DIF"') !== false, 'Rendered SVG should include region keys.');
assert_contract(strpos($renderedSvg, 'map-studio__region is-active') !== false, 'Active region class missing.');
assert_contract(strpos($renderedSvg, 'data-map-studio-region-key="MX-SON"') !== false, 'Custom-colored inactive regions should render.');
assert_contract(strpos($renderedSvg, 'has-custom-color') !== false, 'Custom-colored regions should receive a custom color class.');

$duplicateSvg = new \MapStudio\SvgMap($maps['ASIA']);
$renderedDuplicateSvg = $duplicateSvg->renderForInstance('contract-asia', ['99--northern-cyprus']);
assert_contract(strpos($renderedDuplicateSvg, 'data-map-studio-region-key="99--northern-cyprus"') !== false, 'Duplicate SVG IDs should render with stable region keys.');

$GLOBALS['map_studio_contract_post_meta'][\MapStudio\MapMeta::META_KEY] = '{"mapId":"MX","regions":{"MX-JAL":"<p>Jalisco from JSON meta</p>"},"regionColors":{"MX-SON":"#123456"},"colors":{"inactive":"#222222"},"regionListEnabled":true,"regionListPosition":"left"}';
$jsonMetaPayload = \MapStudio\MapMeta::get(123, $maps['MX']);
assert_contract(isset($jsonMetaPayload['regions']['MX-JAL']), 'JSON string meta payload should be decoded.');
assert_contract($jsonMetaPayload['regionColors']['MX-SON'] === '#123456', 'JSON string meta region colors should be decoded.');
assert_contract($jsonMetaPayload['colors']['inactive'] === '#222222', 'JSON string meta colors should be decoded.');
assert_contract($jsonMetaPayload['regionListEnabled'] === true, 'JSON string meta region list setting should be decoded.');
assert_contract($jsonMetaPayload['regionListPosition'] === 'left', 'JSON string meta region list position should be decoded.');
unset($GLOBALS['map_studio_contract_post_meta']);

$GLOBALS['map_studio_contract_post_meta'][\MapStudio\MapMeta::META_KEY] = '{"mapId":"MX","regions":{"MX-JAL":"<p>Jalisco visible content.</p>"},"regionColors":{"MX-SON":"#aa5500"},"colors":{"inactive":"#d1d5db"},"regionListEnabled":true,"regionListPosition":"left"}';
$GLOBALS['map_studio_contract_posts'][10] = (object) [
    'ID' => 10,
    'post_type' => \MapStudio\Admin\MapPostType::POST_TYPE,
    'post_status' => 'draft',
];
$draftShortcode = (new \MapStudio\Frontend\Shortcode())->render(['id' => 10]);
assert_contract($draftShortcode === '', 'Draft maps must not render publicly.');

$GLOBALS['map_studio_contract_posts'][11] = (object) [
    'ID' => 11,
    'post_type' => \MapStudio\Admin\MapPostType::POST_TYPE,
    'post_status' => 'publish',
];
$publishedShortcode = (new \MapStudio\Frontend\Shortcode())->render(['id' => 11]);
assert_contract(strpos($publishedShortcode, 'data-map-studio-region-key="MX-JAL"') !== false, 'Published maps should render active regions.');
assert_contract(strpos($publishedShortcode, 'data-map-studio-region-key="MX-SON"') !== false, 'Published maps should render colored regions without content.');
assert_contract(strpos($publishedShortcode, 'class="map-studio__reset"') !== false, 'Published maps should render an icon-only zoom reset control.');
assert_contract(strpos($publishedShortcode, 'Reset map zoom') !== false, 'Zoom reset control should have an accessible label.');
assert_contract(strpos($publishedShortcode, 'M7 4H4v3') !== false, 'Zoom reset control should use the fit-to-map corner icon.');
assert_contract(strpos($publishedShortcode, 'class="map-studio__region-list"') !== false, 'Enabled maps should render a public region list.');
assert_contract(strpos($publishedShortcode, 'is-region-list-left') !== false, 'Left-positioned region list should add a frontend layout class.');
assert_contract(strpos($publishedShortcode, 'class="map-studio__region-list-button" data-map-studio-region-key="MX-JAL"') !== false, 'Region list should include active regions.');
assert_contract(strpos($publishedShortcode, 'Jalisco') !== false, 'Region list should render region labels.');
assert_contract(strpos($publishedShortcode, 'class="map-studio__region-list-button" data-map-studio-region-key="MX-SON"') === false, 'Region list should not include color-only regions.');
$publishedInlineCss = implode('', $GLOBALS['map_studio_contract_inline_styles']['map-studio-frontend'] ?? []);
assert_contract(strpos($publishedInlineCss, '#aa5500') !== false, 'Published maps should include custom region color CSS.');
assert_contract(strpos($publishedShortcode, 'class="map-studio__data"') !== false, 'Published maps should include data JSON.');

$GLOBALS['map_studio_contract_post_meta'][\MapStudio\MapMeta::META_KEY] = '{"mapId":"MX","regions":{"MX-JAL":"<p>Jalisco visible content.</p>"},"regionListEnabled":false}';
$GLOBALS['map_studio_contract_posts'][12] = (object) [
    'ID' => 12,
    'post_type' => \MapStudio\Admin\MapPostType::POST_TYPE,
    'post_status' => 'publish',
];
$publishedWithoutList = (new \MapStudio\Frontend\Shortcode())->render(['id' => 12]);
assert_contract(strpos($publishedWithoutList, 'class="map-studio__region-list"') === false, 'Disabled region list setting should not render a public region list.');
unset($GLOBALS['map_studio_contract_post_meta'], $GLOBALS['map_studio_contract_posts']);

$GLOBALS['menu'] = [
    ['', '', \MapStudio\Admin\Menu::SLUG],
];
$GLOBALS['map_studio_contract_add_menu_page_calls'] = [];
(new \MapStudio\Admin\Menu())->registerMenu();
assert_contract(count($GLOBALS['map_studio_contract_add_menu_page_calls']) === 0, 'Existing Map Studio parent menu should not be duplicated.');

$GLOBALS['menu'] = [];
$GLOBALS['map_studio_contract_add_menu_page_calls'] = [];
(new \MapStudio\Admin\Menu())->registerMenu();
assert_contract($GLOBALS['map_studio_contract_add_menu_page_calls'] === [\MapStudio\Admin\Menu::SLUG], 'Missing Map Studio parent menu should be created.');
unset($GLOBALS['menu'], $GLOBALS['map_studio_contract_add_menu_page_calls']);

assert_contract(file_exists(dirname(__DIR__) . '/map-studio.php'), 'Main plugin file is missing.');
assert_contract(file_exists(dirname(__DIR__) . '/includes/Plugin.php'), 'Plugin class is missing.');
assert_contract(file_exists(dirname(__DIR__) . '/assets/maps/MX.svg'), 'Mexico SVG is missing from maps directory.');
assert_contract(file_exists(dirname(__DIR__) . '/assets/js/admin.js'), 'Admin JS is missing.');
assert_contract(file_exists(dirname(__DIR__) . '/assets/css/admin.css'), 'Admin CSS is missing.');
assert_contract(file_exists(dirname(__DIR__) . '/assets/js/frontend.js'), 'Frontend JS is missing.');
assert_contract(file_exists(dirname(__DIR__) . '/assets/css/frontend.css'), 'Frontend CSS is missing.');

$adminJs = file_get_contents(dirname(__DIR__) . '/assets/js/admin.js');
assert_contract(is_string($adminJs), 'Admin JS should be readable.');
assert_contract(strpos($adminJs, 'map-studio-admin__region-colors-json') !== false, 'Admin JS should synchronize region color JSON.');
assert_contract(strpos($adminJs, 'map_studio_region_color') !== false, 'Admin JS should manage the selected region color picker.');

$mapSettingsFields = file_get_contents(dirname(__DIR__) . '/includes/Admin/MapSettingsFields.php');
assert_contract(is_string($mapSettingsFields), 'Map settings fields should be readable.');
assert_contract(strpos($mapSettingsFields, 'map-studio-admin__switch-control') !== false, 'Region list toggle should render a separate switch control.');
assert_contract(strpos($mapSettingsFields, 'map_studio_region_list_position') !== false, 'Map settings should render region list position choices.');

$frontendJs = file_get_contents(dirname(__DIR__) . '/assets/js/frontend.js');
assert_contract(is_string($frontendJs), 'Frontend JS should be readable.');
assert_contract(strpos($frontendJs, 'window.MapStudio') !== false, 'Frontend JS namespace should be renamed.');
assert_contract(strpos($frontendJs, 'resetMap') !== false, 'Frontend JS should expose map reset behavior.');
assert_contract(strpos($frontendJs, 'zoomToRegion') !== false, 'Frontend JS should zoom toward selected regions.');
assert_contract(strpos($frontendJs, 'getPointAtLength') !== false, 'Bubble anchor should use path geometry sampling.');
assert_contract(strpos($frontendJs, 'map-studio__region-list-button') !== false, 'Frontend JS should bind region list buttons.');

$frontendCss = file_get_contents(dirname(__DIR__) . '/assets/css/frontend.css');
assert_contract(is_string($frontendCss), 'Frontend CSS should be readable.');
assert_contract(strpos($frontendCss, '.map-studio__region:focus') !== false, 'Frontend CSS should control SVG region focus outlines.');
assert_contract(strpos($frontendCss, '.map-studio__reset') !== false, 'Frontend CSS should style the icon-only zoom reset control.');
assert_contract(strpos($frontendCss, '--map-studio-zoom-transition: 650ms') !== false, 'Frontend CSS should use the slower zoom transition.');
assert_contract(strpos($frontendCss, '.map-studio__region-list') !== false, 'Frontend CSS should style the public region list.');
assert_contract(strpos($frontendCss, 'is-region-list-left') !== false, 'Frontend CSS should support left-positioned region lists.');

echo 'All contract checks passed.' . PHP_EOL;
