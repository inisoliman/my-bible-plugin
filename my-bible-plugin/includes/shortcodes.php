<?php
// منع الوصول المباشر
if (!defined('ABSPATH')) {
    exit;
}

// الدوال المساعدة مثل my_bible_get_controls_html(), my_bible_get_testaments_books(), 
// my_bible_get_book_order(), my_bible_create_book_slug() معرفة الآن في الملف الرئيسي للإضافة.

// [bible_content book="سفر التكوين" chapter="1"]
function my_bible_display_content_shortcode($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $atts = shortcode_atts(array('book' => '', 'chapter' => ''), $atts, 'bible_content');

    $query_book = get_query_var('book') ? rawurldecode(sanitize_text_field(get_query_var('book'))) : '';
    $query_chapter = get_query_var('chapter') ? intval(get_query_var('chapter')) : 0;
    
    $selected_book_name_raw = !empty($atts['book']) ? $atts['book'] : $query_book;
    $selected_chapter_number = !empty($atts['chapter']) ? intval($atts['chapter']) : $query_chapter;

    $selected_book_name_for_db = trim($selected_book_name_raw);
    if (!empty($selected_book_name_for_db)) {
        // إذا كان اسم السفر في الرابط يحتوي على واصلات، استبدلها بمسافات للمطابقة مع قاعدة البيانات
        $selected_book_name_for_db = str_replace('-', ' ', $selected_book_name_for_db);
        // محاولة إضافية لمطابقة الاسم إذا كان التنظيف مطلوبًا
        $temp_book_name = my_bible_get_book_name_from_slug(my_bible_create_book_slug($selected_book_name_for_db)); // استخدام slug ثم العودة للاسم
        if ($temp_book_name) {
            $selected_book_name_for_db = $temp_book_name;
        }
    }


    $books_for_dropdown_ordered = my_bible_get_book_order(); // الحصول على الترتيب الصحيح
    // جلب الأسفار الموجودة فعلياً في قاعدة البيانات بهذا الترتيب
    $books_for_dropdown = $wpdb->get_col("SELECT DISTINCT book FROM $table_name ORDER BY FIELD(book, " . implode(', ', array_map(function($book) { return "'".esc_sql($book)."'"; }, $books_for_dropdown_ordered)) . ")");


    if (empty($books_for_dropdown)) {
        return '<p>' . esc_html__('خطأ: لم يتم العثور على أي أسفار في قاعدة البيانات.', 'my-bible-plugin') . '</p>';
    }

    // Book Schema Wrapper part 1: Modify the main container
    // Condition to check if book and chapter are selected and verses will be displayed
    if (!empty($selected_book_name_for_db) && $selected_chapter_number > 0 && $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM $table_name WHERE book = %s AND chapter = %d", $selected_book_name_for_db, $selected_chapter_number)) > 0) {
        $output = '<div id="bible-container" class="bible-content-area" itemscope itemtype="http://schema.org/Book">';
        $output .= '<meta itemprop="name" content="' . esc_attr($selected_book_name_for_db) . '" />';
        $output .= '<meta itemprop="url" content="' . esc_url(home_url('/bible/' . my_bible_create_book_slug($selected_book_name_for_db) . '/')) . '" />';
    } else {
        $output = '<div id="bible-container" class="bible-content-area">';
    }
    $output .= '<div class="bible-selection-controls">';
    $output .= '<select id="bible-book-select" name="selected_book" class="bible-select">';
    $output .= '<option value="">' . esc_html__('اختر السفر', 'my-bible-plugin') . '</option>';
    foreach ($books_for_dropdown as $book_item) {
        $is_selected = ($book_item === $selected_book_name_for_db);
        $output .= "<option value='" . esc_attr($book_item) . "' " . ($is_selected ? 'selected' : '') . ">" . esc_html($book_item) . "</option>";
    }
    $output .= '</select>';
    $output .= ' <select id="bible-chapter-select" name="selected_chapter" class="bible-select" ' . (empty($selected_book_name_for_db) ? 'disabled' : '') . '>';
    $output .= '<option value="">' . esc_html__('اختر الأصحاح', 'my-bible-plugin') . '</option>';
    if (!empty($selected_book_name_for_db) && $selected_chapter_number > 0) {
        $chapters_for_selected_book = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT chapter FROM $table_name WHERE book = %s ORDER BY chapter ASC", $selected_book_name_for_db));
        foreach ($chapters_for_selected_book as $chapter_item) {
            $is_selected = (intval($chapter_item) === $selected_chapter_number);
            $output .= "<option value='" . esc_attr($chapter_item) . "' " . ($is_selected ? 'selected' : '') . ">" . esc_html($chapter_item) . "</option>";
        }
    }
    $output .= '</select></div>';

    $output .= '<div id="bible-verses-display" class="bible-verses-content">';
    if (!empty($selected_book_name_for_db) && $selected_chapter_number > 0) {
        $verses_data = $wpdb->get_results($wpdb->prepare("SELECT book, chapter, verse, text FROM $table_name WHERE book = %s AND chapter = %d ORDER BY verse ASC", $selected_book_name_for_db, $selected_chapter_number));
        if (!empty($verses_data)) {
            $output .= my_bible_get_controls_html('content'); // الدالة معرفة في الملف الرئيسي
            $output .= '<div id="verses-content" class="verses-text-container">';
            foreach ($verses_data as $verse_obj) {
                $reference = esc_html($verse_obj->book . ' ' . $verse_obj->chapter . ':' . $verse_obj->verse);
                $book_slug_for_url = my_bible_create_book_slug($verse_obj->book); 
                $verse_url = esc_url(home_url("/bible/" . $book_slug_for_url . "/{$verse_obj->chapter}/{$verse_obj->verse}/"));
                $output .= "<p class='verse-text' data-original-text='" . esc_attr($verse_obj->text) . "' data-verse-url='" . esc_attr($verse_url) . "'>";
                $output .= "<a href='" . esc_url($verse_url) . "' class='verse-number'>" . esc_html($verse_obj->verse) . ".</a> ";
                $output .= "<span class='text-content'>" . esc_html($verse_obj->text) . "</span> ";
                $output .= "<a href='" . esc_url($verse_url) . "' class='verse-reference-link'>[" . $reference . "]</a></p>";
            }
            $output .= '</div>';

            $all_chapters_for_book = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT chapter FROM $table_name WHERE book = %s ORDER BY chapter ASC", $selected_book_name_for_db));
            $current_chapter_idx = array_search($selected_chapter_number, $all_chapters_for_book);
            $book_slug_for_nav = my_bible_create_book_slug($selected_book_name_for_db);
            $output .= '<div class="chapter-navigation">';
            if ($current_chapter_idx !== false && $current_chapter_idx > 0) {
                $prev_chapter = $all_chapters_for_book[$current_chapter_idx - 1];
                $prev_url = esc_url(home_url("/bible/" . $book_slug_for_nav . "/{$prev_chapter}/"));
                $output .= '<a href="' . esc_url($prev_url) . '" class="prev-chapter-link"><i class="fas fa-arrow-right"></i> ' . sprintf(esc_html__('الأصحاح السابق (%s)', 'my-bible-plugin'), $prev_chapter) . '</a>';
            }
            if ($current_chapter_idx !== false && $current_chapter_idx < (count($all_chapters_for_book) - 1)) {
                $next_chapter = $all_chapters_for_book[$current_chapter_idx + 1];
                $next_url = esc_url(home_url("/bible/" . $book_slug_for_nav . "/{$next_chapter}/"));
                $output .= '<a href="' . esc_url($next_url) . '" class="next-chapter-link"><i class="fas fa-arrow-left"></i> ' . sprintf(esc_html__('الأصحاح التالي (%s)', 'my-bible-plugin'), $next_chapter) . '</a>';
            }
            $output .= '</div>';
        } else {
            $output .= '<p>' . esc_html__('لم يتم العثور على آيات لهذا الأصحاح.', 'my-bible-plugin') . '</p>';
        }
    } else {
        $output .= '<p class="bible-select-prompt">' . esc_html__('يرجى اختيار السفر ثم الأصحاح لعرض الآيات.', 'my-bible-plugin') . '</p>';
    }
    $output .= '</div></div>';
    return $output;
}
add_shortcode('bible_content', 'my_bible_display_content_shortcode');


// [bible_search]
function my_bible_search_form_shortcode($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $output = '';
    $search_term_get = isset($_GET['bible_search']) ? sanitize_text_field(wp_unslash($_GET['bible_search'])) : '';

    $output .= '<form method="get" action="' . esc_url(get_permalink()) . '" class="bible-search-form">';
    $output .= '<input type="text" name="bible_search" placeholder="' . esc_attr__('ابحث في الكتاب المقدس...', 'my-bible-plugin') . '" value="' . esc_attr($search_term_get) . '" class="bible-search-input">';
    $output .= '<button type="submit" class="bible-search-button"><i class="fas fa-search"></i> ' . esc_html__('بحث', 'my-bible-plugin') . '</button></form>';

    if (!empty($search_term_get)) {
        $search_term_cleaned = my_bible_sanitize_book_name($search_term_get); 
        $search_term_like = '%' . $wpdb->esc_like($search_term_cleaned) . '%';
        $is_book_search = false; $matched_book_name = '';
        $all_db_books = $wpdb->get_col("SELECT DISTINCT book FROM $table_name");
        foreach ($all_db_books as $db_book) {
            if (strtolower(my_bible_sanitize_book_name($db_book)) === strtolower($search_term_cleaned)) {
                $is_book_search = true; $matched_book_name = $db_book; break;
            }
        }
        $output .= '<div class="bible-search-results bible-content-area">'; // إضافة bible-content-area
        if ($is_book_search) {
            $verses_in_book = $wpdb->get_results($wpdb->prepare("SELECT book, chapter, verse, text FROM $table_name WHERE book = %s ORDER BY chapter ASC, verse ASC", $matched_book_name));
            if ($verses_in_book) {
                $output .= '<h2>' . sprintf(esc_html__('نتائج البحث عن السفر: %s', 'my-bible-plugin'), esc_html($matched_book_name)) . '</h2>';
                $output .= my_bible_get_controls_html('search');
                $output .= '<div class="verses-text-container search-results-container">';
                $current_chapter_display = 0;
                foreach ($verses_in_book as $verse_obj) {
                    if ($verse_obj->chapter != $current_chapter_display) {
                        if ($current_chapter_display != 0) $output .= '</div>';
                        $current_chapter_display = $verse_obj->chapter;
                        $output .= '<h3>' . sprintf(esc_html__('الأصحاح %s', 'my-bible-plugin'), esc_html($current_chapter_display)) . '</h3><div class="chapter-verses-group">';
                    }
                    $reference = esc_html($verse_obj->book . ' ' . $verse_obj->chapter . ':' . $verse_obj->verse);
                    $book_slug_for_url = my_bible_create_book_slug($verse_obj->book);
                    $verse_url = esc_url(home_url("/bible/" . $book_slug_for_url . "/{$verse_obj->chapter}/{$verse_obj->verse}/"));
                    $verse_text_display = esc_html($verse_obj->text);
                    $output .= "<p class='verse-text' data-original-text='" . esc_attr($verse_obj->text) . "' data-verse-url='" . esc_attr($verse_url) . "'>";
                    $output .= "<a href='" . esc_url($verse_url) . "' class='verse-number'>" . esc_html($verse_obj->verse) . ".</a> ";
                    $output .= "<span class='text-content'>" . $verse_text_display . "</span> ";
                    $output .= "<a href='" . esc_url($verse_url) . "' class='verse-reference-link'>[" . $reference . "]</a></p>";
                }
                if ($current_chapter_display != 0) $output .= '</div>';
                $output .= '</div>';
            } else { $output .= '<p>' . esc_html__('لم يتم العثور على آيات في هذا السفر.', 'my-bible-plugin') . '</p>'; }
        } else { 
            $results_per_page = 10;
            $current_page = isset($_GET['search_page']) ? max(1, intval($_GET['search_page'])) : 1;
            $offset = ($current_page - 1) * $results_per_page;
            $sql_text_search_condition = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(text, 'ً', ''), 'َ', ''), 'ُ', ''), 'ِ', ''), 'ْ', ''), 'ّ', ''), 'إ', 'ا'), 'أ', 'ا'), 'آ', 'ا')";
            $total_results = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE $sql_text_search_condition LIKE %s", $search_term_like));
            if ($total_results > 0) {
                $search_results = $wpdb->get_results($wpdb->prepare("SELECT book, chapter, verse, text FROM $table_name WHERE $sql_text_search_condition LIKE %s ORDER BY book ASC, chapter ASC, verse ASC LIMIT %d OFFSET %d", $search_term_like, $results_per_page, $offset));
                $output .= '<h2>' . sprintf(esc_html__('نتائج البحث عن "%s" (%d نتيجة):', 'my-bible-plugin'), esc_html($search_term_get), $total_results) . '</h2>';
                $output .= my_bible_get_controls_html('search');
                $output .= '<div class="verses-text-container search-results-container">';
                foreach ($search_results as $result) {
                    $reference = esc_html($result->book . ' ' . $result->chapter . ':' . $result->verse);
                    $book_slug_for_url = my_bible_create_book_slug($result->book);
                    $verse_url = esc_url(home_url("/bible/" . $book_slug_for_url . "/{$result->chapter}/{$result->verse}/"));
                    $verse_text_display = esc_html($result->text);
                    $verse_text_display = preg_replace('/(' . preg_quote($search_term_cleaned, '/') . ')/iu', '<mark class="search-highlight">$1</mark>', $verse_text_display);
                    $output .= "<p class='verse-text' data-original-text='" . esc_attr($result->text) . "' data-verse-url='" . esc_attr($verse_url) . "'>";
                    $output .= "<a href='" . esc_url($verse_url) . "' class='verse-number'>" . esc_html($result->verse) . ".</a> ";
                    $output .= "<span class='text-content'>" . $verse_text_display . "</span> ";
                    $output .= "<a href='" . esc_url($verse_url) . "' class='verse-reference-link'>[" . $reference . "]</a></p>";
                }
                $output .= '</div>';
                $total_pages = ceil($total_results / $results_per_page);
                if ($total_pages > 1) {
                    $output .= '<div class="bible-pagination">' . paginate_links(array('base' => add_query_arg('search_page', '%#%'), 'format' => '?search_page=%#%', 'current' => $current_page, 'total' => $total_pages, 'prev_text' => __('&laquo; السابق', 'my-bible-plugin'), 'next_text' => __('التالي &raquo;', 'my-bible-plugin'))) . '</div>';
                }
            } else { $output .= '<p>' . sprintf(esc_html__('لم يتم العثور على نتائج للبحث عن "%s".', 'my-bible-plugin'), esc_html($search_term_get)) . '</p>'; }
        }
        $output .= '</div>';
    }
    return $output;
}
add_shortcode('bible_search', 'my_bible_search_form_shortcode');


// [random_verse]
function my_bible_random_verse_shortcode($atts) {
    global $wpdb; $table_name = $wpdb->prefix . 'bible_verses';
    $options = get_option('my_bible_options');
    $selected_book_for_random = isset($options['bible_random_book']) ? $options['bible_random_book'] : '';
    $query = "SELECT book, chapter, verse, text FROM $table_name";
    if (!empty($selected_book_for_random)) { $query .= $wpdb->prepare(" WHERE book = %s", $selected_book_for_random); }
    $query .= " ORDER BY RAND() LIMIT 1";
    $verse_obj = $wpdb->get_row($query);
    if ($verse_obj) {
        $reference = esc_html($verse_obj->book . ' ' . $verse_obj->chapter . ':' . $verse_obj->verse);
        $book_slug_for_url = my_bible_create_book_slug($verse_obj->book);
        $verse_url = esc_url(home_url("/bible/" . $book_slug_for_url . "/{$verse_obj->chapter}/{$verse_obj->verse}/"));
        
        $html = "<div class='random-verse-widget bible-content-area'>";
        // $html .= my_bible_get_controls_html('widget_single_verse'); // يمكن إضافة نسخة مبسطة من الأزرار إذا أردت
        $html .= "<div class='verse-text-container'>"; 
        $html .= "<p class='verse-text random-verse' data-original-text='" . esc_attr($verse_obj->text) . "' data-verse-url='" . esc_attr($verse_url) . "'>";
        $html .= "<span class='text-content'>" . esc_html($verse_obj->text) . "</span> ";
        $html .= "<a href='" . esc_url($verse_url) . "' class='verse-reference-link'>[" . $reference . "]</a></p>";
        $html .= "</div></div>";
        return $html;
    }
    return '<p class="random-verse-widget">' . esc_html__('لم يتم العثور على آية عشوائية.', 'my-bible-plugin') . '</p>';
}
add_shortcode('random_verse', 'my_bible_random_verse_shortcode');

// [daily_verse]
function my_bible_daily_verse_shortcode($atts) {
    global $wpdb; $table_name = $wpdb->prefix . 'bible_verses';
    $options = get_option('my_bible_options');
    $selected_book_for_daily = isset($options['bible_random_book']) ? $options['bible_random_book'] : '';
    $current_date_key_suffix = !empty($selected_book_for_daily) ? '_' . sanitize_key($selected_book_for_daily) : '';
    $transient_key = 'daily_verse_' . date('Y-m-d') . $current_date_key_suffix;
    $verse_data = get_transient($transient_key);
    if (false === $verse_data) {
        $query = "SELECT book, chapter, verse, text FROM $table_name";
        if (!empty($selected_book_for_daily)) { $query .= $wpdb->prepare(" WHERE book = %s", $selected_book_for_daily); }
        $query .= " ORDER BY RAND() LIMIT 1";
        $verse_obj = $wpdb->get_row($query);
        if ($verse_obj) {
            $verse_data = array('text' => $verse_obj->text, 'book' => $verse_obj->book, 'chapter' => $verse_obj->chapter, 'verse' => $verse_obj->verse);
            set_transient($transient_key, $verse_data, DAY_IN_SECONDS);
        } else { set_transient($transient_key, array('empty' => true), DAY_IN_SECONDS); }
    }
    if ($verse_data && !isset($verse_data['empty'])) {
        $reference = esc_html($verse_data['book'] . ' ' . $verse_data['chapter'] . ':' . $verse_data['verse']);
        $book_slug_for_url = my_bible_create_book_slug($verse_data['book']);
        $verse_url = esc_url(home_url("/bible/" . $book_slug_for_url . "/{$verse_data['chapter']}/{$verse_data['verse']}/"));
        
        $html = "<div class='daily-verse-widget bible-content-area'>"; 
        $html .= "<h4>" . esc_html__('آية اليوم', 'my-bible-plugin') . "</h4>";
        // $html .= my_bible_get_controls_html('widget_single_verse');
        $html .= "<div class='verse-text-container'>";
        $html .= "<p class='verse-text daily-verse' data-original-text='" . esc_attr($verse_data['text']) . "' data-verse-url='" . esc_attr($verse_url) . "'>";
        $html .= "<span class='text-content'>" . esc_html($verse_data['text']) . "</span> ";
        $html .= "<a href='" . esc_url($verse_url) . "' class='verse-reference-link'>[" . $reference . "]</a></p>";
        $html .= "</div></div>";
        return $html;
    }
    return '<p class="daily-verse-widget">' . esc_html__('لم يتم تحديد آية اليوم بعد.', 'my-bible-plugin') . '</p>';
}
add_shortcode('daily_verse', 'my_bible_daily_verse_shortcode');


// --- الشورتكود الجديد: [bible_index] ---
function my_bible_index_shortcode($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';

    // الحصول على ترتيب الأسفار الصحيح من الدالة المركزية
    $ordered_books_list = my_bible_get_book_order();
    if (empty($ordered_books_list)) {
         return '<p>' . esc_html__('قائمة ترتيب الأسفار غير محددة.', 'my-bible-plugin') . '</p>';
    }

    // جلب الأسفار الموجودة في قاعدة البيانات فقط، مرتبة حسب القائمة المحددة
    $db_books_query_order_clause = "ORDER BY FIELD(book, " . implode(', ', array_map(function($book) { return "'".esc_sql($book)."'"; }, $ordered_books_list)) . ")";
    $db_books = $wpdb->get_col("SELECT DISTINCT book FROM $table_name $db_books_query_order_clause");

    if (empty($db_books)) {
        return '<p>' . esc_html__('لم يتم العثور على أسفار في قاعدة البيانات.', 'my-bible-plugin') . '</p>';
    }

    $testaments_classification = my_bible_get_testaments_books(); // الدالة معرفة في الملف الرئيسي
    $classified_books = array('OT' => array(), 'NT' => array(), 'Other' => array());

    foreach ($db_books as $book_name) {
        if (in_array($book_name, $testaments_classification['OT'])) {
            $classified_books['OT'][] = $book_name;
        } elseif (in_array($book_name, $testaments_classification['NT'])) {
            $classified_books['NT'][] = $book_name;
        } else {
            $classified_books['Other'][] = $book_name; // للأسفار غير المصنفة
        }
    }

    $output = '<div class="bible-index-container">';

    if (!empty($classified_books['OT'])) {
        $output .= '<div class="bible-testament-section old-testament">';
        $output .= '<h2>' . esc_html__('العهد القديم', 'my-bible-plugin') . '</h2>';
        $output .= '<ul class="bible-books-list">';
        foreach ($classified_books['OT'] as $book) {
            $book_slug = my_bible_create_book_slug($book);
            $book_url = esc_url(home_url('/bible/' . $book_slug . '/')); 
            $output .= '<li><a href="' . $book_url . '">' . esc_html($book) . '</a></li>';
        }
        $output .= '</ul></div>';
    }

    if (!empty($classified_books['NT'])) {
        $output .= '<div class="bible-testament-section new-testament">';
        $output .= '<h2>' . esc_html__('العهد الجديد', 'my-bible-plugin') . '</h2>';
        $output .= '<ul class="bible-books-list">';
        foreach ($classified_books['NT'] as $book) {
            $book_slug = my_bible_create_book_slug($book);
            $book_url = esc_url(home_url('/bible/' . $book_slug . '/'));
            $output .= '<li><a href="' . $book_url . '">' . esc_html($book) . '</a></li>';
        }
        $output .= '</ul></div>';
    }
    
    if (!empty($classified_books['Other'])) { // عرض الأسفار غير المصنفة إذا وجدت
        $output .= '<div class="bible-testament-section other-books">';
        $output .= '<h2>' . esc_html__('أسفار أخرى', 'my-bible-plugin') . '</h2>';
        $output .= '<ul class="bible-books-list">';
        foreach ($classified_books['Other'] as $book) {
            $book_slug = my_bible_create_book_slug($book);
            $book_url = esc_url(home_url('/bible/' . $book_slug . '/'));
            $output .= '<li><a href="' . $book_url . '">' . esc_html($book) . '</a></li>';
        }
        $output .= '</ul></div>';
    }

    $output .= '</div>'; 

    // الأنماط المضمنة (يمكنك نقلها إلى ملف CSS الرئيسي للإضافة)
    $output .= '<style>
        .bible-index-container { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom:20px; }
        .bible-testament-section { flex: 1; min-width: 280px; border: 1px solid #e0e0e0; padding: 20px; border-radius: 8px; background-color:#fdfdfd; box-shadow: 0 2px 5px rgba(0,0,0,0.05);}
        .bible-testament-section h2 { margin-top: 0; border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin-bottom: 20px; font-size: 1.5em; color: #23282d;}
        .bible-books-list { list-style: none; padding: 0; margin: 0; column-count: 2; column-gap: 20px; }
        .bible-books-list li { margin-bottom: 10px; }
        .bible-books-list li a { text-decoration: none; color: #0073aa; font-size: 1.1em; padding: 5px 0; display:inline-block; transition: color 0.2s ease-in-out; }
        .bible-books-list li a:hover { color: #005177; text-decoration: underline; }
        body.dark-mode .bible-testament-section { background-color: #2a2a2a; border-color: #404040; box-shadow: 0 2px 5px rgba(255,255,255,0.05); }
        body.dark-mode .bible-testament-section h2 { color: #e0e0e0; border-color: #505050; }
        body.dark-mode .bible-books-list li a { color: #99ccff; }
        body.dark-mode .bible-books-list li a:hover { color: #cce6ff; }
        @media (max-width: 600px) { .bible-books-list { column-count: 1; } .bible-testament-section {min-width:100%;} }
    </style>';

    return $output;
}
add_shortcode('bible_index', 'my_bible_index_shortcode');
?>
