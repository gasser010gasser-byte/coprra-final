#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import paramiko

SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USER = "u990109832"
SSH_PASS = "Hamo1510@Rayan146"
PROJECT_ROOT = "/home/u990109832/domains/coprra.com/public_html"

def execute_ssh(ssh, cmd, timeout=120):
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=timeout)
    output = stdout.read().decode('utf-8', errors='replace')
    error = stderr.read().decode('utf-8', errors='replace')
    exit_code = stdout.channel.recv_exit_status()
    return {"output": output, "error": error, "exit_code": exit_code, "success": exit_code == 0}

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(hostname=SSH_HOST, port=SSH_PORT, username=SSH_USER, password=SSH_PASS)

print("=" * 70)
print("RESTRUCTURING FOR HOSTINGER - MOVING PUBLIC FOLDER CONTENTS")
print("=" * 70)

# Strategy: For Hostinger, we need to move public/* to root
# because public_html IS the web root

# 1. Backup current structure first
print("\n[1] Creating backup of current structure...")
result = execute_ssh(ssh, f"""cd {PROJECT_ROOT}
tar -czf ../public_html_backup_$(date +%Y%m%d_%H%M%S).tar.gz . 2>&1 | tail -n 5
echo 'Backup created'
""", timeout=300)
print(result["output"])

# 2. Move all files from public/ to root (except those already there)
print("\n[2] Moving public folder contents to web root...")
result = execute_ssh(ssh, f"""cd {PROJECT_ROOT}

# First, remove old test files to avoid conflicts
rm -f test-direct.php test.php test-public.php test-info.php simple-test.php 2>/dev/null

# Copy index.php from public to root (overwrite)
cp -f public/index.php ./index.php
chmod 644 index.php

# Copy .htaccess from public to root (if exists)
[ -f public/.htaccess ] && cp -f public/.htaccess ./.htaccess.public_copy

# Copy other public assets
rsync -av --ignore-existing public/build/ ./build/ 2>/dev/null || echo 'No build dir'
rsync -av --ignore-existing public/storage/ ./storage_link/ 2>/dev/null || echo 'No storage link'

# Copy favicon and manifest
cp -f public/favicon.ico ./ 2>/dev/null || echo 'No favicon'
cp -f public/manifest.json ./ 2>/dev/null || echo 'No manifest'

echo 'Files moved'
ls -la | grep index.php
""")
print(result["output"])

# 3. Update index.php to point to correct paths
print("\n[3] Updating index.php paths...")
result = execute_ssh(ssh, f"""cd {PROJECT_ROOT}

# Read current index.php
cat index.php | head -n 30
""")
print("Current index.php content:")
print(result["output"])

# Check if paths need updating
if "../vendor/autoload.php" in result["output"] or "../bootstrap/app.php" in result["output"]:
    print("\n[INFO] index.php still has ../ paths, fixing...")
    result = execute_ssh(ssh, f"""cd {PROJECT_ROOT}

# Update paths in index.php
sed -i "s|require __DIR__.'/../vendor/autoload.php';|require __DIR__.'/vendor/autoload.php';|g" index.php
sed -i "s|require_once __DIR__.'/../vendor/autoload.php';|require_once __DIR__.'/vendor/autoload.php';|g" index.php
sed -i "s|__DIR__.'/../bootstrap/app.php'|__DIR__.'/bootstrap/app.php'|g" index.php
sed -i "s|__DIR__ . '/../bootstrap/app.php'|__DIR__ . '/bootstrap/app.php'|g" index.php

echo 'Paths updated'
cat index.php | grep -E 'vendor|bootstrap'
""")
    print(result["output"])

# 4. Create proper .htaccess for web root
print("\n[4] Creating final .htaccess for web root...")

htaccess_final = """<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
"""

result = execute_ssh(ssh, f"""cd {PROJECT_ROOT}
cat > .htaccess << 'EOFHTFINAL'
{htaccess_final}
EOFHTFINAL
chmod 644 .htaccess
echo 'htaccess created'
""")
print(result["output"])

# 5. Clear all Laravel caches again
print("\n[5] Clearing Laravel caches...")
result = execute_ssh(ssh, f"""cd {PROJECT_ROOT}
php artisan config:clear 2>&1
php artisan route:clear 2>&1
php artisan view:clear 2>&1
php artisan cache:clear 2>&1
echo 'Caches cleared'
""")
print(result["output"])

# 6. Rebuild caches for production
print("\n[6] Rebuilding production caches...")
result = execute_ssh(ssh, f"""cd {PROJECT_ROOT}
php artisan config:cache 2>&1 | head -n 3
php artisan route:cache 2>&1 | head -n 3
php artisan view:cache 2>&1 | head -n 3
echo 'Caches rebuilt'
""")
print(result["output"])

# 7. Test the site
print("\n[7] Testing site access...")
result = execute_ssh(ssh, f"curl -s -o /dev/null -w '%{{http_code}}' http://localhost/ 2>&1")
status = result["output"].strip()
print(f"HTTP Status: {status}")

if status == "200":
    print("\n[SUCCESS] Site is now accessible!")
    result = execute_ssh(ssh, f"curl -s http://localhost/ 2>&1 | head -n 40")
    print("\nSite content (first 40 lines):")
    print(result["output"])
else:
    print(f"\n[STILL FAILING] Status code: {status}")
    result = execute_ssh(ssh, f"curl -s http://localhost/ 2>&1 | head -n 30")
    print(result["output"])

# 8. Verify index.php can be accessed
print("\n[8] Testing direct index.php access...")
result = execute_ssh(ssh, f"curl -s -I http://localhost/index.php 2>&1 | head -n 10")
print(result["output"])

# 9. Final check - Laravel is responding
print("\n[9] Testing Laravel route...")
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php artisan route:list 2>&1 | grep -E 'GET.*/' | head -n 3")
print("Available routes:")
print(result["output"])

ssh.close()

print("\n" + "=" * 70)
print("RESTRUCTURING COMPLETE")
print("=" * 70)
print("The site should now be accessible at: https://coprra.com")
print("If still showing errors, check:")
print("1. Apache configuration in cPanel")
print("2. PHP version settings in cPanel (should be 8.2)")
print("3. File permissions (should be 644 for files, 755 for directories)")
print("=" * 70)
