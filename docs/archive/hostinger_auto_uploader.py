#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
أداة رفع تلقائي إلى Hostinger باستخدام Playwright
الهدف: تسجيل الدخول إلى hPanel، فتح File Manager، رفع ملف ZIP، ثم استخراج المحتوى داخل public_html.

ملاحظات:
- تم تصميم السكربت ليكون مرنًا مع تغييرات الواجهة باستخدام محددات نصية وأدوار عناصر.
- يعمل في وضع "غير مخفي" لتسهيل المراقبة.
"""

import os
import sys
import time
from datetime import datetime
from pathlib import Path

from playwright.sync_api import sync_playwright, TimeoutError as PlaywrightTimeoutError


HPANEL_URL = "https://hpanel.hostinger.com/"
LOGIN_URL = "https://auth.hostinger.com/login?redirect_url=https%3A%2F%2Fhpanel.hostinger.com%2F"

# بيانات الاعتماد (يمكن ضبطها من المتغيرات البيئية)
HOSTINGER_EMAIL = os.getenv("HOSTINGER_EMAIL", "gasser.elshewaikh@gmail.com")
HOSTINGER_PASSWORD = os.getenv("HOSTINGER_PASSWORD", "Hamo1510@Rayan146")

# مسار ملف الرفع (ZIP)
DEPLOY_ZIP = Path("deploy_payload.zip")


def log(msg: str):
    print(f"[{datetime.now().strftime('%H:%M:%S')}] {msg}")


def wait_and_click(page, text: str, timeout: float = 15000):
    """ينتظر نص عنصر ثم ينقر عليه."""
    page.get_by_text(text, exact=False).first.wait_for(state="visible", timeout=timeout)
    page.get_by_text(text, exact=False).first.click()


def safe_click(page, locator_expr: str, timeout: float = 15000):
    try:
        page.locator(locator_expr).first.wait_for(state="visible", timeout=timeout)
        page.locator(locator_expr).first.click()
        return True
    except Exception:
        return False


def login(page):
    log("فتح صفحة تسجيل الدخول")
    page.goto(LOGIN_URL, wait_until="domcontentloaded")

    # قبول الكوكيز إن ظهرت
    for name in ["Accept", "Agree", "Got it", "أوافق", "تمام"]:
        try:
            page.get_by_role("button", name=name).click(timeout=3000)
            break
        except Exception:
            pass

    log("إدخال البريد وكلمة المرور")
    # محاولات متعددة لتحديد خانات الإدخال حسب الواجهة
    filled = False
    for email_selector in [
        "input[name='email']",
        "input[type='email']",
        "input[placeholder*='Email']",
        "input[placeholder*='البريد']",
    ]:
        try:
            page.locator(email_selector).first.fill(HOSTINGER_EMAIL, timeout=5000)
            filled = True
            break
        except Exception:
            continue

    if not filled:
        # محاولة بالاعتماد على الدور/الملصق
        try:
            page.get_by_label("Email", exact=False).fill(HOSTINGER_EMAIL, timeout=5000)
            filled = True
        except Exception:
            pass

    if not filled:
        # محاولة فتح صفحة تسجيل الدخول من hPanel مباشرة
        try:
            log("محاولة فتح صفحة الدخول من hPanel")
            page.goto("https://hpanel.hostinger.com", wait_until="domcontentloaded")
            for btn_name in ["Log in", "Sign in", "تسجيل الدخول", "دخول"]:
                try:
                    page.get_by_role("button", name=btn_name).click(timeout=3000)
                    break
                except Exception:
                    pass
            # إعادة محاولة العثور على حقل البريد
            for email_selector in [
                "input[name='email']",
                "input[type='email']",
                "input[placeholder*='Email']",
                "input[placeholder*='البريد']",
            ]:
                try:
                    page.locator(email_selector).first.fill(HOSTINGER_EMAIL, timeout=5000)
                    filled = True
                    break
                except Exception:
                    continue
        except Exception:
            pass

    if not filled:
        # محاولة أخيرة: ملء أول حقل إدخال يظهر كحقل بريد إلكتروني
        try:
            log("محاولة أخيرة لملء أول حقل إدخال")
            page.locator("input").first.fill(HOSTINGER_EMAIL, timeout=5000)
            filled = True
        except Exception:
            pass

    if not filled:
        raise RuntimeError("تعذر العثور على حقل البريد الإلكتروني")

    filled_pwd = False
    for pwd_selector in [
        "input[name='password']",
        "input[type='password']",
        "input[placeholder*='Password']",
        "input[placeholder*='كلمة']",
    ]:
        try:
            page.locator(pwd_selector).first.fill(HOSTINGER_PASSWORD, timeout=5000)
            filled_pwd = True
            break
        except Exception:
            continue

    if not filled_pwd:
        try:
            page.get_by_label("Password", exact=False).fill(HOSTINGER_PASSWORD, timeout=5000)
            filled_pwd = True
        except Exception:
            pass

    if not filled_pwd:
        raise RuntimeError("تعذر العثور على حقل كلمة المرور")

    # الضغط على زر الدخول
    log("النقر على زر تسجيل الدخول")
    for btn_name in ["Log in", "Sign in", "تسجيل الدخول", "دخول"]:
        try:
            page.get_by_role("button", name=btn_name).click(timeout=3000)
            break
        except Exception:
            pass

    # انتظار التحويل إلى hPanel
    page.wait_for_url(lambda url: "hpanel.hostinger.com" in url, timeout=30000)
    log("تم تسجيل الدخول بنجاح")


def open_file_manager(page):
    log("فتح لوحة التحكم hPanel")
    page.goto(HPANEL_URL, wait_until="domcontentloaded")

    # فتح الموقع وإدارة الاستضافة
    # نحاول العثور على اسم النطاق coprra.com ثم النقر على الإدارة/ملف مانجر
    log("البحث عن الموقع coprra.com")
    try:
        page.get_by_text("coprra.com", exact=False).first.wait_for(timeout=15000)
        page.get_by_text("coprra.com", exact=False).first.click()
    except Exception:
        log("لم أجد عنصر coprra.com مباشرة؛ أحاول فتح إدارة الاستضافة")
        # محاولات عامة للوصول إلى File Manager من الشريط الجانبي/القائمة
        for text in ["Manage", "إدارة", "Files", "File Manager", "الملفات"]:
            if safe_click(page, f"text={text}"):
                break

    # محاولة مباشرة لفتح File Manager
    log("فتح File Manager")
    for text in ["File Manager", "مدير الملفات", "الملفات"]:
        try:
            wait_and_click(page, text)
            break
        except Exception:
            pass

    # انتظار تحميل مدير الملفات
    page.wait_for_load_state("domcontentloaded")
    log("تم فتح File Manager")


def get_file_manager_frame(page):
    """يحاول الحصول على إطار مدير الملفات (iframe) إن وجد."""
    try:
        iframe = page.locator("iframe").first
        frame = iframe.content_frame()
        if frame:
            return frame
    except Exception:
        pass
    return page


def navigate_to_public_html(page):
    log("الانتقال إلى مجلد public_html")
    fm = get_file_manager_frame(page)

    # محاولات متعددة لفتح المجلد داخل إطار مدير الملفات
    for text in ["public_html", "Public Html", "publichtml", "public-html"]:
        try:
            fm.get_by_text(text, exact=False).first.click(timeout=8000)
            log("تم الدخول إلى public_html")
            return
        except Exception:
            pass

    # بديل: استخدام محددات عامة داخل الإطار
    for selector in [
        "text=public_html",
        "a[href*='public_html']",
        "[data-testid*='public_html']",
        "tr:has-text('public_html')",
    ]:
        try:
            fm.locator(selector).first.click(timeout=8000)
            log("تم الدخول إلى public_html عبر محدد بديل")
            return
        except Exception:
            pass

    raise RuntimeError("تعذر الدخول إلى مجلد public_html")


def upload_zip(page):
    if not DEPLOY_ZIP.exists():
        raise FileNotFoundError(f"الملف غير موجود: {DEPLOY_ZIP}")

    log(f"بدء رفع الملف: {DEPLOY_ZIP}")

    # تحديد إطار مدير الملفات إن وجد
    fm = get_file_manager_frame(page)

    # إيجاد عنصر الإدخال للرفع
    input_found = False
    for selector in [
        "input[type='file']",
        "input[name='file']",
        "input[accept*='zip']",
    ]:
        try:
            fm.set_input_files(selector, str(DEPLOY_ZIP))
            input_found = True
            break
        except Exception:
            continue

    if not input_found:
        # محاولة النقر على زر Upload ثم تعيين الملفات
        for btn in ["Upload", "رفع", "تحميل"]:
            try:
                fm.get_by_role("button", name=btn).click(timeout=3000)
                fm.set_input_files("input[type='file']", str(DEPLOY_ZIP))
                input_found = True
                break
            except Exception:
                continue

    if not input_found:
        raise RuntimeError("تعذر تحديد عنصر رفع الملفات")

    # انتظار اكتمال الرفع (نعتمد على وجود الملف في القائمة)
    log("انتظار اكتمال الرفع...")
    done = False
    for _ in range(60):  # حتى 60 ثانية
        try:
            fm.get_by_text(DEPLOY_ZIP.name, exact=False).first.wait_for(timeout=1000)
            done = True
            break
        except Exception:
            time.sleep(1)

    if not done:
        raise RuntimeError("لم يظهر الملف بعد الرفع؛ قد تكون هناك مشكلة")

    log("تم رفع الملف بنجاح")


def extract_zip(page):
    log("بدء استخراج الملف ZIP")
    fm = get_file_manager_frame(page)
    # تحديد الملف ثم الضغط على Extract
    try:
        fm.get_by_text(DEPLOY_ZIP.name, exact=False).first.click(timeout=8000)
    except Exception:
        raise RuntimeError("تعذر تحديد ملف الـ ZIP بعد الرفع")

    extracted = False
    for btn in ["Extract", "فك الضغط", "استخراج"]:
        try:
            fm.get_by_role("button", name=btn).click(timeout=5000)
            extracted = True
            break
        except Exception:
            continue

    if not extracted:
        # أحياناً تظهر قائمة سياق/قائمة إجراءات
        for selector in [
            "button[title*='Extract']",
            "[data-testid*='extract']",
            "text=Extract",
        ]:
            try:
                fm.locator(selector).first.click(timeout=5000)
                extracted = True
                break
            except Exception:
                continue

    if not extracted:
        raise RuntimeError("تعذر العثور على زر استخراج الملف")

    # انتظار اكتمال الاستخراج (ظهور ملفات معروفة مثل index.php)
    log("انتظار اكتمال الاستخراج...")
    done = False
    for _ in range(60):
        try:
            fm.get_by_text("index.php", exact=False).first.wait_for(timeout=1000)
            done = True
            break
        except Exception:
            time.sleep(1)

    if not done:
        raise RuntimeError("لم يتم العثور على index.php بعد الاستخراج")

    log("تم الاستخراج بنجاح وظهرت الملفات الأساسية")


def main():
    if not DEPLOY_ZIP.exists():
        print(f"❌ الملف {DEPLOY_ZIP} غير موجود. أنشئه أولاً.")
        sys.exit(1)

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=False, slow_mo=75)
        context = browser.new_context()
        page = context.new_page()

        try:
            login(page)
            open_file_manager(page)
            navigate_to_public_html(page)
            upload_zip(page)
            extract_zip(page)
            log("🎉 تمت عملية الرفع والاستخراج بنجاح داخل public_html")
        except PlaywrightTimeoutError as te:
            log(f"⏱️ فشل بسبب انتهاء مهلة: {te}")
            sys.exit(1)
        except Exception as e:
            log(f"❌ خطأ: {e}")
            sys.exit(1)
        finally:
            # إبقاء المتصفح مفتوحاً للحظات لعرض الحالة
            time.sleep(5)
            context.close()
            browser.close()


if __name__ == "__main__":
    main()
