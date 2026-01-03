<?php
/**
 * إدارة SEO للكتاب المقدس - متوافق مع Rank Math
 * 
 * هذا الملف يوفر التكامل الكامل مع Rank Math SEO Plugin
 * بدلاً من إضافة Meta Tags مباشرة التي تسبب تضارب
 * 
 * @package MyBiblePlugin
 * @version 2.5.0
 */

// منع الوصول المباشر
if (!defined('ABSPATH')) {
    exit;
}

/**
 * كشف وجود Rank Math
 */
function my_bible_is_rankmath_active()
{
    return class_exists('RankMath') || defined('RANK_MATH_VERSION');
}

/**
 * كشف وجود Yoast SEO
 */
function my_bible_is_yoast_active()
{
    return defined('WPSEO_VERSION');
}

/**
 * التحقق من أننا في صفحة كتاب مقدس
 */
function my_bible_is_bible_page()
{
    global $post;

    // التحقق من الصفحة الرئيسية للكتاب المقدس
    if (is_page('bible')) {
        return true;
    }

    // التحقق من URL يحتوي على /bible/
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/bible/') !== false) {
        return true;
    }

    return false;
}

/**
 * الحصول على تفاصيل الصفحة الحالية
 */
function my_bible_get_current_page_details()
{
    $book_name = get_query_var('book');
    $chapter = get_query_var('chapter');
    $verse = get_query_var('verse');

    // Fallback for rewrite rules using c_book/c_chapter (Bible Commentary pages)
    if (empty($book_name)) {
        $book_name = get_query_var('c_book');
    }
    if (empty($chapter)) {
        $chapter = get_query_var('c_chapter');
    }

    // Decode if needed (Commentary rewrite might provide url-encoded value)
    if (!empty($book_name)) {
        $book_name = urldecode($book_name);
        $book_name = str_replace('-', ' ', $book_name);
    }

    return array(
        'book' => $book_name,
        'chapter' => $chapter,
        'verse' => $verse,
        'is_book' => !empty($book_name),
        'is_chapter' => !empty($book_name) && !empty($chapter),
        'is_verse' => !empty($book_name) && !empty($chapter) && !empty($verse)
    );
}

// ═══════════════════════════════════════════════════════════════
// التكامل مع Rank Math
// ═══════════════════════════════════════════════════════════════

if (my_bible_is_rankmath_active()) {

    /**
     * تخصيص العنوان (Title) في Rank Math
     */
    add_filter('rank_math/frontend/title', function ($title) {
        if (!my_bible_is_bible_page()) {
            return $title;
        }

        $details = my_bible_get_current_page_details();
        $site_name = get_bloginfo('name');

        if ($details['is_verse']) {
            return sprintf(
                '%s %s:%s - %s',
                $details['book'],
                $details['chapter'],
                $details['verse'],
                $site_name
            );
        } elseif ($details['is_chapter']) {
            return sprintf(
                '%s الأصحاح %s - %s',
                $details['book'],
                $details['chapter'],
                $site_name
            );
        } elseif ($details['is_book']) {
            return sprintf(
                '%s - %s',
                $details['book'],
                $site_name
            );
        }

        return 'الكتاب المقدس - ' . $site_name;
    }, 99);

    /**
     * تخصيص الوصف (Description) في Rank Math
     */
    add_filter('rank_math/frontend/description', function ($description) {
        if (!my_bible_is_bible_page()) {
            return $description;
        }

        $details = my_bible_get_current_page_details();

        if ($details['is_chapter']) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'bible_verses';

            // الحصول على أول آية من الأصحاح
            $first_verse = $wpdb->get_row($wpdb->prepare(
                "SELECT text FROM $table_name 
                WHERE book = %s AND chapter = %d AND verse = 1 
                LIMIT 1",
                $details['book'],
                $details['chapter']
            ));

            // الحصول على عدد الآيات
            $verse_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name 
                WHERE book = %s AND chapter = %d",
                $details['book'],
                $details['chapter']
            ));

            if ($first_verse) {
                // تقصير النص إلى 100 حرف
                $text_preview = mb_substr(strip_tags($first_verse->text), 0, 100);

                return sprintf(
                    '%s الأصحاح %s (%d آية) - يبدأ بـ: "%s..."',
                    $details['book'],
                    $details['chapter'],
                    $verse_count,
                    $text_preview
                );
            }
        } elseif ($details['is_book']) {
            return sprintf(
                'اقرأ %s كاملاً من الكتاب المقدس مع جميع الأصحاحات والآيات',
                $details['book']
            );
        }

        return 'اقرأ الكتاب المقدس كاملاً باللغة العربية مع البحث والقاموس';
    }, 99);

    /**
     * تخصيص الـ Canonical URL
     */
    add_filter('rank_math/frontend/canonical', function ($canonical) {
        if (!my_bible_is_bible_page()) {
            return $canonical;
        }

        // إرجاع URL الحالي كـ canonical
        return home_url(add_query_arg(array(), $_SERVER['REQUEST_URI']));
    }, 99);

    /**
     * تخصيص Open Graph Title
     */
    add_filter('rank_math/opengraph/facebook/title', function ($title) {
        if (!my_bible_is_bible_page()) {
            return $title;
        }

        $details = my_bible_get_current_page_details();

        if ($details['is_chapter']) {
            return sprintf('%s الأصحاح %s', $details['book'], $details['chapter']);
        } elseif ($details['is_book']) {
            return $details['book'];
        }

        return 'الكتاب المقدس';
    }, 99);

    /**
     * تخصيص Open Graph Description
     */
    add_filter('rank_math/opengraph/facebook/description', function ($description) {
        // نفس منطق الوصف العادي
        return apply_filters('rank_math/frontend/description', $description);
    }, 99);

    /**
     * تخصيص Open Graph Image
     */
    add_filter('rank_math/opengraph/facebook/image', function ($image) {
        if (!my_bible_is_bible_page()) {
            return $image;
        }

        // يمكن إضافة صورة مخصصة للكتاب المقدس
        $custom_image = get_option('my_bible_og_image');
        if ($custom_image) {
            return $custom_image;
        }

        return $image;
    }, 99);

    /**
     * تخصيص Schema.org
     */
    add_filter('rank_math/json_ld', function ($data, $jsonld) {
        if (!my_bible_is_bible_page()) {
            return $data;
        }

        $details = my_bible_get_current_page_details();

        // تعديل Article Schema
        if (isset($data['Article'])) {
            if ($details['is_chapter']) {
                $data['Article']['headline'] = sprintf(
                    '%s الأصحاح %s',
                    $details['book'],
                    $details['chapter']
                );

                $data['Article']['articleSection'] = 'الكتاب المقدس';
                $data['Article']['genre'] = 'Religious Text';
            }
        }

        // إضافة Breadcrumb Schema
        $data['BreadcrumbList'] = array(
            '@type' => 'BreadcrumbList',
            'itemListElement' => array(
                array(
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'الرئيسية',
                    'item' => home_url()
                ),
                array(
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'الكتاب المقدس',
                    'item' => home_url('/bible/')
                )
            )
        );

        if ($details['is_book']) {
            $data['BreadcrumbList']['itemListElement'][] = array(
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $details['book'],
                'item' => home_url('/bible/' . urlencode($details['book']) . '/')
            );
        }

        if ($details['is_chapter']) {
            $data['BreadcrumbList']['itemListElement'][] = array(
                '@type' => 'ListItem',
                'position' => 4,
                'name' => 'الأصحاح ' . $details['chapter'],
                'item' => home_url('/bible/' . urlencode($details['book']) . '/' . $details['chapter'] . '/')
            );
        }

        return $data;
    }, 99, 2);

    /**
     * إضافة robots meta
     */
    add_filter('rank_math/frontend/robots', function ($robots) {
        if (!my_bible_is_bible_page()) {
            return $robots;
        }

        // السماح بالفهرسة لجميع صفحات الكتاب المقدس
        return array(
            'index' => 'index',
            'follow' => 'follow',
            'max-snippet' => 'max-snippet:-1',
            'max-image-preview' => 'max-image-preview:large',
            'max-video-preview' => 'max-video-preview:-1'
        );
    }, 99);
}

// ═══════════════════════════════════════════════════════════════
// التكامل مع Yoast SEO (اختياري)
// ═══════════════════════════════════════════════════════════════

if (my_bible_is_yoast_active()) {

    add_filter('wpseo_title', function ($title) {
        if (!my_bible_is_bible_page()) {
            return $title;
        }

        $details = my_bible_get_current_page_details();
        $site_name = get_bloginfo('name');

        if ($details['is_chapter']) {
            return sprintf(
                '%s الأصحاح %s - %s',
                $details['book'],
                $details['chapter'],
                $site_name
            );
        }

        return $title;
    }, 99);

    add_filter('wpseo_metadesc', function ($description) {
        if (!my_bible_is_bible_page()) {
            return $description;
        }

        // نفس منطق Rank Math
        return apply_filters('rank_math/frontend/description', $description);
    }, 99);
}

// ═══════════════════════════════════════════════════════════════
// تسجيل الأخطاء للتصحيح
// ═══════════════════════════════════════════════════════════════

add_action('wp_footer', function () {
    if (defined('WP_DEBUG') && WP_DEBUG && my_bible_is_bible_page()) {
        $details = my_bible_get_current_page_details();
        error_log('Bible Page Details: ' . print_r($details, true));
        error_log('Rank Math Active: ' . (my_bible_is_rankmath_active() ? 'Yes' : 'No'));
    }
});
