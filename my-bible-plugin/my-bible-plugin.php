<?php
/*
Plugin Name: My Bible Plugin
Description: عرض الكتاب المقدس مع البحث والشواهد والتنقل المحسن، مع دعم الوضع الليلي والقراءة الصوتية وإنشاء الصور وفهرس للأسفار.
Version: 1.7.0
Author: اسمك (تم التحديث بواسطة Gemini)
Text Domain: my-bible-plugin
Domain Path: /languages
*/

// منع الوصول المباشر للملف
if (!defined('ABSPATH')) {
    exit;
}

define('MY_BIBLE_PLUGIN_VERSION', '1.7.0');
define('MY_BIBLE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MY_BIBLE_PLUGIN_URL', plugin_dir_url(__FILE__));

// --- الدوال المساعدة العامة ---

if (!function_exists('my_bible_get_controls_html')) {
    function my_bible_get_controls_html($context = 'content', $verse_object = null, $verse_reference_text = '') {
        $id_suffix = ($context === 'search') ? '-search' : '';
        $controls_html = '<div class="bible-controls">';
        $controls_html .= '<button id="toggle-tashkeel' . $id_suffix . '" class="bible-control-button"><i class="fas fa-language"></i> <span class="label">' . esc_html__('إلغاء التشكيل', 'my-bible-plugin') . '</span></button>';
        $controls_html .= '<button id="increase-font' . $id_suffix . '" class="bible-control-button"><i class="fas fa-plus"></i> <span class="label">' . esc_html__('تكبير الخط', 'my-bible-plugin') . '</span></button>';
        $controls_html .= '<button id="decrease-font' . $id_suffix . '" class="bible-control-button"><i class="fas fa-minus"></i> <span class="label">' . esc_html__('تصغير الخط', 'my-bible-plugin') . '</span></button>';
        $controls_html .= '<button id="dark-mode-toggle" class="bible-control-button dark-mode-toggle-button"><i class="fas fa-moon"></i> <span class="label">' . esc_html__('الوضع الليلي', 'my-bible-plugin') . '</span></button>';
        $controls_html .= '<button id="read-aloud-button" class="bible-control-button read-aloud-button"><i class="fas fa-volume-up"></i> <span class="label">' . esc_html__('قراءة بصوت عالٍ', 'my-bible-plugin') . '</span></button>';
        if ($context === 'single_verse' && $verse_object && !empty($verse_reference_text)) {
            $controls_html .= '<button id="generate-verse-image-button" class="bible-control-button" data-verse-text="' . esc_attr($verse_object->text) . '" data-verse-reference="' . esc_attr($verse_reference_text) . '"><i class="fas fa-image"></i> <span class="label">' . esc_html__('إنشاء صورة للمشاركة', 'my-bible-plugin') . '</span></button>';
        }
        $controls_html .= '</div>';
        return $controls_html;
    }
}

if (!function_exists('my_bible_sanitize_book_name')) {
    function my_bible_sanitize_book_name($book_name) {
        if (empty($book_name)) return '';
        $book_name = (string) $book_name;
        $book_name = trim($book_name);
        $book_name = str_replace('-', ' ', $book_name);
        $book_name = preg_replace('/[\x{0617}-\x{061A}\x{064B}-\x{065F}\x{06D6}-\x{06ED}]/u', '', $book_name);
        $book_name = str_replace(array('أ', 'إ', 'آ', 'ٱ', 'أُ', 'إِ'), 'ا', $book_name);
        $book_name = preg_replace('/\s+/', ' ', $book_name);
        return trim($book_name);
    }
}

if (!function_exists('my_bible_create_book_slug')) {
    function my_bible_create_book_slug($book_name) {
        if (empty($book_name)) return '';
        $slug = my_bible_sanitize_book_name($book_name);
        $slug = str_replace(' ', '-', $slug);
        return rawurlencode($slug);
    }
}

// دوال مساعدة خاصة بفهرس الكتاب المقدس
if (!function_exists('my_bible_get_testaments_books')) {
    function my_bible_get_testaments_books() {
        // !! مهم جداً: قم بمراجعة هذه القوائم وتأكد من أنها تطابق أسماء الأسفار في قاعدة بياناتك بالضبط !!
        return array(
            'OT' => array(
                'سفر التكوين', 'سفر الخروج', 'سفر اللاويين', 'سفر العدد', 'سفر التثنية',
                'سفر يشوع', 'سفر القضاة', 'سفر راعوث', 'سفر صموئيل الأول', 'سفر صموئيل الثاني', 'سفر الملوك الأول', 'سفر الملوك الثاني', 'سفر أخبار الأيام الأول', 'سفر أخبار الأيام الثاني', 'سفر عزرا', 'سفر نحميا', 'سفر أستير',
                'سفر أيوب', 'سفر المزامير', 'سفر الأمثال', 'سفر الجامعة', 'سفر نشيد الأنشاد',
                'سفر إشعياء', 'سفر إرميا', 'سفر مراثي إرميا', 'سفر حزقيال', 'سفر دانيال',
                'سفر هوشع', 'سفر يوئيل', 'سفر عاموس', 'سفر عوبديا', 'سفر يونان', 'سفر ميخا', 'سفر ناحوم', 'سفر حبقوق', 'سفر صفنيا', 'سفر حجي', 'سفر زكريا', 'سفر ملاخي'
            ),
            'NT' => array(
                'إنجيل متى', 'إنجيل مرقس', 'إنجيل لوقا', 'إنجيل يوحنا',
                'سفر أعمال الرسل',
                'رسالة بولس الرسول إلى أهل رومية', 'رسالة بولس الرسول الأولى إلى أهل كورنثوس', 'رسالة بولس الرسول الثانية إلى أهل كورنثوس', 'رسالة بولس الرسول إلى أهل غلاطية', 'رسالة بولس الرسول إلى أهل أفسس', 'رسالة بولس الرسول إلى أهل فيلبي', 'رسالة بولس الرسول إلى أهل كولوسي', 'رسالة بولس الرسول الأولى إلى أهل تسالونيكي', 'رسالة بولس الرسول الثانية إلى أهل تسالونيكي', 'رسالة بولس الرسول الأولى إلى تيموثاوس', 'رسالة بولس الرسول الثانية إلى تيموثاوس', 'رسالة بولس الرسول إلى تيطس', 'رسالة بولس الرسول إلى فليمون',
                'الرسالة إلى العبرانيين', 'رسالة يعقوب', 'رسالة بطرس الأولى', 'رسالة بطرس الثانية', 'رسالة يوحنا الأولى', 'رسالة يوحنا الثانية', 'رسالة يوحنا الثالثة', 'رسالة يهوذا',
                'سفر رؤيا يوحنا اللاهوتي'
            )
        );
    }
}

if (!function_exists('my_bible_get_book_order')) {
    function my_bible_get_book_order() {
        $testaments = my_bible_get_testaments_books();
        return array_merge($testaments['OT'], $testaments['NT']);
    }
}

if (!function_exists('my_bible_get_book_name_from_slug')) {
    function my_bible_get_book_name_from_slug($book_slug) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bible_verses';
        $book_name_try = str_replace('-', ' ', rawurldecode($book_slug));

        $db_book_name = $wpdb->get_var($wpdb->prepare( "SELECT DISTINCT book FROM $table_name WHERE book = %s", $book_name_try ));
        if ($db_book_name) return $db_book_name;

        $sanitized_name = my_bible_sanitize_book_name($book_name_try); // تستخدم الدالة المعرفة عالمياً
        $db_book_name_alt = $wpdb->get_var($wpdb->prepare( "SELECT book FROM $table_name WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(book, 'ً', ''), 'َ', ''), 'ُ', ''), 'ِ', ''), 'ْ', ''), 'ّ', ''), 'إ', 'ا'), 'أ', 'ا'), 'آ', 'ا') = %s LIMIT 1", $sanitized_name ));
        if ($db_book_name_alt) return $db_book_name_alt;
        
        return false;
    }
}


// تحميل ملفات الـ CSS والـ JS
function my_bible_enqueue_scripts() {
    // (الكود الخاص بـ my_bible_enqueue_scripts كما هو في الردود السابقة، مع التأكد من أن bibleFrontend.localized_strings موجودة)
    wp_enqueue_script('jquery');
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css', array(), '5.15.4');
    wp_enqueue_style('my-bible-styles', MY_BIBLE_PLUGIN_URL . 'assets/css/bible-styles.css', array(), MY_BIBLE_PLUGIN_VERSION);
    wp_enqueue_style('my-bible-dark-mode', MY_BIBLE_PLUGIN_URL . 'assets/css/dark-mode.css', array('my-bible-styles'), MY_BIBLE_PLUGIN_VERSION);

    wp_enqueue_script('my-bible-frontend', MY_BIBLE_PLUGIN_URL . 'assets/js/bible-frontend.js', array('jquery'), MY_BIBLE_PLUGIN_VERSION, true);
    wp_enqueue_script('my-bible-copy', MY_BIBLE_PLUGIN_URL . 'assets/js/bible-copy.js', array(), MY_BIBLE_PLUGIN_VERSION, true);

    $options = get_option('my_bible_options');
    $default_dark_mode = isset($options['default_dark_mode']) && $options['default_dark_mode'] === '1';
    
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
        'image_generator' => array(
            'generating_image' => __('جارٍ إنشاء الصورة...', 'my-bible-plugin'),
            'download_image' => __('تحميل الصورة', 'my-bible-plugin'),
            'error_generating_image' => __('خطأ في إنشاء الصورة.', 'my-bible-plugin'),
            'canvas_unsupported' => __('متصفحك لا يدعم إنشاء الصور بهذه الطريقة.', 'my-bible-plugin'),
            'website_credit' => get_bloginfo('name'),
        ),
        'localized_strings' => array( // دمج نصوص AJAX هنا
            'loading' => __('جارٍ التحميل...', 'my-bible-plugin'),
            'selectChapter' => __('اختر الأصحاح', 'my-bible-plugin'),
            'pleaseSelectBookAndChapter' => __('يرجى اختيار السفر ثم الأصحاح لعرض الآيات.', 'my-bible-plugin'),
            'pleaseSelectChapter' => __('يرجى اختيار الأصحاح.', 'my-bible-plugin'),
            'noChaptersFound' => __('لم يتم العثور على أصحاحات لهذا السفر.', 'my-bible-plugin'),
            'errorLoadingChapters' => __('حدث خطأ أثناء تحميل الأصحاحات.', 'my-bible-plugin'),
            'errorLoadingChaptersAjax' => __('خطأ في الاتصال (أصحاحات). حاول مرة أخرى.', 'my-bible-plugin'),
            'errorLoadingVerses' => __('حدث خطأ أثناء تحميل الآيات.', 'my-bible-plugin'),
            'errorLoadingVersesAjax' => __('خطأ في الاتصال (آيات). حاول مرة أخرى.', 'my-bible-plugin'),
            'mainPageTitle' => get_the_title(), 
            'mainPageDescription' => __('تصفح الكتاب المقدس', 'my-bible-plugin')
        )
    ));
}
add_action('wp_enqueue_scripts', 'my_bible_enqueue_scripts');

// تحميل ملفات الإضافة الأخرى
// الآن سيتم تحميلها بعد تعريف الدوال المساعدة أعلاه
require_once MY_BIBLE_PLUGIN_DIR . 'includes/rewrite.php';
require_once MY_BIBLE_PLUGIN_DIR . 'includes/templates.php';
require_once MY_BIBLE_PLUGIN_DIR . 'includes/shortcodes.php';
require_once MY_BIBLE_PLUGIN_DIR . 'includes/ajax.php';


// (الكود المتبقي من my-bible-plugin.php كما هو: إنشاء الجدول، الصفحات، الإعدادات، الخ)
// إنشاء جدول الآيات عند تفعيل الإضافة
function my_bible_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        book varchar(100) NOT NULL,
        chapter int NOT NULL,
        verse int NOT NULL,
        text text NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY book_chapter_verse (book, chapter, verse),
        INDEX book_idx (book),
        INDEX chapter_idx (chapter)
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
    $books = get_transient('my_bible_all_books_for_settings'); // مفتاح مختلف للتخزين المؤقت هنا
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
    echo '<p class="description">' . __('يمكن للمستخدمين دائماً تبديل الوضع يدوياً.', 'my-bible-plugin') . '</p>';
}

function my_bible_options_sanitize($input) {
    $sanitized_input = array();
    if (isset($input['bible_random_book'])) {
        $sanitized_input['bible_random_book'] = sanitize_text_field($input['bible_random_book']);
    }
    $sanitized_input['default_dark_mode'] = (isset($input['default_dark_mode']) && $input['default_dark_mode'] == '1') ? '1' : '0';
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

// --- Dynamic Title and Meta Description ---

// Helper function to check if the current page is a Bible page
if (!function_exists('is_my_bible_page')) {
    function is_my_bible_page() {
        // Check for query vars 'book' and 'chapter'
        if (get_query_var('book') && get_query_var('chapter')) {
            return true;
        }
        // Check if the current page is the main Bible page (e.g., slug 'bible')
        // You might need to adjust 'bible' if your page slug is different
        if (is_page('bible')) { // Assuming 'bible' is the slug of your main Bible display page
            return true;
        }
        return false;
    }
}

// Function to dynamically generate title parts
if (!function_exists('my_bible_dynamic_title_parts')) {
    function my_bible_dynamic_title_parts($title_parts) {
        if (!is_my_bible_page()) {
            return $title_parts;
        }

        $book_slug = get_query_var('book');
        $chapter_num = get_query_var('chapter');
        $verse_num = get_query_var('verse');

        if ($book_slug && $chapter_num) {
            $book_name = my_bible_get_book_name_from_slug($book_slug);
            if (!$book_name) {
                // Fallback if book name not found (e.g., use slug or a default)
                $book_name = ucwords(str_replace('-', ' ', sanitize_text_field($book_slug)));
            }

            $new_title = $book_name . ' ' . __('الأصحاح', 'my-bible-plugin') . ' ' . $chapter_num;

            if ($verse_num) {
                $new_title = $book_name . ' ' . $chapter_num . ':' . $verse_num;
            }

            $title_parts['title'] = $new_title;
            // $title_parts['site'] is usually set by WordPress, we keep it.
        }
        // If only on the main 'bible' page without book/chapter, the default page title is fine.
        return $title_parts;
    }
}
add_filter('document_title_parts', 'my_bible_dynamic_title_parts', 15);

// Function to dynamically generate meta description
if (!function_exists('my_bible_dynamic_meta_description')) {
    function my_bible_dynamic_meta_description() {
        global $wpdb;

        if (!is_my_bible_page()) {
            return;
        }

        $book_slug = get_query_var('book');
        $chapter_num = get_query_var('chapter');
        $verse_num = get_query_var('verse');
        $description = '';

        if ($book_slug && $chapter_num) {
            $book_name = my_bible_get_book_name_from_slug($book_slug);
            if (!$book_name) {
                $book_name = ucwords(str_replace('-', ' ', sanitize_text_field($book_slug)));
            }

            $table_name = $wpdb->prefix . 'bible_verses';

            if ($verse_num) {
                $verse_text = $wpdb->get_var($wpdb->prepare(
                    "SELECT text FROM {$table_name} WHERE book = %s AND chapter = %d AND verse = %d",
                    $book_name, // Assuming my_bible_get_book_name_from_slug returns the exact name in DB
                    $chapter_num,
                    $verse_num
                ));
                if ($verse_text) {
                    $description = wp_strip_all_tags($verse_text) . ' (' . $book_name . ' ' . $chapter_num . ':' . $verse_num . '). ' . __('اقرأ الكتاب المقدس على', 'my-bible-plugin') . ' ' . get_bloginfo('name');
                }
            } else {
                $first_verse_text = $wpdb->get_var($wpdb->prepare(
                    "SELECT text FROM {$table_name} WHERE book = %s AND chapter = %d ORDER BY verse ASC LIMIT 1",
                    $book_name,
                    $chapter_num
                ));
                if ($first_verse_text) {
                    $snippet = mb_substr(wp_strip_all_tags($first_verse_text), 0, 100, 'UTF-8');
                    $description = __('اقرأ', 'my-bible-plugin') . ' ' . $book_name . ' ' . __('الأصحاح', 'my-bible-plugin') . ' ' . $chapter_num . '. ' . $snippet . '... ' . __('الكتاب المقدس على', 'my-bible-plugin') . ' ' . get_bloginfo('name');
                } else {
                    $description = __('تصفح واقرأ', 'my-bible-plugin') . ' ' . $book_name . ' ' . __('الأصحاح', 'my-bible-plugin') . ' ' . $chapter_num . ' ' . __('من الكتاب المقدس. مقدم من', 'my-bible-plugin') . ' ' . get_bloginfo('name');
                }
            }
        } elseif (is_page('bible')) { // Main bible page, no specific book/chapter
             $description = __('تصفح وقراءة الكتاب المقدس كاملاً. ابحث عن الأسفار، الأصحاحات، والآيات بسهولة. مقدم من', 'my-bible-plugin') . ' ' . get_bloginfo('name');
        }


        if (!empty($description)) {
            echo '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";
        }
    }
}
add_action('wp_head', 'my_bible_dynamic_meta_description', 5);

?>
