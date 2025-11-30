#!/usr/bin/env python3
"""
Check PHP errors and server configuration
"""

import paramiko

SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USERNAME = "u990109832"
SSH_PASSWORD = "Hamo1510@Rayan146"

print("🔍 CHECKING PHP AND SERVER CONFIGURATION")

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(SSH_HOST, SSH_PORT, SSH_USERNAME, SSH_PASSWORD, timeout=30)

# Check PHP error log
print("\n📋 Checking PHP error logs...")
stdin, stdout, stderr = ssh.exec_command("tail -50 ~/logs/error_log 2>/dev/null || tail -50 /usr/local/lsws/logs/stderr.log 2>/dev/null || echo 'No PHP error log found'")
php_errors = stdout.read().decode()
if php_errors.strip() and "No PHP error log" not in php_errors:
    print(php_errors)
else:
    print("No PHP errors found in standard locations")

# Check if public/index.php is accessible
print("\n📄 Checking public/index.php...")
stdin, stdout, stderr = ssh.exec_command("ls -lh ~/public_html/public/index.php && head -5 ~/public_html/public/index.php")
print(stdout.read().decode())

# Test PHP directly
print("\n🧪 Testing PHP execution...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php -r 'echo \"PHP is working!\";'")
php_test = stdout.read().decode()
print(f"   Result: {php_test}")

# Test Laravel bootstrap
print("\n🧪 Testing Laravel bootstrap...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan inspire 2>&1")
inspire = stdout.read().decode()
if "inspire" in inspire.lower() or len(inspire) > 10:
    print(f"   ✅ Laravel can bootstrap: {inspire[:100]}")
else:
    print(f"   ⚠️  Result: {inspire}")

# Check .htaccess in root
print("\n📄 Checking root .htaccess...")
stdin, stdout, stderr = ssh.exec_command("cat ~/public_html/.htaccess")
root_htaccess = stdout.read().decode()
print(root_htaccess if root_htaccess.strip() else "⚠️  No .htaccess in root!")

# Check public/.htaccess
print("\n📄 Checking public/.htaccess...")
stdin, stdout, stderr = ssh.exec_command("cat ~/public_html/public/.htaccess 2>&1 | head -20")
public_htaccess = stdout.read().decode()
print(public_htaccess if public_htaccess.strip() else "⚠️  No .htaccess in public!")

# Check if mod_rewrite is working
print("\n🔧 Testing web server...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php -S localhost:8888 -t public public/index.php & sleep 2 && curl -s http://localhost:8888 | head -20 ; pkill -f 'php -S'")
builtin_server = stdout.read().decode()
if builtin_server.strip():
    print(f"   Built-in server response:\n{builtin_server[:300]}")
else:
    print("   ⚠️  No response from built-in server")

# Create a simple PHP info file for testing
print("\n📝 Creating phpinfo test file...")
stdin, stdout, stderr = ssh.exec_command("""cat > ~/public_html/public/test-info.php << 'PHPEOF'
<?php
phpinfo();
PHPEOF
echo "Created: https://coprra.com/test-info.php"
""")
print(stdout.read().decode())

# Create super simple test
print("\n📝 Creating simple test file...")
stdin, stdout, stderr = ssh.exec_command("""cat > ~/public_html/public/test.php << 'TESTEOF'
<?php
echo "PHP is working on " . date('Y-m-d H:i:s');
TESTEOF
echo "Created: https://coprra.com/test.php"
""")
print(stdout.read().decode())

# Test these files
print("\n🌐 Testing simple PHP file...")
import requests
try:
    response = requests.get("https://coprra.com/test.php", timeout=10, verify=False)
    print(f"   Status: {response.status_code}")
    print(f"   Content: {response.text}")
    if "PHP is working" in response.text:
        print("   ✅ PHP execution works!")
    elif response.status_code == 404:
        print("   ⚠️  File not found - routing issue")
    else:
        print("   ⚠️  Unexpected response")
except Exception as e:
    print(f"   Error: {e}")

# Check Laravel public/index.php
print("\n📄 Checking Laravel index.php content...")
stdin, stdout, stderr = ssh.exec_command("head -20 ~/public_html/public/index.php")
index_content = stdout.read().decode()
if "autoload" in index_content and "bootstrap" in index_content:
    print("   ✅ index.php looks correct")
else:
    print("   ⚠️  index.php may be incorrect")
    print(index_content[:200])

ssh.close()

print("\n" + "=" * 80)
print("✅ DIAGNOSTIC COMPLETE")
print("=" * 80)
print("""
📊 Check these URLs in your browser:
   1. https://coprra.com/test.php (simple PHP test)
   2. https://coprra.com/test-info.php (phpinfo)
   3. https://coprra.com (Laravel app)
""")
