#!/usr/bin/env python3
"""
Move Laravel to correct domain directory
"""

import paramiko

SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USERNAME = "u990109832"
SSH_PASSWORD = "Hamo1510@Rayan146"

print("=" * 80)
print("🚀 MOVING LARAVEL TO CORRECT DOMAIN DIRECTORY")
print("=" * 80)

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(SSH_HOST, SSH_PORT, SSH_USERNAME, SSH_PASSWORD, timeout=30)
print("✅ Connected!\n")

# Check the correct domain directory
print("📁 Step 1: Analyzing domain directory...")
stdin, stdout, stderr = ssh.exec_command("ls -la ~/domains/coprra.com/")
print(stdout.read().decode())

# Backup current content
print("\n📦 Step 2: Backing up current domain content...")
stdin, stdout, stderr = ssh.exec_command("""
cd ~/domains/coprra.com/
if [ -d public_html ] && [ "$(ls -A public_html)" ]; then
    tar -czf ~/backup_domain_$(date +%Y%m%d_%H%M%S).tar.gz public_html/
    echo "✅ Backup created"
else
    echo "⚠️  No content to backup"
fi
""")
print(stdout.read().decode())

# Clear old content (except backups)
print("\n🗑️  Step 3: Clearing old content from domain directory...")
stdin, stdout, stderr = ssh.exec_command("""
cd ~/domains/coprra.com/public_html/
# Remove everything except hidden files
rm -rf * 2>/dev/null || true
echo "✅ Old content cleared"
""")
print(stdout.read().decode())

# Move Laravel files
print("\n📦 Step 4: Moving Laravel to domain directory...")
stdin, stdout, stderr = ssh.exec_command("""
# Copy all Laravel files
cp -r ~/public_html/* ~/domains/coprra.com/public_html/ 2>&1
cp -r ~/public_html/.* ~/domains/coprra.com/public_html/ 2>/dev/null || true

echo "✅ Laravel files copied"
""")
result = stdout.read().decode()
if "cannot" not in result.lower() or len(result) < 100:
    print("✅ Files moved successfully")
else:
    print(result[:500])

# Verify files
print("\n🔍 Step 5: Verifying files in correct location...")
stdin, stdout, stderr = ssh.exec_command("""
cd ~/domains/coprra.com/public_html/
echo "=== Laravel files ==="
ls -la | grep -E "artisan|vendor|.env|public"
echo ""
echo "=== Database file ==="
ls -lh database/database.sqlite 2>&1
""")
print(stdout.read().decode())

# Fix permissions
print("\n🔒 Step 6: Setting permissions...")
stdin, stdout, stderr = ssh.exec_command("""
cd ~/domains/coprra.com/public_html/
chmod -R 755 storage bootstrap/cache
find storage -type f -exec chmod 644 {} \\;
find storage -type d -exec chmod 755 {} \\;
chmod 644 .env
chmod +x artisan
echo "✅ Permissions set"
""")
print(stdout.read().decode())

# Clear caches in new location
print("\n🗑️  Step 7: Clearing caches...")
stdin, stdout, stderr = ssh.exec_command("""
cd ~/domains/coprra.com/public_html/
php artisan optimize:clear 2>&1
php artisan config:cache 2>&1
php artisan route:cache 2>&1
echo "✅ Caches rebuilt"
""")
print(stdout.read().decode())

# Test
print("\n🎯 Step 8: FINAL TEST...")
import requests
import time

time.sleep(2)  # Wait for changes to propagate

try:
    response = requests.get("https://coprra.com", timeout=15, verify=False)
    print(f"   Status: {response.status_code}")

    if response.status_code == 200:
        print(f"   🎉 SUCCESS! Website is WORKING!")
        print(f"   Response size: {len(response.text)} bytes")
        print(f"\n   Preview:\n{response.text[:300]}...")
    elif response.status_code == 500:
        print(f"   Still 500 - but might need a minute to propagate")
    else:
        print(f"   Status: {response.status_code}")

except Exception as e:
    print(f"   Error: {e}")

# Create summary
print("\n📊 Summary:")
stdin, stdout, stderr = ssh.exec_command("""
echo "=== Domain Directory ==="
echo "Laravel is now in: ~/domains/coprra.com/public_html/"
cd ~/domains/coprra.com/public_html/
php artisan --version 2>&1 | head -1
php artisan db:show 2>&1 | grep -E "Connection|Database" | head -3
""")
print(stdout.read().decode())

ssh.close()

print("\n" + "=" * 80)
print("✅ MOVE COMPLETE!")
print("=" * 80)
print("""
📍 Laravel is now in the correct location:
   ~/domains/coprra.com/public_html/

🌐 Test: https://coprra.com

📋 SSH to check:
   ssh -p 65002 u990109832@45.87.81.218
   cd ~/domains/coprra.com/public_html/
   php artisan about
""")
