// assets/js/bible-frontend.js

jQuery(document).ready(function($) {
    const BIBLE_STRINGS = (typeof bibleFrontend !== 'undefined' && bibleFrontend.localized_strings) ? bibleFrontend.localized_strings : {};
    const IMAGE_GENERATOR_STRINGS = (typeof bibleFrontend !== 'undefined' && bibleFrontend.image_generator) ? bibleFrontend.image_generator : {};
    const AJAX_URL = (typeof bibleFrontend !== 'undefined' && bibleFrontend.ajax_url) ? bibleFrontend.ajax_url : '/wp-admin/admin-ajax.php';
    const AJAX_NONCE = (typeof bibleFrontend !== 'undefined' && bibleFrontend.nonce) ? bibleFrontend.nonce : '';
    const BASE_URL = (typeof bibleFrontend !== 'undefined' && bibleFrontend.base_url) ? bibleFrontend.base_url : '/bible/'; 
    const DEFAULT_DARK_MODE = (typeof bibleFrontend !== 'undefined' && bibleFrontend.default_dark_mode) ? bibleFrontend.default_dark_mode : false;
    let DEFAULT_TESTAMENT_VIEW = (typeof bibleFrontend !== 'undefined' && bibleFrontend.default_testament_view) ? bibleFrontend.default_testament_view : 'all';
    const TESTAMENTS_LABELS_FROM_PHP = (typeof bibleFrontend !== 'undefined' && bibleFrontend.testaments) ? bibleFrontend.testaments : {all: 'الكل'};

    // --- الدوال المساعدة (كما هي من قبل) ---
    function removeArabicTashkeel(text) { 
        if (typeof text !== 'string') return '';
        text = text.replace(/[\u064B-\u0652\u0670]/g, '');
        text = text.replace(/\u0640/g, '');
        return text;
    }
    function applyDarkModePreference() {
        const isDarkMode = localStorage.getItem('darkMode') === 'enabled' ||
                           (localStorage.getItem('darkMode') === null && DEFAULT_DARK_MODE);
        if (isDarkMode) {
            $('body').addClass('dark-mode');
            $('.dark-mode-toggle-button').find('.label').text(bibleFrontend.dark_mode_toggle_label_light || 'الوضع النهاري');
            $('.dark-mode-toggle-button').find('i').removeClass('fa-moon').addClass('fa-sun');
        } else {
            $('body').removeClass('dark-mode');
            $('.dark-mode-toggle-button').find('.label').text(bibleFrontend.dark_mode_toggle_label_dark || 'الوضع الليلي');
            $('.dark-mode-toggle-button').find('i').removeClass('fa-sun').addClass('fa-moon');
        }
    }
    function toggleDarkMode() {
        if ($('body').hasClass('dark-mode')) {
            localStorage.setItem('darkMode', 'disabled');
        } else {
            localStorage.setItem('darkMode', 'enabled');
        }
        applyDarkModePreference();
    }
    applyDarkModePreference(); // تطبيق عند التحميل
    let currentUtterance = null;
    let isReading = false;
    function getArabicVoice() {
        const voices = window.speechSynthesis.getVoices();
        let arabicVoice = voices.find(voice => voice.lang.toLowerCase().startsWith('ar'));
        if (!arabicVoice) arabicVoice = voices.find(voice => voice.name.toLowerCase().includes('arabic'));
        return arabicVoice;
    }
    if (speechSynthesis.onvoiceschanged !== undefined) {
        speechSynthesis.onvoiceschanged = getArabicVoice;
    }
    function handleReadAloud($contentArea, $button) {
        if (!('speechSynthesis' in window)) {
            alert('عذراً، متصفحك لا يدعم ميزة القراءة الصوتية.'); return;
        }
        if (isReading) {
            window.speechSynthesis.cancel();
            isReading = false;
            $button.find('.label').text(bibleFrontend.read_aloud_label || 'قراءة بصوت عالٍ');
            $button.find('i').removeClass('fa-stop-circle').addClass('fa-volume-up');
            return;
        }
        let textToRead = '';
        const $textElements = $contentArea.find('.verses-text-container .verse-text .text-content, .verse-text-container .verse-text .text-content'); 
        if ($textElements.length > 0) {
             $textElements.each(function() { textToRead += $(this).text().trim() + ' '; });
        } else {
            const $singleVerseText = $contentArea.find('.random-verse .text-content, .daily-verse .text-content');
            if ($singleVerseText.length > 0) textToRead = $singleVerseText.first().text().trim();
        }
        if (textToRead.trim() === '') { alert('لا يوجد نص للقراءة.'); return; }
        currentUtterance = new SpeechSynthesisUtterance(textToRead.trim());
        const arabicVoice = getArabicVoice();
        if (arabicVoice) currentUtterance.voice = arabicVoice;
        else { console.warn("لم يتم العثور على صوت عربي."); currentUtterance.lang = 'ar-SA'; }
        currentUtterance.pitch = 1; currentUtterance.rate = 0.9;
        currentUtterance.onstart = () => {
            isReading = true;
            $button.find('.label').text(bibleFrontend.stop_reading_label || 'إيقاف القراءة');
            $button.find('i').removeClass('fa-volume-up').addClass('fa-stop-circle');
        };
        currentUtterance.onend = () => {
            isReading = false;
            $button.find('.label').text(bibleFrontend.read_aloud_label || 'قراءة بصوت عالٍ');
            $button.find('i').removeClass('fa-stop-circle').addClass('fa-volume-up');
            currentUtterance = null;
        };
        currentUtterance.onerror = (event) => {
            console.error('SpeechSynthesisUtterance.onerror', event);
            alert('حدث خطأ أثناء محاولة القراءة: ' + event.error);
            isReading = false;
            $button.find('.label').text(bibleFrontend.read_aloud_label || 'قراءة بصوت عالٍ');
            $button.find('i').removeClass('fa-stop-circle').addClass('fa-volume-up');
        };
        window.speechSynthesis.speak(currentUtterance);
    }

    function generateVerseImage(verseText, verseReference, $imageContainer) {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        if (!ctx) { alert(IMAGE_GENERATOR_STRINGS.canvas_unsupported || 'Canvas not supported'); return; }
        const canvasWidth = 800, canvasHeight = 450;
        canvas.width = canvasWidth; canvas.height = canvasHeight;
        const gradient = ctx.createLinearGradient(0, 0, canvasWidth, canvasHeight);
        gradient.addColorStop(0, '#4B0082'); gradient.addColorStop(0.5, '#00008B'); gradient.addColorStop(1, '#2F4F4F');
        ctx.fillStyle = gradient; ctx.fillRect(0, 0, canvasWidth, canvasHeight);
        const primaryFont = "bold 30px 'Noto Naskh Arabic', 'Arial', 'Tahoma', sans-serif";
        const referenceFont = "italic 22px 'Times New Roman', serif";
        const watermarkFont = "16px 'Arial', sans-serif";
        ctx.fillStyle = '#FFFFFF'; ctx.direction = 'rtl';
        const padding = 50; const maxWidth = canvasWidth - (padding * 2); let yPosition = padding + 50;
        function wrapText(context, text, x, y, maxLineWidth, lineHeight) {
            const lines = []; let currentLine = ""; const words = text.split(/\s+/);
            for (let i = 0; i < words.length; i++) {
                const word = words[i]; const testLine = currentLine.length === 0 ? word : currentLine + " " + word;
                const metrics = context.measureText(testLine);
                if (metrics.width > maxLineWidth && currentLine.length > 0) { lines.push(currentLine); currentLine = word; } 
                else { currentLine = testLine; }
            }
            lines.push(currentLine); 
            lines.forEach(line => { context.fillText(line, x, y); y += lineHeight; });
            return y;
        }
        ctx.font = primaryFont; ctx.textAlign = 'right';
        yPosition = wrapText(ctx, verseText, canvasWidth - padding, yPosition, maxWidth, 45);
        ctx.font = referenceFont; ctx.textAlign = 'left';
        yPosition = Math.min(yPosition + 40, canvasHeight - padding - 40); 
        ctx.fillText(verseReference, padding, yPosition);
        ctx.font = watermarkFont; ctx.fillStyle = 'rgba(255, 255, 255, 0.6)'; ctx.textAlign = 'right';
        const watermarkText = IMAGE_GENERATOR_STRINGS.website_credit || 'اسم الموقع';
        ctx.fillText(watermarkText, canvasWidth - padding, canvasHeight - padding + 10);
        if ($imageContainer.length) {
            $imageContainer.html('');
            const imgElement = $('<img>', { src: canvas.toDataURL('image/png'), alt: verseReference, css: { 'max-width': '100%', 'border-radius': '8px', 'margin-top': '15px' } });
            const downloadLink = $('<a>', { href: imgElement.attr('src'), download: `verse-${verseReference.replace(/[:\s]/g, '_')}.png`, text: IMAGE_GENERATOR_STRINGS.download_image || 'تحميل الصورة', class: 'bible-control-button download-image-button', css: { 'display': 'block', 'text-align': 'center', 'margin-top': '10px' } });
            $imageContainer.append(imgElement).append(downloadLink);
        }
    }


    // --- معالجات الأحداث الموحدة للأزرار ---
    $(document.body).on('click', '.bible-control-button', function(event) {
        const $button = $(this);
        const action = $button.data('action') || $button.attr('id'); 
        let $contentArea = $button.closest('.bible-content-area');
        if (!$contentArea.length) $contentArea = $button.closest('.bible-search-results');
        if (!$contentArea.length) $contentArea = $button.closest('.random-verse-widget, .daily-verse-widget');
        
        // إذا كان الزر هو زر تنقل AJAX، لا تمنع السلوك الافتراضي هنا
        // سيتم معالجته بواسطة مستمع الحدث الخاص بـ .ajax-nav-link
        if (!$(this).hasClass('ajax-nav-link') && ['toggle-tashkeel', 'increase-font', 'decrease-font', 'dark-mode-toggle', 'read-aloud', 'generate-image'].includes(action)) {
            event.preventDefault();
        }

        const $textContainer = $contentArea.length ? $contentArea.find('.verses-text-container, .verse-text-container') : $('body');

        switch (action) {
            case 'toggle-tashkeel': 
                $textContainer.find('.verse-text .text-content').each(function() {
                    const $textContentSpan = $(this);
                    const $verseElement = $textContentSpan.closest('.verse-text');
                    const originalText = $verseElement.data('original-text');
                    const currentText = $textContentSpan.text().trim();
                    if (currentText === originalText) {
                        $textContentSpan.text(removeArabicTashkeel(originalText));
                        $button.find('.label').text(bibleFrontend.show_tashkeel_label || 'إظهار التشكيل');
                    } else {
                        $textContentSpan.text(originalText);
                        $button.find('.label').text(bibleFrontend.hide_tashkeel_label || 'إلغاء التشكيل');
                    }
                });
                break;
            case 'increase-font':
                $textContainer.find('.verse-text .text-content, .verse-text .verse-number, .verse-text .verse-reference-link').each(function() {
                    const $el = $(this);
                    let currentSize = parseFloat($el.css('font-size'));
                    let newSize = Math.min(48, currentSize + 2); 
                    $el.css('font-size', newSize + 'px');
                });
                break;
            case 'decrease-font':
                $textContainer.find('.verse-text .text-content, .verse-text .verse-number, .verse-text .verse-reference-link').each(function() {
                    const $el = $(this);
                    let currentSize = parseFloat($el.css('font-size'));
                    let newSize = Math.max(12, currentSize - 2); 
                    $el.css('font-size', newSize + 'px');
                });
                break;
            case 'dark-mode-toggle':
                toggleDarkMode();
                break;
            case 'read-aloud':
                handleReadAloud($contentArea.length ? $contentArea : $('body'), $button);
                break;
            case 'generate-image':
                const verseText = $button.data('verse-text');
                const verseReference = $button.data('verse-reference');
                if (verseText && verseReference) {
                    const $imageContainer = $contentArea.find('#verse-image-container');
                    if ($imageContainer.length && IMAGE_GENERATOR_STRINGS) {
                        $imageContainer.html(`<p>${IMAGE_GENERATOR_STRINGS.generating_image || 'جارٍ إنشاء الصورة...'}</p>`);
                        setTimeout(() => generateVerseImage(verseText, verseReference, $imageContainer), 50);
                    }
                }
                break;
        }
    });
    
    // --- AJAX لاختيار السفر والأصحاح مع فلتر العهد ---
    const $bibleContentContainer = $('#bible-container'); 
    const $testamentSelect = $bibleContentContainer.find('#bible-testament-select');
    const $bookSelect = $bibleContentContainer.find('#bible-book-select');
    const $chapterSelect = $bibleContentContainer.find('#bible-chapter-select');
    const $versesDisplay = $bibleContentContainer.find('#bible-verses-display'); 
    const $mainPageTitleElement = $('#bible-main-page-title'); 
    
    function showLoadingInVersesDisplay(message = BIBLE_STRINGS.loading || 'جارٍ التحميل...') {
        $versesDisplay.html(`<p class="bible-loading-message"><i class="fas fa-spinner fa-spin"></i> ${message}</p>`);
    }
    
    function resetVersesDisplay(message = BIBLE_STRINGS.please_select_book_and_chapter || 'يرجى اختيار السفر ثم الأصحاح.'){
        $versesDisplay.html(`<p class="bible-select-prompt">${message}</p>`);
        if ($mainPageTitleElement.length) {
            const originalTitle = $('body').data('original-page-title');
            if (originalTitle) {
                $mainPageTitleElement.text(originalTitle);
            } else {
                 const pageTitleParts = document.title.split(' - ');
                 $mainPageTitleElement.text(pageTitleParts[0] || (BIBLE_STRINGS.mainPageTitle || 'الكتاب المقدس'));
            }
        } else {
            $versesDisplay.find('h1#bible-ajax-title').remove(); 
        }
    }

    if ($mainPageTitleElement.length) {
        $('body').data('original-page-title', $mainPageTitleElement.text());
    }

    // ** تعديل دالة تحديث تفاصيل الصفحة **
    function updatePageDetails(pageTitleText, metaDescription, bookName, chapterNum, testamentVal) {
        let browserFullTitle = pageTitleText;
        const siteName = $('meta[property="og:site_name"]').attr('content') || document.title.split(' - ').pop() || '';
        if (siteName && pageTitleText !== siteName) {
            browserFullTitle += ' - ' + siteName;
        }
        document.title = browserFullTitle;

        $('meta[name="description"]').attr('content', metaDescription);

        // تحديث عنوان H1 على الصفحة
        let $titleElementToUpdate = $mainPageTitleElement;
        if (!$titleElementToUpdate.length) { 
            $titleElementToUpdate = $versesDisplay.find('h1#bible-ajax-title');
            if (!$titleElementToUpdate.length) { 
                const $controls = $versesDisplay.find('.bible-controls');
                if($controls.length){
                    $controls.before('<h1 id="bible-ajax-title">' + pageTitleText + '</h1>');
                } else {
                     $versesDisplay.prepend('<h1 id="bible-ajax-title">' + pageTitleText + '</h1>');
                }
                $titleElementToUpdate = $versesDisplay.find('h1#bible-ajax-title'); 
            }
        }
        if ($titleElementToUpdate.length) {
            $titleElementToUpdate.text(pageTitleText);
        }
        
        let newUrlPath = BASE_URL; 
        if (bookName) {
            const createSlug = (str) => {
                if (!str) return '';
                let slug = String(str).trim();
                slug = slug.replace(/\s+/g, '-'); 
                slug = slug.replace(/[^a-zA-Z0-9\u0600-\u06FF\-]/g, ''); 
                return encodeURIComponent(slug); 
            };
            const bookSlug = createSlug(bookName);
            newUrlPath += bookSlug + '/';
            if (chapterNum) {
                newUrlPath += chapterNum + '/';
            }
        }
        const newUrl = `${BASE_URL}${bookName}/${chapterNum}/`;
        window.history.pushState({ path: newUrl }, '', newUrl);
        
        const searchParams = new URLSearchParams();
        if (testamentVal && testamentVal !== 'all') {
            searchParams.set('testament', testamentVal);
        }
        if (searchParams.toString()) {
            newUrl += '?' + searchParams.toString();
        }
        
        try {
            history.pushState({ book: bookName, chapter: chapterNum, testament: testamentVal }, browserFullTitle, newUrl);
        } catch (e) { console.error("Error in history.pushState: ", e); }
    }

    function updateBookDropdown(selectedTestamentValue, preselectedBookName = null, preselectedChapter = null, isInitialLoad = false) {
        $bookSelect.prop('disabled', true).empty().append(`<option value="">${BIBLE_STRINGS.loading || 'جارٍ التحميل...'}</option>`);
        $chapterSelect.prop('disabled', true).empty().append(`<option value="">${BIBLE_STRINGS.select_chapter || 'اختر الأصحاح'}</option>`);
        
        if (!isInitialLoad || ($versesDisplay.find('.verse-text').length === 0 && !$versesDisplay.find('.bible-select-prompt').length) ) { 
             if ($versesDisplay.find('.bible-select-prompt').length === 0 || $versesDisplay.find('.bible-select-prompt').text() !== (BIBLE_STRINGS.please_select_book_and_chapter || 'يرجى اختيار السفر ثم الأصحاح.')) {
                resetVersesDisplay(BIBLE_STRINGS.please_select_book_and_chapter || 'يرجى اختيار السفر ثم الأصحاح.');
            }
        }

        $.ajax({
            url: AJAX_URL, type: 'POST',
            data: { action: 'bible_get_books_by_testament', testament: selectedTestamentValue, nonce: AJAX_NONCE },
            dataType: 'json',
            success: function(response) {
                $bookSelect.empty().append(`<option value="">${BIBLE_STRINGS.select_book || 'اختر السفر'}</option>`); 
                if (response.success && response.data && response.data.length > 0) {
                    $.each(response.data, function(index, bookName) {
                        $bookSelect.append(`<option value="${bookName}">${bookName}</option>`);
                    });
                    $bookSelect.prop('disabled', false);

                    if (preselectedBookName && $bookSelect.find('option[value="' + preselectedBookName + '"]').length > 0) { 
                        $bookSelect.val(preselectedBookName); 
                        $bookSelect.trigger('change', [preselectedChapter]); 
                    } else if (isInitialLoad && $versesDisplay.find('.verse-text').length === 0) {
                        resetVersesDisplay();
                    }
                } else {
                    $bookSelect.append(`<option value="" disabled>${BIBLE_STRINGS.no_books_found || 'لا توجد أسفار لهذا العهد'}</option>`);
                    if (!isInitialLoad || $versesDisplay.find('.verse-text').length === 0) {
                        resetVersesDisplay(BIBLE_STRINGS.no_books_found || 'لا توجد أسفار لهذا العهد');
                    }
                }
            },
            error: function() {
                $bookSelect.empty().append(`<option value="" disabled>${BIBLE_STRINGS.error_loading_books || 'خطأ في تحميل الأسفار'}</option>`);
                 if (!isInitialLoad || $versesDisplay.find('.verse-text').length === 0) {
                    resetVersesDisplay(BIBLE_STRINGS.error_loading_books || 'خطأ في تحميل الأسفار');
                }
            }
        });
    }

    if ($testamentSelect.length) {
        const initialTestamentFromPHP = $testamentSelect.val();
        const urlParams = new URLSearchParams(window.location.search);
        const testamentFromUrlParam = urlParams.get('testament');
        
        let finalInitialTestament = DEFAULT_TESTAMENT_VIEW;

        if (testamentFromUrlParam && TESTAMENTS_LABELS_FROM_PHP.hasOwnProperty(testamentFromUrlParam)) {
            finalInitialTestament = testamentFromUrlParam;
        } else if (initialTestamentFromPHP && TESTAMENTS_LABELS_FROM_PHP.hasOwnProperty(initialTestamentFromPHP)) {
            finalInitialTestament = initialTestamentFromPHP;
        }
        $testamentSelect.val(finalInitialTestament);
        
        const initialBookFromData = $bookSelect.data('initial-book');
        const initialChapterFromData = $chapterSelect.data('initial-chapter');
        
        const bookToPreselect = initialBookFromData || null;
        const chapterToPreselect = initialChapterFromData || null;
        
        let performInitialBookLoad = true;
        if ($versesDisplay.find('.verse-text').length > 0 && bookToPreselect && chapterToPreselect) {
             if ($bookSelect.val() === bookToPreselect && $chapterSelect.val() == chapterToPreselect) {
                performInitialBookLoad = false;
            }
        } else if ($versesDisplay.find('.bible-select-prompt').length > 0 && !bookToPreselect && !chapterToPreselect) {
            performInitialBookLoad = true;
        }


        if(performInitialBookLoad){
            updateBookDropdown(finalInitialTestament, bookToPreselect, chapterToPreselect, true);
        } else if (bookToPreselect) {
             if ($bookSelect.val() === bookToPreselect && $chapterSelect.find('option').length <= 1) { 
                $.ajax({
                    url: AJAX_URL, type: 'POST',
                    data: { action: 'bible_get_chapters', book: bookToPreselect, nonce: AJAX_NONCE },
                    dataType: 'json',
                    async: false, 
                    success: function(response) {
                        $chapterSelect.empty().append(`<option value="">${BIBLE_STRINGS.select_chapter || 'اختر الأصحاح'}</option>`);
                        if (response.success && response.data && response.data.length > 0) {
                            $.each(response.data, function(index, chapter) {
                                $chapterSelect.append(`<option value="${chapter}">${chapter}</option>`);
                            });
                            $chapterSelect.prop('disabled', false);
                            if (chapterToPreselect && $chapterSelect.find('option[value="' + chapterToPreselect + '"]').length > 0) { 
                                $chapterSelect.val(chapterToPreselect);
                            }
                        }
                    }
                });
            }
        }


        $testamentSelect.on('change', function() {
            const selectedTestament = $(this).val();
            updateBookDropdown(selectedTestament, null, null, false); 
        });
    }

    $bookSelect.on('change', function(event, preselectedChapter = null) { 
        const selectedBook = $(this).val();
        $chapterSelect.empty().append(`<option value="">${BIBLE_STRINGS.select_chapter || 'اختر الأصحاح'}</option>`).prop('disabled', true);
        
        if (!selectedBook) {
            resetVersesDisplay();
            return;
        }

        $chapterSelect.prop('disabled', true); 
        $.ajax({
            url: AJAX_URL, type: 'POST',
            data: { action: 'bible_get_chapters', book: selectedBook, nonce: AJAX_NONCE },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    $.each(response.data, function(index, chapter) {
                        $chapterSelect.append(`<option value="${chapter}">${chapter}</option>`);
                    });
                    $chapterSelect.prop('disabled', false);
                    
                    const chapterToSelect = preselectedChapter || $chapterSelect.data('initial-chapter');

                    if (chapterToSelect && $chapterSelect.find('option[value="' + chapterToSelect + '"]').length > 0) { 
                        $chapterSelect.val(chapterToSelect).trigger('change');
                    } else if ($versesDisplay.find('.verse-text').length === 0) { 
                        resetVersesDisplay(BIBLE_STRINGS.please_select_chapter || 'يرجى اختيار الأصحاح.');
                    }
                } else {
                    const errorMsg = (response.data && response.data.message) ? response.data.message : (BIBLE_STRINGS.no_chapters_found || 'لم يتم العثور على أصحاحات.');
                    resetVersesDisplay(errorMsg); 
                }
            },
            error: function() {
                const errorMsg = BIBLE_STRINGS.error_loading_chapters_ajax || 'خطأ في تحميل الأصحاحات.';
                resetVersesDisplay(errorMsg);
                $chapterSelect.prop('disabled', true);
            }
        });
    });

    $chapterSelect.on('change', function() {
        const selectedBook = $bookSelect.val();
        const selectedChapter = $(this).val();
        const selectedTestament = $testamentSelect.length ? $testamentSelect.val() : 'all';

        if (!selectedBook || !selectedChapter) {
            resetVersesDisplay();
            return;
        }
        showLoadingInVersesDisplay();
        $.ajax({
            url: AJAX_URL, type: 'POST',
            data: { action: 'bible_get_verses', book: selectedBook, chapter: selectedChapter, nonce: AJAX_NONCE },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && response.data.html) {
                    $versesDisplay.html(response.data.html);
                    updatePageDetails(response.data.title, response.data.description, response.data.book, response.data.chapter, selectedTestament);
                } else {
                    const errorMsg = (response.data && response.data.message) ? response.data.message : (BIBLE_STRINGS.error_loading_verses || 'خطأ في تحميل الآيات.');
                    $versesDisplay.html(`<p class="bible-error-message">${errorMsg}</p>`);
                }
            },
            error: function() {
                $versesDisplay.html(`<p class="bible-error-message">${BIBLE_STRINGS.error_loading_verses_ajax || 'خطأ في تحميل الآيات.'}</p>`);
            }
        });
    });
    
    // معالجة أزرار التنقل بين الأصحاحات (التالي/السابق)
    $(document.body).on('click', '.ajax-nav-link', function(event) {
        event.preventDefault();
        const $link = $(this);
        const book = $link.data('book');
        const chapter = $link.data('chapter');
        let currentTestament = $testamentSelect.length ? $testamentSelect.val() : 'all';

        if (book && chapter) {
            // تحديث القوائم المنسدلة
            if ($bookSelect.val() !== book) {
                $bookSelect.val(book); 
            }
            if ($chapterSelect.val() !== String(chapter)) {
                 window.addEventListener('popstate', function(event) {
                     if (event.state && event.state.path) {
                         // استخراج bookSlug و chapterNum من الرابط
                         const pathParts = event.state.path.split('/');
                         const bookSlug = pathParts[pathParts.length - 3];
                         const chapterNum = pathParts[pathParts.length - 2];
                         
                         // تحديث القوائم المنسدلة
                         $('.bookSelect').val(bookSlug).trigger('change');
                         setTimeout(() => {
                             $('.chapterSelect').val(chapterNum).trigger('change');
                         }, 500);
                     }
                 });
            } else {
                 loadVersesViaAjax(book, chapter, currentTestament);
            }
        }
    });

    function loadVersesViaAjax(book, chapter, testament) {
        const testamentVal = testament || $testamentSelect.val(); // تأكد من تعريف المتغير
        showLoadingInVersesDisplay();
        $.ajax({
            url: AJAX_URL, type: 'POST',
            data: { action: 'bible_get_verses', book: book, chapter: chapter, nonce: AJAX_NONCE },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && response.data.html) {
                    $versesDisplay.html(response.data.html);
                    updatePageDetails(response.data.title, response.data.description, 
                        response.data.book, response.data.chapter, testamentVal); // استخدام المتغير المعرّف
                } else {
                    const errorMsg = (response.data && response.data.message) ? response.data.message : (BIBLE_STRINGS.error_loading_verses || 'خطأ في تحميل الآيات.');
                    $versesDisplay.html(`<p class="bible-error-message">${errorMsg}</p>`);
                }
            },
            error: function() {
                $versesDisplay.html(`<p class="bible-error-message">${BIBLE_STRINGS.error_loading_verses_ajax || 'خطأ في تحميل الآيات.'}</p>`);
            }
        });
    }
    
});
