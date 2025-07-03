<?php
// منع الوصول المباشر
if (!defined('ABSPATH')) {
    exit;
}

// --- الدوال المساعدة العامة ---
// يتم تضمين هذا الملف في بداية my-bible-plugin.php

if (!function_exists('my_bible_get_controls_html')) {
    function my_bible_get_controls_html($context = 'content', $verse_object = null, $verse_reference_text = '') {
        $id_suffix = ($context === 'search') ? '-search' : '';
        $controls_html = '<div class="bible-controls">';
        $controls_html .= '<button id="toggle-tashkeel' . $id_suffix . '" class="bible-control-button" data-action="toggle-tashkeel"><i class="fas fa-language"></i> <span class="label">' . esc_html__('إلغاء التشكيل', 'my-bible-plugin') . '</span></button>';
        $controls_html .= '<button id="increase-font' . $id_suffix . '" class="bible-control-button" data-action="increase-font"><i class="fas fa-plus"></i> <span class="label">' . esc_html__('تكبير الخط', 'my-bible-plugin') . '</span></button>';
        $controls_html .= '<button id="decrease-font' . $id_suffix . '" class="bible-control-button" data-action="decrease-font"><i class="fas fa-minus"></i> <span class="label">' . esc_html__('تصغير الخط', 'my-bible-plugin') . '</span></button>';
        $controls_html .= '<button id="dark-mode-toggle" class="bible-control-button dark-mode-toggle-button" data-action="dark-mode-toggle"><i class="fas fa-moon"></i> <span class="label">' . esc_html__('الوضع الليلي', 'my-bible-plugin') . '</span></button>';
        $controls_html .= '<button id="read-aloud-button" class="bible-control-button read-aloud-button" data-action="read-aloud"><i class="fas fa-volume-up"></i> <span class="label">' . esc_html__('قراءة بصوت عالٍ', 'my-bible-plugin') . '</span></button>';
        if ($context === 'single_verse' && $verse_object && !empty($verse_reference_text)) {
            $controls_html .= '<button id="generate-verse-image-button" class="bible-control-button" data-action="generate-image" data-verse-text="' . esc_attr($verse_object->text) . '" data-verse-reference="' . esc_attr($verse_reference_text) . '"><i class="fas fa-image"></i> <span class="label">' . esc_html__('إنشاء صورة للمشاركة', 'my-bible-plugin') . '</span></button>';
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
        $book_name = str_replace(array('ى'), 'ي', $book_name);
        $book_name = preg_replace('/\s+/', ' ', $book_name);
        return trim($book_name);
    }
}

if (!function_exists('my_bible_create_book_slug')) {
    function my_bible_create_book_slug($book_name) {
        if (empty($book_name)) return '';
        $slug = my_bible_sanitize_book_name($book_name); 
        $slug = str_replace(' ', '-', $slug);
        $slug = preg_replace('/[^A-Za-z0-9\-\p{Arabic}\p{N}]/u', '', $slug); 
        return rawurlencode($slug); 
    }
}

if (!function_exists('my_bible_get_defined_book_order_within_testaments')) {
    function my_bible_get_defined_book_order_within_testaments() {
        return array(
            'العهد القديم' => array( 
                'سفر التكوين', 'سفر الخروج', 'سفر اللاويين', 'سفر العدد', 'سفر التثنية',
                'سفر يشوع', 'سفر القضاة', 'سفر راعوث', 'سفر صموئيل الأول', 'سفر صموئيل الثاني', 'سفر الملوك الأول', 'سفر الملوك الثاني', 'سفر أخبار الأيام الأول', 'سفر أخبار الأيام الثاني', 'سفر عزرا', 'سفر نحميا', 'سفر أستير',
                'سفر أيوب', 'سفر المزامير', 'سفر الأمثال', 'سفر الجامعة', 'سفر نشيد الأنشاد',
                'سفر إشعياء', 'سفر إرميا', 'سفر مراثي إرميا', 'سفر حزقيال', 'سفر دانيال',
                'سفر هوشع', 'سفر يوئيل', 'سفر عاموس', 'سفر عوبديا', 'سفر يونان', 'سفر ميخا', 'سفر ناحوم', 'سفر حبقوق', 'سفر صفنيا', 'سفر حجي', 'سفر زكريا', 'سفر ملاخي'
            ),
            'العهد الجديد' => array( 
                'إنجيل متى', 'إنجيل مرقس', 'إنجيل لوقا', 'إنجيل يوحنا',
                'سفر أعمال الرسل',
                'رسالة بولس الرسول إلى أهل رومية', 'رسالة بولس الرسول الأولى إلى أهل كورنثوس', 'رسالة بولس الرسول الثانية إلى أهل كورنثوس', 'رسالة بولس الرسول إلى أهل غلاطية', 'رسالة بولس الرسول إلى أهل أفسس', 'رسالة بولس الرسول إلى أهل فيلبي', 'رسالة بولس الرسول إلى أهل كولوسي', 'رسالة بولس الرسول الأولى إلى أهل تسالونيكي', 'رسالة بولس الرسول الثانية إلى أهل تسالونيكي', 'رسالة بولس الرسول الأولى إلى تيموثاوس', 'رسالة بولس الرسول الثانية إلى تيموثاوس', 'رسالة بولس الرسول إلى تيطس', 'رسالة بولس الرسول إلى فليمون',
                'الرسالة إلى العبرانيين', 'رسالة يعقوب', 'رسالة بطرس الأولى', 'رسالة بطرس الثانية', 'رسالة يوحنا الأولى', 'رسالة يوحنا الثانية', 'رسالة يوحنا الثالثة', 'رسالة يهوذا',
                'سفر رؤيا يوحنا اللاهوتي'
            )
        );
    }
}

// Improve the book order function with caching
function my_bible_get_book_order_from_db($testament_value_in_db = 'all') {
    $cache_key = 'bible_book_order_' . md5($testament_value_in_db);
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
            return array(); 
        }
        $where_clause = "WHERE testament = %s";
        $prepare_args[] = $testament_value_in_db;
    }
    
    $defined_order_for_current_testament = array();
    $order_by_clause = "ORDER BY book ASC"; 

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
            $final_ordered_list = array_values(array_intersect($defined_order_for_current_testament, $books_actually_in_db_for_testament));
            $remaining_books = array_diff($books_actually_in_db_for_testament, $final_ordered_list);
            if ($remaining_books) { 
                sort($remaining_books); 
                $final_ordered_list = array_merge($final_ordered_list, $remaining_books);
            }

            if (!empty($final_ordered_list)) {
                 $order_by_clause = "ORDER BY FIELD(book, " . implode(', ', array_map(function($book) { return "'".esc_sql($book)."'"; }, $final_ordered_list)) . ")";
            }
        }
    }
    
    $sql = "SELECT DISTINCT book FROM {$table_name} {$where_clause} {$order_by_clause}";
    if (!empty($prepare_args)) {
        $sql = $wpdb->prepare($sql, $prepare_args);
    }
    
    $books = $wpdb->get_col($sql);
    // Cache the result for 1 hour
    if (!empty($books)) {
        set_transient($cache_key, $books, HOUR_IN_SECONDS);
    }
    
    return $books ? $books : array();
}


if (!function_exists('my_bible_get_book_name_from_slug')) {
    function my_bible_get_book_name_from_slug($book_slug) {
        global $wpdb;
        if (empty($book_slug)) return false;
        $table_name = $wpdb->prefix . 'bible_verses';
        $book_name_try = str_replace('-', ' ', rawurldecode($book_slug));
        $db_book_name = $wpdb->get_var($wpdb->prepare( "SELECT DISTINCT book FROM $table_name WHERE book = %s", $book_name_try ));
        if ($db_book_name) return $db_book_name;
        $sanitized_name = my_bible_sanitize_book_name($book_name_try);
        $db_book_name_alt = $wpdb->get_var($wpdb->prepare( "SELECT DISTINCT book FROM $table_name WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(book, 'ً', ''), 'َ', ''), 'ُ', ''), 'ِ', ''), 'ْ', ''), 'ّ', ''), 'إ', 'ا'), 'أ', 'ا'), 'آ', 'ا') = %s LIMIT 1", $sanitized_name ));
        if ($db_book_name_alt) return $db_book_name_alt;
        return false;
    }
}

if (!function_exists('my_bible_parse_reference')) {
    function my_bible_parse_reference($reference_string) {
        $reference_string = trim($reference_string);
        $parsed = array('book' => null, 'chapter' => null, 'verse' => null, 'is_reference' => false);

        if (preg_match('/^(.+?)\s*([0-9]+)(?:\s*[:.]\s*([0-9]+))?$/u', $reference_string, $matches)) {
            $book_name_input = trim($matches[1]);
            $chapter_num = intval($matches[2]);
            $verse_num = isset($matches[3]) ? intval($matches[3]) : null;

            global $wpdb;
            $table_name = $wpdb->prefix . 'bible_verses';
            $db_book_name = $wpdb->get_var($wpdb->prepare("SELECT DISTINCT book FROM $table_name WHERE book = %s", $book_name_input));
            if (!$db_book_name) {
                $sanitized_input_book = my_bible_sanitize_book_name($book_name_input);
                $db_book_name = $wpdb->get_var($wpdb->prepare("SELECT DISTINCT book FROM $table_name WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(book, 'ً', ''), 'َ', ''), 'ُ', ''), 'ِ', ''), 'ْ', ''), 'ّ', ''), 'إ', 'ا'), 'أ', 'ا'), 'آ', 'ا') = %s", $sanitized_input_book));
            }
            if (!$db_book_name && preg_match('/^([0-9]+)\s+(.+)$/u', $book_name_input, $book_parts)) {
                $number_map = array('1' => 'الأولى', '2' => 'الثانية', '3' => 'الثالثة'); 
                $numeric_prefix = $book_parts[1];
                $book_base_name = trim($book_parts[2]);
                $possible_books = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT book FROM $table_name WHERE book LIKE %s", '%' . $wpdb->esc_like($book_base_name) . '%'));
                foreach ($possible_books as $possible_book) {
                    if (isset($number_map[$numeric_prefix]) && strpos($possible_book, $number_map[$numeric_prefix]) !== false && strpos($possible_book, $book_base_name) !== false) {
                        $db_book_name = $possible_book;
                        break;
                    }
                }
            }

            if ($db_book_name && $chapter_num > 0) {
                $parsed['book'] = $db_book_name;
                $parsed['chapter'] = $chapter_num;
                $parsed['verse'] = ($verse_num > 0) ? $verse_num : null;
                $parsed['is_reference'] = true;
            }
        }
        return $parsed;
    }
}
?>
