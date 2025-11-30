#!/usr/bin/env python3
"""
COPRRA Automated Deployment Script
This script automates the complete deployment process for the COPRRA project.
"""

import os
import sys
import time
import zipfile
import requests
from pathlib import Path

class COPRRADeployer:
    def __init__(self):
        self.project_root = Path(__file__).parent
        self.deployment_zip = self.project_root / "coprra_deployment.zip"
        self.base_url = "https://coprra.com"
        
    def check_deployment_package(self):
        """Check if deployment package exists"""
        print("🔍 Checking deployment package...")
        if not self.deployment_zip.exists():
            print("❌ Deployment package not found!")
            print("Please run upload_via_filemanager.py first to create the deployment package.")
            return False
        
        size_mb = self.deployment_zip.stat().st_size / (1024 * 1024)
        print(f"✅ Deployment package found: {size_mb:.2f} MB")
        return True
    
    def display_manual_steps(self):
        """Display manual deployment steps"""
        print("\n" + "="*60)
        print("🚀 MANUAL DEPLOYMENT STEPS")
        print("="*60)
        
        print("\n📋 STEP 1: Upload Files via Hostinger File Manager")
        print("1. Go to: https://hpanel.hostinger.com/")
        print("2. Login with:")
        print("   Email: gasser.elshewaikh@gmail.com")
        print("   Password: Hamo1510@Rayan146")
        print("3. Navigate to: Websites → Dashboard → Files → File Manager")
        print("4. Delete all existing files in public_html")
        print("5. Upload coprra_deployment.zip to public_html")
        print("6. Extract the zip file")
        print("7. Move all extracted files to the root of public_html")
        
        print("\n📋 STEP 2: Set up Database")
        print("1. Upload coprra_database_setup.php to public_html")
        print("2. Visit: https://coprra.com/coprra_database_setup.php")
        print("3. Follow the database setup instructions")
        
        print("\n📋 STEP 3: Test Website")
        print("1. Visit: https://coprra.com")
        print("2. Verify all functionality works correctly")
        
    def test_website_connectivity(self):
        """Test if the website is accessible"""
        print("\n🌐 Testing website connectivity...")
        try:
            response = requests.get(self.base_url, timeout=10)
            print(f"✅ Website accessible: {response.status_code}")
            
            if response.status_code == 403:
                print("⚠️  403 Forbidden - Files may not be uploaded yet")
            elif response.status_code == 200:
                print("🎉 Website is working!")
            else:
                print(f"⚠️  Unexpected status code: {response.status_code}")
                
        except requests.exceptions.RequestException as e:
            print(f"❌ Website not accessible: {e}")
    
    def check_database_setup(self):
        """Check if database setup script is accessible"""
        print("\n🗄️ Checking database setup...")
        try:
            db_setup_url = f"{self.base_url}/coprra_database_setup.php"
            response = requests.get(db_setup_url, timeout=10)
            
            if response.status_code == 200:
                print("✅ Database setup script is accessible")
                print(f"🔗 Visit: {db_setup_url}")
            else:
                print(f"❌ Database setup script not found: {response.status_code}")
                
        except requests.exceptions.RequestException as e:
            print(f"❌ Cannot access database setup: {e}")
    
    def run_deployment_check(self):
        """Run complete deployment check"""
        print("🚀 COPRRA Automated Deployment Checker")
        print("="*50)
        
        # Check deployment package
        if not self.check_deployment_package():
            return False
        
        # Display manual steps
        self.display_manual_steps()
        
        # Test connectivity
        self.test_website_connectivity()
        
        # Check database setup
        self.check_database_setup()
        
        print("\n" + "="*60)
        print("✅ DEPLOYMENT READY!")
        print("="*60)
        print("All files are prepared. Please follow the manual steps above.")
        print("The deployment package and database setup script are ready.")
        
        return True

def main():
    deployer = COPRRADeployer()
    
    try:
        success = deployer.run_deployment_check()
        if success:
            print("\n🎉 Deployment preparation completed successfully!")
        else:
            print("\n❌ Deployment preparation failed!")
            sys.exit(1)
            
    except KeyboardInterrupt:
        print("\n⚠️ Deployment interrupted by user")
        sys.exit(1)
    except Exception as e:
        print(f"\n❌ Unexpected error: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()