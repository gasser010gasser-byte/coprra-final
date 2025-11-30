import ftplib
import os
from pathlib import Path
import sys
import time
import requests


# ===== إعدادات الاتصال (معبأة من بياناتك) =====
FTP_HOST = "ftp.coprra.com"
FTP_USER = "u990109832.GASSER"
FTP_PASS = "Hamo1510@Rayan146"
FTP_PORT = 21
FTP_USE_PASSIVE = True
DOMAIN = "coprra.com"

# ===== مسارات محلية =====
BASE_DIR = Path(__file__).parent
PUBLIC_DIR = BASE_DIR / "public"
APP_DIRS = [
    BASE_DIR / "app",
    BASE_DIR / "bootstrap",
    BASE_DIR / "config",
    BASE_DIR / "routes",
    BASE_DIR / "storage",
    BASE_DIR / "resources",
    BASE_DIR / "vendor",
]

# ملفات إضافية نرفعها مباشرة إلى public_html
EXTRA_FILES = [
    BASE_DIR / "phpinfo.php",
    BASE_DIR / "diagnostic.php",
    BASE_DIR / "index.php",       # نقطة دخول جذرية تُعيد التوجيه إلى public عند توفره
    BASE_DIR / ".htaccess",       # إعدادات إعادة الكتابة والتهيئة
    BASE_DIR / "advanced_database_setup.php",
    BASE_DIR / "check-environment.php",
    BASE_DIR / "web_install_composer.php",  # مُثبّت Composer عبر الويب
    BASE_DIR / ".env",
    BASE_DIR / "permissions_fix.php",
    BASE_DIR / "deploy_unpack.php",
]


def log(msg: str):
    print(f"[DEPLOY] {msg}")


def connect_ftp():
    log("الاتصال بخادم FTP")
    ftp = ftplib.FTP()
    ftp.connect(FTP_HOST, FTP_PORT, timeout=30)
    ftp.login(FTP_USER, FTP_PASS)
    ftp.set_pasv(FTP_USE_PASSIVE)
    return ftp


def try_cwd(ftp: ftplib.FTP, path: str) -> bool:
    try:
        ftp.cwd(path)
        return True
    except Exception:
        return False


def find_docroot(ftp: ftplib.FTP) -> str:
    """يحاول تحديد مسار الجذر الفعلي للموقع داخل حساب الاستضافة."""
    # أولوية قصوى: مجلد public_html في الجذر مباشرة
    try:
        ftp.cwd("/")
    except Exception:
        pass  # بعض الخوادم لا تسمح بالخروج من الـ chroot
    if try_cwd(ftp, "public_html"):
        log("تم تحديد الجذر مباشرة: public_html")
        return "public_html"

    candidates = [
        f"domains/{DOMAIN}/public_html",
        f"domains/{DOMAIN}",
        "www",
        "htdocs",
        f"{DOMAIN}/public_html",
        f"{DOMAIN}",
    ]
    current = ftp.pwd()
    for path in candidates:
        if try_cwd(ftp, path):
            log(f"تم تحديد الجذر: {path}")
            return path
    # محاولة استكشاف بنية المجلدات بحثًا عن النطاق
    try:
        items = ftp.nlst()
        log(f"محتويات الجذر ({current}): {items}")
        if "domains" in items and try_cwd(ftp, "domains"):
            dom_items = ftp.nlst()
            log(f"محتويات domains/: {dom_items}")
            # ابحث عن مجلد مطابق للنطاق
            candidates_dom = [DOMAIN, DOMAIN.replace("https://", "").replace("http://", "")] 
            for d in candidates_dom:
                if d in dom_items and try_cwd(ftp, d):
                    if try_cwd(ftp, "public_html"):
                        log(f"تم تحديد الجذر عبر domains: domains/{d}/public_html")
                        return f"domains/{d}/public_html"
                    else:
                        log(f"تم تحديد مجلد النطاق: domains/{d}")
                        return f"domains/{d}"
            # إن لم نجد مطابقة صريحة، جرّب أول مجلد يشبه النطاق
            for d in dom_items:
                if "." in d and try_cwd(ftp, d):
                    if try_cwd(ftp, "public_html"):
                        log(f"تم تحديد الجذر عبر domains (تقريبي): domains/{d}/public_html")
                        return f"domains/{d}/public_html"
                    else:
                        log(f"تم تحديد مجلد نطاق (تقريبي): domains/{d}")
                        return f"domains/{d}"
            # العودة للجذر إن لم ينجح شيء
            try_cwd(ftp, "..")
    except Exception:
        log("تعذر قراءة محتويات الجذر عبر nlst")
    # استخدم الجذر الحالي كمسار نشر للحسابات المقيّدة (chroot)
    log(f"استخدام الجذر الحالي كـ docroot: {current}")
    return current

def ensure_remote_dir(ftp: ftplib.FTP, remote_dir: str):
    # يحاول الانتقال للمجلد، وإن لم يكن موجودًا يقوم بإنشائه
    parts = remote_dir.strip("/").split("/")
    for part in parts:
        try:
            ftp.cwd(part)
        except Exception:
            ftp.mkd(part)
            ftp.cwd(part)


def upload_file(ftp: ftplib.FTP, local_path: Path, remote_path: str):
    if not local_path.exists():
        log(f"تخطي: الملف غير موجود محليًا {local_path}")
        return
    log(f"رفع ملف: {local_path} -> {remote_path}")
    dirname = os.path.dirname(remote_path)
    if dirname:
        # انتقل أو أنشئ المسار
        current = ftp.pwd()
        ensure_remote_dir(ftp, dirname)
        # عد للمسار بعد الرفع
        ftp.cwd(current)
    with open(local_path, "rb") as f:
        ftp.storbinary(f"STOR {remote_path}", f)


def upload_directory(ftp: ftplib.FTP, local_dir: Path, remote_base: str):
    if not local_dir.exists():
        log(f"المجلد المحلي غير موجود: {local_dir}")
        return
    for root, dirs, files in os.walk(local_dir):
        root_path = Path(root)
        rel = root_path.relative_to(local_dir)
        remote_dir = str(Path(remote_base) / rel).replace("\\", "/")
        # تأكد من وجود المجلد البعيد
        current = ftp.pwd()
        ensure_remote_dir(ftp, remote_dir)
        ftp.cwd(current)
        for fname in files:
            local_file = root_path / fname
            remote_file = f"{remote_dir}/{fname}".replace("\\", "/")
            upload_file(ftp, local_file, remote_file)


def deploy():
    ftp = None
    try:
        ftp = connect_ftp()
        # حدد الجذر الفعلي
        log("تحديد مسار الجذر للموقع")
        docroot = find_docroot(ftp)

        # رفع مجلدات التطبيق الأساسية إلى الجذر
        log("رفع مجلدات التطبيق الأساسية")
        for d in APP_DIRS:
            upload_directory(ftp, d, d.name)

        # رفع مجلد public كـ public/ داخل الجذر (لا نقوم بالمسار المسطح)
        log("رفع مجلد public إلى public/")
        upload_directory(ftp, PUBLIC_DIR, "public")

        # رفع ملفات إضافية (جذرية)
        for lf in EXTRA_FILES:
            upload_file(ftp, lf, f"./{lf.name}")

        log("اكتمل الرفع بنجاح")
    finally:
        if ftp:
            try:
                ftp.quit()
            except Exception:
                ftp.close()


def verify_site(base: str = "https://coprra.com"):
    log("التحقق من عمل الموقع")
    targets = [
        base,
        f"{base}/index.php",
        f"{base}/phpinfo.php",
        f"{base}/diagnostic.php",
    ]
    ok_any = False
    for url in targets:
        try:
            resp = requests.get(url, timeout=20)
            log(f"Check {url} -> {resp.status_code}")
            snippet = resp.text[:200].replace("\n", " ")
            log(f"Snippet: {snippet}")
            if resp.status_code == 200:
                ok_any = True
        except Exception as e:
            log(f"فشل الوصول إلى {url}: {e}")
    return ok_any


def main():
    start = time.time()
    try:
        deploy()
        ok = verify_site()
        duration = time.time() - start
        if ok:
            log(f"نجاح كامل ✅ استغرق {duration:.1f}s")
            sys.exit(0)
        else:
            log(f"تم الرفع لكن الموقع لا يعيد 200 بعد. استغرق {duration:.1f}s")
            sys.exit(2)
    except Exception as e:
        log(f"فشل النشر: {e}")
        sys.exit(1)


if __name__ == "__main__":
    main()
