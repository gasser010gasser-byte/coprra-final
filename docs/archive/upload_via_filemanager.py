#!/usr/bin/env python3
"""
COPRRA Project Upload via Hostinger File Manager
Alternative to FTP - uses web-based file manager
"""

import os
import zipfile
import shutil
from pathlib import Path

def create_deployment_package():
    """Create a deployment package for upload"""
    print("🔧 Creating deployment package...")
    
    # Project directory
    project_dir = Path(__file__).parent
    
    # Files and directories to exclude
    exclude_patterns = {
        '.git', '.github', 'node_modules', 'vendor', 'storage/logs',
        'storage/framework/cache', 'storage/framework/sessions',
        'storage/framework/views', '.env.testing', '.env.example',
        'tests', 'phpunit.xml', 'webpack.mix.js', 'package.json',
        'package-lock.json', 'yarn.lock', 'composer.lock',
        'raw_outputs*', 'reports', 'temp_task*', 'renamed*',
        'insights*.json', 'trivy-fs.json', 'audit.ps1',
        'run-tests.ps1', 'test*.php', 'test_results',
        'downloaded-ci', 'generated', 'mnt', 'nul',
        'platform-tools', 'system-support', '__pycache__',
        '*.pyc', '*.log', '.DS_Store', 'Thumbs.db',
        'upload_to_hostinger.py', 'upload_via_filemanager.py',
        'setup_database.php'
    }
    
    # Create deployment directory
    deploy_dir = project_dir / 'deployment_package'
    if deploy_dir.exists():
        shutil.rmtree(deploy_dir)
    deploy_dir.mkdir()
    
    # Copy files
    copied_files = 0
    skipped_files = 0
    
    for root, dirs, files in os.walk(project_dir):
        root_path = Path(root)
        rel_path = root_path.relative_to(project_dir)
        
        # Skip excluded directories
        if any(pattern in str(rel_path) for pattern in exclude_patterns):
            continue
            
        # Create directory structure
        target_dir = deploy_dir / rel_path
        target_dir.mkdir(parents=True, exist_ok=True)
        
        # Copy files
        for file in files:
            if any(pattern in file for pattern in exclude_patterns):
                skipped_files += 1
                continue
                
            source_file = root_path / file
            target_file = target_dir / file
            
            try:
                shutil.copy2(source_file, target_file)
                copied_files += 1
            except Exception as e:
                print(f"⚠️ Warning: Could not copy {source_file}: {e}")
                skipped_files += 1
    
    print(f"✅ Copied {copied_files} files")
    print(f"⏭️ Skipped {skipped_files} files")
    
    # Create ZIP file for easy upload
    zip_path = project_dir / 'coprra_deployment.zip'
    if zip_path.exists():
        zip_path.unlink()
    
    print("📦 Creating ZIP file...")
    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
        for root, dirs, files in os.walk(deploy_dir):
            for file in files:
                file_path = Path(root) / file
                arc_name = file_path.relative_to(deploy_dir)
                zipf.write(file_path, arc_name)
    
    # Clean up deployment directory
    shutil.rmtree(deploy_dir)
    
    zip_size = zip_path.stat().st_size / (1024 * 1024)  # MB
    print(f"✅ Created deployment package: {zip_path}")
    print(f"📊 Package size: {zip_size:.2f} MB")
    
    return zip_path

def create_upload_instructions():
    """Create detailed upload instructions"""
    instructions = """
🚀 COPRRA Deployment Instructions via Hostinger File Manager

📋 STEP-BY-STEP GUIDE:

1. 📁 PREPARE FILES:
   ✅ Deployment package created: coprra_deployment.zip
   ✅ Database setup script: setup_database.php

2. 🌐 ACCESS HOSTINGER:
   - Go to: https://hpanel.hostinger.com/
   - Login with: gasser.elshewaikh@gmail.com
   - Password: Hamo1510@Rayan146

3. 📂 OPEN FILE MANAGER:
   - Go to: Websites → Dashboard
   - Click: Files → File Manager
   - Navigate to: public_html folder

4. 🗑️ CLEAN EXISTING FILES:
   - Delete all existing files in public_html
   - Keep only: .htaccess (if exists)

5. 📤 UPLOAD PROJECT:
   - Click "Upload" button
   - Select: coprra_deployment.zip
   - Wait for upload to complete
   - Right-click on zip file → Extract

6. 📁 MOVE FILES:
   - After extraction, move all files from extracted folder to public_html root
   - Ensure index.php is directly in public_html

7. 🔧 SET PERMISSIONS:
   - Select storage folder → Right-click → Permissions → 755
   - Select bootstrap/cache → Right-click → Permissions → 755

8. 🗄️ SETUP DATABASE:
   - Upload setup_database.php to public_html
   - Visit: https://coprra.com/setup_database.php
   - Follow the instructions to run migrations

9. 🧹 CLEANUP:
   - Delete setup_database.php after use
   - Delete coprra_deployment.zip from server

10. 🎉 TEST WEBSITE:
    - Visit: https://coprra.com
    - Check if site loads correctly
    - Test key functionality

⚠️ IMPORTANT NOTES:
- Ensure .env file has correct database credentials
- Storage and bootstrap/cache folders need write permissions
- If you see 500 errors, check error logs in File Manager

🔧 TROUBLESHOOTING:
- 403 Error: Check file permissions and .htaccess
- 500 Error: Check storage permissions and .env configuration
- Database Error: Verify credentials and run migrations

📞 SUPPORT:
If you encounter issues, check the error logs in:
Files → Error Logs → Select your domain
"""
    
    instructions_file = Path(__file__).parent / 'UPLOAD_INSTRUCTIONS.txt'
    with open(instructions_file, 'w', encoding='utf-8') as f:
        f.write(instructions)
    
    print(f"📝 Created instructions: {instructions_file}")
    return instructions_file

def main():
    """Main deployment preparation function"""
    print("🚀 COPRRA Deployment Package Creator")
    print("=" * 50)
    
    try:
        # Create deployment package
        zip_path = create_deployment_package()
        
        # Create upload instructions
        instructions_path = create_upload_instructions()
        
        print("\n" + "=" * 50)
        print("✅ DEPLOYMENT PACKAGE READY!")
        print("=" * 50)
        print(f"📦 Package: {zip_path}")
        print(f"📝 Instructions: {instructions_path}")
        print("\n🎯 Next Steps:")
        print("1. Read the UPLOAD_INSTRUCTIONS.txt file")
        print("2. Login to Hostinger File Manager")
        print("3. Upload and extract the ZIP file")
        print("4. Set proper permissions")
        print("5. Run database setup")
        print("6. Test your website!")
        
    except Exception as e:
        print(f"❌ Error creating deployment package: {e}")
        return False
    
    return True

if __name__ == "__main__":
    main()