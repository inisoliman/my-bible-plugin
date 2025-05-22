// assets/js/bible-frontend.js

jQuery(document).ready(function($) {
    // الوصول إلى النصوص المترجمة والمتغيرات من bibleFrontend
    const BIBLE_STRINGS = (typeof bibleFrontend !== 'undefined' && bibleFrontend.localized_strings) ? bibleFrontend.localized_strings : {};
    const IMAGE_GENERATOR_STRINGS = (typeof bibleFrontend !== 'undefined' && bibleFrontend.image_generator) ? bibleFrontend.image_generator : {};
    const AJAX_URL = (typeof bibleFrontend !== 'undefined') ? bibleFrontend.ajax_url : '/wp-admin/admin-ajax.php';
    const AJAX_NONCE = (typeof bibleFrontend !== 'undefined') ? bibleFrontend.nonce : '';
    const BASE_URL = (typeof bibleFrontend !== 'undefined') ? bibleFrontend.base_url : '/bible/';
    const DEFAULT_DARK_MODE = (typeof bibleFrontend !== 'undefined') ? bibleFrontend.default_dark_mode : false;

    /**
     * يزيل التشكيل (حركات الإعراب) من النص العربي.
     * @param {string} text النص الأصلي مع التشكيل.
     * @returns {string} النص بدون تشكيل.
     */
    function removeArabicTashkeel(text) {
        if (typeof text !== 'string') return '';
        text = text.replace(/[\u064B-\u0652\u0670]/g, '');
        text = text.replace(/\u0640/g, '');
        return text;
    }

    // --- معالجات الأحداث الموحدة باستخدام تفويض الأحداث ---
    $(document.body).on('click', '.bible-control-button', function(event) {
        event.preventDefault();
        const $button = $(this);
        const buttonId = $button.attr('id');
        
        // تحديد الحاوية الرئيسية للمحتوى الذي يعمل عليه الزر
        const $contentArea = $button.closest('.bible-content-area, .bible-search-results, .random-verse-widget, .daily-verse-widget');
        if (!$contentArea.length) {
            // console.warn('Button clicked outside a recognized content area:', buttonId);
            return; 
        }

        const $textContainer = $contentArea.find('.verses-text-container, .verse-text-container'); // حاوية النصوص الفعلية

        switch (buttonId) {
            case 'toggle-tashkeel': // يعمل على الأزرار بالـ ID toggle-tashkeel
            case 'toggle-tashkeel-search': // أو toggle-tashkeel-search
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
            case 'increase-font-search':
                $textContainer.find('.verse-text .text-content, .verse-text .verse-number, .verse-text .verse-reference-link').each(function() {
                    const $el = $(this);
                    let currentSize = parseFloat($el.css('font-size'));
                    let newSize = Math.min(48, currentSize + 2); // حد أقصى 48px
                    $el.css('font-size', newSize + 'px');
                });
                break;

            case 'decrease-font':
            case 'decrease-font-search':
                $textContainer.find('.verse-text .text-content, .verse-text .verse-number, .verse-text .verse-reference-link').each(function() {
                    const $el = $(this);
                    let currentSize = parseFloat($el.css('font-size'));
                    let newSize = Math.max(12, currentSize - 2); // حد أدنى 12px
                    $el.css('font-size', newSize + 'px');
                });
                break;

            case 'dark-mode-toggle':
                toggleDarkMode();
                break;

            case 'read-aloud-button':
                handleReadAloud($contentArea, $button);
                break;
            
            case 'generate-verse-image-button':
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

    // --- الوضع الليلي ---
    function applyDarkModePreference() {
        const isDarkMode = localStorage.getItem('darkMode') === 'enabled' ||
                           (localStorage.getItem('darkMode') === null && DEFAULT_DARK_MODE);
        const $toggleButton = $('#dark-mode-toggle'); // استهداف الزر الرئيسي إذا وجد (أو جميعهم)

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
    applyDarkModePreference(); // تطبيق عند تحميل الصفحة

    // --- القراءة الصوتية ---
    let currentUtterance = null;
    let isReading = false;

    function getArabicVoice() {
        const voices = window.speechSynthesis.getVoices();
        let arabicVoice = voices.find(voice => voice.lang.toLowerCase().startsWith('ar'));
        if (!arabicVoice) {
            arabicVoice = voices.find(voice => voice.name.toLowerCase().includes('arabic'));
        }
        return arabicVoice;
    }
    if (speechSynthesis.onvoiceschanged !== undefined) {
        speechSynthesis.onvoiceschanged = getArabicVoice;
    }
    
    function handleReadAloud($contentArea, $button) {
        if (!('speechSynthesis' in window)) {
            alert('عذراً، متصفحك لا يدعم ميزة القراءة الصوتية.');
            return;
        }

        if (isReading) {
            window.speechSynthesis.cancel();
            isReading = false;
            $button.find('.label').text(bibleFrontend.read_aloud_label || 'قراءة بصوت عالٍ');
            $button.find('i').removeClass('fa-stop-circle').addClass('fa-volume-up');
            return;
        }

        let textToRead = '';
        // البحث عن النصوص داخل الحاوية المحددة
        const $textElements = $contentArea.find('.verses-text-container .verse-text .text-content, .verse-text-container .verse-text .text-content'); 
        
        if ($textElements.length > 0) {
             $textElements.each(function() {
                textToRead += $(this).text().trim() + ' ';
            });
        } else {
            // محاولة قراءة آية عشوائية أو يومية إذا كانت هي الحاوية
            const $singleVerseText = $contentArea.find('.random-verse .text-content, .daily-verse .text-content');
            if ($singleVerseText.length > 0) {
                textToRead = $singleVerseText.first().text().trim();
            }
        }
        
        if (textToRead.trim() === '') {
            alert('لا يوجد نص للقراءة.');
            return;
        }

        currentUtterance = new SpeechSynthesisUtterance(textToRead.trim());
        const arabicVoice = getArabicVoice();
        if (arabicVoice) {
            currentUtterance.voice = arabicVoice;
        } else {
            console.warn("لم يتم العثور على صوت عربي. سيتم استخدام الصوت الافتراضي.");
            currentUtterance.lang = 'ar-SA';
        }
        currentUtterance.pitch = 1;
        currentUtterance.rate = 0.9;

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

    // --- إنشاء صورة للآية ---
    function generateVerseImage(verseText, verseReference, $imageContainer) {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            alert(IMAGE_GENERATOR_STRINGS.canvas_unsupported || 'Canvas not supported');
            return;
        }

        const canvasWidth = 800, canvasHeight = 450;
        canvas.width = canvasWidth; canvas.height = canvasHeight;

        const gradient = ctx.createLinearGradient(0, 0, canvasWidth, canvasHeight);
        gradient.addColorStop(0, '#4B0082'); // Indigo
        gradient.addColorStop(0.5, '#00008B'); // DarkBlue
        gradient.addColorStop(1, '#2F4F4F'); // DarkSlateGray
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, canvasWidth, canvasHeight);

        const primaryFont = "bold 30px 'Noto Naskh Arabic', 'Arial', 'Tahoma', sans-serif"; // خط عربي أفضل
        const referenceFont = "italic 22px 'Times New Roman', serif";
        const watermarkFont = "16px 'Arial', sans-serif";
        ctx.fillStyle = '#FFFFFF';
        ctx.direction = 'rtl';

        const padding = 50;
        const maxWidth = canvasWidth - (padding * 2);
        let yPosition = padding + 50;

        // وظيفة التفاف النص المحسنة
        function wrapText(context, text, x, y, maxLineWidth, lineHeight) {
            const lines = [];
            let currentLine = "";
            const words = text.split(/\s+/); // تقسيم أفضل للكلمات

            for (let i = 0; i < words.length; i++) {
                const word = words[i];
                const testLine = currentLine.length === 0 ? word : currentLine + " " + word;
                const metrics = context.measureText(testLine);

                if (metrics.width > maxLineWidth && currentLine.length > 0) {
                    lines.push(currentLine);
                    currentLine = word;
                } else {
                    currentLine = testLine;
                }
            }
            lines.push(currentLine); // إضافة السطر الأخير

            lines.forEach(line => {
                context.fillText(line, x, y);
                y += lineHeight;
            });
            return y;
        }

        ctx.font = primaryFont;
        ctx.textAlign = 'right';
        yPosition = wrapText(ctx, verseText, canvasWidth - padding, yPosition, maxWidth, 45);

        ctx.font = referenceFont;
        ctx.textAlign = 'left';
        yPosition = Math.min(yPosition + 40, canvasHeight - padding - 40); // تأكد من بقاء المرجع داخل الصورة
        ctx.fillText(verseReference, padding, yPosition);

        ctx.font = watermarkFont;
        ctx.fillStyle = 'rgba(255, 255, 255, 0.6)';
        ctx.textAlign = 'right';
        const watermarkText = IMAGE_GENERATOR_STRINGS.website_credit || 'اسم الموقع';
        ctx.fillText(watermarkText, canvasWidth - padding, canvasHeight - padding + 10);

        if ($imageContainer.length) {
            $imageContainer.html('');
            const imgElement = $('<img>', {
                src: canvas.toDataURL('image/png'),
                alt: verseReference,
                css: { 'max-width': '100%', 'border-radius': '8px', 'margin-top': '15px' }
            });
            const downloadLink = $('<a>', {
                href: imgElement.attr('src'),
                download: `verse-${verseReference.replace(/[:\s]/g, '_')}.png`,
                text: IMAGE_GENERATOR_STRINGS.download_image || 'تحميل الصورة',
                class: 'bible-control-button download-image-button',
                css: { 'display': 'block', 'text-align': 'center', 'margin-top': '10px' }
            });
            $imageContainer.append(imgElement).append(downloadLink);
        }
    }
    
    // --- AJAX لاختيار السفر والأصحاح (من bible-ajax.js سابقاً، تم دمجه هنا) ---
    const $bookSelect = $('#bible-book-select');
    const $chapterSelect = $('#bible-chapter-select');
    const $versesDisplay = $('#bible-verses-display'); // حاوية عرض الآيات الرئيسية
    const $initialPrompt = $versesDisplay.find('.bible-select-prompt');

    function showLoadingInVersesDisplay() {
        if ($initialPrompt.length > 0) $initialPrompt.hide();
        $versesDisplay.html(`<p class="bible-loading-message"><i class="fas fa-spinner fa-spin"></i> ${BIBLE_STRINGS.loading || 'جارٍ التحميل...'}</p>`);
    }

    function updatePageDetails(title, description, bookSlug, chapterNum) {
        document.title = title;
        $('meta[name="description"]').attr('content', description);
        if (bookSlug && chapterNum) {
            const safeBookSlug = String(bookSlug).replace(/\s+/g, '-');
            const newUrl = BASE_URL + encodeURIComponent(safeBookSlug) + '/' + chapterNum + '/';
            try {
                history.pushState({ book: bookSlug, chapter: chapterNum }, title, newUrl);
            } catch (e) { console.error("Error in history.pushState: ", e); }
        }
    }

    $bookSelect.on('change', function() {
        const selectedBook = $(this).val();
        $chapterSelect.empty().append(`<option value="">${BIBLE_STRINGS.selectChapter || 'اختر الأصحاح'}</option>`);
        
        if (!selectedBook) {
            $chapterSelect.prop('disabled', true);
            if ($initialPrompt.length > 0) {
                $versesDisplay.html($initialPrompt.show());
            } else {
                $versesDisplay.html(`<p class="bible-select-prompt">${BIBLE_STRINGS.pleaseSelectBookAndChapter || 'يرجى اختيار السفر ثم الأصحاح.'}</p>`);
            }
            updatePageDetails(BIBLE_STRINGS.mainPageTitle || document.title, BIBLE_STRINGS.mainPageDescription || '', '', '');
            return;
        }

        $chapterSelect.prop('disabled', true);
        showLoadingInVersesDisplay();

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
                    if ($initialPrompt.length > 0) {
                         $versesDisplay.html($initialPrompt.show());
                    } else {
                        $versesDisplay.html(`<p class="bible-select-prompt">${BIBLE_STRINGS.pleaseSelectChapter || 'يرجى اختيار الأصحاح.'}</p>`);
                    }
                } else {
                    const errorMsg = (response.data && response.data.message) ? response.data.message : (BIBLE_STRINGS.noChaptersFound || 'لم يتم العثور على أصحاحات.');
                    $versesDisplay.html(`<p class="bible-error-message">${errorMsg}</p>`);
                }
            },
            error: function() {
                $versesDisplay.html(`<p class="bible-error-message">${BIBLE_STRINGS.errorLoadingChaptersAjax || 'خطأ في تحميل الأصحاحات.'}</p>`);
                $chapterSelect.prop('disabled', true);
            }
        });
    });

    $chapterSelect.on('change', function() {
        const selectedBook = $bookSelect.val();
        const selectedChapter = $(this).val();
        if (!selectedBook || !selectedChapter) {
            if ($initialPrompt.length > 0) $versesDisplay.html($initialPrompt.show());
            else $versesDisplay.html(`<p class="bible-select-prompt">${BIBLE_STRINGS.pleaseSelectBookAndChapter || 'يرجى اختيار السفر والأصحاح.'}</p>`);
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
                    updatePageDetails(response.data.title, response.data.description, response.data.book, response.data.chapter);
                } else {
                    const errorMsg = (response.data && response.data.message) ? response.data.message : (BIBLE_STRINGS.errorLoadingVerses || 'خطأ في تحميل الآيات.');
                    $versesDisplay.html(`<p class="bible-error-message">${errorMsg}</p>`);
                }
            },
            error: function() {
                $versesDisplay.html(`<p class="bible-error-message">${BIBLE_STRINGS.errorLoadingVersesAjax || 'خطأ في تحميل الآيات.'}</p>`);
            }
        });
    });

}); // End jQuery $(document).ready
