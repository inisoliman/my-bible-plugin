<?php
// منع الوصول المباشر
if (!defined('ABSPATH')) {
    exit;
}

// [bible_content] - مع فلتر العهد
function my_bible_display_content_shortcode($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $options = get_option('my_bible_options');
    $default_testament_db_value = isset($options['default_testament_view_db']) ? $options['default_testament_view_db'] : 'all';

    $atts = shortcode_atts(array(
        'book'      => '',
        'chapter'   => '',
        'testament' => $default_testament_db_value
    ), $atts, 'bible_content');

    $selected_testament_db_value = sanitize_text_field($atts['testament']);
    $valid_db_testaments = $wpdb->get_col("SELECT DISTINCT testament FROM {$table_name} WHERE testament != ''");
    if (!in_array($selected_testament_db_value, array_merge(array('all'), $valid_db_testaments ? $valid_db_testaments : array() ) ) ) {
        $selected_testament_db_value = $default_testament_db_value;
    }

    $query_book_slug = get_query_var('book');
    $query_chapter = get_query_var('chapter') ? intval(get_query_var('chapter')) : 0;

    $initial_selected_book_name = '';
    $initial_selected_chapter_number = 0;
    $data_current_testament_attr = '';
    $dictionary_terms_for_chapter = array(); // Initialize dictionary terms array

    if (!empty($atts['book'])) {
        $initial_selected_book_name = $atts['book'];
        if (function_exists('my_bible_get_book_name_from_slug') && function_exists('my_bible_create_book_slug')) {
            $temp_book_name = my_bible_get_book_name_from_slug(my_bible_create_book_slug($initial_selected_book_name));
            if ($temp_book_name) $initial_selected_book_name = $temp_book_name;
        }
    } elseif (!empty($query_book_slug)) {
        if (function_exists('my_bible_get_book_name_from_slug')) {
            $initial_selected_book_name = my_bible_get_book_name_from_slug($query_book_slug);
        }
    }

    if (!empty($atts['chapter'])) {
        $initial_selected_chapter_number = intval($atts['chapter']);
    } elseif ($query_chapter > 0) {
        $initial_selected_chapter_number = $query_chapter;
    }

    if ($initial_selected_book_name && $initial_selected_chapter_number > 0) {
        $testament_of_initial_book = $wpdb->get_var($wpdb->prepare("SELECT testament FROM {$table_name} WHERE book = %s LIMIT 1", $initial_selected_book_name));
        if ($testament_of_initial_book && in_array($testament_of_initial_book, $valid_db_testaments ? $valid_db_testaments : array())) {
            if($selected_testament_db_value !== $testament_of_initial_book && $atts['testament'] === $default_testament_db_value){
                 $selected_testament_db_value = $testament_of_initial_book;
            }
            $data_current_testament_attr = ' data-current-testament="' . esc_attr($testament_of_initial_book) . '"';
        }

        // Fetch dictionary terms for the initially selected chapter
        $dict_table_name = $wpdb->prefix . 'bible_dictionary';
        $normalized_book_name_for_dict_query = '';
        if (function_exists('my_bible_sanitize_book_name')) {
            $normalized_book_name_for_dict_query = str_replace(' ', '', my_bible_sanitize_book_name($initial_selected_book_name));
        } else {
            $normalized_book_name_for_dict_query = str_replace(' ', '', $initial_selected_book_name);
            if (function_exists('my_bible_log_error')) my_bible_log_error("my_bible_sanitize_book_name function not found in shortcodes.php for dictionary query (bible_content).", 'dictionary_highlight');
        }

        if (!empty($normalized_book_name_for_dict_query)) {
            $dictionary_terms_for_chapter = $wpdb->get_results($wpdb->prepare(
                "SELECT word, meaning FROM %i WHERE book_name = %s AND chapter = %d",
                $dict_table_name,
                $normalized_book_name_for_dict_query,
                $initial_selected_chapter_number
            ));
            if ($wpdb->last_error) {
                if (function_exists('my_bible_log_error')) my_bible_log_error("DB error fetching dictionary terms for chapter (bible_content): " . $wpdb->last_error, 'dictionary_highlight');
                $dictionary_terms_for_chapter = array();
            }
        }
    }

    $books_for_dropdown = array();
    if (function_exists('my_bible_get_book_order_from_db')) {
        $books_for_dropdown = my_bible_get_book_order_from_db($selected_testament_db_value);
    }

    $output = '<div id="bible-container" class="bible-content-area">';
    // ... (rest of the dropdowns and controls HTML generation) ...
    $output .= '<div class="bible-selection-controls-wrapper">';
    $output .= '<div class="bible-selection-controls testament-filter-controls">';
    $output .= '<label for="bible-testament-select">' . esc_html__('اختر العهد:', 'my-bible-plugin') . ' </label>';
    $output .= '<select id="bible-testament-select" name="selected_testament" class="bible-select">';
    $testaments_options_for_select = array('all' => __('الكل', 'my-bible-plugin'));
    if($valid_db_testaments){
        foreach($valid_db_testaments as $db_test_val){
            $testaments_options_for_select[$db_test_val] = $db_test_val;
        }
    }
    foreach ($testaments_options_for_select as $value => $label) {
        $output .= "<option value='" . esc_attr($value) . "' " . selected($selected_testament_db_value, $value, false) . ">" . esc_html($label) . "</option>";
    }
    $output .= '</select></div>';

    $output .= '<div class="bible-selection-controls book-chapter-controls">';
    $output .= '<select id="bible-book-select" name="selected_book" class="bible-select" data-initial-book="' . esc_attr($initial_selected_book_name) . '"' . $data_current_testament_attr . '>';
    $output .= '<option value="">' . esc_html__('اختر السفر', 'my-bible-plugin') . '</option>';
    if (!empty($books_for_dropdown)) {
        foreach ($books_for_dropdown as $book_item) {
            $is_selected_book = ($book_item === $initial_selected_book_name);
            $output .= "<option value='" . esc_attr($book_item) . "' " . ($is_selected_book ? 'selected' : '') . ">" . esc_html($book_item) . "</option>";
        }
    } else {
        $output .= '<option value="" disabled>' . esc_html__('لا توجد أسفار لهذا العهد', 'my-bible-plugin') . '</option>';
    }
    $output .= '</select>';
    $output .= ' <select id="bible-chapter-select" name="selected_chapter" class="bible-select" data-initial-chapter="' . esc_attr($initial_selected_chapter_number) . '" ' . (empty($initial_selected_book_name) || empty($books_for_dropdown) ? 'disabled' : '') . '>';
    $output .= '<option value="">' . esc_html__('اختر الأصحاح', 'my-bible-plugin') . '</option>';
    if (!empty($initial_selected_book_name) && !empty($books_for_dropdown) && in_array($initial_selected_book_name, $books_for_dropdown)) {
        $chapters_for_selected_book = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT chapter FROM $table_name WHERE book = %s ORDER BY chapter ASC", $initial_selected_book_name));
        if ($chapters_for_selected_book) {
            foreach ($chapters_for_selected_book as $chapter_item) {
                $is_selected_chapter = (intval($chapter_item) === $initial_selected_chapter_number);
                $output .= "<option value='" . esc_attr($chapter_item) . "' " . ($is_selected_chapter ? 'selected' : '') . ">" . esc_html($chapter_item) . "</option>";
            }
        }
    }
    $output .= '</select></div>';
    $output .= '</div>'; // end bible-selection-controls-wrapper

    $output .= '<div id="bible-verses-display" class="bible-verses-content">';
    if (!empty($initial_selected_book_name) && $initial_selected_chapter_number > 0 && !empty($books_for_dropdown) && in_array($initial_selected_book_name, $books_for_dropdown)) {
        $verses_data = $wpdb->get_results($wpdb->prepare("SELECT book, chapter, verse, text FROM $table_name WHERE book = %s AND chapter = %d ORDER BY verse ASC", $initial_selected_book_name, $initial_selected_chapter_number));
        if (!empty($verses_data)) {
            if (function_exists('my_bible_get_controls_html')) {
                $output .= my_bible_get_controls_html('content');
            }
            $output .= '<div id="verses-content" class="verses-text-container">';
            $base_bible_page_uri_sc = trailingslashit(get_page_by_path('bible') ? get_page_uri(get_page_by_path('bible')->ID) : 'bible');
            foreach ($verses_data as $verse_obj) {
                $reference = esc_html($verse_obj->book . ' ' . $verse_obj->chapter . ':' . $verse_obj->verse);
                $book_slug_for_url = function_exists('my_bible_create_book_slug') ? my_bible_create_book_slug($verse_obj->book) : sanitize_title($verse_obj->book);
                $verse_url = esc_url(home_url($base_bible_page_uri_sc . $book_slug_for_url . "/{$verse_obj->chapter}/{$verse_obj->verse}/"));

                $modified_verse_text = $verse_obj->text;
                if (!empty($dictionary_terms_for_chapter)) {
                    foreach ($dictionary_terms_for_chapter as $dict_term) {
                        $term_word = $dict_term->word;
                        $term_meaning = $dict_term->meaning;
                        $pattern = '/\b(' . preg_quote($term_word, '/') . ')\b/ui';
                        $replacement = '<span class="dict-term" data-meaning="' . esc_attr($term_meaning) . '">$1</span>';
                        $modified_verse_text = preg_replace($pattern, $replacement, $modified_verse_text, 1);
                    }
                }

                $output .= "<p class='verse-text' data-original-text='" . esc_attr($verse_obj->text) . "' data-verse-url='" . esc_attr($verse_url) . "'>";
                $output .= "<a href='" . esc_url($verse_url) . "' class='verse-number ajax-nav-link-verse' data-book='".esc_attr($verse_obj->book)."' data-chapter='".esc_attr($verse_obj->chapter)."' data-verse='".esc_attr($verse_obj->verse)."'>" . esc_html($verse_obj->verse) . ".</a> ";
                $output .= "<span class='text-content'>" . wp_kses_post($modified_verse_text) . "</span> "; // Use wp_kses_post
                $output .= "<a href='" . esc_url($verse_url) . "' class='verse-reference-link ajax-nav-link-verse' data-book='".esc_attr($verse_obj->book)."' data-chapter='".esc_attr($verse_obj->chapter)."' data-verse='".esc_attr($verse_obj->verse)."'>[" . $reference . "]</a></p>";
            }
            $output .= '</div>';
            // ... (rest of the chapter navigation) ...
            $all_chapters_for_book = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT chapter FROM $table_name WHERE book = %s ORDER BY chapter ASC", $initial_selected_book_name));
            $current_chapter_idx = array_search($initial_selected_chapter_number, $all_chapters_for_book);
            $book_slug_for_nav = function_exists('my_bible_create_book_slug') ? my_bible_create_book_slug($initial_selected_book_name) : sanitize_title($initial_selected_book_name);
            $output .= '<div class="chapter-navigation">';
            if ($current_chapter_idx !== false && $current_chapter_idx > 0) {
                $prev_chapter = $all_chapters_for_book[$current_chapter_idx - 1];
                $prev_url = esc_url(home_url($base_bible_page_uri_sc . $book_slug_for_nav . "/{$prev_chapter}/"));
                $output .= '<a href="' . esc_url($prev_url) . '" class="prev-chapter-link ajax-nav-link" data-book="'.esc_attr($initial_selected_book_name).'" data-chapter="'.esc_attr($prev_chapter).'"><i class="fas fa-arrow-right"></i> ' . sprintf(esc_html__('الأصحاح السابق (%s)', 'my-bible-plugin'), $prev_chapter) . '</a>';
            }
            if ($current_chapter_idx !== false && $current_chapter_idx < (count($all_chapters_for_book) - 1)) {
                $next_chapter = $all_chapters_for_book[$current_chapter_idx + 1];
                $next_url = esc_url(home_url($base_bible_page_uri_sc . $book_slug_for_nav . "/{$next_chapter}/"));
                $output .= '<a href="' . esc_url($next_url) . '" class="next-chapter-link ajax-nav-link" data-book="'.esc_attr($initial_selected_book_name).'" data-chapter="'.esc_attr($next_chapter).'"><i class="fas fa-arrow-left"></i> ' . sprintf(esc_html__('الأصحاح التالي (%s)', 'my-bible-plugin'), $next_chapter) . '</a>';
            }
            $output .= '</div>';
        } else {
            $output .= '<p class="bible-select-prompt">' . esc_html__('لم يتم العثور على آيات لهذا الأصحاح.', 'my-bible-plugin') . '</p>';
        }
    } else {
        $output .= '<p class="bible-select-prompt">' . esc_html__('يرجى اختيار العهد ثم السفر ثم الأصحاح لعرض الآيات.', 'my-bible-plugin') . '</p>';
    }
    $output .= '</div></div>';
    return $output;
}
add_shortcode('bible_content', 'my_bible_display_content_shortcode');

function my_bible_search_form_shortcode($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $dict_table_name = $wpdb->prefix . 'bible_dictionary'; // Define dict table name
    $options = get_option('my_bible_options');
    $default_testament_search_db = isset($options['default_testament_view_db']) ? $options['default_testament_view_db'] : 'all';

    $output = '';
    $search_term_get = isset($_GET['bible_search']) ? sanitize_text_field(wp_unslash($_GET['bible_search'])) : '';
    $search_testament_filter_db = isset($_GET['search_testament']) ? sanitize_text_field($_GET['search_testament']) : $default_testament_search_db;

    // ... (form HTML generation as before) ...
    $db_testaments_for_search_select = $wpdb->get_col("SELECT DISTINCT testament FROM " . $wpdb->prefix . "bible_verses WHERE testament != '' ORDER BY testament ASC");
    $allowed_search_testaments = array_merge(array('all'), $db_testaments_for_search_select ? $db_testaments_for_search_select : array());
    if (!in_array($search_testament_filter_db, $allowed_search_testaments)) {
        $search_testament_filter_db = 'all';
    }

    $output .= '<form method="get" action="' . esc_url(get_permalink()) . '" class="bible-search-form">';
    $output .= '<div class="bible-search-controls">';
    $output .= '<label for="search-testament-select" class="screen-reader-text">' . esc_html__('تحديد العهد للبحث:', 'my-bible-plugin') . '</label>';
    $output .= '<select id="search-testament-select" name="search_testament" class="bible-select">';
    $testaments_options_search = array('all' => __('البحث في الكل', 'my-bible-plugin'));
    if($db_testaments_for_search_select){
        foreach($db_testaments_for_search_select as $db_test_val){
            $testaments_options_search[$db_test_val] = sprintf(esc_html__('البحث في %s', 'my-bible-plugin'), $db_test_val);
        }
    }
    foreach ($testaments_options_search as $value => $label) {
        $output .= "<option value='" . esc_attr($value) . "' " . selected($search_testament_filter_db, $value, false) . ">" . esc_html($label) . "</option>";
    }
    $output .= '</select>';
    $output .= '<input type="text" name="bible_search" placeholder="' . esc_attr__('ابحث بكلمة أو شاهد (مثال: يوحنا 3:16)', 'my-bible-plugin') . '" value="' . esc_attr($search_term_get) . '" class="bible-search-input">';
    $output .= '<button type="submit" class="bible-search-button"><i class="fas fa-search"></i> ' . esc_html__('بحث', 'my-bible-plugin') . '</button>';
    $output .= '</div>';
    $output .= '</form>';


    if (!empty($search_term_get)) {
        $output .= '<div class="bible-search-results bible-content-area">';
        $parsed_ref = function_exists('my_bible_parse_reference') ? my_bible_parse_reference($search_term_get) : array('is_reference' => false);
        $base_bible_page_uri_search = trailingslashit(get_page_by_path('bible') ? get_page_uri(get_page_by_path('bible')->ID) : 'bible');

        if ($parsed_ref['is_reference']) {
            $book_name = $parsed_ref['book']; // This is already normalized by my_bible_parse_reference
            $chapter_num = $parsed_ref['chapter'];
            $verse_num = $parsed_ref['verse'];

            // Normalize book name for dictionary query (remove spaces from the already canonical name from parser)
            $normalized_book_name_for_dict_query = str_replace(' ', '', $book_name);

            if ($verse_num) { // Single verse display
                $verse_object = $wpdb->get_row($wpdb->prepare( "SELECT book, chapter, verse, text FROM $table_name WHERE book = %s AND chapter = %d AND verse = %d", $book_name, $chapter_num, $verse_num ));
                if ($verse_object) {
                    $dictionary_terms_for_single_verse = array();
                    if (!empty($normalized_book_name_for_dict_query)) {
                        $dictionary_terms_for_single_verse = $wpdb->get_results($wpdb->prepare(
                            "SELECT word, meaning FROM %i WHERE book_name = %s AND chapter = %d AND verse = %d",
                            $dict_table_name, $normalized_book_name_for_dict_query, $verse_object->chapter, $verse_object->verse
                        ));
                        if ($wpdb->last_error) { if (function_exists('my_bible_log_error')) my_bible_log_error("DB error fetching dictionary terms for single verse (search ref): " . $wpdb->last_error, 'dictionary_highlight'); $dictionary_terms_for_single_verse = array(); }
                    }

                    $modified_verse_text = $verse_object->text;
                    if (!empty($dictionary_terms_for_single_verse)) {
                        foreach ($dictionary_terms_for_single_verse as $dict_term) {
                            $pattern = '/\b(' . preg_quote($dict_term->word, '/') . ')\b/ui';
                            $replacement = '<span class="dict-term" data-meaning="' . esc_attr($dict_term->meaning) . '">$1</span>';
                            $modified_verse_text = preg_replace($pattern, $replacement, $modified_verse_text, 1);
                        }
                    }

                    $reference_text = esc_html($verse_object->book . ' ' . $verse_object->chapter . ':' . $verse_object->verse);
                    $output .= '<h2>' . sprintf(esc_html__('عرض الشاهد: %s', 'my-bible-plugin'), $reference_text) . '</h2>';
                    if (function_exists('my_bible_get_controls_html')) {
                        $output .= my_bible_get_controls_html('single_verse', $verse_object, $reference_text);
                    }
                    $output .= '<div class="verse-text-container">';
                    $book_slug_for_url = function_exists('my_bible_create_book_slug') ? my_bible_create_book_slug($verse_object->book) : sanitize_title($verse_object->book);
                    $verse_url = esc_url(home_url($base_bible_page_uri_search . $book_slug_for_url . "/{$verse_object->chapter}/{$verse_object->verse}/"));
                    $output .= "<p class='verse-text' data-original-text='" . esc_attr($verse_object->text) . "' data-verse-url='" . esc_attr($verse_url) . "'>";
                    $output .= "<span class='text-content'>" . wp_kses_post($modified_verse_text) . "</span> "; // Use wp_kses_post
                    $output .= "<a href='" . esc_url($verse_url) . "' class='verse-reference-link'>[" . $reference_text . "]</a></p>";
                    $output .= '</div>';
                    $output .= '<div id="verse-image-container" style="margin-top: 20px;"></div>';
                } else {
                    $output .= '<p>' . sprintf(esc_html__('لم يتم العثور على الشاهد: %s', 'my-bible-plugin'), esc_html($search_term_get)) . '</p>';
                }
            } else { // Full chapter display
                $dictionary_terms_for_search_chapter = array();
                 if (!empty($normalized_book_name_for_dict_query)) {
                    $dictionary_terms_for_search_chapter = $wpdb->get_results($wpdb->prepare(
                        "SELECT word, meaning FROM %i WHERE book_name = %s AND chapter = %d",
                        $dict_table_name, $normalized_book_name_for_dict_query, $chapter_num
                    ));
                    if ($wpdb->last_error) { if (function_exists('my_bible_log_error')) my_bible_log_error("DB error fetching dictionary terms for chapter (search ref): " . $wpdb->last_error, 'dictionary_highlight'); $dictionary_terms_for_search_chapter = array(); }
                }

                $verses_in_chapter = $wpdb->get_results($wpdb->prepare( "SELECT book, chapter, verse, text FROM $table_name WHERE book = %s AND chapter = %d ORDER BY verse ASC", $book_name, $chapter_num ));
                if ($verses_in_chapter) {
                    $output .= '<h2>' . sprintf(esc_html__('عرض الأصحاح: %s %d', 'my-bible-plugin'), esc_html($book_name), $chapter_num) . '</h2>';
                    if (function_exists('my_bible_get_controls_html')) {
                        $output .= my_bible_get_controls_html('content');
                    }
                    $output .= '<div id="verses-content" class="verses-text-container">';
                    foreach ($verses_in_chapter as $verse_obj) {
                        $modified_verse_text = $verse_obj->text;
                        if (!empty($dictionary_terms_for_search_chapter)) {
                            foreach ($dictionary_terms_for_search_chapter as $dict_term) {
                                $pattern = '/\b(' . preg_quote($dict_term->word, '/') . ')\b/ui';
                                $replacement = '<span class="dict-term" data-meaning="' . esc_attr($dict_term->meaning) . '">$1</span>';
                                $modified_verse_text = preg_replace($pattern, $replacement, $modified_verse_text, 1);
                            }
                        }
                        $reference = esc_html($verse_obj->book . ' ' . $verse_obj->chapter . ':' . $verse_obj->verse);
                        $book_slug_for_url = function_exists('my_bible_create_book_slug') ? my_bible_create_book_slug($verse_obj->book) : sanitize_title($verse_obj->book);
                        $verse_url = esc_url(home_url($base_bible_page_uri_search . $book_slug_for_url . "/{$verse_obj->chapter}/{$verse_obj->verse}/"));
                        $output .= "<p class='verse-text' data-original-text='" . esc_attr($verse_obj->text) . "' data-verse-url='" . esc_attr($verse_url) . "'>";
                        $output .= "<a href='" . esc_url($verse_url) . "' class='verse-number'>" . esc_html($verse_obj->verse) . ".</a> ";
                        $output .= "<span class='text-content'>" . wp_kses_post($modified_verse_text) . "</span> "; // Use wp_kses_post
                        $output .= "<a href='" . esc_url($verse_url) . "' class='verse-reference-link'>[" . $reference . "]</a></p>";
                    }
                    $output .= '</div>';
                } else {
                    $output .= '<p>' . sprintf(esc_html__('لم يتم العثور على الأصحاح: %s %d', 'my-bible-plugin'), esc_html($book_name), $chapter_num) . '</p>';
                }
            }
        } else { // Keyword search
            $search_term_cleaned_for_highlight = function_exists('my_bible_sanitize_book_name') ? my_bible_sanitize_book_name($search_term_get) : $search_term_get; // For highlighting
            $search_term_for_query = $search_term_cleaned_for_highlight; // Use the same for query text matching

            $search_term_like = '%' . $wpdb->esc_like($search_term_for_query) . '%';
            // ... (rest of keyword search logic, where_clauses, pagination etc.)
            $where_clauses = array(); $prepare_args = array();
            $where_clauses[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(text, 'ً', ''), 'َ', ''), 'ُ', ''), 'ِ', ''), 'ْ', ''), 'ّ', ''), 'إ', 'ا'), 'أ', 'ا'), 'آ', 'ا') LIKE %s";
            $prepare_args[] = $search_term_like;
            if ($search_testament_filter_db !== 'all') {
                $where_clauses[] = "testament = %s";
                $prepare_args[] = $search_testament_filter_db;
            }
            $where_sql = implode(' AND ', $where_clauses);
            $total_results = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE $where_sql", $prepare_args));

            if ($total_results > 0) {
                // ... (pagination setup)
                $results_per_page = apply_filters('my_bible_search_results_per_page', 10);
                $current_page = isset($_GET['search_page']) ? max(1, intval($_GET['search_page'])) : 1;
                $offset = ($current_page - 1) * $results_per_page;
                $prepare_args_paged = $prepare_args;
                $prepare_args_paged[] = $results_per_page;
                $prepare_args_paged[] = $offset;
                $search_results = $wpdb->get_results($wpdb->prepare("SELECT book, chapter, verse, text FROM $table_name WHERE $where_sql ORDER BY book ASC, chapter ASC, verse ASC LIMIT %d OFFSET %d", $prepare_args_paged));

                $output .= '<h2>' . sprintf(esc_html__('نتائج البحث عن "%s" (%d نتيجة):', 'my-bible-plugin'), esc_html($search_term_get), $total_results) . '</h2>';
                if (function_exists('my_bible_get_controls_html')) {
                    $output .= my_bible_get_controls_html('search');
                }
                $output .= '<div class="verses-text-container search-results-container">';

                // Prepare dictionary terms for all unique book-chapter combinations in results to minimize DB calls
                $chapter_dictionary_terms_cache = array();
                foreach ($search_results as $result) {
                    $cache_key = $result->book . '-' . $result->chapter;
                    if (!isset($chapter_dictionary_terms_cache[$cache_key])) {
                        $normalized_book_name_for_dict_query_sr = str_replace(' ', '', function_exists('my_bible_sanitize_book_name') ? my_bible_sanitize_book_name($result->book) : $result->book);
                        if (!empty($normalized_book_name_for_dict_query_sr)) {
                             $chapter_dictionary_terms_cache[$cache_key] = $wpdb->get_results($wpdb->prepare(
                                "SELECT word, meaning FROM %i WHERE book_name = %s AND chapter = %d",
                                $dict_table_name, $normalized_book_name_for_dict_query_sr, $result->chapter
                            ));
                            if ($wpdb->last_error) { if (function_exists('my_bible_log_error')) my_bible_log_error("DB error fetching dictionary terms for search result chapter {$cache_key}: " . $wpdb->last_error, 'dictionary_highlight'); $chapter_dictionary_terms_cache[$cache_key] = array(); }
                        } else {
                            $chapter_dictionary_terms_cache[$cache_key] = array();
                        }
                    }
                }

                foreach ($search_results as $result) {
                    $modified_verse_text = $result->text;
                    $cache_key = $result->book . '-' . $result->chapter;
                    $current_verse_dict_terms = isset($chapter_dictionary_terms_cache[$cache_key]) ? $chapter_dictionary_terms_cache[$cache_key] : array();

                    if (!empty($current_verse_dict_terms)) {
                        foreach ($current_verse_dict_terms as $dict_term) {
                            // Optional: Add a verse check here if dictionary terms are verse-specific within the chapter results
                            // if ($dict_term->verse != $result->verse) continue;
                            $pattern = '/\b(' . preg_quote($dict_term->word, '/') . ')\b/ui';
                            $replacement = '<span class="dict-term" data-meaning="' . esc_attr($dict_term->meaning) . '">$1</span>';
                            $modified_verse_text = preg_replace($pattern, $replacement, $modified_verse_text, 1);
                        }
                    }

                    // Then apply search term highlighting
                    $highlighted_text = preg_replace('/(' . preg_quote($search_term_cleaned_for_highlight, '/') . ')/iu', '<mark class="search-highlight">$1</mark>', $modified_verse_text);

                    $reference = esc_html($result->book . ' ' . $result->chapter . ':' . $result->verse);
                    $book_slug_for_url = function_exists('my_bible_create_book_slug') ? my_bible_create_book_slug($result->book) : sanitize_title($result->book);
                    $verse_url = esc_url(home_url($base_bible_page_uri_search . $book_slug_for_url . "/{$result->chapter}/{$result->verse}/"));

                    $output .= "<div class='search-result-item verse-text' data-original-text='" . esc_attr($result->text) . "' data-verse-url='" . esc_attr($verse_url) . "'>";
                    $output .= "<p class='search-result-reference'><a href='" . esc_url($verse_url) . "'>" . $reference . "</a></p>";
                    $output .= "<p class='search-result-text'><span class='text-content'>" . wp_kses_post($highlighted_text) . "</span></p>"; // Use wp_kses_post
                    $output .= "</div>";
                }
                $output .= '</div>';
                // Pagination
                $total_pages = ceil($total_results / $results_per_page);
                if ($total_pages > 1) {
                    $pagination_args = array(
                        'base' => add_query_arg(array('bible_search' => urlencode($search_term_get), 'search_testament' => $search_testament_filter_db, 'search_page' => '%#%'), remove_query_arg('search_page', wp_specialchars_decode(get_permalink()))),
                        'format' => '', 'current' => $current_page, 'total' => $total_pages,
                        'prev_text' => __('&laquo; السابق', 'my-bible-plugin'), 'next_text' => __('التالي &raquo;', 'my-bible-plugin'),
                        'add_args'  => false,
                    );
                    $output .= '<div class="bible-pagination">' . paginate_links($pagination_args) . '</div>';
                }
            } else {
                $output .= '<p>' . sprintf(esc_html__('لم يتم العثور على نتائج للبحث عن "%s" ضمن العهد المحدد.', 'my-bible-plugin'), esc_html($search_term_get)) . '</p>';
            }
        }
        $output .= '</div>';
    }
    return $output;
}
add_shortcode('bible_search', 'my_bible_search_form_shortcode');

function my_bible_random_verse_shortcode($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $dict_table_name = $wpdb->prefix . 'bible_dictionary';
    $options = get_option('my_bible_options');
    $selected_book_for_random = isset($options['bible_random_book']) ? $options['bible_random_book'] : '';

    $query = "SELECT book, chapter, verse, text FROM $table_name";
    $prepare_args = array();
    if (!empty($selected_book_for_random)) {
        $query .= " WHERE book = %s";
        $prepare_args[] = $selected_book_for_random;
    }
    $query .= " ORDER BY RAND() LIMIT 1";

    if(!empty($prepare_args)){
        $verse_obj = $wpdb->get_row($wpdb->prepare($query, $prepare_args));
    } else {
        $verse_obj = $wpdb->get_row($query);
    }

    if ($verse_obj) {
        $dictionary_terms_for_single_verse = array();
        $normalized_book_name_for_dict_query = str_replace(' ', '', function_exists('my_bible_sanitize_book_name') ? my_bible_sanitize_book_name($verse_obj->book) : $verse_obj->book);
        if (!empty($normalized_book_name_for_dict_query)) {
            $dictionary_terms_for_single_verse = $wpdb->get_results($wpdb->prepare(
                "SELECT word, meaning FROM %i WHERE book_name = %s AND chapter = %d AND verse = %d",
                $dict_table_name, $normalized_book_name_for_dict_query, $verse_obj->chapter, $verse_obj->verse
            ));
            if ($wpdb->last_error) { if (function_exists('my_bible_log_error')) my_bible_log_error("DB error fetching dictionary terms for random verse: " . $wpdb->last_error, 'dictionary_highlight'); $dictionary_terms_for_single_verse = array(); }
        }

        $modified_verse_text = $verse_obj->text;
        if (!empty($dictionary_terms_for_single_verse)) {
            foreach ($dictionary_terms_for_single_verse as $dict_term) {
                $pattern = '/\b(' . preg_quote($dict_term->word, '/') . ')\b/ui';
                $replacement = '<span class="dict-term" data-meaning="' . esc_attr($dict_term->meaning) . '">$1</span>';
                $modified_verse_text = preg_replace($pattern, $replacement, $modified_verse_text, 1);
            }
        }

        $reference = esc_html($verse_obj->book . ' ' . $verse_obj->chapter . ':' . $verse_obj->verse);
        $book_slug_for_url = function_exists('my_bible_create_book_slug') ? my_bible_create_book_slug($verse_obj->book) : sanitize_title($verse_obj->book);
        $base_bible_page_uri_rand = trailingslashit(get_page_by_path('bible') ? get_page_uri(get_page_by_path('bible')->ID) : 'bible');
        $verse_url = esc_url(home_url($base_bible_page_uri_rand . $book_slug_for_url . "/{$verse_obj->chapter}/{$verse_obj->verse}/"));

        $html = "<div class='random-verse-widget bible-content-area'>";
        $html .= '<h4 style="text-align:center;">' . esc_html__('آية عشوائية', 'my-bible-plugin') . '</h4>';
        if (function_exists('my_bible_get_controls_html')) {
            $html .= my_bible_get_controls_html('random_verse', $verse_obj, $reference);
        }
        $html .= "<div class='verse-text-container'>";
        $html .= "<p class='verse-text random-verse' data-original-text='" . esc_attr($verse_obj->text) . "' data-verse-url='" . esc_attr($verse_url) . "'>";
        $html .= "<span class='text-content'>" . wp_kses_post($modified_verse_text) . "</span> "; // Use wp_kses_post
        $html .= "<a href='" . esc_url($verse_url) . "' class='verse-reference-link'>[" . $reference . "]</a></p>";
        $html .= "</div>";
        $html .= '<div id="verse-image-container" style="margin-top: 20px;"></div>';
        $html .= "</div>";
        return $html;
    }
    return '<p class="random-verse-widget">' . esc_html__('لم يتم العثور على آية عشوائية.', 'my-bible-plugin') . '</p>';
}
add_shortcode('random_verse', 'my_bible_random_verse_shortcode');

function my_bible_daily_verse_shortcode($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $dict_table_name = $wpdb->prefix . 'bible_dictionary';
    $options = get_option('my_bible_options');
    $selected_book_for_daily = isset($options['bible_random_book']) ? $options['bible_random_book'] : '';
    $current_date_key_suffix = !empty($selected_book_for_daily) ? '_' . sanitize_key($selected_book_for_daily) : '';
    $transient_key = 'daily_verse_' . date('Y-m-d') . $current_date_key_suffix;
    $verse_data_transient = get_transient($transient_key);
    $verse_object_for_controls = null;

    if (false === $verse_data_transient) {
        // ... (logic to fetch and set transient for daily verse as before) ...
        $query = "SELECT id, book, chapter, verse, text FROM $table_name";
        $prepare_args_daily = array();
        if (!empty($selected_book_for_daily)) {
             $query .= " WHERE book = %s";
             $prepare_args_daily[] = $selected_book_for_daily;
        }
        $query .= " ORDER BY RAND() LIMIT 1";
        if(!empty($prepare_args_daily)){
            $verse_obj_daily = $wpdb->get_row($wpdb->prepare($query, $prepare_args_daily));
        } else {
            $verse_obj_daily = $wpdb->get_row($query);
        }
        if ($verse_obj_daily) {
            $verse_data_transient = array( 'book' => $verse_obj_daily->book, 'chapter' => $verse_obj_daily->chapter, 'verse' => $verse_obj_daily->verse, 'text' => $verse_obj_daily->text );
            $verse_object_for_controls = $verse_obj_daily; // Use the actual object here
            set_transient($transient_key, $verse_data_transient, DAY_IN_SECONDS);
        } else {
            set_transient($transient_key, array('empty' => true), DAY_IN_SECONDS);
            return '<p class="daily-verse-widget">' . esc_html__('لم يتم تحديد آية اليوم بعد (لا توجد بيانات).', 'my-bible-plugin') . '</p>';
        }
    } elseif (isset($verse_data_transient['empty'])) {
         return '<p class="daily-verse-widget">' . esc_html__('لم يتم تحديد آية اليوم بعد (بيانات فارغة مخزنة).', 'my-bible-plugin') . '</p>';
    } else { // Construct object from transient data
        $verse_object_for_controls = new stdClass();
        $verse_object_for_controls->book = $verse_data_transient['book'];
        $verse_object_for_controls->chapter = $verse_data_transient['chapter'];
        $verse_object_for_controls->verse = $verse_data_transient['verse'];
        $verse_object_for_controls->text = $verse_data_transient['text'];
    }

    if ($verse_object_for_controls) {
        $dictionary_terms_for_single_verse = array();
        $normalized_book_name_for_dict_query = str_replace(' ', '', function_exists('my_bible_sanitize_book_name') ? my_bible_sanitize_book_name($verse_object_for_controls->book) : $verse_object_for_controls->book);
        if (!empty($normalized_book_name_for_dict_query)) {
            $dictionary_terms_for_single_verse = $wpdb->get_results($wpdb->prepare(
                "SELECT word, meaning FROM %i WHERE book_name = %s AND chapter = %d AND verse = %d",
                $dict_table_name, $normalized_book_name_for_dict_query, $verse_object_for_controls->chapter, $verse_object_for_controls->verse
            ));
            if ($wpdb->last_error) { if (function_exists('my_bible_log_error')) my_bible_log_error("DB error fetching dictionary terms for daily verse: " . $wpdb->last_error, 'dictionary_highlight'); $dictionary_terms_for_single_verse = array(); }
        }

        $modified_verse_text = $verse_object_for_controls->text;
        if (!empty($dictionary_terms_for_single_verse)) {
            foreach ($dictionary_terms_for_single_verse as $dict_term) {
                $pattern = '/\b(' . preg_quote($dict_term->word, '/') . ')\b/ui';
                $replacement = '<span class="dict-term" data-meaning="' . esc_attr($dict_term->meaning) . '">$1</span>';
                $modified_verse_text = preg_replace($pattern, $replacement, $modified_verse_text, 1);
            }
        }

        $reference = esc_html($verse_object_for_controls->book . ' ' . $verse_object_for_controls->chapter . ':' . $verse_object_for_controls->verse);
        $book_slug_for_url = function_exists('my_bible_create_book_slug') ? my_bible_create_book_slug($verse_object_for_controls->book) : sanitize_title($verse_object_for_controls->book);
        $base_bible_page_uri_daily = trailingslashit(get_page_by_path('bible') ? get_page_uri(get_page_by_path('bible')->ID) : 'bible');
        $verse_url = esc_url(home_url($base_bible_page_uri_daily . $book_slug_for_url . "/{$verse_object_for_controls->chapter}/{$verse_object_for_controls->verse}/"));

        $html = "<div class='daily-verse-widget bible-content-area'>";
        $html .= "<h4>" . esc_html__('آية اليوم', 'my-bible-plugin') . "</h4>";
        if (function_exists('my_bible_get_controls_html')) {
            // Pass the original $verse_object_for_controls which might be stdClass or original DB object
            $html .= my_bible_get_controls_html('daily_verse', $verse_object_for_controls, $reference);
        }
        $html .= "<div class='verse-text-container'>";
        $html .= "<p class='verse-text daily-verse' data-original-text='" . esc_attr($verse_object_for_controls->text) . "' data-verse-url='" . esc_attr($verse_url) . "'>";
        $html .= "<span class='text-content'>" . wp_kses_post($modified_verse_text) . "</span> "; // Use wp_kses_post
        $html .= "<a href='" . esc_url($verse_url) . "' class='verse-reference-link'>[" . $reference . "]</a></p>";
        $html .= "</div>";
        $html .= '<div id="verse-image-container" style="margin-top: 20px;"></div>';
        $html .= "</div>";
        return $html;
    }
    return '<p class="daily-verse-widget">' . esc_html__('لم يتم تحديد آية اليوم بعد.', 'my-bible-plugin') . '</p>';
}
add_shortcode('daily_verse', 'my_bible_daily_verse_shortcode');

function my_bible_index_shortcode($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $base_bible_page_uri_index = trailingslashit(get_page_by_path('bible') ? get_page_uri(get_page_by_path('bible')->ID) : 'bible');
    $ot_books_ordered = function_exists('my_bible_get_book_order_from_db') ? my_bible_get_book_order_from_db('العهد القديم') : array();
    $nt_books_ordered = function_exists('my_bible_get_book_order_from_db') ? my_bible_get_book_order_from_db('العهد الجديد') : array();
    $all_db_books_for_index = $wpdb->get_col("SELECT DISTINCT book FROM {$table_name} ORDER BY book ASC");
    $other_books = array();
    if($all_db_books_for_index){
        $ot_array = $ot_books_ordered ? $ot_books_ordered : array();
        $nt_array = $nt_books_ordered ? $nt_books_ordered : array();
        $other_books = array_diff($all_db_books_for_index, $ot_array, $nt_array);
        if($other_books) sort($other_books);
    }
    $output = '<div class="bible-index-container bible-content-area">';
    $output .= '<div class="testaments-main-wrapper">';
    if (!empty($ot_books_ordered)) {
        $output .= '<div class="bible-testament-section old-testament">';
        $output .= '<h2>' . esc_html__('العهد القديم', 'my-bible-plugin') . '</h2>';
        $output .= '<ul class="bible-books-list">';
        foreach ($ot_books_ordered as $book) {
            $book_slug = function_exists('my_bible_create_book_slug') ? my_bible_create_book_slug($book) : sanitize_title($book);
            $book_url = esc_url(home_url($base_bible_page_uri_index . $book_slug . '/'));
            $output .= '<li><a href="' . $book_url . '">' . esc_html($book) . '</a></li>';
        }
        $output .= '</ul></div>';
    }
    if (!empty($nt_books_ordered)) {
        $output .= '<div class="bible-testament-section new-testament">';
        $output .= '<h2>' . esc_html__('العهد الجديد', 'my-bible-plugin') . '</h2>';
        $output .= '<ul class="bible-books-list">';
        foreach ($nt_books_ordered as $book) {
            $book_slug = function_exists('my_bible_create_book_slug') ? my_bible_create_book_slug($book) : sanitize_title($book);
            $book_url = esc_url(home_url($base_bible_page_uri_index . $book_slug . '/'));
            $output .= '<li><a href="' . $book_url . '">' . esc_html($book) . '</a></li>';
        }
        $output .= '</ul></div>';
    }
    $output .= '</div>';
    if (!empty($other_books)) {
        $output .= '<div class="bible-testament-section other-books">';
        $output .= '<h2>' . esc_html__('أسفار إضافية / أخرى', 'my-bible-plugin') . '</h2>';
        $output .= '<ul class="bible-books-list">';
        foreach ($other_books as $book) {
            $book_slug = function_exists('my_bible_create_book_slug') ? my_bible_create_book_slug($book) : sanitize_title($book);
            $book_url = esc_url(home_url($base_bible_page_uri_index . $book_slug . '/'));
            $output .= '<li><a href="' . $book_url . '">' . esc_html($book) . '</a></li>';
        }
        $output .= '</ul></div>';
    }
    $output .= '</div>';
    return $output;
}
add_shortcode('bible_index', 'my_bible_index_shortcode');
?>
