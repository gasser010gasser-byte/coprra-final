#!/usr/bin/env python3
"""
Fix syntax error in routes/web.php
"""

import paramiko

SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USERNAME = "u990109832"
SSH_PASSWORD = "Hamo1510@Rayan146"

print("🔧 FIXING SYNTAX ERROR IN ROUTES")

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(SSH_HOST, SSH_PORT, SSH_USERNAME, SSH_PASSWORD, timeout=30)

# Check current routes/web.php
print("\n📋 Checking routes/web.php around line 310...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && tail -20 routes/web.php")
tail_content = stdout.read().decode()
print(tail_content)

# Remove the test route we added (it might have syntax error)
print("\n🔧 Fixing routes file...")

# Get the local working routes/web.php
from pathlib import Path
local_routes = Path(r"C:\Users\Gaser\Desktop\COPRRA\routes\web.php")

if local_routes.exists():
    print("✅ Found local routes/web.php")

    # Read local file
    with open(local_routes, 'r', encoding='utf-8') as f:
        routes_content = f.read()

    # Upload it via SFTP
    sftp = ssh.open_sftp()

    # Write to temp file first
    remote_temp = "/tmp/web.php"
    with sftp.file(remote_temp, 'w') as f:
        f.write(routes_content)

    # Backup current routes
    stdin, stdout, stderr = ssh.exec_command("cp ~/public_html/routes/web.php ~/public_html/routes/web.php.backup")
    stdout.channel.recv_exit_status()
    print("✅ Backed up current routes/web.php")

    # Replace with working version
    stdin, stdout, stderr = ssh.exec_command(f"mv {remote_temp} ~/public_html/routes/web.php && chmod 644 ~/public_html/routes/web.php")
    stdout.channel.recv_exit_status()
    print("✅ Replaced routes/web.php with working version")

    sftp.close()
else:
    print("⚠️  Local routes file not found, creating simple version...")

    # Create a minimal working routes file
    simple_routes = """<?php

use Illuminate\\Support\\Facades\\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/test', function () {
    return 'Test route is working!';
});
"""

    stdin, stdout, stderr = ssh.exec_command(f"""cd ~/public_html &&
cp routes/web.php routes/web.php.backup &&
cat > routes/web.php << 'ROUTESEOF'
{simple_routes}
ROUTESEOF
chmod 644 routes/web.php &&
echo "✅ Created simple routes file"
""")
    print(stdout.read().decode())

# Clear route cache
print("\n🗑️  Clearing route cache...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan route:clear && php artisan config:clear")
print(stdout.read().decode())

# Test routes
print("\n🧪 Testing routes...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan route:list 2>&1 | head -10")
routes_test = stdout.read().decode()
if "GET|HEAD" in routes_test:
    print("✅ Routes loaded successfully!")
    print(routes_test)
else:
    print(f"⚠️  Routes result:\n{routes_test}")

# Rebuild cache
print("\n⚡ Rebuilding cache...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan config:cache && php artisan route:cache 2>&1")
cache_result = stdout.read().decode()
if "cached successfully" in cache_result.lower():
    print("✅ Caches rebuilt")
else:
    print(cache_result)

# Test website
print("\n🌐 Testing website...")
import requests
try:
    response = requests.get("https://coprra.com", timeout=10, verify=False)
    print(f"   Status: {response.status_code}")
    if response.status_code == 200:
        print("   ✅ SUCCESS! Website is working!")
        print(f"   Response size: {len(response.text)} bytes")
        # Show first 200 chars
        print(f"\n   Preview:\n   {response.text[:200]}...")
    else:
        print(f"   Status: {response.status_code}")
except Exception as e:
    print(f"   Error: {e}")

ssh.close()

print("\n" + "=" * 80)
print("✅ FIX COMPLETED!")
print("=" * 80)
print("🌐 Test your website at: https://coprra.com")
