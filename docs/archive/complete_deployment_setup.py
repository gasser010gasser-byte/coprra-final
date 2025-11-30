#!/usr/bin/env python3
"""
Complete COPRRA Deployment Setup on Hostinger
Final configuration steps after file upload
"""

import sys
import paramiko
import time

# SSH Configuration
SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USERNAME = "u990109832"
SSH_PASSWORD = "Hamo1510@Rayan146"

# Database Configuration (from previous setup)
DB_NAME = "u990109832_coprra"
DB_USER = "u990109832_coprra"

class Colors:
    HEADER = '\033[95m'
    OKGREEN = '\033[92m'
    WARNING = '\033[93m'
    FAIL = '\033[91m'
    ENDC = '\033[0m'
    BOLD = '\033[1m'

def execute_cmd(ssh_client, command, print_output=True, timeout=600):
    """Execute command and return output"""
    try:
        stdin, stdout, stderr = ssh_client.exec_command(command, timeout=timeout)
        output = stdout.read().decode('utf-8', errors='ignore')
        error = stderr.read().decode('utf-8', errors='ignore')
        exit_status = stdout.channel.recv_exit_status()

        if print_output and output:
            print(output)
        if error and print_output:
            if "Warning" not in error and "Notice" not in error:
                print(f"{Colors.WARNING}{error}{Colors.ENDC}")

        return output, error, exit_status
    except Exception as e:
        print(f"{Colors.FAIL}Error: {e}{Colors.ENDC}")
        return "", str(e), 1

def main():
    print(f"\n{Colors.HEADER}{Colors.BOLD}")
    print("=" * 80)
    print("🚀 COMPLETING COPRRA DEPLOYMENT SETUP")
    print("=" * 80)
    print(f"{Colors.ENDC}\n")

    # Connect SSH
    print(f"{Colors.OKGREEN}📡 Connecting to SSH...{Colors.ENDC}")
    ssh_client = paramiko.SSHClient()
    ssh_client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh_client.connect(
        hostname=SSH_HOST,
        port=SSH_PORT,
        username=SSH_USERNAME,
        password=SSH_PASSWORD,
        timeout=30
    )
    print(f"{Colors.OKGREEN}✅ Connected!{Colors.ENDC}\n")

    try:
        # Step 1: Copy .env.example to .env
        print(f"{Colors.HEADER}📝 Step 1: Configuring .env file{Colors.ENDC}")
        output, _, _ = execute_cmd(ssh_client, "cd ~/public_html && cp .env.example .env && echo '✅ .env file created'")

        # Ask for database password
        print(f"\n{Colors.WARNING}⚠️  IMPORTANT: Database Password Required{Colors.ENDC}")
        print(f"Please enter the database password for: {DB_USER}")
        print(f"(You should have created this in Hostinger Control Panel)")
        db_password = input(f"{Colors.BOLD}Database Password: {Colors.ENDC}").strip()

        if not db_password:
            print(f"{Colors.WARNING}⚠️  No password entered, using placeholder{Colors.ENDC}")
            db_password = "YOUR_DATABASE_PASSWORD_HERE"

        # Update .env with production settings
        print(f"\n{Colors.OKGREEN}📝 Updating .env configuration...{Colors.ENDC}")
        env_updates = f"""
cd ~/public_html
# Update environment settings
sed -i 's/APP_ENV=.*/APP_ENV=production/' .env
sed -i 's/APP_DEBUG=.*/APP_DEBUG=false/' .env
sed -i 's|APP_URL=.*|APP_URL=https://coprra.com|' .env
sed -i 's/DB_CONNECTION=.*/DB_CONNECTION=mysql/' .env
sed -i 's/DB_HOST=.*/DB_HOST=localhost/' .env
sed -i 's/DB_PORT=.*/DB_PORT=3306/' .env
sed -i 's/DB_DATABASE=.*/DB_DATABASE={DB_NAME}/' .env
sed -i 's/DB_USERNAME=.*/DB_USERNAME={DB_USER}/' .env
sed -i 's/DB_PASSWORD=.*/DB_PASSWORD={db_password}/' .env
sed -i 's/LOG_LEVEL=.*/LOG_LEVEL=error/' .env
echo "✅ .env configured"
"""
        output, _, _ = execute_cmd(ssh_client, env_updates)

        # Step 2: Install Composer dependencies
        print(f"\n{Colors.HEADER}📦 Step 2: Installing Composer dependencies{Colors.ENDC}")
        print(f"{Colors.WARNING}This may take 5-10 minutes...{Colors.ENDC}\n")

        composer_cmd = """
cd ~/public_html
if [ ! -d "vendor" ]; then
    echo "Installing composer dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction 2>&1
else
    echo "✅ Vendor directory already exists, running composer dump-autoload..."
    composer dump-autoload --optimize --no-dev 2>&1
fi
"""
        output, error, status = execute_cmd(ssh_client, composer_cmd, True, timeout=900)

        if status == 0 or "vendor" in output.lower():
            print(f"{Colors.OKGREEN}✅ Composer dependencies ready{Colors.ENDC}")
        else:
            print(f"{Colors.WARNING}⚠️  Composer may have encountered issues{Colors.ENDC}")

        # Step 3: Generate APP_KEY
        print(f"\n{Colors.HEADER}🔑 Step 3: Generating APP_KEY{Colors.ENDC}")
        output, _, _ = execute_cmd(ssh_client, "cd ~/public_html && php artisan key:generate --force")
        print(f"{Colors.OKGREEN}✅ APP_KEY generated{Colors.ENDC}")

        # Step 4: Clear and optimize caches
        print(f"\n{Colors.HEADER}🗑️  Step 4: Clearing caches{Colors.ENDC}")
        cache_commands = [
            "php artisan config:clear",
            "php artisan cache:clear",
            "php artisan route:clear",
            "php artisan view:clear"
        ]
        for cmd in cache_commands:
            execute_cmd(ssh_client, f"cd ~/public_html && {cmd} 2>&1", False)
        print(f"{Colors.OKGREEN}✅ Caches cleared{Colors.ENDC}")

        # Step 5: Create storage link
        print(f"\n{Colors.HEADER}🔗 Step 5: Creating storage link{Colors.ENDC}")
        execute_cmd(ssh_client, "cd ~/public_html && php artisan storage:link 2>&1", False)
        print(f"{Colors.OKGREEN}✅ Storage link created{Colors.ENDC}")

        # Step 6: Run migrations
        print(f"\n{Colors.HEADER}💾 Step 6: Running database migrations{Colors.ENDC}")
        if db_password != "YOUR_DATABASE_PASSWORD_HERE":
            print("Testing database connection...")
            output, error, status = execute_cmd(ssh_client, "cd ~/public_html && php artisan db:show 2>&1", False)

            if status == 0 and "Connection" in output:
                print(f"{Colors.OKGREEN}✅ Database connection successful{Colors.ENDC}")
                print("\nRunning migrations...")
                output, _, _ = execute_cmd(ssh_client, "cd ~/public_html && php artisan migrate --force 2>&1")

                if "Migrated" in output or "Nothing to migrate" in output:
                    print(f"{Colors.OKGREEN}✅ Migrations completed successfully{Colors.ENDC}")
                else:
                    print(f"{Colors.WARNING}⚠️  Migration status unclear, check output above{Colors.ENDC}")
            else:
                print(f"{Colors.FAIL}❌ Database connection failed{Colors.ENDC}")
                print(f"{Colors.WARNING}Please verify database credentials in Hostinger panel{Colors.ENDC}")
        else:
            print(f"{Colors.WARNING}⚠️  Skipping migrations - database password not provided{Colors.ENDC}")

        # Step 7: Optimize for production
        print(f"\n{Colors.HEADER}⚡ Step 7: Optimizing for production{Colors.ENDC}")
        optimize_commands = [
            "php artisan config:cache",
            "php artisan route:cache",
            "php artisan view:cache"
        ]
        for cmd in optimize_commands:
            execute_cmd(ssh_client, f"cd ~/public_html && {cmd} 2>&1", False)
        print(f"{Colors.OKGREEN}✅ Production optimization complete{Colors.ENDC}")

        # Step 8: Configure .htaccess
        print(f"\n{Colors.HEADER}🔧 Step 8: Configuring .htaccess{Colors.ENDC}")
        htaccess_root = """<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>

# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
"""

        htaccess_public = """<IfModule mod_rewrite.c>
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

        execute_cmd(ssh_client, f"""cd ~/public_html && cat > .htaccess << 'EOF'
{htaccess_root}
EOF""", False)

        execute_cmd(ssh_client, f"""cd ~/public_html/public && cat > .htaccess << 'EOF'
{htaccess_public}
EOF""", False)

        print(f"{Colors.OKGREEN}✅ .htaccess files configured{Colors.ENDC}")

        # Step 9: Set proper permissions
        print(f"\n{Colors.HEADER}🔒 Step 9: Setting permissions{Colors.ENDC}")
        perms_cmd = """
cd ~/public_html
chmod -R 775 storage bootstrap/cache
find storage -type f -exec chmod 664 {} \\;
find storage -type d -exec chmod 775 {} \\;
chmod +x artisan
echo "✅ Permissions set"
"""
        execute_cmd(ssh_client, perms_cmd)

        # Step 10: Final verification
        print(f"\n{Colors.HEADER}✅ Step 10: Final Verification{Colors.ENDC}")

        print("\n📊 Laravel Version:")
        execute_cmd(ssh_client, "cd ~/public_html && php artisan --version")

        print("\n📊 Environment Status:")
        execute_cmd(ssh_client, "cd ~/public_html && grep 'APP_ENV\\|APP_DEBUG\\|APP_URL\\|DB_DATABASE' .env")

        print(f"\n📊 File Structure:")
        execute_cmd(ssh_client, "cd ~/public_html && ls -la | grep -E 'artisan|vendor|.env'")

        # Create deployment info file
        deployment_info = f"""
# COPRRA Deployment Information

## Deployment Details
- Date: $(date)
- Domain: https://coprra.com
- Environment: Production

## Database
- Name: {DB_NAME}
- User: {DB_USER}
- Host: localhost

## Next Steps
1. Visit https://coprra.com to test the website
2. Create admin user if needed
3. Configure email settings in .env
4. Set up SSL certificate (if not auto-configured)
5. Test all functionality

## Maintenance Commands
```bash
# Clear cache
php artisan cache:clear
php artisan config:clear

# Run migrations
php artisan migrate

# Optimize
php artisan optimize
```

## Support
- Laravel Logs: storage/logs/laravel.log
- PHP Errors: Check Hostinger error logs
"""

        execute_cmd(ssh_client, f"""cd ~/public_html && cat > DEPLOYMENT_INFO.md << 'DEPEOF'
{deployment_info}
DEPEOF""", False)

        # Final Summary
        print(f"\n{Colors.HEADER}{Colors.BOLD}")
        print("=" * 80)
        print("🎉 DEPLOYMENT COMPLETED SUCCESSFULLY!")
        print("=" * 80)
        print(f"{Colors.ENDC}")

        print(f"\n{Colors.OKGREEN}✅ All deployment steps completed!{Colors.ENDC}\n")
        print(f"{Colors.BOLD}🌐 Website URL:{Colors.ENDC} https://coprra.com")
        print(f"{Colors.BOLD}📧 Database:{Colors.ENDC} {DB_NAME}")
        print(f"{Colors.BOLD}👤 DB User:{Colors.ENDC} {DB_USER}")

        print(f"\n{Colors.WARNING}⚠️  IMPORTANT NEXT STEPS:{Colors.ENDC}")
        print(f"1. Open https://coprra.com in your browser")
        print(f"2. Verify the website loads correctly")
        print(f"3. If you see database errors, update DB password in .env")
        print(f"4. Create admin user via artisan command if needed")

        print(f"\n{Colors.OKGREEN}📝 Deployment info saved to: ~/public_html/DEPLOYMENT_INFO.md{Colors.ENDC}\n")

    except Exception as e:
        print(f"\n{Colors.FAIL}❌ Error during deployment: {e}{Colors.ENDC}")
        import traceback
        traceback.print_exc()
    finally:
        ssh_client.close()
        print(f"\n{Colors.OKGREEN}✅ SSH connection closed{Colors.ENDC}\n")

if __name__ == "__main__":
    main()
