<?php
// منع الوصول المباشر
if (!defined('ABSPATH')) {
    exit;
}

// --- الدوال المساعدة العامة ---

if (!function_exists('my_bible_get_controls_html')) {
    function my_bible_get_controls_html($context = 'content', $verse_object = null, $verse_reference_text = '') {
        $unique_id_suffix = '-' . uniqid();
        if ($context === 'content' || $context === 'search') {
            // For 'content' and 'search' (full chapter in search), use a consistent suffix or none if IDs should be static for these contexts
            // For simplicity, if these are unique major blocks, a static ID might be okay, or a context-based suffix.
            // Let's use a predictable suffix for these primary views if needed, or keep unique if multiple instances can occur.
            // The original logic used -search for search, and empty for content. Let's refine.
            if ($context === 'search' && $verse_object) { // Single verse in search result
                 $unique_id_suffix = '-sv-' . uniqid(); // Unique for single verse results
            } elseif ($context === 'search') { // Full chapter view in search
                 $unique_id_suffix = '-search-chap';
            } elseif ($context === 'content') {
                 $unique_id_suffix = '-content-chap';
            }
            // For random_verse, daily_verse, they are typically single instances on a page, so unique_id is fine.
        }


        $controls_html = '<div class="bible-controls-wrapper">';

        $controls_html .= '<div class="bible-main-controls">';
        $controls_html .= '<button id="toggle-tashkeel' . esc_attr($unique_id_suffix) . '" class="bible-control-button" data-action="toggle-tashkeel"><i class="fas fa-language"></i> <span class="label">' . esc_html__('إلغاء التشكيل', 'my-bible-plugin') . '</span></button>';
        $controls_html .= '<button id="increase-font' . esc_attr($unique_id_suffix) . '" class="bible-control-button" data-action="increase-font"><i class="fas fa-plus"></i> <span class="label">' . esc_html__('تكبير الخط', 'my-bible-plugin') . '</span></button>';
        $controls_html .= '<button id="decrease-font' . esc_attr($unique_id_suffix) . '" class="bible-control-button" data-action="decrease-font"><i class="fas fa-minus"></i> <span class="label">' . esc_html__('تصغير الخط', 'my-bible-plugin') . '</span></button>';

        // Use a consistent ID for the main dark mode toggle if it's unique per page load, or suffix if multiple control sets exist
        $dark_mode_button_id = 'dark-mode-toggle' . esc_attr($unique_id_suffix);
        // However, if there's one global dark mode button expected by JS, its ID should be static.
        // Assuming the JS targets '.dark-mode-toggle-button' class for generic behavior and specific IDs for specific instances if needed.
        // For now, keeping unique IDs for buttons to avoid conflicts if multiple shortcodes are on a page.
        $controls_html .= '<button id="' . esc_attr($dark_mode_button_id) . '" class="bible-control-button dark-mode-toggle-button" data-action="dark-mode-toggle"><i class="fas fa-moon"></i> <span class="label">' . esc_html__('الوضع الليلي', 'my-bible-plugin') . '</span></button>';
        $controls_html .= '<button id="read-aloud-button' . esc_attr($unique_id_suffix) . '" class="bible-control-button read-aloud-button" data-action="read-aloud"><i class="fas fa-volume-up"></i> <span class="label">' . esc_html__('قراءة بصوت عالٍ', 'my-bible-plugin') . '</span></button>';

        // Add "Show Meanings for Chapter" button for relevant contexts
        // $verse_object is null for full chapter display in search results
        if ($context === 'content' || ($context === 'search' && !$verse_object)) {
            $controls_html .= '<button id="show-chapter-meanings' . esc_attr($unique_id_suffix) . '" class="bible-control-button" data-action="show-chapter-meanings"><i class="fas fa-book-reader"></i> <span class="label">' . esc_html__('إظهار معاني كلمات الأصحاح', 'my-bible-plugin') . '</span></button>';
        }

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
    function my_bible_sanitize_book_name($book_name) {
        if (empty($book_name)) return '';
        $book_name = (string) $book_name;
        $book_name = trim($book_name);
        $book_name = str_replace('-', ' ', $book_name);
        // Remove Tashkeel (Arabic diacritics)
        $book_name = preg_replace('/[\x{0617}-\x{061A}\x{064B}-\x{065F}\x{06D6}-\x{06ED}]/u', '', $book_name);
        // Standardize common Arabic letters (Alef variants to simple Alef, Yaa variants to simple Yaa)
        $book_name = str_replace(array('أ', 'إ', 'آ', 'ٱ', 'أُ', 'إِ'), 'ا', $book_name);
        $book_name = str_replace(array('ى'), 'ي', $book_name);
        // Remove extra spaces
        $book_name = preg_replace('/\s+/', ' ', $book_name);
        return trim($book_name);
    }
}

if (!function_exists('my_bible_create_book_slug')) {
    function my_bible_create_book_slug($book_name) {
        if (empty($book_name)) return '';
        $slug = my_bible_sanitize_book_name($book_name); // Sanitize first
        $slug = str_replace(' ', '-', $slug); // Replace spaces with hyphens
        // Remove any characters not Arabic, alphanumeric, or hyphen.
        $slug = preg_replace('/[^\p{Arabic}\p{N}a-zA-Z0-9\-]+/u', '', $slug);
        return rawurlencode($slug); // URL encode the final slug
    }
}

// Function to get the canonical book order for OT and NT
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
                'رسالة بولس الرسول إلى أهل رومية', // Full name for Romans
                'رسالة بولس الرسول الأولى إلى أهل كورنثوس', 'رسالة بولس الرسول الثانية إلى أهل كورنثوس',
                'رسالة بولس الرسول إلى أهل غلاطية', 'رسالة بولس الرسول إلى أهل أفسس', 'رسالة بولس الرسول إلى أهل فيلبي', 'رسالة بولس الرسول إلى أهل كولوسي',
                'رسالة بولس الرسول الأولى إلى أهل تسالونيكي', 'رسالة بولس الرسول الثانية إلى أهل تسالونيكي',
                'رسالة بولس الرسول الأولى إلى تيموثاوس', 'رسالة بولس الرسول الثانية إلى تيموثاوس', 'رسالة بولس الرسول إلى تيطس', 'رسالة بولس الرسول إلى فليمون',
                'الرسالة إلى العبرانيين', 'رسالة يعقوب', 'رسالة بطرس الأولى', 'رسالة بطرس الثانية', 'رسالة يوحنا الأولى', 'رسالة يوحنا الثانية', 'رسالة يوحنا الثالثة', 'رسالة يهوذا',
                'سفر رؤيا يوحنا اللاهوتي'
            )
        );
    }
}


// Helper to get book order, respecting defined order first, then remaining DB books
if (!function_exists('my_bible_get_book_order_from_db')) {
    function my_bible_get_book_order_from_db($testament_value_in_db = 'all') {
        $cache_key = 'bible_book_order_' . md5(is_string($testament_value_in_db) ? $testament_value_in_db : 'serialized_array_or_obj'); // More robust cache key
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) { return $cached_result; }

        global $wpdb; $table_name = $wpdb->prefix . 'bible_verses'; $where_clause = ''; $prepare_args = array();

        if ($testament_value_in_db !== 'all' && !empty($testament_value_in_db)) {
            // Validate testament against DB values to prevent SQL injection if this value somehow comes from user input without sanitization prior.
            $valid_testaments = $wpdb->get_col("SELECT DISTINCT testament FROM {$table_name} WHERE testament != ''");
            if (!in_array($testament_value_in_db, $valid_testaments) && $testament_value_in_db !== 'all') { // also check 'all' for safety, though it means no filter
                set_transient($cache_key, array(), HOUR_IN_SECONDS); // Cache empty for invalid testament
                return array();
            }
            $where_clause = "WHERE testament = %s"; $prepare_args[] = $testament_value_in_db;
        }

        $defined_order_for_current_testament = array(); $order_by_clause_parts = array();
        $all_defined_orders = my_bible_get_defined_book_order_within_testaments();

        if ($testament_value_in_db !== 'all' && isset($all_defined_orders[$testament_value_in_db])) {
            $defined_order_for_current_testament = $all_defined_orders[$testament_value_in_db];
        } elseif ($testament_value_in_db === 'all') { // For 'all', merge OT and NT defined orders
            $ot_order = isset($all_defined_orders['العهد القديم']) ? $all_defined_orders['العهد القديم'] : array();
            $nt_order = isset($all_defined_orders['العهد الجديد']) ? $all_defined_orders['العهد الجديد'] : array();
            $defined_order_for_current_testament = array_merge($ot_order, $nt_order);
        }
        // Else, if specific testament not in defined_orders (e.g. "أسفار أخرى"), $defined_order_for_current_testament remains empty, will sort by name.

        if (!empty($defined_order_for_current_testament)) {
            // Get books actually in DB for the current testament filter
            $books_in_db_for_testament_query = "SELECT DISTINCT book FROM {$table_name} {$where_clause}";
            if (!empty($prepare_args)) { $books_in_db_for_testament_query = $wpdb->prepare($books_in_db_for_testament_query, $prepare_args); }
            $books_actually_in_db_for_testament = $wpdb->get_col($books_in_db_for_testament_query);

            if ($books_actually_in_db_for_testament) {
                // Filter defined order to only include books present in the DB for this testament
                $final_ordered_list_from_defined = array_values(array_intersect($defined_order_for_current_testament, $books_actually_in_db_for_testament));
                // Get any books from DB for this testament not in the defined order (e.g. deuterocanonical if not in primary lists)
                $remaining_books_in_db = array_diff($books_actually_in_db_for_testament, $final_ordered_list_from_defined);
                if ($remaining_books_in_db) { sort($remaining_books_in_db); $final_ordered_list_from_defined = array_merge($final_ordered_list_from_defined, $remaining_books_in_db); }

                if (!empty($final_ordered_list_from_defined)) {
                     $order_by_clause_parts[] = "FIELD(book, " . implode(', ', array_map(function($book) use ($wpdb) { return $wpdb->prepare("%s", $book); }, $final_ordered_list_from_defined)) . ")";
                }
            }
        }

        // Fallback or additional sort criteria
        $order_by_clause_parts[] = "book ASC"; // Ensures any books not in FIELD() are sorted alphabetically
        $order_by_sql = "ORDER BY " . implode(', ', $order_by_clause_parts);

        $sql = "SELECT DISTINCT book FROM {$table_name} {$where_clause} {$order_by_sql}";
        if(!empty($prepare_args) && !empty($where_clause)){ $sql = $wpdb->prepare($sql, $prepare_args); }

        $books = $wpdb->get_col($sql);

        if ($wpdb->last_error) {
            my_bible_log_error("DB Error in get_book_order_from_db: " . $wpdb->last_error . " SQL: " . $sql);
            set_transient($cache_key, array(), HOUR_IN_SECONDS); // Cache empty on error
            return array();
        }
        set_transient($cache_key, $books ? $books : array(), HOUR_IN_SECONDS);
        return $books ? $books : array();
    }
}


// Helper to get book name from slug (more robust)
if (!function_exists('my_bible_get_book_name_from_slug')) {
    function my_bible_get_book_name_from_slug($book_slug) {
        global $wpdb;
        if (empty($book_slug)) return false;

        $decoded_slug = rawurldecode($book_slug);
        $table_name = $wpdb->prefix . 'bible_verses';

        // Try direct match (slug might be the actual book name if it contains no special chars/spaces)
        $db_book_name = $wpdb->get_var($wpdb->prepare( "SELECT DISTINCT book FROM $table_name WHERE book = %s", $decoded_slug ));
        if ($db_book_name) return $db_book_name;

        // Try replacing hyphens with spaces (common slug format)
        $book_name_from_hyphenated_slug = str_replace('-', ' ', $decoded_slug);
        $db_book_name = $wpdb->get_var($wpdb->prepare( "SELECT DISTINCT book FROM $table_name WHERE book = %s", $book_name_from_hyphenated_slug ));
        if ($db_book_name) return $db_book_name;

        // Try matching against sanitized version (as created by my_bible_create_book_slug before urlencode)
        // This handles cases where the slug was created from a sanitized name
        $sanitized_input_name = my_bible_sanitize_book_name($book_name_from_hyphenated_slug); // Sanitize the space-replaced slug

        // Query by comparing sanitized book names (remove Alef variants, Yaa, Tashkeel, then spaces)
        // This query attempts to match the DB book name after it undergoes the same sanitization process as the slug would have
        $db_book_name_alt = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT DISTINCT book FROM $table_name WHERE
                REPLACE(
                    REPLACE(
                        REPLACE(
                            REPLACE(
                                REPLACE(
                                    REPLACE(
                                        REPLACE(
                                            REPLACE(
                                                REPLACE(
                                                    REPLACE(
                                                        REPLACE(
                                                            REPLACE(
                                                                REPLACE(book, 'أ', 'ا'),
                                                            'إ', 'ا'),
                                                        'آ', 'ا'),
                                                    'ٱ', 'ا'),
                                                'أُ', 'ا'),
                                            'إِ', 'ا'),
                                        'ى', 'ي'),
                                    'ً', ''),
                                'ٌ', ''),
                            'ٍ', ''),
                        'َ', ''),
                    'ُ', ''),
                'ِ', '') = %s LIMIT 1",
                $sanitized_input_name // Compare against the sanitized input
            )
        );
        if ($db_book_name_alt) return $db_book_name_alt;

        // Fallback: if no numbers in slug, try LIKE query (less precise)
        if (!preg_match('/\d/', $decoded_slug)) {
            $possible_books = $wpdb->get_results($wpdb->prepare( "SELECT DISTINCT book FROM $table_name WHERE book LIKE %s", '%' . $wpdb->esc_like($book_name_from_hyphenated_slug) . '%' ));
            if (count($possible_books) === 1) { return $possible_books[0]->book; }
        }
        if (function_exists('my_bible_log_error')) my_bible_log_error("Book slug not resolved: " . $book_slug, 'slug_resolution');
        return false; // No match found
    }
}


// Helper to parse reference string (e.g., "يوحنا 3:16" or "1كو 13")
if (!function_exists('my_bible_parse_reference')) {
    function my_bible_parse_reference($reference_string) {
        $reference_string = trim($reference_string);
        $parsed = array('book' => null, 'chapter' => null, 'verse' => null, 'is_reference' => false);

        // Regex: Catches book name (can have numbers like "1" or "2" and spaces), chapter, and optional verse
        // Allows for various separators like space, colon, period between chapter and verse.
        if (preg_match('/^([0-9]?\s*[^\d\s]+(?:\s+[^\d\s]+)*)\s*([0-9]+)(?:[\s:.]*\s*([0-9]+))?$/u', $reference_string, $matches)) {
            $book_name_input = trim($matches[1]);
            $chapter_num = intval($matches[2]);
            $verse_num = isset($matches[3]) && !empty($matches[3]) ? intval($matches[3]) : null;

            global $wpdb;
            $table_name = $wpdb->prefix . 'bible_verses';

            // Attempt 1: Direct match of book name input
            $db_book_name = $wpdb->get_var($wpdb->prepare("SELECT DISTINCT book FROM $table_name WHERE book = %s", $book_name_input));

            // Attempt 2: Match against sanitized book name (common for abbreviations like "تك" vs "سفر التكوين")
            if (!$db_book_name) {
                $sanitized_input_book = my_bible_sanitize_book_name($book_name_input);
                // This complex REPLACE sequence in SQL is to mimic parts of my_bible_sanitize_book_name on the DB side for comparison
                $db_book_name = $wpdb->get_var($wpdb->prepare(
                    "SELECT DISTINCT book FROM $table_name WHERE
                    REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                    REPLACE(REPLACE(REPLACE(REPLACE(book, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ٱ', 'ا'),
                    'أُ', 'ا'), 'إِ', 'ا'), 'ى', 'ي'),
                    'ً', ''), 'ٌ', ''), 'ٍ', ''),
                    'َ', ''), 'ُ', ''), 'ِ', '') = %s", $sanitized_input_book));
            }

            // Attempt 3: Handle cases like "1 مل" or "2 صم" where the number is part of the input
            if (!$db_book_name && preg_match('/^([0-9]+)\s+(.+)$/u', $book_name_input, $book_parts)) {
                $number_map_to_text = array('1' => 'الأول', '2' => 'الثاني', '3' => 'الثالث'); // Expand as needed
                $numeric_prefix_num_str = $book_parts[1];
                $book_base_name = trim($book_parts[2]);
                $textual_prefix = isset($number_map_to_text[$numeric_prefix_num_str]) ? $number_map_to_text[$numeric_prefix_num_str] : null;

                if($textual_prefix){
                    // Search for "الاسم اللفظي السفر" e.g. "صموئيل الأول" or "الملوك الثاني"
                    $possible_books_query = $wpdb->prepare(
                        "SELECT DISTINCT book FROM $table_name WHERE book LIKE %s AND book LIKE %s",
                        '%' . $wpdb->esc_like($book_base_name) . '%',
                        '%' . $wpdb->esc_like($textual_prefix) . '%'
                    );
                    $possible_books = $wpdb->get_col($possible_books_query);

                    if (count($possible_books) === 1) {
                        $db_book_name = $possible_books[0];
                    } elseif (count($possible_books) > 1) {
                        // Try to find a more exact match if multiple books share the base name
                        foreach ($possible_books as $possible_book) {
                            // Check for patterns like "سفر صموئيل الأول" or "صموئيل الأول"
                            if (strpos($possible_book, $book_base_name . ' ' . $textual_prefix) !== false ||
                                strpos($possible_book, $textual_prefix . ' ' . $book_base_name) !== false ) { // Less common but possible
                                $db_book_name = $possible_book;
                                break;
                            }
                        }
                    }
                }
            }

            // Final check: if a book name was found and chapter is positive
            if ($db_book_name && $chapter_num > 0) {
                $parsed['book'] = $db_book_name; // Use the name as stored in DB
                $parsed['chapter'] = $chapter_num;
                $parsed['verse'] = ($verse_num !== null && $verse_num > 0) ? $verse_num : null;
                $parsed['is_reference'] = true;
            }
        }
        return $parsed;
    }
}
?>
