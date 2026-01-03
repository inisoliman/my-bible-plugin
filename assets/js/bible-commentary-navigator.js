jQuery(document).ready(function($) {
    let currentSource = '';
    let currentBook = '';
    
    const strings = commentaryNavigator || {};
    const ajaxUrl = strings.ajax_url || '/wp-admin/admin-ajax.php';
    const nonce = strings.nonce || '';
    
    // Helper: Create slug from Arabic text
    function createSlug(text) {
        if (!text) return '';
        let slug = text.trim();
        // Remove diacritics
        slug = slug.replace(/[\u064B-\u065F\u0670\u06D6-\u06ED]/g, '');
        // Replace spaces with hyphens
        slug = slug.replace(/\s+/g, '-');
        // Remove special characters except Arabic letters, numbers, and hyphens
        slug = slug.replace(/[^a-zA-Z0-9\u0600-\u06FF\-]/g, '');
        return encodeURIComponent(slug);
    }
    
    // Show specific level
    function showLevel(levelId) {
        $('.navigator-level').removeClass('active');
        $('#' + levelId + '-level').addClass('active');
        
        // Scroll to top
        $('html, body').animate({ scrollTop: 0 }, 300);
    }
    
    // Load books for selected source
    function loadBooks(source) {
        currentSource = source;
        
        const sourceName = $('.source-card[data-source="' + source + '"] .source-name').text();
        $('#current-source-name').text(sourceName);
        
        const $container = $('#books-container');
        $container.html('<div class="loading-message"><i class="fas fa-spinner fa-spin"></i><p>' + (strings.loading || 'جارٍ التحميل...') + '</p></div>');
        
        showLevel('books');
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_commentary_books',
                source: source,
                nonce: nonce
            },
            success: function(response) {
                if (response.success && response.data.books) {
                    displayBooks(response.data.books);
                } else {
                    $container.html('<div class="error-message"><i class="fas fa-exclamation-circle"></i><p>' + (response.data.message || strings.no_books || 'لا توجد أسفار') + '</p></div>');
                }
            },
            error: function() {
                $container.html('<div class="error-message"><i class="fas fa-exclamation-circle"></i><p>' + (strings.error || 'حدث خطأ') + '</p></div>');
            }
        });
    }
    
    // Display books grid
    function displayBooks(books) {
        const $container = $('#books-container');
        let html = '';
        
        books.forEach(function(book) {
            html += '<div class="book-card" data-book="' + book + '">';
            html += '<div class="book-icon"><i class="fas fa-book"></i></div>';
            html += '<h3 class="book-name">' + book + '</h3>';
            html += '<button class="book-button bible-control-button">';
            html += '<i class="fas fa-arrow-left"></i>';
            html += '<span>عرض الأصحاحات</span>';
            html += '</button>';
            html += '</div>';
        });
        
        $container.html(html);
        
        // Reset filters and update counter
        $('.filter-btn').removeClass('active');
        $('.filter-btn[data-testament="all"]').addClass('active');
        $('#books-search').val('');
        updateBooksCounter();
    }
    
    // Load chapters for selected book
    function loadChapters(book) {
        currentBook = book;
        
        $('#current-book-name').text(book);
        
        const $container = $('#chapters-container');
        $container.html('<div class="loading-message"><i class="fas fa-spinner fa-spin"></i><p>' + (strings.loading || 'جارٍ التحميل...') + '</p></div>');
        
        showLevel('chapters');
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_commentary_chapters',
                source: currentSource,
                book: book,
                nonce: nonce
            },
            success: function(response) {
                if (response.success && response.data.chapters) {
                    displayChapters(response.data.chapters);
                } else {
                    $container.html('<div class="error-message"><i class="fas fa-exclamation-circle"></i><p>' + (response.data.message || strings.no_chapters || 'لا توجد أصحاحات') + '</p></div>');
                }
            },
            error: function() {
                $container.html('<div class="error-message"><i class="fas fa-exclamation-circle"></i><p>' + (strings.error || 'حدث خطأ') + '</p></div>');
            }
        });
    }
    
    // Display chapters grid
    function displayChapters(chapters) {
        const $container = $('#chapters-container');
        let html = '';
        
        chapters.forEach(function(chapter) {
            const bookSlug = createSlug(currentBook);
            const url = '/bible-commentary/' + currentSource + '/' + bookSlug + '/' + chapter + '/';
            
            html += '<a href="' + url + '" class="chapter-card">';
            html += '<div class="chapter-number">' + chapter + '</div>';
            html += '<div class="chapter-label">الأصحاح</div>';
            html += '</a>';
        });
        
        $container.html(html);
    }
    
    // Event: Click on source card
    $(document).on('click', '.source-card .source-button', function(e) {
        e.preventDefault();
        const source = $(this).closest('.source-card').data('source');
        loadBooks(source);
    });
    
    // Event: Click on book card
    $(document).on('click', '.book-card .book-button', function(e) {
        e.preventDefault();
        const book = $(this).closest('.book-card').data('book');
        loadChapters(book);
    });
    
    // Event: Back buttons
    $(document).on('click', '.back-button', function(e) {
        e.preventDefault();
        const backTo = $(this).data('back-to');
        showLevel(backTo);
    });
    
    // Event: Search in books
    $(document).on('input', '#books-search', function() {
        filterBooks();
    });
    
    // Event: Filter by testament
    $(document).on('click', '.filter-btn', function() {
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');
        filterBooks();
    });
    
    // Function: Filter books based on search and testament
    function filterBooks() {
        const searchTerm = $('#books-search').val().trim().toLowerCase();
        const testament = $('.filter-btn.active').data('testament');
        const $bookCards = $('.book-card');
        let visibleCount = 0;
        
        // قائمة أسفار العهد القديم (بالترتيب الصحيح - 46 سفر)
        const oldTestamentBooks = [
            // أسفار موسى الخمسة (التوراة)
            'التكوين', 'تكوين',
            'الخروج', 'خروج',
            'اللاويين', 'لاويين',
            'العدد', 'عدد',
            'التثنية', 'تثنية',
            
            // الأسفار التاريخية
            'يشوع', 'يوشع',
            'القضاة', 'قضاة',
            'راعوث', 'روث',
            'صموئيل الأول', '1 صموئيل', 'صموئيل 1',
            'صموئيل الثاني', '2 صموئيل', 'صموئيل 2',
            'الملوك الأول', '1 ملوك', 'ملوك 1',
            'الملوك الثاني', '2 ملوك', 'ملوك 2',
            'أخبار الأيام الأول', '1 أخبار', 'أخبار 1', 'اخبار الايام الاول',
            'أخبار الأيام الثاني', '2 أخبار', 'أخبار 2', 'اخبار الايام الثاني',
            'عزرا',
            'نحميا',
            'طوبيا', 'طوبيت',
            'يهوديت',
            'أستير', 'استير',
            'المكابيين الأول', '1 مكابيين', 'مكابيين 1',
            'المكابيين الثاني', '2 مكابيين', 'مكابيين 2',
            
            // أسفار الحكمة والشعر
            'أيوب',
            'المزامير', 'مزامير',
            'الأمثال', 'امثال',
            'الجامعة', 'جامعة',
            'نشيد الأنشاد', 'نشيد',
            'الحكمة', 'حكمة سليمان',
            'يشوع بن سيراخ', 'ابن سيراخ', 'سيراخ',
            
            // الأنبياء الكبار
            'إشعياء', 'اشعياء', 'إشعيا', 'اشعيا',
            'إرميا', 'ارميا', 'إرمياء', 'ارمياء',
            'مراثي إرميا', 'مراثي', 'مراثي ارميا',
            'باروخ',
            'حزقيال', 'حزقيل',
            'دانيال', 'دانيال النبي',
            
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
        ];
        
        // قائمة أسفار العهد الجديد (بالترتيب الصحيح - 27 سفر)
        const newTestamentBooks = [
            // الأناجيل
            'متى', 'انجيل متى',
            'مرقس', 'انجيل مرقس',
            'لوقا', 'انجيل لوقا',
            'يوحنا', 'انجيل يوحنا',
            
            // أعمال الرسل
            'أعمال الرسل', 'اعمال', 'اعمال الرسل', 'أع',
            
            // رسائل بولس الرسول
            'رومية', 'الرومية',
            'كورنثوس الأولى', '1 كورنثوس', 'كورنثوس 1',
            'كورنثوس الثانية', '2 كورنثوس', 'كورنثوس 2',
            'غلاطية', 'الغلاطية',
            'أفسس', 'افسس',
            'فيلبي',
            'كولوسي',
            'تسالونيكي الأولى', '1 تسالونيكي', 'تسالونيكي 1',
            'تسالونيكي الثانية', '2 تسالونيكي', 'تسالونيكي 2',
            'تيموثاوس الأولى', '1 تيموثاوس', 'تيموثاوس 1',
            'تيموثاوس الثانية', '2 تيموثاوس', 'تيموثاوس 2',
            'تيطس',
            'فليمون',
            'العبرانيين', 'عبرانيين',
            
            // الرسائل الجامعة
            'يعقوب',
            'بطرس الأولى', '1 بطرس', 'بطرس 1',
            'بطرس الثانية', '2 بطرس', 'بطرس 2',
            'يوحنا الأولى', '1 يوحنا', 'يوحنا 1',
            'يوحنا الثانية', '2 يوحنا', 'يوحنا 2',
            'يوحنا الثالثة', '3 يوحنا', 'يوحنا 3',
            'يهوذا',
            
            // سفر الرؤيا
            'الرؤيا', 'رؤيا', 'رؤيا يوحنا'
        ];
        
        $bookCards.each(function() {
            const $card = $(this);
            const bookName = $card.data('book');
            const bookNameLower = bookName.toLowerCase();
            
            let showCard = true;
            
            // Filter by search term
            if (searchTerm !== '' && !bookNameLower.includes(searchTerm)) {
                showCard = false;
            }
            
            // Filter by testament
            if (testament !== 'all') {
                const isOldTestament = oldTestamentBooks.some(book => {
                    const bookLower = book.toLowerCase();
                    return bookNameLower.includes(bookLower) || bookLower.includes(bookNameLower);
                });
                
                const isNewTestament = newTestamentBooks.some(book => {
                    const bookLower = book.toLowerCase();
                    return bookNameLower.includes(bookLower) || bookLower.includes(bookNameLower);
                });
                
                if (testament === 'old' && !isOldTestament) {
                    showCard = false;
                } else if (testament === 'new' && !isNewTestament) {
                    showCard = false;
                }
            }
            
            if (showCard) {
                $card.show();
                visibleCount++;
            } else {
                $card.hide();
            }
        });
        
        // Update counter
        $('#books-count').text(visibleCount);
    }
    
    // Update counter when books are loaded
    function updateBooksCounter() {
        const visibleCount = $('.book-card:visible').length;
        $('#books-count').text(visibleCount);
    }
});
