<?php
declare(strict_types=1);

/**
 * Contract checks for secure Map Studio draft duplication.
 * WordPress post and metadata APIs use deterministic fixtures from the main suite.
 */

$sourcePayload = [
    'mapId' => 'MX',
    'regions' => ['MX-JAL' => '<p>Jalisco</p>'],
    'regionColors' => ['MX-SON' => '#123456'],
    'colors' => [
        'inactive' => '#111111',
        'active' => '#222222',
        'hover' => '#333333',
        'stroke' => '#ffffff',
        'bubbleBackground' => '#fafafa',
        'bubbleText' => '#121212',
    ],
    'regionListEnabled' => true,
    'regionListPosition' => 'left',
    'regionListHiddenByDefault' => true,
    'regionSearchEnabled' => true,
    'regionSearchPlaceholder' => 'Buscar…',
    'legend' => '<p>Legend</p>',
];

$GLOBALS['map_studio_contract_posts'][21] = (object) [
    'ID' => 21,
    'post_type' => \MapStudio\Admin\MapPostType::POST_TYPE,
    'post_status' => 'publish',
    'post_title' => 'Original map',
];
$GLOBALS['map_studio_contract_post_meta'][\MapStudio\MapMeta::META_KEY] = $sourcePayload;
$GLOBALS['map_studio_contract_insert_result'] = 100;
$GLOBALS['map_studio_contract_inserted_post'] = [];
$GLOBALS['map_studio_contract_saved_post_meta'] = [];

$duplicateAction = new \MapStudio\Admin\MapDuplicateAction();
$newPostId = $duplicateAction->duplicate(21);
$inserted = $GLOBALS['map_studio_contract_inserted_post'];
$savedPayload = $GLOBALS['map_studio_contract_saved_post_meta'][100][\MapStudio\MapMeta::META_KEY] ?? null;
$expectedPayload = \MapStudio\MapMeta::sanitizePayload($sourcePayload, 'MX', $maps['MX']);

assert_contract($newPostId === 100, 'Duplication should return the new draft post ID.');
assert_contract(($inserted['post_type'] ?? '') === \MapStudio\Admin\MapPostType::POST_TYPE, 'Duplicate should use the Map Studio post type.');
assert_contract(($inserted['post_status'] ?? '') === 'draft', 'Duplicate should be created as a draft.');
assert_contract(($inserted['post_title'] ?? '') === 'Copy of Original map', 'Duplicate should use the expected title.');
assert_contract($savedPayload === $expectedPayload, 'Duplicate should copy only the sanitized Map Studio payload.');
assert_contract(($savedPayload['regionSearchEnabled'] ?? false) === true, 'Duplicate should preserve the region-search enabled state.');
assert_contract(($savedPayload['regionSearchPlaceholder'] ?? '') === 'Buscar…', 'Duplicate should preserve the region-search placeholder.');
assert_contract(array_keys($GLOBALS['map_studio_contract_saved_post_meta'][100] ?? []) === [\MapStudio\MapMeta::META_KEY], 'Duplicate should not copy unrelated post metadata.');

$GLOBALS['map_studio_contract_posts'][22] = (object) [
    'ID' => 22,
    'post_type' => 'post',
    'post_title' => 'Wrong type',
];
assert_contract($duplicateAction->duplicate(22) === 0, 'Duplication should reject other post types.');
assert_contract($duplicateAction->duplicate(999) === 0, 'Duplication should reject missing source posts.');

$GLOBALS['map_studio_contract_insert_result'] = (object) ['is_wp_error' => true];
assert_contract($duplicateAction->duplicate(21) === 0, 'Duplication should report insert failures.');

$duplicateActionPhp = file_get_contents(dirname(__DIR__) . '/includes/Admin/MapDuplicateAction.php');
assert_contract(is_string($duplicateActionPhp), 'Duplicate action service should be readable.');
assert_contract(strpos($duplicateActionPhp, 'check_admin_referer') !== false, 'Duplicate requests should verify a nonce.');
assert_contract(strpos($duplicateActionPhp, "current_user_can('edit_post'") !== false, 'Duplicate requests should verify source edit capability.');
assert_contract(strpos($duplicateActionPhp, 'create_posts') !== false, 'Duplicate requests should verify create capability.');
assert_contract(strpos($duplicateActionPhp, 'wp_safe_redirect') !== false, 'Duplicate requests should redirect safely.');
assert_contract(strpos($duplicateActionPhp, 'notice-success') !== false, 'Duplicate success should use a WordPress success notice.');
assert_contract(strpos($duplicateActionPhp, 'notice-error') !== false, 'Duplicate failure should use a WordPress error notice.');

unset(
    $GLOBALS['map_studio_contract_posts'][21],
    $GLOBALS['map_studio_contract_posts'][22],
    $GLOBALS['map_studio_contract_post_meta'],
    $GLOBALS['map_studio_contract_insert_result'],
    $GLOBALS['map_studio_contract_inserted_post'],
    $GLOBALS['map_studio_contract_saved_post_meta']
);

echo 'All duplicate contract checks passed.' . PHP_EOL;
