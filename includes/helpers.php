<?php
// منع الوصول المباشر
if (!defined('ABSPATH')) {
    exit;
}

// --- الدوال المساعدة للقاموس ---

/**
 * Normalizes a string for dictionary lookup by removing diacritics and converting to lowercase.
 * This is a private helper function to ensure consistent normalization.
 * @param string $string The string to normalize.
 * @return string The normalized string.
 */
function _my_bible_normalize_for_lookup($string)
{
    // Return empty string if input is not a string or is empty
    if (!is_string($string) || empty($string)) {
        return '';
    }
    // Remove all Unicode combining marks (this is the most effective way to remove diacritics).
    $normalized = preg_replace('/\p{M}/u', '', $string);
    // Convert to lowercase for case-insensitive matching.
    $normalized = mb_strtolower($normalized, 'UTF-8');
    return $normalized;
}


/**
 * Fetches all terms and definitions from the Bible dictionary.
 * Uses the normalization function to create clean keys for matching.
 * Caches the result for the duration of the page load.
 *
 * @return array An associative array mapping normalized terms to their definitions.
 */
function get_bible_dictionary_data()
{
    static $dictionary_data = null;

    if ($dictionary_data === null) {
        global $wpdb;
        $dictionary_data = array();
        $table_name = $wpdb->prefix . 'my_bible_dictionary';

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name) {
            $results = $wpdb->get_results("SELECT term, definition FROM {$table_name}", ARRAY_A);

            if ($results) {
                foreach ($results as $row) {
                    if (!empty($row['term'])) {
                        // Use the new helper to create a clean, normalized key.
                        $normalized_term = _my_bible_normalize_for_lookup($row['term']);
                        if (!empty($normalized_term)) {
                            // Store the original term casing along with the definition.
                            $dictionary_data[$normalized_term] = [
                                'original' => $row['term'],
                                'definition' => $row['definition']
                            ];
                        }
                    }
                }
            }
        }
    }
    return $dictionary_data;
}

/**
 * Get Book Name by ID (Orthodox Canon Order)
 * Based on analysis: Psalms = 21, Joshua = 6
 */
function my_bible_get_book_name_by_id($id)
{
    static $books_map = array(
    // Pentateuch
    1 => 'التكوين',
    2 => 'الخروج',
    3 => 'اللاويين',
    4 => 'العدد',
    5 => 'التثنية',
    // Historical
    6 => 'يشوع',
    7 => 'القضاة',
    8 => 'راعوث',
    9 => '1 صموئيل',
    10 => '2 صموئيل',
    11 => '1 ملوك',
    12 => '2 ملوك',
    89 => '1 أخبار',
    90 => '2 أخبار',
    91 => 'عزرا',
    92 => 'نحميا',
    // Apocrypha (Historical)
    93 => 'طوبيا',
    94 => 'يهوديت',
    95 => 'أستير', // Note: Bible text usually uses "أستير" or "استير", commentary doc says "أستير"
    96 => 'أيوب',
    97 => 'المزامير',
    98 => 'الأمثال',
    99 => 'الجامعة',
    100 => 'نشيد الأنشاد',
    101 => 'الحكمة',
    102 => 'يشوع بن سيراخ',
    // Prophets (Major)
    103 => 'إشعياء',
    104 => 'إرميا',
    105 => 'مراثي إرميا',
    106 => 'باروخ',
    107 => 'حزقيال',
    108 => 'دانيال',
    // Prophets (Minor)
    109 => 'هوشع',
    110 => 'يوئيل',
    111 => 'عاموس',
    112 => 'عوبديا',
    113 => 'يونان',
    114 => 'ميخا',
    115 => 'ناحوم',
    116 => 'حبقوق',
    117 => 'صفنيا',
    118 => 'حجي',
    119 => 'زكريا',
    120 => 'ملاخي',
    // Maccabees (Missing in map but listed in doc)
    // New Testament
    // Gospels
    127 => 'متى',
    128 => 'مرقس',
    129 => 'لوقا',
    130 => 'يوحنا',
    131 => 'أعمال الرسل',
    // Epistles
    132 => 'رومية',
    133 => '1 كورنثوس',
    134 => '2 كورنثوس',
    135 => 'غلاطية',
    136 => 'أفسس',
    61 => 'فيلبي',
    62 => 'كولوسي',
    140 => '1 تسالونيكي',
    141 => '2 تسالونيكي',
    142 => '1 تيموثاوس',
    143 => '2 تيموثاوس',
    144 => 'تيطس',
    145 => 'فليمون',
    146 => 'عبرانيين',
    147 => 'يعقوب',
    148 => '1 بطرس',
    149 => '2 بطرس',
    150 => '1 يوحنا',
    151 => '2 يوحنا',
    152 => '3 يوحنا',
    153 => 'يهوذا',
    154 => 'رؤيا يوحنا'
    );

    // Fallback logic for IDs that might not match exactly or for gaps
    // But for ref_s_6 and ref_s_21 it works.

    return isset($books_map[$id]) ? $books_map[$id] : null;
}

/**
 * [V4 - High Performance & Corrected Normalization] Finds and links dictionary terms in verse text.
 * Updated: Works for all users (logged in and visitors)
 *
 * @param string $verse_text The raw Bible verse text.
 * @return string The processed text with interactive HTML links for dictionary terms.
 */
function link_bible_terms($verse_text)
{
    $dictionary = get_bible_dictionary_data();

    if (empty($dictionary) || empty(trim($verse_text))) {
        return esc_html($verse_text);
    }

    static $structured_dictionary = null;
    if ($structured_dictionary === null) {
        $structured_dictionary = [];
        // The keys of $dictionary are already normalized.
        foreach ($dictionary as $term_key => $data) {
            $word_count = count(explode(' ', $term_key));
            if (!isset($structured_dictionary[$word_count])) {
                $structured_dictionary[$word_count] = [];
            }
            $structured_dictionary[$word_count][$term_key] = $data['definition'];
        }
        krsort($structured_dictionary);
    }

    $verse_tokens = preg_split('/(\s+|[.,:;!?\(\)\[\]{}<>«»\-"\'`\r\n\t]+)/u', $verse_text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $final_html = '';
    $token_count = count($verse_tokens);
    $i = 0;

    while ($i < $token_count) {
        $current_token = $verse_tokens[$i];

        if (trim($current_token) === '' || preg_match('/^[.,:;!?\(\)\[\]{}<>«»\-"\'`\r\n\t]+$/u', $current_token)) {
            $final_html .= esc_html($current_token);
            $i++;
            continue;
        }

        $match_found = false;

        foreach ($structured_dictionary as $word_count => $terms) {
            if ($i + ($word_count * 2) - 1 < $token_count) {
                $phrase_tokens = array_slice($verse_tokens, $i, ($word_count * 2) - 1);
                $original_phrase = implode('', $phrase_tokens);

                // Normalize the phrase from the verse using the same helper function.
                $normalized_phrase = _my_bible_normalize_for_lookup($original_phrase);

                if (isset($terms[$normalized_phrase])) {
                    $definition = esc_attr($terms[$normalized_phrase]);
                    $final_html .= "<a href='javascript:void(0);' class='bible-term' data-definition='{$definition}'>" . esc_html($original_phrase) . "</a>";
                    $i += ($word_count * 2) - 1;
                    $match_found = true;
                    break;
                }
            }
        }

        if (!$match_found) {
            $final_html .= esc_html($current_token);
            $i++;
        }
    }

    return $final_html;
}


// --- الدوال المساعدة العامة (موجودة مسبقاً) ---

if (!function_exists('my_bible_get_controls_html')) {
    function my_bible_get_controls_html($context = 'content', $verse_object = null, $verse_reference_text = '')
    {
        $unique_id_suffix = '-' . uniqid();
        if ($context === 'content' || $context === 'search') {
            $unique_id_suffix = ($context === 'search') ? '-search' : '';
        }

        $controls_html = '<div class="bible-controls-wrapper">';

        $controls_html .= '<div class="bible-main-controls">';
        $controls_html .= '<button class="bible-control-button" data-action="show-chapter-terms"><i class="fas fa-book-open"></i> <span class="label">' . esc_html__('معاني كلمات الأصحاح', 'my-bible-plugin') . '</span></button>';

        $controls_html .= '<button id="toggle-tashkeel' . esc_attr($unique_id_suffix) . '" class="bible-control-button" data-action="toggle-tashkeel"><i class="fas fa-language"></i> <span class="label">' . esc_html__('إلغاء التشكيل', 'my-bible-plugin') . '</span></button>';
        $controls_html .= '<button id="increase-font' . esc_attr($unique_id_suffix) . '" class="bible-control-button" data-action="increase-font"><i class="fas fa-plus"></i> <span class="label">' . esc_html__('تكبير الخط', 'my-bible-plugin') . '</span></button>';
        $controls_html .= '<button id="decrease-font' . esc_attr($unique_id_suffix) . '" class="bible-control-button" data-action="decrease-font"><i class="fas fa-minus"></i> <span class="label">' . esc_html__('تصغير الخط', 'my-bible-plugin') . '</span></button>';

        $dark_mode_button_id = ($context === 'content' || $context === 'search') ? 'dark-mode-toggle' : 'dark-mode-toggle' . esc_attr($unique_id_suffix);
        $controls_html .= '<button id="' . esc_attr($dark_mode_button_id) . '" class="bible-control-button dark-mode-toggle-button" data-action="dark-mode-toggle"><i class="fas fa-moon"></i> <span class="label">' . esc_html__('الوضع الليلي', 'my-bible-plugin') . '</span></button>';
        $controls_html .= '<button id="read-aloud-button' . esc_attr($unique_id_suffix) . '" class="bible-control-button read-aloud-button" data-action="read-aloud"><i class="fas fa-volume-up"></i> <span class="label">' . esc_html__('قراءة بصوت عالٍ', 'my-bible-plugin') . '</span></button>';

        // زر التفاسير مع قائمة منسدلة
        $controls_html .= '<div class="bible-commentary-dropdown-wrapper">';
        $controls_html .= '<button id="commentary-button' . esc_attr($unique_id_suffix) . '" class="bible-control-button commentary-toggle-button" data-action="toggle-commentary-menu"><i class="fas fa-book-reader"></i> <span class="label">' . esc_html__('التفاسير', 'my-bible-plugin') . '</span> <i class="fas fa-chevron-down dropdown-icon"></i></button>';
        $controls_html .= '<div class="commentary-dropdown-menu" id="commentary-menu' . esc_attr($unique_id_suffix) . '">';
        $controls_html .= '<a href="#" class="commentary-menu-item" data-source="af"><i class="fas fa-user"></i> ' . esc_html__('القمص أنطونيوس فكري', 'my-bible-plugin') . '</a>';
        $controls_html .= '<a href="#" class="commentary-menu-item" data-source="ty"><i class="fas fa-user"></i> ' . esc_html__('القمص تادرس يعقوب ملطي', 'my-bible-plugin') . '</a>';
        $controls_html .= '<a href="#" class="commentary-menu-item" data-source="sm"><i class="fas fa-church"></i> ' . esc_html__('كنيسة مارمرقس', 'my-bible-plugin') . '</a>';
        $controls_html .= '</div>';
        $controls_html .= '</div>';

        $controls_html .= '</div>';

        $show_image_options_contexts = array('single_verse', 'random_verse', 'daily_verse');
        if (in_array($context, $show_image_options_contexts) && $verse_object && !empty($verse_reference_text)) {
            $controls_html .= '<div class="bible-image-generator-controls">';
            $controls_html .= '<button id="generate-verse-image-button' . esc_attr($unique_id_suffix) . '" class="bible-control-button" data-action="generate-image" data-verse-text="' . esc_attr($verse_object->text) . '" data-verse-reference="' . esc_attr($verse_reference_text) . '"><i class="fas fa-image"></i> <span class="label">' . esc_html__('إنشاء صورة للمشاركة', 'my-bible-plugin') . '</span></button>';
            $controls_html .= '<div class="bible-image-options-group">';
            $controls_html .= '<div class="bible-image-option">';
            $controls_html .= '<label for="bible-image-font-select' . esc_attr($unique_id_suffix) . '">' . esc_html__('الخط:', 'my-bible-plugin') . '</label>';
            $controls_html .= '<select id="bible-image-font-select' . esc_attr($unique_id_suffix) . '" class="bible-image-select">';
            $controls_html .= '<option value="">' . esc_html__('اختر الخط...', 'my-bible-plugin') . '</option>';
            $controls_html .= '</select></div>';
            $controls_html .= '<div class="bible-image-option">';
            $controls_html .= '<label for="bible-image-bg-select' . esc_attr($unique_id_suffix) . '">' . esc_html__('الخلفية:', 'my-bible-plugin') . '</label>';
            $controls_html .= '<select id="bible-image-bg-select' . esc_attr($unique_id_suffix) . '" class="bible-image-select">';
            $controls_html .= '<option value="">' . esc_html__('اختر الخلفية...', 'my-bible-plugin') . '</option>';
            $controls_html .= '</select></div>';
            $controls_html .= '</div>';
            $controls_html .= '</div>';
        }
        $controls_html .= '</div>';
        return $controls_html;
    }
}

if (!function_exists('my_bible_sanitize_book_name')) {
    function my_bible_sanitize_book_name($book_name)
    {
        if (empty($book_name))
            return '';
        $book_name = (string) $book_name;
        $book_name = trim($book_name);
        $book_name = str_replace('-', ' ', $book_name);
        $book_name = preg_replace('/[\x{0617}-\x{061A}\x{064B}-\x{065F}\x{06D6}-\x{06ED}]/u', '', $book_name);
        $book_name = str_replace(array('أ', 'إ', 'آ', 'ٱ', 'أُ', 'إِ'), 'ا', $book_name);
        $book_name = str_replace(array('ى'), 'ي', $book_name);
        $book_name = preg_replace('/\s+/', ' ', $book_name);
        return trim($book_name);
    }
}

if (!function_exists('my_bible_create_book_slug')) {
    function my_bible_create_book_slug($book_name)
    {
        if (empty($book_name))
            return '';
        $slug = my_bible_sanitize_book_name($book_name);
        $slug = str_replace(' ', '-', $slug);
        $slug = preg_replace('/[^\p{Arabic}\p{N}a-zA-Z0-9\-]+/u', '', $slug);
        return rawurlencode($slug);
    }
}

if (!function_exists('my_bible_get_defined_book_order_within_testaments')) {
    function my_bible_get_defined_book_order_within_testaments()
    {
        return array(
            'العهد القديم' => array('التكوين', 'الخروج', 'اللاويين', 'العدد', 'التثنية', 'يشوع', 'القضاة', 'راعوث', '1 صموئيل', '2 صموئيل', '1 ملوك', '2 ملوك', '1 أخبار', '2 أخبار', 'عزرا', 'نحميا', 'أستير', 'أيوب', 'المزامير', 'الأمثال', 'الجامعة', 'نشيد الأنشاد', 'إشعياء', 'إرميا', 'مراثي إرميا', 'حزقيال', 'دانيال', 'هوشع', 'يوئيل', 'عاموس', 'عوبديا', 'يونان', 'ميخا', 'ناحوم', 'حبقوق', 'صفنيا', 'حجي', 'زكريا', 'ملاخي', 'طوبيا', 'يهوديت', 'تتمة أستير', 'الحكمة', 'يشوع بن سيراخ', 'باروخ', 'تتمة دانيال', '1 مكابيين', '2 مكابيين'),
            'العهد الجديد' => array('متى', 'مرقس', 'لوقا', 'يوحنا', 'أعمال الرسل', 'رومية', '1 كورنثوس', '2 كورنثوس', 'غلاطية', 'أفسس', 'فيلبي', 'كولوسي', '1 تسالونيكي', '2 تسالونيكي', '1 تيموثاوس', '2 تيموثاوس', 'تيطس', 'فليمون', 'عبرانيين', 'يعقوب', '1 بطرس', '2 بطرس', '1 يوحنا', '2 يوحنا', '3 يوحنا', 'يهوذا', 'رؤيا يوحنا')
        );
    }
}

if (!function_exists('my_bible_get_book_order_from_db')) {
    function my_bible_get_book_order_from_db($testament_value_in_db = 'all')
    {
        $cache_key = 'bible_book_order_' . md5(is_string($testament_value_in_db) ? $testament_value_in_db : 'serialized_array');
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            return $cached_result;
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'bible_verses';
        $where_clause = '';
        $prepare_args = array();
        if ($testament_value_in_db !== 'all' && !empty($testament_value_in_db)) {
            $valid_testaments = $wpdb->get_col("SELECT DISTINCT testament FROM {$table_name} WHERE testament != ''");
            if (!in_array($testament_value_in_db, $valid_testaments) && $testament_value_in_db !== 'all') {
                set_transient($cache_key, array(), HOUR_IN_SECONDS);
                return array();
            }
            $where_clause = "WHERE testament = %s";
            $prepare_args[] = $testament_value_in_db;
        }
        $defined_order_for_current_testament = array();
        $order_by_clause_parts = array();
        $all_defined_orders = my_bible_get_defined_book_order_within_testaments();
        if ($testament_value_in_db !== 'all' && isset($all_defined_orders[$testament_value_in_db])) {
            $defined_order_for_current_testament = $all_defined_orders[$testament_value_in_db];
        } elseif ($testament_value_in_db === 'all') {
            $ot_order = isset($all_defined_orders['العهد القديم']) ? $all_defined_orders['العهد القديم'] : array();
            $nt_order = isset($all_defined_orders['العهد الجديد']) ? $all_defined_orders['العهد الجديد'] : array();
            $defined_order_for_current_testament = array_merge($ot_order, $nt_order);
        }
        if (!empty($defined_order_for_current_testament)) {
            $books_in_db_for_testament_query = "SELECT DISTINCT book FROM {$table_name} {$where_clause}";
            if (!empty($prepare_args)) {
                $books_in_db_for_testament_query = $wpdb->prepare($books_in_db_for_testament_query, $prepare_args);
            }
            $books_actually_in_db_for_testament = $wpdb->get_col($books_in_db_for_testament_query);
            if ($books_actually_in_db_for_testament) {
                $final_ordered_list_from_defined = array_values(array_intersect($defined_order_for_current_testament, $books_actually_in_db_for_testament));
                $remaining_books_in_db = array_diff($books_actually_in_db_for_testament, $final_ordered_list_from_defined);
                if ($remaining_books_in_db) {
                    sort($remaining_books_in_db);
                    $final_ordered_list_from_defined = array_merge($final_ordered_list_from_defined, $remaining_books_in_db);
                }
                if (!empty($final_ordered_list_from_defined)) {
                    // إصلاح أمني: استخدام طريقة آمنة لبناء FIELD query
                    $placeholders = implode(', ', array_fill(0, count($final_ordered_list_from_defined), '%s'));
                    $order_by_clause_parts[] = $wpdb->prepare("FIELD(book, $placeholders)", ...$final_ordered_list_from_defined);
                }
            }
        }
        $order_by_clause_parts[] = "book ASC";
        $order_by_sql = "ORDER BY " . implode(', ', $order_by_clause_parts);
        $sql = "SELECT DISTINCT book FROM {$table_name} {$where_clause} {$order_by_sql}";
        if (!empty($prepare_args) && !empty($where_clause)) {
            $sql = $wpdb->prepare($sql, $prepare_args);
        }
        $books = $wpdb->get_col($sql);
        if ($wpdb->last_error) {
            my_bible_log_error("DB Error in get_book_order_from_db: " . $wpdb->last_error . " SQL: " . $sql);
            set_transient($cache_key, array(), HOUR_IN_SECONDS);
            return array();
        }
        set_transient($cache_key, $books ? $books : array(), HOUR_IN_SECONDS);
        return $books ? $books : array();
    }
}
if (!function_exists('my_bible_get_book_name_from_slug')) {
    function my_bible_get_book_name_from_slug($book_slug)
    {
        global $wpdb;
        if (empty($book_slug))
            return false;
        $decoded_slug = rawurldecode($book_slug);
        $table_name = $wpdb->prefix . 'bible_verses';

        // 1. Try Direct Match (English numbers as is)
        $book_name_try_direct = str_replace('-', ' ', $decoded_slug);
        $db_book_name = $wpdb->get_var($wpdb->prepare("SELECT DISTINCT book FROM $table_name WHERE book = %s", $book_name_try_direct));
        if ($db_book_name)
            return $db_book_name;

        // 2. Try converting Arabic numbers to English (DB uses English numbers usually)
        $book_name_english_nums = str_replace(
            array('٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'),
            array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9'),
            $book_name_try_direct
        );
        if ($book_name_english_nums !== $book_name_try_direct) {
            $db_book_name = $wpdb->get_var($wpdb->prepare("SELECT DISTINCT book FROM $table_name WHERE book = %s", $book_name_english_nums));
            if ($db_book_name)
                return $db_book_name;
        }

        // 3. Try constructing standard format "Num Name"
        if (preg_match('/^(\d+)\s*(.+)$/u', $book_name_english_nums, $matches)) {
            $num = $matches[1];
            $base = trim($matches[2]);
            // Try "1 Samuel" format
            $try_std = $num . ' ' . $base;
            $db_book_name = $wpdb->get_var($wpdb->prepare("SELECT DISTINCT book FROM $table_name WHERE book = %s", $try_std));
            if ($db_book_name)
                return $db_book_name;
        }

        // 4. Fallback: Sanitized match
        $sanitized_slug_as_name = my_bible_sanitize_book_name($book_name_try_direct);
        $db_book_name_alt_query = $wpdb->prepare("SELECT DISTINCT book FROM $table_name WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE( REPLACE(REPLACE(REPLACE(REPLACE(book, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ٱ', 'ا'), 'أُ', 'ا'), 'إِ', 'ا'), 'ى', 'ي'), 'ً', ''), 'ٌ', ''), 'ٍ', ''), 'َ', ''), 'ُ', ''), 'ِ', '') = %s LIMIT 1", $sanitized_slug_as_name);
        $db_book_name_alt = $wpdb->get_var($db_book_name_alt_query);
        if ($db_book_name_alt)
            return $db_book_name_alt;

        // 5. Final Fallback: LIKE query if no numbers (to avoid false positives e.g. 1 Kings matching Kings)
        // Only do LIKE if input doesn't start with a number, OR if we are desperate.
        // Actually, for "1 Samuel", if exact match failed, LIKE might help if DB has "1  Samuel".
        if (!preg_match('/\d/', $decoded_slug)) {
            $possible_books = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT book FROM $table_name WHERE book LIKE %s", '%' . $wpdb->esc_like($book_name_try_direct) . '%'));
            if (count($possible_books) === 1) {
                return $possible_books[0]->book;
            }
        }
        return false;
    }
}
if (!function_exists('my_bible_parse_reference')) {
    function my_bible_parse_reference($reference_string)
    {
        $reference_string = trim($reference_string);
        $parsed = array('book' => null, 'chapter' => null, 'verse' => null, 'is_reference' => false);
        if (preg_match('/^([0-9]?\s*[^\d\s]+(?:\s+[^\d\s]+)*)\s*([0-9]+)(?:[\s:.]*\s*([0-9]+))?$/u', $reference_string, $matches)) {
            $book_name_input = trim($matches[1]);
            $chapter_num = intval($matches[2]);
            $verse_num = isset($matches[3]) && !empty($matches[3]) ? intval($matches[3]) : null;
            global $wpdb;
            $table_name = $wpdb->prefix . 'bible_verses';
            $db_book_name = $wpdb->get_var($wpdb->prepare("SELECT DISTINCT book FROM $table_name WHERE book = %s", $book_name_input));
            if (!$db_book_name) {
                $sanitized_input_book = my_bible_sanitize_book_name($book_name_input);
                $db_book_name = $wpdb->get_var($wpdb->prepare("SELECT DISTINCT book FROM $table_name WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE( REPLACE(REPLACE(REPLACE(REPLACE(book, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ٱ', 'ا'), 'أُ', 'ا'), 'إِ', 'ا'), 'ى', 'ي'), 'ً', ''), 'ٌ', ''), 'ٍ', ''), 'َ', ''), 'ُ', ''), 'ِ', '') = %s", $sanitized_input_book));
            }
            if (!$db_book_name && preg_match('/^([0-9]+)\s+(.+)$/u', $book_name_input, $book_parts)) {
                $number_map_to_text = array('1' => 'الأول', '2' => 'الثاني', '3' => 'الثالث');
                $numeric_prefix_num = $book_parts[1];
                $book_base_name = trim($book_parts[2]);
                $possible_books_query = $wpdb->prepare("SELECT DISTINCT book FROM $table_name WHERE book LIKE %s AND book LIKE %s", '%' . $wpdb->esc_like($book_base_name) . '%', isset($number_map_to_text[$numeric_prefix_num]) ? '%' . $wpdb->esc_like($number_map_to_text[$numeric_prefix_num]) . '%' : '% %');
                $possible_books = $wpdb->get_col($possible_books_query);
                if (count($possible_books) === 1) {
                    $db_book_name = $possible_books[0];
                } elseif (count($possible_books) > 1) {
                    foreach ($possible_books as $possible_book) {
                        if (isset($number_map_to_text[$numeric_prefix_num]) && (strpos($possible_book, $number_map_to_text[$numeric_prefix_num] . ' ' . $book_base_name) !== false || strpos($possible_book, $book_base_name . ' ' . $number_map_to_text[$numeric_prefix_num]) !== false || strpos($possible_book, $book_base_name . $number_map_to_text[$numeric_prefix_num]) !== false)) {
                            $db_book_name = $possible_book;
                            break;
                        }
                    }
                }
            }
            if ($db_book_name && $chapter_num > 0) {
                $parsed['book'] = $db_book_name;
                $parsed['chapter'] = $chapter_num;
                $parsed['verse'] = ($verse_num !== null && $verse_num > 0) ? $verse_num : null;
                $parsed['is_reference'] = true;
            }
        }
        return $parsed;
    }
}
if (!function_exists('my_bible_find_book_match')) {
    // Helper to find exact book name from input (text or abbreviation)
    function my_bible_find_book_match($input_book)
    {
        if (empty($input_book))
            return null;
        $cache_key = 'my_bible_bk_match_' . md5(trim((string) $input_book));
        $cached = get_transient($cache_key);
        if ($cached !== false)
            return $cached;

        $result = my_bible_find_book_match_internal($input_book);

        if ($result)
            set_transient($cache_key, $result, 604800); // 1 week
        return $result;
    }

    function my_bible_find_book_match_internal($input_book)
    {
        global $wpdb;
        $table_verses = $wpdb->prefix . 'bible_verses';

        // DEBUG: Trace lookup
        // error_log("My Bible Lookup: Searching for ['$input_book']");

        // 1. Common Arabic book abbreviation dictionary
        // Updated to match actual database names
        $book_abbreviations = array(
            // Old Testament
            'تك' => 'تكوين',
            'خر' => 'خروج',
            'لا' => 'لاويين',
            'اللاويين' => 'لاويين',  // DB has no "ال"
            'عد' => 'عدد',
            'العدد' => 'عدد',  // DB has no "ال"
            'تث' => 'تثنية',
            'التثنية' => 'تثنية',
            'يش' => 'يشوع',
            'قض' => 'قضاة',
            'را' => 'راعوث',

            // Samuel: DB uses "صموئيل الاول", "صموئيل الثاني"
            'ص' => 'صموئيل الاول',
            'صم' => 'صموئيل', // Ambiguous
            '١صم' => 'صموئيل الاول',
            '٢صم' => 'صموئيل الثاني',
            '1صم' => 'صموئيل الاول',
            '2صم' => 'صموئيل الثاني',
            '١ صم' => 'صموئيل الاول',
            '٢ صم' => 'صموئيل الثاني',
            '1 صم' => 'صموئيل الاول',
            '2 صم' => 'صموئيل الثاني',

            'مل' => 'ملوك الاول', // Default/Ambiguous
            // Kings: DB uses "ملوك الاول", "ملوك الثاني"
            '١مل' => 'ملوك الاول',
            '٢مل' => 'ملوك الثاني',
            '1مل' => 'ملوك الاول',
            '2مل' => 'ملوك الثاني',
            '١ مل' => 'ملوك الاول',
            '٢ مل' => 'ملوك الثاني',
            '1 مل' => 'ملوك الاول',
            '2 مل' => 'ملوك الثاني',

            // Chronicles: DB uses "اخبار الايام الاول", "اخبار الايام الثاني"
            'أخ' => 'اخبار الايام الاول',
            'اخ' => 'اخبار الايام الاول',
            '١أخ' => 'اخبار الايام الاول',
            '٢أخ' => 'اخبار الايام الثاني',
            '1أخ' => 'اخبار الايام الاول',
            '2أخ' => 'اخبار الايام الثاني',
            '١اخ' => 'اخبار الايام الاول',
            '٢اخ' => 'اخبار الايام الثاني',
            '1اخ' => 'اخبار الايام الاول',
            '2اخ' => 'اخبار الايام الثاني',

            // Wisdom (Apocrypha)
            'حك' => 'الحكمة',
            'حكمة' => 'الحكمة',

            // Common variations with AL
            'التكوين' => 'تكوين',
            'الخروج' => 'خروج',
            'اللاويين' => 'لاويين',
            'العدد' => 'عدد',
            'التثنية' => 'تثنية',
            'القضاة' => 'قضاة',
            'المزامير' => 'مزامير',
            'الامثال' => 'امثال',
            'الأمثال' => 'امثال',
            'الجامعة' => 'جامعة',
            // Standardizing others
            'نشيد' => 'نشيد الأنشاد', // DB uses hamza
            'النشيد' => 'نشيد الأنشاد',

            // Handle spaced versions
            '١ أخ' => 'اخبار الايام الاول',
            '٢ أخ' => 'اخبار الايام الثاني',
            '1 أخ' => 'اخبار الايام الاول',
            '2 أخ' => 'اخبار الايام الثاني',
            '١ اخ' => 'اخبار الايام الاول',
            '٢ اخ' => 'اخبار الايام الثاني',
            '1 اخ' => 'اخبار الايام الاول',
            '2 اخ' => 'اخبار الايام الثاني',
            // Also add for أي (Chronicles abbreviation variant)
            '١أي' => 'اخبار الايام الاول',
            '٢أي' => 'اخبار الايام الثاني',
            '1أي' => 'اخبار الايام الاول',
            '2أي' => 'اخبار الايام الثاني',
            '١ أي' => 'اخبار الايام الاول',
            '٢ أي' => 'اخبار الايام الثاني',
            '1 أي' => 'اخبار الايام الاول',
            '2 أي' => 'اخبار الايام الثاني',

            'عز' => 'عزرا',
            'نح' => 'نحميا',
            'أس' => 'أستير',
            'اس' => 'استير', // DB check needed, likely 'استير' based on pattern
            'أي' => 'ايوب', // DB: ايوب
            'اي' => 'ايوب',
            'أيوب' => 'ايوب',
            'ايوب' => 'ايوب',
            'مز' => 'مزامير', // DB often no AL
            'أم' => 'امثال', // DB: امثال
            'ام' => 'امثال',
            'جا' => 'الجامعة', // DB usually has AL
            'نش' => 'نشيد الانشاد', // DB: نشيد الانشاد
            // Fixed: إشعياء -> اشعياء
            'إش' => 'اشعياء', // DB: اشعياء
            'اش' => 'اشعياء',
            // Fixed: إرميا -> ارميا
            'إر' => 'ارميا',// DB: ارميا
            'ار' => 'ارميا',
            'مرا' => 'مراثي ارميا', // Assuming follows pattern
            'حز' => 'حزقيال',
            'دا' => 'دانيال',
            'هو' => 'هوشع',
            'يوء' => 'يوئيل',
            'يؤ' => 'يوئيل',
            'عا' => 'عاموس',
            'عو' => 'عوبديا',
            'يون' => 'يونان',
            'مي' => 'ميخا',
            'نا' => 'ناحوم',
            'حب' => 'حبقوق',
            'صف' => 'صفنيا',
            'حج' => 'حجي',
            'زك' => 'زكريا',
            'ملا' => 'ملاخي',
            'طو' => 'طوبيا',
            'يهو' => 'يهوديت',
            'تتمة' => 'تتمة أستير',
            'يشوع بن سيراخ' => 'يشوع بن سيراخ',
            'سيراخ' => 'يشوع بن سيراخ',
            'بار' => 'باروخ',
            'تتمة دانيال' => 'تتمة دانيال',
            'مك' => 'مكابيين', // Ambiguous
            '١مك' => 'مكابيين الاول',
            '٢مك' => 'مكابيين الثاني',
            '1مك' => 'مكابيين الاول',
            '2مك' => 'مكابيين الثاني',

            // New Testament
            'مت' => 'متى',
            'مر' => 'مرقس',
            'لو' => 'لوقا',
            'يو' => 'يوحنا',
            'أع' => 'اعمال الرسل', // DB: اعمال الرسل
            'اع' => 'اعمال الرسل',
            'رو' => 'رومية',
            'كو' => 'كولوسي', // Changed from 1 Cor (Ambiguous, but Colossians context fits reported issues)
            'كول' => 'كولوسي',
            'كورنثوس الاولى' => 'كورنثوس الاولى',
            'كورنثوس الأولى' => 'كورنثوس الاولى',
            'كورنثوس الاولي' => 'كورنثوس الاولى',
            'كورنثوس الثانية' => 'كورنثوس الثانية',
            'كورنثوس الثانيه' => 'كورنثوس الثانية',
            // Corinthians with numbers
            '١كو' => 'كورنثوس الاولى',
            '٢كو' => 'كورنثوس الثانية',
            '1كو' => 'كورنثوس الاولى',
            '2كو' => 'كورنثوس الثانية',
            '١ كو' => 'كورنثوس الاولى',
            '٢ كو' => 'كورنثوس الثانية',
            '1 كو' => 'كورنثوس الاولى',
            '2 كو' => 'كورنثوس الثانية',
            'غل' => 'غلاطية',
            'غلط' => 'غلاطية',
            'أف' => 'افسس', // DB: افسس
            'اف' => 'افسس',
            'في' => 'فيلبي',
            'كول' => 'كولوسي',
            'تس' => 'تسالونيكي الاولى',
            'تسالونيكي الاولى' => 'تسالونيكي الاولى',
            'تسالونيكي الأولى' => 'تسالونيكي الاولى',
            'تسالونيكي الثانية' => 'تسالونيكي الثانية',
            'تسالونيكي الثانيه' => 'تسالونيكي الثانية',
            // Thessalonians with numbers
            '١تس' => 'تسالونيكي الاولى',
            '٢تس' => 'تسالونيكي الثانية',
            '1تس' => 'تسالونيكي الاولى',
            '2تس' => 'تسالونيكي الثانية',
            '١ تس' => 'تسالونيكي الاولى',
            '٢ تس' => 'تسالونيكي الثانية',
            '1 تس' => 'تسالونيكي الاولى',
            '2 تس' => 'تسالونيكي الثانية',
            'تي' => 'تيموثاوس الاولى',
            'تيموثاوس الاولى' => 'تيموثاوس الاولى',
            'تيموثاوس الأولى' => 'تيموثاوس الاولى',
            'تيموثاوس الثانية' => 'تيموثاوس الثانية',
            'تيموثاوس الثانيه' => 'تيموثاوس الثانية',
            // Timothy with numbers
            '١تي' => 'تيموثاوس الاولى',
            '٢تي' => 'تيموثاوس الثانية',
            '1تي' => 'تيموثاوس الاولى',
            '2تي' => 'تيموثاوس الثانية',
            '١ تي' => 'تيموثاوس الاولى',
            '٢ تي' => 'تيموثاوس الثانية',
            '1 تي' => 'تيموثاوس الاولى',
            '2 تي' => 'تيموثاوس الثانية',
            'تط' => 'تيطس',
            'فل' => 'فليمون',
            'عب' => 'عبرانيين',
            'يع' => 'يعقوب',
            'بط' => 'بطرس الاولى',
            'بطرس الاولى' => 'بطرس الاولى',
            'بطرس الأولى' => 'بطرس الاولى',
            'بطرس الثانية' => 'بطرس الثانية',
            'بطرس الثانيه' => 'بطرس الثانية',
            // Peter with numbers
            '١بط' => 'بطرس الاولى',
            '٢بط' => 'بطرس الثانية',
            '1بط' => 'بطرس الاولى',
            '2بط' => 'بطرس الثانية',
            '١ بط' => 'بطرس الاولى',
            '٢ بط' => 'بطرس الثانية',
            '1 بط' => 'بطرس الاولى',
            '2 بط' => 'بطرس الثانية',
            // John with numbers
            'يوحنا الاولى' => 'يوحنا الاولى',
            'يوحنا الأولى' => 'يوحنا الاولى',
            'يوحنا الثانية' => 'يوحنا الثانية',
            'يوحنا الثانيه' => 'يوحنا الثانية',
            'يوحنا الرابعة' => 'يوحنا الثالثة', // Typo in user input logic sometimes? Just safe.
            'يوحنا الثالثة' => 'يوحنا الثالثة',
            '١يو' => 'يوحنا الاولى',
            '٢يو' => 'يوحنا الثانية',
            '٣يو' => 'يوحنا الثالثة',
            '1يو' => 'يوحنا الاولى',
            '2يو' => 'يوحنا الثانية',
            '3يو' => 'يوحنا الثالثة',
            '١ يو' => 'يوحنا الاولى',
            '٢ يو' => 'يوحنا الثانية',
            '٣ يو' => 'يوحنا الثالثة',
            '1 يو' => 'يوحنا الاولى',
            '2 يو' => 'يوحنا الثانية',
            '3 يو' => 'يوحنا الثالثة',
            'يه' => 'يهوذا',
            'رؤ' => 'رؤيا يوحنا'
        );


        // Number to text mapping
        $number_map_to_text = array('1' => 'الأول', '2' => 'الثاني', '3' => 'الثالث');

        // 0. Clean common prefixes (added for better matching)
        $input_book = html_entity_decode($input_book); // Handle &nbsp; etc
        $input_book = str_replace('&nbsp;', ' ', $input_book);
        $input_book = preg_replace('/^(راجع|انظر|شاهد|قارن|مثل|سفر|رسالة)\s+/u', '', trim($input_book));

        // 0.1 Handle spaced abbreviations (e.g., "م ز" -> "مز")
        $input_book = str_replace("\xc2\xa0", ' ', $input_book); // Explicit NBSP clean
        $input_book = preg_replace('/\s+/u', ' ', $input_book); // Normalize spaces (Unicode)
        if ($input_book === 'م ز')
            $input_book = 'مز';

        // 2. Extract number prefix if exists (٢صم -> 2, صم)
        $book_number = '';
        $book_base = $input_book;
        if (preg_match('/^([\d\x{0660}-\x{0669}]+)(.+)$/u', $input_book, $num_matches)) {
            $book_number = function_exists('my_bible_arabic_to_english_numbers')
                ? my_bible_arabic_to_english_numbers($num_matches[1])
                : str_replace(array('٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'), array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9'), $num_matches[1]);
            $book_base = trim($num_matches[2]);
        }

        // 3. Sanitize (remove diacritics, simple normalization)
        $sanitized_input = function_exists('my_bible_sanitize_book_name')
            ? my_bible_sanitize_book_name($book_base)
            : preg_replace('/[\x{064B}-\x{065F}]/u', '', str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', str_replace('ى', 'ي', $book_base)));

        // Strategy A: Dictionary Lookup
        // Definitions (RESTORED)
        // Definitions (RESTORED)
        $abbrev_key = $book_number ? $book_number . $book_base : $book_base;

        // Sanitize for lookup (Normalization V2 logic ideally)
        $book_base_norm = my_bible_normalize_arabic($book_base);
        // Sometimes valid keys are normalized (ام) and sometimes not (أم).
        // Best strategy: Check original, then check normalized.

        $keys_to_check = [
            $abbrev_key,
            $book_number ? $book_number . $book_base_norm : $book_base_norm,
            // Try without spaces for merged check later
        ];

        // Helper closure to lookup dictionary name in DB safely
        $lookup_in_db = function ($name) use ($wpdb, $table_verses) {
            // 1. Try Exact Match First (To differentiate 'John' from '1 John')
            $exact = $wpdb->get_var($wpdb->prepare("SELECT book FROM $table_verses WHERE book = %s LIMIT 1", $name));
            if ($exact)
                return $exact;

            // 2. Try Exact Normalized Match (ignoring hamzas/ya in DB)
            // SQL REPLACE chain ...
            // Simplified: LIKE with no wildcards? No, LIKE 'name' is same as = in MySQL (mostly case insensitive, but we deal with Arabic)

            // 3. Fallback to LIKE %name% (Use with caution)
            // If name is short (e.g. يو) this is dangerous. Dictionary usually gives full names though (يوحنا).
            // "يوحنا" LIKE match "يوحنا الاولى". 
            // So we want to match WHERE book = name OR book LIKE 'name %' (starts with name space) OR book LIKE '% name' ?
            // Better: just stick to the original logic but try exact first.

            // 3. Fallback to LIKE %name% (Use with caution)
            return $wpdb->get_var($wpdb->prepare(
                "SELECT DISTINCT book FROM $table_verses WHERE book LIKE %s LIMIT 1",
                '%' . $wpdb->esc_like(trim($name)) . '%'
            ));
        };

        // Add sanitized and merged keys to the list for checking
        $abbrev_key_sanitized = $book_number ? $book_number . $sanitized_input : $sanitized_input;
        $merged_key = str_replace(' ', '', $abbrev_key);
        $merged_key_sanitized = str_replace(' ', '', $abbrev_key_sanitized);

        $keys_to_check = [
            $abbrev_key,
            $abbrev_key_sanitized,
            $merged_key,
            $merged_key_sanitized,
        ];

        // Check Keys
        foreach ($keys_to_check as $k) {
            if (isset($book_abbreviations[$k])) {
                $search_name = $book_abbreviations[$k];
                // Append Number Suffix if needed (only if map didn't already include it)
                // e.g. Input: "2مل"، Key: "2مل" -> val: "ملوك الثاني" (No suffix needed)
                // Input: "2 مل" -> Base "مل", Number "2". Key "مل" -> val "ملوك" (Need suffix)

                // Logic: If input had a number (book_number), AND the resolved name doesn't seem to have a number yet
                // But wait, our map handles "1 مل" -> "ملوك الاول".
                // If we matched "1مل" (abbrev_key), search_name is "ملوك الاول". Suffix NOT needed.
                // If we matched "مل" (base only)? No, abbrev_key includes number.
                // The only case we append number is if we matched the BASE only, which we don't do here yet unless we add it to keys_to_check.

                // Correction: The original code logic was:
                // if ($book_number && !preg_match...)
                // That implies it was checking the Base Key separately?
                // Line 776: $abbrev_key = $book_number . $book_base;
                // So "2مل" matches "2مل".

                // What if map only has "مل" => "ملوك"?
                // Then "2مل" -> key "2مل" (not found) -> key "مل" (not checked!).

                // We should add the BASE key to check list if number exists!

                $db_book = $lookup_in_db($search_name);
                if ($db_book)
                    return $db_book;
            }
        }

        // Fallback: Check Base Key Only + Append Number
        if ($book_number) {
            $base_keys = [$book_base, $book_base_norm];
            foreach ($base_keys as $bk) {
                if (isset($book_abbreviations[$bk])) {
                    $search_name = $book_abbreviations[$bk];
                    if (!preg_match('/ال[اأ]ول|الثاني|الثالث/u', $search_name)) {
                        $search_name .= ' ' . ($number_map_to_text[$book_number] ?? '');
                    }
                    $db_book = $lookup_in_db($search_name);
                    if ($db_book)
                        return $db_book;
                }
            }
        }

        // Strategy B: Exact Match
        $db_book = $wpdb->get_var($wpdb->prepare(
            "SELECT DISTINCT book FROM $table_verses WHERE book = %s LIMIT 1",
            $input_book
        ));
        if ($db_book)
            return $db_book;

        // Strategy C: SQL Normalization (ignore hamzas/dots)
        $db_book = $wpdb->get_var($wpdb->prepare(
            "SELECT DISTINCT book FROM $table_verses 
             WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                   REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(book, 
                   'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ٱ', 'ا'), 'أُ', 'ا'), 'إِ', 'ا'), 
                   'ى', 'ي'), 'ً', ''), 'ٌ', ''), 'ٍ', ''), 'َ', ''), 'ُ', ''), 'ِ', '') = %s 
             LIMIT 1",
            $sanitized_input
        ));
        if ($db_book)
            return $db_book;

        // Strategy D: LIKE Search (Partial match) - CAUTION
        // Only use if length is reasonable to avoid matching "in" to "Corinthians" etc.
        if (mb_strlen($book_base) > 2) {
            $possible_books = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT book FROM $table_verses WHERE book LIKE %s",
                '%' . $wpdb->esc_like($book_base) . '%'
            ));

            if (count($possible_books) === 1) {
                return $possible_books[0];
            } elseif (count($possible_books) > 1 && $book_number) {
                $number_text = isset($number_map_to_text[$book_number]) ? $number_map_to_text[$book_number] : '';
                foreach ($possible_books as $possible_book) {
                    if ($number_text && mb_strpos($possible_book, $number_text, 0, 'UTF-8') !== false) {
                        return $possible_book;
                    }
                }
            } elseif (count($possible_books) > 1 && !$book_number) {
                // Return exact match if exists in the list
                foreach ($possible_books as $pb) {
                    if ($pb === $input_book)
                        return $pb;
                }
                // Otherwise ambiguous - unsafe to guess "Acts" from "A"
                return false;
            }
        }

        return false;
    }
}

if (!function_exists('my_bible_parse_commentary_tags')) {
    /**
     * Parse commentary text and handle all custom tags
     * 
     * Supported Tags:
     * - {{t}}Title{{/t}} -> <h2 class="commentary-title">
     * - {{b}}Subtitle{{/b}} -> <h3 class="commentary-subtitle"> OR <span class="verse-prefix"> if matches verse pattern
     * - {{vt}}Verse Text{{/vt}} -> <div class="commentary-verse-text">
     * - {{p}}Paragraph{{/p}} -> <p class="commentary-p">
     * - {{d}}Divider{{/d}} -> <hr class="commentary-divider">
     * - {{g}}Grid{{/g}} -> <div class="commentary-grid">
     * - {{gr}}Row{{/gr}} -> <div class="commentary-row">
     * - {{gc}}Col{{/gc}} -> <div class="commentary-col">
     * - {{ref...}} -> <a href="..." class="scripture-ref">
     */
    function my_bible_parse_commentary_tags($text, $exclude = array())
    {
        // 1. Titles
        if (!in_array('title', $exclude)) {
            $text = preg_replace('/\{\{t\}\}(.*?)\{\{\/t\}\}/su', '<h2 class="commentary-title">$1</h2>', $text);
        }

        // 2. Subtitles (Bold)
        // Note: Specific regex for verse prefix is handled in specific contexts (ajax), 
        // but here we provide a general fallback styling.
        if (!in_array('subtitle', $exclude)) {
            $text = preg_replace('/\{\{b\}\}(.*?)\{\{\/b\}\}/su', '<h3 class="commentary-subtitle">$1</h3>', $text);
        }

        // 3. Paragraphs
        if (!in_array('paragraph', $exclude)) {
            $text = str_replace('{{p}}', '<p class="commentary-p">', $text);
        }

        // 4. Dividers
        $text = str_replace('{{d}}', '<hr class="commentary-divider">', $text);

        // 5. Grid System
        $text = str_replace('{{g}}', '<div class="commentary-grid">', $text);
        $text = str_replace('{{/g}}', '</div>', $text);
        $text = str_replace('{{gr}}', '<div class="commentary-row">', $text);
        $text = str_replace('{{/gr}}', '</div>', $text);
        $text = str_replace('{{gc}}', '<div class="commentary-col">', $text);
        $text = str_replace('{{/gc}}', '</div>', $text);

        // 6. Line Breaks
        $text = str_replace('{{l}}', '<br class="line-break">', $text);

        // 7. Scripture References
        // Pattern: {{ref_TYPE_BOOK_CHAPTER_...}}Text{{/ref}}
        // We capture the whole tag and replace with link.
        $text = preg_replace_callback('/\{\{ref_([a-z]+)_([\d_]+)\}\}(.*?)\{\{\/ref\}\}/u', function ($matches) {
            $type = $matches[1]; // s (single), r (range), etc.
            $coords = $matches[2]; // e.g. 24_2_4
            $link_text = $matches[3];

            // Generate a data attribute for JS to handle
            return sprintf(
                '<a href="javascript:void(0);" class="scripture-ref" data-ref-type="%s" data-ref-coords="%s">%s</a>',
                $type,
                $coords,
                $link_text
            );
        }, $text);

        return $text;
    }
}
?>