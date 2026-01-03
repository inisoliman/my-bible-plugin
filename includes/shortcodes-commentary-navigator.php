<?php
// منع الوصول المباشر
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode: Bible Commentary Navigator
 * [bible_commentary_navigator]
 * 
 * عرض فهرس تفاعلي متعدد المستويات للتفاسير:
 * المستوى 1: المفسرين
 * المستوى 2: الأسفار (عند اختيار مفسر)
 * المستوى 3: الأصحاحات (عند اختيار سفر)
 */
function my_bible_commentary_navigator_shortcode($atts)
{
    global $wpdb;

    // Enqueue styles and scripts
    wp_enqueue_style('my-bible-styles', MY_BIBLE_PLUGIN_URL . 'assets/css/bible-styles.css', array(), MY_BIBLE_PLUGIN_VERSION);
    wp_enqueue_script('my-bible-commentary-navigator', MY_BIBLE_PLUGIN_URL . 'assets/js/bible-commentary-navigator.js', array('jquery'), MY_BIBLE_PLUGIN_VERSION, true);

    // المفسرين المتاحين
    $sources = array(
        'af' => array(
            'name' => 'القمص أنطونيوس فكري',
            'icon' => 'fa-user',
            'color' => '#2563eb'
        ),
        'ty' => array(
            'name' => 'القمص تادرس يعقوب ملطي',
            'icon' => 'fa-user',
            'color' => '#7c3aed'
        ),
        'sm' => array(
            'name' => 'كنيسة مارمرقس',
            'icon' => 'fa-church',
            'color' => '#059669'
        )
    );

    // Localize script
    wp_localize_script('my-bible-commentary-navigator', 'commentaryNavigator', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('commentary_navigator_nonce'),
        'loading' => __('جارٍ التحميل...', 'my-bible-plugin'),
        'error' => __('حدث خطأ أثناء تحميل البيانات.', 'my-bible-plugin'),
        'no_books' => __('لا توجد أسفار متاحة لهذا المفسر.', 'my-bible-plugin'),
        'no_chapters' => __('لا توجد أصحاحات متاحة لهذا السفر.', 'my-bible-plugin')
    ));

    ob_start();
    ?>
    <div class="bible-commentary-navigator bible-content-area">
        <h1 id="bible-main-page-title"><?php _e('فهرس التفاسير', 'my-bible-plugin'); ?></h1>

        <!-- المستوى 1: اختيار المفسر -->
        <div id="sources-level" class="navigator-level active">
            <h2 class="level-title">
                <i class="fas fa-users"></i>
                <?php _e('اختر المفسر', 'my-bible-plugin'); ?>
            </h2>
            <div class="sources-grid">
                <?php foreach ($sources as $source_id => $source_data): ?>
                    <div class="source-card" data-source="<?php echo esc_attr($source_id); ?>">
                        <div class="source-icon" style="background: <?php echo esc_attr($source_data['color']); ?>">
                            <i class="fas <?php echo esc_attr($source_data['icon']); ?>"></i>
                        </div>
                        <h3 class="source-name"><?php echo esc_html($source_data['name']); ?></h3>
                        <button class="source-button bible-control-button">
                            <i class="fas fa-arrow-left"></i>
                            <span><?php _e('عرض الأسفار', 'my-bible-plugin'); ?></span>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- المستوى 2: الأسفار -->
        <div id="books-level" class="navigator-level">
            <div class="level-header">
                <button class="back-button bible-control-button" data-back-to="sources">
                    <i class="fas fa-arrow-right"></i>
                    <span><?php _e('العودة للمفسرين', 'my-bible-plugin'); ?></span>
                </button>
                <h2 class="level-title">
                    <i class="fas fa-book"></i>
                    <span id="current-source-name"></span>
                </h2>
            </div>

            <!-- حقل البحث -->
            <div class="navigator-search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="books-search" class="navigator-search-input"
                    placeholder="<?php _e('ابحث عن سفر...', 'my-bible-plugin'); ?>">
            </div>

            <!-- أزرار الفلترة وعداد النتائج -->
            <div class="navigator-filter-box">
                <div class="filter-buttons">
                    <button class="filter-btn active" data-testament="all">
                        <i class="fas fa-bible"></i>
                        <span><?php _e('الكل', 'my-bible-plugin'); ?></span>
                    </button>
                    <button class="filter-btn" data-testament="old">
                        <i class="fas fa-scroll"></i>
                        <span><?php _e('العهد القديم', 'my-bible-plugin'); ?></span>
                    </button>
                    <button class="filter-btn" data-testament="new">
                        <i class="fas fa-cross"></i>
                        <span><?php _e('العهد الجديد', 'my-bible-plugin'); ?></span>
                    </button>
                </div>
                <div class="results-counter">
                    <i class="fas fa-book"></i>
                    <span id="books-count">0</span>
                    <span><?php _e('سفر', 'my-bible-plugin'); ?></span>
                </div>
            </div>

            <div id="books-container" class="books-grid">
                <div class="loading-message">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p><?php _e('جارٍ تحميل الأسفار...', 'my-bible-plugin'); ?></p>
                </div>
            </div>
        </div>

        <!-- المستوى 3: الأصحاحات -->
        <div id="chapters-level" class="navigator-level">
            <div class="level-header">
                <button class="back-button bible-control-button" data-back-to="books">
                    <i class="fas fa-arrow-right"></i>
                    <span><?php _e('العودة للأسفار', 'my-bible-plugin'); ?></span>
                </button>
                <h2 class="level-title">
                    <i class="fas fa-book-open"></i>
                    <span id="current-book-name"></span>
                </h2>
            </div>
            <div id="chapters-container" class="chapters-grid">
                <div class="loading-message">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p><?php _e('جارٍ تحميل الأصحاحات...', 'my-bible-plugin'); ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('bible_commentary_navigator', 'my_bible_commentary_navigator_shortcode');

/**
 * AJAX: Get books for a specific commentary source
 */
function my_bible_navigator_get_books()
{
    check_ajax_referer('commentary_navigator_nonce', 'nonce');

    global $wpdb;
    $source = isset($_POST['source']) ? sanitize_text_field($_POST['source']) : '';

    if (empty($source)) {
        wp_send_json_error(array('message' => 'Invalid source'));
        return;
    }

    $table = $wpdb->prefix . 'bible_commentaries';

    // Get distinct books for this source
    $books = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT book_name FROM $table WHERE source_id = %s ORDER BY id ASC",
        $source
    ));

    if (empty($books)) {
        wp_send_json_error(array('message' => 'No books found'));
        return;
    }

    $books_array = array();
    foreach ($books as $book) {
        $books_array[] = $book->book_name;
    }

    wp_send_json_success(array('books' => $books_array));
}
add_action('wp_ajax_get_commentary_books', 'my_bible_navigator_get_books');
add_action('wp_ajax_nopriv_get_commentary_books', 'my_bible_navigator_get_books');

/**
 * AJAX: Get chapters for a specific book and source
 */
function my_bible_navigator_get_chapters()
{
    check_ajax_referer('commentary_navigator_nonce', 'nonce');

    global $wpdb;
    $source = isset($_POST['source']) ? sanitize_text_field($_POST['source']) : '';
    $book = isset($_POST['book']) ? sanitize_text_field($_POST['book']) : '';

    if (empty($source) || empty($book)) {
        wp_send_json_error(array('message' => 'Invalid parameters'));
        return;
    }

    $table = $wpdb->prefix . 'bible_commentaries';

    // Get distinct chapters for this book and source
    $chapters = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT chapter FROM $table WHERE source_id = %s AND book_name = %s ORDER BY chapter ASC",
        $source,
        $book
    ));

    if (empty($chapters)) {
        wp_send_json_error(array('message' => 'No chapters found'));
        return;
    }

    $chapters_array = array();
    foreach ($chapters as $chapter) {
        $chapters_array[] = intval($chapter->chapter);
    }

    wp_send_json_success(array('chapters' => $chapters_array));
}
add_action('wp_ajax_get_commentary_chapters', 'my_bible_navigator_get_chapters');
add_action('wp_ajax_nopriv_get_commentary_chapters', 'my_bible_navigator_get_chapters');
