#!/usr/bin/env python3
"""
Upload COPRRA Laravel files to Hostinger via SSH
"""

import os
import sys
import paramiko
from pathlib import Path

# SSH Configuration
SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USERNAME = "u990109832"
SSH_PASSWORD = "Hamo1510@Rayan146"

# Paths
LOCAL_PATH = r"C:\Users\Gaser\Desktop\COPRRA"
REMOTE_PATH = "/home/u990109832/public_html"

# Directories and files to upload (exclude heavy folders)
INCLUDE_DIRS = [
    "app",
    "bootstrap",
    "config",
    "database",
    "public",
    "resources",
    "routes",
    "storage/app",
    "storage/framework",
    "storage/logs",
]

INCLUDE_FILES = [
    "artisan",
    ".env.example",
    "composer.json",
    "composer.lock",
    "package.json",
    "vite.config.js",
]

# Exclude patterns
EXCLUDE_PATTERNS = [
    "node_modules",
    "vendor",
    ".git",
    ".idea",
    "tests",
    "*.md",
    "*.log",
    "*.txt",
    ".phpunit.result.cache",
]

def should_exclude(path):
    """Check if path should be excluded"""
    path_str = str(path).lower()
    for pattern in EXCLUDE_PATTERNS:
        if pattern.replace('*', '') in path_str:
            return True
    return False

def upload_file(sftp, local_file, remote_file):
    """Upload a single file"""
    try:
        # Create remote directory if needed
        remote_dir = os.path.dirname(remote_file)
        try:
            sftp.stat(remote_dir)
        except FileNotFoundError:
            # Create directory recursively
            dirs = []
            while remote_dir and remote_dir != '/':
                dirs.insert(0, remote_dir)
                remote_dir = os.path.dirname(remote_dir)

            for d in dirs:
                try:
                    sftp.stat(d)
                except FileNotFoundError:
                    try:
                        sftp.mkdir(d)
                    except:
                        pass

        # Upload file
        sftp.put(local_file, remote_file)
        return True
    except Exception as e:
        print(f"  ❌ Error uploading {local_file}: {e}")
        return False

def upload_directory(sftp, local_dir, remote_dir):
    """Upload directory recursively"""
    try:
        sftp.stat(remote_dir)
    except FileNotFoundError:
        try:
            sftp.mkdir(remote_dir)
        except:
            pass

    for item in os.listdir(local_dir):
        local_path = os.path.join(local_dir, item)
        remote_path = f"{remote_dir}/{item}"

        if should_exclude(local_path):
            continue

        if os.path.isfile(local_path):
            print(f"  📤 {local_path} -> {remote_path}")
            upload_file(sftp, local_path, remote_path)
        elif os.path.isdir(local_path):
            upload_directory(sftp, local_path, remote_path)

def main():
    print("=" * 80)
    print("🚀 UPLOADING LARAVEL FILES TO HOSTINGER")
    print("=" * 80)
    print(f"\nLocal: {LOCAL_PATH}")
    print(f"Remote: {REMOTE_PATH}")
    print(f"Server: {SSH_HOST}:{SSH_PORT}\n")

    if not os.path.exists(LOCAL_PATH):
        print(f"❌ Local path not found: {LOCAL_PATH}")
        return

    print("Connecting to SSH...")
    ssh_client = paramiko.SSHClient()
    ssh_client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

    try:
        ssh_client.connect(
            hostname=SSH_HOST,
            port=SSH_PORT,
            username=SSH_USERNAME,
            password=SSH_PASSWORD,
            timeout=30
        )
        print("✅ SSH Connected!\n")

        # Open SFTP session
        sftp = ssh_client.open_sftp()

        # Create backup of existing files
        print("📦 Creating backup...")
        stdin, stdout, stderr = ssh_client.exec_command(
            f"cd {REMOTE_PATH} && tar -czf ~/backup_$(date +%Y%m%d_%H%M%S).tar.gz * 2>/dev/null || true"
        )
        stdout.channel.recv_exit_status()
        print("✅ Backup created\n")

        # Upload directories
        print("📁 Uploading Laravel directories...")
        for dir_name in INCLUDE_DIRS:
            local_dir = os.path.join(LOCAL_PATH, dir_name)
            if os.path.exists(local_dir):
                print(f"\n📂 Uploading {dir_name}/...")
                remote_dir = f"{REMOTE_PATH}/{dir_name}"
                upload_directory(sftp, local_dir, remote_dir)
            else:
                print(f"⚠️  Skipping {dir_name}/ (not found)")

        # Upload individual files
        print(f"\n📄 Uploading root files...")
        for file_name in INCLUDE_FILES:
            local_file = os.path.join(LOCAL_PATH, file_name)
            if os.path.exists(local_file):
                remote_file = f"{REMOTE_PATH}/{file_name}"
                print(f"  📤 {file_name}")
                upload_file(sftp, local_file, remote_file)
            else:
                print(f"  ⚠️  Skipping {file_name} (not found)")

        # Fix permissions
        print("\n🔒 Fixing permissions...")
        commands = f"""
        cd {REMOTE_PATH}
        chmod -R 755 storage bootstrap/cache 2>/dev/null || true
        chmod 644 .env.example 2>/dev/null || true
        chmod +x artisan 2>/dev/null || true
        echo "✅ Permissions fixed"
        """
        stdin, stdout, stderr = ssh_client.exec_command(commands)
        print(stdout.read().decode())

        # Verify upload
        print("\n✅ Verifying uploaded files...")
        stdin, stdout, stderr = ssh_client.exec_command(
            f"cd {REMOTE_PATH} && ls -la | grep -E 'artisan|app|config|routes'"
        )
        print(stdout.read().decode())

        sftp.close()

        print("\n" + "=" * 80)
        print("✅ UPLOAD COMPLETED!")
        print("=" * 80)
        print("\n📋 Next steps:")
        print("1. Copy .env.example to .env")
        print("2. Run: composer install --no-dev")
        print("3. Run: php artisan key:generate")
        print("4. Run: php artisan migrate")
        print("\n")

    except Exception as e:
        print(f"\n❌ Error: {e}")
        import traceback
        traceback.print_exc()
    finally:
        ssh_client.close()

if __name__ == "__main__":
    main()
