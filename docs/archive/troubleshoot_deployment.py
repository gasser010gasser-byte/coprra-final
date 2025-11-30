#!/usr/bin/env python3
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
        print("\n🔧 خطوات الإصلاح المقترحة:")
        print("1. تأكد من رفع جميع الملفات")
        print("2. تحقق من إعدادات قاعدة البيانات")
        print("3. راجع صلاحيات الملفات")
        print("4. تحقق من ملف .htaccess")
    
    return results

if __name__ == "__main__":
    test_website_health()
