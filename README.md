<div dir="rtl">

# AI Connect Pro

> اتصال کامل هوش مصنوعی به وردپرس — مدیریت، سئو، امنیت و تولید محتوا

[![WordPress](https://img.shields.io/badge/WordPress-5.0+-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-8.3.0-orange.svg)](https://github.com/deioces120/ai-connect-pro)

</div>

<div align="center">

![AI Connect Pro Banner](https://via.placeholder.com/1280x400/667eea/ffffff?text=AI+Connect+Pro+%E2%80%A2+%D8%A7%D8%AA%D8%B5%D8%A7%D9%84+%D9%87%D9%88%D8%B4%D9%85%D8%B5%D9%86%D8%A7%D8%B9%DB%8C+%D8%A8%D9%87+%D9%88%D8%B1%D8%AF%D9%BE%D8%B1%D8%B3)

</div>

---

## 📋 فهرست مطالب

- [امکانات کلیدی](#-امکانات-کلیدی)
- [نمای کلی پنل مدیریت](#-نمای-کلی-پنل-مدیریت)
- [نصب و راه‌اندازی](#-نصب-و-راه‌اندازی)
- [راهنمای استفاده](#-راهنمای-استفاده)
- [مستندات API](#-مستندات-api)
- [پشتیبانی زبان](#-پشتیبانی-زبان)
- [نکات امنیتی](#-نکات-امنیتی)

---

## ✨ امکانات کلیدی

| ویژگی | توضیح |
|--------|--------|
| 🔗 **REST API جامع** | مدیریت کامل نوشته، صفحه، رسانه، کاربر، افزونه، قالب و... |
| 🎯 **تحلیل سئو** | تحلیل عمیق صفحات با نمره‌دهی A/B/C/D |
| 🤖 **هوش مصنوعی** | اتصال به GPT-4o برای تحلیل و تولید محتوا |
| 📝 **تولید محتوا** | مقاله، توضیحات محصول، لندینگ، FAQ |
| 🔒 **امنیت** | Rate Limiting، فیلتر IP، اسکن امنیتی |
| 🛒 **ووکامرس** | مدیریت محصولات و مشاهده آمار فروش |
| 🌐 **چند زبانه** | پشتیبانی کامل از فارسی و انگلیسی |

---

## 🖥️ نمای کلی پنل مدیریت

### داشبورد
![Dashboard](https://via.placeholder.com/800x400/ffffff/333333?text=Dashboard+%E2%80%A2+%D8%AF%D8%A7%D8%B4%D8%A8%D9%88%D8%B1%D8%AF)

- نمایش آمار کلی سایت (نوشته، صفحه، محصول، دیدگاه، کاربر)
- خلاصه سئوی صفحات
- آمار فروشگاه ووکامرس
- دسترسی سریع به بخش‌های مختلف

### تحلیل سئو
![SEO Analysis](https://via.placeholder.com/800x400/ffffff/333333?text=SEO+Analysis+%E2%80%A2+%D8%AA%D8%AD%D9%84%DB%8C%D9%84+SEO)

- نمره‌دهی 0-100 با گرید A/B/C/D
- تحلیل عنوان، متا، هدینگ، محتوا، تصاویر، لینک‌ها
- پیشنهادات عملیاتی
- تحلیل خودکار با هوش مصنوعی

### تولید محتوا
![Content Generator](https://via.placeholder.com/800x400/ffffff/333333?text=Content+Generator+%E2%80%A2+%D8%AA%D9%88%D9%84%DB%8C%D8%AF+%D9%85%D8%AD%D8%AA%D9%88%D8%A7)

- تولید مقاله، توضیحات محصول، لندینگ پیج
- پشتیبانی از دستورالعمل‌های سفارشی
- جستجوی تصاویر رایگان (Pexels و Wikimedia)

---

## 🚀 نصب و راه‌اندازی

### روش ۱: نصب از فایل ZIP
1. فایل `ai-connect.zip` را دانلود کنید
2. به **افزونه‌ها > افزودن > بارگذاری افزونه** بروید
3. فایل ZIP را آپلود و فعال کنید

### روش ۲: نصب دستی
1. پوشه `ai-connect` را در مسیر `wp-content/plugins/` کپی کنید
2. افزونه را از بخش **افزونه‌ها** فعال کنید

### تنظیمات اولیه
پس از فعال‌سازی:
1. ✅ قالب `AI Connect Theme` به‌صورت خودکار فعال می‌شود
2. ✅ یک کلید API تصادفی تولید می‌شود
3. ✅ صفحه فروشگاه ووکامرس (در صورت وجود) ساخته می‌شود
4. ✅ زبان پیش‌فرض فارسی است

برای تغییر زبان، به منوی **AI Connect > زبان** بروید.

---

## 📖 راهنمای استفاده

### ۱. تست اتصال API
<p>

از بخش **AI Connect > API** کلید API خود را کپی کنید و با این دستور تست کنید:

</p>

```bash
curl -s "https://your-site.com/wp-json/ai-connect/v1/ping"
```

خروجی مورد انتظار:
```json
{
  "status": "ok",
  "version": "8.3.0",
  "time": "2026-06-21 03:00:00",
  "site": "https://your-site.com"
}
```

### ۲. دریافت لیست نوشته‌ها
```bash
curl -s -H "X-API-Key: YOUR_KEY" \
  "https://your-site.com/wp-json/ai-connect/v1/posts"
```

### ۳. ایجاد نوشته جدید
```bash
curl -s -X POST \
  -H "X-API-Key: YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "عنوان نوشته",
    "content": "<p>محتوای نوشته</p>",
    "status": "publish"
  }' \
  "https://your-site.com/wp-json/ai-connect/v1/posts"
```

### ۴. تحلیل سئوی یک صفحه
```bash
curl -s -H "X-API-Key: YOUR_KEY" \
  "https://your-site.com/wp-json/ai-connect/v1/seo/analyze/1"
```

خروجی شامل:
- امتیاز کل (0-100)
- نمره (A/B/C/D)
- تحلیل عنوان و متا
- ساختار هدینگ
- کیفیت محتوا و خوانایی
- تحلیل تصاویر و لینک‌ها
- لیست مشکلات و پیشنهادات

### ۵. تولید محتوا با AI
```bash
curl -s -X POST \
  -H "X-API-Key: YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "topic": "آموزش سئو",
    "content_type": "article",
    "word_limit": 800,
    "instructions": "مقاله آموزشی درباره سئوی on-page"
  }' \
  "https://your-site.com/wp-json/ai-connect/v1/seo/generate-content"
```

### ۶. تنظیم هوش مصنوعی
1. به **AI Connect > SEO و قالب > تنظیمات** بروید
2. در بخش **تنظیمات AI Agent** اطلاعات زیر را وارد کنید:
   - **API URL:** `https://api.openai.com/v1/chat/completions`
   - **API Key:** کلید API خود
   - **مدل:** GPT-4o, GPT-4o Mini, یا GPT-3.5 Turbo
3. ذخیره کنید

### ۷. تحلیل خودکار سئو
1. به **AI Connect > SEO و قالب > گزارش سئو** بروید
2. روی یک صفحه کلیک کنید
3. دکمه **«تحلیل خودکار»** را بزنید
4. نتیجه تحلیل هوش مصنوعی نمایش داده می‌شود

### ۸. مشاهده آمار فروشگاه
اگر ووکامرس نصب دارید:
- تعداد محصولات
- سفارشات تکمیل شده
- کل درآمد
- میانگین ارزش سفارش

### ۹. امنیت و مانیتورینگ
- **Rate Limiting:** محدودیت تعداد درخواست در دقیقه
- **فیلتر IP:** فقط IP‌های مجاز اجازه دسترسی دارند
- **مانیتور 404:** ثبت خودکار خطاهای 404
- **لینک‌های شکسته:** شناسایی لینک‌های خراب

---

## 📡 مستندات API

### آدرس پایه
```
https://your-site.com/wp-json/ai-connect/v1/
```

### احراز هویت
```bash
# هدر
X-API-Key: YOUR_API_KEY

# یا پارامتر URL
?api_key=YOUR_API_KEY
```

### لیست Endpoints

| متد | مسیر | توضیح |
|------|--------|--------|
| GET | `/ping` | تست اتصال |
| GET/POST | `/posts` | مدیریت نوشته‌ها |
| GET/POST | `/pages` | مدیریت صفحات |
| GET/POST | `/media` | رسانه‌ها |
| GET/POST | `/comments` | دیدگاه‌ها |
| GET | `/users` | کاربران |
| GET/POST | `/products` | محصولات ووکامرس |
| GET | `/orders` | سفارشات |
| GET | `/plugins` | افزونه‌ها |
| GET | `/themes` | قالب‌ها |
| GET/POST | `/menus` | منوها |
| GET/POST | `/options` | تنظیمات |
| GET/POST | `/files` | فایل‌سیستم |
| POST | `/db/query` | اجرای SQL |
| GET | `/seo/analyze/{id}` | تحلیل سئو |
| GET | `/seo/scores` | امتیاز همه صفحات |
| GET | `/seo/site-health` | سلامت سایت |
| POST | `/seo/generate-content` | تولید محتوا |
| GET | `/analytics/summary` | خلاصه آمار |
| POST | `/devops/scan` | اسکن امنیتی |

---

## 🌐 پشتیبانی زبان

افزونه AI Connect Pro از دو زبان پشتیبانی می‌کند:

### 🇮🇷 فارسی (پیش‌فرض)
- رابط کاربری کاملاً فارسی
- پشتیبانی از RTL (راست به چپ)
- فونت پیش‌فرض Tahoma

### 🇺🇸 انگلیسی
- رابط کاربری کاملاً انگلیسی
- پشتیبانی از LTR (چپ به راست)

### تغییر زبان
1. به **AI Connect > زبان** بروید
2. زبان مورد نظر را انتخاب کنید
3. ذخیره کنید

### اضافه کردن زبان جدید
برای اضافه کردن زبان جدید:
1. فایل جدیدی در پوشه `languages/` با نام کد زبان بسازید (مثلاً `de.php`)
2. کلیدهای ترجمه را کپی کنید و مقادیر را ترجمه کنید
3. کد زبان را در کلاس `AIC_i18n` اضافه کنید

---

## 🔐 نکات امنیتی

1. **کلید API را مخفی نگه دارید**
2. **از HTTPS استفاده کنید**
3. **محدودیت IP تنظیم کنید**
4. **Rate Limit را فعال نگه دارید**
5. **دسترسی SQL را در production غیرفعال کنید**
6. **دسترسی فایل‌سیستم را محدود کنید**

---

## 📋 نیازمندی‌ها

- وردپرس 5.0 یا بالاتر
- PHP 7.4 یا بالاتر
- MySQL 5.6 یا بالاتر
- حافظه PHP حداقل 128MB (توصیه: 256MB)

### اختیاری
- ووکامرس (برای امکانات فروشگاهی)
- cURL (برای بررسی لینک‌ها)
- حساب OpenAI (برای تحلیل و تولید محتوا)
- کلید Pexels API (برای جستجوی تصاویر)

---

## 🤝 مشارکت

1. مخزن را Fork کنید
2. شاخه جدید بسازید: `git checkout -b feature/امکانات-جدید`
3. تغییرات را commit کنید
4. Pull Request ارسال کنید

---

## 📄 لایسنس

GPL v2 - [مشاهده لایسنس](https://www.gnu.org/licenses/gpl-2.0.html)

---

## 👨‍💻 نویسنده

**Deioces120** - [deioces120.ir](https://deioces120.ir)

---

<div align="center">

اگر این افزونه مفید بود، لطفاً ⭐ به مخزن بدهید!

</div>
