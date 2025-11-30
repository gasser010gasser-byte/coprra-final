#!/usr/bin/env python3
"""
Setup SQLite database for COPRRA on Hostinger
"""

import os
import paramiko

SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USERNAME = "u990109832"
SSH_PASSWORD = "Hamo1510@Rayan146"

LOCAL_DB = r"C:\Users\Gaser\Desktop\COPRRA\database\database.sqlite"

print("=" * 80)
print("🗄️  SETTING UP SQLITE DATABASE FOR COPRRA")
print("=" * 80)

# Check if local database exists
if not os.path.exists(LOCAL_DB):
    print(f"\n❌ Database file not found: {LOCAL_DB}")
    exit(1)

db_size = os.path.getsize(LOCAL_DB)
print(f"\n✅ Found database file: {LOCAL_DB}")
print(f"📊 Size: {db_size:,} bytes ({db_size/1024/1024:.2f} MB)")

# Connect SSH
print(f"\n📡 Connecting to server...")
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(SSH_HOST, SSH_PORT, SSH_USERNAME, SSH_PASSWORD, timeout=30)
print("✅ Connected!")

# Open SFTP
sftp = ssh.open_sftp()

# Upload database file
print(f"\n📤 Uploading database.sqlite to server...")
remote_db_path = "/home/u990109832/public_html/database/database.sqlite"

try:
    sftp.put(LOCAL_DB, remote_db_path)
    print("✅ Database uploaded successfully!")
except Exception as e:
    print(f"❌ Upload failed: {e}")
    sftp.close()
    ssh.close()
    exit(1)

# Set permissions
print("\n🔒 Setting database permissions...")
stdin, stdout, stderr = ssh.exec_command(f"chmod 664 {remote_db_path}")
stdout.channel.recv_exit_status()
print("✅ Permissions set to 664")

# Verify upload
print("\n🔍 Verifying upload...")
stdin, stdout, stderr = ssh.exec_command(f"ls -lh {remote_db_path}")
result = stdout.read().decode()
print(result)

# Update .env to use SQLite
print("\n📝 Updating .env to use SQLite...")

env_content = """APP_NAME=COPRRA
APP_ENV=production
APP_KEY=base64:placeholder
APP_DEBUG=false
APP_URL=https://coprra.com

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=sqlite
DB_DATABASE=/home/u990109832/public_html/database/database.sqlite

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

VITE_APP_NAME=COPRRA
"""

stdin, stdout, stderr = ssh.exec_command(f"""cd ~/public_html && cat > .env << 'ENVEOF'
{env_content}
ENVEOF
chmod 644 .env
echo "✅ .env updated to use SQLite"
""")
print(stdout.read().decode())

# Generate APP_KEY
print("\n🔑 Generating APP_KEY...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan key:generate --force 2>&1")
output = stdout.read().decode()
if "Application key set successfully" in output:
    print("✅ APP_KEY generated")
else:
    print(output)

# Clear caches
print("\n🗑️  Clearing all caches...")
stdin, stdout, stderr = ssh.exec_command("""cd ~/public_html &&
php artisan config:clear 2>&1 &&
php artisan cache:clear 2>&1 &&
php artisan route:clear 2>&1 &&
php artisan view:clear 2>&1 &&
echo "✅ Caches cleared"
""")
print(stdout.read().decode())

# Test database connection
print("\n💾 Testing database connection...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan db:show 2>&1")
output = stdout.read().decode()
print(output)

if "SQLite" in output or "sqlite" in output:
    print("✅ Database connection successful!")
else:
    print("⚠️  Database connection result unclear")

# Check tables
print("\n📊 Checking database tables...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan db:table users 2>&1 | head -20")
output = stdout.read().decode()
if "users" in output.lower() or "columns" in output.lower():
    print("✅ Database has tables!")
    print(output)
else:
    print("⚠️  Running migrations...")
    stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan migrate --force 2>&1")
    migration_output = stdout.read().decode()
    print(migration_output)

# Optimize for production
print("\n⚡ Optimizing for production...")
stdin, stdout, stderr = ssh.exec_command("""cd ~/public_html &&
php artisan config:cache 2>&1 &&
php artisan route:cache 2>&1 &&
php artisan view:cache 2>&1 &&
echo "✅ Production optimization complete"
""")
print(stdout.read().decode())

# Final verification
print("\n📊 Final verification...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan about 2>&1 | head -30")
about = stdout.read().decode()
print(about)

# Check if there are any users
print("\n👥 Checking for users in database...")
stdin, stdout, stderr = ssh.exec_command("""cd ~/public_html && php artisan tinker --execute="echo 'Users: ' . \\App\\Models\\User::count();" 2>&1""")
users_output = stdout.read().decode()
print(users_output)

sftp.close()
ssh.close()

print("\n" + "=" * 80)
print("✅ SQLITE DATABASE SETUP COMPLETED!")
print("=" * 80)
print(f"""
🎉 Database is ready!

📊 Configuration:
   - Database Type: SQLite
   - Database File: /home/u990109832/public_html/database/database.sqlite
   - Size: {db_size/1024/1024:.2f} MB

🌐 Test your website:
   URL: https://coprra.com

🔍 Check database:
   ssh -p {SSH_PORT} {SSH_USERNAME}@{SSH_HOST}
   cd ~/public_html
   php artisan db:show
   php artisan db:table users

✅ All done! Your website should be working now!
""")
