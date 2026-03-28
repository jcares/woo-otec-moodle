<?php
if (PHP_SAPI !== 'cli') {
    echo "Run in CLI only.\n";
    exit(1);
}

$wp_load = 'C:/xampp/htdocs/wordpress/wp-load.php';
if (!file_exists($wp_load)) {
    echo "wp-load.php not found: $wp_load\n";
    exit(1);
}

require_once $wp_load;

if (!class_exists('Woo_OTEC_Moodle_Sync')) {
    $plugin_main = WP_PLUGIN_DIR . '/woo-otec-moodle/woo-otec-moodle.php';
    if (file_exists($plugin_main)) {
        require_once $plugin_main;
    }
}

if (!class_exists('Woo_OTEC_Moodle_Sync')) {
    echo "Woo_OTEC_Moodle_Sync not available.\n";
    exit(1);
}

$sync = Woo_OTEC_Moodle_Sync::instance();

// 1) Existing product test: values must be preserved if incoming data is empty.
$existing = get_posts(array(
    'post_type'      => 'product',
    'post_status'    => array('publish', 'draft', 'private'),
    'posts_per_page' => 1,
    'meta_query'     => array(
        array(
            'key'     => '_moodle_id',
            'compare' => 'EXISTS',
        ),
    ),
    'fields'         => 'ids',
));

if (empty($existing)) {
    echo "No existing Moodle-linked product found for preservation test.\n";
    exit(1);
}

$existing_id = (int) $existing[0];
$moodle_id = (int) get_post_meta($existing_id, '_moodle_id', true);
$old_sku = (string) get_post_meta($existing_id, '_sku', true);
$old_duration = (string) get_post_meta($existing_id, '_duration', true);
$old_teacher = (string) get_post_meta($existing_id, '_instructor', true);

if ($old_duration === '') {
    $old_duration = 'duracion-qa-previa';
    update_post_meta($existing_id, '_duration', $old_duration);
}
if ($old_teacher === '') {
    $old_teacher = 'Relator QA Previo';
    update_post_meta($existing_id, '_instructor', $old_teacher);
}

$payload_existing = array(
    'id'         => $moodle_id,
    'fullname'   => '',
    'summary'    => '',
    'teacher'    => 'No asignado',
    'duration'   => '',
    'categoryid' => (int) get_post_meta($existing_id, '_moodle_category_id', true),
    'startdate'  => 0,
    'enddate'    => 0,
);

$res_existing = $sync->sync_single_course($payload_existing);
$new_sku = (string) get_post_meta($existing_id, '_sku', true);
$new_duration = (string) get_post_meta($existing_id, '_duration', true);
$new_teacher = (string) get_post_meta($existing_id, '_instructor', true);

$preserved = ($old_sku === $new_sku) && ($old_duration === $new_duration) && ($old_teacher === $new_teacher);

echo "Existing course sync status: " . ($res_existing['status'] ?? 'n/a') . "\n";
echo "SKU preserved: " . ($old_sku === $new_sku ? 'YES' : 'NO') . " ($old_sku -> $new_sku)\n";
echo "Duration preserved: " . ($old_duration === $new_duration ? 'YES' : 'NO') . " ($old_duration -> $new_duration)\n";
echo "Teacher preserved: " . ($old_teacher === $new_teacher ? 'YES' : 'NO') . " ($old_teacher -> $new_teacher)\n";

// 2) New product test: SKU must be OTEC-YYMMID with current YYMM.
$new_moodle_id = (int) (990000 + wp_rand(10, 999));
$current_yymm = wp_date('ym', current_time('timestamp'));
$expected_prefix = 'OTEC-' . $current_yymm . $new_moodle_id;

$payload_new = array(
    'id'         => $new_moodle_id,
    'fullname'   => 'QA SKU Test ' . $new_moodle_id,
    'summary'    => 'QA summary for sku format test',
    'teacher'    => 'Relator QA',
    'duration'   => '8 horas',
    'categoryid' => 0,
    'startdate'  => 0,
    'enddate'    => 0,
    'modality'   => 'online',
    'format'     => 'topics',
);

$res_new = $sync->sync_single_course($payload_new);
$new_product_id = $sync->find_product_id($new_moodle_id);
$created_sku = $new_product_id > 0 ? (string) get_post_meta($new_product_id, '_sku', true) : '';
$sku_ok = ($created_sku === $expected_prefix);

echo "New course sync status: " . ($res_new['status'] ?? 'n/a') . "\n";
echo "SKU format expected: $expected_prefix\n";
echo "SKU format actual:   $created_sku\n";
echo "SKU format valid: " . ($sku_ok ? 'YES' : 'NO') . "\n";

if ($new_product_id > 0) {
    wp_delete_post($new_product_id, true);
    echo "Cleanup: deleted QA product ID $new_product_id\n";
}

echo "Overall preservation test: " . ($preserved ? 'PASS' : 'FAIL') . "\n";
echo "Overall sku-format test: " . ($sku_ok ? 'PASS' : 'FAIL') . "\n";

exit(($preserved && $sku_ok) ? 0 : 2);

