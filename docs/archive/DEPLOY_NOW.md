# 🚀 Quick Deployment Instructions

## الطريقة الأبسط والأسرع:

### 1. افتح PowerShell أو Terminal

### 2. قم بتشغيل هذا الأمر مباشرة:

```bash
ssh -p 65002 u990109832@45.87.81.218
```

عندما يطلب كلمة المرور، أدخل:
```
Hamo1510@Rayan146
```

### 3. بعد الاتصال، قم بتشغيل هذه الأوامر واحدة تلو الأخرى:

```bash
cd /home/u990109832/domains/coprra.com/public_html

git fetch origin
git checkout feature/build-affiliate-store-foundation
git pull origin feature/build-affiliate-store-foundation

composer install --no-dev --optimize-autoloader

php artisan config:clear
php artisan config:cache

php artisan route:clear
php artisan route:cache

php artisan view:clear
php artisan view:cache

echo "✅ Deployment completed!"
```

### 4. تحقق من الموقع:
افتح المتصفح واذهب إلى: https://coprra.com

---

## ملاحظة:
إذا واجهت أي مشاكل، تحقق من:
- أن الفرع `feature/build-affiliate-store-foundation` موجود على GitHub
- أن Composer مثبت على السيرفر
- أن PHP 8.2+ مثبت

