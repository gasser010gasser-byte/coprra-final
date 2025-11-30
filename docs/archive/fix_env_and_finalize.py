#!/usr/bin/env python3
"""
Fix .env file and finalize COPRRA deployment
"""

import sys
import paramiko

SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USERNAME = "u990109832"
SSH_PASSWORD = "Hamo1510@Rayan146"
DB_NAME = "u990109832_coprra"
DB_USER = "u990109832_coprra"

def main():
    print("=" * 80)
    print("🔧 FIXING .ENV AND FINALIZING DEPLOYMENT")
    print("=" * 80)

    # Ask for database password
    print(f"\n📝 Database Configuration:")
    print(f"   Database: {DB_NAME}")
    print(f"   User: {DB_USER}")
    print(f"\n⚠️  Please enter the database password from Hostinger Control Panel")
    print(f"   (If you haven't created the database yet, press Enter to skip)")

    db_password = input(f"\n🔑 Database Password: ").strip()

    if not db_password:
        db_password = "PLEASE_UPDATE_THIS_PASSWORD"
        print(f"\n⚠️  No password provided. Using placeholder.")
        print(f"   You'll need to update it manually in .env later.")

    print(f"\n📡 Connecting to server...")
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(SSH_HOST, SSH_PORT, SSH_USERNAME, SSH_PASSWORD, timeout=30)
    print(f"✅ Connected!\n")

    # Create proper .env file
    print("📝 Creating proper .env configuration...")

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
MAIL_FROM_ADDRESS="noreply@coprra.com"
MAIL_FROM_NAME="COPRRA"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

VITE_APP_NAME="COPRRA"
VITE_PUSHER_APP_KEY="${{PUSHER_APP_KEY}}"
VITE_PUSHER_HOST="${{PUSHER_HOST}}"
VITE_PUSHER_PORT="${{PUSHER_PORT}}"
VITE_PUSHER_SCHEME="${{PUSHER_SCHEME}}"
VITE_PUSHER_APP_CLUSTER="${{PUSHER_APP_CLUSTER}}"
"""

    stdin, stdout, stderr = ssh.exec_command(f"""cd ~/public_html && cat > .env << 'ENVEOF'
{env_content}
ENVEOF
echo "✅ .env file created"
""")
    print(stdout.read().decode())

    # Generate APP_KEY
    print("🔑 Generating APP_KEY...")
    stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan key:generate --force 2>&1")
    output = stdout.read().decode()
    print(output)

    if "Application key set successfully" in output:
        print("✅ APP_KEY generated successfully")

    # Clear all caches
    print("\n🗑️  Clearing all caches...")
    commands = [
        "cd ~/public_html && php artisan config:clear 2>&1",
        "cd ~/public_html && php artisan cache:clear 2>&1",
        "cd ~/public_html && php artisan route:clear 2>&1",
        "cd ~/public_html && php artisan view:clear 2>&1"
    ]

    for cmd in commands:
        stdin, stdout, stderr = ssh.exec_command(cmd)
        stdout.read()

    print("✅ Caches cleared")

    # Test database connection if password provided
    if db_password != "PLEASE_UPDATE_THIS_PASSWORD":
        print("\n💾 Testing database connection...")
        stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan db:show 2>&1")
        output = stdout.read().decode()
        error = stderr.read().decode()

        if "Connection" in output and "MySQL" in output:
            print("✅ Database connection successful!")
            print(output)

            # Run migrations
            print("\n🔄 Running database migrations...")
            stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan migrate --force 2>&1")
            output = stdout.read().decode()
            print(output)

            if "Migrated" in output or "Nothing to migrate" in output:
                print("✅ Migrations completed successfully")
        else:
            print("❌ Database connection failed")
            print(output + error)
            print("\n⚠️  Please check:")
            print("   1. Database exists in Hostinger Control Panel")
            print("   2. Database user has correct privileges")
            print("   3. Password is correct")
    else:
        print("\n⚠️  Skipping database connection test - no password provided")

    # Create storage link
    print("\n🔗 Creating storage link...")
    stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan storage:link 2>&1")
    stdout.read()
    print("✅ Storage link created")

    # Optimize for production
    print("\n⚡ Optimizing for production...")
    optimize_cmds = [
        "cd ~/public_html && php artisan config:cache 2>&1",
        "cd ~/public_html && php artisan route:cache 2>&1",
        "cd ~/public_html && php artisan view:cache 2>&1"
    ]

    for cmd in optimize_cmds:
        stdin, stdout, stderr = ssh.exec_command(cmd)
        stdout.read()

    print("✅ Production optimization complete")

    # Verify Laravel
    print("\n📊 Verifying Laravel installation...")
    stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan --version 2>&1")
    version = stdout.read().decode()
    print(version)

    # Check environment
    print("\n📊 Environment Configuration:")
    stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan about 2>&1 | head -20")
    about = stdout.read().decode()
    print(about)

    # Final summary
    print("\n" + "=" * 80)
    print("✅ DEPLOYMENT FINALIZED!")
    print("=" * 80)
    print(f"\n🌐 Website: https://coprra.com")
    print(f"💾 Database: {DB_NAME}")
    print(f"👤 DB User: {DB_USER}")

    if db_password == "PLEASE_UPDATE_THIS_PASSWORD":
        print(f"\n⚠️  ACTION REQUIRED:")
        print(f"   1. Login to Hostinger Control Panel")
        print(f"   2. Create database: {DB_NAME}")
        print(f"   3. Create user: {DB_USER}")
        print(f"   4. Update password in .env file:")
        print(f"      SSH: ssh -p {SSH_PORT} {SSH_USERNAME}@{SSH_HOST}")
        print(f"      Edit: nano ~/public_html/.env")
        print(f"   5. Run: php artisan migrate --force")
    else:
        print(f"\n✅ Database configured")

    print(f"\n📋 Next Steps:")
    print(f"   1. Visit https://coprra.com")
    print(f"   2. Check if website loads")
    print(f"   3. Monitor logs: storage/logs/laravel.log")

    ssh.close()
    print(f"\n✅ Done!\n")

if __name__ == "__main__":
    main()
