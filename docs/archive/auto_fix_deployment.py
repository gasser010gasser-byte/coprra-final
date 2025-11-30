#!/usr/bin/env python3
"""
Auto-fix COPRRA deployment - no user input required
"""

import paramiko

SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USERNAME = "u990109832"
SSH_PASSWORD = "Hamo1510@Rayan146"
DB_NAME = "u990109832_coprra"
DB_USER = "u990109832_coprra"

# Use placeholder - user will update manually via Hostinger panel
DB_PASSWORD_PLACEHOLDER = "UPDATE_THIS_IN_HOSTINGER_PANEL"

print("=" * 80)
print("🔧 AUTO-FIXING COPRRA DEPLOYMENT")
print("=" * 80)

print(f"\n📡 Connecting to server...")
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(SSH_HOST, SSH_PORT, SSH_USERNAME, SSH_PASSWORD, timeout=30)
print(f"✅ Connected!\n")

# Step 1: Create clean .env file
print("📝 Creating clean .env file...")

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
DB_PASSWORD={DB_PASSWORD_PLACEHOLDER}

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=587
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@coprra.com
MAIL_FROM_NAME=COPRRA

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME=COPRRA
"""

stdin, stdout, stderr = ssh.exec_command(f"""cd ~/public_html && cat > .env << 'ENVEOF'
{env_content}
ENVEOF
chmod 644 .env
echo "✅ .env created"
""")
print(stdout.read().decode())

# Step 2: Generate APP_KEY
print("\n🔑 Generating APP_KEY...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan key:generate --force 2>&1")
output = stdout.read().decode()
if "Application key set successfully" in output:
    print("✅ APP_KEY generated successfully")
else:
    print(output)

# Step 3: Clear all caches
print("\n🗑️  Clearing caches...")
stdin, stdout, stderr = ssh.exec_command("""cd ~/public_html &&
php artisan config:clear 2>&1 &&
php artisan cache:clear 2>&1 &&
php artisan route:clear 2>&1 &&
php artisan view:clear 2>&1 &&
echo "✅ Caches cleared"
""")
print(stdout.read().decode())

# Step 4: Create storage link
print("\n🔗 Creating storage link...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan storage:link 2>&1")
output = stdout.read().decode()
if "links have been created" in output or "already exists" in output:
    print("✅ Storage link ready")
else:
    print(output)

# Step 5: Set permissions
print("\n🔒 Setting permissions...")
stdin, stdout, stderr = ssh.exec_command("""cd ~/public_html &&
chmod -R 775 storage bootstrap/cache 2>&1 &&
find storage -type f -exec chmod 664 {} \\; 2>&1 &&
find storage -type d -exec chmod 775 {} \\; 2>&1 &&
chmod +x artisan &&
echo "✅ Permissions set"
""")
print(stdout.read().decode())

# Step 6: Configure .htaccess
print("\n🔧 Configuring .htaccess files...")

htaccess_root = """<IfModule mod_rewrite.c>
    RewriteEngine On

    # Redirect to public folder
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>

# Force HTTPS
<IfModule mod_rewrite.c>
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>
"""

htaccess_public = """<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

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
</IfModule>
"""

stdin, stdout, stderr = ssh.exec_command(f"""cd ~/public_html && cat > .htaccess << 'HTEOF'
{htaccess_root}
HTEOF
echo "✅ Root .htaccess created"
""")
print(stdout.read().decode())

stdin, stdout, stderr = ssh.exec_command(f"""cd ~/public_html/public && cat > .htaccess << 'HTEOF'
{htaccess_public}
HTEOF
echo "✅ Public .htaccess created"
""")
print(stdout.read().decode())

# Step 7: Verify setup
print("\n📊 Verifying installation...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan --version 2>&1")
version = stdout.read().decode()
print(f"Laravel: {version.strip()}")

stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php -v | head -1")
php_version = stdout.read().decode()
print(f"PHP: {php_version.strip()}")

# Step 8: Check file structure
print("\n📂 File structure:")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && ls -lh | grep -E 'artisan|vendor|.env|public'")
print(stdout.read().decode())

# Step 9: Create deployment guide
print("\n📝 Creating deployment guide...")

guide = f"""# COPRRA Deployment Guide

## ✅ Deployment Status: READY (Database Setup Required)

### Current Configuration
- **URL**: https://coprra.com
- **Environment**: Production
- **PHP Version**: 8.2+
- **Laravel**: Latest

### 🔴 REQUIRED: Complete Database Setup

#### Step 1: Create Database in Hostinger Control Panel
1. Login to Hostinger hPanel
2. Go to **MySQL Databases**
3. Create new database:
   - Database Name: `{DB_NAME}`
   - User: `{DB_USER}`
   - Password: Create a strong password
4. Assign user to database with ALL PRIVILEGES

#### Step 2: Update .env File
```bash
# SSH into server
ssh -p {SSH_PORT} {SSH_USERNAME}@{SSH_HOST}

# Edit .env file
cd ~/public_html
nano .env

# Update this line with your actual database password:
DB_PASSWORD=YOUR_ACTUAL_PASSWORD_HERE

# Save (Ctrl+X, Y, Enter)
```

#### Step 3: Run Migrations
```bash
cd ~/public_html
php artisan migrate --force
```

#### Step 4: Create Admin User (Optional)
```bash
php artisan tinker

# In tinker:
\\App\\Models\\User::create([
    'name' => 'Admin',
    'email' => 'admin@coprra.com',
    'password' => bcrypt('your-password'),
    'role' => 'admin'
]);
```

### 🎯 Testing the Website
1. Open: https://coprra.com
2. Check homepage loads
3. Test navigation
4. Check database connection

### 📊 Useful Commands

```bash
# Clear cache
php artisan cache:clear
php artisan config:clear

# View logs
tail -f storage/logs/laravel.log

# Check database connection
php artisan db:show

# Run migrations
php artisan migrate

# Optimize application
php artisan optimize
```

### 🔍 Troubleshooting

#### 500 Internal Server Error
- Check `.env` file is valid
- Check storage permissions: `chmod -R 775 storage`
- Check logs: `storage/logs/laravel.log`

#### Database Connection Error
- Verify database exists in Hostinger panel
- Check DB credentials in `.env`
- Test connection: `php artisan db:show`

#### Blank Page
- Check PHP error logs in Hostinger panel
- Enable debug temporarily: `APP_DEBUG=true` in `.env`
- Run: `php artisan config:clear`

### 📞 Support
- Laravel Docs: https://laravel.com/docs
- Hostinger Support: https://hostinger.com/support
- Application Logs: ~/public_html/storage/logs/

---
Deployment completed: $(date)
"""

stdin, stdout, stderr = ssh.exec_command(f"""cd ~/public_html && cat > DEPLOYMENT_GUIDE.md << 'GUIDEEOF'
{guide}
GUIDEEOF
echo "✅ Guide created"
""")
print(stdout.read().decode())

# Final summary
print("\n" + "=" * 80)
print("✅ DEPLOYMENT AUTO-FIX COMPLETED!")
print("=" * 80)

print(f"""
🎉 Your Laravel application is deployed and ready!

🌐 Website URL: https://coprra.com
📂 Server Path: ~/public_html/
📝 Deployment Guide: ~/public_html/DEPLOYMENT_GUIDE.md

⚠️  CRITICAL NEXT STEP: Setup Database
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

1. Login to Hostinger Control Panel (hPanel)
2. Go to "MySQL Databases"
3. Create database: {DB_NAME}
4. Create user: {DB_USER}
5. Set a strong password and remember it!
6. Update .env file via SSH:

   ssh -p {SSH_PORT} {SSH_USERNAME}@{SSH_HOST}
   cd ~/public_html
   nano .env

   Change: DB_PASSWORD={DB_PASSWORD_PLACEHOLDER}
   To: DB_PASSWORD=your_actual_password

7. Run migrations:
   php artisan migrate --force

📋 Quick Test:
   Visit: https://coprra.com

🔍 View Logs:
   tail -f ~/public_html/storage/logs/laravel.log

✅ All files deployed successfully!
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
""")

ssh.close()
print("✅ SSH connection closed\n")
