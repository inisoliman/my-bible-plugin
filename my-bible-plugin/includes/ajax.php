<?php
// منع الوصول المباشر
if (!defined('ABSPATH')) {
    exit;
}

// جلب الأصحاحات لسفر معين
function my_bible_get_chapters_ajax() {
    check_ajax_referer('bible_ajax_nonce', 'nonce');
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
    wp_send_json_success($chapters, 200); // لا نرسل خطأ إذا كانت المصفوفة فارغة، JS سيعالج ذلك
}
add_action('wp_ajax_bible_get_chapters', 'my_bible_get_chapters_ajax');
add_action('wp_ajax_nopriv_bible_get_chapters', 'my_bible_get_chapters_ajax');

// جلب الآيات لأصحاح معين
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

    // بناء HTML للأزرار بشكل موحد
    $controls_html = '<div class="bible-controls">';
    $controls_html .= '<button id="toggle-tashkeel" class="bible-control-button"><i class="fas fa-language"></i> <span class="label">' . esc_html__('إلغاء التشكيل', 'my-bible-plugin') . '</span></button>';
    $controls_html .= '<button id="increase-font" class="bible-control-button"><i class="fas fa-plus"></i> <span class="label">' . esc_html__('تكبير الخط', 'my-bible-plugin') . '</span></button>';
    $controls_html .= '<button id="decrease-font" class="bible-control-button"><i class="fas fa-minus"></i> <span class="label">' . esc_html__('تصغير الخط', 'my-bible-plugin') . '</span></button>';
    $controls_html .= '<button id="dark-mode-toggle" class="bible-control-button dark-mode-toggle-button"><i class="fas fa-moon"></i> <span class="label">' . esc_html__('الوضع الليلي', 'my-bible-plugin') . '</span></button>';
    $controls_html .= '<button id="read-aloud-button" class="bible-control-button read-aloud-button"><i class="fas fa-volume-up"></i> <span class="label">' . esc_html__('قراءة بصوت عالٍ', 'my-bible-plugin') . '</span></button>';
    $controls_html .= '</div>'; // bible-controls

    $output = $controls_html; // إضافة الأزرار أولاً

    $output .= '<div id="verses-content" class="verses-text-container">'; // تأكد من وجود هذا الـ ID
    $first_verse_text = '';
    foreach ($verses as $verse_obj) {
        $reference = esc_html($verse_obj->book . ' ' . $verse_obj->chapter . ':' . $verse_obj->verse);
        $book_slug_for_url = my_bible_create_book_slug($verse_obj->book);
        $verse_url = esc_url(home_url("/bible/" . $book_slug_for_url . "/{$verse_obj->chapter}/{$verse_obj->verse}/"));
        
        $output .= "<p class='verse-text' data-original-text='" . esc_attr($verse_obj->text) . "' data-verse-url='" . esc_attr($verse_url) . "'>";
        $output .= "<a href='" . esc_url($verse_url) . "' class='verse-number'>" . esc_html($verse_obj->verse) . ".</a> ";
        $output .= "<span class='text-content'>" . esc_html($verse_obj->text) . "</span> ";
        $output .= "<a href='" . esc_url($verse_url) . "' class='verse-reference-link'>[" . $reference . "]</a>";
        $output .= "</p>";
        if (empty($first_verse_text)) { $first_verse_text = $verse_obj->text; }
    }
    $output .= '</div>'; // verses-content

    // بناء روابط التنقل بين الأصحاحات (كما كانت)
    $all_chapters_for_book = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT chapter FROM $table_name WHERE book = %s ORDER BY chapter ASC", $book_name_input));
    $current_chapter_index = array_search($chapter_number, $all_chapters_for_book);
    $book_slug_for_nav = my_bible_create_book_slug($book_name_input);

    $output .= '<div class="chapter-navigation">';
    if ($current_chapter_index !== false && $current_chapter_index > 0) {
        $prev_chapter = $all_chapters_for_book[$current_chapter_index - 1];
        $prev_url = esc_url(home_url("/bible/" . $book_slug_for_nav . "/{$prev_chapter}/"));
        $output .= '<a href="' . esc_url($prev_url) . '" class="prev-chapter-link"><i class="fas fa-arrow-right"></i> ' . sprintf(esc_html__('الأصحاح السابق (%s)', 'my-bible-plugin'), $prev_chapter) . '</a>';
    }
    if ($current_chapter_index !== false && $current_chapter_index < (count($all_chapters_for_book) - 1)) {
        $next_chapter = $all_chapters_for_book[$current_chapter_index + 1];
        $next_url = esc_url(home_url("/bible/" . $book_slug_for_nav . "/{$next_chapter}/"));
        $output .= '<a href="' . esc_url($next_url) . '" class="next-chapter-link"><i class="fas fa-arrow-left"></i> ' . sprintf(esc_html__('الأصحاح التالي (%s)', 'my-bible-plugin'), $next_chapter) . '</a>';
    }
    $output .= '</div>'; // chapter-navigation

    $response_data = array(
        'html' => $output,
        'title' => esc_html($book_name_input . ' ' . $chapter_number) . ' - ' . get_bloginfo('name'),
        'description' => esc_html(wp_trim_words($first_verse_text, 25, '...')),
        'book' => $book_name_input,
        'chapter' => $chapter_number
    );
    wp_send_json_success($response_data, 200);
}
add_action('wp_ajax_bible_get_verses', 'my_bible_get_verses_ajax');
add_action('wp_ajax_nopriv_bible_get_verses', 'my_bible_get_verses_ajax');
?>
