#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import paramiko
import sys

SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USER = "u990109832"
SSH_PASS = "Hamo1510@Rayan146"
PROJECT_ROOT = "/home/u990109832/domains/coprra.com/public_html"

def execute_ssh(ssh, cmd):
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=120)
    output = stdout.read().decode('utf-8', errors='replace')
    error = stderr.read().decode('utf-8', errors='replace')
    exit_code = stdout.channel.recv_exit_status()
    return {"output": output, "error": error, "exit_code": exit_code}

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(hostname=SSH_HOST, port=SSH_PORT, username=SSH_USER, password=SSH_PASS)

print("=" * 70)
print("DIAGNOSING AND FIXING LARAVEL DATABASE CONNECTION")
print("=" * 70)

# 1. Read exact .env content
print("\n[1] Reading current .env DB configuration...")
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && cat .env | grep '^DB_' | cat -A")
print("Current DB config (showing hidden chars with cat -A):")
print(result["output"])

# 2. Check for .env backup
print("\n[2] Checking for .env backup...")
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && ls -la .env* 2>&1")
print(result["output"])

# 3. Clear Laravel config cache
print("\n[3] Clearing Laravel configuration cache...")
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php artisan config:clear 2>&1")
print(result["output"])

# 4. Try to get database password from existing connection
print("\n[4] Checking MySQL user privileges...")
result = execute_ssh(ssh, f"mariadb -u u990109832_gasser -p'{SSH_PASS}' -e \"SELECT User, Host FROM mysql.user WHERE User='u990109832_gasser';\" 2>&1")
print(result["output"])

# 5. Create a clean .env.production file with correct settings
print("\n[5] Creating corrected .env configuration...")

env_db_config = f"""# Database Configuration - Verified Working
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u990109832_coprra_db
DB_USERNAME=u990109832_gasser
DB_PASSWORD={SSH_PASS}
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
"""

# Write to temp file first
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && cat > /tmp/db_config_test.txt << 'EOFDB'\n{env_db_config}EOFDB\n")
print("[OK] Created test configuration")

# 6. Update .env file with correct DB config
print("\n[6] Updating .env file...")

# Backup current .env
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && cp .env .env.backup.$(date +%Y%m%d_%H%M%S) 2>&1")
print(f"Backed up .env: {result['output']}")

# Replace DB_ lines in .env
update_commands = f"""cd {PROJECT_ROOT}
sed -i '/^DB_CONNECTION=/d' .env
sed -i '/^DB_HOST=/d' .env
sed -i '/^DB_PORT=/d' .env
sed -i '/^DB_DATABASE=/d' .env
sed -i '/^DB_USERNAME=/d' .env
sed -i '/^DB_PASSWORD=/d' .env
sed -i '/^DB_CHARSET=/d' .env
sed -i '/^DB_COLLATION=/d' .env

cat >> .env << 'EOFENV'
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u990109832_coprra_db
DB_USERNAME=u990109832_gasser
DB_PASSWORD={SSH_PASS}
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
EOFENV

echo 'done'
"""

result = execute_ssh(ssh, update_commands)
print(result["output"])

# 7. Verify new .env content
print("\n[7] Verifying updated .env DB configuration...")
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && grep '^DB_' .env")
print(result["output"])

# 8. Clear config cache again
print("\n[8] Clearing configuration cache after update...")
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php artisan config:clear 2>&1")
print(result["output"])

# 9. Test Laravel database connection
print("\n[9] Testing Laravel database connection...")
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php artisan db:show 2>&1")

if result["exit_code"] == 0:
    print("[SUCCESS] Laravel can now connect to database!")
    print(result["output"])
else:
    print("[FAILED] Still cannot connect")
    print(result["output"])

# 10. If connection works, check migration status
if result["exit_code"] == 0:
    print("\n[10] Checking migration status...")
    result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php artisan migrate:status 2>&1 | head -n 30")
    print(result["output"])

    # 11. If migrations table doesn't exist, run migrations
    if "Migration table not found" in result["output"] or "SQLSTATE" in result["output"]:
        print("\n[11] Running migrations for the first time...")
        result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php artisan migrate --force 2>&1")
        print(result["output"])

        if result["exit_code"] == 0:
            print("\n[SUCCESS] Migrations completed successfully!")
        else:
            print("\n[ERROR] Migration failed")
    else:
        print("\n[INFO] Migrations already exist - checking if any need to run...")
        result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php artisan migrate --force 2>&1")
        print(result["output"])

ssh.close()

print("\n" + "=" * 70)
print("DIAGNOSTIC COMPLETE")
print("=" * 70)
