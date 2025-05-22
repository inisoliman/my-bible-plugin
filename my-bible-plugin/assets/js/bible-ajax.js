// assets/js/bible-ajax.js
jQuery(document).ready(function($) {
    const bookSelect = $('#bible-book-select');
    const chapterSelect = $('#bible-chapter-select');
    const versesDisplay = $('#bible-verses-display');
    const initialPrompt = versesDisplay.find('.bible-select-prompt'); // الرسالة الأولية

    function showLoading() {
        if (initialPrompt.length > 0) {
            initialPrompt.hide(); // إخفاء الرسالة الأولية إذا كانت موجودة
        }
        versesDisplay.html('<p class="bible-loading-message"><i class="fas fa-spinner fa-spin"></i> ' + (bibleStrings.loading || 'جارٍ التحميل...') + '</p>');
    }

    function updatePageDetails(title, description, bookSlug, chapterNum) {
        document.title = title;
        $('meta[name="description"]').attr('content', description);
        
        if (bookSlug && chapterNum) {
            // التأكد من أن bookSlug لا يحتوي على مسافات، بل واصلات
            const safeBookSlug = String(bookSlug).replace(/\s+/g, '-');
            const newUrl = bibleAjax.base_url + encodeURIComponent(safeBookSlug) + '/' + chapterNum + '/';
            try {
                history.pushState({book: bookSlug, chapter: chapterNum}, title, newUrl);
            } catch (e) {
                // console.error("Error in history.pushState: ", e);
                // قد يحدث خطأ إذا كان المستند من أصل مختلف (نادر في هذا السياق)
            }
        }
    }
    
    // عند تغيير السفر
    bookSelect.on('change', function() {
        const selectedBook = $(this).val();
        chapterSelect.empty().append('<option value="">' + (bibleStrings.selectChapter || 'اختر الأصحاح') + '</option>');
        
        if (!selectedBook) {
            chapterSelect.prop('disabled', true);
            if (initialPrompt.length > 0) {
                versesDisplay.html(initialPrompt); // إظهار الرسالة الأولية مرة أخرى
                initialPrompt.show();
            } else {
                versesDisplay.html('<p class="bible-select-prompt">' + (bibleStrings.pleaseSelectBookAndChapter || 'يرجى اختيار السفر ثم الأصحاح لعرض الآيات.') + '</p>');
            }
            // مسح تفاصيل الصفحة إذا تم إلغاء اختيار السفر
             updatePageDetails(bibleStrings.mainPageTitle || document.title, bibleStrings.mainPageDescription || '', '', '');
            return;
        }

        chapterSelect.prop('disabled', true); // تعطيل حتى يتم تحميل الأصحاحات
        showLoading();

        $.ajax({
            url: bibleAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'bible_get_chapters', // اسم الأكشن في PHP
                book: selectedBook,
                nonce: bibleAjax.nonce // إرسال Nonce
            },
            dataType: 'json', // نتوقع رد JSON
            success: function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    $.each(response.data, function(index, chapter) {
                        chapterSelect.append('<option value="' + chapter + '">' + chapter + '</option>');
                    });
                    chapterSelect.prop('disabled', false);
                    if (initialPrompt.length > 0) {
                         versesDisplay.html(initialPrompt); 
                         initialPrompt.show();
                    } else {
                        versesDisplay.html('<p class="bible-select-prompt">' + (bibleStrings.pleaseSelectChapter || 'يرجى اختيار الأصحاح.') + '</p>');
                    }
                } else if (response.success && response.data && response.data.length === 0) {
                    versesDisplay.html('<p class="bible-error-message">' + (bibleStrings.noChaptersFound || 'لم يتم العثور على أصحاحات لهذا السفر.') + '</p>');
                } 
                else {
                    const errorMessage = response.data && response.data.message ? response.data.message : (bibleStrings.errorLoadingChapters || 'حدث خطأ أثناء تحميل الأصحاحات.');
                    versesDisplay.html('<p class="bible-error-message">' + errorMessage + '</p>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // console.error('AJAX Error (get_chapters):', textStatus, errorThrown, jqXHR.responseText);
                versesDisplay.html('<p class="bible-error-message">' + (bibleStrings.errorLoadingChaptersAjax || 'خطأ في الاتصال (أصحاحات). حاول مرة أخرى.') + '</p>');
                chapterSelect.prop('disabled', true);
            }
        });
    });

    // عند تغيير الأصحاح
    chapterSelect.on('change', function() {
        const selectedBook = bookSelect.val();
        const selectedChapter = $(this).val();

        if (!selectedBook || !selectedChapter) {
            if (initialPrompt.length > 0) {
                 versesDisplay.html(initialPrompt);
                 initialPrompt.show();
            } else {
                versesDisplay.html('<p class="bible-select-prompt">' + (bibleStrings.pleaseSelectBookAndChapter || 'يرجى اختيار السفر ثم الأصحاح لعرض الآيات.') + '</p>');
            }
            return;
        }
        
        showLoading();

        $.ajax({
            url: bibleAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'bible_get_verses', // اسم الأكشن في PHP
                book: selectedBook,
                chapter: selectedChapter,
                nonce: bibleAjax.nonce // إرسال Nonce
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && response.data.html) {
                    versesDisplay.html(response.data.html);
                    // تحديث عنوان الصفحة والوصف والرابط
                    updatePageDetails(response.data.title, response.data.description, response.data.book, response.data.chapter);
                } else {
                    const errorMessage = response.data && response.data.message ? response.data.message : (bibleStrings.errorLoadingVerses || 'حدث خطأ أثناء تحميل الآيات.');
                    versesDisplay.html('<p class="bible-error-message">' + errorMessage + '</p>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // console.error('AJAX Error (get_verses):', textStatus, errorThrown, jqXHR.responseText);
                versesDisplay.html('<p class="bible-error-message">' + (bibleStrings.errorLoadingVersesAjax || 'خطأ في الاتصال (آيات). حاول مرة أخرى.') + '</p>');
            }
        });
    });

    // تمرير بعض النصوص المترجمة من PHP (إذا لم تكن موجودة بالفعل في bibleAjax)
    // هذا يعتمد على كيفية إعداد wp_localize_script
    const bibleStrings = window.bibleAjaxLocalizedStrings || {
        loading: 'جارٍ التحميل...',
        selectChapter: 'اختر الأصحاح',
        pleaseSelectBookAndChapter: 'يرجى اختيار السفر ثم الأصحاح لعرض الآيات.',
        pleaseSelectChapter: 'يرجى اختيار الأصحاح.',
        noChaptersFound: 'لم يتم العثور على أصحاحات لهذا السفر.',
        errorLoadingChapters: 'حدث خطأ أثناء تحميل الأصحاحات.',
        errorLoadingChaptersAjax: 'خطأ في الاتصال (أصحاحات). حاول مرة أخرى.',
        errorLoadingVerses: 'حدث خطأ أثناء تحميل الآيات.',
        errorLoadingVersesAjax: 'خطأ في الاتصال (آيات). حاول مرة أخرى.',
        mainPageTitle: document.title, // عنوان الصفحة الأصلي
        mainPageDescription: $('meta[name="description"]').attr('content') || '' // الوصف الأصلي
    };
     // إذا كان هناك سفر وأصحاح محددان مسبقاً عند تحميل الصفحة (من الرابط المباشر)
    // تأكد من أن القوائم المنسدلة تعكس هذا التحديد
    // وأن الأصحاحات للسفر المحدد محملة في قائمة الأصحاحات
    // هذا الجزء تم التعامل معه بشكل أفضل في shortcodes.php الآن لعرض المحتوى الأولي
    // ولكن يمكن إضافة منطق هنا لتحديث الواجهة إذا لزم الأمر بعد تحميل الصفحة بالكامل
    // if (bookSelect.val() && chapterSelect.children('option:selected').val() === "") {
    //     // إذا كان هناك سفر محدد ولكن لم يتم اختيار أصحاح (أو قائمة الأصحاحات فارغة)
    //     // قم بتشغيل حدث 'change' على قائمة الأسفار لمحاولة تحميل الأصحاحات
    //     bookSelect.trigger('change');
    // }


});
