<?php
// منع الوصول المباشر
if (!defined('ABSPATH')) {
    exit;
}
// لا حاجة لتضمين helpers.php هنا، فهو مُضمن في الملف الرئيسي للإضافة

// جلب الأصحاحات لسفر معين
// Add this function for better error handling
function my_bible_ajax_error_response($message, $code = 400) {
    wp_send_json_error(array(
        'message' => esc_html($message),
        'code' => $code
    ), $code);
}

// Improve the existing functions with better validation
function my_bible_get_chapters_ajax() {
    // Verify nonce first
    if (!check_ajax_referer('bible_ajax_nonce', 'nonce', false)) {
        my_bible_ajax_error_response(__('Security check failed.', 'my-bible-plugin'), 403);
        return;
    }
    
    if (!isset($_POST['book']) || empty($_POST['book'])) {
        wp_send_json_error(array('message' => __('اسم السفر مطلوب.', 'my-bible-plugin')), 400);
        return;
    }
    $book_name_input = sanitize_text_field($_POST['book']);
    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $transient_key = 'bible_chapters_' . md5($book_name_input);
    $chapters = get_transient($transient_key);

    if (false === $chapters) {
        $chapters = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT chapter FROM $table_name WHERE book = %s ORDER BY chapter ASC",
            $book_name_input
        ));
        if ($wpdb->last_error) {
            wp_send_json_error(array('message' => __('حدث خطأ أثناء جلب الأصحاحات.', 'my-bible-plugin')), 500);
            return;
        }
        set_transient($transient_key, $chapters, HOUR_IN_SECONDS);
    }
    wp_send_json_success($chapters ? $chapters : array()); 
}
add_action('wp_ajax_bible_get_chapters', 'my_bible_get_chapters_ajax');
add_action('wp_ajax_nopriv_bible_get_chapters', 'my_bible_get_chapters_ajax');


// دالة AJAX لجلب الأسفار بناءً على فلتر العهد (يستخدم قيم DB)
function my_bible_get_books_by_testament_ajax() {
    check_ajax_referer('bible_ajax_nonce', 'nonce');
    
    $testament_value_from_js = isset($_POST['testament']) ? sanitize_text_field($_POST['testament']) : 'all';

    if (!function_exists('my_bible_get_book_order_from_db')) {
        wp_send_json_error(array('message' => __('الدالة المساعدة للأسفار غير موجودة (AJAX).', 'my-bible-plugin')), 500);
        return;
    }
    $books_for_testament = my_bible_get_book_order_from_db($testament_value_from_js);

    if (is_wp_error($books_for_testament)) { 
        wp_send_json_error(array('message' => $books_for_testament->get_error_message()), 500);
        return;
    }
    
    wp_send_json_success($books_for_testament ? $books_for_testament : array());
}
add_action('wp_ajax_bible_get_books_by_testament', 'my_bible_get_books_by_testament_ajax');
add_action('wp_ajax_nopriv_bible_get_books_by_testament', 'my_bible_get_books_by_testament_ajax');


// جلب الآيات لأصحاح معين
// Fix the bible_get_verses AJAX handler
function my_bible_get_verses_ajax() {
    check_ajax_referer('bible_ajax_nonce', 'nonce');
    if (!isset($_POST['book']) || empty($_POST['book']) || !isset($_POST['chapter']) || empty($_POST['chapter'])) {
        wp_send_json_error(array('message' => __('اسم السفر ورقم الأصحاح مطلوبان.', 'my-bible-plugin')), 400);
        return;
    }
    $book_name_input = sanitize_text_field($_POST['book']);
    $chapter_number = intval($_POST['chapter']); 

    if ($chapter_number <= 0) {
        wp_send_json_error(array('message' => __('رقم الأصحاح غير صالح.', 'my-bible-plugin')), 400);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_verses';
    $verses = $wpdb->get_results($wpdb->prepare(
        "SELECT book, chapter, verse, text FROM $table_name WHERE book = %s AND chapter = %d ORDER BY verse ASC",
        $book_name_input, $chapter_number
    ));

    if ($wpdb->last_error) {
        wp_send_json_error(array('message' => __('حدث خطأ أثناء جلب الآيات.', 'my-bible-plugin')), 500);
        return;
    }
    if (empty($verses)) {
        wp_send_json_error(array('message' => __('لم يتم العثور على آيات لهذا الأصحاح.', 'my-bible-plugin')), 404);
        return;
    }

    if (!function_exists('my_bible_get_controls_html') || !function_exists('my_bible_create_book_slug')) {
         wp_send_json_error(array('message' => __('الدوال المساعدة للعرض غير موجودة (AJAX).', 'my-bible-plugin')), 500);
        return;
    }

    $html_output = '';
    
    // Add controls
    if (function_exists('my_bible_get_controls_html')) {
        $html_output .= my_bible_get_controls_html('content');
    }
    
    // Add verses container
    $html_output .= '<div id="verses-content" class="verses-text-container">';
    foreach ($verses as $verse_obj) {
        $reference = esc_html($verse_obj->book . ' ' . $verse_obj->chapter . ':' . $verse_obj->verse);
        $book_slug_for_url = function_exists('my_bible_create_book_slug') ? my_bible_create_book_slug($verse_obj->book) : sanitize_title($verse_obj->book);
        $verse_url = esc_url(home_url("/bible/" . $book_slug_for_url . "/{$verse_obj->chapter}/{$verse_obj->verse}/"));
        $html_output .= "<p class='verse-text' data-original-text='" . esc_attr($verse_obj->text) . "' data-verse-url='" . esc_attr($verse_url) . "'>";
        $html_output .= "<a href='" . esc_url($verse_url) . "' class='verse-number'>" . esc_html($verse_obj->verse) . ".</a> ";
        $html_output .= "<span class='text-content'>" . esc_html($verse_obj->text) . "</span> ";
        $html_output .= "<a href='" . esc_url($verse_url) . "' class='verse-reference-link'>[" . $reference . "]</a></p>";
    }
    $html_output .= '</div>';
    
    // Add navigation buttons
    $all_chapters_for_book = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT chapter FROM $table_name WHERE book = %s ORDER BY chapter ASC", $book_name_input));
    $current_chapter_idx = array_search($chapter_number, $all_chapters_for_book);
    $book_slug_for_nav = function_exists('my_bible_create_book_slug') ? my_bible_create_book_slug($book_name_input) : sanitize_title($book_name_input);
    
    $html_output .= '<div class="chapter-navigation">';
    if ($current_chapter_idx !== false && $current_chapter_idx > 0) {
        $prev_chapter = $all_chapters_for_book[$current_chapter_idx - 1];
        $prev_url = esc_url(home_url("/bible/" . $book_slug_for_nav . "/{$prev_chapter}/"));
        $html_output .= '<a href="' . esc_url($prev_url) . '" class="prev-chapter-link"><i class="fas fa-arrow-right"></i> ' . sprintf(esc_html__('الأصحاح السابق (%s)', 'my-bible-plugin'), $prev_chapter) . '</a>';
    }
    if ($current_chapter_idx !== false && $current_chapter_idx < (count($all_chapters_for_book) - 1)) {
        $next_chapter = $all_chapters_for_book[$current_chapter_idx + 1];
        $next_url = esc_url(home_url("/bible/" . $book_slug_for_nav . "/{$next_chapter}/"));
        $html_output .= '<a href="' . esc_url($next_url) . '" class="next-chapter-link"><i class="fas fa-arrow-left"></i> ' . sprintf(esc_html__('الأصحاح التالي (%s)', 'my-bible-plugin'), $next_chapter) . '</a>';
    }
    $html_output .= '</div>';
    
    // Get first verse text for description
    $first_verse_text = !empty($verses) ? $verses[0]->text : '';
    
    $response_data = array(
        'html' => $html_output,
        'title' => esc_html($book_name_input . ' ' . $chapter_number), 
        'description' => esc_html(wp_trim_words($first_verse_text, 25, '...')),
        'book' => $book_name_input,
        'chapter' => $chapter_number 
    );
    wp_send_json_success($response_data);
}
add_action('wp_ajax_bible_get_verses', 'my_bible_get_verses_ajax');
add_action('wp_ajax_nopriv_bible_get_verses', 'my_bible_get_verses_ajax');
?>
