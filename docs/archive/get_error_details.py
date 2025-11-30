#!/usr/bin/env python3
"""
Get detailed error information with debug enabled
"""

import paramiko
import time

SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USERNAME = "u990109832"
SSH_PASSWORD = "Hamo1510@Rayan146"

print("🔍 GETTING DETAILED ERROR INFORMATION")

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(SSH_HOST, SSH_PORT, SSH_USERNAME, SSH_PASSWORD, timeout=30)

# Enable debug
print("\n🐛 Enabling debug mode...")
stdin, stdout, stderr = ssh.exec_command("""cd ~/public_html &&
sed -i 's/APP_DEBUG=false/APP_DEBUG=true/' .env &&
sed -i 's/APP_ENV=production/APP_ENV=local/' .env &&
php artisan config:clear
""")
stdout.read()
print("✅ Debug enabled")

# Clear old logs
print("\n🗑️  Clearing old logs...")
stdin, stdout, stderr = ssh.exec_command("rm -f ~/public_html/storage/logs/laravel-*.log && echo '' > ~/public_html/storage/logs/laravel.log")
stdout.read()

# Make a request to generate fresh error
print("\n🌐 Making request to generate error...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && curl -k https://coprra.com > /dev/null 2>&1")
stdout.read()

time.sleep(2)

# Get the latest error
print("\n📋 Latest Laravel error:")
stdin, stdout, stderr = ssh.exec_command("tail -100 ~/public_html/storage/logs/laravel.log")
log_content = stdout.read().decode()

if log_content.strip():
    print(log_content)
else:
    print("⚠️  No errors in Laravel log")

# Check if it's a view/blade error
if "icon" in log_content.lower() or "component" in log_content.lower():
    print("\n🔧 Component issue detected - checking components...")
    stdin, stdout, stderr = ssh.exec_command("ls -la ~/public_html/resources/views/components/")
    print(stdout.read().decode())

# Try to access with wget and see headers
print("\n🌐 Checking HTTP headers...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && wget --spider -S https://coprra.com 2>&1 | head -20")
headers = stdout.read().decode()
if headers.strip():
    print(headers)

# Check if HomeController exists
print("\n📄 Checking HomeController...")
stdin, stdout, stderr = ssh.exec_command("ls -lh ~/public_html/app/Http/Controllers/HomeController.php 2>&1")
controller_check = stdout.read().decode()
print(controller_check)

if "No such file" in controller_check:
    print("⚠️  HomeController missing - creating it...")

    home_controller = """<?php

namespace App\\Http\\Controllers;

use Illuminate\\Http\\Request;

class HomeController extends Controller
{
    public function index()
    {
        return view('welcome');
    }
}
"""

    stdin, stdout, stderr = ssh.exec_command(f"""cd ~/public_html &&
cat > app/Http/Controllers/HomeController.php << 'HOMEEOF'
{home_controller}
HOMEEOF
echo "✅ HomeController created"
""")
    print(stdout.read().decode())

    # Clear cache again
    stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan route:clear && php artisan config:clear")
    stdout.read()

# Disable debug back
print("\n🔒 Disabling debug...")
stdin, stdout, stderr = ssh.exec_command("""cd ~/public_html &&
sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env &&
sed -i 's/APP_ENV=local/APP_ENV=production/' .env &&
php artisan config:clear
""")
stdout.read()

ssh.close()

print("\n✅ Complete!")
print("\n📋 Summary:")
print("   Check the error log output above for the specific issue")
print("   Common issues:")
print("   - Missing controller")
print("   - Missing view")
print("   - Missing component")
print("   - Syntax error")
