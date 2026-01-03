jQuery(document).ready(function ($) {

    // Elements
    const $sourceSelect = $('#commentary-source');
    const $bookSelect = $('#commentary-book');
    const $chapterSelect = $('#commentary-chapter');
    const $btnLoad = $('#btn-load-commentary');
    const $contentArea = $('#commentary-content-area');
    const $tocList = $('#commentary-toc-list');
    const $sidebar = $('#commentary-sidebar');
    const $verseModal = $('#verse-modal');
    
    // Lazy Loading variables
    let currentOffset = 0;
    let isLoading = false;
    let hasMoreContent = true;
    let currentBook = '';
    let currentChapter = 0;
    let currentSource = '';
    const ROWS_PER_LOAD = 10; // عدد الصفوف في كل تحميل

    // Helper function to normalize Arabic text
    function normalizeArabic(text) {
        if (!text) return '';
        let normalized = text
            .replace(/[أإآٱ]/g, 'ا')
            .replace(/ؤ/g, 'و')
            .replace(/ئ/g, 'ي')
            .replace(/ة/g, 'ه')
            .replace(/ى/g, 'ي')
            .replace(/\s+/g, ' ')
            .trim();
        
        // Strip leading 'AL' (ال) for fuzzy matching books
        // Check if starts with 'ال' and follow with non-space
        if (normalized.startsWith('ال')) {
             normalized = normalized.substring(2);
        }
        return normalized;
    }

    // Helper function to load chapters (used by both auto-load and manual selection)
    function loadChapters(book, source, callback) {
        $chapterSelect.html('<option value="">جارٍ التحميل...</option>');
        $chapterSelect.prop('disabled', true);
        $btnLoad.prop('disabled', true);

        if (book && source) {
            $.post(bibleCommentary.ajax_url, {
                action: 'my_bible_get_commentary_chapters',
                nonce: bibleCommentary.nonce,
                book: book,
                source: source
            }, function (response) {
                if (response.success) {
                    let options = '<option value="">اختر الأصحاح</option>';
                    response.data.chapters.forEach(function (chap) {
                        options += `<option value="${chap}">${chap}</option>`;
                    });
                    $chapterSelect.html(options).prop('disabled', false);
                    if (callback) callback(true);
                } else {
                    const errorMsg = response.data && response.data.message ? response.data.message : 'لا توجد أصحاحات';
                    $chapterSelect.html('<option value="">' + errorMsg + '</option>');
                    console.error('Commentary chapters error:', response);
                    if (callback) callback(false);
                }
            }).fail(function (xhr, status, error) {
                console.error('AJAX Error:', status, error, xhr.responseText);
                $chapterSelect.html('<option value="">خطأ في التحميل</option>');
                if (callback) callback(false);
            });
        }
    }

    // Check for URL parameters and auto-load
    const $wrapper = $('.bible-commentary-wrapper');
    const urlBook = $wrapper.data('url-book');
    const urlChapter = $wrapper.data('url-chapter');
    const urlSource = $wrapper.data('url-source');

    // Robust Normalizer for Matching (Ignores dashes, spaces, diacritics)
    function normalizeForMatch(str) {
        if (!str) return '';
        // 1. Decode URI component (in case it's encoded)
        try { str = decodeURIComponent(str); } catch(e) {}
        
        // 2. Normalize Arabic (Remove diacritics, unification)
        str = str.replace(/[\u064B-\u065F]/g, '') // Tashkeel
                 .replace(/[أإآٱ]/g, 'ا')
                 .replace(/ى/g, 'ي')
                 .replace(/ة/g, 'ه');
                 
        // 3. Remove non-alphanumeric (spaces, dashes, underscores) to equate "1-Samuel" with "1 Samuel"
        // Keep only letters and numbers
        return str.replace(/[^\w\u0600-\u06FF0-9]/g, '').toLowerCase();
    }

    if (urlBook && urlChapter) {
        console.log('Auto-loading from URL:', urlBook, urlChapter, urlSource);

        // Set source first - default to 'ty' (تادرس يعقوب) if not specified
        const sourceToUse = urlSource || 'ty';
        $sourceSelect.val(sourceToUse);

        // Load books for this source first, then select the book
        loadBooksForSource(sourceToUse, function(success) {
            if (!success) {
                console.error('Failed to load books for source:', sourceToUse);
                return;
            }

            // Wait a bit for DOM to update
            setTimeout(function() {
                // Find matching book (handle normalization)
                let bookFound = false;
                let matchedBookValue = null;
                
                // Prepare target from URL
                const targetNorm = normalizeForMatch(urlBook);

                $bookSelect.find('option').each(function () {
                    const optionVal = $(this).val();
                    // Compare normalized versions
                    if (optionVal && normalizeForMatch(optionVal) === targetNorm) {
                        matchedBookValue = optionVal;
                        bookFound = true;
                        return false;
                    }
                });

                if (!bookFound) {
                    // Try direct match (fallback)
                    matchedBookValue = urlBook;
                }

                if (matchedBookValue) {
                    console.log('Matched book:', matchedBookValue);
                    $bookSelect.val(matchedBookValue);

                    // Load chapters directly using AJAX
                    loadChapters(matchedBookValue, sourceToUse, function (success) {
                        if (success) {
                            // Wait a bit for DOM to update, then select chapter
                            setTimeout(function () {
                                $chapterSelect.val(urlChapter);

                                if ($chapterSelect.val() == urlChapter) {
                                    console.log('Chapter selected, loading commentary...');
                                    $btnLoad.prop('disabled', false).trigger('click');
                                } else {
                                    console.error('Chapter not found in list:', urlChapter);
                                }
                            }, 100);
                        } else {
                            console.error('Failed to load chapters for:', matchedBookValue);
                        }
                    });
                } else {
                    console.error('Book not found:', urlBook);
                }
            }, 200);
        });
    }

    // Function to update URL in address bar - FIXED for Pretty URLs
    function updateCommentaryURL(book, chapter, source) {
        // Build SEO-friendly pretty URL: /bible-commentary/source/book-slug/chapter/
        const bookSlug = book.replace(/\s+/g, '-').replace(/[^\u0600-\u06FF\w-]/g, '');
        const sourceSlug = source || 'af';
        
        // Construct the pretty URL (without /bible/ prefix to avoid duplication)
        const baseUrl = window.location.origin + '/bible-commentary/';
        const newUrl = baseUrl + sourceSlug + '/' + encodeURIComponent(bookSlug) + '/' + chapter + '/';
        
        // Update browser history
        window.history.pushState({ 
            book: book, 
            chapter: chapter, 
            source: source 
        }, '', newUrl);
    }

    // Load Chapters when Book changes
    $bookSelect.on('change', function () {
        const book = $(this).val();
        const source = $sourceSelect.val();
        loadChapters(book, source);
    });
    
    // Load books when source changes
    $sourceSelect.on('change', function() {
        const source = $(this).val();
        loadBooksForSource(source);
    });
    
    // Function to load books for selected source
    function loadBooksForSource(source, callback) {
        $bookSelect.html('<option value="">جارٍ التحميل...</option>');
        $bookSelect.prop('disabled', true);
        $chapterSelect.html('<option value="">اختر الأصحاح</option>');
        $chapterSelect.prop('disabled', true);
        $btnLoad.prop('disabled', true);
        
        $.post(bibleCommentary.ajax_url, {
            action: 'get_books_by_source',
            nonce: bibleCommentary.nonce,
            source: source
        }, function(response) {
            if (response.success) {
                let options = '<option value="">اختر السفر</option>';
                
                // Old Testament
                if (response.data.old_testament && response.data.old_testament.length > 0) {
                    options += '<optgroup label="العهد القديم">';
                    response.data.old_testament.forEach(function(book) {
                        options += `<option value="${book}">${book}</option>`;
                    });
                    options += '</optgroup>';
                }
                
                // New Testament
                if (response.data.new_testament && response.data.new_testament.length > 0) {
                    options += '<optgroup label="العهد الجديد">';
                    response.data.new_testament.forEach(function(book) {
                        options += `<option value="${book}">${book}</option>`;
                    });
                    options += '</optgroup>';
                }
                
                $bookSelect.html(options).prop('disabled', false);
                if (callback) callback(true);
            } else {
                $bookSelect.html('<option value="">لا توجد أسفار متاحة</option>');
                console.error('Error loading books:', response);
                if (callback) callback(false);
            }
        }).fail(function() {
            $bookSelect.html('<option value="">خطأ في التحميل</option>');
            if (callback) callback(false);
        });
    }
    
    // Load books for default source on page load
    const initialSource = $sourceSelect.val();
    if (initialSource) {
        loadBooksForSource(initialSource);
    }

    // Enable Load button
    $chapterSelect.on('change', function () {
        if ($(this).val()) {
            $btnLoad.prop('disabled', false);
            // Auto load?
            loadCommentary();
        }
    });

    // Load Commentary
    $btnLoad.on('click', function (e) {
        e.preventDefault();
        loadCommentary();
    });

    function loadCommentary() {
        const book = $bookSelect.val();
        const chapter = $chapterSelect.val();
        const source = $sourceSelect.val();

        if (!book || !chapter || !source) return;
        
        // Reset lazy loading variables
        currentOffset = 0;
        hasMoreContent = true;
        currentBook = book;
        currentChapter = chapter;
        currentSource = source;

        $contentArea.html('<div class="loading-spinner">' + bibleCommentary.loading + '</div>');
        $tocList.empty();
        
        // Load first chunk
        loadCommentaryChunk(true);
    }
    
    // Load commentary chunk (Lazy Loading)
    function loadCommentaryChunk(isInitial = false) {
        if (isLoading || !hasMoreContent) return;
        
        isLoading = true;
        
        if (isInitial) {
            // Show clear loading message
            $contentArea.html('<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> جارٍ تحميل التفسير...</div>');
        } else {
            $contentArea.append('<div class="loading-more"><i class="fas fa-spinner fa-spin"></i> جارٍ تحميل المزيد...</div>');
        }
        
        $.post(bibleCommentary.ajax_url, {
            action: 'get_commentary_text_paginated',
            nonce: bibleCommentary.nonce,
            book: currentBook,
            chapter: currentChapter,
            source: currentSource,
            offset: currentOffset,
            limit: ROWS_PER_LOAD
        }, function (response) {
            if (response.success) {
                const data = response.data;
                
                if (isInitial) {
                    // First load - replace content
                    $contentArea.html(data.html);
                    
                    // Update URL in address bar
                    updateCommentaryURL(currentBook, currentChapter, currentSource);
                    
                    // Update Page Title
                    if (data.page_title) {
                        document.title = data.page_title;
                    }
                    
                    // Build TOC
                    if (data.toc && data.toc.length > 0) {
                        let tocHtml = '';
                        data.toc.forEach(function (item) {
                            tocHtml += `<li class="toc-level-${item.level}"><a href="#${item.id}">${item.title}</a></li>`;
                        });
                        $tocList.html(tocHtml);
                    } else {
                        $tocList.html('<li><em>لا توجد عناوين فرعية</em></li>');
                    }
                    
                    // Add "Read Chapter" button with proper book name conversion
                    $.post(bibleCommentary.ajax_url, {
                        action: 'convert_commentary_book_name',
                        nonce: bibleCommentary.nonce,
                        book: currentBook
                    }, function(response) {
                        if (response.success && response.data.bible_book_name) {
                            const bibleBaseUrl = bibleCommentary.bible_url || '/bible/';
                            const bookSlug = response.data.bible_book_name.replace(/\s+/g, '-');
                            const chapterUrl = bibleBaseUrl + encodeURIComponent(bookSlug) + '/' + currentChapter + '/';
                            const readChapterBtn = `
                                <div class="commentary-actions">
                                    <a href="${chapterUrl}" class="button button-secondary read-chapter-link">
                                        <i class="fas fa-bible"></i> قراءة الأصحاح (${currentChapter})
                                    </a>
                                </div>`;
                            $contentArea.prepend(readChapterBtn); // في الأعلى
                            $contentArea.append(readChapterBtn);  // في الأسفل
                        }
                    });
                } else {
                    // Subsequent loads - append content
                    $('.loading-more').remove();
                    $contentArea.append(data.html);
                    
                    // Append to TOC if exists
                    if (data.toc && data.toc.length > 0) {
                        let tocHtml = '';
                        data.toc.forEach(function (item) {
                            tocHtml += `<li class="toc-level-${item.level}"><a href="#${item.id}">${item.title}</a></li>`;
                        });
                        $tocList.append(tocHtml);
                    }
                }
                
                // Update progress
                currentOffset += ROWS_PER_LOAD;
                hasMoreContent = data.has_more;
                isLoading = false;
                
                // Hide progress indicator (commented out - shows "1 of 1" which is confusing)
                // if (data.total_rows) {
                //     updateProgressIndicator(data.loaded_count, data.total_rows, data.progress_percent);
                // }
                
                // Show completion message
                if (!hasMoreContent) {
                    $contentArea.append('<div class="load-complete"><i class="fas fa-check-circle"></i> تم تحميل التفسير كاملاً</div>');
                }
                
            } else {
                if (isInitial) {
                    $contentArea.html('<p class="error-msg">' + response.data.message + '</p>');
                } else {
                    $('.loading-more').remove();
                }
                isLoading = false;
                hasMoreContent = false;
            }
        }).fail(function () {
            if (isInitial) {
                $contentArea.html('<p class="error-msg">' + bibleCommentary.error + '</p>');
            } else {
                $('.loading-more').remove();
            }
            isLoading = false;
            hasMoreContent = false;
        });
    }
    
    // Update progress indicator
    function updateProgressIndicator(loaded, total, percent) {
        let $indicator = $('.progress-indicator');
        if ($indicator.length === 0) {
            $contentArea.prepend(`
                <div class="progress-indicator">
                    <span class="progress-text">تم تحميل <strong class="loaded-count">${loaded}</strong> من <strong class="total-count">${total}</strong> صف</span>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${percent}%"></div>
                    </div>
                </div>
            `);
        } else {
            $indicator.find('.loaded-count').text(loaded);
            $indicator.find('.progress-fill').css('width', percent + '%');
        }
    }
    
    // Scroll detection for lazy loading
    $(window).on('scroll', function() {
        if (hasMoreContent && !isLoading && currentBook) {
            const scrollTop = $(window).scrollTop();
            const windowHeight = $(window).height();
            const docHeight = $(document).height();
            
            // Load more when reaching 80% of the page
            if (scrollTop + windowHeight >= docHeight * 0.8) {
                loadCommentaryChunk(false);
            }
        }
    });

    // TOC Smooth Scroll - Use event delegation for dynamically loaded links
    $(document).on('click', '#commentary-toc-list a', function (e) {
        e.preventDefault();
        const targetId = $(this).attr('href');

        if (targetId && $(targetId).length) {
            const targetTop = $(targetId).offset().top - 100; // Offset for header
            $('html, body').animate({ scrollTop: targetTop }, 500);
        } else {
            console.warn('TOC target not found:', targetId);
        }
    });

    // Handle Smart Verse Links - Use event delegation for dynamically loaded content
    $(document).on('click', '.smart-verse-link', function (e) {
        e.preventDefault();
        const $link = $(this);
        const book = $link.data('book');
        const chapter = $link.data('chapter');
        const verse = $link.data('verse');
        const verseEnd = $link.data('verse-end'); // For ranges like 56-64

        console.log('Verse link clicked:', book, chapter, verse, verseEnd ? `to ${verseEnd}` : ''); // Debug

        // Fetch verse text (or range)
        $.post(bibleCommentary.ajax_url, {
            action: 'my_bible_get_verse_for_modal',
            book: book,
            chapter: chapter,
            verse: verse,
            verse_end: verseEnd || '' // Pass end verse if exists
        }, function (response) {
            if (response.success) {
                $('#modal-verse-title').text(response.data.reference);
                $('#modal-verse-text').html(response.data.text);

                // Set up "Read Chapter" button
                let baseUrl = bibleCommentary.bible_url;
                if (!baseUrl.endsWith('/')) baseUrl += '/';
                const chapterUrl = baseUrl + `${response.data.book_slug}/${chapter}/`;
                $('#btn-goto-chapter').attr('href', chapterUrl);

                // Show modal with CSS class (for transitions)
                $verseModal.addClass('active').css('display', 'flex');
                console.log('Modal shown for:', response.data.reference);
            } else {
                console.error('Verse fetch error:', response);
                alert('خطأ في تحميل الآية: ' + (response.data && response.data.message ? response.data.message : ''));
            }
        }).fail(function (xhr, status, error) {
            console.error('AJAX Error:', status, error, xhr.responseText);
            alert('خطأ في الاتصال');
        });
    });

    // Close modal when clicking close button or outside
    $(document).on('click', '#verse-modal .modal-close, #verse-modal', function (e) {
        if (e.target === this) {
            $verseModal.removeClass('active');
            setTimeout(function () {
                $verseModal.css('display', 'none');
            }, 300); // Wait for transition
        }
    });

    // Verse Modal Implementation
    // We assume the text comes with links or we parse them here if PHP didn't enough.
    // PHP parsing of text is safer for HTML structure.
    // If we have <a class="ref-link" data-ref="Mat 1:1">Mat 1:1</a>...

    // Since we didn't implement robust regex in PHP yet, let's assume raw text.
    // Ideally, we enhance PHP to wrap references.

    $('#close-verse-modal').on('click', function () {
        $verseModal.hide();
    });

    $(window).on('click', function (e) {
        if ($(e.target).is($verseModal)) {
            $verseModal.hide();
        }
    });

    // TOC Toggle for Mobile
    $('#toc-toggle').on('click', function () {
        $sidebar.toggleClass('active');
    });

    // Close TOC when clicking outside on mobile
    $(document).on('click', function (e) {
        if ($(window).width() <= 768) {
            if (!$(e.target).closest('.commentary-sidebar, #toc-toggle').length) {
                $sidebar.removeClass('active');
            }
        }
    });

});
