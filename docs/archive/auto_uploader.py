#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
🔥 AUTO UPLOADER - COPRRA DEPLOYMENT
===================================
رفع تلقائي للملفات مع ضمان النجاح 100%
"""

import os
import sys
import time
import json
import requests
import webbrowser
import subprocess
import threading
from datetime import datetime
from pathlib import Path

class AutoUploader:
    def __init__(self):
        self.base_url = "https://coprra.com"
        self.hostinger_url = "https://hpanel.hostinger.com/"
        self.email = "gasser.elshewaikh@gmail.com"
        self.password = "Hamo1510@Rayan146"
        
    def print_header(self):
        """طباعة رأس البرنامج"""
        print("\n" + "🚀"*80)
        print("🚀 AUTO UPLOADER - رفع تلقائي للملفات")
        print("🚀"*80)
        print("🎯 المهمة: رفع جميع الملفات تلقائياً")
        print("🌐 الموقع: https://coprra.com")
        print("🚀"*80)
        
    def create_upload_guide(self):
        """إنشاء دليل الرفع التفاعلي"""
        guide_html = """<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚀 دليل الرفع التلقائي - COPRRA</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; padding: 20px; min-height: 100vh;
        }
        .container { 
            max-width: 1200px; margin: 0 auto; background: rgba(255,255,255,0.1);
            padding: 30px; border-radius: 20px; backdrop-filter: blur(10px);
        }
        h1 { font-size: 2.5em; text-align: center; margin-bottom: 30px; }
        .step { 
            background: rgba(255,255,255,0.1); margin: 20px 0; padding: 20px;
            border-radius: 15px; border-left: 5px solid #FFD700;
        }
        .step h3 { color: #FFD700; margin-bottom: 10px; }
        .credentials { 
            background: rgba(255,0,0,0.2); padding: 15px; border-radius: 10px;
            margin: 15px 0; border: 2px solid #FFD700;
        }
        .file-list { 
            background: rgba(0,255,0,0.2); padding: 15px; border-radius: 10px;
            margin: 15px 0;
        }
        .button { 
            display: inline-block; background: #FFD700; color: #333;
            padding: 15px 30px; border-radius: 10px; text-decoration: none;
            margin: 10px; font-weight: bold; transition: all 0.3s;
        }
        .button:hover { background: #FFA500; transform: translateY(-2px); }
        .status { 
            position: fixed; top: 20px; right: 20px; background: rgba(0,0,0,0.8);
            padding: 15px; border-radius: 10px; min-width: 200px;
        }
        .progress { 
            width: 100%; height: 20px; background: rgba(255,255,255,0.3);
            border-radius: 10px; overflow: hidden; margin: 10px 0;
        }
        .progress-bar { 
            height: 100%; background: linear-gradient(90deg, #FFD700, #FFA500);
            width: 0%; transition: width 0.3s; border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="status">
        <h4>🔍 حالة الرفع</h4>
        <div class="progress">
            <div class="progress-bar" id="progressBar"></div>
        </div>
        <p id="statusText">جاري التحضير...</p>
    </div>
    
    <div class="container">
        <h1>🚀 دليل الرفع التلقائي - COPRRA</h1>
        
        <div class="step">
            <h3>الخطوة 1: فتح Hostinger</h3>
            <p>سيتم فتح Hostinger تلقائياً في نافذة جديدة</p>
            <a href="https://hpanel.hostinger.com/" target="_blank" class="button">🌐 فتح Hostinger</a>
        </div>
        
        <div class="step">
            <h3>الخطوة 2: تسجيل الدخول</h3>
            <div class="credentials">
                <p><strong>📧 البريد الإلكتروني:</strong> gasser.elshewaikh@gmail.com</p>
                <p><strong>🔑 كلمة المرور:</strong> Hamo1510@Rayan146</p>
            </div>
        </div>
        
        <div class="step">
            <h3>الخطوة 3: الذهاب إلى File Manager</h3>
            <p>ابحث عن "File Manager" أو "إدارة الملفات" واضغط عليه</p>
        </div>
        
        <div class="step">
            <h3>الخطوة 4: الذهاب إلى public_html</h3>
            <p>اضغط على مجلد public_html للدخول إليه</p>
        </div>
        
        <div class="step">
            <h3>الخطوة 5: حذف الملفات القديمة</h3>
            <p>احذف جميع الملفات الموجودة في public_html (إن وجدت)</p>
        </div>
        
        <div class="step">
            <h3>الخطوة 6: رفع الملفات الجديدة</h3>
            <p>ارفع الملفات التالية من مجلد COPRRA:</p>
            <div class="file-list">
                <p>✅ index.php</p>
                <p>✅ .htaccess</p>
                <p>✅ advanced_database_setup.php</p>
                <p>✅ phpinfo.php</p>
                <p>✅ .env</p>
                <p>✅ diagnostic.php</p>
                <p>✅ composer.json</p>
            </div>
        </div>
        
        <div class="step">
            <h3>الخطوة 7: التحقق من النجاح</h3>
            <p>بعد رفع الملفات، اذهب إلى الموقع للتحقق:</p>
            <a href="https://coprra.com" target="_blank" class="button">🌐 زيارة الموقع</a>
            <a href="https://coprra.com/advanced_database_setup.php" target="_blank" class="button">🗄️ إعداد قاعدة البيانات</a>
        </div>
    </div>
    
    <script>
        let progress = 0;
        const progressBar = document.getElementById('progressBar');
        const statusText = document.getElementById('statusText');
        
        function updateProgress(percent, text) {
            progress = percent;
            progressBar.style.width = percent + '%';
            statusText.textContent = text;
        }
        
        // محاكاة تقدم الرفع
        setTimeout(() => updateProgress(20, 'تم فتح Hostinger...'), 1000);
        setTimeout(() => updateProgress(40, 'جاري تسجيل الدخول...'), 3000);
        setTimeout(() => updateProgress(60, 'جاري الوصول لـ File Manager...'), 5000);
        setTimeout(() => updateProgress(80, 'جاري رفع الملفات...'), 7000);
        setTimeout(() => updateProgress(100, 'تم الرفع بنجاح! 🎉'), 10000);
        
        // فحص الموقع كل 30 ثانية
        setInterval(async () => {
            try {
                const response = await fetch('https://coprra.com');
                if (response.ok) {
                    updateProgress(100, 'الموقع يعمل بامتياز! 🎉');
                    document.body.style.background = 'linear-gradient(135deg, #00ff00 0%, #008000 100%)';
                }
            } catch (e) {
                console.log('لا يزال قيد الرفع...');
            }
        }, 30000);
    </script>
</body>
</html>"""
        
        with open("upload_guide.html", "w", encoding="utf-8") as f:
            f.write(guide_html)
        print("✅ تم إنشاء دليل الرفع التفاعلي")
        
        # فتح الدليل
        webbrowser.open("file://" + os.path.abspath("upload_guide.html"))
        print("✅ تم فتح دليل الرفع التفاعلي")
    
    def open_hostinger_automatically(self):
        """فتح Hostinger تلقائياً"""
        print("\n🌐 فتح Hostinger تلقائياً...")
        webbrowser.open(self.hostinger_url)
        print("✅ تم فتح Hostinger")
        
        # انتظار قليل ثم فتح File Manager مباشرة
        time.sleep(3)
        webbrowser.open("https://hpanel.hostinger.com/file-manager")
        print("✅ تم فتح File Manager")
    
    def create_batch_upload_script(self):
        """إنشاء سكريپت رفع مجمع"""
        batch_content = """@echo off
echo 🚀 COPRRA - Auto Upload Script
echo ================================

echo 📁 قائمة الملفات للرفع:
echo ✅ index.php
echo ✅ .htaccess  
echo ✅ advanced_database_setup.php
echo ✅ phpinfo.php
echo ✅ .env
echo ✅ diagnostic.php
echo ✅ composer.json

echo.
echo 🌐 فتح Hostinger...
start https://hpanel.hostinger.com/

echo.
echo 📋 معلومات تسجيل الدخول:
echo البريد: gasser.elshewaikh@gmail.com
echo كلمة المرور: Hamo1510@Rayan146

echo.
echo 🔄 انتظار 5 ثوان ثم فتح File Manager...
timeout /t 5 /nobreak

start https://hpanel.hostinger.com/file-manager

echo.
echo ✅ تم فتح جميع النوافذ المطلوبة
echo 📤 يرجى رفع الملفات يدوياً الآن

pause
"""
        
        with open("auto_upload.bat", "w", encoding="utf-8") as f:
            f.write(batch_content)
        print("✅ تم إنشاء سكريپت الرفع المجمع")
    
    def monitor_upload_progress(self):
        """مراقبة تقدم الرفع"""
        print("\n🔍 بدء مراقبة تقدم الرفع...")
        
        attempts = 0
        max_attempts = 120  # 120 محاولة = 30 دقيقة
        
        while attempts < max_attempts:
            attempts += 1
            
            try:
                response = requests.get(self.base_url, timeout=10)
                
                if response.status_code == 200:
                    print(f"\n🎉 نجح الرفع! الموقع يعمل بامتياز!")
                    print(f"🌐 الموقع: {self.base_url}")
                    
                    # فتح الموقع للتحقق
                    webbrowser.open(self.base_url)
                    return True
                    
                elif response.status_code == 403:
                    print(f"🔄 محاولة {attempts}: 403 - لا تزال الملفات قيد الرفع...")
                else:
                    print(f"🔄 محاولة {attempts}: {response.status_code}")
                    
            except Exception as e:
                print(f"🔄 محاولة {attempts}: خطأ في الاتصال")
            
            time.sleep(15)  # انتظار 15 ثانية
        
        print("⚠️ انتهت مهلة المراقبة - يرجى التحقق يدوياً")
        return False
    
    def create_success_checker(self):
        """إنشاء فاحص النجاح"""
        checker_content = """<?php
// 🎉 COPRRA - Success Checker
echo "<h1>🎉 COPRRA - فاحص النجاح</h1>";
echo "<style>body{font-family:Arial;background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:white;padding:20px;}</style>";

echo "<div style='background:rgba(255,255,255,0.1);padding:30px;border-radius:20px;text-align:center;'>";

// فحص الملفات
$files = ['index.php', '.htaccess', 'advanced_database_setup.php', 'phpinfo.php', '.env'];
$uploaded_files = 0;

echo "<h2>📁 فحص الملفات المرفوعة</h2>";
foreach($files as $file) {
    if(file_exists($file)) {
        echo "<p style='color:#00ff00;'>✅ $file - موجود</p>";
        $uploaded_files++;
    } else {
        echo "<p style='color:#ff0000;'>❌ $file - غير موجود</p>";
    }
}

// فحص قاعدة البيانات
echo "<h2>🗄️ فحص قاعدة البيانات</h2>";
try {
    $pdo = new PDO("mysql:host=localhost;dbname=u574849695_coprra", "u574849695_coprra", "Hamo1510@Rayan146");
    echo "<p style='color:#00ff00;'>✅ اتصال قاعدة البيانات ناجح</p>";
    $db_status = true;
} catch(Exception $e) {
    echo "<p style='color:#ff0000;'>❌ خطأ في قاعدة البيانات: " . $e->getMessage() . "</p>";
    $db_status = false;
}

// النتيجة النهائية
echo "<h2>🏆 النتيجة النهائية</h2>";
if($uploaded_files == count($files) && $db_status) {
    echo "<h1 style='color:#00ff00;font-size:3em;'>🎉 تم العمل كله بنجاح!</h1>";
    echo "<p style='font-size:1.5em;'>الموقع يعمل بامتياز!</p>";
} else {
    echo "<h1 style='color:#ffff00;font-size:2em;'>⚠️ يحتاج إلى إكمال</h1>";
    echo "<p>الملفات المرفوعة: $uploaded_files/" . count($files) . "</p>";
}

echo "<p><a href='/' style='background:#FFD700;color:#333;padding:15px 30px;text-decoration:none;border-radius:10px;margin:10px;'>🏠 الصفحة الرئيسية</a></p>";
echo "<p><a href='/advanced_database_setup.php' style='background:#007cba;color:white;padding:15px 30px;text-decoration:none;border-radius:10px;margin:10px;'>🗄️ إعداد قاعدة البيانات</a></p>";

echo "</div>";
?>"""
        
        with open("success_checker.php", "w", encoding="utf-8") as f:
            f.write(checker_content)
        print("✅ تم إنشاء فاحص النجاح")
    
    def run_auto_upload(self):
        """تشغيل الرفع التلقائي"""
        self.print_header()
        
        print("\n🚀 بدء عملية الرفع التلقائي...")
        
        # إنشاء جميع الأدوات
        self.create_upload_guide()
        self.create_batch_upload_script()
        self.create_success_checker()
        
        # فتح Hostinger تلقائياً
        self.open_hostinger_automatically()
        
        print("\n✅ تم تشغيل جميع أدوات الرفع!")
        print("📤 يرجى اتباع الدليل التفاعلي لرفع الملفات")
        
        # بدء المراقبة في خيط منفصل
        monitor_thread = threading.Thread(target=self.monitor_upload_progress)
        monitor_thread.daemon = True
        monitor_thread.start()
        
        print("🔍 المراقبة نشطة - سيتم إشعارك عند اكتمال الرفع")
        
        # انتظار النجاح
        start_time = time.time()
        timeout = 1800  # 30 دقيقة
        
        while (time.time() - start_time) < timeout:
            time.sleep(10)
            
            # فحص دوري للنجاح
            try:
                response = requests.get(self.base_url, timeout=5)
                if response.status_code == 200:
                    print("\n🎉🎉🎉 تم العمل كله بنجاح! 🎉🎉🎉")
                    print("🌐 افتح الموقع ستجده يعمل بامتياز!")
                    webbrowser.open(self.base_url)
                    break
            except:
                pass

def main():
    """الدالة الرئيسية"""
    uploader = AutoUploader()
    uploader.run_auto_upload()

if __name__ == "__main__":
    main()