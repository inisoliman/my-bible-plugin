<?php
// منع الوصول المباشر
if (!defined('ABSPATH')) {
    exit;
}

function my_bible_register_rewrite_rules() {
    // قاعدة لصفحة القراءة الرئيسية
    add_rewrite_rule(
        '^bible_read/?$',
        'index.php?pagename=bible_read',
        'top'
    );

    // **قاعدة جديدة: لعرض قائمة الأصحاحات لسفر معين**
    // تأتي قبل قاعدة عرض الأصحاح المحدد
    add_rewrite_rule(
        '^bible/([^/]+)/?$', // يطابق /bible/اسم-السفر/
        'index.php?pagename=bible&book=$matches[1]&my_bible_view=chapters', // استخدم متغير استعلام جديد للتمييز
        'top'
    );

    // قاعدة لعرض آية محددة (تبقى كما هي)
    add_rewrite_rule(
        '^bible/([^/]+)/([0-9]+)/([0-9]+)/?$',
        'index.php?pagename=bible&book=$matches[1]&chapter=$matches[2]&verse=$matches[3]',
        'top'
    );

    // قاعدة لعرض أصحاح كامل (تبقى كما هي)
    add_rewrite_rule(
        '^bible/([^/]+)/([0-9]+)/?$',
        'index.php?pagename=bible&book=$matches[1]&chapter=$matches[2]',
        'top'
    );
    
    // قاعدة اختيارية لصفحة /bible/ الرئيسية (إذا كانت مختلفة عن bible_read)
    add_rewrite_rule(
        '^bible/?$',
        'index.php?pagename=bible', // أو bible_read
        'top'
    );
}
add_action('init', 'my_bible_register_rewrite_rules');

// إضافة متغير الاستعلام الجديد
function my_bible_register_query_vars($vars) {
    $vars[] = 'book';
    $vars[] = 'chapter';
    $vars[] = 'verse';
    $vars[] = 'my_bible_view'; // المتغير الجديد لعرض الأصحاحات
    return $vars;
}
add_filter('query_vars', 'my_bible_register_query_vars');

// تذكير: قم بزيارة الإعدادات > الروابط الدائمة لحفظ التغييرات وتحديث قواعد إعادة الكتابة.
?>
