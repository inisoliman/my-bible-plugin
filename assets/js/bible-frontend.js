jQuery(document).ready(function($) {
    // Bible frontend JavaScript
    console.log('My Bible Plugin frontend JS loaded.');

    // --- Go to Verse Feature ---
    if ($('.bible-selection-controls').length) {
        var goToVerseHtml = '<div class="go-to-verse-container" style="margin-top: 10px; margin-bottom:10px; display:flex; gap:5px;">';
        goToVerseHtml += '<input type="text" id="go-to-verse-input" placeholder="مثال: يوحنا 3:16" style="flex-grow:1; padding:5px;" />';
        goToVerseHtml += '<button id="go-to-verse-button" class="bible-control-button" style="padding:5px 10px;">اذهب</button>';
        goToVerseHtml += '</div>';
        // Prepend to bible-selection-controls, which should exist if book/chapter dropdowns are there.
        // If bible-content-area is the main container for the shortcode, it's also a candidate.
        // Let's target where the book/chapter dropdowns are usually placed.
        if ($('#bible-book-select').length && $('#bible-chapter-select').length) {
             $('.bible-selection-controls').first().prepend(goToVerseHtml);
        } else if ($('.bible-content-area').length) {
            // Fallback: if specific controls not found, try to add it to the main content area or a prominent part.
            $('.bible-content-area').first().prepend(goToVerseHtml);
        }


        $('#go-to-verse-button').on('click', function() {
            var reference = $('#go-to-verse-input').val().trim();
            if (!reference) {
                alert('الرجاء إدخال مرجع آية (مثال: يوحنا 3:16).');
                return;
            }

            // Regex to parse "Book Chapter:Verse" or "Book Chapter"
            // Assumes book name does not contain numbers and is followed by chapter.
            const match = reference.match(/^([^0-9]+)\s*([0-9]+)(?:\s*[:.]\s*([0-9]+))?$/);

            if (match) {
                let bookName = match[1].trim();
                let chapterNum = parseInt(match[2], 10);
                let verseNum = match[3] ? parseInt(match[3], 10) : null;

                if (isNaN(chapterNum) || chapterNum <= 0) {
                    alert('رقم الأصحاح غير صالح.');
                    return;
                }
                if (verseNum !== null && (isNaN(verseNum) || verseNum <= 0)) {
                    alert('رقم الآية غير صالح.');
                    return;
                }

                // Simple slugification: lowercase and replace spaces/multiple hyphens with a single hyphen.
                // This is a basic version and might need to be more robust to match PHP's my_bible_create_book_slug.
                let bookSlug = bookName.toLowerCase()
                                     .replace(/\s+/g, '-') // Replace spaces with hyphens
                                     .replace(/-+/g, '-'); // Replace multiple hyphens with single

                // Ensure bibleFrontend.base_url is available. If not, this will cause an error.
                // It's expected to be set by wp_localize_script.
                if (typeof bibleFrontend === 'undefined' || typeof bibleFrontend.base_url === 'undefined') {
                    alert('خطأ: إعدادات الإضافة غير متاحة (base_url).');
                    console.error('bibleFrontend or bibleFrontend.base_url is undefined.');
                    return;
                }
                
                let newUrl = bibleFrontend.base_url + bookSlug + '/' + chapterNum + '/';
                if (verseNum) {
                    newUrl += verseNum + '/';
                }

                console.log('Navigating to: ' + newUrl);
                window.location.href = newUrl;

            } else {
                alert('صيغة المرجع غير صحيحة. استخدم "السفر الاصحاح:الآية" أو "السفر الاصحاح". مثال: يوحنا 3:16');
            }
        });
    } else {
        console.warn('.bible-selection-controls not found. "Go to Verse" feature not added.');
    }
    // Event listeners and other functions will be added here.

    // --- Client-Side Bookmarking Feature ---
    const BOOKMARKS_LS_KEY = 'myBiblePlugin_bookmarks';

    // Load bookmarks from localStorage
    function loadBookmarks() {
        const bookmarksJson = localStorage.getItem(BOOKMARKS_LS_KEY);
        try {
            return bookmarksJson ? JSON.parse(bookmarksJson) : [];
        } catch (e) {
            console.error("Error parsing bookmarks from localStorage:", e);
            return [];
        }
    }

    // Save bookmarks to localStorage
    function saveBookmarks(bookmarksArray) {
        localStorage.setItem(BOOKMARKS_LS_KEY, JSON.stringify(bookmarksArray));
    }

    // Check if a verse is bookmarked
    // Uses bookSlug for comparison as canonical book name might be tricky on JS side initially
    function isBookmarked(bookSlug, chapter, verse) {
        const bookmarks = loadBookmarks();
        return bookmarks.some(function(bm) {
            // Normalize verse to number for comparison, as it might be stored as string from data attributes
            return bm.bookSlug === bookSlug && 
                   parseInt(bm.chapter, 10) === parseInt(chapter, 10) && 
                   parseInt(bm.verse, 10) === parseInt(verse, 10);
        });
    }

    // Add a bookmark
    function addBookmark(bookmark) {
        const bookmarks = loadBookmarks();
        if (!isBookmarked(bookmark.bookSlug, bookmark.chapter, bookmark.verse)) {
            bookmarks.push(bookmark);
            saveBookmarks(bookmarks);
            console.log('Bookmark added:', bookmark);
        } else {
            console.log('Bookmark already exists:', bookmark);
        }
    }

    // Remove a bookmark
    function removeBookmark(bookSlug, chapter, verse) {
        let bookmarks = loadBookmarks();
        const initialLength = bookmarks.length;
        bookmarks = bookmarks.filter(function(bm) {
            return !(bm.bookSlug === bookSlug && 
                     parseInt(bm.chapter, 10) === parseInt(chapter, 10) && 
                     parseInt(bm.verse, 10) === parseInt(verse, 10));
        });
        if (bookmarks.length < initialLength) {
            saveBookmarks(bookmarks);
            console.log('Bookmark removed:', { bookSlug: bookSlug, chapter: chapter, verse: verse });
        } else {
            console.log('Bookmark not found for removal:', { bookSlug: bookSlug, chapter: chapter, verse: verse });
        }
    }

    // Helper function to parse verse URL (e.g., /bible/genesis/1/1/)
    // Returns an object { bookSlug, chapter, verse } or null
    function parseVerseUrl(url) {
        if (!url) return null;
        // Example URL: /bible/genesis/1/1/ or /bible/1-samuel/10/5/
        // The base_url might be tricky if it's not just '/' or includes subdirectories.
        // Assuming bibleFrontend.base_url ends with a slash.
        let pathAfterBase = url;
        if (typeof bibleFrontend !== 'undefined' && typeof bibleFrontend.base_url !== 'undefined') {
             if (url.startsWith(bibleFrontend.base_url)) {
                pathAfterBase = url.substring(bibleFrontend.base_url.length);
            } else if (url.startsWith('/')) { // Fallback for relative URLs if base_url is complex
                 pathAfterBase = url.substring(1); 
                 if (pathAfterBase.startsWith(bibleFrontend.base_url.substring(1))) { // if base_url was /something/
                    pathAfterBase = pathAfterBase.substring(bibleFrontend.base_url.length -1);
                 }
            }
        } else { // No bibleFrontend.base_url defined
            const parts = url.split('/');
            // Attempt to find 'bible' segment and take parts after it.
            const bibleIndex = parts.indexOf('bible');
            if (bibleIndex !== -1 && parts.length > bibleIndex + 2) {
                 pathAfterBase = parts.slice(bibleIndex + 1).join('/');
            } else { // very basic fallback
                 pathAfterBase = url.replace(/^\/(bible\/)?/, '');
            }
        }
       
        const parts = pathAfterBase.split('/').filter(function(p) { return p.length > 0; }); // Filter empty parts

        if (parts.length >= 2) {
            const bookSlug = parts[0];
            const chapter = parseInt(parts[1], 10);
            const verse = parts.length >= 3 ? parseInt(parts[2], 10) : null;

            if (bookSlug && !isNaN(chapter) && chapter > 0) {
                if (verse !== null && (isNaN(verse) || verse <= 0)) return null; // Invalid verse
                return { bookSlug: bookSlug, chapter: chapter, verse: verse };
            }
        }
        console.warn('Could not parse verse URL:', url, 'Derived path:', pathAfterBase);
        return null;
    }

    // Function to inject or update bookmark icons
    function injectOrUpdateBookmarkIcons() {
        console.log('Attempting to inject/update bookmark icons...');
        $('p.verse-text').each(function() {
            const $verseP = $(this);
            // Try to remove any existing bookmark icon container to prevent duplicates
            $verseP.find('.bookmark-icon-container').remove();

            const verseUrl = $verseP.data('verse-url');
            const originalText = $verseP.data('original-text');
            const textPreview = originalText ? (originalText.substring(0, 70) + (originalText.length > 70 ? '...' : '')) : '...';
            
            const parsedRef = parseVerseUrl(verseUrl);

            if (parsedRef) {
                // Use bookSlug from parsed URL, chapter, and verse.
                // For book name in bookmark object, ideally, we'd get it from page, but slug is fine for isBookmarked logic.
                // The actual book name for display can be fetched from the reference link if needed when displaying bookmarks.
                const bookmarked = isBookmarked(parsedRef.bookSlug, parsedRef.chapter, parsedRef.verse);
                const iconClass = bookmarked ? 'fas fa-bookmark' : 'far fa-bookmark'; // Using fa-bookmark
                
                // Store all necessary data on the icon container
                const $iconContainer = $('<span class="bookmark-icon-container" style="margin-right: 5px; cursor:pointer;"></span>')
                    .data('book-slug', parsedRef.bookSlug)
                    .data('chapter', parsedRef.chapter)
                    .data('verse', parsedRef.verse)
                    .data('text-preview', textPreview) 
                    // Store the displayable book name from the reference link if possible
                    .data('book-name', $verseP.find('.verse-reference-link').text().replace(/\[|\]/g, '').replace(/[:0-9]+$/, '').trim() || parsedRef.bookSlug);

                const $icon = $('<i class="bookmark-icon"></i>').addClass(iconClass);
                $iconContainer.append($icon);
                $verseP.prepend($iconContainer);
            } else {
                console.warn('Could not parse verse details for icon injection:', verseUrl);
            }
        });
        console.log('Finished injecting/updating bookmark icons.');
    }
    
    // Delegated event handler for bookmark icons
    // Attach to a static parent, or document if verses are loaded into bible-verses-content dynamically
    $(document).on('click', '.bookmark-icon-container', function(e) {
        e.preventDefault(); // Prevent any default link behavior if icon is inside an <a>
        e.stopPropagation(); // Stop event from bubbling up to verse link

        const $iconContainer = $(this);
        const bookSlug = $iconContainer.data('book-slug');
        const chapter = $iconContainer.data('chapter');
        const verse = $iconContainer.data('verse');
        const textPreview = $iconContainer.data('text-preview');
        const bookName = $iconContainer.data('book-name') || bookSlug; // Fallback to slug

        if (!bookSlug || !chapter || !verse) {
            console.error('Bookmark click: Missing data attributes on icon container.');
            return;
        }

        const $icon = $iconContainer.find('.bookmark-icon');

        if (isBookmarked(bookSlug, chapter, verse)) {
            removeBookmark(bookSlug, chapter, verse);
            $icon.removeClass('fas fa-bookmark').addClass('far fa-bookmark');
            console.log('Verse unbookmarked via icon click.');
        } else {
            addBookmark({ 
                bookName: bookName, // Store the displayable book name
                bookSlug: bookSlug, // Store slug for unique identification logic
                chapter: chapter, 
                verse: verse, 
                text: textPreview,
                url: bibleFrontend.base_url + bookSlug + '/' + chapter + '/' + verse + '/' // Store full URL for navigation
            });
            $icon.removeClass('far fa-bookmark').addClass('fas fa-bookmark');
            console.log('Verse bookmarked via icon click.');
        }
    });

    // Initial call to add icons to any server-rendered verses
    injectOrUpdateBookmarkIcons();

    // Simple "View Bookmarks" button (for now, logs to console)
    if ($('.bible-selection-controls').length) {
        var viewBookmarksButton = '<button id="view-bookmarks-button" class="bible-control-button" style="margin-left: 5px; padding:5px 10px;">عرض العلامات</button>';
        $('#go-to-verse-button').after(viewBookmarksButton); // Place it after the "Go to Verse" button

        $('#view-bookmarks-button').on('click', function() {
            const bookmarks = loadBookmarks();
            if (bookmarks.length === 0) {
                alert('ليس لديك أي آيات محفوظة في العلامات المرجعية.');
                console.log('No bookmarks to display.');
                return;
            }
            console.log('--- My Bookmarks ---');
            bookmarks.forEach(function(bm, index) {
                console.log((index + 1) + '. ' + bm.bookName + ' ' + bm.chapter + ':' + bm.verse + ' - "' + bm.text + '" (URL: ' + bm.url + ')');
            });
            // For now, just an alert. A proper display would be in a modal or a dedicated section.
            alert('تم عرض العلامات المرجعية في وحدة التحكم (Console).');
        });
    }

    // TODO: Need to ensure injectOrUpdateBookmarkIcons() is called if verses are loaded via AJAX.
    // This might involve listening to custom events or modifying AJAX success handlers if they exist.
    // For example, if an AJAX call populates #bible-verses-display:
    // $(document).ajaxComplete(function(event, xhr, settings) {
    //    if (settings.url.includes('get_verses_action')) { // Fictional AJAX action
    //        injectOrUpdateBookmarkIcons();
    //    }
    // });

    // --- Client-Side Reading History Feature ---
    const HISTORY_LS_KEY = 'myBiblePlugin_readingHistory';
    const MAX_HISTORY_ITEMS = 15; // As per suggestion, can be 10 or 15

    // Load reading history from localStorage
    function loadReadingHistory() {
        const historyJson = localStorage.getItem(HISTORY_LS_KEY);
        try {
            return historyJson ? JSON.parse(historyJson) : [];
        } catch (e) {
            console.error("Error parsing reading history from localStorage:", e);
            return [];
        }
    }

    // Save reading history to localStorage
    function saveReadingHistory(historyArray) {
        localStorage.setItem(HISTORY_LS_KEY, JSON.stringify(historyArray));
    }

    // Add an entry to the reading history
    // Entry: { bookName, bookSlug, chapter, verse (optional), url, textPreview, timestamp }
    function addReadingHistoryEntry(newEntry) {
        if (!newEntry || !newEntry.bookSlug || !newEntry.chapter || !newEntry.url) {
            console.warn('Attempted to add invalid history entry:', newEntry);
            return;
        }
        
        let history = loadReadingHistory();

        // Prevent duplicate consecutive entries
        if (history.length > 0) {
            const latestEntry = history[0];
            if (latestEntry.bookSlug === newEntry.bookSlug &&
                parseInt(latestEntry.chapter, 10) === parseInt(newEntry.chapter, 10) &&
                (parseInt(latestEntry.verse, 10) || null) === (parseInt(newEntry.verse, 10) || null) // Compare verse, handling null/undefined
            ) {
                console.log('Skipping duplicate consecutive history entry:', newEntry);
                // Optionally, update timestamp of existing entry if desired, but for now, just skip.
                // latestEntry.timestamp = newEntry.timestamp;
                // saveReadingHistory(history);
                return;
            }
        }

        // Add new entry to the beginning
        history.unshift(newEntry);

        // Limit history size
        if (history.length > MAX_HISTORY_ITEMS) {
            history = history.slice(0, MAX_HISTORY_ITEMS);
        }

        saveReadingHistory(history);
        console.log('Reading history entry added:', newEntry);
    }

    // Function to log current page on load or after navigation
    function logCurrentPageAsHistory() {
        console.log('Attempting to log current page to history...');
        let bookName, bookSlug, chapter, verse, textPreview = 'Viewed passage'; // Default preview

        // Option 1: Use localized script data if available (preferred)
        if (typeof bibleFrontend !== 'undefined' && 
            bibleFrontend.currentBookSlug && 
            bibleFrontend.currentChapter) {
            
            bookSlug = bibleFrontend.currentBookSlug;
            // Try to get the localized book name if available, otherwise fallback to slug
            bookName = bibleFrontend.currentBookName || bookSlug.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            chapter = parseInt(bibleFrontend.currentChapter, 10);
            verse = bibleFrontend.currentVerse ? parseInt(bibleFrontend.currentVerse, 10) : null;
            
            // For textPreview, if on a specific verse page, try to get its text.
            // This assumes the verse text is available in a specific element when a single verse is loaded.
            // This part is speculative as the HTML structure for single verse isn't fully defined here.
            if (verse && $('p.verse-text[data-verse-url*="/' + chapter + '/' + verse + '/"]').length) {
                const verseTextContent = $('p.verse-text[data-verse-url*="/' + chapter + '/' + verse + '/"] .text-content').first().text();
                if (verseTextContent) {
                    textPreview = verseTextContent.substring(0, 70) + (verseTextContent.length > 70 ? '...' : '');
                } else {
                     textPreview = 'Viewed ' + bookName + ' ' + chapter + ':' + verse;
                }
            } else if (chapter) { // For chapter view
                 // Try to get the first verse text as preview
                const firstVerseP = $('#verses-content p.verse-text').first();
                if (firstVerseP.length) {
                    const firstVerseText = firstVerseP.find('.text-content').text();
                     textPreview = firstVerseText ? (firstVerseText.substring(0, 70) + (firstVerseText.length > 70 ? '...' : '')) : ('Chapter ' + chapter);
                } else {
                    textPreview = 'Viewed ' + bookName + ' ' + chapter;
                }
            }
            console.log('Logging from localized data:', { bookName, bookSlug, chapter, verse });

        } else {
            // Option 2: Fallback to parsing URL if localized data is not available
            const parsedUrl = parseVerseUrl(window.location.href);
            if (parsedUrl && parsedUrl.bookSlug && parsedUrl.chapter) {
                bookSlug = parsedUrl.bookSlug;
                // Best effort for bookName from slug
                bookName = bookSlug.replace(/-/g, ' ').replace(/\b\w/g, function(l){ return l.toUpperCase(); });
                chapter = parsedUrl.chapter;
                verse = parsedUrl.verse || null;
                
                if (verse) {
                    textPreview = 'Viewed ' + bookName + ' ' + chapter + ':' + verse;
                } else {
                    textPreview = 'Viewed ' + bookName + ' ' + chapter;
                }
                console.log('Logging from URL parse:', { bookName, bookSlug, chapter, verse });
            } else {
                console.log('Could not determine current page details for history.');
                return; // Cannot determine page details
            }
        }

        if (bookSlug && chapter) { // Ensure essential parts are present
            const entry = {
                bookName: bookName,
                bookSlug: bookSlug,
                chapter: chapter,
                verse: verse, // Can be null
                url: window.location.href,
                textPreview: textPreview,
                timestamp: new Date().getTime()
            };
            addReadingHistoryEntry(entry);
        }
    }
    
    // Call on page load
    logCurrentPageAsHistory();

    // --- AJAX Integration for Reading History ---
    // We need to tap into the AJAX success handler for chapter/verse loading.
    // Since the original AJAX call setup might be outside this specific file or part of a larger framework,
    // we use a global ajaxComplete handler. This is broad but ensures we catch it.
    // A more specific event or callback from the main AJAX function would be cleaner if available.
    // if (response.success && response.data && response.data.html) {
    //     $versesDisplay.html(response.data.html).scrollTop(0);
    //     injectOrUpdateBookmarkIcons(); // Keep this if it's still relevant
    //     // Now add reading history entry
    //     const firstVerseText = $versesDisplay.find('p.verse-text .text-content').first().text();
    //     const textPreview = firstVerseText ? (firstVerseText.substring(0, 50) + (firstVerseText.length > 50 ? '...' : '')) : 'Chapter loaded';
    //     const bookName = $('#bible-book-select option:selected').text(); // Or from response if available
    //     const bookSlug = $('#bible-book-select').val(); // Assuming value is slug, or parse from bookName
    //     const chapter = parseInt($('#bible-chapter-select').val(), 10);
    //     
    //     if (bookSlug && chapter) {
    //        const chapterUrl = bibleFrontend.base_url + bookSlug + '/' + chapter + '/';
    //        addReadingHistoryEntry({
    //            bookName: bookName,
    //            bookSlug: bookSlug, // This needs to be a slug
    //            chapter: chapter,
    //            verse: null, // This is a chapter view
    //            url: chapterUrl,
    //            textPreview: textPreview,
    //            timestamp: new Date().getTime()
    //        });
    //    }
    // }

    $(document).ajaxComplete(function(event, xhr, settings) {
        // Check if the AJAX request is likely the one that loads Bible content.
        // This requires knowing the specific AJAX action or a characteristic of its URL/settings.
        // Let's assume 'action=get_bible_chapter' or similar is part of the data or URL for chapter loading.
        // This is a common pattern in WordPress.
        // We also check if #bible-verses-display was the target of the update.
        if (settings.data && (settings.data.includes('action=get_bible_chapter') || settings.data.includes('action=mbp_load_chapter')) || // Check common actions
            (settings.url && settings.url.includes('admin-ajax.php')) // General check for WordPress AJAX
           ) { 
            
            // A brief delay to ensure DOM is updated.
            setTimeout(function() {
                if ($('#bible-verses-display').length && $('#bible-verses-display').html().length > 0) {
                    console.log('AJAX complete, attempting to log history for newly loaded content.');
                    
                    // Try to get the book name and chapter from the dropdowns as they should be selected
                    const $bookSelect = $('#bible-book-select');
                    const $chapterSelect = $('#bible-chapter-select');
                    
                    let bookName = $bookSelect.find('option:selected').text();
                    let bookSlug = $bookSelect.val(); // Assuming the value is the slug
                    let chapter = parseInt($chapterSelect.val(), 10);
                    
                    if (!bookSlug && bookName) { // Fallback if value is not slug but text is name
                        bookSlug = bookName.toLowerCase().replace(/\s+/g, '-').replace(/-+/g, '-');
                    }
                    if (bookSlug && !bookName) { // Fallback if slug is there but text is not (e.g. if select is rebuilt weirdly)
                         bookName = bookSlug.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    }

                    if (bookSlug && chapter) {
                        const firstVerseP = $('#verses-content p.verse-text').first();
                        let textPreview = 'Viewed ' + bookName + ' ' + chapter;
                        if (firstVerseP.length) {
                            const firstVerseText = firstVerseP.find('.text-content').text();
                            if (firstVerseText) {
                                textPreview = firstVerseText.substring(0, 70) + (firstVerseText.length > 70 ? '...' : '');
                            }
                        }
                        
                        const currentUrl = bibleFrontend.base_url + bookSlug + '/' + chapter + '/'; // Construct URL for chapter view

                        addReadingHistoryEntry({
                            bookName: bookName,
                            bookSlug: bookSlug,
                            chapter: chapter,
                            verse: null, // AJAX load is typically for a whole chapter
                            url: currentUrl,
                            textPreview: textPreview,
                            timestamp: new Date().getTime()
                        });
                         injectOrUpdateBookmarkIcons(); // Also refresh bookmark icons for new content
                    } else {
                        console.warn('Could not determine book/chapter from dropdowns after AJAX.');
                    }
                }
            }, 200); // 200ms delay
        }
    });

    // Function to setup the "View History" button and its click handler
    function setupViewHistoryButton() {
        if ($('.bible-selection-controls').length) {
            var viewHistoryButton = '<button id="view-reading-history-button" class="bible-control-button" style="margin-left: 5px; padding:5px 10px;">عرض سجل القراءة</button>';
            const $appendAfter = $('#view-bookmarks-button').length ? $('#view-bookmarks-button') : $('#go-to-verse-button');
            
            if ($appendAfter.length) {
                 $appendAfter.after(viewHistoryButton);
            } else { 
                $('.bible-selection-controls').first().append(viewHistoryButton); // Fallback
            }

            $('#view-reading-history-button').on('click', function() {
                const history = loadReadingHistory();
                if (history.length === 0) {
                    alert('لا يوجد سجل قراءة حتى الآن.');
                    return;
                }
                
                let historyDisplayContent = 'سجل القراءة:\n\n';
                history.forEach(function(entry, index) {
                    const refText = entry.bookName + ' ' + entry.chapter + (entry.verse ? ':' + entry.verse : '');
                    const dateString = new Date(entry.timestamp).toLocaleString('ar-EG-u-nu-latn', { dateStyle: 'short', timeStyle: 'short'}); // Example Arabic locale
                    historyDisplayContent += (index + 1) + '. ' + refText + '\n';
                    historyDisplayContent += '  "' + (entry.textPreview || '...') + '"\n';
                    historyDisplayContent += '  (' + dateString + ')\n';
                    historyDisplayContent += '  الرابط: ' + entry.url + '\n\n';
                });
                
                // For simplicity, using alert. A modal would be better for actual links.
                alert(historyDisplayContent);
                
                console.log("--- Reading History (for clickable links) ---");
                history.forEach(function(entry) {
                    console.log(entry.bookName + ' ' + entry.chapter + (entry.verse ? ':' + entry.verse : '') + ' - "' + entry.textPreview + '" @ ' + new Date(entry.timestamp).toLocaleString() + ' URL: ' + entry.url);
                });
            });
        } else {
            console.warn('.bible-selection-controls not found. "View History" button not added.');
        }
    }
    // Call setup function for the history button
    setupViewHistoryButton(); 
});
