<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Auto-import commentary data from SQL file on plugin activation
 * Handles large files with batch processing
 */
function my_bible_import_commentaries_on_activation()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'bible_commentaries';

    // Check if table exists first
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

    if (!$table_exists) {
        error_log('Bible Commentaries: Table does not exist. Run my_bible_create_tables() first.');
        return;
    }

    // Check if table has data already
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    if ($count > 0) {
        // Data already exists, skip import
        error_log('Bible Commentaries: Data already exists (' . $count . ' rows). Skipping import.');
        return;
    }

    // Path to SQL file
    $sql_file = MY_BIBLE_PLUGIN_DIR . 'assets/data/commentaries.sql';

    if (!file_exists($sql_file)) {
        error_log('Bible Commentaries: SQL file not found at ' . $sql_file);
        return;
    }

    error_log('Bible Commentaries: Starting import from ' . $sql_file);

    // Open file for reading
    $handle = fopen($sql_file, "r");

    if ($handle === false) {
        error_log('Bible Commentaries: Failed to open SQL file');
        return;
    }

    $processed = 0;
    $errors = 0;
    $current_query = '';

    // Temporarily disable foreign key checks and unique checks for speed
    $wpdb->query('SET foreign_key_checks = 0');
    $wpdb->query('SET unique_checks = 0');
    $wpdb->query('SET autocommit = 0'); // Wrap in transaction for speed

    while (($line = fgets($handle)) !== false) {
        $line = trim($line);

        // Skip comments and empty lines
        if (empty($line) || strpos($line, '--') === 0 || strpos($line, '/*') === 0) {
            continue;
        }

        // Add line to current query
        $current_query .= $line;

        // If line ends with semicolon, it's a complete query
        if (substr($line, -1) == ';') {
            
            // Skip CREATE TABLE and DROP TABLE statements (handled by dbDelta)
            if (stripos($current_query, 'CREATE TABLE') !== false || stripos($current_query, 'DROP TABLE') !== false) {
               $current_query = '';
               continue;
            }
            
            // Replace table name logic
            // Handle `commentaries` -> `wp_bible_commentaries`
            // Check if replacement is needed (optimization)
            if (strpos($current_query, '`commentaries`') !== false) {
                 $current_query = str_replace('`commentaries`', "`$table_name`", $current_query);
            } elseif (stripos($current_query, 'INSERT INTO commentaries') !== false) {
                 // Case insensitive unquoted match
                 $current_query = str_ireplace('INSERT INTO commentaries', "INSERT INTO `$table_name`", $current_query);
            }

            // Replace column names if needed (safe replacements)
            $current_query = str_replace('chapter_number', 'chapter', $current_query);
            // $current_query = str_replace('`source`', '`source_id`', $current_query); // Only if you are sure
             
            // Execute
            $result = $wpdb->query($current_query);

            if ($result === false) {
                $errors++;
                if ($errors <= 5) {
                    error_log('Bible Commentaries: SQL Error - ' . $wpdb->last_error);
                }
            } else {
                $processed++;
            }

            // Reset query buffer
            $current_query = '';
        }
    }

    // Commit and re-enable checks
    $wpdb->query('COMMIT');
    $wpdb->query('SET foreign_key_checks = 1');
    $wpdb->query('SET unique_checks = 1');
    $wpdb->query('SET autocommit = 1');

    fclose($handle);

    error_log("Bible Commentaries: Import completed. Processed: $processed statements. Errors: $errors");

    // Verify import
    $final_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    error_log("Bible Commentaries: Total rows in table: $final_count");

    if ($final_count == 0 && $errors > 0) {
        error_log("Bible Commentaries: WARNING - No rows imported! Check SQL file format.");
    }
}

/**
 * Alternative: AJAX-based import for very large files
 * This can be triggered from admin panel if needed
 */
function my_bible_ajax_import_commentaries_batch()
{
    check_ajax_referer('bible_import_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'bible_commentaries';
    $sql_file = MY_BIBLE_PLUGIN_DIR . 'assets/data/commentaries.sql';

    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $batch_size = 50;

    if (!file_exists($sql_file)) {
        wp_send_json_error(array('message' => 'SQL file not found'));
    }

    $sql_content = file_get_contents($sql_file);
    $statements = preg_split('/;[\r\n]+/', $sql_content);
    $total = count($statements);

    $batch = array_slice($statements, $offset, $batch_size);
    $processed = 0;

    foreach ($batch as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0)
            continue;

        $wpdb->query($statement);
        $processed++;
    }

    $new_offset = $offset + $batch_size;
    $is_complete = $new_offset >= $total;

    wp_send_json_success(array(
        'processed' => $processed,
        'offset' => $new_offset,
        'total' => $total,
        'complete' => $is_complete,
        'progress' => round(($new_offset / $total) * 100, 2)
    ));
}
add_action('wp_ajax_my_bible_import_commentaries_batch', 'my_bible_ajax_import_commentaries_batch');
