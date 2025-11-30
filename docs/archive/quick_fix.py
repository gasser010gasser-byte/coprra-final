#!/usr/bin/env python3
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
    
    print("\nNext Steps:")
    print("1. If website shows 403: Upload all project files")
    print("2. If database issues: Run coprra_database_setup.php")
    print("3. If Laravel issues: Check .env file configuration")
    print("4. For persistent issues: Contact hosting support")

if __name__ == "__main__":
    check_website_health()
