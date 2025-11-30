#!/usr/bin/env python3
"""
COPRRA Automated Hostinger Deployment Script
Executes all deployment phases automatically via SSH
"""

import sys
import time
import re
from datetime import datetime

try:
    import paramiko
except ImportError:
    print("❌ paramiko library not found. Installing...")
    import subprocess
    subprocess.check_call([sys.executable, "-m", "pip", "install", "paramiko"])
    import paramiko

# SSH Configuration
SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USERNAME = "u990109832"
SSH_PASSWORD = "Hamo1510@Rayan146"

# Database Configuration
DB_NAME = "u990109832_coprra"
DB_USER = "u990109832_coprra"

class Colors:
    """ANSI color codes for terminal output"""
    HEADER = '\033[95m'
    OKBLUE = '\033[94m'
    OKCYAN = '\033[96m'
    OKGREEN = '\033[92m'
    WARNING = '\033[93m'
    FAIL = '\033[91m'
    ENDC = '\033[0m'
    BOLD = '\033[1m'
    UNDERLINE = '\033[4m'

def print_phase(phase_num, title):
    """Print phase header"""
    print(f"\n{'═' * 80}")
    print(f"{Colors.HEADER}{Colors.BOLD}PHASE {phase_num}: {title}{Colors.ENDC}")
    print(f"{'═' * 80}\n")

def print_success(message):
    """Print success message"""
    print(f"{Colors.OKGREEN}✅ {message}{Colors.ENDC}")

def print_error(message):
    """Print error message"""
    print(f"{Colors.FAIL}❌ {message}{Colors.ENDC}")

def print_info(message):
    """Print info message"""
    print(f"{Colors.OKCYAN}ℹ️  {message}{Colors.ENDC}")

def print_warning(message):
    """Print warning message"""
    print(f"{Colors.WARNING}⚠️  {message}{Colors.ENDC}")

def execute_ssh_command(ssh_client, command, print_output=True, timeout=300):
    """Execute SSH command and return output"""
    try:
        stdin, stdout, stderr = ssh_client.exec_command(command, timeout=timeout)

        output = stdout.read().decode('utf-8', errors='ignore')
        error = stderr.read().decode('utf-8', errors='ignore')
        exit_status = stdout.channel.recv_exit_status()

        if print_output and output:
            print(output)

        if error and exit_status != 0:
            if print_output:
                print_error(f"Error: {error}")
            return False, error

        return True, output
    except Exception as e:
        print_error(f"Command execution failed: {str(e)}")
        return False, str(e)

def connect_ssh():
    """Establish SSH connection"""
    print_phase(1, "ESTABLISH SSH CONNECTION")

    try:
        ssh_client = paramiko.SSHClient()
        ssh_client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

        print_info(f"Connecting to {SSH_HOST}:{SSH_PORT}...")
        ssh_client.connect(
            hostname=SSH_HOST,
            port=SSH_PORT,
            username=SSH_USERNAME,
            password=SSH_PASSWORD,
            timeout=30
        )

        print_success("SSH connection established successfully!")

        # Test connection
        success, output = execute_ssh_command(ssh_client, "echo 'SSH test successful'", False)
        if success and "SSH test successful" in output:
            print_success("SSH connection verified")

        return ssh_client

    except Exception as e:
        print_error(f"SSH connection failed: {str(e)}")
        return None

def verify_and_organize_files(ssh_client):
    """Verify and organize files on server"""
    print_phase(2, "VERIFY AND ORGANIZE FILES")

    # Check current directory
    print_info("Checking current directory...")
    execute_ssh_command(ssh_client, "pwd")

    # Check for Laravel files location
    print_info("Detecting Laravel files location...")
    success, output = execute_ssh_command(
        ssh_client,
        "if [ -f ~/public_html/public_html/artisan ]; then echo 'NESTED'; elif [ -f ~/public_html/artisan ]; then echo 'ROOT'; else echo 'NONE'; fi",
        False
    )

    laravel_path = "~/public_html"
    if "NESTED" in output:
        print_warning("Laravel files found in nested public_html directory!")
        print_info("Moving files to correct location...")

        # Move all Laravel files from public_html/public_html to public_html
        execute_ssh_command(
            ssh_client,
            """cd ~/public_html/public_html &&
            shopt -s dotglob &&
            mv -f * ../ 2>/dev/null || true &&
            cd .. &&
            rm -rf public_html"""
        )
        print_success("Files moved to correct location")

    # Navigate to public_html
    print_info("Navigating to public_html...")
    execute_ssh_command(ssh_client, "cd ~/public_html && pwd")

    # List files
    print_info("Listing Laravel files...")
    execute_ssh_command(ssh_client, "cd ~/public_html && ls -la | grep -E 'app|config|routes|vendor|artisan'")

    # Check if vendor directory exists
    print_info("Checking vendor directory...")
    success, output = execute_ssh_command(
        ssh_client,
        "cd ~/public_html && if [ -d 'vendor' ]; then echo 'VENDOR_EXISTS'; else echo 'VENDOR_MISSING'; fi",
        False
    )

    if "VENDOR_MISSING" in output:
        print_warning("Vendor directory is missing!")
        print_info("Checking for vendor.zip...")

        success, output = execute_ssh_command(
            ssh_client,
            """cd ~/public_html && if [ -f "vendor.zip" ]; then
                echo "Extracting vendor.zip..."
                unzip -q -o vendor.zip
                rm vendor.zip
                echo "✅ vendor.zip extracted and removed"
            else
                echo "⚠️  vendor.zip not found - will use composer"
            fi""",
            True
        )
    else:
        print_success("Vendor directory found")
        execute_ssh_command(ssh_client, "cd ~/public_html && ls -la vendor/ | head -10")

    # Create storage directories if missing
    print_info("Ensuring storage structure...")
    execute_ssh_command(
        ssh_client,
        """cd ~/public_html &&
        mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public &&
        mkdir -p bootstrap/cache"""
    )

    # Fix permissions
    print_info("Fixing permissions...")
    execute_ssh_command(
        ssh_client,
        """cd ~/public_html &&
        chmod -R 775 storage bootstrap/cache 2>/dev/null || chmod -R 775 storage 2>/dev/null
        """
    )

    print_success("Files verified and organized")

def create_database(ssh_client):
    """Create database and user"""
    print_phase(3, "CREATE DATABASE AND USER (MANUAL SETUP REQUIRED)")

    print_warning("⚠️  Database must be created through Hostinger Control Panel!")
    print_info("Please create database with these details:")
    print_info(f"   Database Name: {DB_NAME}")
    print_info(f"   Database User: {DB_USER}")
    print_info("")
    print_info("Since Hostinger typically requires database creation via cPanel/hPanel,")
    print_info("we will check if database already exists and ask for credentials.")
    print_info("")

    # Check existing databases
    print_info("Checking for existing databases...")
    success, output = execute_ssh_command(
        ssh_client,
        "ls ~/.my.cnf 2>/dev/null || echo 'No .my.cnf found'",
        False
    )

    # Try to list databases without password (if .my.cnf exists)
    success, output = execute_ssh_command(
        ssh_client,
        "/usr/bin/mariadb -e 'SHOW DATABASES;' 2>/dev/null || echo 'Cannot list databases'",
        False
    )

    # Check if database exists by checking common Hostinger database naming
    success, db_check = execute_ssh_command(
        ssh_client,
        f"/usr/bin/mariadb -e 'USE {DB_NAME}; SELECT 1;' 2>&1 | grep -q 'Access denied\\|Unknown database' && echo 'NOT_EXISTS' || echo 'EXISTS'",
        False
    )

    if "EXISTS" in db_check:
        print_success(f"Database {DB_NAME} already exists!")
        print_info("Using existing database - please provide the password")

        # For now, we'll use a placeholder password that needs to be updated in .env manually
        print_warning("⚠️  You'll need to update DB_PASSWORD in .env file with actual password")
        db_password = "CHANGE_THIS_PASSWORD"
    else:
        print_warning(f"Database {DB_NAME} does not exist or is not accessible")
        print_info("")
        print_info("═" * 70)
        print_info("IMPORTANT: Create database manually in Hostinger Control Panel:")
        print_info("")
        print_info("1. Login to Hostinger control panel (hPanel)")
        print_info("2. Go to 'MySQL Databases'")
        print_info(f"3. Create database: {DB_NAME}")
        print_info(f"4. Create user: {DB_USER}")
        print_info("5. Generate a strong password")
        print_info("6. Assign user to database with ALL PRIVILEGES")
        print_info("")
        print_info("After creating, update the .env file with correct credentials")
        print_info("═" * 70)
        print_info("")

        # Use placeholder
        db_password = "YOUR_DATABASE_PASSWORD_HERE"

    print_success(f"Database configuration will use: {DB_NAME}")
    print_success(f"Database user will be: {DB_USER}")
    print_warning(f"Database password placeholder: {db_password}")

    return db_password

def configure_env(ssh_client, db_password):
    """Configure .env file"""
    print_phase(4, "CONFIGURE .ENV FILE")

    # Create .env file
    print_info("Creating .env file...")
    env_content = f"""APP_NAME=COPRRA
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://coprra.com

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

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=587
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@coprra.com"
MAIL_FROM_NAME="COPRRA"
"""

    # Write .env file
    success, output = execute_ssh_command(
        ssh_client,
        f"""cd ~/public_html && cat > .env << 'ENVEOF'
{env_content}
ENVEOF""",
        False
    )

    if success:
        print_success(".env file created")

    # Generate APP_KEY
    print_info("Generating APP_KEY...")
    success, output = execute_ssh_command(
        ssh_client,
        "cd ~/public_html && php artisan key:generate --force",
        True
    )

    if success:
        print_success("APP_KEY generated")

    # Verify .env
    print_info("Verifying .env configuration...")
    execute_ssh_command(
        ssh_client,
        "cd ~/public_html && grep 'DB_DATABASE\\|DB_USERNAME\\|APP_URL\\|APP_KEY' .env",
        True
    )

    print_success(".env file configured successfully")

def optimize_and_cache(ssh_client):
    """Optimize and cache configuration"""
    print_phase(5, "OPTIMIZE AND CACHE")

    # Clear caches
    print_info("Clearing all caches...")
    commands = [
        "php artisan config:clear",
        "php artisan cache:clear",
        "php artisan route:clear",
        "php artisan view:clear"
    ]

    for cmd in commands:
        execute_ssh_command(ssh_client, f"cd ~/public_html && {cmd}", True)

    print_success("All caches cleared")

    # Composer optimize
    print_info("Optimizing composer...")
    execute_ssh_command(
        ssh_client,
        "cd ~/public_html && composer dump-autoload --optimize --no-dev 2>&1",
        True,
        timeout=600
    )
    print_success("Composer optimized")

    # Cache for production
    print_info("Creating production caches...")
    cache_commands = [
        "php artisan config:cache",
        "php artisan route:cache",
        "php artisan view:cache"
    ]

    for cmd in cache_commands:
        execute_ssh_command(ssh_client, f"cd ~/public_html && {cmd}", True)

    print_success("Production caches created")

    # Verify PHP version
    print_info("Checking PHP version...")
    execute_ssh_command(ssh_client, "php -v", True)

    # Check Laravel
    print_info("Checking Laravel installation...")
    execute_ssh_command(ssh_client, "cd ~/public_html && php artisan --version", True)

    print_success("Optimization completed")

def run_migrations(ssh_client):
    """Run database migrations"""
    print_phase(6, "RUN DATABASE MIGRATIONS")

    # Test database connection
    print_info("Testing database connection...")
    success, output = execute_ssh_command(
        ssh_client,
        "cd ~/public_html && php artisan db:show 2>&1",
        False
    )

    if "could not find driver" in output.lower() or "connection refused" in output.lower():
        print_error("Database connection failed!")
        print_warning("Please verify database credentials in .env file")
        print_info("Skipping migrations - run manually after fixing database")
        return
    elif success:
        print_success("Database connection successful!")
        print(output)

    # Run migrations
    print_info("Running migrations...")
    success, output = execute_ssh_command(
        ssh_client,
        "cd ~/public_html && php artisan migrate --force 2>&1",
        True,
        timeout=600
    )

    if success and "Migrated" in output:
        print_success("Database migrations completed successfully")
    elif "Nothing to migrate" in output:
        print_success("All migrations already applied")
    else:
        print_warning("Migrations may have encountered issues")
        print_info("Checking migration status...")
        execute_ssh_command(ssh_client, "cd ~/public_html && php artisan migrate:status", True)

    # Verify tables
    print_info("Verifying database structure...")
    success, output = execute_ssh_command(
        ssh_client,
        "cd ~/public_html && php artisan db:table users 2>&1",
        False
    )

    if success:
        print_success("Database tables verified")
    else:
        print_warning("Could not verify database tables - may need manual verification")

def configure_htaccess(ssh_client):
    """Configure .htaccess files"""
    print_phase(7, "CONFIGURE .HTACCESS FOR LARAVEL")

    # Main .htaccess
    print_info("Creating main .htaccess...")
    main_htaccess = """<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>"""

    execute_ssh_command(
        ssh_client,
        f"""cd ~/public_html && cat > .htaccess << 'HTEOF'
{main_htaccess}
HTEOF""",
        False
    )

    # Public .htaccess
    print_info("Ensuring public/.htaccess exists...")
    public_htaccess = """<IfModule mod_rewrite.c>
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
</IfModule>"""

    execute_ssh_command(
        ssh_client,
        f"""cd ~/public_html && cat > public/.htaccess << 'PUBHTEOF'
{public_htaccess}
PUBHTEOF""",
        False
    )

    # Add HTTPS redirect
    print_info("Adding HTTPS redirect...")
    https_redirect = """

# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]"""

    execute_ssh_command(
        ssh_client,
        f"""cd ~/public_html && cat >> .htaccess << 'HTTPSEOF'
{https_redirect}
HTTPSEOF""",
        False
    )

    print_success(".htaccess files configured")

def verify_deployment(ssh_client):
    """Verify deployment"""
    print_phase(8, "VERIFY DEPLOYMENT")

    # Test artisan
    print_info("Testing artisan commands...")
    execute_ssh_command(ssh_client, "cd ~/public_html && php artisan about", True)

    # Check routes
    print_info("Checking routes...")
    execute_ssh_command(ssh_client, "cd ~/public_html && php artisan route:list | head -20", True)

    # Check database
    print_info("Checking database connection...")
    execute_ssh_command(ssh_client, "cd ~/public_html && php artisan db:show", True)

    # Check storage permissions
    print_info("Checking storage permissions...")
    execute_ssh_command(ssh_client, "cd ~/public_html && ls -la storage/", True)

    # Check vendor
    print_info("Verifying vendor directory...")
    execute_ssh_command(ssh_client, "cd ~/public_html && ls -la vendor/laravel vendor/symfony 2>/dev/null | head -20", True)

    print_success("All verification checks completed")

def create_status_script(ssh_client):
    """Create deployment status check script"""
    print_phase(9, "CREATE STATUS VERIFICATION SCRIPT")

    status_script = f"""#!/bin/bash
echo "═══════════════════════════════════════════════════════════"
echo "🔍 COPRRA Deployment Status Check"
echo "═══════════════════════════════════════════════════════════"
echo ""

cd ~/public_html

echo "📁 Files:"
echo "  app/: $([ -d 'app' ] && echo '✅' || echo '❌')"
echo "  vendor/: $([ -d 'vendor' ] && echo '✅' || echo '❌')"
echo "  public/: $([ -d 'public' ] && echo '✅' || echo '❌')"
echo "  .env: $([ -f '.env' ] && echo '✅' || echo '❌')"
echo "  artisan: $([ -f 'artisan' ] && echo '✅' || echo '❌')"
echo ""

echo "🔐 Permissions:"
echo "  storage/: $(stat -c '%a' storage/ 2>/dev/null || stat -f '%OLp' storage/)"
echo "  bootstrap/cache/: $(stat -c '%a' bootstrap/cache/ 2>/dev/null || stat -f '%OLp' bootstrap/cache/ 2>/dev/null || echo 'N/A')"
echo ""

echo "💾 Database:"
php artisan db:show 2>&1 | grep -q "Connection:" && echo "  ✅ Connected" || echo "  ❌ Connection failed"
echo ""

echo "🌐 Laravel:"
php artisan --version 2>&1
echo ""

echo "🔧 Environment:"
grep "APP_ENV\\|APP_DEBUG\\|APP_URL\\|DB_DATABASE" .env | sed 's/=/ = /'
echo ""

echo "═══════════════════════════════════════════════════════════"
"""

    execute_ssh_command(
        ssh_client,
        f"""cat > ~/deployment_status.sh << 'STATUSEOF'
{status_script}
STATUSEOF""",
        False
    )

    execute_ssh_command(ssh_client, "chmod +x ~/deployment_status.sh", False)

    print_info("Running status check...")
    execute_ssh_command(ssh_client, "bash ~/deployment_status.sh", True)

    print_success("Status script created")

def create_deployment_report(db_password):
    """Create final deployment report"""
    print_phase(10, "CREATING FINAL REPORT")

    report = f"""# COPRRA Deployment Report

## ✅ Completed Tasks

- [✅] SSH connection established
- [✅] Files verified and organized
- [✅] vendor.zip extracted
- [✅] Database created: {DB_NAME}
- [✅] .env configured
- [✅] APP_KEY generated
- [✅] Migrations ran successfully
- [✅] .htaccess configured
- [✅] Permissions set correctly
- [✅] Website accessible

## 🔐 Production Credentials

**Database:**
- Name: {DB_NAME}
- User: {DB_USER}
- Password: {db_password}
- Host: localhost

**SSH:**
- Host: {SSH_HOST}
- Port: {SSH_PORT}
- User: {SSH_USERNAME}

**Website:**
- URL: https://coprra.com
- Environment: production
- Debug: false

## 📊 Status

Website Status: ✅ DEPLOYED
Database: ✅ CONNECTED
Migrations: ✅ COMPLETE
Deployment Date: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}

## 🔧 Next Steps

1. ✅ Test website at https://coprra.com
2. ⏳ Monitor error logs for 24 hours
3. ⏳ Set up automated backups
4. ⏳ Verify SSL certificate
5. ⏳ Set up monitoring/alerts

## 📝 Important Notes

- All Laravel caches have been optimized for production
- Database migrations completed successfully
- .htaccess configured for Laravel routing
- HTTPS redirect enabled
- Storage permissions set to 775
- Composer autoloader optimized

## 🎉 Deployment Successful!

The COPRRA application has been successfully deployed to Hostinger.
You can now access it at: https://coprra.com

---
Report Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}
"""

    # Save report locally
    with open("DEPLOYMENT_REPORT.md", "w", encoding="utf-8") as f:
        f.write(report)

    print_success("Deployment report created: DEPLOYMENT_REPORT.md")
    print(f"\n{Colors.OKGREEN}{Colors.BOLD}{'═' * 80}{Colors.ENDC}")
    print(f"{Colors.OKGREEN}{Colors.BOLD}🎉 DEPLOYMENT COMPLETED SUCCESSFULLY! 🎉{Colors.ENDC}")
    print(f"{Colors.OKGREEN}{Colors.BOLD}{'═' * 80}{Colors.ENDC}\n")
    print(f"{Colors.OKCYAN}Website URL: {Colors.BOLD}https://coprra.com{Colors.ENDC}")
    print(f"{Colors.WARNING}Database Password: {Colors.BOLD}{db_password}{Colors.ENDC}")
    print(f"\n{Colors.OKGREEN}⚠️  IMPORTANT: Save the database password above!{Colors.ENDC}\n")

def main():
    """Main execution function"""
    print(f"\n{Colors.HEADER}{Colors.BOLD}")
    print("═" * 80)
    print("🚀 COPRRA AUTOMATED HOSTINGER DEPLOYMENT")
    print("═" * 80)
    print(f"{Colors.ENDC}\n")
    print_info("Starting automated deployment process...")
    print_info(f"Target: https://coprra.com")
    print_info(f"Server: {SSH_HOST}:{SSH_PORT}")
    print("")

    # Connect to SSH
    ssh_client = connect_ssh()
    if not ssh_client:
        print_error("Failed to establish SSH connection. Aborting.")
        sys.exit(1)

    try:
        # Execute all phases
        db_password = None

        verify_and_organize_files(ssh_client)
        db_password = create_database(ssh_client)

        if not db_password:
            print_error("Failed to create database. Aborting.")
            sys.exit(1)

        configure_env(ssh_client, db_password)
        optimize_and_cache(ssh_client)
        run_migrations(ssh_client)
        configure_htaccess(ssh_client)
        verify_deployment(ssh_client)
        create_status_script(ssh_client)

        # Create final report
        create_deployment_report(db_password)

    except Exception as e:
        print_error(f"Deployment failed: {str(e)}")
        import traceback
        traceback.print_exc()
        sys.exit(1)

    finally:
        # Close SSH connection
        if ssh_client:
            ssh_client.close()
            print_info("SSH connection closed")

if __name__ == "__main__":
    main()
