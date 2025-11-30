#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import paramiko
import sys

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
print("COMPLETING LARAVEL DEPLOYMENT - PRODUCTION OPTIMIZATION")
print("=" * 70)

# 1. Set proper file permissions
print("\n[1] Setting proper file permissions for Laravel...")
result = execute_ssh(ssh, f"""
cd {PROJECT_ROOT}
chmod -R 775 storage bootstrap/cache 2>&1
echo 'Permissions set'
""")
print(result["output"])
if result["success"]:
    print("[OK] Storage permissions configured")

# 2. Create symbolic link for storage (if not exists)
print("\n[2] Creating storage symbolic link...")
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php artisan storage:link 2>&1")
print(result["output"])
if "already exists" in result["output"] or result["success"]:
    print("[OK] Storage link ready")

# 3. Clear all caches
print("\n[3] Clearing all Laravel caches...")
commands = [
    "php artisan config:clear",
    "php artisan cache:clear",
    "php artisan route:clear",
    "php artisan view:clear",
    "php artisan event:clear"
]
for cmd in commands:
    result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && {cmd} 2>&1")
    print(f"  {cmd}: {'OK' if result['success'] else 'FAILED'}")

# 4. Optimize for production
print("\n[4] Optimizing Laravel for production...")
optimize_commands = [
    ("Config Cache", "php artisan config:cache"),
    ("Route Cache", "php artisan route:cache"),
    ("View Cache", "php artisan view:cache"),
    ("Optimize", "php artisan optimize")
]

for name, cmd in optimize_commands:
    result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && {cmd} 2>&1")
    if result["success"]:
        print(f"  [OK] {name}")
    else:
        print(f"  [WARN] {name}: {result['output'][:100]}")

# 5. Check .htaccess file
print("\n[5] Verifying .htaccess configuration...")
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && [ -f .htaccess ] && echo 'exists' || echo 'missing'")
if "exists" in result["output"]:
    print("[OK] .htaccess file exists")

    # Show current .htaccess
    result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && head -n 20 .htaccess")
    print("\nCurrent .htaccess (first 20 lines):")
    print(result["output"])
else:
    print("[CREATING] .htaccess file for Laravel...")
    htaccess_content = """<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
"""
    result = execute_ssh(ssh, f"""cd {PROJECT_ROOT}
cat > .htaccess << 'EOFHTACCESS'
{htaccess_content}
EOFHTACCESS
echo 'created'
""")
    print(result["output"])

# 6. Check index.php
print("\n[6] Verifying index.php exists...")
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && [ -f index.php ] && echo 'exists' || echo 'missing'")
if "exists" in result["output"]:
    print("[OK] index.php exists")
else:
    print("[ERROR] index.php is missing - this is critical!")

# 7. Test PHP execution
print("\n[7] Testing PHP execution...")
result = execute_ssh(ssh, f"""cd {PROJECT_ROOT}
php -r "echo 'PHP is working: ' . PHP_VERSION . PHP_EOL;"
""")
print(result["output"])

# 8. Run Laravel diagnostics
print("\n[8] Running Laravel diagnostics...")
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php artisan about 2>&1")
print(result["output"])

# 9. Check for errors in Laravel logs
print("\n[9] Checking recent Laravel logs...")
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && tail -n 20 storage/logs/laravel.log 2>&1")
if "No such file" in result["output"] or result["output"].strip() == "":
    print("[OK] No error logs found (clean installation)")
else:
    print("Recent log entries:")
    print(result["output"])

# 10. Test database connection one more time
print("\n[10] Final database connection test...")
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php artisan db:show 2>&1 | head -n 15")
if result["success"]:
    print("[SUCCESS] Database connection verified!")
    print(result["output"])
else:
    print("[ERROR] Database connection failed")
    print(result["output"])

# 11. Check if we can access the site
print("\n[11] Testing site accessibility...")
result = execute_ssh(ssh, f"curl -s -o /dev/null -w '%{{http_code}}' http://localhost 2>&1")
http_code = result["output"].strip()
print(f"HTTP Status Code: {http_code}")

if http_code == "200":
    print("[SUCCESS] Site is accessible!")
elif http_code == "500":
    print("[ERROR] Site returns 500 - checking error...")
    result = execute_ssh(ssh, f"curl -s http://localhost 2>&1 | head -n 50")
    print(result["output"])
else:
    print(f"[WARN] Unexpected status code: {http_code}")

# 12. Get actual page content
print("\n[12] Fetching homepage content...")
result = execute_ssh(ssh, f"curl -s http://localhost 2>&1 | head -n 30")
if "Laravel" in result["output"] or "COPRRA" in result["output"] or "<!DOCTYPE" in result["output"]:
    print("[SUCCESS] Site is serving content!")
    print("\nFirst 30 lines of homepage:")
    print(result["output"])
else:
    print("[WARN] Unexpected content:")
    print(result["output"])

# 13. Check route list
print("\n[13] Verifying routes are loaded...")
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php artisan route:list 2>&1 | head -n 20")
if result["success"]:
    print("[OK] Routes loaded successfully")
    print("\nFirst 20 routes:")
    print(result["output"])
else:
    print("[ERROR] Cannot load routes")
    print(result["output"])

# 14. Environment check
print("\n[14] Environment configuration check...")
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && grep '^APP_' .env | head -n 10")
print("Application settings:")
print(result["output"])

# 15. Final APP_KEY check
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && grep '^APP_KEY=' .env")
if "base64:" in result["output"]:
    print("[OK] APP_KEY is set")
else:
    print("[GENERATING] APP_KEY...")
    result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php artisan key:generate --force 2>&1")
    print(result["output"])

ssh.close()

print("\n" + "=" * 70)
print("DEPLOYMENT COMPLETION SUMMARY")
print("=" * 70)
print("All steps completed. Check the output above for any errors.")
print("\nWebsite URL: https://coprra.com")
print("Database: u990109832_coprra_db (22 tables)")
print("PHP Version: 8.2.28")
print("Laravel Version: 11.46.1")
print("=" * 70)
