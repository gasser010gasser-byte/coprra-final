#!/usr/bin/env python3
"""
Fix COPRRA file structure on Hostinger
Move files from public_html/public_html to public_html
"""

import sys
import paramiko

# SSH Configuration
SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USERNAME = "u990109832"
SSH_PASSWORD = "Hamo1510@Rayan146"

def execute_cmd(ssh_client, command):
    """Execute command and return output"""
    stdin, stdout, stderr = ssh_client.exec_command(command, timeout=300)
    output = stdout.read().decode('utf-8', errors='ignore')
    error = stderr.read().decode('utf-8', errors='ignore')
    return output, error

def main():
    print("Connecting to SSH...")
    ssh_client = paramiko.SSHClient()
    ssh_client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh_client.connect(
        hostname=SSH_HOST,
        port=SSH_PORT,
        username=SSH_USERNAME,
        password=SSH_PASSWORD,
        timeout=30
    )
    print("✅ Connected!\n")

    # Check where Laravel files are
    print("Checking file structure...")
    output, _ = execute_cmd(ssh_client, "ls -la ~/public_html/ | head -20")
    print(output)

    print("\nChecking for nested public_html...")
    output, _ = execute_cmd(ssh_client, "ls -la ~/public_html/public_html/ 2>/dev/null | head -20")
    print(output)

    # Check for artisan in nested directory
    output, error = execute_cmd(ssh_client, "if [ -f ~/public_html/public_html/artisan ]; then echo 'FOUND_NESTED'; elif [ -f ~/public_html/artisan ]; then echo 'FOUND_ROOT'; else echo 'NOT_FOUND'; fi")
    result = output.strip()

    print(f"\nArtisan location: {result}")

    if result == "FOUND_NESTED":
        print("\n🔧 Laravel files are in nested directory. Moving to root...")

        # Backup existing files in root if any
        print("Creating backup directory...")
        execute_cmd(ssh_client, "mkdir -p ~/backup_$(date +%Y%m%d_%H%M%S)")
        execute_cmd(ssh_client, "mv ~/public_html/diagnostic.php ~/public_html/phpinfo.php ~/backup_$(date +%Y%m%d_%H%M%S)/ 2>/dev/null || true")

        # Move all files from nested to root
        print("Moving Laravel files to root...")
        commands = """
        cd ~/public_html/public_html
        shopt -s dotglob
        for file in *; do
            if [ -e "$file" ]; then
                mv -f "$file" ../
            fi
        done
        cd ..
        rmdir public_html 2>/dev/null || rm -rf public_html
        echo "✅ Files moved successfully"
        """
        output, error = execute_cmd(ssh_client, commands)
        print(output)
        if error:
            print(f"Warnings: {error}")

        # Verify
        print("\nVerifying file structure...")
        output, _ = execute_cmd(ssh_client, "ls -la ~/public_html/ | grep -E 'artisan|app|config|routes|vendor'")
        print(output)

        # Check artisan now
        output, _ = execute_cmd(ssh_client, "cd ~/public_html && php artisan --version 2>&1")
        print(f"\n✅ Laravel verification:\n{output}")

    elif result == "FOUND_ROOT":
        print("✅ Laravel files are already in the correct location!")
    else:
        print("❌ Laravel files not found in either location!")
        print("Please check if files were uploaded correctly.")

    ssh_client.close()
    print("\n✅ Done!")

if __name__ == "__main__":
    main()
