#!/usr/bin/env python3
"""
COPRRA Project Upload Script for Hostinger
This script uploads the Laravel project to Hostinger hosting via FTP
"""

import ftplib
import os
import sys
from pathlib import Path
import time

# Hostinger FTP Configuration
FTP_HOST = "ftp.coprra.com"
FTP_USER = "u990109832"
FTP_PASS = input("Enter FTP Password: ")  # Will prompt for password
REMOTE_DIR = "public_html"

# Local project directory
LOCAL_DIR = Path(__file__).parent

# Files and directories to exclude from upload
EXCLUDE_PATTERNS = {
    '.git', '.github', '.vscode', '.idea', 'node_modules', 
    'tests', 'storage/logs', 'storage/framework/cache',
    'storage/framework/sessions', 'storage/framework/views',
    '.env.example', '.env.testing', 'phpunit.xml', 'vite.config.js',
    'package.json', 'package-lock.json', 'webpack.mix.js',
    'docker-compose.yml', 'Dockerfile', '.dockerignore',
    'test_browser_use.py', 'upload_to_hostinger.py',
    '__pycache__', '*.pyc', '.pytest_cache',
    'raw_outputs*', 'temp_task*', 'renamed*', 'insights*.json',
    'trivy-fs.json', 'audit.ps1', 'run-tests.ps1'
}

def should_exclude(path):
    """Check if a file/directory should be excluded from upload"""
    path_str = str(path)
    for pattern in EXCLUDE_PATTERNS:
        if pattern in path_str or path.name.startswith('.') and path.name not in ['.htaccess', '.user.ini']:
            return True
    return False

def upload_file(ftp, local_path, remote_path):
    """Upload a single file via FTP"""
    try:
        with open(local_path, 'rb') as file:
            ftp.storbinary(f'STOR {remote_path}', file)
        print(f"✓ Uploaded: {remote_path}")
        return True
    except Exception as e:
        print(f"✗ Failed to upload {remote_path}: {e}")
        return False

def create_remote_directory(ftp, remote_dir):
    """Create remote directory if it doesn't exist"""
    try:
        ftp.mkd(remote_dir)
        print(f"✓ Created directory: {remote_dir}")
    except ftplib.error_perm:
        # Directory might already exist
        pass

def upload_directory(ftp, local_dir, remote_dir=""):
    """Recursively upload directory contents"""
    uploaded_count = 0
    failed_count = 0
    
    for item in local_dir.iterdir():
        if should_exclude(item):
            print(f"⏭ Skipping: {item.name}")
            continue
            
        remote_path = f"{remote_dir}/{item.name}" if remote_dir else item.name
        
        if item.is_file():
            if upload_file(ftp, item, remote_path):
                uploaded_count += 1
            else:
                failed_count += 1
        elif item.is_dir():
            create_remote_directory(ftp, remote_path)
            sub_uploaded, sub_failed = upload_directory(ftp, item, remote_path)
            uploaded_count += sub_uploaded
            failed_count += sub_failed
    
    return uploaded_count, failed_count

def main():
    """Main upload function"""
    print("🚀 Starting COPRRA Project Upload to Hostinger...")
    print(f"📁 Local directory: {LOCAL_DIR}")
    print(f"🌐 FTP Host: {FTP_HOST}")
    print(f"👤 FTP User: {FTP_USER}")
    print(f"📂 Remote directory: {REMOTE_DIR}")
    print("-" * 50)
    
    try:
        # Connect to FTP server
        print("🔌 Connecting to FTP server...")
        ftp = ftplib.FTP(FTP_HOST)
        ftp.login(FTP_USER, FTP_PASS)
        print("✅ Connected successfully!")
        
        # Change to remote directory
        try:
            ftp.cwd(REMOTE_DIR)
            print(f"📂 Changed to directory: {REMOTE_DIR}")
        except ftplib.error_perm:
            print(f"❌ Cannot access directory: {REMOTE_DIR}")
            return False
        
        # Start upload
        print("📤 Starting file upload...")
        start_time = time.time()
        
        uploaded_count, failed_count = upload_directory(ftp, LOCAL_DIR)
        
        end_time = time.time()
        duration = end_time - start_time
        
        print("-" * 50)
        print(f"📊 Upload Summary:")
        print(f"   ✅ Files uploaded: {uploaded_count}")
        print(f"   ❌ Files failed: {failed_count}")
        print(f"   ⏱ Duration: {duration:.2f} seconds")
        
        if failed_count == 0:
            print("🎉 All files uploaded successfully!")
        else:
            print(f"⚠️ {failed_count} files failed to upload")
        
        # Close FTP connection
        ftp.quit()
        print("🔌 FTP connection closed")
        
        return failed_count == 0
        
    except ftplib.error_perm as e:
        print(f"❌ FTP Permission Error: {e}")
        return False
    except ftplib.error_temp as e:
        print(f"❌ FTP Temporary Error: {e}")
        return False
    except Exception as e:
        print(f"❌ Unexpected Error: {e}")
        return False

if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)