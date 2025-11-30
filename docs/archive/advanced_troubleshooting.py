#!/usr/bin/env python3
"""
Advanced troubleshooting - trying different approaches
"""

import paramiko
import time

SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USERNAME = "u990109832"
SSH_PASSWORD = "Hamo1510@Rayan146"

print("=" * 80)
print("🔧 ADVANCED TROUBLESHOOTING - NEW APPROACHES")
print("=" * 80)

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(SSH_HOST, SSH_PORT, SSH_USERNAME, SSH_PASSWORD, timeout=30)
print("✅ Connected!\n")

# Approach 1: Check PHP error log in different locations
print("📋 APPROACH 1: Check ALL possible error logs...")
error_log_locations = [
    "~/logs/error_log",
    "~/public_html/error_log",
    "/usr/local/lsws/logs/stderr.log",
    "/var/log/apache2/error.log",
    "/var/log/httpd/error_log",
    "~/public_html/storage/logs/laravel.log"
]

for location in error_log_locations:
    stdin, stdout, stderr = ssh.exec_command(f"tail -30 {location} 2>/dev/null")
    content = stdout.read().decode()
    if content.strip() and len(content) > 50:
        print(f"\n📄 Found errors in {location}:")
        print(content[-500:])  # Last 500 chars
        break

# Approach 2: Create super simple PHP test in public root
print("\n🧪 APPROACH 2: Testing direct PHP execution...")
stdin, stdout, stderr = ssh.exec_command("""cat > ~/public_html/public/simple-test.php << 'PHPEOF'
<?php
echo "Direct PHP works!<br>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Extensions: " . implode(", ", get_loaded_extensions());
?>
PHPEOF
chmod 644 ~/public_html/public/simple-test.php
echo "Created: https://coprra.com/simple-test.php"
""")
print(stdout.read().decode())

# Test it
import requests
try:
    response = requests.get("https://coprra.com/simple-test.php", timeout=10, verify=False)
    print(f"   Status: {response.status_code}")
    if response.status_code == 200:
        print(f"   ✅ Direct PHP works!")
        print(f"   Content: {response.text[:200]}")
    else:
        print(f"   ❌ Status {response.status_code}")
except Exception as e:
    print(f"   Error: {e}")

# Approach 3: Check if PDO SQLite is available
print("\n💾 APPROACH 3: Checking PDO SQLite extension...")
stdin, stdout, stderr = ssh.exec_command("php -m | grep -i pdo")
pdo_check = stdout.read().decode()
print(f"   PDO modules: {pdo_check}")

if "pdo_sqlite" not in pdo_check.lower():
    print("   ⚠️  PDO SQLite may not be enabled!")

# Approach 4: Create a minimal Laravel test
print("\n🧪 APPROACH 4: Creating minimal Laravel endpoint...")
minimal_route = """<?php

use Illuminate\\Support\\Facades\\Route;

Route::get('/minimal-test', function () {
    return response()->json([
        'status' => 'working',
        'laravel' => app()->version(),
        'php' => PHP_VERSION,
        'db' => DB::connection()->getPdo() ? 'connected' : 'failed'
    ]);
});

// Original routes below...
"""

stdin, stdout, stderr = ssh.exec_command(f"""cd ~/public_html &&
# Backup current routes
cp routes/web.php routes/web.php.bak2

# Prepend minimal route
cat > routes/web.php.new << 'ROUTEEOF'
{minimal_route}
ROUTEEOF

# Append original routes (skip first few lines)
tail -n +3 routes/web.php.bak2 >> routes/web.php.new

# Replace
mv routes/web.php.new routes/web.php

# Clear cache
php artisan route:clear 2>&1
php artisan config:clear 2>&1

echo "✅ Minimal route added"
""")
print(stdout.read().decode())

# Test minimal route
print("\n🌐 Testing minimal Laravel route...")
try:
    response = requests.get("https://coprra.com/minimal-test", timeout=10, verify=False)
    print(f"   Status: {response.status_code}")
    if response.status_code == 200:
        print(f"   ✅ Laravel is working!")
        print(f"   Response: {response.text}")
    else:
        print(f"   Status: {response.status_code}")
except Exception as e:
    print(f"   Error: {e}")

# Approach 5: Bypass .htaccess temporarily
print("\n🔧 APPROACH 5: Testing without .htaccess redirection...")
stdin, stdout, stderr = ssh.exec_command("""cd ~/public_html &&
# Rename .htaccess temporarily
mv .htaccess .htaccess.disabled 2>/dev/null || true
echo "✅ .htaccess disabled"
""")
print(stdout.read().decode())

# Test direct public/index.php
print("\n🌐 Testing direct access to public/index.php...")
try:
    response = requests.get("https://coprra.com/public/index.php", timeout=10, verify=False)
    print(f"   Status: {response.status_code}")
    if response.status_code == 200:
        print(f"   ✅ Works via public/index.php!")
        print(f"   Content length: {len(response.text)} bytes")
        print(f"   Preview: {response.text[:200]}")
    else:
        print(f"   Status: {response.status_code}")
except Exception as e:
    print(f"   Error: {e}")

# Re-enable .htaccess
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && mv .htaccess.disabled .htaccess 2>/dev/null || true")
stdout.read()

# Approach 6: Check for PHP extensions
print("\n🔍 APPROACH 6: Checking required PHP extensions...")
required_extensions = [
    'PDO', 'pdo_sqlite', 'mbstring', 'tokenizer',
    'xml', 'ctype', 'json', 'bcmath', 'fileinfo'
]

stdin, stdout, stderr = ssh.exec_command("php -m")
installed_extensions = stdout.read().decode().lower()

missing = []
for ext in required_extensions:
    if ext.lower() not in installed_extensions:
        missing.append(ext)

if missing:
    print(f"   ⚠️  Missing extensions: {', '.join(missing)}")
else:
    print(f"   ✅ All required extensions installed")

# Approach 7: Check index.php
print("\n📄 APPROACH 7: Verifying public/index.php...")
stdin, stdout, stderr = ssh.exec_command("cat ~/public_html/public/index.php")
index_content = stdout.read().decode()

if "LARAVEL_START" in index_content or "bootstrap" in index_content:
    print("   ✅ index.php looks correct")
else:
    print("   ⚠️  index.php may be incorrect, recreating...")

    # Upload correct index.php
    correct_index = """<?php

use Illuminate\\Contracts\\Http\\Kernel;
use Illuminate\\Http\\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
"""

    stdin, stdout, stderr = ssh.exec_command(f"""cat > ~/public_html/public/index.php << 'INDEXEOF'
{correct_index}
INDEXEOF
chmod 644 ~/public_html/public/index.php
echo "✅ index.php recreated"
""")
    print(stdout.read().decode())

# Approach 8: Final comprehensive test
print("\n🎯 APPROACH 8: Final comprehensive test...")

# Clear everything
stdin, stdout, stderr = ssh.exec_command("""cd ~/public_html &&
php artisan optimize:clear 2>&1 &&
php artisan config:cache 2>&1 &&
php artisan route:cache 2>&1
""")
stdout.read()

# Test main site
print("\n🌐 Final test of main website...")
try:
    response = requests.get("https://coprra.com", timeout=10, verify=False)
    print(f"   Status: {response.status_code}")

    if response.status_code == 200:
        print(f"   🎉 SUCCESS! Website is working!")
        print(f"   Response size: {len(response.text)} bytes")
        print(f"\n   Preview:\n{response.text[:300]}")
    elif response.status_code == 500:
        print(f"   Still 500 - checking response body...")
        if response.text:
            print(f"   Body: {response.text[:500]}")
    else:
        print(f"   Unexpected status: {response.status_code}")

except Exception as e:
    print(f"   Error: {e}")

ssh.close()

print("\n" + "=" * 80)
print("✅ TROUBLESHOOTING COMPLETE")
print("=" * 80)
print("""
📊 Results will show which approach worked.
🔍 Check the output above for SUCCESS messages.
""")
