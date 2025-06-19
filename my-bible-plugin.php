<?php
/*
Plugin Name: My Bible Plugin
Description: عرض الكتاب المقدس مع بحث متقدم، فلتر العهد، شواهد، تنقل محسن، دعم الوضع الليلي، قراءة صوتية، إنشاء صور، فهرس للأسفار، وخريطة موقع مخصصة.
Version: 2.2.0
Author: اسمك (تم التحديث بواسطة Gemini)
Text Domain: my-bible-plugin
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit;
}

define('MY_BIBLE_PLUGIN_VERSION', '2.2.0');
define('MY_BIBLE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MY_BIBLE_PLUGIN_URL', plugin_dir_url(__FILE__));

// --- تضمين ملف الدوال المساعدة أولاً ---
if (file_exists(MY_BIBLE_PLUGIN_DIR . 'includes/helpers.php')) {
    require_once MY_BIBLE_PLUGIN_DIR . 'includes/helpers.php';
} else {
    // ... رسالة خطأ ...
}

if (!function_exists('my_bible_log_error')) {
    function my_bible_log_error($message, $context = '') {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[My Bible Plugin] ' . ($context ? '[' . $context . '] ' : '') . $message);
        }
    }
}

function my_bible_enqueue_scripts() {
    try {
        wp_enqueue_script('jquery');
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css', array(), '5.15.4');
        wp_enqueue_style('my-bible-styles', MY_BIBLE_PLUGIN_URL . 'assets/css/bible-styles.css', array(), MY_BIBLE_PLUGIN_VERSION);
        wp_enqueue_style('my-bible-dark-mode', MY_BIBLE_PLUGIN_URL . 'assets/css/dark-mode.css', array('my-bible-styles'), MY_BIBLE_PLUGIN_VERSION);
        wp_enqueue_script('my-bible-frontend', MY_BIBLE_PLUGIN_URL . 'assets/js/bible-frontend.js', array('jquery'), MY_BIBLE_PLUGIN_VERSION, true);
        wp_enqueue_script('my-bible-copy', MY_BIBLE_PLUGIN_URL . 'assets/js/bible-copy.js', array(), MY_BIBLE_PLUGIN_VERSION, true);

        $options = get_option('my_bible_options');
        $default_dark_mode = isset($options['default_dark_mode']) && $options['default_dark_mode'] === '1';
        $default_testament_view_db = isset($options['default_testament_view_db']) ? $options['default_testament_view_db'] : 'all';

        global $wpdb;
        $testament_values_from_db = $wpdb->get_col("SELECT DISTINCT testament FROM " . $wpdb->prefix . "bible_verses WHERE testament != '' ORDER BY testament ASC");
        if ($wpdb->last_error) { my_bible_log_error($wpdb->last_error, 'DB Error loading testaments'); $testament_values_from_db = array(); }

        $testaments_for_js = array('all' => __('الكل', 'my-bible-plugin'));
        if ($testament_values_from_db) {
            foreach ($testament_values_from_db as $test_val) {
                $testaments_for_js[$test_val] = esc_html($test_val);
            }
        }

        $bible_page_for_url = get_page_by_path('bible');
        $base_url_path = 'bible'; 
        if ($bible_page_for_url instanceof WP_Post) {
            $base_url_path = get_page_uri($bible_page_for_url->ID);
        }
        
        $image_fonts_data_php = array(
            'noto_naskh_arabic' => array('label' => __('خط نسخ (افتراضي)', 'my-bible-plugin'), 'family' => '"Noto Naskh Arabic", Arial, Tahoma, sans-serif'),
            'amiri' => array('label' => __('خط أميري', 'my-bible-plugin'), 'family' => 'Amiri, Georgia, serif'),
            'tahoma' => array('label' => __('خط تاهوما', 'my-bible-plugin'), 'family' => 'Tahoma, Geneva, sans-serif'),
            'arial' => array('label' => __('خط آريال', 'my-bible-plugin'), 'family' => 'Arial, Helvetica, sans-serif'),
            'times_new_roman' => array('label' => __('خط تايمز نيو رومان', 'my-bible-plugin'), 'family' => '"Times New Roman", Times, serif')
        );
        $image_backgrounds_data_php = array(
            'gradient_purple_blue' => array('type' => 'gradient', 'colors' => array('#4B0082', '#00008B', '#2F4F4F'), 'label' => __('تدرج بنفسجي-أزرق', 'my-bible-plugin'), 'textColor' => '#FFFFFF'),
            'gradient_blue_green' => array('type' => 'gradient', 'colors' => array('#007bff', '#28a745', '#17a2b8'), 'label' => __('تدرج أزرق-أخضر', 'my-bible-plugin'), 'textColor' => '#FFFFFF' ),
            'solid_dark_grey' => array('type' => 'solid', 'color' => '#343a40', 'label' => __('رمادي داكن ثابت', 'my-bible-plugin'), 'textColor' => '#FFFFFF'),
            'solid_light_beige' => array('type' => 'solid', 'color' => '#f5f5dc', 'label' => __('بيج فاتح ثابت', 'my-bible-plugin'), 'textColor' => '#222222' ),
        );

        $frontend_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('bible_ajax_nonce'),
            'base_url' => home_url(trailingslashit($base_url_path)),
            'plugin_url' => MY_BIBLE_PLUGIN_URL,
            'site_name' => get_bloginfo('name'),
            'default_dark_mode' => $default_dark_mode,
            'default_testament_view' => $default_testament_view_db,
            'testaments' => $testaments_for_js,
            'image_fonts_data' => $image_fonts_data_php,
            'image_backgrounds_data' => $image_backgrounds_data_php,
            'image_generator' => array(
                'generating_image' => __('جارٍ إنشاء الصورة...', 'my-bible-plugin'),
                'download_image' => __('تحميل الصورة', 'my-bible-plugin'),
                'website_credit' => get_bloginfo('name'),
            ),
            'localized_strings' => array( /* ... array content ... */ )
        );

        wp_localize_script('my-bible-frontend', 'bibleFrontend', $frontend_data);
    } catch (Exception $e) {
        my_bible_log_error($e->getMessage(), 'Error in my_bible_enqueue_scripts');
    }
}
add_action('wp_enqueue_scripts', 'my_bible_enqueue_scripts');

if (file_exists(MY_BIBLE_PLUGIN_DIR . 'includes/rewrite.php')) { require_once MY_BIBLE_PLUGIN_DIR . 'includes/rewrite.php'; }
if (file_exists(MY_BIBLE_PLUGIN_DIR . 'includes/templates.php')) { require_once MY_BIBLE_PLUGIN_DIR . 'includes/templates.php'; }
if (file_exists(MY_BIBLE_PLUGIN_DIR . 'includes/shortcodes.php')) { require_once MY_BIBLE_PLUGIN_DIR . 'includes/shortcodes.php'; }
if (file_exists(MY_BIBLE_PLUGIN_DIR . 'includes/ajax.php')) { require_once MY_BIBLE_PLUGIN_DIR . 'includes/ajax.php'; }
if (file_exists(MY_BIBLE_PLUGIN_DIR . 'includes/sitemap.php')) { require_once MY_BIBLE_PLUGIN_DIR . 'includes/sitemap.php'; }

function my_bible_create_table() { /* ... كما كان ... */ }
register_activation_hook(__FILE__, 'my_bible_create_table');

function my_bible_create_pages() { /* ... كما كان ... */ }
register_activation_hook(__FILE__, 'my_bible_create_pages');

function my_bible_deactivation() { flush_rewrite_rules(); }
register_deactivation_hook(__FILE__, 'my_bible_deactivation');

function my_bible_load_textdomain() { load_plugin_textdomain('my-bible-plugin', false, dirname(plugin_basename(__FILE__)) . '/languages'); }
add_action('plugins_loaded', 'my_bible_load_textdomain');

function my_bible_settings_menu() { add_options_page( __('إعدادات إضافة الكتاب المقدس', 'my-bible-plugin'), __('الكتاب المقدس', 'my-bible-plugin'), 'manage_options', 'my-bible-settings', 'my_bible_settings_page_content' ); }
add_action('admin_menu', 'my_bible_settings_menu');

function my_bible_register_settings() { /* ... كما كان ... */ }
add_action('admin_init', 'my_bible_register_settings');

function my_bible_general_settings_section_callback() { echo '<p>' . esc_html__('اختر الإعدادات العامة لإضافة الكتاب المقدس.', 'my-bible-plugin') . '</p>'; }

function my_bible_random_book_field_callback() { 
    global $wpdb; $options = get_option('my_bible_options'); $selected_book = isset($options['bible_random_book']) ? $options['bible_random_book'] : ''; $table_name = $wpdb->prefix . 'bible_verses'; 
    $books = $wpdb->get_col("SELECT DISTINCT book FROM $table_name ORDER BY book ASC");
    if ($wpdb->last_error) { my_bible_log_error($wpdb->last_error, 'Settings - loading random books'); echo '<p>' . esc_html__('خطأ في جلب قائمة الأسفار.', 'my-bible-plugin') . '</p>'; return; } 
    if (empty($books)) { echo '<p>' . esc_html__('لم يتم العثور على أسفار في قاعدة البيانات.', 'my-bible-plugin') . '</p>'; return; } 
    echo '<select id="bible_random_book_select" name="my_bible_options[bible_random_book]">'; echo '<option value="">' . esc_html__('كل الأسفار', 'my-bible-plugin') . '</option>'; 
    foreach ($books as $book) { echo '<option value="' . esc_attr($book) . '" ' . selected($selected_book, $book, false) . '>' . esc_html($book) . '</option>'; } 
    echo '</select>'; echo '<p class="description">' . esc_html__('اختر سفراً للآيات العشوائية واليومية.', 'my-bible-plugin') . '</p>'; 
}

function my_bible_default_dark_mode_field_callback() { /* ... كما كان ... */ }
function my_bible_default_testament_view_db_field_callback() { /* ... كما كان ... */ }
function my_bible_options_sanitize($input) { /* ... كما كان ... */ return $input; }
function my_bible_settings_page_content() { /* ... كما كان ... */ }
add_filter('widget_text', 'do_shortcode');
?>
