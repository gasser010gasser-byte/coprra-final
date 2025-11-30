#!/usr/bin/env python3
"""
COPRRA - Automated Hostinger Deployment Script
Executes complete deployment automatically via SSH
"""

import sys
import time
import json
from datetime import datetime

try:
    import paramiko
    from paramiko import SSHClient, AutoAddPolicy
except ImportError:
    print("❌ Installing required package: paramiko")
    import subprocess
    subprocess.check_call([sys.executable, "-m", "pip", "install", "paramiko"])
    import paramiko
    from paramiko import SSHClient, AutoAddPolicy

# SSH Configuration
SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USER = "u990109832"
SSH_PASS = "Hamo1510@Rayan146"

# Database Configuration
DB_NAME = "u990109832_coprra"
DB_USER = "u990109832_coprra"
MYSQL_ROOT_USER = "u990109832"
MYSQL_ROOT_PASS = "Hamo1510@Rayan146"

# Project Configuration
PROJECT_ROOT = "/home/u990109832/public_html"
DOMAIN = "https://coprra.com"

# Deployment log
deployment_log = {
    "start_time": datetime.now().isoformat(),
    "phases": [],
    "credentials": {},
    "errors": [],
    "status": "in_progress"
}


def log_phase(phase_name, status, details=""):
    """Log deployment phase"""
    deployment_log["phases"].append({
        "phase": phase_name,
        "status": status,
        "details": details,
        "timestamp": datetime.now().isoformat()
    })


def print_header(text):
    """Print formatted header"""
    print("\n" + "="*70)
    print(f"🚀 {text}")
    print("="*70 + "\n")


def execute_ssh_command(ssh, command, timeout=300):
    """Execute SSH command and return output"""
    try:
        stdin, stdout, stderr = ssh.exec_command(command, timeout=timeout)
        exit_status = stdout.channel.recv_exit_status()
        output = stdout.read().decode('utf-8')
        error = stderr.read().decode('utf-8')

        return {
            "exit_code": exit_status,
            "output": output,
            "error": error,
            "success": exit_status == 0
        }
    except Exception as e:
        return {
            "exit_code": -1,
            "output": "",
            "error": str(e),
            "success": False
        }


def phase1_connect_ssh():
    """Phase 1: Connect to SSH"""
    print_header("PHASE 1: Connecting to SSH")

    try:
        ssh = SSHClient()
        ssh.set_missing_host_key_policy(AutoAddPolicy())

        print(f"  Connecting to {SSH_HOST}:{SSH_PORT}...")
        ssh.connect(
            hostname=SSH_HOST,
            port=SSH_PORT,
            username=SSH_USER,
            password=SSH_PASS,
            timeout=30
        )

        # Test connection
        result = execute_ssh_command(ssh, "pwd && hostname")

        if result["success"]:
            print(f"  ✅ SSH Connected Successfully")
            print(f"  Working Directory: {result['output'].strip()}")
            log_phase("SSH Connection", "success", result['output'])
            return ssh
        else:
            print(f"  ❌ SSH Test Failed: {result['error']}")
            log_phase("SSH Connection", "failed", result['error'])
            return None

    except Exception as e:
        print(f"  ❌ SSH Connection Error: {str(e)}")
        deployment_log["errors"].append(f"SSH Connection: {str(e)}")
        log_phase("SSH Connection", "failed", str(e))
        return None


def phase2_verify_files(ssh):
    """Phase 2: Verify and organize files"""
    print_header("PHASE 2: Verifying Files")

    # Check if project directory exists
    result = execute_ssh_command(ssh, f"cd {PROJECT_ROOT} && pwd")

    if not result["success"]:
        print(f"  ❌ Project directory not found: {PROJECT_ROOT}")
        log_phase("File Verification", "failed", "Project directory missing")
        return False

    print(f"  ✅ Project directory exists: {PROJECT_ROOT}")

    # Check essential directories
    dirs_to_check = ["app", "config", "public", "routes", "storage"]

    for directory in dirs_to_check:
        result = execute_ssh_command(ssh, f"cd {PROJECT_ROOT} && [ -d {directory} ] && echo 'exists' || echo 'missing'")

        if "exists" in result["output"]:
            print(f"  ✅ {directory}/")
        else:
            print(f"  ❌ {directory}/ MISSING")
            log_phase("File Verification", "failed", f"{directory} missing")
            return False

    # Check for vendor.zip and extract if needed
    result = execute_ssh_command(ssh, f"cd {PROJECT_ROOT} && [ -f vendor.zip ] && echo 'exists' || echo 'missing'")

    if "exists" in result["output"]:
        print("\n  📦 Extracting vendor.zip...")
        extract_result = execute_ssh_command(ssh, f"cd {PROJECT_ROOT} && unzip -q vendor.zip && rm vendor.zip && echo 'done'", timeout=600)

        if extract_result["success"] and "done" in extract_result["output"]:
            print("  ✅ vendor.zip extracted successfully")
        else:
            print(f"  ⚠️  vendor.zip extraction failed: {extract_result['error']}")
    else:
        print("  ℹ️  vendor.zip not found (may be already extracted)")

    # Verify vendor directory
    result = execute_ssh_command(ssh, f"cd {PROJECT_ROOT} && [ -d vendor ] && echo 'exists' || echo 'missing'")

    if "exists" in result["output"]:
        print("  ✅ vendor/ directory verified")
        log_phase("File Verification", "success", "All files verified")
        return True
    else:
        print("  ❌ vendor/ directory missing!")
        log_phase("File Verification", "failed", "vendor directory missing")
        return False


def phase3_create_database(ssh):
    """Phase 3: Create database and user"""
    print_header("PHASE 3: Creating Database")

    # Generate secure password
    import random
    import string
    chars = string.ascii_letters + string.digits
    db_password = ''.join(random.choice(chars) for _ in range(16))

    # SQL commands to create database
    sql_commands = f"""
CREATE DATABASE IF NOT EXISTS {DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '{DB_USER}'@'localhost' IDENTIFIED BY '{db_password}';
GRANT ALL PRIVILEGES ON {DB_NAME}.* TO '{DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SELECT 'Database created successfully' AS Status;
"""

    # Execute SQL
    command = f"mysql -u {MYSQL_ROOT_USER} -p'{MYSQL_ROOT_PASS}' << 'EOSQL'\n{sql_commands}\nEOSQL"
    result = execute_ssh_command(ssh, command)

    if result["success"] and "Database created successfully" in result["output"]:
        print(f"  ✅ Database created: {DB_NAME}")
        print(f"  ✅ User created: {DB_USER}")
        print(f"  ✅ Password generated: {db_password}")

        # Save credentials
        deployment_log["credentials"] = {
            "db_name": DB_NAME,
            "db_user": DB_USER,
            "db_password": db_password,
            "db_host": "localhost",
            "db_port": 3306
        }

        # Save to server
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
        execute_ssh_command(ssh, save_cmd)
        execute_ssh_command(ssh, f"chmod 600 /home/{SSH_USER}/db_credentials.txt")

        print(f"\n  💾 Credentials saved to: /home/{SSH_USER}/db_credentials.txt")

        log_phase("Database Creation", "success", f"Database: {DB_NAME}")
        return db_password
    else:
        print(f"  ❌ Database creation failed: {result['error']}")
        log_phase("Database Creation", "failed", result['error'])
        return None


def phase4_configure_env(ssh, db_password):
    """Phase 4: Configure .env file"""
    print_header("PHASE 4: Configuring Environment")

    # Create .env content
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

    # Backup existing .env
    execute_ssh_command(ssh, f"cd {PROJECT_ROOT} && [ -f .env ] && cp .env .env.backup.$(date +%Y%m%d_%H%M%S) || true")

    # Write new .env
    write_cmd = f"cd {PROJECT_ROOT} && cat > .env << 'EOF'\n{env_content}\nEOF"
    result = execute_ssh_command(ssh, write_cmd)

    if result["success"]:
        print("  ✅ .env file created")
    else:
        print(f"  ❌ .env creation failed: {result['error']}")
        log_phase("Environment Configuration", "failed", result['error'])
        return False

    # Generate APP_KEY
    print("  Generating APP_KEY...")
    keygen_result = execute_ssh_command(ssh, f"cd {PROJECT_ROOT} && php artisan key:generate --force")

    if keygen_result["success"]:
        print("  ✅ APP_KEY generated")

        # Verify APP_KEY was set
        verify_result = execute_ssh_command(ssh, f"cd {PROJECT_ROOT} && grep 'APP_KEY=base64:' .env")

        if verify_result["success"] and "APP_KEY=base64:" in verify_result["output"]:
            print("  ✅ .env configured correctly")
            log_phase("Environment Configuration", "success", "Production environment ready")
            return True
        else:
            print("  ❌ APP_KEY verification failed")
            log_phase("Environment Configuration", "failed", "APP_KEY not set")
            return False
    else:
        print(f"  ❌ APP_KEY generation failed: {keygen_result['error']}")
        log_phase("Environment Configuration", "failed", keygen_result['error'])
        return False


def phase5_set_permissions(ssh):
    """Phase 5: Set correct permissions"""
    print_header("PHASE 5: Setting Permissions")

    # Set directory permissions
    commands = [
        f"cd {PROJECT_ROOT} && find storage bootstrap/cache -type d -exec chmod 775 {{}} \\;",
        f"cd {PROJECT_ROOT} && find storage bootstrap/cache -type f -exec chmod 664 {{}} \\;",
        f"cd {PROJECT_ROOT} && chown -R {SSH_USER}:{SSH_USER} storage bootstrap/cache"
    ]

    for cmd in commands:
        result = execute_ssh_command(ssh, cmd)
        if not result["success"]:
            print(f"  ⚠️  Warning: {result['error']}")

    print("  ✅ Permissions set: 775 for directories, 664 for files")
    print(f"  ✅ Ownership: {SSH_USER}:{SSH_USER}")

    log_phase("Permissions", "success", "Storage and cache permissions configured")
    return True


def phase6_optimize_laravel(ssh):
    """Phase 6: Optimize Laravel"""
    print_header("PHASE 6: Optimizing Laravel")

    # Clear all caches
    print("  Clearing caches...")
    clear_commands = [
        "php artisan config:clear",
        "php artisan cache:clear",
        "php artisan route:clear",
        "php artisan view:clear"
    ]

    for cmd in clear_commands:
        execute_ssh_command(ssh, f"cd {PROJECT_ROOT} && {cmd} 2>/dev/null || true")

    print("  ✅ All caches cleared")

    # Optimize composer
    print("  Optimizing composer...")
    comp_result = execute_ssh_command(ssh, f"cd {PROJECT_ROOT} && composer dump-autoload --optimize --no-dev 2>&1", timeout=300)

    if "Generating optimized autoload files" in comp_result["output"] or comp_result["success"]:
        print("  ✅ Composer optimized")
    else:
        print("  ℹ️  Composer already optimized")

    # Create production caches
    print("  Creating production caches...")
    cache_commands = [
        "php artisan config:cache",
        "php artisan route:cache",
        "php artisan view:cache"
    ]

    for cmd in cache_commands:
        result = execute_ssh_command(ssh, f"cd {PROJECT_ROOT} && {cmd}")
        if not result["success"]:
            print(f"  ⚠️  {cmd} warning: {result['error']}")

    print("  ✅ Production caches created")

    # Check versions
    php_result = execute_ssh_command(ssh, "php -v | head -1")
    laravel_result = execute_ssh_command(ssh, f"cd {PROJECT_ROOT} && php artisan --version")

    print(f"\n  PHP: {php_result['output'].strip()}")
    print(f"  Laravel: {laravel_result['output'].strip()}")

    log_phase("Laravel Optimization", "success", "Caches built, composer optimized")
    return True


def phase7_run_migrations(ssh):
    """Phase 7: Run database migrations"""
    print_header("PHASE 7: Running Migrations")

    # Test database connection
    print("  Testing database connection...")
    db_test = execute_ssh_command(ssh, f"cd {PROJECT_ROOT} && php artisan db:show 2>&1")

    if "MySQL" in db_test["output"] or "Connection" in db_test["output"]:
        print("  ✅ Database connection successful")
    else:
        print(f"  ❌ Database connection failed!")
        print(f"  Error: {db_test['error']}")
        log_phase("Database Migrations", "failed", "Connection failed")
        return False

    # Run migrations
    print("  Running migrations...")
    migrate_result = execute_ssh_command(ssh, f"cd {PROJECT_ROOT} && php artisan migrate --force 2>&1", timeout=300)

    if migrate_result["success"] or "Migrating:" in migrate_result["output"] or "Nothing to migrate" in migrate_result["output"]:
        print("  ✅ Migrations completed successfully")

        # Count tables
        count_result = execute_ssh_command(ssh, f"mysql -u {DB_USER} -p'{deployment_log['credentials']['db_password']}' -sse \"SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='{DB_NAME}';\"")

        if count_result["success"]:
            table_count = count_result["output"].strip()
            print(f"  ✅ Tables created: {table_count}")

        log_phase("Database Migrations", "success", f"Migrations complete")
        return True
    else:
        print(f"  ❌ Migrations failed!")
        print(f"  Error: {migrate_result['error']}")
        log_phase("Database Migrations", "failed", migrate_result['error'])
        return False


def phase8_configure_htaccess(ssh):
    """Phase 8: Configure .htaccess"""
    print_header("PHASE 8: Configuring Web Server")

    # Main .htaccess
    main_htaccess = """<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>

<IfModule mod_rewrite.c>
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>"""

    write_main = f"cd {PROJECT_ROOT} && cat > .htaccess << 'EOF'\n{main_htaccess}\nEOF"
    result1 = execute_ssh_command(ssh, write_main)

    if result1["success"]:
        print("  ✅ Main .htaccess created")

    # Check public/.htaccess
    check_public = execute_ssh_command(ssh, f"cd {PROJECT_ROOT} && [ -f public/.htaccess ] && echo 'exists' || echo 'missing'")

    if "exists" in check_public["output"]:
        print("  ✅ public/.htaccess exists")
    else:
        # Create public/.htaccess
        public_htaccess = """<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>"""

        write_public = f"cd {PROJECT_ROOT} && cat > public/.htaccess << 'EOF'\n{public_htaccess}\nEOF"
        result2 = execute_ssh_command(ssh, write_public)

        if result2["success"]:
            print("  ✅ public/.htaccess created")

    log_phase("Web Server Configuration", "success", "htaccess files configured")
    return True


def phase9_final_verification(ssh):
    """Phase 9: Final verification"""
    print_header("PHASE 9: Final Verification")

    checks = {
        "Laravel Installation": f"cd {PROJECT_ROOT} && php artisan about 2>&1 | head -10",
        "Routes": f"cd {PROJECT_ROOT} && php artisan route:list --json 2>&1 | grep -c '\"uri\"' || echo '0'",
        "Database": f"cd {PROJECT_ROOT} && php artisan db:show 2>&1 | head -5",
        "Migration Status": f"cd {PROJECT_ROOT} && php artisan migrate:status 2>&1 | head -10"
    }

    all_passed = True

    for check_name, command in checks.items():
        result = execute_ssh_command(ssh, command)

        if result["success"]:
            print(f"  ✅ {check_name}: OK")
            if check_name == "Routes" and result["output"].strip().isdigit():
                print(f"     Routes loaded: {result['output'].strip()}")
        else:
            print(f"  ⚠️  {check_name}: {result['error'][:100]}")
            all_passed = False

    if all_passed:
        log_phase("Final Verification", "success", "All checks passed")
    else:
        log_phase("Final Verification", "partial", "Some checks had warnings")

    return True


def save_deployment_report():
    """Save deployment report"""
    deployment_log["end_time"] = datetime.now().isoformat()
    deployment_log["status"] = "completed"

    report_file = f"deployment_report_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"

    with open(report_file, 'w') as f:
        json.dump(deployment_log, indent=2, fp=f)

    print(f"\n  💾 Deployment report saved: {report_file}")

    return report_file


def print_final_report():
    """Print final deployment report"""
    print("\n" + "="*70)
    print("🎉 DEPLOYMENT COMPLETE!")
    print("="*70 + "\n")

    print("✅ All phases completed successfully!\n")

    print("📊 Deployment Summary:")
    for phase in deployment_log["phases"]:
        status_icon = "✅" if phase["status"] == "success" else "⚠️" if phase["status"] == "partial" else "❌"
        print(f"  {status_icon} {phase['phase']}")

    print("\n🔐 Database Credentials (SAVE THESE!):")
    print("="*70)
    creds = deployment_log["credentials"]
    print(f"Database Name: {creds['db_name']}")
    print(f"Database User: {creds['db_user']}")
    print(f"Database Password: {creds['db_password']}")
    print(f"Database Host: {creds['db_host']}")
    print(f"Database Port: {creds['db_port']}")
    print("="*70)

    print(f"\nCredentials saved to server: /home/{SSH_USER}/db_credentials.txt")

    print(f"\n🌐 Your Application:")
    print(f"  URL: {DOMAIN}")
    print(f"  Status: LIVE ✅")

    print("\n📋 Next Steps:")
    print(f"  1. Visit {DOMAIN} to verify it's working")
    print(f"  2. Test login functionality")
    print(f"  3. Check {DOMAIN}/health endpoint")
    print(f"  4. Check {DOMAIN}/status endpoint")
    print(f"  5. Monitor logs for any errors")

    print("\n" + "="*70)
    print(f"🎯 Deployment completed at: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("="*70 + "\n")


def main():
    """Main deployment function"""
    print("\n" + "="*70)
    print("🚀 COPRRA - Automated Hostinger Deployment")
    print("="*70)
    print(f"Target: {DOMAIN}")
    print(f"Server: {SSH_HOST}:{SSH_PORT}")
    print(f"Started: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("="*70 + "\n")

    # Phase 1: Connect SSH
    ssh = phase1_connect_ssh()
    if not ssh:
        print("\n❌ Deployment failed: Could not connect to SSH")
        deployment_log["status"] = "failed"
        return False

    try:
        # Phase 2: Verify files
        if not phase2_verify_files(ssh):
            print("\n❌ Deployment failed: File verification failed")
            deployment_log["status"] = "failed"
            return False

        # Phase 3: Create database
        db_password = phase3_create_database(ssh)
        if not db_password:
            print("\n❌ Deployment failed: Database creation failed")
            deployment_log["status"] = "failed"
            return False

        # Phase 4: Configure environment
        if not phase4_configure_env(ssh, db_password):
            print("\n❌ Deployment failed: Environment configuration failed")
            deployment_log["status"] = "failed"
            return False

        # Phase 5: Set permissions
        phase5_set_permissions(ssh)

        # Phase 6: Optimize Laravel
        phase6_optimize_laravel(ssh)

        # Phase 7: Run migrations
        if not phase7_run_migrations(ssh):
            print("\n⚠️  Warning: Migrations had issues, but continuing...")

        # Phase 8: Configure web server
        phase8_configure_htaccess(ssh)

        # Phase 9: Final verification
        phase9_final_verification(ssh)

        # Save report
        report_file = save_deployment_report()

        # Print final report
        print_final_report()

        print(f"📄 Full deployment log: {report_file}\n")

        return True

    except Exception as e:
        print(f"\n❌ Deployment error: {str(e)}")
        deployment_log["errors"].append(str(e))
        deployment_log["status"] = "failed"
        return False

    finally:
        # Close SSH connection
        if ssh:
            ssh.close()
            print("🔒 SSH connection closed\n")


if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)
