<?php
// منع الوصول المباشر
if (!defined('ABSPATH')) {
    exit;
}

// لا تقم بتضمين helpers.php هنا، فهو مُضمن في الملف الرئيسي

function my_bible_custom_template_redirect($template) {
    global $wp_query, $wpdb; // ** التأكد من أن $wpdb عام هنا **

    if (is_page('bible_read')) { 
        return $template; 
    }

    $book_slug_from_query = get_query_var('book');
    $chapter_num_from_query = get_query_var('chapter');
    $verse_num_from_query = get_query_var('verse');
    $view_type = get_query_var('my_bible_view');

    // --- معالجة عرض قائمة الأصحاحات ---
    if (!empty($book_slug_from_query) && $view_type === 'chapters' && empty($chapter_num_from_query)) {
        if (!function_exists('my_bible_get_book_name_from_slug')) return $template; // تأكد أن الدالة موجودة

        $book_name_for_db = my_bible_get_book_name_from_slug($book_slug_from_query); 

        if (!$book_name_for_db) {
             add_filter('the_content', function($content) use ($book_slug_from_query) {
                return '<div class="bible-content-area"><p>' . sprintf(esc_html__('السفر "%s" غير موجود.', 'my-bible-plugin'), esc_html(rawurldecode($book_slug_from_query))) . '</p></div>';
            });
            return get_page_template();
        }

        // ** التأكد من أن $wpdb متاح هنا أيضاً **
        global $wpdb;
        $table_name = $wpdb->prefix . 'bible_verses';
        $chapters = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT chapter FROM $table_name WHERE book = %s ORDER BY chapter ASC",
            $book_name_for_db
        ));

        if (empty($chapters)) {
             add_filter('the_content', function($content) use ($book_name_for_db) {
                return '<div class="bible-content-area"><p>' . sprintf(esc_html__('لم يتم العثور على أصحاحات للسفر "%s".', 'my-bible-plugin'), esc_html($book_name_for_db)) . '</p></div>';
            });
            return get_page_template();
        }

        $page_title = sprintf(esc_html__('أصحاحات سفر %s', 'my-bible-plugin'), esc_html($book_name_for_db));
        add_filter('pre_get_document_title', function() use ($page_title) {
            return $page_title . ' - ' . get_bloginfo('name');
        }, 999);
        add_action('wp_head', function() use ($book_name_for_db) {
            echo '<meta name="description" content="' . sprintf(esc_attr__('تصفح أصحاحات سفر %s في الكتاب المقدس.', 'my-bible-plugin'), esc_html($book_name_for_db)) . '">';
        });

        add_filter('the_content', function($content) use ($chapters, $book_name_for_db, $book_slug_from_query, $page_title) {
            $page_content = '<div class="bible-chapters-index bible-content-area">';
            // ** استخدام ID موحد للعنوان الرئيسي **
            $page_content .= '<h1 id="bible-main-page-title">' . $page_title . '</h1>';
            $page_content .= '<ul class="bible-chapters-list">';
            foreach ($chapters as $chapter_num) {
                $chapter_url = esc_url(home_url('/bible/' . $book_slug_from_query . '/' . $chapter_num . '/')); 
                $page_content .= '<li><a href="' . $chapter_url . '">' . sprintf(esc_html__('الأصحاح %s', 'my-bible-plugin'), esc_html($chapter_num)) . '</a></li>';
            }
            $page_content .= '</ul>';
            
            $index_page_slug = 'bible-index'; 
            $index_page = get_page_by_path($index_page_slug); 
            if ($index_page) {
                 $index_page_url = get_permalink($index_page->ID);
                 $page_content .= '<div class="bible-back-to-index" style="margin-top:20px; text-align:center;"><a href="' . esc_url($index_page_url) . '" class="bible-control-button"><i class="fas fa-list-ul"></i> ' . esc_html__('العودة إلى فهرس الكتاب المقدس', 'my-bible-plugin') . '</a></div>';
            }
            $page_content .= '</div>'; 
            $page_content .= '<style>
                /* ... (الأنماط كما هي) ... */
            </style>';
            return $page_content;
        });
        return get_page_template(); 
    }
    // --- نهاية معالجة عرض قائمة الأصحاحات ---

    // --- معالجة عرض الأصحاح الكامل أو الآية المنفردة ---
    elseif (!empty($book_slug_from_query) && !empty($chapter_num_from_query)) {
        // ** التأكد من أن الدوال المساعدة موجودة قبل استدعائها **
        if (!function_exists('my_bible_get_book_name_from_slug') || 
            !function_exists('my_bible_create_book_slug') || 
            !function_exists('my_bible_get_controls_html')) {
            // يمكنك تسجيل خطأ هنا إذا أردت
            return $template; // ارجع القالب الافتراضي إذا كانت الدوال غير موجودة
        }

        $book_name_for_db = my_bible_get_book_name_from_slug($book_slug_from_query); 

        if (!$book_name_for_db) {
             add_filter('the_content', function($content) use ($book_slug_from_query) {
                return '<div class="bible-content-area"><p>' . sprintf(esc_html__('السفر "%s" غير موجود.', 'my-bible-plugin'), esc_html(rawurldecode($book_slug_from_query))) . '</p></div>';
            });
            return get_page_template();
        }
        // ** التأكد من أن $wpdb متاح هنا أيضاً **
        global $wpdb;
        $table_name = $wpdb->prefix . 'bible_verses';
        $page_main_title = esc_html($book_name_for_db . ' ' . $chapter_num_from_query); 

        if (!empty($verse_num_from_query)) { // عرض آية منفردة
            $verse_object = $wpdb->get_row($wpdb->prepare(
                "SELECT book, chapter, verse, text FROM $table_name WHERE book = %s AND chapter = %d AND verse = %d",
                $book_name_for_db, $chapter_num_from_query, $verse_num_from_query
            ));

            if ($verse_object) {
                $wp_query->is_single = true; $wp_query->is_page = false; $wp_query->is_home = false; $wp_query->is_archive = false;
                $page_main_title = esc_html($verse_object->book . ' ' . $verse_object->chapter . ':' . $verse_object->verse); 

                add_filter('pre_get_document_title', function() use ($page_main_title) {
                    return $page_main_title . ' - ' . get_bloginfo('name');
                }, 999);
                add_action('wp_head', function() use ($verse_object) {
                    echo '<meta name="description" content="' . esc_attr(wp_trim_words($verse_object->text, 30, '...')) . '">';
                });

                add_filter('the_content', function($content) use ($verse_object, $page_main_title) { 
                    $book_slug_for_url = my_bible_create_book_slug($verse_object->book); 
                    // ** إصلاح رابط "العودة إلى الأصحاح الكامل" **
                    $chapter_url = home_url('/bible/' . $book_slug_for_url . '/' . $verse_object->chapter . '/');

                    $verse_url = home_url('/bible/' . $book_slug_for_url . '/' . $verse_object->chapter . '/' . $verse_object->verse . '/');
                    
                    $page_content = '<div class="bible-single-verse-container bible-content-area">'; 
                    $page_content .= '<h1 id="bible-main-page-title">' . $page_main_title . '</h1>'; 
                    
                    $page_content .= my_bible_get_controls_html('single_verse', $verse_object, $page_main_title);

                    $page_content .= '<div class="verses-text-container">'; 
                    $page_content .= "<p class='verse-text' data-original-text='" . esc_attr($verse_object->text) . "' data-verse-url='" . esc_url($verse_url) . "'>";
                    $page_content .= "<span class='text-content'>" . esc_html($verse_object->text) . "</span></p>";
                    $page_content .= '</div>'; 
                    
                    $page_content .= '<div id="verse-image-container" style="margin-top: 20px;"></div>';

                    $page_content .= '<div class="chapter-navigation single-verse-nav">';
                    $page_content .= '<a href="' . esc_url($chapter_url) . '"><i class="fas fa-book-open"></i> ' . sprintf(esc_html__('العودة إلى الأصحاح الكامل (%s %s)', 'my-bible-plugin'), esc_html($verse_object->book), esc_html($verse_object->chapter)) . '</a>';
                    $page_content .= '</div></div>'; 
                    return $page_content;
                });
            } else { 
                 add_filter('the_content', function($content) use ($book_name_for_db, $chapter_num_from_query, $verse_num_from_query) {
                    return '<div class="bible-content-area"><p>' . sprintf(esc_html__('الآية %s %s:%s غير موجودة.', 'my-bible-plugin'), esc_html($book_name_for_db), esc_html($chapter_num_from_query), esc_html($verse_num_from_query)) . '</p></div>';
                });
            }
            return get_page_template(); 
        } else { // عرض أصحاح كامل
            $wp_query->is_page = true; $wp_query->is_single = false; $wp_query->is_home = false;
            
            add_filter('pre_get_document_title', function() use ($page_main_title) { 
                return $page_main_title . ' - ' . get_bloginfo('name');
            }, 999);

            // ** التأكد من أن $wpdb متاح هنا أيضاً **
            global $wpdb; 
            $table_name_local = $wpdb->prefix . 'bible_verses'; 
            $first_verse_text = $wpdb->get_var($wpdb->prepare("SELECT text FROM $table_name_local WHERE book = %s AND chapter = %d AND verse = 1", $book_name_for_db, $chapter_num_from_query));
            
            add_action('wp_head', function() use ($first_verse_text, $book_name_for_db, $chapter_num_from_query) {
                $meta_desc = $first_verse_text ? esc_attr(wp_trim_words($first_verse_text, 30, '...')) : sprintf(esc_html__('اقرأ %s الأصحاح %s.', 'my-bible-plugin'), esc_html($book_name_for_db), esc_html($chapter_num_from_query));
                echo '<meta name="description" content="' . $meta_desc . '">';
            });
            
            add_filter('the_content', function($content) use ($book_name_for_db, $chapter_num_from_query, $page_main_title) {
                // ** التأكد من أن $wpdb متاح هنا أيضاً داخل الـ closure **
                global $wpdb;
                $shortcode_output = '<div class="bible-chapter-container bible-content-area">'; 
                $shortcode_output .= '<h1 id="bible-main-page-title">' . $page_main_title . '</h1>';
                
                $current_testament_for_shortcode = $wpdb->get_var($wpdb->prepare("SELECT testament FROM " . $wpdb->prefix . "bible_verses WHERE book = %s LIMIT 1", $book_name_for_db));
                $testament_attr = $current_testament_for_shortcode ? ' testament="' . esc_attr($current_testament_for_shortcode) . '"' : '';

                $shortcode_output .= do_shortcode('[bible_content book="' . esc_attr($book_name_for_db) . '" chapter="' . esc_attr($chapter_num_from_query) . '"' . $testament_attr . ']');
                $shortcode_output .= '</div>';
                return $shortcode_output;
            });
            return get_page_template();
        }
    }
    // --- نهاية معالجة عرض الأصحاح الكامل أو الآية المنفردة ---

    return $template; 
}
add_filter('template_include', 'my_bible_custom_template_redirect', 99);

// فلاتر عنوان ووصف صفحة القراءة الرئيسية
function my_bible_read_page_title_filter($title) {
    if (get_query_var('book') && get_query_var('chapter')) {
        return $title;
    }
    return esc_html__('الكتاب المقدس - اختر السفر والأصحاح', 'my-bible-plugin') . ' - ' . get_bloginfo('name');
}
function my_bible_read_page_meta_description() {
    if (get_query_var('book') && get_query_var('chapter')) {
        return; 
    }
    echo '<meta name="description" content="' . esc_attr__('اقرأ الكتاب المقدس، اختر السفر والأصحاح، أو ابحث عن كلمة معينة.', 'my-bible-plugin') . '">';
}
?>
