<?php
// منع الوصول المباشر
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode to display Bible Commentaries
 * [bible_commentary]
 */
function my_bible_commentary_shortcode($atts)
{
    global $wpdb;

    // Enqueue scripts
    wp_enqueue_style('my-bible-commentary-css', MY_BIBLE_PLUGIN_URL . 'assets/css/bible-commentary.css', array(), MY_BIBLE_PLUGIN_VERSION);
    wp_enqueue_script('my-bible-commentary-js', MY_BIBLE_PLUGIN_URL . 'assets/js/bible-commentary.js', array('jquery'), MY_BIBLE_PLUGIN_VERSION, true);

    // Get Bible Page URL dynamically
    $bible_page = get_page_by_path('bible');
    $bible_url = $bible_page ? get_permalink($bible_page->ID) : home_url('/bible/');

    // Localize script
    wp_localize_script('my-bible-commentary-js', 'bibleCommentary', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bible_commentary_nonce'),
        'site_url' => site_url(),
        'bible_url' => $bible_url,
        'loading' => __('جارٍ التحميل...', 'my-bible-plugin'),
        'error' => __('حدث خطأ أثناء تحميل التفسير.', 'my-bible-plugin')
    ));

    $sources = array(
        'ty' => 'القمص تادرس يعقوب ملطي',
        'af' => 'القمص أنطونيوس فكري',
        'sm' => 'كنيسة مارمرقس مصر الجديدة'
    );

    // Get books available in commentaries
    // NOTE: Books are now loaded dynamically via AJAX based on selected source
    // This ensures only books with actual commentaries for each source are shown
    $books = array(); // Empty - will be loaded via AJAX

    // Check for URL parameters (both $_GET and rewrite query vars for SSR)
    $url_book = isset($_GET['book']) ? sanitize_text_field($_GET['book']) : get_query_var('c_book');
    $url_chapter = isset($_GET['chapter']) ? intval($_GET['chapter']) : intval(get_query_var('c_chapter'));
    $url_source = isset($_GET['source']) ? sanitize_text_field($_GET['source']) : get_query_var('c_source');

    // Decode and clean book name if from pretty URL
    if ($url_book) {
        $url_book = urldecode($url_book);
        $url_book = str_replace(['-', '_'], ' ', $url_book);
    }

    ob_start();
    ?>
    <div class="bible-commentary-wrapper" data-url-book="<?php echo esc_attr($url_book); ?>"
        data-url-chapter="<?php echo esc_attr($url_chapter); ?>" data-url-source="<?php echo esc_attr($url_source); ?>">
        <!-- Controls -->
        <div class="bible-commentary-controls">
            <div class="control-group">
                <label><?php _e('المفسر', 'my-bible-plugin'); ?></label>
                <select id="commentary-source">
                    <?php foreach ($sources as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="control-group">
                <label><?php _e('السفر', 'my-bible-plugin'); ?></label>
                <select id="commentary-book">
                    <option value=""><?php _e('اختر السفر', 'my-bible-plugin'); ?></option>
                    <!-- Books will be loaded dynamically via AJAX based on selected source -->
                </select>
            </div>

            <div class="control-group">
                <label><?php _e('الأصحاح', 'my-bible-plugin'); ?></label>
                <select id="commentary-chapter" disabled>
                    <option value=""><?php _e('اختر الأصحاح', 'my-bible-plugin'); ?></option>
                </select>
            </div>

            <button id="btn-load-commentary" class="button-primary"
                disabled><?php _e('عرض التفسير', 'my-bible-plugin'); ?></button>
        </div>

        <!-- Layout: Sidebar (TOC) + Content -->
        <div class="bible-commentary-layout">
            <div id="commentary-sidebar" class="commentary-sidebar">
                <h3><?php _e('الفهرس', 'my-bible-plugin'); ?></h3>
                <ul id="commentary-toc-list">
                    <li><em><?php _e('اختر أصحاحاً لعرض الفهرس', 'my-bible-plugin'); ?></em></li>
                </ul>
            </div>

            <div id="commentary-content-area" class="commentary-content">
                <div class="placeholder-text">
                    <i class="fas fa-book-open"></i>
                    <p><?php _e('يرجى اختيار المفسر والسفر والأصحاح للبدء.', 'my-bible-plugin'); ?></p>
                </div>
            </div>
        </div>

        <!-- TOC Toggle Button for Mobile -->
        <button class="toc-toggle-btn" id="toc-toggle" title="<?php _e('الفهرس', 'my-bible-plugin'); ?>">
            <i class="fas fa-list"></i>
        </button>
    </div>

    <!-- Modal for Verse Display -->
    <div id="verse-modal" class="bible-modal-overlay">
        <div class="bible-modal-content">
            <h3 id="modal-verse-title"></h3>
            <div id="modal-verse-text" class="bible-modal-definition"></div>
            <div class="modal-actions">
                <a href="#" id="btn-goto-chapter" class="button"><?php _e('قراءة الأصحاح كامل', 'my-bible-plugin'); ?></a>
                <button id="close-verse-modal"
                    class="bible-modal-close-button"><?php _e('إغلاق', 'my-bible-plugin'); ?></button>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('bible_commentary', 'my_bible_commentary_shortcode');
