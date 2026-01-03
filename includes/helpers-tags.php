<?php

if (!function_exists('my_bible_parse_commentary_tags')) {
    /**
     * Parse commentary text and handle all custom tags
     * 
     * Supported Tags:
     * - {{t}}Title{{/t}} -> <h2 class="commentary-title">
     * - {{b}}Subtitle{{/b}} -> <h3 class="commentary-subtitle"> OR <span class="verse-prefix"> if matches verse pattern
     * - {{vt}}Verse Text{{/vt}} -> <div class="commentary-verse-text">
     * - {{p}}Paragraph{{/p}} -> <p class="commentary-p">
     * - {{d}}Divider{{/d}} -> <hr class="commentary-divider">
     * - {{g}}Grid{{/g}} -> <div class="commentary-grid">
     * - {{gr}}Row{{/gr}} -> <div class="commentary-row">
     * - {{gc}}Col{{/gc}} -> <div class="commentary-col">
     * - {{ref...}} -> <a href="..." class="scripture-ref">
     */
    function my_bible_parse_commentary_tags($text)
    {
        // 1. Titles
        $text = preg_replace('/\{\{t\}\}(.*?)\{\{\/t\}\}/s', '<h2 class="commentary-title">$1</h2>', $text);

        // 2. Subtitles (Bold) - Note: Special handling for verse prefixes is often done in specific contexts, 
        // but generally {{b}} is a subtitle.
        // We need to preserve the logic where "أية (X):-" inside {{b}} might need special handling if not inside {{vt}}.
        // However, standardizing:
        $text = preg_replace('/\{\{b\}\}(.*?)\{\{\/b\}\}/s', '<h3 class="commentary-subtitle">$1</h3>', $text);

        // 3. Verse Boxes (vt tags)
        // Note: The complex regex for verses usually resides in the caller to handle specific prefix logic 
        // but we can standardize simple cases.
        // For full compatibility with the regex fixes we made in ajax-commentary.php, we should probably 
        // keep the verse box logic there OR migrate it here carefully.
        // For now, let's process the structural tags first.

        // 4. Paragraphs
        // Replaces {{p}} with closing previous paragraph (if any implied) and starting new? 
        // Or simply <p class="commentary-p">. Since text might not start with p, 
        // usually {{p}} implies a break. Let's try converting to block paragraphs.
        // Analysis shows {{p}} behaves like a paragraph break / separator.
        $text = str_replace('{{p}}', '<p class="commentary-p">', $text);
        // We might want to close them? HTML5 parsers often auto-close <p>. 
        // But better style might be: <div class="commentary-text-block">...</div>
        // Let's stick to simple replacement for now, equivalent to <br><br> but styled.
        // Actually, often it's used as a splitter.

        // 5. Dividers
        $text = str_replace('{{d}}', '<hr class="commentary-divider">', $text);

        // 6. Grid System
        $text = str_replace('{{g}}', '<div class="commentary-grid">', $text);
        $text = str_replace('{{/g}}', '</div>', $text);
        $text = str_replace('{{gr}}', '<div class="commentary-row">', $text);
        $text = str_replace('{{/gr}}', '</div>', $text);
        $text = str_replace('{{gc}}', '<div class="commentary-col">', $text);
        $text = str_replace('{{/gc}}', '</div>', $text);

        // 7. Line Breaks
        $text = str_replace('{{l}}', '<br class="line-break">', $text);

        // 8. Scripture References
        // Pattern: {{ref_TYPE_BOOK_CHAPTER_...}}Text{{/ref}}
        // We capture the whole tag and replace with link.
        $text = preg_replace_callback('/\{\{ref_([a-z]+)_([\d_]+)\}\}(.*?)\{\{\/ref\}\}/', function ($matches) {
            $type = $matches[1]; // s (single), r (range), etc.
            $coords = $matches[2]; // e.g. 24_2_4
            $link_text = $matches[3];

            // Generate a data attribute for JS to handle
            return sprintf(
                '<a href="javascript:void(0);" class="scripture-ref" data-ref-type="%s" data-ref-coords="%s">%s</a>',
                $type,
                $coords,
                $link_text
            );
        }, $text);

        return $text;
    }
}
