<?php
/**
 * إدارة SEO للكتاب المقدس - متوافق مع Rank Math & Yoast
 * 
 * @package MyBiblePlugin
 * @version 3.0.0
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
 * التحقق من أننا في صفحة كتاب مقدس أو تفسير
 */
function my_bible_is_bible_page()
{
    // 1. صفحة الكتاب المقدس الرئيسية
    if (is_page('bible')) {
        return true;
    }

    // 2. صفحة التفسير الرئيسية
    if (is_page('bible-commentary') || is_page('tafser')) {
        return true;
    }

    // 3. التحقق من URL يحتوي على /bible/ أو /bible-commentary/
    if (isset($_SERVER['REQUEST_URI'])) {
        if (strpos($_SERVER['REQUEST_URI'], '/bible/') !== false)
            return true;
        if (strpos($_SERVER['REQUEST_URI'], '/bible-commentary/') !== false)
            return true;
    }

    return false;
}

/**
 * هل نحن في صفحة تفسير؟
 */
function my_bible_is_commentary_page()
{
    if (is_page('bible-commentary') || is_page('tafser'))
        return true;
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/bible-commentary/') !== false)
        return true;
    if (get_query_var('c_source'))
        return true;
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

    // Commentary Variables
    $c_source = get_query_var('c_source');
    $c_book = get_query_var('c_book');
    $c_chapter = get_query_var('c_chapter');

    // Priority to Commentary if active
    if (!empty($c_book)) {
        if (function_exists('my_bible_convert_from_commentary_book_name')) {
            // Try to normalize book name for display
            // $c_book is usually a slug here, we might need to decode it
            $decoded_book = urldecode($c_book);
            $decoded_book = str_replace('-', ' ', $decoded_book);
        } else {
            $decoded_book = urldecode($c_book);
            $decoded_book = str_replace('-', ' ', $decoded_book);
        }

        return array(
            'type' => 'commentary',
            'book' => $decoded_book,
            'chapter' => $c_chapter,
            'source' => $c_source,
            'is_book' => !empty($c_book) && empty($c_chapter),
            'is_chapter' => !empty($c_book) && !empty($c_chapter),
            'is_verse' => false
        );
    }

    // Bible Variables
    if (!empty($book_name)) {
        $book_name = urldecode($book_name);
        $book_name = str_replace('-', ' ', $book_name);
    }

    return array(
        'type' => 'bible',
        'book' => $book_name,
        'chapter' => $chapter,
        'verse' => $verse,
        'is_book' => !empty($book_name) && empty($chapter),
        'is_chapter' => !empty($book_name) && !empty($chapter),
        'is_verse' => !empty($book_name) && !empty($chapter) && !empty($verse)
    );
}

// ═══════════════════════════════════════════════════════════════
// منطق إنشاء العناوين والأوصاف المشتركة
// ═══════════════════════════════════════════════════════════════

function my_bible_get_seo_title($details)
{
    $site_name = get_bloginfo('name');

    if ($details['type'] === 'commentary') {
        $source_name = $details['source'];
        // تحسين اسم المصدر
        if ($source_name == 'af')
            $source_name = 'القمص أنطونيوس فكري';
        if ($source_name == 'ty')
            $source_name = 'القمص تادرس يعقوب';
        if ($source_name == 'sm')
            $source_name = 'كنيسة مارمرقس';

        if ($details['is_chapter']) {
            return sprintf('تفسير %s الأصحاح %s - %s - %s', $details['book'], $details['chapter'], $source_name, $site_name);
        } elseif ($details['is_book']) {
            return sprintf('تفسير سفر %s - %s - %s', $details['book'], $source_name, $site_name);
        }
        return 'التفاسير - ' . $site_name;
    }

    // Bible Pages
    if ($details['is_verse']) {
        return sprintf('%s %s:%s - %s', $details['book'], $details['chapter'], $details['verse'], $site_name);
    } elseif ($details['is_chapter']) {
        return sprintf('%s الأصحاح %s - الكتاب المقدس - %s', $details['book'], $details['chapter'], $site_name);
    } elseif ($details['is_book']) {
        return sprintf('سفر %s - الكتاب المقدس - %s', $details['book'], $site_name);
    }

    return 'الكتاب المقدس - ' . $site_name;
}

function my_bible_get_seo_description($details)
{
    if ($details['type'] === 'commentary') {
        $source_name = $details['source'];
        if ($source_name == 'af')
            $source_name = 'للقمص أنطونيوس فكري';
        if ($source_name == 'ty')
            $source_name = 'للقمص تادرس يعقوب ملطي';
        if ($source_name == 'sm')
            $source_name = 'من تفسير كنيسة مارمرقس';

        if ($details['is_chapter']) {
            return sprintf('اقرأ تفسير سفر %s الأصحاح %s %s. شرح مفصل وآبائي للكتاب المقدس.', $details['book'], $details['chapter'], $source_name);
        }
        return sprintf('تصفح تفاسير سفر %s %s.', $details['book'], $source_name);
    }

    // Bible Pages logic (Fetching text logic remains similar but simplified here for generic use)
    if ($details['is_chapter']) {
        return sprintf('اقرأ %s الأصحاح %s كاملاً من الكتاب المقدس بالتشكيل.', $details['book'], $details['chapter']);
    } elseif ($details['is_book']) {
        return sprintf('سفر %s - اقرأ السفر كاملاً مقسماً إلى أصحاحات. الكتاب المقدس باللغة العربية.', $details['book']);
    }

    return 'الكتاب المقدس باللغة العربية مع التفاسير والبحث المتقدم.';
}

// ═══════════════════════════════════════════════════════════════
// التكامل مع Rank Math
// ═══════════════════════════════════════════════════════════════

if (my_bible_is_rankmath_active()) {

    // 1. Title
    add_filter('rank_math/frontend/title', function ($title) {
        if (!my_bible_is_bible_page())
            return $title;
        return my_bible_get_seo_title(my_bible_get_current_page_details());
    }, 99);

    // 2. Description
    add_filter('rank_math/frontend/description', function ($description) {
        if (!my_bible_is_bible_page())
            return $description;
        $details = my_bible_get_current_page_details();

        // محاولة جلب نص حقيقي للأصحاح في حالة الكتاب المقدس (كما كان سابقاً)
        if ($details['type'] === 'bible' && $details['is_chapter']) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'bible_verses';
            $verse_data = $wpdb->get_row($wpdb->prepare("SELECT text FROM $table_name WHERE book = %s AND chapter = %d AND verse = 1", $details['book'], $details['chapter']));
            if ($verse_data) {
                return sprintf('%s الأصحاح %s: "%s..."', $details['book'], $details['chapter'], mb_substr(strip_tags($verse_data->text), 0, 100));
            }
        }

        return my_bible_get_seo_description($details);
    }, 99);

    // 3. Canonical
    add_filter('rank_math/frontend/canonical', function ($canonical) {
        if (!my_bible_is_bible_page())
            return $canonical;
        return home_url(add_query_arg(array(), $_SERVER['REQUEST_URI']));
    }, 99);

    // 4. OpenGraph
    add_filter('rank_math/opengraph/facebook/title', function ($title) {
        if (!my_bible_is_bible_page())
            return $title;
        $details = my_bible_get_current_page_details();
        if ($details['type'] === 'commentary') {
            return sprintf('تفسير %s %s', $details['book'], $details['chapter']);
        }
        return sprintf('%s الأصحاح %s', $details['book'], $details['chapter']);
    }, 99);

    // 5. Schema.org (JSON-LD) - IMPROVED
    add_filter('rank_math/json_ld', function ($data, $jsonld) {
        if (!my_bible_is_bible_page())
            return $data;

        $details = my_bible_get_current_page_details();

        if ($details['type'] === 'bible') {
            // إضافة CreativeWork Schema للكتاب المقدس
            $entity = array(
                '@type' => 'CreativeWork',
                '@id' => home_url($details['book'] . '/' . $details['chapter'] . '/#chapter'),
                'name' => sprintf('%s الأصحاح %s', $details['book'], $details['chapter']),
                'inLanguage' => 'ar',
                'genre' => 'ReligiousText',
                'isPartOf' => array(
                    '@type' => 'Book',
                    'name' => 'الكتاب المقدس',
                    'url' => home_url('/bible/')
                )
            );
            $data['CreativeWork'] = $entity;
        } elseif ($details['type'] === 'commentary') {
            // إضافة Comment/Review Schema للتفاسير
            $entity = array(
                '@type' => 'Comment',
                '@id' => home_url($_SERVER['REQUEST_URI'] . '#commentary'),
                'about' => array(
                    '@type' => 'CreativeWork',
                    'name' => sprintf('%s الأصحاح %s', $details['book'], $details['chapter'])
                ),
                'author' => array(
                    '@type' => 'Person',
                    'name' => ($details['source'] == 'af' ? 'القمص أنطونيوس فكري' : ($details['source'] == 'ty' ? 'القمص تادرس يعقوب' : 'كنيسة مارمرقس'))
                )
            );
            $data['Comment'] = $entity;
        }

        // تحسين Breadcrumbs
        // ... (يمكن الاحتفاظ بمنطق Breadcrumbs الموجود وتحديثه)

        return $data;
    }, 99, 2);

    // 6. Robots
    add_filter('rank_math/frontend/robots', function ($robots) {
        if (!my_bible_is_bible_page())
            return $robots;
        return array('index' => 'index', 'follow' => 'follow', 'max-snippet' => 'max-snippet:-1', 'max-image-preview' => 'max-image-preview:large', 'max-video-preview' => 'max-video-preview:-1');
    }, 99);
}

// ═══════════════════════════════════════════════════════════════
// التكامل مع Yoast SEO (نفس المنطق)
// ═══════════════════════════════════════════════════════════════
if (my_bible_is_yoast_active()) {
    add_filter('wpseo_title', function ($title) {
        if (!my_bible_is_bible_page())
            return $title;
        return my_bible_get_seo_title(my_bible_get_current_page_details());
    }, 99);

    add_filter('wpseo_metadesc', function ($desc) {
        if (!my_bible_is_bible_page())
            return $desc;
        return my_bible_get_seo_description(my_bible_get_current_page_details());
    }, 99);
}
