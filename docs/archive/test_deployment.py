#!/usr/bin/env python3
"""
COPRRA Deployment Testing Script
Tests the deployed website functionality and reports status.
"""

import requests
import time
import sys
from urllib.parse import urljoin

class COPRRADeploymentTester:
    def __init__(self):
        self.base_url = "https://coprra.com"
        self.test_results = []
        
    def test_main_website(self):
        """Test main website accessibility"""
        print("🌐 Testing main website...")
        try:
            response = requests.get(self.base_url, timeout=15)
            
            if response.status_code == 200:
                print("✅ Main website is accessible and working!")
                self.test_results.append(("Main Website", "✅ PASS"))
                return True
            elif response.status_code == 403:
                print("❌ 403 Forbidden - Files may not be uploaded correctly")
                self.test_results.append(("Main Website", "❌ 403 Forbidden"))
                return False
            else:
                print(f"⚠️ Unexpected status code: {response.status_code}")
                self.test_results.append(("Main Website", f"⚠️ Status {response.status_code}"))
                return False
                
        except requests.exceptions.RequestException as e:
            print(f"❌ Cannot access website: {e}")
            self.test_results.append(("Main Website", f"❌ Error: {e}"))
            return False
    
    def test_database_setup(self):
        """Test database setup script"""
        print("\n🗄️ Testing database setup script...")
        try:
            db_url = urljoin(self.base_url, "coprra_database_setup.php")
            response = requests.get(db_url, timeout=15)
            
            if response.status_code == 200:
                print("✅ Database setup script is accessible")
                self.test_results.append(("Database Setup", "✅ ACCESSIBLE"))
                return True
            else:
                print(f"❌ Database setup script not found: {response.status_code}")
                self.test_results.append(("Database Setup", f"❌ Status {response.status_code}"))
                return False
                
        except requests.exceptions.RequestException as e:
            print(f"❌ Cannot access database setup: {e}")
            self.test_results.append(("Database Setup", f"❌ Error: {e}"))
            return False
    
    def test_laravel_routes(self):
        """Test common Laravel routes"""
        print("\n🛣️ Testing Laravel routes...")
        
        routes_to_test = [
            "/api/health",
            "/login", 
            "/register",
            "/dashboard"
        ]
        
        working_routes = 0
        
        for route in routes_to_test:
            try:
                url = urljoin(self.base_url, route)
                response = requests.get(url, timeout=10)
                
                if response.status_code in [200, 302, 401]:  # 302 for redirects, 401 for auth required
                    print(f"✅ {route} - Working")
                    working_routes += 1
                else:
                    print(f"⚠️ {route} - Status {response.status_code}")
                    
            except requests.exceptions.RequestException:
                print(f"❌ {route} - Not accessible")
        
        if working_routes > 0:
            self.test_results.append(("Laravel Routes", f"✅ {working_routes}/{len(routes_to_test)} working"))
        else:
            self.test_results.append(("Laravel Routes", "❌ No routes working"))
    
    def test_static_assets(self):
        """Test static assets loading"""
        print("\n📁 Testing static assets...")
        
        assets_to_test = [
            "/css/app.css",
            "/js/app.js",
            "/favicon.ico"
        ]
        
        working_assets = 0
        
        for asset in assets_to_test:
            try:
                url = urljoin(self.base_url, asset)
                response = requests.head(url, timeout=10)
                
                if response.status_code == 200:
                    print(f"✅ {asset} - Available")
                    working_assets += 1
                else:
                    print(f"⚠️ {asset} - Status {response.status_code}")
                    
            except requests.exceptions.RequestException:
                print(f"❌ {asset} - Not accessible")
        
        if working_assets > 0:
            self.test_results.append(("Static Assets", f"✅ {working_assets}/{len(assets_to_test)} available"))
        else:
            self.test_results.append(("Static Assets", "❌ No assets available"))
    
    def generate_report(self):
        """Generate final test report"""
        print("\n" + "="*60)
        print("📊 DEPLOYMENT TEST REPORT")
        print("="*60)
        
        for test_name, result in self.test_results:
            print(f"{test_name:20} | {result}")
        
        # Count passed tests
        passed_tests = sum(1 for _, result in self.test_results if "✅" in result)
        total_tests = len(self.test_results)
        
        print(f"\nSummary: {passed_tests}/{total_tests} tests passed")
        
        if passed_tests == total_tests:
            print("\n🎉 ALL TESTS PASSED! Deployment successful!")
            return True
        elif passed_tests > 0:
            print("\n⚠️ PARTIAL SUCCESS - Some issues need attention")
            return False
        else:
            print("\n❌ DEPLOYMENT FAILED - Major issues detected")
            return False
    
    def run_all_tests(self):
        """Run all deployment tests"""
        print("🚀 COPRRA Deployment Testing")
        print("="*50)
        
        # Test main website
        main_working = self.test_main_website()
        
        # Test database setup
        self.test_database_setup()
        
        # Only test routes and assets if main site is working
        if main_working:
            self.test_laravel_routes()
            self.test_static_assets()
        else:
            print("\n⚠️ Skipping additional tests due to main website issues")
        
        # Generate final report
        success = self.generate_report()
        
        return success

def main():
    tester = COPRRADeploymentTester()
    
    try:
        print("Starting deployment tests...")
        print("This may take a few minutes...\n")
        
        success = tester.run_all_tests()
        
        if success:
            print("\n✅ Deployment testing completed successfully!")
            sys.exit(0)
        else:
            print("\n⚠️ Deployment testing completed with issues!")
            sys.exit(1)
            
    except KeyboardInterrupt:
        print("\n⚠️ Testing interrupted by user")
        sys.exit(1)
    except Exception as e:
        print(f"\n❌ Unexpected error during testing: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()