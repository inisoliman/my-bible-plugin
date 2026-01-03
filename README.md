# 📖 My Bible Plugin - إضافة الكتاب المقدس الشاملة

[![Version](https://img.shields.io/badge/version-3.4.1-blue.svg)](https://github.com/yourusername/my-bible-plugin)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)](LICENSE)

إضافة ووردبريس متكاملة لعرض الكتاب المقدس باللغة العربية مع تفاسير متعددة، بحث متقدم، قاموس مصطلحات، وميزات تفاعلية متقدمة.

## ✨ المميزات الرئيسية

### 📚 عرض الكتاب المقدس

- ✅ عرض جميع أسفار العهدين القديم والجديد
- ✅ تنقل سلس بين الأسفار والأصحاحات
- ✅ فلتر العهد (القديم/الجديد/الكل)
- ✅ روابط SEO-friendly للأسفار والأصحاحات
- ✅ دعم الآيات الفردية والأصحاحات الكاملة

### 📝 التفاسير

- ✅ **ثلاثة مصادر للتفاسير:**
  - القمص أنطونيوس فكري (af)
  - القمص تادرس يعقوب ملطي (ty)
  - كنيسة مارمرقس مصر الجديدة (sm)
- ✅ **Lazy Loading** للتفاسير الطويلة
- ✅ فهرس تفاعلي (TOC) للتنقل السريع
- ✅ زر "قراءة الأصحاح" للانتقال للكتاب المقدس
- ✅ تحويل تلقائي لأسماء الأسفار بين النظامين

### 🔍 البحث المتقدم

- ✅ بحث في النص الكامل
- ✅ بحث بالشواهد (مثال: يوحنا 3:16)
- ✅ فلتر البحث حسب العهد
- ✅ ترقيم الصفحات للنتائج
- ✅ إزالة التشكيل تلقائياً للبحث الدقيق

### 📖 القاموس

- ✅ قاموس مصطلحات كتابية شامل
- ✅ روابط تلقائية للمصطلحات في النصوص
- ✅ نافذة منبثقة لعرض التعريفات
- ✅ أكثر من 1000 مصطلح

### 🎨 الميزات التفاعلية

- ✅ **الوضع الليلي** (Dark Mode)
- ✅ **التحكم في حجم الخط** (3 أحجام)
- ✅ **إزالة/إضافة التشكيل**
- ✅ **القراءة الصوتية** (Text-to-Speech)
- ✅ **إنشاء صور للآيات** للمشاركة
- ✅ **نسخ الآيات مع المرجع** تلقائياً

### 🗺️ SEO و Sitemap

- ✅ خريطة موقع XML مخصصة
- ✅ عناوين ووصف meta محسّنة
- ✅ روابط canonical
- ✅ Open Graph tags
- ✅ Schema.org markup

### ⚡ الأداء

- ✅ تحميل AJAX للمحتوى
- ✅ Lazy Loading للتفاسير
- ✅ تخزين مؤقت (Caching)
- ✅ تحسين استعلامات قاعدة البيانات

---

## 📦 التثبيت

### المتطلبات

- WordPress 5.0 أو أحدث
- PHP 7.4 أو أحدث
- MySQL 5.6 أو أحدث

### خطوات التثبيت

1. **تحميل الإضافة:**

   ```bash
   cd wp-content/plugins/
   git clone https://github.com/inisoliman/my-bible-plugin.git
   ```

2. **تفعيل الإضافة:**

   - اذهب إلى لوحة تحكم ووردبريس
   - الإضافات → الإضافات المثبتة
   - ابحث عن "My Bible Plugin"
   - اضغط "تفعيل"

3. **استيراد قاعدة البيانات:**

   - قم باستيراد ملف SQL للكتاب المقدس
   - قم باستيراد ملف SQL للتفاسير (اختياري)

4. **إنشاء الصفحات:**
   - أنشئ صفحة جديدة بعنوان "الكتاب المقدس" (slug: `bible`)
   - أنشئ صفحة جديدة بعنوان "فهرس الكتاب المقدس" (slug: `bible-index`)
   - أنشئ صفحة جديدة بعنوان "التفاسير" (slug: `bible-commentary`)
   - أنشئ صفحة جديدة بعنوان "فهرس التفاسير" (slug: `tafser`)

---

## 🎯 الاستخدام

### الشورت كودات (Shortcodes)

#### 1. عرض الكتاب المقدس

```php
[bible_content]
```

**المعاملات (Parameters):**

- `book` - اسم السفر (اختياري)
- `chapter` - رقم الأصحاح (اختياري)
- `testament` - العهد: `all`, `العهد القديم`, `العهد الجديد` (اختياري)

**أمثلة:**

```php
// عرض سفر التكوين الأصحاح 1
[bible_content book="تكوين" chapter="1"]

// عرض العهد الجديد فقط
[bible_content testament="العهد الجديد"]
```

---

#### 2. البحث في الكتاب المقدس

```php
[bible_search]
```

**المعاملات:**

- لا توجد معاملات - يعرض نموذج البحث

**مثال:**

```php
[bible_search]
```

---

#### 3. فهرس الأسفار

```php
[bible_index]
```

**الوصف:** يعرض قائمة بجميع أسفار الكتاب المقدس مقسمة حسب العهد

---

#### 4. آية عشوائية

```php
[random_verse]
```

**الوصف:** يعرض آية عشوائية من الكتاب المقدس

**ملاحظة:** يمكن تحديد سفر معين من الإعدادات

---

#### 5. آية اليوم

```php
[daily_verse]
```

**الوصف:** يعرض آية اليوم (تتغير يومياً)

**ملاحظة:** يتم تخزين الآية مؤقتاً لمدة 24 ساعة

---

#### 6. فهرس التفاسير

```php
[commentary_navigator]
```

**الوصف:** يعرض واجهة تفاعلية لتصفح التفاسير حسب المصدر والسفر والأصحاح

---

### الروابط الجميلة (Pretty URLs)

#### الكتاب المقدس:

```
/bible/                          # الصفحة الرئيسية
/bible/تكوين/                    # قائمة أصحاحات سفر التكوين
/bible/تكوين/1/                  # الأصحاح 1 من سفر التكوين
/bible/تكوين/1/1/                # الآية 1 من الأصحاح 1
```

#### التفاسير:

```
/bible-commentary/af/تكوين/1/    # تفسير القمص أنطونيوس فكري - تكوين 1
/bible-commentary/ty/يوحنا/3/    # تفسير القمص تادرس - يوحنا 3
/bible-commentary/sm/رومية/8/    # تفسير كنيسة مارمرقس - رومية 8
```

---

## ⚙️ الإعدادات

### إعدادات الآيات العشوائية واليومية

1. اذهب إلى: **الإعدادات → الكتاب المقدس**
2. اختر السفر المفضل للآيات العشوائية واليومية
3. احفظ التغييرات

---

## 🗄️ هيكل قاعدة البيانات

### جداول الكتاب المقدس

#### `wp_bible_verses`

```sql
CREATE TABLE `wp_bible_verses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `book` varchar(100) NOT NULL,
  `chapter` int(11) NOT NULL,
  `verse` int(11) NOT NULL,
  `text` text NOT NULL,
  `testament` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `book_chapter` (`book`, `chapter`),
  KEY `testament` (`testament`)
);
```

#### `wp_bible_commentaries`

```sql
CREATE TABLE `wp_bible_commentaries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `book_name` varchar(100) NOT NULL,
  `chapter` int(11) NOT NULL,
  `source_id` varchar(10) NOT NULL,
  `commentary_text` longtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `book_chapter_source` (`book_name`, `chapter`, `source_id`)
);
```

#### `wp_bible_dictionary`

```sql
CREATE TABLE `wp_bible_dictionary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `term` varchar(255) NOT NULL,
  `definition` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `term` (`term`)
);
```

---

## 🎨 التخصيص

### CSS Classes الرئيسية

```css
/* الحاوية الرئيسية */
.bible-content-area {
}

/* الآيات */
.verse-text {
}
.verse-number {
}
.text-content {
}

/* أزرار التحكم */
.bible-controls {
}
.bible-control-button {
}

/* الوضع الليلي */
.dark-mode {
}

/* التفاسير */
.bible-commentary-wrapper {
}
.commentary-content {
}
.commentary-toc {
}
```

### تخصيص الألوان

يمكنك تجاوز الألوان الافتراضية في ملف CSS الخاص بقالبك:

```css
:root {
  --bible-primary-color: #2c3e50;
  --bible-secondary-color: #3498db;
  --bible-text-color: #333;
  --bible-bg-color: #fff;
}
```

---

## 🔧 الدوال المساعدة (Helper Functions)

### PHP Functions

```php
// تحويل اسم السفر إلى slug
my_bible_create_book_slug($book_name)

// الحصول على اسم السفر من slug
my_bible_get_book_name_from_slug($slug)

// تحويل اسم السفر من نظام الكتاب المقدس إلى نظام التفاسير
my_bible_convert_to_commentary_book_name($book_name)

// تحويل اسم السفر من نظام التفاسير إلى نظام الكتاب المقدس
my_bible_convert_from_commentary_book_name($commentary_book_name)

// الحصول على ترتيب الأسفار حسب العهد
my_bible_get_book_order_from_db($testament)
```

### JavaScript Functions

```javascript
// إزالة التشكيل من النص العربي
removeArabicTashkeel(text);

// تغيير حجم الخط
changeFontSize(action); // 'increase', 'decrease', 'reset'

// تبديل التشكيل
toggleTashkeel();

// القراءة الصوتية
handleReadAloud();

// إنشاء صورة للآية
generateVerseImage();
```

---

## 📊 الأداء والتحسين

### نصائح لتحسين الأداء

1. **استخدم Plugin للتخزين المؤقت:**

   - WP Super Cache
   - W3 Total Cache

2. **تحسين قاعدة البيانات:**

   - أضف indexes على الأعمدة المستخدمة في البحث
   - نظف البيانات القديمة بانتظام

3. **CDN للملفات الثابتة:**

   - استخدم CDN لملفات CSS و JavaScript

4. **تحسين الصور:**
   - ضغط الصور المستخدمة في الواجهة

---

## 🐛 استكشاف الأخطاء

### المشاكل الشائعة

#### 1. لا تظهر الآيات

**الحل:**

- تأكد من استيراد قاعدة البيانات بشكل صحيح
- تحقق من أن جدول `wp_bible_verses` يحتوي على بيانات

#### 2. الروابط الجميلة لا تعمل

**الحل:**

- اذهب إلى: الإعدادات → الروابط الدائمة
- احفظ التغييرات (حتى بدون تغيير)

#### 3. التفاسير لا تحمل

**الحل:**

- تأكد من استيراد جدول `wp_bible_commentaries`
- تحقق من أن أسماء الأسفار متطابقة

#### 4. القراءة الصوتية لا تعمل

**الحل:**

- تأكد من أن المتصفح يدعم Web Speech API
- جرّب متصفح Chrome أو Edge

---

## 🤝 المساهمة

نرحب بمساهماتكم! إذا كنت ترغب في المساهمة:

1. Fork المشروع
2. أنشئ فرع للميزة الجديدة (`git checkout -b feature/AmazingFeature`)
3. Commit التغييرات (`git commit -m 'Add some AmazingFeature'`)
4. Push إلى الفرع (`git push origin feature/AmazingFeature`)
5. افتح Pull Request

---

## 📝 Changelog

### Version 3.4.1 (2026-01-02)

- ✅ إضافة Lazy Loading للتفاسير
- ✅ تحسين دوال تحويل أسماء الأسفار
- ✅ إصلاح مشكلة الروابط المكررة
- ✅ إضافة زر "قراءة الأصحاح" في صفحة التفسير
- ✅ حذف أيقونة التفسير من الآيات
- ✅ تحسين قائمة المفسرين المنسدلة

### Version 3.4.0

- ✅ إضافة فهرس تفاعلي للتفاسير
- ✅ تحسين SEO والـ meta tags
- ✅ إضافة دعم Open Graph

### Version 3.3.0

- ✅ إضافة القاموس المحسّن
- ✅ تحسين البحث
- ✅ إضافة الوضع الليلي

---

## 📄 الترخيص

هذا المشروع مرخص تحت رخصة GPL-2.0+ - انظر ملف [LICENSE](LICENSE) للتفاصيل.

---

## 👨‍💻 المطور

**Eng/ Ibrahim Noshy**

- GitHub: [@inisoliman](https://github.com/inisoliman)
- Email: orsozox@gmail.com

---

## 🙏 شكر وتقدير

- شكراً لجميع المساهمين في هذا المشروع
- شكراً لمصادر التفاسير المستخدمة
- شكراً لمجتمع ووردبريس

---

## 📞 الدعم

إذا واجهت أي مشاكل أو لديك اقتراحات:

- افتح [Issue](https://github.com/inisoliman/my-bible-plugin/issues) على GitHub
- راسلنا على: orsozox@gmail.com

---

**صُنع بـ ❤️ لخدمة كلمة الله**
