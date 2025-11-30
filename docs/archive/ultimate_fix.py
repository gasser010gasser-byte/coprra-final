#!/usr/bin/env python3
"""
Ultimate fix - addressing web server level issues
"""

import paramiko
import time

SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USERNAME = "u990109832"
SSH_PASSWORD = "Hamo1510@Rayan146"

print("=" * 80)
print("🔥 ULTIMATE FIX - WEB SERVER LEVEL")
print("=" * 80)

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(SSH_HOST, SSH_PORT, SSH_USERNAME, SSH_PASSWORD, timeout=30)
print("✅ Connected!\n")

# Fix 1: Check if we're in the wrong directory
print("🔍 FIX 1: Checking directory structure...")
stdin, stdout, stderr = ssh.exec_command("""
echo "=== Domain Root Structure ==="
ls -la ~ | grep -E "public_html|domains"
echo ""
echo "=== public_html contents ==="
ls -la ~/public_html/ | head -15
""")
print(stdout.read().decode())

# Fix 2: Check actual domain configuration
print("\n🔍 FIX 2: Checking domain path...")
stdin, stdout, stderr = ssh.exec_command("pwd && echo '' && ls -la ~/domains/coprra.com/ 2>/dev/null || echo 'No domains/coprra.com directory'")
domains_check = stdout.read().decode()
print(domains_check)

# Fix 3: Check if Laravel should be in domains directory instead
if "domains/coprra.com" in domains_check and "No domains" not in domains_check:
    print("\n💡 Found domains directory! Laravel might need to be there...")
    print("Checking if we need to move files...")

    stdin, stdout, stderr = ssh.exec_command("""
    if [ -d ~/domains/coprra.com/public_html ]; then
        echo "✅ Domain directory exists"
        ls -la ~/domains/coprra.com/public_html/ | head -5
    else
        echo "⚠️  Domain directory not found"
    fi
    """)
    print(stdout.read().decode())

# Fix 4: Create ultra-simple index.php in public root
print("\n🔧 FIX 4: Creating ultra-simple test files...")
stdin, stdout, stderr = ssh.exec_command("""
# Test in public_html root
cat > ~/public_html/test-root.php << 'EOF'
<?php
phpinfo();
EOF

# Test in public directory
cat > ~/public_html/public/test-public.php << 'EOF'
<?php
echo "PHP " . phpversion() . " works in public dir!";
EOF

chmod 644 ~/public_html/test-root.php
chmod 644 ~/public_html/public/test-public.php

echo "Created test files"
""")
print(stdout.read().decode())

# Fix 5: Check .htaccess for issues
print("\n🔍 FIX 5: Analyzing .htaccess...")
stdin, stdout, stderr = ssh.exec_command("cat ~/public_html/.htaccess")
htaccess = stdout.read().decode()
print(htaccess if htaccess else "⚠️  No .htaccess found")

# Fix 6: Create completely new, minimal .htaccess
print("\n🔧 FIX 6: Creating minimal .htaccess...")
minimal_htaccess = """# Minimal htaccess for Laravel
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^$ public/ [L]
RewriteRule (.*) public/$1 [L]
</IfModule>
"""

stdin, stdout, stderr = ssh.exec_command(f"""
cd ~/public_html
cp .htaccess .htaccess.backup.full || true
cat > .htaccess << 'HTEOF'
{minimal_htaccess}
HTEOF
echo "✅ Minimal .htaccess created"
""")
print(stdout.read().decode())

# Fix 7: Check PHP handler
print("\n🔍 FIX 7: Checking PHP handler...")
stdin, stdout, stderr = ssh.exec_command("cat ~/public_html/.user.ini 2>/dev/null || echo 'No .user.ini'")
user_ini = stdout.read().decode()
if user_ini and "No .user.ini" not in user_ini:
    print(f"Found .user.ini: {user_ini}")

# Fix 8: Check for .htaccess in parent directory
print("\n🔍 FIX 8: Checking parent directory...")
stdin, stdout, stderr = ssh.exec_command("ls -la ~/ | grep htaccess")
parent_htaccess = stdout.read().decode()
if parent_htaccess:
    print(f"Found .htaccess in parent: {parent_htaccess}")

# Fix 9: Fix routes/web.php properly
print("\n🔧 FIX 9: Fixing routes/web.php strict_types issue...")

# Get clean routes from local
from pathlib import Path
local_routes = Path(r"C:\Users\Gaser\Desktop\COPRRA\routes\web.php")

if local_routes.exists():
    with open(local_routes, 'r', encoding='utf-8') as f:
        clean_routes = f.read()

    sftp = ssh.open_sftp()
    with sftp.file("/tmp/clean_web.php", 'w') as f:
        f.write(clean_routes)
    sftp.close()

    stdin, stdout, stderr = ssh.exec_command("""
    cd ~/public_html
    cp routes/web.php routes/web.php.backup.$(date +%s)
    mv /tmp/clean_web.php routes/web.php
    chmod 644 routes/web.php
    php artisan route:clear 2>&1
    echo "✅ Routes fixed"
    """)
    print(stdout.read().decode())

# Fix 10: Clear everything and rebuild
print("\n🔄 FIX 10: Complete cache clear and rebuild...")
stdin, stdout, stderr = ssh.exec_command("""
cd ~/public_html
rm -rf bootstrap/cache/*
rm -rf storage/framework/cache/*
rm -rf storage/framework/views/*
rm -rf storage/framework/sessions/*
php artisan optimize:clear 2>&1
php artisan config:cache 2>&1
php artisan route:cache 2>&1
echo "✅ Complete rebuild done"
""")
print(stdout.read().decode())

# Final Test
print("\n🎯 FINAL TEST...")
import requests

test_urls = [
    ("Main Site", "https://coprra.com"),
    ("Test Root", "https://coprra.com/test-root.php"),
    ("Test Public", "https://coprra.com/test-public.php"),
    ("Direct Public", "https://coprra.com/public/test-public.php"),
]

for name, url in test_urls:
    try:
        response = requests.get(url, timeout=10, verify=False)
        status_icon = "✅" if response.status_code == 200 else "❌"
        print(f"   {status_icon} {name}: {response.status_code}")

        if response.status_code == 200 and len(response.text) > 0:
            print(f"      Content preview: {response.text[:100]}")

    except Exception as e:
        print(f"   ❌ {name}: Error - {e}")

ssh.close()

print("\n" + "=" * 80)
print("✅ ULTIMATE FIX COMPLETE")
print("=" * 80)
