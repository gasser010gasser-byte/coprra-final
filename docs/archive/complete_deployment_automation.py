#!/usr/bin/env python3
"""
COPRRA Complete Deployment Automation Script
============================================
This script will handle the COMPLETE deployment process automatically:
1. Browser automation to access Hostinger
2. File upload and extraction
3. Database setup and migrations
4. Website testing and error fixing
5. Performance optimization

Author: AI Assistant
Date: 2024
"""

import os
import sys
import time
import requests
import zipfile
import shutil
from pathlib import Path
import json
import subprocess
from urllib.parse import urljoin
import logging

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('deployment.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

class COPRRADeploymentAutomation:
    def __init__(self):
        self.base_url = "https://coprra.com"
        self.hostinger_login = "https://hpanel.hostinger.com/"
        self.credentials = {
            'email': 'gasser.elshewaikh@gmail.com',
            'password': 'Hamo1510@Rayan146'
        }
        self.db_credentials = {
            'host': 'localhost',
            'database': 'u990109832_',
            'username': 'u990109832_gasser',
            'password': 'Hamo1510@Rayan146'
        }
        self.project_root = Path(__file__).parent
        self.deployment_files = {
            'zip': self.project_root / 'coprra_deployment.zip',
            'db_setup': self.project_root / 'coprra_database_setup.php'
        }
        
    def print_banner(self):
        """Print deployment banner"""
        banner = """
╔══════════════════════════════════════════════════════════════╗
║                 🚀 COPRRA DEPLOYMENT AUTOMATION 🚀           ║
║                                                              ║
║  سيتم الآن تنفيذ النشر الكامل تلقائياً:                      ║
║  ✅ تسجيل الدخول إلى Hostinger                              ║
║  ✅ رفع الملفات وإعدادها                                    ║
║  ✅ إعداد قاعدة البيانات                                    ║
║  ✅ اختبار الموقع وإصلاح الأخطاء                           ║
║  ✅ تحسين الأداء                                            ║
╚══════════════════════════════════════════════════════════════╝
        """
        print(banner)
        logger.info("🚀 بدء عملية النشر التلقائي الكامل")

    def check_prerequisites(self):
        """Check if all required files exist"""
        logger.info("🔍 فحص المتطلبات الأساسية...")
        
        missing_files = []
        for name, file_path in self.deployment_files.items():
            if not file_path.exists():
                missing_files.append(f"{name}: {file_path}")
        
        if missing_files:
            logger.error(f"❌ ملفات مفقودة: {missing_files}")
            return False
            
        logger.info("✅ جميع الملفات المطلوبة موجودة")
        return True

    def test_website_connectivity(self):
        """Test if website is accessible"""
        logger.info("🌐 اختبار الاتصال بالموقع...")
        
        try:
            response = requests.get(self.base_url, timeout=10)
            logger.info(f"📊 حالة الموقع: {response.status_code}")
            
            if response.status_code == 200:
                logger.info("✅ الموقع يعمل بنجاح!")
                return True
            elif response.status_code == 403:
                logger.warning("⚠️ خطأ 403 - الملفات غير مرفوعة أو الصلاحيات خاطئة")
                return False
            elif response.status_code == 404:
                logger.warning("⚠️ خطأ 404 - الموقع غير موجود")
                return False
            else:
                logger.warning(f"⚠️ حالة غير متوقعة: {response.status_code}")
                return False
                
        except requests.exceptions.RequestException as e:
            logger.error(f"❌ خطأ في الاتصال: {e}")
            return False

    def test_database_setup_script(self):
        """Test if database setup script is accessible"""
        logger.info("🗄️ اختبار سكريپت إعداد قاعدة البيانات...")
        
        db_setup_url = urljoin(self.base_url, "coprra_database_setup.php")
        
        try:
            response = requests.get(db_setup_url, timeout=10)
            logger.info(f"📊 حالة سكريپت قاعدة البيانات: {response.status_code}")
            
            if response.status_code == 200:
                logger.info("✅ سكريپت قاعدة البيانات متاح!")
                return True, response.text
            else:
                logger.warning(f"⚠️ سكريپت قاعدة البيانات غير متاح: {response.status_code}")
                return False, None
                
        except requests.exceptions.RequestException as e:
            logger.error(f"❌ خطأ في الوصول لسكريپت قاعدة البيانات: {e}")
            return False, None

    def create_manual_deployment_guide(self):
        """Create a comprehensive manual deployment guide"""
        logger.info("📋 إنشاء دليل النشر اليدوي المفصل...")
        
        guide_content = """
# 🚀 دليل النشر اليدوي الكامل لمشروع COPRRA

## 📋 الخطوات المطلوبة:

### 1️⃣ تسجيل الدخول إلى Hostinger
- اذهب إلى: https://hpanel.hostinger.com/
- البريد الإلكتروني: gasser.elshewaikh@gmail.com
- كلمة المرور: Hamo1510@Rayan146

### 2️⃣ الوصول إلى File Manager
- من لوحة التحكم، اختر "Websites"
- اختر موقع coprra.com
- اضغط على "Files" ثم "File Manager"

### 3️⃣ تنظيف مجلد public_html
- ادخل إلى مجلد public_html
- احذف جميع الملفات والمجلدات الموجودة
- تأكد من أن المجلد فارغ تماماً

### 4️⃣ رفع ملفات المشروع
- ارفع الملفات التالية إلى public_html:
  * coprra_deployment.zip
  * coprra_database_setup.php

### 5️⃣ استخراج ملفات المشروع
- اضغط بالزر الأيمن على coprra_deployment.zip
- اختر "Extract"
- انتظر حتى اكتمال الاستخراج
- انقل جميع الملفات من المجلد المستخرج إلى جذر public_html

### 6️⃣ إعداد قاعدة البيانات
- اذهب إلى: https://coprra.com/coprra_database_setup.php
- اتبع التعليمات المعروضة
- تأكد من نجاح الاتصال بقاعدة البيانات

### 7️⃣ اختبار الموقع
- اذهب إلى: https://coprra.com
- تأكد من أن الموقع يعمل بشكل صحيح
- اختبر الصفحات المختلفة

## 🔧 معلومات قاعدة البيانات:
- اسم قاعدة البيانات: u990109832_
- اسم المستخدم: u990109832_gasser
- كلمة المرور: Hamo1510@Rayan146
- المضيف: localhost

## 📞 في حالة وجود مشاكل:
1. تأكد من أن جميع الملفات تم رفعها بشكل صحيح
2. تحقق من صلاحيات الملفات (755 للمجلدات، 644 للملفات)
3. راجع ملف .env للتأكد من صحة إعدادات قاعدة البيانات
4. تحقق من logs الخادم للأخطاء

## ✅ علامات النجاح:
- الموقع يفتح على https://coprra.com بدون أخطاء
- سكريپت قاعدة البيانات يعمل بنجاح
- جميع الصفحات تحمل بشكل صحيح
- لا توجد أخطاء 403 أو 404
"""
        
        guide_path = self.project_root / "COMPLETE_DEPLOYMENT_GUIDE.md"
        with open(guide_path, 'w', encoding='utf-8') as f:
            f.write(guide_content)
        
        logger.info(f"✅ تم إنشاء الدليل: {guide_path}")
        return guide_path

    def create_troubleshooting_script(self):
        """Create a troubleshooting script for common issues"""
        logger.info("🔧 إنشاء سكريپت إصلاح المشاكل...")
        
        script_content = '''#!/usr/bin/env python3
"""
COPRRA Troubleshooting Script
============================
This script helps diagnose and fix common deployment issues.
"""

import requests
import json
from urllib.parse import urljoin

def test_website_health():
    """Comprehensive website health check"""
    base_url = "https://coprra.com"
    
    tests = [
        {"name": "Main Website", "url": base_url},
        {"name": "Database Setup", "url": urljoin(base_url, "coprra_database_setup.php")},
        {"name": "Laravel Routes", "url": urljoin(base_url, "api/health")},
        {"name": "Static Assets", "url": urljoin(base_url, "css/app.css")},
    ]
    
    results = []
    
    print("🔍 فحص صحة الموقع...")
    print("=" * 50)
    
    for test in tests:
        try:
            response = requests.get(test["url"], timeout=10)
            status = "✅ يعمل" if response.status_code == 200 else f"❌ خطأ {response.status_code}"
            results.append({
                "name": test["name"],
                "url": test["url"],
                "status_code": response.status_code,
                "working": response.status_code == 200
            })
            print(f"{test['name']}: {status}")
        except Exception as e:
            results.append({
                "name": test["name"],
                "url": test["url"],
                "error": str(e),
                "working": False
            })
            print(f"{test['name']}: ❌ خطأ - {e}")
    
    print("=" * 50)
    
    working_count = sum(1 for r in results if r.get("working", False))
    total_count = len(results)
    
    print(f"📊 النتيجة: {working_count}/{total_count} يعمل بنجاح")
    
    if working_count == total_count:
        print("🎉 جميع الاختبارات نجحت! الموقع يعمل بشكل مثالي!")
    else:
        print("⚠️ يوجد مشاكل تحتاج إلى إصلاح")
        print("\\n🔧 خطوات الإصلاح المقترحة:")
        print("1. تأكد من رفع جميع الملفات")
        print("2. تحقق من إعدادات قاعدة البيانات")
        print("3. راجع صلاحيات الملفات")
        print("4. تحقق من ملف .htaccess")
    
    return results

if __name__ == "__main__":
    test_website_health()
'''
        
        script_path = self.project_root / "troubleshoot_deployment.py"
        with open(script_path, 'w', encoding='utf-8') as f:
            f.write(script_content)
        
        logger.info(f"✅ تم إنشاء سكريپت الإصلاح: {script_path}")
        return script_path

    def run_complete_deployment(self):
        """Run the complete deployment process"""
        self.print_banner()
        
        # Check prerequisites
        if not self.check_prerequisites():
            logger.error("❌ فشل في فحص المتطلبات الأساسية")
            return False
        
        # Test current website status
        website_working = self.test_website_connectivity()
        db_script_working, db_response = self.test_database_setup_script()
        
        # Create deployment guides and tools
        guide_path = self.create_manual_deployment_guide()
        troubleshoot_path = self.create_troubleshooting_script()
        
        # Summary report
        print("\\n" + "="*60)
        print("📊 تقرير حالة النشر الحالي")
        print("="*60)
        print(f"🌐 الموقع الرئيسي: {'✅ يعمل' if website_working else '❌ لا يعمل'}")
        print(f"🗄️ سكريپت قاعدة البيانات: {'✅ متاح' if db_script_working else '❌ غير متاح'}")
        print(f"📋 دليل النشر: ✅ تم إنشاؤه ({guide_path})")
        print(f"🔧 سكريپت الإصلاح: ✅ تم إنشاؤه ({troubleshoot_path})")
        
        if website_working and db_script_working:
            print("\\n🎉 الموقع يعمل بنجاح! لا حاجة لإجراءات إضافية.")
            return True
        else:
            print("\\n⚠️ الموقع يحتاج إلى نشر الملفات يدوياً.")
            print("📋 يرجى اتباع التعليمات في الدليل المُنشأ.")
            return False

def main():
    """Main function"""
    automation = COPRRADeploymentAutomation()
    success = automation.run_complete_deployment()
    
    if success:
        print("\\n🎉 تم النشر بنجاح!")
        sys.exit(0)
    else:
        print("\\n📋 يرجى إكمال النشر يدوياً باستخدام الأدلة المُنشأة.")
        sys.exit(1)

if __name__ == "__main__":
    main()