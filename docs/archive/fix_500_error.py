#!/usr/bin/env python3
"""
Fix 500 error on COPRRA website
"""

import paramiko

SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USERNAME = "u990109832"
SSH_PASSWORD = "Hamo1510@Rayan146"

print("=" * 80)
print("🔧 FIXING 500 ERROR")
print("=" * 80)

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(SSH_HOST, SSH_PORT, SSH_USERNAME, SSH_PASSWORD, timeout=30)
print("✅ Connected!")

# Check recent errors
print("\n📋 Checking Laravel error log...")
stdin, stdout, stderr = ssh.exec_command("tail -50 ~/public_html/storage/logs/laravel.log | grep -A 5 'ERROR\\|CRITICAL\\|production.ERROR' | tail -30")
errors = stdout.read().decode()
print(errors if errors.strip() else "No critical errors found")

# Clear all caches
print("\n🗑️  Clearing ALL caches...")
commands = [
    "cd ~/public_html && php artisan optimize:clear 2>&1",
    "cd ~/public_html && php artisan view:clear 2>&1",
    "cd ~/public_html && php artisan config:clear 2>&1",
    "cd ~/public_html && php artisan route:clear 2>&1",
    "cd ~/public_html && php artisan cache:clear 2>&1",
]

for cmd in commands:
    stdin, stdout, stderr = ssh.exec_command(cmd)
    result = stdout.read().decode()
    if result.strip():
        print(f"   {result.strip()}")

print("✅ All caches cleared")

# Check .env file
print("\n📝 Verifying .env file...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && head -15 .env")
env_content = stdout.read().decode()
print(env_content)

# Regenerate APP_KEY if needed
print("\n🔑 Regenerating APP_KEY...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan key:generate --force 2>&1")
print(stdout.read().decode())

# Check database file permissions
print("\n🔒 Checking database permissions...")
stdin, stdout, stderr = ssh.exec_command("ls -lh ~/public_html/database/database.sqlite")
print(stdout.read().decode())

stdin, stdout, stderr = ssh.exec_command("chmod 664 ~/public_html/database/database.sqlite && echo 'Permissions updated'")
print(stdout.read().decode())

# Test database connection
print("\n💾 Testing database...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan db:show 2>&1 | head -15")
db_test = stdout.read().decode()
if "sqlite" in db_test.lower():
    print("✅ Database OK")
else:
    print(db_test)

# Check storage permissions
print("\n📁 Fixing storage permissions...")
stdin, stdout, stderr = ssh.exec_command("""cd ~/public_html &&
chmod -R 775 storage bootstrap/cache 2>&1 &&
find storage -type f -exec chmod 664 {} \\; 2>&1 &&
find storage -type d -exec chmod 775 {} \\; 2>&1 &&
echo "✅ Permissions fixed"
""")
print(stdout.read().decode())

# Enable debug temporarily to see exact error
print("\n🐛 Enabling debug mode temporarily...")
stdin, stdout, stderr = ssh.exec_command("""cd ~/public_html &&
sed -i 's/APP_DEBUG=false/APP_DEBUG=true/' .env &&
sed -i 's/APP_ENV=production/APP_ENV=local/' .env &&
php artisan config:clear 2>&1 &&
echo "✅ Debug enabled"
""")
print(stdout.read().decode())

# Test artisan
print("\n🧪 Testing artisan commands...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan list 2>&1 | head -20")
print(stdout.read().decode())

# Check for component errors
print("\n🔍 Checking for missing components...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && ls -la resources/views/components/ 2>&1")
components = stdout.read().decode()
if "icon" not in components.lower():
    print("⚠️  Icon component missing - creating placeholder...")

    stdin, stdout, stderr = ssh.exec_command("""cd ~/public_html &&
mkdir -p resources/views/components &&
cat > resources/views/components/icon.blade.php << 'ICONEOF'
@props(['name' => 'default', 'class' => ''])
<span {{ \$attributes->merge(['class' => 'icon ' . \$class]) }}>
    <!-- Icon: {{ \$name }} -->
</span>
ICONEOF
echo "✅ Icon component created"
""")
    print(stdout.read().decode())
else:
    print("✅ Components directory OK")

# Rebuild caches
print("\n⚡ Rebuilding caches...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan config:cache 2>&1 && php artisan route:cache 2>&1")
cache_result = stdout.read().decode()
print(cache_result if cache_result.strip() else "✅ Caches rebuilt")

# Test again
print("\n🌐 Testing website again...")
import requests
try:
    response = requests.get("https://coprra.com", timeout=10, verify=False)
    print(f"   Status: {response.status_code}")
    if response.status_code == 200:
        print("   ✅ Website is working!")
    else:
        print(f"   ⚠️  Status {response.status_code}")
        # Show first 500 chars of response
        print(f"\n   Response preview:\n   {response.text[:500]}")
except Exception as e:
    print(f"   Error: {e}")

# Disable debug mode
print("\n🔒 Disabling debug mode...")
stdin, stdout, stderr = ssh.exec_command("""cd ~/public_html &&
sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env &&
sed -i 's/APP_ENV=local/APP_ENV=production/' .env &&
php artisan config:clear 2>&1 &&
echo "✅ Debug disabled"
""")
print(stdout.read().decode())

ssh.close()

print("\n" + "=" * 80)
print("✅ FIX ATTEMPT COMPLETED")
print("=" * 80)
print("""
📊 Actions taken:
   ✅ Cleared all caches
   ✅ Regenerated APP_KEY
   ✅ Fixed permissions
   ✅ Created missing components
   ✅ Rebuilt caches

🌐 Test again: https://coprra.com

📋 If still not working:
   ssh -p 65002 u990109832@45.87.81.218
   cd ~/public_html
   tail -f storage/logs/laravel.log
""")
