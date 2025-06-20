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
        wp_enqueue_style('my-bible-dictionary-styles', MY_BIBLE_PLUGIN_URL . 'assets/css/dictionary-styles.css', array('my-bible-styles'), MY_BIBLE_PLUGIN_VERSION); // Dictionary styles
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
            'localized_strings' => array(
                'loading' => __('جارٍ التحميل...', 'my-bible-plugin'),
                'please_select_book_and_chapter' => __('يرجى اختيار السفر ثم الأصحاح لعرض الآيات.', 'my-bible-plugin'),
                'please_select_chapter' => __('يرجى اختيار الأصحاح.', 'my-bible-plugin'),
                'select_book' => __('اختر السفر', 'my-bible-plugin'),
                'select_chapter' => __('اختر الأصحاح', 'my-bible-plugin'),
                'no_books_found' => __('لا توجد أسفار لهذا العهد', 'my-bible-plugin'),
                'error_loading_books' => __('خطأ في تحميل الأسفار', 'my-bible-plugin'),
                'no_chapters_found' => __('لم يتم العثور على أصحاحات لهذا السفر.', 'my-bible-plugin'),
                'error_loading_chapters' => __('حدث خطأ أثناء تحميل الأصحاحات.', 'my-bible-plugin'),
                'error_loading_chapters_ajax' => __('خطأ في الاتصال (أصحاحات). حاول مرة أخرى.', 'my-bible-plugin'),
                'error_loading_verses' => __('حدث خطأ أثناء تحميل الآيات.', 'my-bible-plugin'),
                'error_loading_verses_ajax' => __('خطأ في الاتصال (آيات). حاول مرة أخرى.', 'my-bible-plugin'),
                'mainPageTitle' => __('الكتاب المقدس', 'my-bible-plugin'),
                'mainPageDescription' => __('تصفح الكتاب المقدس وقراءة النصوص المقدسة.', 'my-bible-plugin'),
                'all' => __('الكل', 'my-bible-plugin'),
                'font_noto_naskh' => __('خط نسخ (افتراضي)', 'my-bible-plugin'),
                'font_amiri' => __('خط أميري', 'my-bible-plugin'),
                'font_tahoma' => __('خط تاهوما', 'my-bible-plugin'),
                'font_arial' => __('خط آريال', 'my-bible-plugin'),
                'font_times' => __('خط تايمز نيو رومان', 'my-bible-plugin'),
                'bg_gradient_purple_blue' => __('تدرج بنفسجي-أزرق', 'my-bible-plugin'),
                'bg_gradient_blue_green' => __('تدرج أزرق-أخضر', 'my-bible-plugin'),
                'bg_solid_dark_grey' => __('رمادي داكن ثابت', 'my-bible-plugin'),
                'bg_solid_light_beige' => __('بيج فاتح ثابت', 'my-bible-plugin'),
                'speech_unsupported' => __('عذراً، متصفحك لا يدعم ميزة القراءة الصوتية.', 'my-bible-plugin'),
                'no_text_to_read' => __('لا يوجد نص للقراءة.', 'my-bible-plugin'),
                'error_reading_aloud' => __('حدث خطأ أثناء محاولة القراءة: ', 'my-bible-plugin'),
                'read_aloud_label' => __('قراءة بصوت عالٍ', 'my-bible-plugin'),
                'stop_reading_label' => __('إيقاف القراءة', 'my-bible-plugin'),
                'show_tashkeel_label' => __('إظهار التشكيل', 'my-bible-plugin'),
                'hide_tashkeel_label' => __('إلغاء التشكيل', 'my-bible-plugin'),
                'dark_mode_toggle_label_light' => __('الوضع النهاري', 'my-bible-plugin'),
                'dark_mode_toggle_label_dark' => __('الوضع الليلي', 'my-bible-plugin'),
                'chapter_meanings_title' => __('معاني كلمات الأصحاح', 'my-bible-plugin'),
                'no_dictionary_terms_in_chapter' => __('لا توجد كلمات من القاموس في هذا الأصحاح.', 'my-bible-plugin'),
                'no_chapter_content' => __('لم يتم العثور على محتوى الأصحاح.', 'my-bible-plugin'),
            )
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

function my_bible_create_dictionary_table_and_import_data() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_dictionary';
    $charset_collate = $wpdb->get_charset_collate();

    // SQL schema for the dictionary table
    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        dictionary_key VARCHAR(191) NOT NULL,
        book_name VARCHAR(255) NOT NULL,
        chapter INT NOT NULL,
        verse INT NOT NULL,
        word TEXT NOT NULL,
        meaning TEXT NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY dictionary_key (dictionary_key),
        KEY book_chapter_verse (book_name(191), chapter, verse)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Helper function to parse VerseID
    if (!function_exists('_my_bible_parse_verse_id')) {
        function _my_bible_parse_verse_id($verse_id_str) {
            // Original normalization attempts
            $original_verse_id_str = $verse_id_str; // Keep original for logging on failure
            $verse_id_str = trim($verse_id_str);

            // Define book name map (abbreviation -> full name)
            // Keys should be the most common raw abbreviations found in data
            $book_map = array(
                'تك' => 'سفر التكوين',
                'خر' => 'سفر الخروج',
                'لا' => 'سفر اللاويين', // Added
                'لاو' => 'سفر اللاويين',
                'عد' => 'سفر العدد',
                'تث' => 'سفر التثنية',
                'يش' => 'سفر يشوع',
                'قض' => 'سفر القضاة',
                'را' => 'سفر راعوث',
                '1صم' => 'سفر صموئيل الأول',
                '2صم' => 'سفر صموئيل الثاني',
                '1مل' => 'سفر الملوك الأول',
                '2مل' => 'سفر الملوك الثاني',
                '1اخ' => 'سفر أخبار الأيام الأول',
                '2اخ' => 'سفر أخبار الأيام الثاني',
                'عز' => 'سفر عزرا',
                'نح' => 'سفر نحميا',
                'اس' => 'سفر أستير',
                'أس' => 'سفر أستير', // Added
                'اي' => 'سفر أيوب',
                'أي' => 'سفر أيوب', // Added
                'مز' => 'سفر المزامير',
                'ام' => 'سفر الأمثال',
                'أم' => 'سفر الأمثال', // Added
                'جا' => 'سفر الجامعة',
                'نش' => 'سفر نشيد الأنشاد',
                'اش' => 'سفر إشعياء',
                'إش' => 'سفر إشعياء', // Added
                'ار' => 'سفر إرميا',
                'إر' => 'سفر إرميا', // Added
                'مرا' => 'سفر مراثي إرميا',
                'حز' => 'سفر حزقيال',
                'دا' => 'سفر دانيال',
                'هو' => 'سفر هوشع',
                'يؤ' => 'سفر يوئيل',
                'عا' => 'سفر عاموس',
                'عو' => 'سفر عوبديا',
                'يو' => 'سفر يونان', // Changed from 'يون' as per new map
                'يون' => 'سفر يونان', // Keep both if both appear
                'مي' => 'سفر ميخا',
                'نا' => 'سفر ناحوم',
                'حب' => 'سفر حبقوق',
                'صف' => 'سفر صفنيا',
                'حج' => 'سفر حجي',
                'زك' => 'سفر زكريا',
                'ملا' => 'سفر ملاخي',
                'مت' => 'إنجيل متى',
                'مر' => 'إنجيل مرقس',
                'لو' => 'إنجيل لوقا',
                'يوح' => 'إنجيل يوحنا', // Added
                // 'يو' => 'إنجيل يوحنا', // This was in old map, 'يوح' is more specific for John gospel, 'يو' was for Jonah.
                'اع' => 'سفر أعمال الرسل',
                'أع' => 'سفر أعمال الرسل', // Added
                'رو' => 'رسالة بولس الرسول إلى أهل رومية',
                '1كو' => 'رسالة بولس الرسول الأولى إلى أهل كورنثوس',
                '2كو' => 'رسالة بولس الرسول الثانية إلى أهل كورنثوس',
                'غلا' => 'رسالة بولس الرسول إلى أهل غلاطية', // Added
                'اف' => 'رسالة بولس الرسول إلى أهل أفسس',
                'أف' => 'رسالة بولس الرسول إلى أهل أفسس', // Added
                'في' => 'رسالة بولس الرسول إلى أهل فيلبي',
                'كو' => 'رسالة بولس الرسول إلى أهل كولوسي',
                '1تس' => 'رسالة بولس الرسول الأولى إلى أهل تسالونيكي',
                '2تس' => 'رسالة بولس الرسول الثانية إلى أهل تسالونيكي',
                '1تي' => 'رسالة بولس الرسول الأولى إلى تيموثاوس',
                '2تي' => 'رسالة بولس الرسول الثانية إلى تيموثاوس',
                'تي' => 'رسالة بولس الرسول إلى تيطس',
                'فل' => 'رسالة بولس الرسول إلى فليمون',
                'فيل' => 'رسالة بولس الرسول إلى فليمون', // Added
                'عب' => 'الرسالة إلى العبرانيين',
                'يع' => 'رسالة يعقوب',
                '1بط' => 'رسالة بطرس الأولى',
                '2بط' => 'رسالة بطرس الثانية',
                '1يو' => 'رسالة يوحنا الأولى',
                '2يو' => 'رسالة يوحنا الثانية',
                '3يو' => 'رسالة يوحنا الثالثة',
                'يه' => 'رسالة يهوذا',
                'رؤ' => 'سفر رؤيا يوحنا اللاهوتي'
            );

            // New Regex: Handles optional leading digit, multi-word book names, and specific Arabic characters like tatweel.
            // Allows for colon or period as chapter/verse separator.
            $pattern = '/^(\d?[^\d\s]+(?:[\s\x{0640}-\x{064F}\x{0650}-\x{065F}]*[^\d\s]+)*)\s*(\d+)\s*[:\.]\s*(\d+)$/u';

            if (preg_match($pattern, $verse_id_str, $matches)) {
                $book_abbr_raw = trim($matches[1]); // Raw abbreviation from regex
                $chapter = (int)$matches[2];
                $verse = (int)$matches[3];

                // Normalize the raw abbreviation for map lookup
                // 1. Remove Tatweel (U+0640) and Arabic diacritics (U+064B to U+065F)
                $normalized_abbr_for_lookup = preg_replace('/[\x{0640}\x{064B}-\x{065F}]/u', '', $book_abbr_raw);
                // 2. Standardize Alef forms to simple Alef, Yaa to simple Yaa
                $normalized_abbr_for_lookup = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $normalized_abbr_for_lookup);
                $normalized_abbr_for_lookup = str_replace('ى', 'ي', $normalized_abbr_for_lookup);
                // 3. Remove any remaining spaces within the abbreviation string (e.g. "1 صم" -> "1صم")
                $normalized_abbr_for_lookup = preg_replace('/\s+/', '', $normalized_abbr_for_lookup);
                // 4. Convert to lowercase (though Arabic script doesn't have case, this is good practice if map keys are all lowercase)
                // However, map keys provided are in Arabic script, so direct match after normalization is better.
                // $normalized_abbr_for_lookup = strtolower($normalized_abbr_for_lookup); // Not strictly needed if keys are Arabic

                // Attempt to map abbreviation to full name
                $book_name = isset($book_map[$normalized_abbr_for_lookup]) ? $book_map[$normalized_abbr_for_lookup] : $book_abbr_raw; // Fallback to raw abbr if not in map

                // Final normalization for storage (consistent with how it might be stored in bible_verses or queried)
                // 1. Remove all spaces from the full book name
                $final_book_name = str_replace(' ', '', $book_name);
                // 2. Standardize Alef forms and Yaa (already done for abbreviation, do it for full name if it came from fallback)
                $final_book_name = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $final_book_name);
                $final_book_name = str_replace('ى', 'ي', $final_book_name);
                // 3. Remove Tashkeel (diacritics) and Tatweel from the final book name
                $final_book_name = preg_replace('/[\x{0640}\x{064B}-\x{065F}]/u', '', $final_book_name);

                return ['book' => $final_book_name, 'chapter' => $chapter, 'verse' => $verse];
            } else {
                my_bible_log_error("Failed to parse VerseID (new regex): " . $original_verse_id_str, 'dictionary_import_parsing');
                return false;
            }
        }
    }

    $csv_file_path = MY_BIBLE_PLUGIN_DIR . 'assets/data/dictionary_data.csv';

    if (file_exists($csv_file_path) && is_readable($csv_file_path)) {
        if (($handle = fopen($csv_file_path, 'r')) !== FALSE) {
            fgetcsv($handle); // Skip header

            while (($row = fgetcsv($handle)) !== FALSE) {
                if (count($row) < 4) {
                    my_bible_log_error("Skipping row due to insufficient columns: " . implode(',', $row), 'dictionary_import');
                    continue;
                }

                $verse_id_str = trim($row[1]);
                $word = trim(stripslashes($row[2]));
                $meaning = trim(stripslashes($row[3]));

                if (empty($verse_id_str) || empty($word) || empty($meaning)) {
                    my_bible_log_error("Skipping row due to empty essential data. VerseID: '{$verse_id_str}', Word: '{$word}'", 'dictionary_import');
                    continue;
                }

                $parsed_verse_id = _my_bible_parse_verse_id($verse_id_str);

                if ($parsed_verse_id) {
                    $normalized_key_book_name = strtolower($parsed_verse_id['book']);
                    $normalized_key_word = strtolower(preg_replace('/[\x{0640}\x{064B}-\x{065F}]/u', '', str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', str_replace('ى', 'ي', $word))));


                    $dictionary_key_string = $normalized_key_book_name . '-' . $parsed_verse_id['chapter'] . '-' . $parsed_verse_id['verse'] . '-' . $normalized_key_word;
                    $dictionary_key = md5($dictionary_key_string);

                    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE dictionary_key = %s", $dictionary_key));

                    if (!$existing) {
                        $insert_result = $wpdb->insert(
                            $table_name,
                            array(
                                'dictionary_key' => $dictionary_key,
                                'book_name' => $parsed_verse_id['book'],
                                'chapter' => $parsed_verse_id['chapter'],
                                'verse' => $parsed_verse_id['verse'],
                                'word' => $word, // Store original word
                                'meaning' => $meaning
                            ),
                            array('%s', '%s', '%d', '%d', '%s', '%s')
                        );
                        if ($insert_result === false) {
                            my_bible_log_error("DB Insert Error for dictionary key {$dictionary_key} (VerseID: {$verse_id_str}, Word: {$word}): " . $wpdb->last_error, 'dictionary_import');
                        }
                    }
                }
            }
            fclose($handle);
        } else {
            my_bible_log_error("Failed to open CSV file: " . $csv_file_path, 'dictionary_import');
        }
    } else {
        my_bible_log_error("CSV file not found or not readable: " . $csv_file_path, 'dictionary_import');
    }
}

function my_bible_create_table() { /* ... كما كان ... */ }
register_activation_hook(__FILE__, 'my_bible_create_table');
register_activation_hook(__FILE__, 'my_bible_create_dictionary_table_and_import_data');

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
