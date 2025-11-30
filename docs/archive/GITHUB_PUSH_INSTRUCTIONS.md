# تعليمات الدفع إلى GitHub

## المشكلة الحالية
الدفع عبر SSH لا يعمل. الحل هو استخدام HTTPS مع Personal Access Token.

## الخطوات المطلوبة

### 1. إنشاء Personal Access Token (PAT)

1. اذهب إلى: https://github.com/settings/tokens
2. اضغط على "Generate new token" → "Generate new token (classic)"
3. أدخل اسم للـ Token (مثل: "COPRRA-Push-Token")
4. اختر الصلاحيات:
   - ✅ **repo** (Full control of private repositories)
5. اضغط "Generate token"
6. **انسخ الـ Token فوراً** (لن تتمكن من رؤيته مرة أخرى!)

### 2. استخدام الـ Token للدفع

بعد الحصول على الـ Token، سأستخدمه في الأمر التالي:

```bash
git push https://YOUR_TOKEN@github.com/COPRRA/coprra-platform.git refactor/start-over-2025
```

أو يمكنك إعطائي:
- اسم المستخدم GitHub
- Personal Access Token

وسأقوم بإعداد كل شيء تلقائياً.

## بديل: استخدام GitHub Desktop

إذا كان لديك GitHub Desktop مثبت:
1. افتح GitHub Desktop
2. File → Add Local Repository
3. اختر: `C:\Users\Gaser\Desktop\COPRRA`
4. تأكد من تسجيل الدخول
5. اضغط "Publish branch" للفرع `refactor/start-over-2025`

