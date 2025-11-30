
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
    print("\nNavigating to File Manager:")
    print("1. Click on 'Websites' in the sidebar")
    print("2. Select your coprra.com website")
    print("3. Click on 'Files' tab")
    print("4. Click on 'File Manager'")
    input("Press Enter when you're in File Manager...")

def clean_public_html():
    """Instructions for cleaning public_html"""
    print("\nCleaning public_html directory:")
    print("1. Navigate to public_html folder")
    print("2. Select ALL files and folders (Ctrl+A)")
    print("3. Click Delete button")
    print("4. Confirm deletion")
    print("5. Ensure public_html is completely empty")
    input("Press Enter when public_html is clean...")

def upload_deployment_files():
    """Instructions for uploading files"""
    print("\nUploading deployment files:")
    print("1. Click 'Upload Files' button")
    print("2. Select these files from your computer:")
    print("   - coprra_deployment.zip")
    print("   - coprra_database_setup.php")
    print("3. Wait for upload to complete")
    input("Press Enter when files are uploaded...")

def extract_project_files():
    """Instructions for extracting project"""
    print("\nExtracting project files:")
    print("1. Right-click on coprra_deployment.zip")
    print("2. Select 'Extract'")
    print("3. Wait for extraction to complete")
    print("4. Move all extracted files to public_html root")
    print("5. Delete the zip file after extraction")
    input("Press Enter when extraction is complete...")

def setup_database():
    """Instructions for database setup"""
    print("\nSetting up database:")
    print("1. Open new browser tab")
    print("2. Go to: https://coprra.com/coprra_database_setup.php")
    print("3. Follow the database setup instructions")
    print("4. Ensure all database tests pass")
    input("Press Enter when database setup is complete...")

def test_website():
    """Instructions for testing website"""
    print("\nTesting website:")
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
        print(f"\n=== Step {i}: {step_name} ===")
        step_func()
    
    print("\n=== DEPLOYMENT COMPLETE ===")
    print("Your COPRRA website should now be live at https://coprra.com")
    print("If you encounter any issues, check the troubleshooting guide.")

if __name__ == "__main__":
    main()
