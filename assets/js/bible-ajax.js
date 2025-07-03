// assets/js/bible-ajax.js
// Define these variables and functions at the global scope so they can be used everywhere
let bookSelect, chapterSelect, versesDisplay, initialPrompt, bibleStrings, bibleAjax;

function showLoading() {
    if (initialPrompt.length > 0) {
        initialPrompt.hide();
    }
    versesDisplay.html('<p class="bible-loading-message"><i class="fas fa-spinner fa-spin"></i> ' + (bibleStrings.loading || 'جارٍ التحميل...') + '</p>');
}

/*
function updatePageDetails(title, description, bookSlug, chapterNum) {
    document.title = title;
    document.querySelector('meta[name="description"]').setAttribute('content', description);
    
    const newUrl = `${BASE_URL}${bookSlug}/${chapterNum}/`;
    window.history.pushState({ path: newUrl }, '', newUrl);
}
*/
jQuery(document).ready(function($) {
    // Initialize the global variables
    bibleAjax = {
        ajax_url: (typeof bibleFrontend !== 'undefined' && bibleFrontend.ajax_url) ? bibleFrontend.ajax_url : '/wp-admin/admin-ajax.php',
        nonce: (typeof bibleFrontend !== 'undefined' && bibleFrontend.nonce) ? bibleFrontend.nonce : '',
        base_url: (typeof bibleFrontend !== 'undefined' && bibleFrontend.base_url) ? bibleFrontend.base_url : '/bible/'
    };
    
    bookSelect = $('#bible-book-select');
    chapterSelect = $('#bible-chapter-select');
    versesDisplay = $('#bible-verses-display');
    initialPrompt = versesDisplay.find('.bible-select-prompt');
    
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
    $('.chapterSelect').on('change', function() {
        const bookSlug = $(this).data('book-slug');
        const chapterNum = $(this).val();
        
        // الحصول على عنوان ووصف جديد
        const title = `الكتاب المقدس - ${bookSlug} ${chapterNum}`;
        const description = `اقرأ ${bookSlug} أصحاح ${chapterNum} من الكتاب المقدس`;
        
        // تحديث تفاصيل الصفحة والرابط
        updatePageDetails(title, description, bookSlug, chapterNum);
        
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

// Add this after the chapter select change handler
// Handle navigation button clicks - FIXED VERSION
$(document).on('click', '.prev-chapter-link, .next-chapter-link', function(e) {
    e.preventDefault();
    
    const url = $(this).attr('href');
    const urlParts = url.split('/');
    
    // Filter out empty elements from the URL parts
    const filteredParts = urlParts.filter(part => part.trim() !== '');
    
    // Get the book slug and chapter number from the filtered URL parts
    const chapterNum = filteredParts[filteredParts.length - 1] || '';
    const bookSlug = filteredParts[filteredParts.length - 2] || '';
    
    if (bookSlug && chapterNum) {
        // Load the chapter content via AJAX
        showLoading();
        
        $.ajax({
            url: bibleAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'bible_get_verses',
                book: decodeURIComponent(bookSlug),
                chapter: chapterNum,
                nonce: bibleAjax.nonce
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && response.data.html) {
                    versesDisplay.html(response.data.html);
                    // Update the page details including URL
                    updatePageDetails(response.data.title, response.data.description, bookSlug, chapterNum);
                    
                    // Update the dropdown selections if they exist
                    if (bookSelect.length && chapterSelect.length) {
                        // Find the book option that matches
                        const bookOption = bookSelect.find('option').filter(function() {
                            const optionSlug = $(this).val().replace(/\s+/g, '-');
                            return optionSlug === decodeURIComponent(bookSlug);
                        });
                        
                        if (bookOption.length) {
                            bookSelect.val(bookOption.val());
                            
                            // Load chapters for this book if needed
                            if (chapterSelect.find('option').length <= 1) {
                                // Trigger book change to load chapters
                                bookSelect.trigger('change');
                                
                                // Set a timeout to select the chapter after chapters are loaded
                                setTimeout(function() {
                                    chapterSelect.val(chapterNum);
                                }, 500);
                            } else {
                                // Just update the chapter selection
                                chapterSelect.val(chapterNum);
                            }
                        }
                    } else {
                        const errorMessage = response.data && response.data.message ? response.data.message : (bibleStrings.errorLoadingVerses || 'حدث خطأ أثناء تحميل الآيات.');
                        versesDisplay.html('<p class="bible-error-message">' + errorMessage + '</p>');
                    }
                },
                error: function() {
                    versesDisplay.html('<p class="bible-error-message">' + (bibleStrings.errorLoadingVersesAjax || 'خطأ في الاتصال (آيات). حاول مرة أخرى.') + '</p>');
                }
            });
        }
    });
});
