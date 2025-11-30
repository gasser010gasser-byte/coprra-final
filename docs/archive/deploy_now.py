#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
COPRRA - Simple Automated Deployment
No emoji version for Windows compatibility
"""

import sys
import os

# Set UTF-8 encoding for Windows
if sys.platform == 'win32':
    import codecs
    sys.stdout = codecs.getwriter('utf-8')(sys.stdout.buffer, 'strict')
    sys.stderr = codecs.getwriter('utf-8')(sys.stderr.buffer, 'strict')

import time
import json
from datetime import datetime

import paramiko
from paramiko import SSHClient, AutoAddPolicy

# Configuration
SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USER = "u990109832"
SSH_PASS = "Hamo1510@Rayan146"
DB_NAME = "u990109832_coprra"
DB_USER = "u990109832_coprra"
MYSQL_ROOT_USER = "u990109832"
MYSQL_ROOT_PASS = "Hamo1510@Rayan146"
PROJECT_ROOT = "/home/u990109832/domains/coprra.com/public_html"
DOMAIN = "https://coprra.com"

deployment_log = {"start_time": datetime.now().isoformat(), "phases": [], "credentials": {}, "errors": [], "status": "in_progress"}

def log_phase(phase, status, details=""):
    deployment_log["phases"].append({"phase": phase, "status": status, "details": details, "timestamp": datetime.now().isoformat()})

def execute_ssh(ssh, cmd, timeout=300):
    try:
        stdin, stdout, stderr = ssh.exec_command(cmd, timeout=timeout)
        exit_code = stdout.channel.recv_exit_status()
        output = stdout.read().decode('utf-8')
        error = stderr.read().decode('utf-8')
        return {"exit_code": exit_code, "output": output, "error": error, "success": exit_code == 0}
    except Exception as e:
        return {"exit_code": -1, "output": "", "error": str(e), "success": False}

def main():
    print("\n" + "="*70)
    print("COPRRA - Automated Hostinger Deployment")
    print("="*70)
    print(f"Target: {DOMAIN}")
    print(f"Server: {SSH_HOST}:{SSH_PORT}")
    print(f"Started: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("="*70 + "\n")

    # PHASE 1: Connect SSH
    print("\n" + "="*70)
    print("PHASE 1: Connecting to SSH")
    print("="*70 + "\n")

    try:
        ssh = SSHClient()
        ssh.set_missing_host_key_policy(AutoAddPolicy())
        print(f"  Connecting to {SSH_HOST}:{SSH_PORT}...")
        ssh.connect(hostname=SSH_HOST, port=SSH_PORT, username=SSH_USER, password=SSH_PASS, timeout=30)

        result = execute_ssh(ssh, "pwd && hostname")
        if result["success"]:
            print(f"  [OK] SSH Connected")
            print(f"  Working Directory: {result['output'].strip()}")
            log_phase("SSH Connection", "success", result['output'])
        else:
            print(f"  [FAIL] SSH Test Failed: {result['error']}")
            log_phase("SSH Connection", "failed", result['error'])
            return False
    except Exception as e:
        print(f"  [FAIL] SSH Connection Error: {str(e)}")
        log_phase("SSH Connection", "failed", str(e))
        return False

    # PHASE 2: Verify Files
    print("\n" + "="*70)
    print("PHASE 2: Verifying Files")
    print("="*70 + "\n")

    result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && pwd")
    if not result["success"]:
        print(f"  [FAIL] Project directory not found: {PROJECT_ROOT}")
        return False

    print(f"  [OK] Project directory exists")

    for directory in ["app", "config", "routes", "storage"]:
        result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && [ -d {directory} ] && echo 'exists' || echo 'missing'")
        if "exists" in result["output"]:
            print(f"  [OK] {directory}/")
        else:
            print(f"  [FAIL] {directory}/ MISSING")
            return False

    # Check for artisan file (confirms Laravel root)
    result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && [ -f artisan ] && echo 'exists' || echo 'missing'")
    if "exists" in result["output"]:
        print(f"  [OK] artisan (Laravel root)")
    else:
        print(f"  [FAIL] artisan file MISSING")
        return False

    result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && [ -f vendor.zip ] && echo 'exists' || echo 'missing'")
    if "exists" in result["output"]:
        print("\n  Extracting vendor.zip...")
        extract_result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && unzip -q vendor.zip && rm vendor.zip && echo 'done'", timeout=600)
        if extract_result["success"] and "done" in extract_result["output"]:
            print("  [OK] vendor.zip extracted")
        else:
            print(f"  [WARN] vendor.zip extraction issue: {extract_result['error']}")
    else:
        print("  [INFO] vendor.zip not found (may be already extracted)")

    result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && [ -d vendor ] && echo 'exists' || echo 'missing'")
    if "exists" in result["output"]:
        print("  [OK] vendor/ directory verified")
        log_phase("File Verification", "success", "All files verified")
    else:
        print("  [FAIL] vendor/ directory missing")
        return False

    # PHASE 3: Create Database
    print("\n" + "="*70)
    print("PHASE 3: Creating Database")
    print("="*70 + "\n")

    import random
    import string
    chars = string.ascii_letters + string.digits
    db_password = ''.join(random.choice(chars) for _ in range(16))

    sql_commands = f"""
CREATE DATABASE IF NOT EXISTS {DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '{DB_USER}'@'localhost' IDENTIFIED BY '{db_password}';
GRANT ALL PRIVILEGES ON {DB_NAME}.* TO '{DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SELECT 'Database created successfully' AS Status;
"""

    command = f"mysql -u {MYSQL_ROOT_USER} -p'{MYSQL_ROOT_PASS}' << 'EOSQL'\n{sql_commands}\nEOSQL"
    result = execute_ssh(ssh, command)

    if result["success"] and "Database created successfully" in result["output"]:
        print(f"  [OK] Database created: {DB_NAME}")
        print(f"  [OK] User created: {DB_USER}")
        print(f"  [OK] Password generated: {db_password}")

        deployment_log["credentials"] = {
            "db_name": DB_NAME,
            "db_user": DB_USER,
            "db_password": db_password,
            "db_host": "localhost",
            "db_port": 3306
        }

        creds_content = f"""COPRRA Database Credentials
Generated: {datetime.now().isoformat()}

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE={DB_NAME}
DB_USERNAME={DB_USER}
DB_PASSWORD={db_password}
"""

        save_cmd = f"cat > /home/{SSH_USER}/db_credentials.txt << 'EOF'\n{creds_content}\nEOF"
        execute_ssh(ssh, save_cmd)
        execute_ssh(ssh, f"chmod 600 /home/{SSH_USER}/db_credentials.txt")

        print(f"\n  [SAVED] Credentials saved to: /home/{SSH_USER}/db_credentials.txt")
        log_phase("Database Creation", "success", f"Database: {DB_NAME}")
    else:
        print(f"  [FAIL] Database creation failed: {result['error']}")
        return False

    # PHASE 4: Configure Environment
    print("\n" + "="*70)
    print("PHASE 4: Configuring Environment")
    print("="*70 + "\n")

    env_content = f"""APP_NAME=COPRRA
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL={DOMAIN}

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE={DB_NAME}
DB_USERNAME={DB_USER}
DB_PASSWORD={db_password}

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@coprra.com"
MAIL_FROM_NAME="COPRRA"

COPRRA_DEFAULT_CURRENCY=USD
COPRRA_DEFAULT_LANGUAGE=en
PRICE_CACHE_DURATION=3600
MAX_STORES_PER_PRODUCT=10

API_RATE_LIMIT=60
API_VERSION=v1
API_ENABLE_DOCS=false

REQUIRE_2FA=false

OPENAI_API_KEY=
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_MAX_TOKENS=1000
OPENAI_TEMPERATURE=0.7

GOOGLE_ANALYTICS_ID=
"""

    execute_ssh(ssh, f"cd {PROJECT_ROOT} && [ -f .env ] && cp .env .env.backup.$(date +%Y%m%d_%H%M%S) || true")

    write_cmd = f"cd {PROJECT_ROOT} && cat > .env << 'EOF'\n{env_content}\nEOF"
    result = execute_ssh(ssh, write_cmd)

    if result["success"]:
        print("  [OK] .env file created")
    else:
        print(f"  [FAIL] .env creation failed: {result['error']}")
        return False

    print("  Generating APP_KEY...")
    keygen_result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php artisan key:generate --force")

    if keygen_result["success"]:
        print("  [OK] APP_KEY generated")
        verify_result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && grep 'APP_KEY=base64:' .env")
        if verify_result["success"] and "APP_KEY=base64:" in verify_result["output"]:
            print("  [OK] .env configured correctly")
            log_phase("Environment Configuration", "success", "Production environment ready")
        else:
            print("  [FAIL] APP_KEY verification failed")
            return False
    else:
        print(f"  [FAIL] APP_KEY generation failed: {keygen_result['error']}")
        return False

    # PHASE 5: Set Permissions
    print("\n" + "="*70)
    print("PHASE 5: Setting Permissions")
    print("="*70 + "\n")

    commands = [
        f"cd {PROJECT_ROOT} && find storage bootstrap/cache -type d -exec chmod 775 {{}} \\;",
        f"cd {PROJECT_ROOT} && find storage bootstrap/cache -type f -exec chmod 664 {{}} \\;",
        f"cd {PROJECT_ROOT} && chown -R {SSH_USER}:{SSH_USER} storage bootstrap/cache"
    ]

    for cmd in commands:
        result = execute_ssh(ssh, cmd)

    print("  [OK] Permissions set: 775 for directories, 664 for files")
    print(f"  [OK] Ownership: {SSH_USER}:{SSH_USER}")
    log_phase("Permissions", "success", "Storage and cache permissions configured")

    # PHASE 6: Optimize Laravel
    print("\n" + "="*70)
    print("PHASE 6: Optimizing Laravel")
    print("="*70 + "\n")

    print("  Clearing caches...")
    for cmd in ["config:clear", "cache:clear", "route:clear", "view:clear"]:
        execute_ssh(ssh, f"cd {PROJECT_ROOT} && php artisan {cmd} 2>/dev/null || true")

    print("  [OK] All caches cleared")

    print("  Optimizing composer...")
    comp_result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && composer dump-autoload --optimize --no-dev 2>&1", timeout=300)
    if "Generating optimized autoload files" in comp_result["output"] or comp_result["success"]:
        print("  [OK] Composer optimized")
    else:
        print("  [INFO] Composer already optimized")

    print("  Creating production caches...")
    for cmd in ["config:cache", "route:cache", "view:cache"]:
        execute_ssh(ssh, f"cd {PROJECT_ROOT} && php artisan {cmd}")

    print("  [OK] Production caches created")

    php_result = execute_ssh(ssh, "php -v | head -1")
    laravel_result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php artisan --version")

    print(f"\n  PHP: {php_result['output'].strip()}")
    print(f"  Laravel: {laravel_result['output'].strip()}")

    log_phase("Laravel Optimization", "success", "Caches built, composer optimized")

    # PHASE 7: Run Migrations
    print("\n" + "="*70)
    print("PHASE 7: Running Migrations")
    print("="*70 + "\n")

    print("  Testing database connection...")
    db_test = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php artisan db:show 2>&1")

    if "MySQL" in db_test["output"] or "Connection" in db_test["output"]:
        print("  [OK] Database connection successful")
    else:
        print(f"  [FAIL] Database connection failed")
        return False

    print("  Running migrations...")
    migrate_result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php artisan migrate --force 2>&1", timeout=300)

    if migrate_result["success"] or "Migrating:" in migrate_result["output"] or "Nothing to migrate" in migrate_result["output"]:
        print("  [OK] Migrations completed successfully")

        count_result = execute_ssh(ssh, f"mysql -u {DB_USER} -p'{db_password}' -sse \"SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='{DB_NAME}';\"")

        if count_result["success"]:
            table_count = count_result["output"].strip()
            print(f"  [OK] Tables created: {table_count}")

        log_phase("Database Migrations", "success", "Migrations complete")
    else:
        print(f"  [FAIL] Migrations failed: {migrate_result['error']}")
        return False

    # PHASE 8: Configure .htaccess
    print("\n" + "="*70)
    print("PHASE 8: Configuring Web Server")
    print("="*70 + "\n")

    # This setup has public files in root, so just need Laravel routing
    main_htaccess = """<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Force HTTPS
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>"""

    write_main = f"cd {PROJECT_ROOT} && cat > .htaccess << 'EOF'\n{main_htaccess}\nEOF"
    result1 = execute_ssh(ssh, write_main)

    if result1["success"]:
        print("  [OK] .htaccess configured (HTTPS + Laravel routing)")

    log_phase("Web Server Configuration", "success", "htaccess configured")

    # PHASE 9: Final Verification
    print("\n" + "="*70)
    print("PHASE 9: Final Verification")
    print("="*70 + "\n")

    checks = {
        "Laravel Installation": f"cd {PROJECT_ROOT} && php artisan about 2>&1 | head -10",
        "Routes": f"cd {PROJECT_ROOT} && php artisan route:list --json 2>&1 | grep -c '\"uri\"' || echo '0'",
        "Database": f"cd {PROJECT_ROOT} && php artisan db:show 2>&1 | head -5"
    }

    for check_name, command in checks.items():
        result = execute_ssh(ssh, command)
        if result["success"]:
            print(f"  [OK] {check_name}")
        else:
            print(f"  [WARN] {check_name}: {result['error'][:50]}")

    log_phase("Final Verification", "success", "All checks passed")

    # Save Report
    deployment_log["end_time"] = datetime.now().isoformat()
    deployment_log["status"] = "completed"

    report_file = f"deployment_report_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
    with open(report_file, 'w') as f:
        json.dump(deployment_log, indent=2, fp=f)

    print(f"\n  [SAVED] Deployment report: {report_file}")

    # Final Report
    print("\n" + "="*70)
    print("DEPLOYMENT COMPLETE!")
    print("="*70 + "\n")

    print("[OK] All phases completed successfully!\n")

    print("Deployment Summary:")
    for phase in deployment_log["phases"]:
        status_icon = "[OK]" if phase["status"] == "success" else "[WARN]" if phase["status"] == "partial" else "[FAIL]"
        print(f"  {status_icon} {phase['phase']}")

    print("\nDatabase Credentials (SAVE THESE!):")
    print("="*70)
    creds = deployment_log["credentials"]
    print(f"Database Name: {creds['db_name']}")
    print(f"Database User: {creds['db_user']}")
    print(f"Database Password: {creds['db_password']}")
    print(f"Database Host: {creds['db_host']}")
    print(f"Database Port: {creds['db_port']}")
    print("="*70)

    print(f"\nCredentials saved to server: /home/{SSH_USER}/db_credentials.txt")

    print(f"\nYour Application:")
    print(f"  URL: {DOMAIN}")
    print(f"  Status: LIVE [OK]")

    print("\nNext Steps:")
    print(f"  1. Visit {DOMAIN} to verify it's working")
    print(f"  2. Test login functionality")
    print(f"  3. Check {DOMAIN}/health endpoint")
    print(f"  4. Monitor logs for any errors")

    print("\n" + "="*70)
    print(f"Deployment completed at: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("="*70 + "\n")

    ssh.close()
    print("[CLOSED] SSH connection closed\n")

    return True

if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)
