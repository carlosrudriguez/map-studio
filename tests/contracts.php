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
    'includes/Admin/MapDashboardSection.php',
    'includes/Admin/MapSettingsFields.php',
    'includes/Admin/MapMetaBox.php',
    'includes/Frontend/Shortcode.php',
];

foreach ($map_studio_files as $map_studio_file) {
    $map_studio_path = __DIR__ . '/../' . $map_studio_file;

    if (file_exists($map_studio_path)) {
        require_once $map_studio_path;
    }
}

if (!defined('MAP_STUDIO_VERSION')) {
    define('MAP_STUDIO_VERSION', '1.0.1');
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
    $GLOBALS['map_studio_contract_enqueued_style_versions'][$handle][] = $ver;
}

function wp_enqueue_script(string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, array|bool $args = []): void {
    $GLOBALS['map_studio_contract_enqueued_scripts'][] = $handle;
    $GLOBALS['map_studio_contract_enqueued_script_versions'][$handle][] = $ver;
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

function wp_nonce_field(string $action, string $name): void {
    echo '<input type="hidden" name="' . esc_attr($name) . '" value="contract-nonce">';
}

function wp_editor(string $content, string $editor_id, array $settings = []): void {
    $textareaName = isset($settings['textarea_name']) && is_string($settings['textarea_name']) ? $settings['textarea_name'] : $editor_id;
    echo '<textarea id="' . esc_attr($editor_id) . '" name="' . esc_attr($textareaName) . '">' . esc_textarea($content) . '</textarea>';
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
        'regionListHiddenByDefault' => '1',
        'legend' => '<p>Legend <strong>content</strong></p><script>alert(1)</script>',
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
assert_contract($payload['regionListHiddenByDefault'] === true, 'Region list hidden-by-default setting should be saved when enabled.');
assert_contract(isset($payload['legend']) && strpos($payload['legend'], '<strong>content</strong>') !== false, 'Map legend content should be saved.');
assert_contract(isset($payload['legend']) && strpos($payload['legend'], '<script>') === false, 'Map legend content should be sanitized.');

$defaultSettingsPayload = \MapStudio\MapMeta::sanitizePayload([], '', $maps['MX']);
assert_contract($defaultSettingsPayload['regionListEnabled'] === false, 'Region list setting should default to disabled.');
assert_contract($defaultSettingsPayload['regionListPosition'] === 'right', 'Region list position should default to right.');
assert_contract($defaultSettingsPayload['regionListHiddenByDefault'] === false, 'Region list hidden-by-default setting should default to disabled.');
assert_contract(($defaultSettingsPayload['legend'] ?? null) === '', 'Map legend should default to empty.');

$invalidSettingsPayload = \MapStudio\MapMeta::sanitizePayload(['regionListPosition' => 'top'], '', $maps['MX']);
assert_contract($invalidSettingsPayload['regionListPosition'] === 'right', 'Invalid region list position should fall back to right.');

$disabledHiddenSettingsPayload = \MapStudio\MapMeta::sanitizePayload(['regionListEnabled' => '0', 'regionListHiddenByDefault' => '1'], '', $maps['MX']);
assert_contract($disabledHiddenSettingsPayload['regionListHiddenByDefault'] === false, 'Hidden-by-default should not be enabled when the region list is disabled.');

$emptyLegendPayload = \MapStudio\MapMeta::sanitizePayload(['legend' => '   '], '', $maps['MX']);
assert_contract(($emptyLegendPayload['legend'] ?? null) === '', 'Empty map legend content should not be saved.');

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

$GLOBALS['map_studio_contract_post_meta'][\MapStudio\MapMeta::META_KEY] = '{"mapId":"MX","regions":{"MX-JAL":"<p>Jalisco from JSON meta</p>"},"regionColors":{"MX-SON":"#123456"},"colors":{"inactive":"#222222"},"regionListEnabled":true,"regionListPosition":"left","regionListHiddenByDefault":true,"legend":"<p>JSON legend</p>"}';
$jsonMetaPayload = \MapStudio\MapMeta::get(123, $maps['MX']);
assert_contract(isset($jsonMetaPayload['regions']['MX-JAL']), 'JSON string meta payload should be decoded.');
assert_contract($jsonMetaPayload['regionColors']['MX-SON'] === '#123456', 'JSON string meta region colors should be decoded.');
assert_contract($jsonMetaPayload['colors']['inactive'] === '#222222', 'JSON string meta colors should be decoded.');
assert_contract($jsonMetaPayload['regionListEnabled'] === true, 'JSON string meta region list setting should be decoded.');
assert_contract($jsonMetaPayload['regionListPosition'] === 'left', 'JSON string meta region list position should be decoded.');
assert_contract($jsonMetaPayload['regionListHiddenByDefault'] === true, 'JSON string meta region list hidden setting should be decoded.');
assert_contract(($jsonMetaPayload['legend'] ?? null) === '<p>JSON legend</p>', 'JSON string meta legend should be decoded.');
unset($GLOBALS['map_studio_contract_post_meta']);

$GLOBALS['map_studio_contract_post_meta'][\MapStudio\MapMeta::META_KEY] = '{"mapId":"MX","regions":{"MX-JAL":"<p>Jalisco visible content.</p>"},"regionColors":{"MX-SON":"#aa5500"},"colors":{"inactive":"#d1d5db"},"regionListEnabled":true,"regionListPosition":"left","legend":"<p>Map legend content.</p>"}';
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
assert_contract(strpos($publishedShortcode, 'class="map-studio__bubble-pointer" aria-hidden="true"') !== false, 'Published maps should render a targetable bubble pointer.');
assert_contract(strpos($publishedShortcode, 'class="map-studio__region-list is-position-left"') !== false, 'Enabled maps should render a public region list with a position class.');
assert_contract(strpos($publishedShortcode, 'is-region-list-left') !== false, 'Left-positioned region list should add a frontend layout class.');
assert_contract(strpos($publishedShortcode, 'class="map-studio__region-list-item" role="listitem"') !== false, 'Region list entries should have a targetable item class.');
assert_contract(strpos($publishedShortcode, 'class="map-studio__region-list-button" data-map-studio-region-key="MX-JAL"') !== false, 'Region list should include active regions.');
assert_contract(strpos($publishedShortcode, 'class="map-studio__region-list-label">Jalisco</span>') !== false, 'Region list labels should have a targetable label class.');
assert_contract(strpos($publishedShortcode, 'Jalisco') !== false, 'Region list should render region labels.');
assert_contract(strpos($publishedShortcode, 'class="map-studio__region-list-button" data-map-studio-region-key="MX-SON"') === false, 'Region list should not include color-only regions.');
assert_contract(strpos($publishedShortcode, 'map-studio__region-list-toggle') === false, 'Visible region lists should not render a sidebar toggle button.');
assert_contract(strpos($publishedShortcode, 'class="map-studio__legend-toggle"') !== false, 'Maps with legend content should render a legend action button.');
assert_contract(strpos($publishedShortcode, 'aria-controls="map-studio-legend-map-11-') !== false, 'Legend action button should target a unique legend content container.');
assert_contract(strpos($publishedShortcode, 'class="map-studio__legend-content" id="map-studio-legend-map-11-') !== false, 'Maps with legend content should render a hidden legend content container.');
assert_contract(strpos($publishedShortcode, '<p>Map legend content.</p>') !== false, 'Maps with legend content should render sanitized legend content.');
$publishedScripts = $GLOBALS['map_studio_contract_enqueued_scripts'] ?? [];
assert_contract(in_array('map-studio-viewbox-animation', $publishedScripts, true), 'Published maps should enqueue the viewBox animation helper.');
assert_contract(in_array('map-studio-frontend', $publishedScripts, true), 'Published maps should enqueue the frontend interaction script.');
$publishedStyleVersions = $GLOBALS['map_studio_contract_enqueued_style_versions']['map-studio-frontend'] ?? [];
$publishedScriptVersions = $GLOBALS['map_studio_contract_enqueued_script_versions']['map-studio-frontend'] ?? [];
assert_contract($publishedStyleVersions !== [] && end($publishedStyleVersions) !== MAP_STUDIO_VERSION, 'Frontend CSS should use a file-based asset version so browser cache refreshes after CSS changes.');
assert_contract($publishedScriptVersions !== [] && end($publishedScriptVersions) !== MAP_STUDIO_VERSION, 'Frontend JS should use a file-based asset version so browser cache refreshes after JS changes.');
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
assert_contract(strpos($publishedWithoutList, 'map-studio__region-list-toggle') === false, 'Disabled region list setting should not render a sidebar toggle button.');
assert_contract(strpos($publishedWithoutList, 'map-studio__legend-toggle') === false, 'Maps without legend content should not render a legend action button.');

$GLOBALS['map_studio_contract_post_meta'][\MapStudio\MapMeta::META_KEY] = '{"mapId":"MX","regions":{"MX-JAL":"<p>Jalisco visible content.</p>"},"regionListEnabled":true,"regionListPosition":"right","regionListHiddenByDefault":true}';
$GLOBALS['map_studio_contract_posts'][13] = (object) [
    'ID' => 13,
    'post_type' => \MapStudio\Admin\MapPostType::POST_TYPE,
    'post_status' => 'publish',
];
$publishedHiddenList = (new \MapStudio\Frontend\Shortcode())->render(['id' => 13]);
assert_contract(strpos($publishedHiddenList, 'has-collapsible-region-list') !== false, 'Hidden region lists should mark the map as collapsible.');
assert_contract(strpos($publishedHiddenList, 'is-region-list-collapsed') !== false, 'Hidden region lists should render collapsed by default.');
assert_contract(strpos($publishedHiddenList, 'class="map-studio__region-list-toggle"') !== false, 'Hidden region lists should render a sidebar toggle button.');
assert_contract(strpos($publishedHiddenList, 'aria-expanded="false"') !== false, 'Hidden region list toggle should start collapsed.');
assert_contract(strpos($publishedHiddenList, 'aria-controls="map-studio-region-list-map-13-') !== false, 'Hidden region list toggle should target the public region list.');
assert_contract(strpos($publishedHiddenList, 'class="map-studio__region-list is-position-right"') !== false, 'Hidden region lists should still render the targetable public list.');
assert_contract(strpos($publishedHiddenList, 'class="map-studio__region-list is-position-right" aria-label="Map regions" id="map-studio-region-list-map-13-') !== false, 'Hidden region list should include a unique controlled ID.');
assert_contract(strpos($publishedHiddenList, ' hidden>') !== false, 'Hidden region list should use the hidden attribute before user activation.');
unset($GLOBALS['map_studio_contract_post_meta'], $GLOBALS['map_studio_contract_posts']);

$GLOBALS['map_studio_contract_current_user_can']['manage_options'] = true;
$GLOBALS['map_studio_contract_post_meta'][\MapStudio\MapMeta::META_KEY] = '{"mapId":"MX","regions":{"MX-JAL":"<p>Jalisco visible content.</p>"},"regionColors":{"MX-JAL":"#123456"},"colors":{"inactive":"#d1d5db"},"regionListEnabled":true,"regionListPosition":"right"}';
ob_start();
(new \MapStudio\Admin\MapMetaBox())->render((object) ['ID' => 20]);
$adminMarkup = ob_get_clean();
assert_contract(is_string($adminMarkup), 'Admin map editor should render markup.');
assert_contract(strpos($adminMarkup, 'map-studio-admin__section is-setup') !== false, 'Admin editor should render a Map Setup section.');
assert_contract(strpos($adminMarkup, 'map-studio-admin__section is-content') !== false, 'Admin editor should render a Region Content section.');
assert_contract(strpos($adminMarkup, 'map-studio-admin__section is-appearance') !== false, 'Admin editor should render an Appearance section.');
assert_contract(strpos($adminMarkup, 'map-studio-admin__section-title') !== false, 'Admin editor sections should render titled headers.');
assert_contract(strpos($adminMarkup, 'map-studio-admin__appearance-grid') !== false, 'Appearance controls should render in a dedicated layout.');
assert_contract(strpos($adminMarkup, 'map_studio_region_list_hidden_by_default') !== false, 'Admin settings should render the hidden-by-default region list option.');
assert_contract(strpos($adminMarkup, 'map_studio_legend') !== false, 'Admin editor should render a WYSIWYG map legend field.');
assert_contract(strpos($adminMarkup, 'Map Legend') !== false, 'Admin editor should label the map legend field.');
unset($GLOBALS['map_studio_contract_current_user_can'], $GLOBALS['map_studio_contract_post_meta']);

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
assert_contract(file_exists(dirname(__DIR__) . '/assets/css/admin-dashboard.css'), 'Admin dashboard CSS is missing.');
assert_contract(file_exists(dirname(__DIR__) . '/assets/js/viewbox-animation.js'), 'ViewBox animation JS is missing.');
assert_contract(file_exists(dirname(__DIR__) . '/assets/js/frontend.js'), 'Frontend JS is missing.');
assert_contract(file_exists(dirname(__DIR__) . '/assets/css/frontend.css'), 'Frontend CSS is missing.');

$adminJs = file_get_contents(dirname(__DIR__) . '/assets/js/admin.js');
assert_contract(is_string($adminJs), 'Admin JS should be readable.');
assert_contract(strpos($adminJs, 'map-studio-admin__region-colors-json') !== false, 'Admin JS should synchronize region color JSON.');
assert_contract(strpos($adminJs, 'map_studio_region_color') !== false, 'Admin JS should manage the selected region color picker.');

$adminDashboardCss = file_get_contents(dirname(__DIR__) . '/assets/css/admin-dashboard.css');
assert_contract(is_string($adminDashboardCss), 'Admin dashboard CSS should be readable.');
assert_contract(strpos($adminDashboardCss, '.map-studio-admin__section') !== false, 'Admin dashboard CSS should style dashboard sections.');
assert_contract(strpos($adminDashboardCss, '.map-studio-admin__setup-grid') !== false, 'Admin dashboard CSS should style the map setup section.');
assert_contract(strpos($adminDashboardCss, '.map-studio-admin__appearance-grid') !== false, 'Admin dashboard CSS should style the appearance section.');

$mapSettingsFields = file_get_contents(dirname(__DIR__) . '/includes/Admin/MapSettingsFields.php');
assert_contract(is_string($mapSettingsFields), 'Map settings fields should be readable.');
assert_contract(strpos($mapSettingsFields, 'map-studio-admin__switch-control') !== false, 'Region list toggle should render a separate switch control.');
assert_contract(strpos($mapSettingsFields, 'map_studio_region_list_position') !== false, 'Map settings should render region list position choices.');
assert_contract(strpos($mapSettingsFields, 'map_studio_region_list_hidden_by_default') !== false, 'Map settings should render the hidden-by-default option.');

$frontendJs = file_get_contents(dirname(__DIR__) . '/assets/js/frontend.js');
assert_contract(is_string($frontendJs), 'Frontend JS should be readable.');
assert_contract(strpos($frontendJs, 'window.MapStudio') !== false, 'Frontend JS namespace should be renamed.');
assert_contract(strpos($frontendJs, 'MapStudioViewBoxAnimation') !== false, 'Frontend JS should use the animated viewBox helper.');
assert_contract(strpos($frontendJs, 'resetMap') !== false, 'Frontend JS should expose map reset behavior.');
assert_contract(strpos($frontendJs, 'zoomToRegion') !== false, 'Frontend JS should zoom toward selected regions.');
assert_contract(strpos($frontendJs, 'originalViewBox') !== false, 'Frontend JS should preserve the original SVG viewBox.');
assert_contract(strpos($frontendJs, '.set(targetViewBox') !== false, 'Frontend JS should animate to the target SVG viewBox.');
assert_contract(strpos($frontendJs, '--map-studio-zoom-scale') === false, 'Frontend JS should not use CSS scale variables for map zoom.');
assert_contract(strpos($frontendJs, 'getPointAtLength') !== false, 'Bubble anchor should use path geometry sampling.');
assert_contract(strpos($frontendJs, '--map-studio-bubble-pointer-x') !== false, 'Frontend JS should position the bubble pointer toward the selected region.');
assert_contract(strpos($frontendJs, 'is-above-region') !== false, 'Frontend JS should mark bubbles positioned above their selected region.');
assert_contract(strpos($frontendJs, 'is-below-region') !== false, 'Frontend JS should mark bubbles positioned below their selected region.');
assert_contract(strpos($frontendJs, 'map-studio__region-list-button') !== false, 'Frontend JS should bind region list buttons.');
assert_contract(strpos($frontendJs, 'map-studio__region-list-toggle') !== false, 'Frontend JS should bind the region list toggle button.');
assert_contract(strpos($frontendJs, 'is-region-list-collapsed') !== false, 'Frontend JS should toggle the collapsed region list class.');
assert_contract(strpos($frontendJs, 'aria-expanded') !== false, 'Frontend JS should update sidebar toggle expanded state.');
assert_contract(strpos($frontendJs, 'map-studio__legend-toggle') !== false, 'Frontend JS should bind the legend toggle button.');
assert_contract(strpos($frontendJs, 'map-studio__legend-content') !== false, 'Frontend JS should read hidden legend content.');
assert_contract(strpos($frontendJs, 'is-legend') !== false, 'Frontend JS should mark legend bubbles so they render without a region pointer.');

$viewBoxAnimationJs = file_get_contents(dirname(__DIR__) . '/assets/js/viewbox-animation.js');
assert_contract(is_string($viewBoxAnimationJs), 'ViewBox animation JS should be readable.');
assert_contract(strpos($viewBoxAnimationJs, 'window.MapStudioViewBoxAnimation') !== false, 'ViewBox animation helper should expose a global namespace.');
assert_contract(strpos($viewBoxAnimationJs, 'requestAnimationFrame') !== false, 'ViewBox animation helper should animate with requestAnimationFrame.');
assert_contract(strpos($viewBoxAnimationJs, 'cancelAnimationFrame') !== false, 'ViewBox animation helper should cancel interrupted animations.');
assert_contract(strpos($viewBoxAnimationJs, 'prefers-reduced-motion: reduce') !== false, 'ViewBox animation helper should respect reduced-motion preferences.');

$frontendCss = file_get_contents(dirname(__DIR__) . '/assets/css/frontend.css');
assert_contract(is_string($frontendCss), 'Frontend CSS should be readable.');
assert_contract(strpos($frontendCss, '.map-studio__region:focus') !== false, 'Frontend CSS should control SVG region focus outlines.');
assert_contract(strpos($frontendCss, '.map-studio__reset') !== false, 'Frontend CSS should style the icon-only zoom reset control.');
assert_contract(strpos($frontendCss, 'transform: translate(var(--map-studio-zoom-x)') === false, 'Frontend CSS should not scale the SVG with transforms.');
assert_contract(strpos($frontendCss, 'will-change: transform') === false, 'Frontend CSS should not force the SVG into a transform raster layer.');
assert_contract(strpos($frontendCss, '.map-studio__bubble-pointer') !== false, 'Frontend CSS should style the bubble pointer.');
assert_contract(strpos($frontendCss, '.map-studio__bubble.is-above-region .map-studio__bubble-pointer') !== false, 'Frontend CSS should place the pointer under bubbles that sit above regions.');
assert_contract(strpos($frontendCss, '.map-studio__bubble.is-below-region .map-studio__bubble-pointer') !== false, 'Frontend CSS should place the pointer above bubbles that sit below regions.');
assert_contract(strpos($frontendCss, '.map-studio__bubble-pointer::before') !== false, 'Frontend CSS should draw the bubble pointer border as a triangle.');
assert_contract(strpos($frontendCss, '.map-studio__bubble-pointer::after') !== false, 'Frontend CSS should draw the bubble pointer fill as a triangle.');
assert_contract(strpos($frontendCss, 'rotate(45deg)') === false, 'Frontend CSS should not use a rotated square for the bubble pointer.');
assert_contract(
    preg_match('/\.map-studio__close\s*\{[^}]*position:\s*absolute;/s', $frontendCss) === 1,
    'Bubble close button should not occupy a separate flex row above the content.'
);
assert_contract(
    preg_match('/\.map-studio__bubble-content\s*>\s*:first-child\s*\{[^}]*padding-right:\s*calc\(var\(--map-studio-bubble-close-size\)\s*\+\s*var\(--map-studio-space-xs\)\);/s', $frontendCss) === 1,
    'Bubble first content block should reserve space for the overlaid close button.'
);
assert_contract(strpos($frontendCss, '.map-studio__region-list') !== false, 'Frontend CSS should style the public region list.');
assert_contract(strpos($frontendCss, '.map-studio__region-list-item') !== false, 'Frontend CSS should expose a public region list item hook.');
assert_contract(strpos($frontendCss, '.map-studio__region-list-label') !== false, 'Frontend CSS should expose a public region list label hook.');
assert_contract(strpos($frontendCss, 'is-region-list-left') !== false, 'Frontend CSS should support left-positioned region lists.');
assert_contract(strpos($frontendCss, '.map-studio__region-list-toggle') !== false, 'Frontend CSS should style the region list toggle button.');
assert_contract(strpos($frontendCss, '.map-studio.is-region-list-collapsed .map-studio__region-list') !== false, 'Frontend CSS should hide collapsed region lists.');
assert_contract(strpos($frontendCss, '.map-studio__legend-toggle') !== false, 'Frontend CSS should style the legend action button.');
assert_contract(strpos($frontendCss, '.map-studio__bubble.is-legend .map-studio__bubble-pointer') !== false, 'Frontend CSS should hide the region pointer for legend bubbles.');
$responsiveRulePositions = array_filter([
    strpos($frontendCss, '@media'),
    strpos($frontendCss, '@container'),
], 'is_int');
assert_contract($responsiveRulePositions !== [], 'Frontend CSS should define responsive rules for narrow map layouts.');
$firstResponsiveRulePosition = min($responsiveRulePositions);
$desktopFrontendCss = substr($frontendCss, 0, $firstResponsiveRulePosition);
assert_contract(strpos($desktopFrontendCss, '.map-studio.has-collapsible-region-list.has-region-list .map-studio__body') === false, 'Collapsible region lists should not force mobile stacking at desktop widths.');
assert_contract(strpos($frontendCss, '.map-studio.has-collapsible-region-list.has-region-list .map-studio__body') !== false, 'Collapsible region lists should open in a single readable column instead of a squeezed sidebar.');
assert_contract(strpos($frontendCss, '@media (max-width: 782px)') !== false, 'Frontend CSS should stack region lists before narrow mobile layouts squeeze sidebar text.');
assert_contract(strpos($frontendCss, 'container-name: map-studio') !== false, 'Frontend CSS should name the map component container for responsive region list layout.');
assert_contract(strpos($frontendCss, 'container-type: inline-size') !== false, 'Frontend CSS should use the map component width for responsive region list layout.');
assert_contract(strpos($frontendCss, '@container map-studio (max-width: 782px)') !== false, 'Frontend CSS should stack region lists when the map container is narrow.');
assert_contract(strpos($frontendCss, '.map-studio__actions button:focus') !== false, 'Frontend CSS should suppress native focus outlines on action buttons.');
assert_contract(strpos($frontendCss, 'border-color: var(--map-studio-color-stroke)') !== false, 'Frontend CSS should avoid visible focus border changes on action buttons.');

echo 'All contract checks passed.' . PHP_EOL;
