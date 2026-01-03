<?php
// منع الوصول المباشر
if (!defined('ABSPATH')) {
    exit;
}

// Helper function to normalize Arabic text (remove diacritics, normalize hamzas)
// Enhanced Normalizer with MB String Support
function my_bible_normalize_arabic_v2($string)
{
    if (empty($string))
        return '';

    // 1. Remove diacritics
    $string = preg_replace('/[\x{064B}-\x{065F}]/u', '', $string);

    // 2. Unify Alifs
    $string = preg_replace('/[أإآٱ]/u', 'ا', $string);

    // 3. Unify Ya/Alif Maqsura
    $string = str_replace('ى', 'ي', $string);

    // 4. Unify Taa Marbuta / Haa
    $string = str_replace('ة', 'ه', $string);

    // 5. Remove "AL" (ال) prefix
    if (mb_substr($string, 0, 2, 'UTF-8') === 'ال') {
        $string = mb_substr($string, 2, null, 'UTF-8');
    }

    return trim($string);
}

// wrapper
function my_bible_normalize_arabic($string)
{
    return my_bible_normalize_arabic_v2($string);
}

/**
 * Converts "Old System" book names (from Verses table/Dropdown) to "New System" (Commentaries table).
 * e.g. "صموئيل الاول" -> "1 صموئيل"
 */
function my_bible_convert_to_commentary_book_name($book_name)
{
    if (empty($book_name))
        return $book_name;

    $book_name = trim($book_name);

    // Direct Map for known mismatches
    $map = array(
        // Samuel
        'صموئيل الاول' => '1 صموئيل',
        'صموئيل الأول' => '1 صموئيل',
        'صموئيل الثاني' => '2 صموئيل',

        // Kings
        'ملوك الاول' => '1 ملوك',
        'ملوك الأول' => '1 ملوك',
        'ملوك اول' => '1 ملوك',
        'ملوك الثاني' => '2 ملوك',
        'ملوك ثاني' => '2 ملوك',

        // Chronicles
        'اخبار الايام الاول' => '1 أخبار',
        'أخبار الأيام الأول' => '1 أخبار',
        'اخبار الايام الثاني' => '2 أخبار',
        'أخبار الأيام الثاني' => '2 أخبار',

        // Corinthians
        'كورنثوس الاولى' => '1 كورنثوس',
        'كورنثوس الأولى' => '1 كورنثوس',
        'كورنثوس الثانية' => '2 كورنثوس',

        // Thessalonians
        'تسالونيكي الاولى' => '1 تسالونيكي',
        'تسالونيكي الأولى' => '1 تسالونيكي',
        'تسالونيكي الثانية' => '2 تسالونيكي',

        // Timothy
        'تيموثاوس الاولى' => '1 تيموثاوس',
        'تيموثاوس الأولى' => '1 تيموثاوس',
        'تيموثاوس الثانية' => '2 تيموثاوس',

        // Peter
        'بطرس الاولى' => '1 بطرس',
        'بطرس الأولى' => '1 بطرس',
        'بطرس الثانية' => '2 بطرس',

        // John
        'يوحنا الاولى' => '1 يوحنا',
        'يوحنا الأولى' => '1 يوحنا',
        'يوحنا الثانية' => '2 يوحنا',
        'يوحنا الثالثة' => '3 يوحنا',

        // Books with/without "ال" prefix
        'قضاة' => 'القضاة',
        'تكوين' => 'التكوين',
        'خروج' => 'الخروج',
        'لاويين' => 'اللاويين',
        'عدد' => 'العدد',
        'تثنية' => 'التثنية',
        'مزامير' => 'المزامير',
        'امثال' => 'الأمثال',
        'الامثال' => 'الأمثال',
        'جامعة' => 'الجامعة',
        'حكمة' => 'الحكمة',
        'رؤيا' => 'الرؤيا',
        'رومية' => 'الرومية',
        'غلاطية' => 'الغلاطية',
        'عبرانيين' => 'العبرانيين',

        // Hamza variations
        'اشعياء' => 'إشعياء',
        'اشعيا' => 'إشعياء',
        'ارميا' => 'إرميا',
        'ارمياء' => 'إرميا',
        'استير' => 'إستير',
        'ايوب' => 'أيوب',
        'اعمال الرسل' => 'أعمال الرسل',
        'افسس' => 'أفسس',
    );

    if (isset($map[$book_name])) {
        return $map[$book_name];
    }

    // Heuristic: "Name Al-Awal" -> "1 Name"
    if (preg_match('/(.+)\s+(الاول|الأول)$/u', $book_name, $m)) {
        return '1 ' . trim($m[1]);
    }
    // Heuristic: "Name Al-Thani" -> "2 Name"
    if (preg_match('/(.+)\s+الثاني$/u', $book_name, $m)) {
        return '2 ' . trim($m[1]);
    }

    return $book_name;
}

/**
 * Convert book name FROM Commentary format BACK TO Bible verses format
 * Reverse of my_bible_convert_to_commentary_book_name()
 * Example: "1 ملوك" -> "ملوك الاول"
 */
function my_bible_convert_from_commentary_book_name($commentary_book_name)
{
    if (empty($commentary_book_name))
        return $commentary_book_name;

    $commentary_book_name = trim($commentary_book_name);

    // Reverse mapping
    $reverse_map = array(
        // Numbered books
        '1 صموئيل' => 'صموئيل الاول',
        '2 صموئيل' => 'صموئيل الثاني',
        '1 ملوك' => 'ملوك الاول',
        '2 ملوك' => 'ملوك الثاني',
        '1 أخبار' => 'اخبار الايام الاول',
        '2 أخبار' => 'اخبار الايام الثاني',
        '1 كورنثوس' => 'كورنثوس الاولى',
        '2 كورنثوس' => 'كورنثوس الثانية',
        '1 تسالونيكي' => 'تسالونيكي الاولى',
        '2 تسالونيكي' => 'تسالونيكي الثانية',
        '1 تيموثاوس' => 'تيموثاوس الاولى',
        '2 تيموثاوس' => 'تيموثاوس الثانية',
        '1 بطرس' => 'بطرس الاولى',
        '2 بطرس' => 'بطرس الثانية',
        '1 يوحنا' => 'يوحنا الاولى',
        '2 يوحنا' => 'يوحنا الثانية',
        '3 يوحنا' => 'يوحنا الثالثة',

        // Books with "ال" prefix in commentary -> without in Bible
        'القضاة' => 'قضاة',
        'التكوين' => 'تكوين',
        'الخروج' => 'خروج',
        'اللاويين' => 'لاويين',
        'العدد' => 'عدد',
        'التثنية' => 'تثنية',
        'المزامير' => 'مزامير',
        'الأمثال' => 'امثال',
        'الجامعة' => 'جامعة',
        'الحكمة' => 'حكمة',
        'الرؤيا' => 'رؤيا',
        'الرومية' => 'رومية',
        'الغلاطية' => 'غلاطية',
        'العبرانيين' => 'عبرانيين',

        // Hamza variations (reverse)
        'إشعياء' => 'اشعياء',
        'إرميا' => 'ارميا',
        'مراثي إرميا' => 'مراثي ارميا',
        'إستير' => 'استير',
        'أيوب' => 'ايوب',
        'أعمال الرسل' => 'اعمال الرسل',
        'أفسس' => 'افسس',
    );

    if (isset($reverse_map[$commentary_book_name])) {
        return $reverse_map[$commentary_book_name];
    }

    // Heuristic reverse: "1 Name" -> "Name الاول"
    if (preg_match('/^1\\s+(.+)$/u', $commentary_book_name, $m)) {
        return trim($m[1]) . ' الاول';
    }
    // Heuristic reverse: "2 Name" -> "Name الثاني"
    if (preg_match('/^2\\s+(.+)$/u', $commentary_book_name, $m)) {
        return trim($m[1]) . ' الثاني';
    }
    // Heuristic reverse: "3 Name" -> "Name الثالثة"
    if (preg_match('/^3\\s+(.+)$/u', $commentary_book_name, $m)) {
        return trim($m[1]) . ' الثالثة';
    }

    return $commentary_book_name;
}

// --------------------------------------------------------------------
// New Advanced Commentary Parser (V2.0)
// Professional Rebuild to handle structured tags
// --------------------------------------------------------------------

function my_bible_process_commentary_tags($content, $book_name = null, $chapter = null, $source_id = null)
{
    // 1. Initialize TOC
    $toc = array();

    // 2. Pre-process basic formatting tags
    // {{p}} -> Paragraph break
    $content = str_replace('{{p}}', '</p><p>', $content);

    // {{d}} -> Divider
    // {{d}} -> Divider
    $content = str_replace('{{d}}', '<hr class="commentary-divider">', $content);

    // 2.5 Fix Inline Numbered Lists (e.g. "1- Point A 2- Point B" -> New lines)
    // Matches: [Space/Start] Number [.-] [Space]
    $content = preg_replace_callback('/(?:\s|^)([\d\x{0660}-\x{0669}]+[\.\-])\s+/u', function ($m) {
        $num = $m[1];
        // Only trigger for numbers (avoid dates/verses like 1.1)
        // Check if pure number + sep
        return "<br><span class='list-number'>$num</span> ";
    }, $content);

    // 3. Process Verse Reference Tags (Structure: {{ref_s_BookID_Chapter_Verse}})
    // This is the core fix: Use ID mapping instead of regex guessing on text
    $content = preg_replace_callback('/\{\{ref_s_(\d+)_(\d+)_(\d+)\}\}(.*?)\{\{\/ref\}\}/s', function ($matches) {
        $book_id = intval($matches[1]);
        $chapter = intval($matches[2]);
        $verse = intval($matches[3]);
        $text = $matches[4]; // The text likely contains formatting like "مز ٨٠ : ١"

        // Resolve Book Name from ID
        $book_name = my_bible_get_book_name_by_id($book_id);

        // Fix: Override with text detection if available (e.g. "أع ١٣: ٣٢" or "2 بط 3: 18")
        // Normalized text for processing
        $text_norm = preg_replace('/\s+/u', ' ', $text);

        // Regex: Include [\d\x{0660}-\x{0669}] for Arabic digits in group 1
        if (preg_match('/(?:^|[\(\s])((?:[\d\x{0660}-\x{0669}]+\s*)?[\p{L}\p{M}]+(?:\s+[\p{L}\p{M}]+)*)\s*[\d\x{0660}-\x{0669}]+[:：]/u', $text_norm, $text_m)) {
            $extracted = trim($text_m[1]);
            $extracted_clean = str_replace(array('(', ')', '؛', '،'), '', $extracted);
            $extracted_clean = trim($extracted_clean);

            // Fix Fragmented Abbreviations in Display Text (e.g. "ي و" -> "يو")
            $cleaned_fragment = str_replace(' ', '', $extracted_clean);

            // Heuristic: If originally spaced, but short & letters only -> likely fragmented
            if ($extracted_clean !== $cleaned_fragment && mb_strlen($cleaned_fragment) <= 4 && preg_match('/^[\p{L}\p{M}]+$/u', $cleaned_fragment)) {
                // Replace in the FULL $text. using robust regex replacement
                $chars = preg_split('//u', $cleaned_fragment, -1, PREG_SPLIT_NO_EMPTY);
                $pattern_frag = '/' . implode('\s*', array_map(function ($c) {
                    return preg_quote($c, '/');
                }, $chars)) . '/u';

                // Apply replacement to $text
                $text = preg_replace($pattern_frag, $cleaned_fragment, $text);

                $extracted_clean = $cleaned_fragment;
                // Renormalize text just in case
                $text = preg_replace('/\s+/u', ' ', $text);
            }

            // Only override if extraction looks valid
            if (mb_strlen($extracted_clean) >= 1) {
                $book_name = $extracted_clean;
            }
        }

        if ($book_name) {
            return sprintf(
                '<a href="#" class="smart-verse-link" data-book="%s" data-chapter="%d" data-verse="%d">%s</a>',
                esc_attr($book_name),
                $chapter,
                $verse,
                $text
            );
        } else {
            return $text;
        }
    }, $content);

    // 4. Process Range/Double Reference Tags
    // Structure assumed: {{ref_d_BookID_Chap1_Verse1_Chap2_Verse2}}
    $content = preg_replace_callback('/\{\{ref_d_(\d+)_(\d+)_(\d+)_(\d+)_(\d+)\}\}(.*?)\{\{\/ref\}\}/s', function ($matches) {
        $book_id = intval($matches[1]);
        $chap1 = intval($matches[2]);
        $verse1 = intval($matches[3]);
        // $chap2  = intval($matches[4]); 
        // $verse2 = intval($matches[5]);
        $text = $matches[6];

        $book_name = my_bible_get_book_name_by_id($book_id);

        // Fix: Override with text detection if available (Identical logic to ref_s)
        $text_norm = preg_replace('/\s+/u', ' ', $text);

        if (preg_match('/(?:^|[\(\s])((?:[\d\x{0660}-\x{0669}]+\s*)?[\p{L}\p{M}]+(?:\s+[\p{L}\p{M}]+)*)\s*[\d\x{0660}-\x{0669}]+[:：]/u', $text_norm, $text_m)) {
            $extracted = trim($text_m[1]);
            $extracted_clean = str_replace(array('(', ')', '؛', '،'), '', $extracted);
            $extracted_clean = trim($extracted_clean);

            // Fix Fragmented Abbreviations
            $cleaned_fragment = str_replace(' ', '', $extracted_clean);

            if ($extracted_clean !== $cleaned_fragment && mb_strlen($cleaned_fragment) <= 4 && preg_match('/^[\p{L}\p{M}]+$/u', $cleaned_fragment)) {
                $chars = preg_split('//u', $cleaned_fragment, -1, PREG_SPLIT_NO_EMPTY);
                $pattern_frag = '/' . implode('\s*', array_map(function ($c) {
                    return preg_quote($c, '/');
                }, $chars)) . '/u';

                $text = preg_replace($pattern_frag, $cleaned_fragment, $text);

                $extracted_clean = $cleaned_fragment;
                $text = preg_replace('/\s+/u', ' ', $text);
            }

            if (mb_strlen($extracted_clean) >= 1) {
                $book_name = $extracted_clean;
            }
        }

        if ($book_name) {
            // Link to start verse
            return sprintf(
                '<a href="#" class="smart-verse-link" data-book="%s" data-chapter="%d" data-verse="%d">%s</a>',
                esc_attr($book_name),
                $chap1,
                $verse1,
                $text
            );
        }
        return $text;
    }, $content);

    // 4.5 Process Standard Commentary Tags (New: p, d, g, ref...)
    if (function_exists('my_bible_parse_commentary_tags')) {
        // Exclude title/subtitle to preserve existing TOC logic
        $content = my_bible_parse_commentary_tags($content, array('title', 'subtitle'));
    }

    // 5. Process Headings for TOC {{t}}...{{/t}} (Level 1)
    $content = preg_replace_callback('/\{\{t\}\}(.*?)\{\{\/t\}\}/s', function ($matches) use (&$toc) {
        $title = trim(strip_tags($matches[1]));
        if (empty($title))
            return '';

        // Use hash for distinct, valid HTML ID (handles Arabic safely)
        $id = 'toc-' . substr(md5($title), 0, 8) . '-' . rand(100, 999);
        $toc[] = array('level' => 1, 'title' => $title, 'id' => $id);

        return "<h2 id='$id' class='commentary-heading-1'>$title</h2>";
    }, $content);

    // 6. Process Sub-headings {{b}}...{{/b}} (Level 2) - ENHANCED FOR ARABIC NUMERALS
    $content = preg_replace_callback('/\{\{b\}\}(.*?)\{\{\/b\}\}/s', function ($matches) use (&$toc) {
        $text = $matches[1];
        $plain_text = trim(strip_tags($text));

        $check_text = rtrim($plain_text, ':');

        // Skip if it's a verse prefix with EASTERN ARABIC numerals (e.g., "أية (١):-")
        if (preg_match('/^أية\s*\([\d\x{0660}-\x{0669}]+\)\s*:-?$/u', $plain_text)) {
            // Return as plain text, will be captured by {{vt}} processing
            return $text;
        }

        if (mb_strlen($check_text) < 100 && strpos($check_text, "\n") === false && strpos($plain_text, '</p>') === false) {
            // Valid Subheading
            $id = 'toc-' . substr(md5($plain_text), 0, 8) . '-' . rand(100, 999);
            $toc[] = array('level' => 2, 'title' => $plain_text, 'id' => $id);
            return "<h3 id='$id' class='commentary-heading-2'>$text</h3>";
        } else {
            return "<strong>$text</strong>";
        }
    }, $content);

    // 7. Process Verse Text {{vtX}}...{{/vt}} (Styling) - FIXED FOR ARABIC NUMERALS
    // Capture optional preceding text with EASTERN ARABIC numerals (e.g., "أية (١):- ")
    $content = preg_replace_callback('/(أية\s*\([\d\x{0660}-\x{0669}]+\)\s*:-?\s*)?\{\{vt([\d\x{0660}-\x{0669}]+)\}\}(.*?)\{\{\/vt\}\}/su', function ($matches) {
        $prefix = isset($matches[1]) && !empty($matches[1]) ? $matches[1] : ''; // "أية (١):- "
        $verse_raw = $matches[2]; // "1" or "١"
        $text = $matches[3]; // Verse text

        // Convert to English for data attribute
        $verse_num = function_exists('my_bible_arabic_to_english_numbers') ?
            my_bible_arabic_to_english_numbers($verse_raw) : $verse_raw;

        // Clean up quotes if present at edges
        $text = trim($text, '" ');

        // Build the verse box with prefix inside
        $html = "<div class='commentary-verse-text' data-verse-num='$verse_num'>";

        // If there's a prefix, include it
        if ($prefix) {
            $html .= "<span class='verse-prefix'>" . trim($prefix) . "</span> ";
        }

        $html .= "<span class='verse-number'>($verse_raw)</span> $text</div>";

        return $html;
    }, $content);

    // 8. Remove Verse Markers {{vm...}} and PROCESS {{l}} correctly
    $content = preg_replace('/\{\{vm\d+\}\}/', '', $content);

    // Fix: Maps {{l}} to Line Break based on user evidence
    $content = str_replace('{{l}}', '<br class="commentary-line">', $content);

    // 8. Clean up any remaining unknown tags {{...}}
    // Be careful not to kill braces in text, so match specific patterns only if needed.
    // Generally safe to assume double braces are tags here.
    $content = preg_replace('/\{\{\/?[a-z0-9_]+\}\}/', '', $content);

    // Wrap content
    if (strpos($content, '<p>') === false && strpos($content, '<h') === false) {
        $content = '<p>' . $content . '</p>';
    }

    // Generate Page Title for Client-Side Update
    $page_title = '';
    if (function_exists('my_bible_generate_title_string')) {
        $page_title = my_bible_generate_title_string($book_name, $chapter, $source_id);
    }

    return array(
        'html' => $content,
        'toc' => $toc,
        'page_title' => $page_title
    );
}

function my_bible_split_multiple_references($content)
{
    // This function handles TEXT references like (خر ١٠: ١٨؛ ١١: ٢٢) or (لا ٢٤: ١٠ + ١مل ٣٨: ١٨)
    // It's a pre-processor for the fallback regex
    return preg_replace_callback(
        '/\(([^)]+)\)/u',
        function ($matches) {
            $inside = $matches[1];
            // Split by semicolon, comma, or PLUS
            if (strpos($inside, '؛') === false && strpos($inside, '،') === false && strpos($inside, ';') === false && strpos($inside, '+') === false) {
                return $matches[0];
            }

            // Split pattern includes +
            $parts = preg_split('/[؛،;+]+/u', $inside);
            if (count($parts) < 2)
                return $matches[0];

            $processed_parts = array();
            $last_book = '';
            $last_chapter = '';

            foreach ($parts as $part) {
                $part = trim($part);
                if (empty($part))
                    continue;

                // Pattern 1: Full ref (Updated to support numbered books like 1مل)
                // NOW Supports: Optional Number + Book Name
                // e.g. "1مل 38: 18" or "تك 1: 1" or "عب11: 3" (no space)
                if (preg_match('/^([\d\x{0660}-\x{0669}]*\s*[^\d:\s]+(?:\s+[^\d:\s]+)*)\s*([\d\x{0660}-\x{0669}]+)\s*[:：]\s*([\d\x{0660}-\x{0669}]+)/u', $part, $m)) {
                    $last_book = trim($m[1]); // e.g. "1مل" or "تك"
                    $last_chapter = $m[2];
                    $processed_parts[] = "($part)";
                }
                // Pattern 2: Chap:Verse
                elseif (preg_match('/^([\d\x{0660}-\x{0669}]+)\s*[:：]\s*([\d\x{0660}-\x{0669}]+)/u', $part, $m)) {
                    if ($last_book) {
                        $last_chapter = $m[1];
                        $processed_parts[] = "($last_book $part)";
                    } else {
                        $processed_parts[] = "($part)";
                    }
                }
                // Pattern 3: Verse only
                elseif (preg_match('/^([\d\x{0660}-\x{0669}]+(?:[-–][\d\x{0660}-\x{0669}]+)?)$/u', $part)) {
                    // Added range support to verse only pattern
                    if ($last_book && $last_chapter) {
                        $processed_parts[] = "($last_book $last_chapter:$part)";
                    } else {
                        $processed_parts[] = "($part)";
                    }
                } else {
                    // Try to detect just a book change without full ref?
                    // Rarely happens in (Book Chap:Verse + Book Chap:Verse) format, usually it's full ref.
                    $processed_parts[] = "($part)";
                }
            }
            return implode(' ', $processed_parts);
        },
        $content
    );
}

// Process Commentary Content (Entry Point)
function my_bible_process_commentary_content($content, $book_name = null, $chapter = null, $source_id = null)
{

    // 1. Split text references first
    $content = my_bible_split_multiple_references($content);

    // 2. Process with New Parser
    $processed = my_bible_process_commentary_tags($content, $book_name, $chapter, $source_id);
    $html = $processed['html'];

    // 3. Fallback: Parse Plain Text References (Smart Linking)
    // Only for text NOT already linked

    // Simplified Regex for performance and safety
    // Matches (Book Chap:Verse)
    $pattern1 = '/\(([^\d\s<]+)\s*([\d\x{0660}-\x{0669}]+)\s*[:：]\s*([\d\x{0660}-\x{0669}]+)(?:\s*[-–]\s*([\d\x{0660}-\x{0669}]+))?\)/u';

    $html = preg_replace_callback($pattern1, function ($matches) {
        // Skip if inside link
        return my_bible_create_verse_link($matches);
    }, $html);

    // Matches Book Chap:Verse without parens (careful not to match inside tags)
    // Updated to support: ٢مل١٣: ١-١٥ (no space between book and chapter)
    // Pattern: [Arabic letters/numbers][optional space][digits]:[digits][-digits]
    $pattern2 = '/(?<!\()(?<!>)([\d\x{0660}-\x{0669}]*[\x{0600}-\x{06FF}]+)\s*([\d\x{0660}-\x{0669}]+)\s*[:：]\s*([\d\x{0660}-\x{0669}]+)(?:\s*[-–]\s*([\d\x{0660}-\x{0669}]+))?(?!\))/u';

    $html = preg_replace_callback($pattern2, function ($matches) {
        if (strpos($matches[0], 'href') !== false)
            return $matches[0];
        return my_bible_create_verse_link($matches);
    }, $html);

    // Save TOC to a global variable or specialized transient if needed? 
    // Ideally we pass it back, but we return string here.
    // We already do return array in the main parsing function.

    return $html;
}

// Helper for regex callback
function my_bible_create_verse_link($matches)
{
    $full_match = $matches[0];
    $book_name = trim($matches[1]);

    // Skip if looks like HTML attribute or tag
    if (strpos($book_name, '<') !== false || strpos($book_name, '=') !== false)
        return $full_match;

    $chapter = $matches[2];
    $verse_start = $matches[3];
    $verse_end = isset($matches[4]) ? $matches[4] : '';

    $chapter_en = my_bible_arabic_to_english_numbers($chapter);
    $verse_start_en = my_bible_arabic_to_english_numbers($verse_start);
    $verse_end_en = $verse_end ? my_bible_arabic_to_english_numbers($verse_end) : '';

    $clean_book = preg_replace('/^[\d\x{0660}-\x{0669}]+/u', '', $book_name);
    $clean_book = trim($clean_book);

    $attrs = "data-book='" . esc_attr($clean_book) . "' data-chapter='" . esc_attr($chapter_en) . "' data-verse='" . esc_attr($verse_start_en) . "'";
    if ($verse_end_en)
        $attrs .= " data-verse-end='" . esc_attr($verse_end_en) . "'";

    // Wrap in bdi to prevent RTL number reversal (12:13 becoming 13:12)
    return "<a href='#' class='smart-verse-link' $attrs><bdi>$full_match</bdi></a>";
}

// Helper for V2 regex (With Number Prefix support)
function my_bible_create_verse_link_v2($matches, $has_parens = true)
{
    $full_match = $matches[0];

    // Regex Structure:
    // [1] = Prefix Number (optional)
    // [2] = Book Name Text (can have spaces)
    // [3] = Chapter
    // [4] = Verse Start
    // [5] = Verse End (Optional)

    $prefix_num = isset($matches[1]) && !empty($matches[1]) ? trim($matches[1]) : '';
    $book_text = trim($matches[2]);

    $book_combined = $prefix_num ? $prefix_num . ' ' . $book_text : $book_text;

    // Skip if looks like HTML attribute
    if (strpos($book_combined, '<') !== false || strpos($book_combined, '=') !== false)
        return $full_match;

    $chapter = $matches[3];
    $verse_start = $matches[4];
    $verse_end = isset($matches[5]) ? $matches[5] : '';

    $chapter_en = my_bible_arabic_to_english_numbers($chapter);
    $verse_start_en = my_bible_arabic_to_english_numbers($verse_start);
    $verse_end_en = $verse_end ? my_bible_arabic_to_english_numbers($verse_end) : '';

    // Use Helper to resolve proper DB name (handles prefix logic internally too, but we pass combined)
    // Actually, passing individual parts is cleaner if helper supported it, but we standardized on string input.
    // The book combined string "2 بط" will be handled by my_bible_find_book_match.

    // But for the data attribute, we want the CLEAN name (e.g. "بطرس الثانية").
    // We don't have that yet unless we query.
    // The previous logic used regex to strip numbers.
    // Here we should rely on the text content?
    // Actually, the JS side uses the data-book to query DB.
    // If we pass "2 بط", the JS -> AJAX -> PHP(modal) -> find_book_match will resolve it. 
    // So passing "2 بط" is fine IF finding logic is consistent.

    // Let's pass the raw text we found as the book.
    $clean_book = $book_combined;

    $attrs = "data-book='" . esc_attr($clean_book) . "' data-chapter='" . esc_attr($chapter_en) . "' data-verse='" . esc_attr($verse_start_en) . "'";
    if ($verse_end_en)
        $attrs .= " data-verse-end='" . esc_attr($verse_end_en) . "'";

    return "<a href='#' class='smart-verse-link' $attrs><bdi>$full_match</bdi></a>";
}


// AJAX: Get Commentary Chapters
function my_bible_ajax_get_commentary_chapters()
{
    check_ajax_referer('bible_commentary_nonce', 'nonce');
    global $wpdb;
    $book = isset($_POST['book']) ? sanitize_text_field($_POST['book']) : '';
    $source = isset($_POST['source']) ? sanitize_text_field($_POST['source']) : '';

    if (empty($book))
        wp_send_json_error(array('message' => __('Missing parameters', 'my-bible-plugin')));

    $table_commentaries = $wpdb->prefix . 'bible_commentaries';

    // Build Query
    $sql = "SELECT DISTINCT chapter FROM $table_commentaries WHERE book_name = %s";
    $params = array($book);

    if (!empty($source)) {
        $sql .= " AND source_id = %s";
        $params[] = $source;
    }

    $sql .= " ORDER BY chapter ASC";

    // Debug
    // error_log("AJAX Chapters: Input Book='$book', Source='$source'");

    // 1. Try to resolve book name first
    // Use the specific converter for Commentary Table names (e.g. "1 صموئيل")
    $converted_book_comm = my_bible_convert_to_commentary_book_name($book);
    if ($converted_book_comm !== $book) {
        $book = $converted_book_comm;
        $params[0] = $book;
    } elseif (function_exists('my_bible_find_book_match')) {
        // Fallback: If not found in map, try general abbreviation (e.g. "ص") -> "1 صموئيل"
        // But be careful not to map back to "صموئيل الاول" which is wrong for this table
        $resolved_book = my_bible_find_book_match($book);
        // Only use if it looks different and follows '1 Name' pattern or simple name
        if ($resolved_book) {
            $converted_resolved = my_bible_convert_to_commentary_book_name($resolved_book);
            if ($converted_resolved !== $book) {
                $book = $converted_resolved;
                $params[0] = $book;
            }
        }
    }

    // Try exact match (with potentially resolved name)
    $chapters = $wpdb->get_col($wpdb->prepare($sql, $params));

    // Try normalized match if empty properties
    if (empty($chapters)) {
        $normalized_input = my_bible_normalize_arabic($book);
        $all_books = $wpdb->get_col("SELECT DISTINCT book_name FROM $table_commentaries");
        foreach ($all_books as $db_book) {
            if (my_bible_normalize_arabic($db_book) === $normalized_input) {
                // Re-build query with matched book name
                $sql = "SELECT DISTINCT chapter FROM $table_commentaries WHERE book_name = %s";
                $params = array($db_book);
                if (!empty($source)) {
                    $sql .= " AND source_id = %s";
                    $params[] = $source;
                }
                $sql .= " ORDER BY chapter ASC";

                $chapters = $wpdb->get_col($wpdb->prepare($sql, $params));
                break;
            }
        }
    }

    if (empty($chapters))
        wp_send_json_error(array('message' => __('No chapters found', 'my-bible-plugin')));

    wp_send_json_success(array('chapters' => $chapters));
}
add_action('wp_ajax_my_bible_get_commentary_chapters', 'my_bible_ajax_get_commentary_chapters');
add_action('wp_ajax_nopriv_my_bible_get_commentary_chapters', 'my_bible_ajax_get_commentary_chapters');

// --------------------------------------------------------------------
// Core Logic: Get Commentary Content (Reusable for SSR & AJAX)
// --------------------------------------------------------------------
function my_bible_get_commentary_content($book_name, $chapter, $source_id = 'af')
{
    global $wpdb;

    // DEBUG: Log the incoming request
    error_log("[COMMENTARY REQUEST] Book='$book_name', Chapter=$chapter, Source='$source_id'");

    // Validate inputs
    if (empty($book_name) || empty($chapter)) {
        error_log("[COMMENTARY ERROR] Empty book_name or chapter");
        return array('success' => false, 'message' => __('البيانات غير مكتملة', 'my-bible-plugin'));
    }

    $table_commentaries = $wpdb->prefix . 'bible_commentaries';

    // Check cache first (Transient)
    $cache_key = 'commentary_' . md5($book_name . '_' . $chapter . '_' . $source_id);
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    // Convert Book Name to Commentary Table Standards (e.g. "صموئيل الاول" -> "1 صموئيل")
    $book_name_query = my_bible_convert_to_commentary_book_name($book_name);

    // 1. Fetch    // Prepare the query with new schema columns: chapter, source_id
    $query = $wpdb->prepare(
        "SELECT text FROM $table_commentaries 
         WHERE book_name = %s 
         AND chapter = %d 
         AND source_id = %s
         LIMIT 1",
        $book_name_query, // Use converted name
        $chapter,
        $source_id // Variable name in PHP might still be $source_id or we map $source -> source_id
    );
    error_log("[COMMENTARY SQL] $query");
    $rows = $wpdb->get_results($query);

    // DEBUG: Direct check
    if (empty($rows)) {
        $check_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_commentaries");
        error_log("[COMMENTARY DEBUG] Total rows in table: $check_count");

        // Try a broader search to see if book name matches partially
        $like_book = '%' . $wpdb->esc_like($book_name) . '%';
        $broad_sql = $wpdb->prepare("SELECT id, book_name FROM $table_commentaries WHERE book_name LIKE %s LIMIT 5", $like_book);
        $broad_res = $wpdb->get_results($broad_sql);
        error_log("[COMMENTARY DEBUG] Broad search for '$book_name' returned: " . print_r($broad_res, true));
    }

    // Fallback normalization logic if empty
    if (empty($rows)) {
        error_log("[COMMENTARY] No rows found with exact match, trying normalization...");
        $normalized_input = function_exists('my_bible_normalize_arabic') ? my_bible_normalize_arabic($book_name) : $book_name;
        error_log("[COMMENTARY NORMALIZED] Input: '$book_name' -> Normalized: '$normalized_input'");
        $all_books = $wpdb->get_col("SELECT DISTINCT book_name FROM $table_commentaries");
        foreach ($all_books as $db_book) {
            $norm_db = function_exists('my_bible_normalize_arabic') ? my_bible_normalize_arabic($db_book) : $db_book;
            if ($norm_db === $normalized_input) {
                // Found match
                error_log("[COMMENTARY MATCH] Found normalized match: DB='$db_book' matches input='$book_name'");
                $book_name = $db_book; // Update book name for subsequent queries
                $sql_fallback = $wpdb->prepare(
                    "SELECT * FROM $table_commentaries WHERE book_name = %s AND chapter = %d AND source_id = %s ORDER BY id ASC",
                    $book_name,
                    $chapter,
                    $source_id
                );
                error_log("[COMMENTARY SQL FALLBACK] $sql_fallback");
                $rows = $wpdb->get_results($sql_fallback);
                break;
            }
        }
    }

    if (empty($rows)) {
        error_log("[COMMENTARY ERROR] No content found after all attempts for Book='$book_name', Chapter=$chapter, Source='$source_id'");
        return array('success' => false, 'message' => __('Content not found', 'my-bible-plugin'));
    }

    // DEBUG: Log what we actually got
    $row_count = count($rows);
    $first_row = $rows[0];
    $actual_book = isset($first_row->book_name) ? $first_row->book_name : 'N/A';
    $actual_chapter = isset($first_row->chapter) ? $first_row->chapter : 'N/A';
    $first_id = isset($first_row->id) ? $first_row->id : 'N/A';
    $text_preview = isset($first_row->text) ? mb_substr($first_row->text, 0, 100) : 'N/A';
    error_log("[COMMENTARY SUCCESS] Found $row_count rows | Actual: Book='$actual_book', Chapter=$actual_chapter, First ID=$first_id");
    error_log("[COMMENTARY PREVIEW] Text: '$text_preview...'");

    // 2. Fetch Bible Verses for Text Matching
    $table_verses = $wpdb->prefix . 'bible_verses';

    // Resolve Book Name for Verses Table (Abbreviation/No-AL Mapping)
    $verse_book_name = function_exists('my_bible_find_book_match') ? my_bible_find_book_match($book_name) : $book_name;

    // Fallback if abbreviation lookup fails (e.g. if DB uses same name)
    if (!$verse_book_name)
        $verse_book_name = $book_name;

    $chapter_verses = $wpdb->get_results($wpdb->prepare(
        "SELECT verse, text FROM $table_verses WHERE book = %s AND chapter = %d",
        $verse_book_name,
        $chapter
    ));

    $verse_texts = array();
    if ($chapter_verses) {
        foreach ($chapter_verses as $v_obj) {
            $v_text = $v_obj->text;
            $v_text = str_replace(array('أ', 'إ', 'آ'), 'ا', $v_text);
            $v_text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $v_text);
            $verse_texts[$v_obj->verse] = $v_text;
        }
    }

    // 3. Smart Sorting Logic
    $groups = array();
    $buffer_headers = array();
    $current_verse_key = 0;

    foreach ($rows as $row) {
        // Detect Verse Tag
        if (preg_match('/\{\{vt([\d\x{0660}-\x{0669}]+)\}\}/u', $row->text, $m)) {
            $v_num = intval(my_bible_arabic_to_english_numbers($m[1]));
            $current_verse_key = $v_num;
            if (!empty($buffer_headers)) {
                if (!isset($groups[$current_verse_key]))
                    $groups[$current_verse_key] = array();
                foreach ($buffer_headers as $h_row)
                    $groups[$current_verse_key][] = $h_row;
                $buffer_headers = array();
            }
            $groups[$current_verse_key][] = $row;
        }
        // Detect Title
        elseif (strpos($row->text, '{{t}}') !== false) {
            $current_verse_key = 0;
            if (!empty($buffer_headers)) {
                if (!isset($groups[0]))
                    $groups[0] = array();
                foreach ($buffer_headers as $h_row)
                    $groups[0][] = $h_row;
                $buffer_headers = array();
            }
            $groups[0][] = $row;
        }
        // Detect Header
        elseif (strpos($row->text, '{{b}}') !== false && mb_strlen(strip_tags($row->text)) < 100) {
            $buffer_headers[] = $row;
        } else {
            // Try to match verse text or assign to buffer
            $matched_verse = false;

            // Try to extract verse number from text patterns like "فى آية ٢١ :-"
            if (preg_match('/(?:فى|في)\s*(?:آية|اية)\s*(?:\()?([\d\x{0660}-\x{0669}]+)(?:\))?/u', $row->text, $verse_match)) {
                $v_num = intval(my_bible_arabic_to_english_numbers($verse_match[1]));
                if ($v_num > 0) {
                    $current_verse_key = $v_num;
                    $matched_verse = true;
                }
            }

            // Fallback: Try text matching with verse content
            if (!$matched_verse && preg_match('/\{\{b\}\}(.*?)\{\{\/b\}\}/u', $row->text, $b_match)) {
                $bold_text = $b_match[1];
                $bold_text = str_replace(array('أ', 'إ', 'آ'), 'ا', $bold_text);
                $bold_text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $bold_text);
                $bold_text = trim($bold_text);
                $bold_text = str_replace(array(':', '-', '؟', '!', '.'), '', $bold_text);

                if (mb_strlen($bold_text) > 3) {
                    foreach ($verse_texts as $v_num => $v_text) {
                        if (mb_strpos($v_text, $bold_text) !== false) {
                            $current_verse_key = $v_num;
                            $matched_verse = true;
                            break;
                        }
                    }
                }
            }

            // If matched or we have a current verse, add to that group
            // Otherwise, add to buffer to be assigned later
            if ($matched_verse || $current_verse_key > 0) {
                if (!isset($groups[$current_verse_key]))
                    $groups[$current_verse_key] = array();
                $groups[$current_verse_key][] = $row;
            } else {
                // Unmatched content goes to buffer (will be added to next verse or intro)
                $buffer_headers[] = $row;
            }
        }
    }

    if (!empty($buffer_headers)) {
        if (!isset($groups[$current_verse_key]))
            $groups[$current_verse_key] = array();
        foreach ($buffer_headers as $h_row)
            $groups[$current_verse_key][] = $h_row;
    }

    ksort($groups);

    $full_text = '';
    foreach ($groups as $v_key => $g_rows) {
        foreach ($g_rows as $row) {
            $full_text .= $row->text . "\n\n";
        }
    }

    // 4. Processing
    // Fix Inline Lists ({{l}})
    $full_text = str_replace('{{l}}', '<br class="commentary-line">', $full_text);

    // Fix Numbered Lists Patterns
    $full_text = preg_replace_callback('/(?:\s|^)([\d\x{0660}-\x{0669}]+[\.\-])\s+/u', function ($m) {
        return "<br><span class='list-number'>$m[1]</span> ";
    }, $full_text);

    $full_text = my_bible_split_multiple_references($full_text);
    $processed = my_bible_process_commentary_tags($full_text, $book_name, $chapter, $source_id);

    // Link Processing (Placeholders)
    $protected_links = [];
    $processed['html'] = preg_replace_callback('/<a [^>]*class=["\']smart-verse-link["\'][^>]*>.*?<\/a>/s', function ($m) use (&$protected_links) {
        $placeholder = '<!--SMART_LINK_' . count($protected_links) . '-->';
        $protected_links[$placeholder] = $m[0];
        return $placeholder;
    }, $processed['html']);

    // Fallback Regexes (V2)
    // Extended dash support: -, –, —, − (minus sign)
    $pattern1 = '/\(\s*(?:([\d\x{0660}-\x{0669}]+)\s+)?([^\d:\(\)]+(?:\s+[^\d:\(\)]+)?)\s*([\d\x{0660}-\x{0669}]+)\s*[:：]\s*([\d\x{0660}-\x{0669}]+)(?:\s*[-–—−]\s*([\d\x{0660}-\x{0669}]+))?\s*\)/u';
    $processed['html'] = preg_replace_callback($pattern1, function ($matches) {
        return my_bible_create_verse_link_v2($matches, true);
    }, $processed['html']);

    $pattern2 = '/(?<!\()(?<!>)(?:([\d\x{0660}-\x{0669}]+)\s+)?([^\d:\(\)]+(?:\s+[^\d:\(\)]+)?)\s+([\d\x{0660}-\x{0669}]+)\s*[:：]\s*([\d\x{0660}-\x{0669}]+)(?:\s*[-–]\s*([\d\x{0660}-\x{0669}]+))?(?!\))/u';
    $processed['html'] = preg_replace_callback($pattern2, function ($matches) {
        if (strpos($matches[0], 'href') !== false)
            return $matches[0];
        return my_bible_create_verse_link_v2($matches, false);
    }, $processed['html']);

    if (!empty($protected_links)) {
        $processed['html'] = str_replace(array_keys($protected_links), array_values($protected_links), $processed['html']);
    }

    $book_slug = function_exists('my_bible_create_book_slug') ? my_bible_create_book_slug($book_name) : sanitize_title($book_name);

    $result = array(
        'success' => true,
        'html' => $processed['html'],
        'toc' => $processed['toc'],
        'page_title' => isset($processed['page_title']) ? $processed['page_title'] : '',
        'book_slug' => $book_slug,
        'chapter' => $chapter
    );

    set_transient($cache_key, $result, 12 * HOUR_IN_SECONDS);
    return $result;
}

// AJAX: Get Commentary Text (Wrapper)
function my_bible_ajax_get_commentary_text()
{
    check_ajax_referer('bible_commentary_nonce', 'nonce');

    $book = isset($_POST['book']) ? sanitize_text_field($_POST['book']) : '';
    $chapter = isset($_POST['chapter']) ? intval($_POST['chapter']) : 0;
    $source = isset($_POST['source']) ? sanitize_text_field($_POST['source']) : '';

    // Map Source
    $source_code = 'af';
    if ($source == 'القمص تادرس يعقوب ملطي' || $source == 'ty')
        $source_code = 'ty';
    elseif ($source == 'القمص أنطونيوس فكري' || $source == 'af')
        $source_code = 'af';
    elseif ($source == 'كنيسة مارمرقس مصر الجديدة' || $source == 'sm')
        $source_code = 'sm';

    // Use paginated version for Lazy Loading (load first 10 rows only)
    $result = my_bible_get_commentary_content_paginated($book, $chapter, $source_code, 0, 10);

    if ($result['success']) {
        wp_send_json_success($result['data']);
    } else {
        wp_send_json_error(array('message' => $result['message']));
    }
}
add_action('wp_ajax_my_bible_get_commentary_text', 'my_bible_ajax_get_commentary_text');
add_action('wp_ajax_nopriv_my_bible_get_commentary_text', 'my_bible_ajax_get_commentary_text');

// Helper: Convert Arabic number to English
function my_bible_arabic_to_english_numbers($str)
{
    if (!$str)
        return '';
    $arabic = array('٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩');
    $english = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
    return str_replace($arabic, $english, $str);
}

// AJAX: Get Verse for Modal
function my_bible_ajax_get_verse_for_modal()
{
    global $wpdb;
    $book = isset($_POST['book']) ? sanitize_text_field($_POST['book']) : '';
    $chapter = isset($_POST['chapter']) ? intval($_POST['chapter']) : 0;
    $verse = isset($_POST['verse']) ? intval($_POST['verse']) : 0;
    $verse_end = isset($_POST['verse_end']) ? intval($_POST['verse_end']) : 0;

    // DEBUG LOGGING
    error_log("My Bible Verse Request: Book=['$book'], Chap=[$chapter], Verse=[$verse]");

    if (empty($book) || $chapter <= 0 || $verse <= 0)
        wp_send_json_error(array('message' => __('Invalid params')));

    $table_verses = $wpdb->prefix . 'bible_verses';

    // Find Book Name Logic
    $db_book_name = function_exists('my_bible_find_book_match') ? my_bible_find_book_match($book) : null;

    error_log("My Bible Verse Resolution: Input=['$book'] -> Resolved=['$db_book_name']");

    if (!$db_book_name) {
        $db_book_name = $wpdb->get_var($wpdb->prepare("SELECT DISTINCT book FROM $table_verses WHERE book = %s LIMIT 1", $book));
        error_log("My Bible Verse Fallback: Direct DB Search -> ['$db_book_name']");
    }

    $verses_html = '';
    $reference = '';

    if ($db_book_name) {
        if ($verse_end && $verse_end > $verse) {
            $verses = $wpdb->get_results($wpdb->prepare(
                "SELECT verse, text FROM $table_verses WHERE book = %s AND chapter = %d AND verse >= %d AND verse <= %d ORDER BY verse ASC",
                $db_book_name,
                $chapter,
                $verse,
                $verse_end
            ));
            if ($verses) {
                foreach ($verses as $v) {
                    $verses_html .= '<p><strong>' . $v->verse . '.</strong> ' . $v->text . '</p>';
                }
                $reference = $db_book_name . ' ' . $chapter . ':' . $verse . '-' . $verse_end;
            }
        } else {
            $verse_data = $wpdb->get_row($wpdb->prepare(
                "SELECT text, verse FROM $table_verses WHERE book = %s AND chapter = %d AND verse = %d LIMIT 1",
                $db_book_name,
                $chapter,
                $verse
            ));
            if ($verse_data) {
                $verses_html = $verse_data->text;
                $reference = $db_book_name . ' ' . $chapter . ':' . $verse_data->verse;
            }
        }
    }

    if (!$verses_html) {
        wp_send_json_error(array('message' => __('Verse not found', 'my-bible-plugin')));
    }

    $book_slug = function_exists('my_bible_create_book_slug') ? my_bible_create_book_slug($db_book_name) : sanitize_title($db_book_name);

    wp_send_json_success(array(
        'reference' => $reference,
        'text' => $verses_html,
        'book_slug' => $book_slug,
        'debug_resolved_book' => $db_book_name,
        'debug_original_search' => $book
    ));
}
add_action('wp_ajax_my_bible_get_verse_for_modal', 'my_bible_ajax_get_verse_for_modal');
add_action('wp_ajax_nopriv_my_bible_get_verse_for_modal', 'my_bible_ajax_get_verse_for_modal');

/**
 * AJAX: Get books available for a specific source with testament grouping
 */
function my_bible_ajax_get_books_by_source()
{
    check_ajax_referer('bible_commentary_nonce', 'nonce');

    global $wpdb;
    $source = isset($_POST['source']) ? sanitize_text_field($_POST['source']) : '';

    if (empty($source)) {
        wp_send_json_error(array('message' => 'Invalid source'));
        return;
    }

    $table = $wpdb->prefix . 'bible_commentaries';

    // Get distinct books for this source, ordered by ID (canonical order)
    $books = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT book_name FROM $table WHERE source_id = %s ORDER BY id ASC",
        $source
    ));

    if (empty($books)) {
        wp_send_json_error(array('message' => 'No books found for this source'));
        return;
    }

    // Classify books by testament
    $classified = my_bible_classify_books_by_testament($books);

    wp_send_json_success(array(
        'old_testament' => $classified['old'],
        'new_testament' => $classified['new']
    ));
}
add_action('wp_ajax_get_books_by_source', 'my_bible_ajax_get_books_by_source');
add_action('wp_ajax_nopriv_get_books_by_source', 'my_bible_ajax_get_books_by_source');

/**
 * Helper: Classify books by testament
 */
function my_bible_classify_books_by_testament($books)
{
    // قائمة أسفار العهد القديم (46 سفر) - بجميع الأسماء البديلة
    $old_testament_list = array(
        // أسفار موسى الخمسة
        'التكوين',
        'تكوين',
        'الخروج',
        'خروج',
        'اللاويين',
        'لاويين',
        'العدد',
        'عدد',
        'التثنية',
        'تثنية',

        // الأسفار التاريخية
        'يشوع',
        'يوشع',
        'القضاة',
        'قضاة',
        'راعوث',
        'روث',
        'صموئيل الأول',
        '1 صموئيل',
        'صموئيل 1',
        'صموئيل الاول',
        'صموئيل الثاني',
        '2 صموئيل',
        'صموئيل 2',
        'صموئيل الثاني',
        'الملوك الأول',
        '1 ملوك',
        'ملوك 1',
        'ملوك الاول',
        'الملوك الثاني',
        '2 ملوك',
        'ملوك 2',
        'ملوك الثاني',
        'أخبار الأيام الأول',
        '1 أخبار',
        'أخبار 1',
        'اخبار الايام الاول',
        'أخبار الأيام الثاني',
        '2 أخبار',
        'أخبار 2',
        'اخبار الايام الثاني',
        'عزرا',
        'نحميا',

        // الأسفار القانونية الثانية
        'طوبيا',
        'طوبيت',
        'يهوديت',
        'أستير',
        'استير',
        'المكابيين الأول',
        '1 مكابيين',
        'مكابيين 1',
        'المكابيين الثاني',
        '2 مكابيين',
        'مكابيين 2',

        // أسفار الحكمة والشعر
        'أيوب',
        'المزامير',
        'مزامير',
        'الأمثال',
        'امثال',
        'الجامعة',
        'جامعة',
        'نشيد الأنشاد',
        'نشيد',
        'الحكمة',
        'حكمة سليمان',
        'يشوع بن سيراخ',
        'ابن سيراخ',
        'سيراخ',

        // الأنبياء الكبار
        'إشعياء',
        'اشعياء',
        'إشعيا',
        'اشعيا',
        'إرميا',
        'ارميا',
        'إرمياء',
        'ارمياء',
        'مراثي إرميا',
        'مراثي',
        'مراثي ارميا',
        'باروخ',
        'حزقيال',
        'حزقيل',
        'دانيال',
        'دانيال النبي',

        // الأنبياء الصغار
        'هوشع',
        'يوئيل',
        'عاموس',
        'عوبديا',
        'يونان',
        'ميخا',
        'ناحوم',
        'حبقوق',
        'صفنيا',
        'حجي',
        'زكريا',
        'ملاخي'
    );

    $old = array();
    $new = array();

    foreach ($books as $book) {
        $is_old = false;
        $book_lower = mb_strtolower($book);

        // Check if book is in Old Testament list
        foreach ($old_testament_list as $old_book) {
            $old_book_lower = mb_strtolower($old_book);
            // Check if they match or contain each other
            if (
                $book_lower === $old_book_lower ||
                mb_strpos($book_lower, $old_book_lower) !== false ||
                mb_strpos($old_book_lower, $book_lower) !== false
            ) {
                $is_old = true;
                break;
            }
        }

        if ($is_old) {
            $old[] = $book;
        } else {
            $new[] = $book;
        }
    }

    return array('old' => $old, 'new' => $new);
}

/**
 * AJAX: Get commentary text with pagination (Lazy Loading)
 */
function my_bible_ajax_get_commentary_text_paginated()
{
    check_ajax_referer('bible_commentary_nonce', 'nonce');

    global $wpdb;
    $book = isset($_POST['book']) ? sanitize_text_field($_POST['book']) : '';
    $chapter = isset($_POST['chapter']) ? intval($_POST['chapter']) : 0;
    $source = isset($_POST['source']) ? sanitize_text_field($_POST['source']) : '';
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;

    if (empty($book) || empty($chapter) || empty($source)) {
        wp_send_json_error(array('message' => 'Missing parameters'));
        return;
    }

    // Get paginated commentary content
    $result = my_bible_get_commentary_content_paginated($book, $chapter, $source, $offset, $limit);

    if ($result['success']) {
        wp_send_json_success($result['data']);
    } else {
        wp_send_json_error(array('message' => $result['message']));
    }
}
add_action('wp_ajax_get_commentary_text_paginated', 'my_bible_ajax_get_commentary_text_paginated');
add_action('wp_ajax_nopriv_get_commentary_text_paginated', 'my_bible_ajax_get_commentary_text_paginated');

/**
 * Get commentary content with pagination support
 */
function my_bible_get_commentary_content_paginated($book_name, $chapter, $source_id, $offset = 0, $limit = 10)
{
    global $wpdb;

    // Convert book name if needed
    $book_name_query = my_bible_convert_to_commentary_book_name($book_name);

    $table_commentaries = $wpdb->prefix . 'bible_commentaries';

    // Get total count first
    $total_query = $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_commentaries 
         WHERE book_name = %s AND chapter = %d AND source_id = %s",
        $book_name_query,
        $chapter,
        $source_id
    );
    $total_rows = $wpdb->get_var($total_query);

    if ($total_rows === null || $total_rows == 0) {
        return array(
            'success' => false,
            'message' => 'No content found'
        );
    }

    // Get paginated rows
    $query = $wpdb->prepare(
        "SELECT * FROM $table_commentaries 
         WHERE book_name = %s AND chapter = %d AND source_id = %s 
         ORDER BY id ASC 
         LIMIT %d OFFSET %d",
        $book_name_query,
        $chapter,
        $source_id,
        $limit,
        $offset
    );

    $rows = $wpdb->get_results($query);

    if (empty($rows)) {
        return array(
            'success' => false,
            'message' => 'No more content'
        );
    }

    // Process the rows (similar to original function but simplified)
    $full_text = '';
    foreach ($rows as $row) {
        $full_text .= $row->text . "\n\n";
    }

    // Basic processing
    $full_text = str_replace('{{l}}', '<br class="commentary-line">', $full_text);
    $full_text = my_bible_split_multiple_references($full_text);
    $processed = my_bible_process_commentary_tags($full_text, $book_name, $chapter, $source_id);

    // Calculate progress
    $loaded_count = $offset + count($rows);
    $has_more = $loaded_count < $total_rows;
    $progress_percent = round(($loaded_count / $total_rows) * 100);

    return array(
        'success' => true,
        'data' => array(
            'html' => $processed['html'],
            'toc' => $processed['toc'],
            'has_more' => $has_more,
            'total_rows' => intval($total_rows),
            'loaded_count' => $loaded_count,
            'progress_percent' => $progress_percent,
            'page_title' => $processed['page_title']
        )
    );
}

/**
 * AJAX: Convert commentary book name to bible book name
 */
function my_bible_ajax_convert_commentary_book_name()
{
    check_ajax_referer('bible_commentary_nonce', 'nonce');

    $book = isset($_POST['book']) ? sanitize_text_field($_POST['book']) : '';

    if (empty($book)) {
        wp_send_json_error(array('message' => 'Missing book parameter'));
        return;
    }

    // Convert from commentary format to bible format
    $bible_book_name = my_bible_convert_from_commentary_book_name($book);

    wp_send_json_success(array(
        'bible_book_name' => $bible_book_name,
        'commentary_book_name' => $book
    ));
}
add_action('wp_ajax_convert_commentary_book_name', 'my_bible_ajax_convert_commentary_book_name');
add_action('wp_ajax_nopriv_convert_commentary_book_name', 'my_bible_ajax_convert_commentary_book_name');

/**
 * AJAX: Convert bible book name to commentary book name
 */
function my_bible_ajax_convert_bible_to_commentary_book_name()
{
    check_ajax_referer('bible_ajax_nonce', 'nonce');

    $book = isset($_POST['book']) ? sanitize_text_field($_POST['book']) : '';

    if (empty($book)) {
        wp_send_json_error(array('message' => 'Missing book parameter'));
        return;
    }

    // Convert from bible format to commentary format
    $commentary_book_name = my_bible_convert_to_commentary_book_name($book);

    wp_send_json_success(array(
        'commentary_book_name' => $commentary_book_name,
        'bible_book_name' => $book
    ));
}
add_action('wp_ajax_convert_bible_to_commentary_book_name', 'my_bible_ajax_convert_bible_to_commentary_book_name');
add_action('wp_ajax_nopriv_convert_bible_to_commentary_book_name', 'my_bible_ajax_convert_bible_to_commentary_book_name');

