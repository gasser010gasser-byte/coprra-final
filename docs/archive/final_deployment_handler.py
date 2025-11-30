#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
COPRRA Final Deployment Handler
==============================
Complete automation for COPRRA deployment with error handling
"""

import os
import sys
import time
import requests
import json
from pathlib import Path
import subprocess
from urllib.parse import urljoin

# Set UTF-8 encoding for Windows
if sys.platform == "win32":
    import codecs
    sys.stdout = codecs.getwriter("utf-8")(sys.stdout.detach())
    sys.stderr = codecs.getwriter("utf-8")(sys.stderr.detach())

class COPRRAFinalDeployment:
    def __init__(self):
        self.base_url = "https://coprra.com"
        self.project_root = Path(__file__).parent
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

    def print_status(self, message, status="INFO"):
        """Print status message with proper encoding"""
        symbols = {
            "INFO": "[INFO]",
            "SUCCESS": "[SUCCESS]", 
            "ERROR": "[ERROR]",
            "WARNING": "[WARNING]"
        }
        print(f"{symbols.get(status, '[INFO]')} {message}")

    def test_website_status(self):
        """Test current website status"""
        self.print_status("Testing website connectivity...")
        
        tests = [
            {"name": "Main Website", "url": self.base_url},
            {"name": "Database Setup Script", "url": urljoin(self.base_url, "coprra_database_setup.php")},
            {"name": "Laravel Health Check", "url": urljoin(self.base_url, "api/health")},
        ]
        
        results = {}
        
        for test in tests:
            try:
                response = requests.get(test["url"], timeout=10)
                results[test["name"]] = {
                    "status_code": response.status_code,
                    "working": response.status_code == 200,
                    "url": test["url"]
                }
                status = "SUCCESS" if response.status_code == 200 else "WARNING"
                self.print_status(f"{test['name']}: HTTP {response.status_code}", status)
                
            except requests.exceptions.RequestException as e:
                results[test["name"]] = {
                    "error": str(e),
                    "working": False,
                    "url": test["url"]
                }
                self.print_status(f"{test['name']}: Connection failed - {e}", "ERROR")
        
        return results

    def create_browser_automation_script(self):
        """Create a browser automation script for manual execution"""
        self.print_status("Creating browser automation script...")
        
        script_content = '''
# Browser Automation Script for COPRRA Deployment
# ===============================================

# This script provides step-by-step browser automation instructions
# for deploying COPRRA to Hostinger hosting

import time
import webbrowser
from pathlib import Path

def open_hostinger_dashboard():
    """Open Hostinger dashboard in browser"""
    print("Opening Hostinger dashboard...")
    webbrowser.open("https://hpanel.hostinger.com/")
    print("Please log in with:")
    print("Email: gasser.elshewaikh@gmail.com")
    print("Password: Hamo1510@Rayan146")
    input("Press Enter after logging in...")

def navigate_to_file_manager():
    """Instructions for navigating to File Manager"""
    print("\\nNavigating to File Manager:")
    print("1. Click on 'Websites' in the sidebar")
    print("2. Select your coprra.com website")
    print("3. Click on 'Files' tab")
    print("4. Click on 'File Manager'")
    input("Press Enter when you're in File Manager...")

def clean_public_html():
    """Instructions for cleaning public_html"""
    print("\\nCleaning public_html directory:")
    print("1. Navigate to public_html folder")
    print("2. Select ALL files and folders (Ctrl+A)")
    print("3. Click Delete button")
    print("4. Confirm deletion")
    print("5. Ensure public_html is completely empty")
    input("Press Enter when public_html is clean...")

def upload_deployment_files():
    """Instructions for uploading files"""
    print("\\nUploading deployment files:")
    print("1. Click 'Upload Files' button")
    print("2. Select these files from your computer:")
    print("   - coprra_deployment.zip")
    print("   - coprra_database_setup.php")
    print("3. Wait for upload to complete")
    input("Press Enter when files are uploaded...")

def extract_project_files():
    """Instructions for extracting project"""
    print("\\nExtracting project files:")
    print("1. Right-click on coprra_deployment.zip")
    print("2. Select 'Extract'")
    print("3. Wait for extraction to complete")
    print("4. Move all extracted files to public_html root")
    print("5. Delete the zip file after extraction")
    input("Press Enter when extraction is complete...")

def setup_database():
    """Instructions for database setup"""
    print("\\nSetting up database:")
    print("1. Open new browser tab")
    print("2. Go to: https://coprra.com/coprra_database_setup.php")
    print("3. Follow the database setup instructions")
    print("4. Ensure all database tests pass")
    input("Press Enter when database setup is complete...")

def test_website():
    """Instructions for testing website"""
    print("\\nTesting website:")
    print("1. Open new browser tab")
    print("2. Go to: https://coprra.com")
    print("3. Verify website loads without errors")
    print("4. Test navigation and key features")
    print("5. Check for any 403/404 errors")
    input("Press Enter when testing is complete...")

def main():
    """Main deployment process"""
    print("COPRRA Deployment Browser Automation")
    print("====================================")
    
    steps = [
        ("Open Hostinger Dashboard", open_hostinger_dashboard),
        ("Navigate to File Manager", navigate_to_file_manager),
        ("Clean public_html Directory", clean_public_html),
        ("Upload Deployment Files", upload_deployment_files),
        ("Extract Project Files", extract_project_files),
        ("Setup Database", setup_database),
        ("Test Website", test_website)
    ]
    
    for i, (step_name, step_func) in enumerate(steps, 1):
        print(f"\\n=== Step {i}: {step_name} ===")
        step_func()
    
    print("\\n=== DEPLOYMENT COMPLETE ===")
    print("Your COPRRA website should now be live at https://coprra.com")
    print("If you encounter any issues, check the troubleshooting guide.")

if __name__ == "__main__":
    main()
'''
        
        script_path = self.project_root / "browser_automation_guide.py"
        with open(script_path, 'w', encoding='utf-8') as f:
            f.write(script_content)
        
        self.print_status(f"Browser automation script created: {script_path}", "SUCCESS")
        return script_path

    def create_quick_fix_script(self):
        """Create a quick fix script for common issues"""
        self.print_status("Creating quick fix script...")
        
        script_content = '''#!/usr/bin/env python3
"""
COPRRA Quick Fix Script
======================
Automatically diagnose and suggest fixes for common deployment issues
"""

import requests
import json
from urllib.parse import urljoin

def check_website_health():
    """Check website health and provide specific fixes"""
    base_url = "https://coprra.com"
    
    print("COPRRA Website Health Check")
    print("==========================")
    
    # Test main website
    try:
        response = requests.get(base_url, timeout=10)
        if response.status_code == 200:
            print("✓ Main website is working!")
        elif response.status_code == 403:
            print("✗ 403 Forbidden Error")
            print("  Fix: Upload project files to public_html")
            print("  Check: File permissions (755 for folders, 644 for files)")
        elif response.status_code == 404:
            print("✗ 404 Not Found Error")
            print("  Fix: Ensure files are in public_html root directory")
        else:
            print(f"✗ Unexpected status: {response.status_code}")
    except Exception as e:
        print(f"✗ Connection failed: {e}")
        print("  Fix: Check domain DNS settings")
    
    # Test database setup script
    try:
        db_url = urljoin(base_url, "coprra_database_setup.php")
        response = requests.get(db_url, timeout=10)
        if response.status_code == 200:
            print("✓ Database setup script is accessible")
        else:
            print("✗ Database setup script not found")
            print("  Fix: Upload coprra_database_setup.php to public_html")
    except Exception as e:
        print(f"✗ Database script check failed: {e}")
    
    # Test Laravel routes
    try:
        api_url = urljoin(base_url, "api/health")
        response = requests.get(api_url, timeout=10)
        if response.status_code == 200:
            print("✓ Laravel routes are working")
        else:
            print("✗ Laravel routes not working")
            print("  Fix: Check .htaccess file and mod_rewrite")
    except Exception as e:
        print("✗ Laravel routes check failed")
        print("  Fix: Ensure all Laravel files are uploaded")
    
    print("\\nNext Steps:")
    print("1. If website shows 403: Upload all project files")
    print("2. If database issues: Run coprra_database_setup.php")
    print("3. If Laravel issues: Check .env file configuration")
    print("4. For persistent issues: Contact hosting support")

if __name__ == "__main__":
    check_website_health()
'''
        
        script_path = self.project_root / "quick_fix.py"
        with open(script_path, 'w', encoding='utf-8') as f:
            f.write(script_content)
        
        self.print_status(f"Quick fix script created: {script_path}", "SUCCESS")
        return script_path

    def create_deployment_checklist(self):
        """Create a comprehensive deployment checklist"""
        self.print_status("Creating deployment checklist...")
        
        checklist_content = """
# COPRRA Deployment Checklist
============================

## Pre-Deployment Verification
- [ ] coprra_deployment.zip exists and is complete
- [ ] coprra_database_setup.php is available
- [ ] Hostinger login credentials are correct
- [ ] Database credentials are configured

## Hostinger File Manager Steps
- [ ] Log into Hostinger hPanel
- [ ] Navigate to File Manager for coprra.com
- [ ] Clean public_html directory completely
- [ ] Upload coprra_deployment.zip
- [ ] Upload coprra_database_setup.php
- [ ] Extract coprra_deployment.zip
- [ ] Move all files to public_html root
- [ ] Set proper file permissions (755/644)

## Database Setup
- [ ] Access https://coprra.com/coprra_database_setup.php
- [ ] Verify database connection
- [ ] Run Laravel migrations
- [ ] Seed database if needed
- [ ] Test database functionality

## Website Testing
- [ ] Access https://coprra.com
- [ ] Verify homepage loads without errors
- [ ] Test main navigation
- [ ] Check Laravel routes work
- [ ] Verify static assets load
- [ ] Test user registration/login
- [ ] Check admin functionality

## Performance Optimization
- [ ] Enable caching in Laravel
- [ ] Optimize images and assets
- [ ] Configure CDN if available
- [ ] Set up SSL certificate
- [ ] Configure error logging

## Security Checklist
- [ ] Remove debug mode in production
- [ ] Secure .env file permissions
- [ ] Configure proper error pages
- [ ] Set up backup schedule
- [ ] Enable security headers

## Final Verification
- [ ] Website loads at https://coprra.com
- [ ] All features work correctly
- [ ] No 403/404/500 errors
- [ ] Database operations successful
- [ ] Performance is acceptable

## Troubleshooting Resources
- [ ] quick_fix.py - Automated diagnostics
- [ ] browser_automation_guide.py - Step-by-step guide
- [ ] COMPLETE_DEPLOYMENT_GUIDE.md - Detailed instructions
- [ ] troubleshoot_deployment.py - Health checks

## Contact Information
- Hosting: Hostinger Support
- Domain: coprra.com
- Database: MySQL on localhost
- Email: gasser.elshewaikh@gmail.com

## Success Criteria
✓ Website accessible at https://coprra.com
✓ No HTTP errors (403, 404, 500)
✓ Database connectivity working
✓ Laravel application functional
✓ All features operational
"""
        
        checklist_path = self.project_root / "DEPLOYMENT_CHECKLIST.md"
        with open(checklist_path, 'w', encoding='utf-8') as f:
            f.write(checklist_content)
        
        self.print_status(f"Deployment checklist created: {checklist_path}", "SUCCESS")
        return checklist_path

    def run_final_deployment(self):
        """Execute the final deployment process"""
        print("COPRRA Final Deployment Handler")
        print("==============================")
        
        # Test current status
        self.print_status("Checking current website status...")
        results = self.test_website_status()
        
        # Create automation tools
        browser_script = self.create_browser_automation_script()
        fix_script = self.create_quick_fix_script()
        checklist = self.create_deployment_checklist()
        
        # Analyze results
        working_tests = sum(1 for r in results.values() if r.get('working', False))
        total_tests = len(results)
        
        print(f"\nDeployment Status: {working_tests}/{total_tests} tests passing")
        
        if working_tests == total_tests:
            self.print_status("Website is fully operational!", "SUCCESS")
            self.print_status("No further action needed.", "SUCCESS")
        elif working_tests > 0:
            self.print_status("Partial deployment detected.", "WARNING")
            self.print_status("Some components need attention.", "WARNING")
        else:
            self.print_status("Full deployment required.", "WARNING")
            self.print_status("Files need to be uploaded to hosting.", "WARNING")
        
        print("\nDeployment Tools Created:")
        print(f"- Browser Automation Guide: {browser_script}")
        print(f"- Quick Fix Script: {fix_script}")
        print(f"- Deployment Checklist: {checklist}")
        
        print("\nNext Steps:")
        if working_tests < total_tests:
            print("1. Run: python browser_automation_guide.py")
            print("2. Follow the step-by-step browser instructions")
            print("3. Run: python quick_fix.py (after upload)")
            print("4. Use DEPLOYMENT_CHECKLIST.md to verify completion")
        else:
            print("1. Website is working correctly!")
            print("2. Run: python quick_fix.py (for health check)")
            print("3. Monitor website performance")
        
        return working_tests == total_tests

def main():
    """Main execution function"""
    try:
        deployment = COPRRAFinalDeployment()
        success = deployment.run_final_deployment()
        
        if success:
            print("\n=== DEPLOYMENT SUCCESSFUL ===")
            print("COPRRA website is live and operational!")
        else:
            print("\n=== MANUAL DEPLOYMENT REQUIRED ===")
            print("Please follow the generated guides to complete deployment.")
        
        return 0 if success else 1
        
    except Exception as e:
        print(f"Error during deployment: {e}")
        return 1

if __name__ == "__main__":
    sys.exit(main())