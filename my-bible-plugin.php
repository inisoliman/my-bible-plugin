<?php
/*
Plugin Name: My Bible Plugin
Description: عرض الكتاب المقدس مع بحث متقدم، فلتر العهد، شواهد، تنقل محسن، دعم الوضع الليلي، قراءة صوتية، إنشاء صور، فهرس للأسفار، وخريطة موقع مخصصة.
Version: 2.0.0
Author: اسمك (تم التحديث بواسطة Gemini)
Text Domain: my-bible-plugin
Domain Path: /languages
*/

// منع الوصول المباشر للملف
if (!defined('ABSPATH')) {
    exit;
}

define('MY_BIBLE_PLUGIN_VERSION', '2.0.0');
define('MY_BIBLE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MY_BIBLE_PLUGIN_URL', plugin_dir_url(__FILE__));

// --- تضمين ملف الدوال المساعدة أولاً وقبل كل شيء ---
if (file_exists(MY_BIBLE_PLUGIN_DIR . 'includes/helpers.php')) {
    require_once MY_BIBLE_PLUGIN_DIR . 'includes/helpers.php';
} else {
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === true) {
        error_log('[My Bible Plugin CRITICAL] helpers.php not found at ' . MY_BIBLE_PLUGIN_DIR . 'includes/helpers.php. Plugin will not function correctly.');
    }
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>My Bible Plugin Error:</strong> Essential helper file not found. The plugin cannot function.</p></div>';
    });
    return; 
}


// تحميل ملفات الـ CSS والـ JS
// Add after the helpers.php include
if (!function_exists('my_bible_log_error')) {
    function my_bible_log_error($message, $context = '') {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[My Bible Plugin] ' . $context . ': ' . $message);
        }
    }
}

// Improve the enqueue function with error handling
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
        $testament_values_from_db = $wpdb->get_col(
            "SELECT DISTINCT testament FROM " . $wpdb->prefix . "bible_verses WHERE testament != '' ORDER BY testament ASC"
        );
        
        if ($wpdb->last_error) {
            my_bible_log_error($wpdb->last_error, 'Database Error in enqueue_scripts');
            $testament_values_from_db = array();
        }
        
        $testaments_for_js = array('all' => __('الكل', 'my-bible-plugin'));
        if ($testament_values_from_db) {
            foreach ($testament_values_from_db as $test_val) {
                $testaments_for_js[$test_val] = $test_val; 
            }
        }

        wp_localize_script('my-bible-frontend', 'bibleFrontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('bible_ajax_nonce'), 
            'base_url' => home_url('/bible/'),
            'show_tashkeel_label' => __('إظهار التشكيل', 'my-bible-plugin'),
            'hide_tashkeel_label' => __('إلغاء التشكيل', 'my-bible-plugin'),
            'read_aloud_label' => __('قراءة بصوت عالٍ', 'my-bible-plugin'),
            'stop_reading_label' => __('إيقاف القراءة', 'my-bible-plugin'),
            'dark_mode_toggle_label_dark' => __('الوضع الليلي', 'my-bible-plugin'),
            'dark_mode_toggle_label_light' => __('الوضع النهاري', 'my-bible-plugin'),
            'default_dark_mode' => $default_dark_mode,
            'default_testament_view' => $default_testament_view_db, 
            'testaments' => $testaments_for_js, 
            'image_generator' => array(
                'generating_image' => __('جارٍ إنشاء الصورة...', 'my-bible-plugin'),
                'download_image' => __('تحميل الصورة', 'my-bible-plugin'),
                'error_generating_image' => __('خطأ في إنشاء الصورة.', 'my-bible-plugin'),
                'canvas_unsupported' => __('متصفحك لا يدعم إنشاء الصور بهذه الطريقة.', 'my-bible-plugin'),
                'website_credit' => get_bloginfo('name'),
            ),
            'localized_strings' => array( 
                'loading' => __('جارٍ التحميل...', 'my-bible-plugin'),
                'select_book' => __('اختر السفر', 'my-bible-plugin'), 
                'select_chapter' => __('اختر الأصحاح', 'my-bible-plugin'),
                'please_select_book_and_chapter' => __('يرجى اختيار السفر ثم الأصحاح لعرض الآيات.', 'my-bible-plugin'),
                'please_select_chapter' => __('يرجى اختيار الأصحاح.', 'my-bible-plugin'),
                'no_books_found' => __('لا توجد أسفار لهذا العهد', 'my-bible-plugin'), 
                'no_chapters_found' => __('لم يتم العثور على أصحاحات لهذا السفر.', 'my-bible-plugin'),
                'error_loading_books' => __('خطأ في تحميل الأسفار', 'my-bible-plugin'), 
                'error_loading_chapters' => __('حدث خطأ أثناء تحميل الأصحاحات.', 'my-bible-plugin'),
                'error_loading_chapters_ajax' => __('خطأ في الاتصال (أصحاحات). حاول مرة أخرى.', 'my-bible-plugin'),
                'error_loading_verses' => __('حدث خطأ أثناء تحميل الآيات.', 'my-bible-plugin'),
                'error_loading_verses_ajax' => __('خطأ في الاتصال (آيات). حاول مرة أخرى.', 'my-bible-plugin'),
                'mainPageTitle' => get_the_title(), 
                'mainPageDescription' => __('تصفح الكتاب المقدس', 'my-bible-plugin')
            )
        ));
    } catch (Exception $e) {
        my_bible_log_error($e->getMessage(), 'Error in my_bible_enqueue_scripts');
    }
}
add_action('wp_enqueue_scripts', 'my_bible_enqueue_scripts');

// تحميل ملفات الإضافة الأخرى (بعد ملف الدوال المساعدة)
require_once MY_BIBLE_PLUGIN_DIR . 'includes/rewrite.php';
require_once MY_BIBLE_PLUGIN_DIR . 'includes/templates.php';
require_once MY_BIBLE_PLUGIN_DIR . 'includes/shortcodes.php';
require_once MY_BIBLE_PLUGIN_DIR . 'includes/ajax.php';
require_once MY_BIBLE_PLUGIN_DIR . 'includes/sitemap.php'; 


// (الكود المتبقي من my-bible-plugin.php كما هو: إنشاء الجدول، الصفحات، الإعدادات، الخ)
// إنشاء جدول الآيات عند تفعيل الإضافة
function my_bible_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        testament varchar(50) DEFAULT '' NOT NULL, 
        book varchar(100) NOT NULL,
        chapter int NOT NULL,
        verse int NOT NULL,
        text text NOT NULL,
        reference varchar(150) DEFAULT '' NOT NULL, 
        PRIMARY KEY (id),
        UNIQUE KEY book_chapter_verse (book, chapter, verse),
        INDEX book_idx (book),
        INDEX chapter_idx (chapter),
        INDEX testament_idx (testament) 
    ) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'my_bible_create_table');

// إضافة الصفحات عند تفعيل الإضافة
function my_bible_create_pages() {
    $bible_read_page = array(
        'post_title' => __('قراءة الكتاب المقدس', 'my-bible-plugin'),
        'post_name' => 'bible_read',
        'post_content' => '[bible_content]',
        'post_status' => 'publish', 'post_type' => 'page',
    );
    if (!get_page_by_path('bible_read')) { wp_insert_post($bible_read_page); }

    $bible_page = array(
        'post_title' => __('الكتاب المقدس', 'my-bible-plugin'),
        'post_name' => 'bible', 
        'post_content' => '',
        'post_status' => 'publish', 'post_type' => 'page',
    );
    if (!get_page_by_path('bible')) { wp_insert_post($bible_page); }

    $bible_index_page = array(
        'post_title' => __('فهرس الكتاب المقدس', 'my-bible-plugin'),
        'post_name' => 'bible-index', 
        'post_content' => '[bible_index]',
        'post_status' => 'publish',
        'post_type' => 'page',
    );
    if (!get_page_by_path('bible-index')) {
        wp_insert_post($bible_index_page);
    }
}
register_activation_hook(__FILE__, 'my_bible_create_pages');

function my_bible_deactivation() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'my_bible_deactivation');

function my_bible_load_textdomain() {
    load_plugin_textdomain('my-bible-plugin', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'my_bible_load_textdomain');

/* --- قسم الإعدادات --- */
function my_bible_settings_menu() {
    add_options_page(
        __('إعدادات إضافة الكتاب المقدس', 'my-bible-plugin'),
        __('الكتاب المقدس', 'my-bible-plugin'),
        'manage_options', 'my-bible-settings', 'my_bible_settings_page_content'
    );
}
add_action('admin_menu', 'my_bible_settings_menu');

function my_bible_register_settings() {
    register_setting('my_bible_settings_group', 'my_bible_options', 'my_bible_options_sanitize');
    add_settings_section('my_bible_general_settings_section', __('الإعدادات العامة', 'my-bible-plugin'), 'my_bible_general_settings_section_callback', 'my-bible-settings');
    
    add_settings_field('bible_random_book', __('سفر الآيات العشوائية/اليومية', 'my-bible-plugin'), 'my_bible_random_book_field_callback', 'my-bible-settings', 'my_bible_general_settings_section', array('label_for' => 'bible_random_book_select'));
    add_settings_field('default_dark_mode', __('الوضع الليلي الافتراضي', 'my-bible-plugin'), 'my_bible_default_dark_mode_field_callback', 'my-bible-settings', 'my_bible_general_settings_section', array('label_for' => 'default_dark_mode_checkbox'));
    add_settings_field('default_testament_view_db', __('عرض العهد الافتراضي', 'my-bible-plugin'), 'my_bible_default_testament_view_db_field_callback', 'my-bible-settings', 'my_bible_general_settings_section');
}
add_action('admin_init', 'my_bible_register_settings');

function my_bible_general_settings_section_callback() {
    echo '<p>' . __('اختر الإعدادات العامة لإضافة الكتاب المقدس.', 'my-bible-plugin') . '</p>';
}

function my_bible_random_book_field_callback() {
    global $wpdb;
    $options = get_option('my_bible_options');
    $selected_book = isset($options['bible_random_book']) ? $options['bible_random_book'] : '';
    $table_name = $wpdb->prefix . 'bible_verses';
    $books = get_transient('my_bible_all_books_for_settings'); 
    if (false === $books) {
        $books = $wpdb->get_col("SELECT DISTINCT book FROM $table_name ORDER BY book ASC");
        set_transient('my_bible_all_books_for_settings', $books, DAY_IN_SECONDS);
    }
    if (empty($books)) {
        echo '<p>' . __('لم يتم العثور على أسفار.', 'my-bible-plugin') . '</p>'; return;
    }
    echo '<select id="bible_random_book_select" name="my_bible_options[bible_random_book]">';
    echo '<option value="">' . __('كل الأسفار', 'my-bible-plugin') . '</option>';
    foreach ($books as $book) {
        echo '<option value="' . esc_attr($book) . '" ' . selected($selected_book, $book, false) . '>' . esc_html($book) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">' . __('اختر سفراً للآيات العشوائية واليومية.', 'my-bible-plugin') . '</p>';
}

function my_bible_default_dark_mode_field_callback() {
    $options = get_option('my_bible_options');
    $checked = isset($options['default_dark_mode']) && $options['default_dark_mode'] === '1';
    echo '<input type="checkbox" id="default_dark_mode_checkbox" name="my_bible_options[default_dark_mode]" value="1" ' . checked($checked, true, false) . ' />';
    echo '<label for="default_dark_mode_checkbox"> ' . __('تفعيل الوضع الليلي بشكل افتراضي عند تحميل الصفحة.', 'my-bible-plugin') . '</label>';
}

function my_bible_default_testament_view_db_field_callback() {
    $options = get_option('my_bible_options');
    $current_value = isset($options['default_testament_view_db']) ? $options['default_testament_view_db'] : 'all';
    
    global $wpdb;
    $db_testaments = $wpdb->get_col("SELECT DISTINCT testament FROM " . $wpdb->prefix . "bible_verses WHERE testament != '' ORDER BY testament ASC");

    ?>
    <select name="my_bible_options[default_testament_view_db]" id="default_testament_view_db_select">
        <option value="all" <?php selected($current_value, 'all'); ?>><?php esc_html_e('الكل (العهدين)', 'my-bible-plugin'); ?></option>
        <?php if ($db_testaments): ?>
            <?php foreach ($db_testaments as $test_val): ?>
                <option value="<?php echo esc_attr($test_val); ?>" <?php selected($current_value, $test_val); ?>><?php echo esc_html($test_val); ?></option>
            <?php endforeach; ?>
        <?php endif; ?>
    </select>
    <p class="description"><?php esc_html_e('اختر العهد الذي يتم عرضه افتراضياً في قائمة اختيار الأسفار (في شورتكود عرض المحتوى والبحث).', 'my-bible-plugin'); ?></p>
    <?php
}

function my_bible_options_sanitize($input) {
    $sanitized_input = array();
    if (isset($input['bible_random_book'])) {
        $sanitized_input['bible_random_book'] = sanitize_text_field($input['bible_random_book']);
    }
    $sanitized_input['default_dark_mode'] = (isset($input['default_dark_mode']) && $input['default_dark_mode'] == '1') ? '1' : '0';
    
    $allowed_testaments = array('all');
    global $wpdb;
    $db_testaments = $wpdb->get_col("SELECT DISTINCT testament FROM " . $wpdb->prefix . "bible_verses WHERE testament != ''");
    if ($db_testaments) {
        $allowed_testaments = array_merge($allowed_testaments, $db_testaments);
    }

    if (isset($input['default_testament_view_db']) && in_array($input['default_testament_view_db'], $allowed_testaments)) {
        $sanitized_input['default_testament_view_db'] = $input['default_testament_view_db'];
    } else {
        $sanitized_input['default_testament_view_db'] = 'all'; 
    }
    return $sanitized_input;
}

function my_bible_settings_page_content() {
    if (!current_user_can('manage_options')) { wp_die(__('ليس لديك الصلاحيات.', 'my-bible-plugin')); }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('my_bible_settings_group');
            do_settings_sections('my-bible-settings');
            submit_button(__('حفظ التغييرات', 'my-bible-plugin'));
            ?>
        </form>
    </div>
    <?php
}

add_filter('widget_text', 'do_shortcode');
?>
