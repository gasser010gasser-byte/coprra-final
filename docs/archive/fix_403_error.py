#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import paramiko

SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USER = "u990109832"
SSH_PASS = "Hamo1510@Rayan146"
PROJECT_ROOT = "/home/u990109832/domains/coprra.com/public_html"

def execute_ssh(ssh, cmd, timeout=120):
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=timeout)
    output = stdout.read().decode('utf-8', errors='replace')
    error = stderr.read().decode('utf-8', errors='replace')
    exit_code = stdout.channel.recv_exit_status()
    return {"output": output, "error": error, "exit_code": exit_code}

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(hostname=SSH_HOST, port=SSH_PORT, username=SSH_USER, password=SSH_PASS)

print("=" * 70)
print("FIXING 403 FORBIDDEN ERROR")
print("=" * 70)

# 1. Check directory structure
print("\n[1] Checking directory structure...")
result = execute_ssh(ssh, f"ls -la {PROJECT_ROOT}/ | grep -E 'index.php|public|.htaccess'")
print(result["output"])

# 2. Check if public directory exists
result = execute_ssh(ssh, f"[ -d {PROJECT_ROOT}/public ] && echo 'exists' || echo 'missing'")
has_public_dir = "exists" in result["output"]
print(f"\nPublic directory: {'EXISTS' if has_public_dir else 'MISSING'}")

# 3. Check what's in public directory
if has_public_dir:
    print("\n[2] Contents of public directory:")
    result = execute_ssh(ssh, f"ls -la {PROJECT_ROOT}/public/ | head -n 20")
    print(result["output"])

    # Check if index.php exists in public
    result = execute_ssh(ssh, f"[ -f {PROJECT_ROOT}/public/index.php ] && echo 'exists' || echo 'missing'")
    public_index_exists = "exists" in result["output"]
    print(f"\npublic/index.php: {'EXISTS' if public_index_exists else 'MISSING'}")

# 4. Fix .htaccess to properly route to public directory
print("\n[3] Creating proper .htaccess for Laravel...")

htaccess_content = """<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect to public folder
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ public/$1 [L]

    # Remove public from URL
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^public/(.*)$ public/index.php/$1 [L]
</IfModule>
"""

result = execute_ssh(ssh, f"""cd {PROJECT_ROOT}
cat > .htaccess << 'EOFHT'
{htaccess_content}
EOFHT
echo 'htaccess created'
""")
print(result["output"])

# 5. Also create .htaccess in public directory
print("\n[4] Creating .htaccess in public directory...")

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
</IfModule>
"""

result = execute_ssh(ssh, f"""cd {PROJECT_ROOT}/public
cat > .htaccess << 'EOFPUB'
{public_htaccess}
EOFPUB
echo 'public htaccess created'
""")
print(result["output"])

# 6. Set proper permissions
print("\n[5] Setting proper permissions...")
result = execute_ssh(ssh, f"""cd {PROJECT_ROOT}
chmod 644 .htaccess
chmod 644 public/.htaccess
chmod 644 public/index.php
chmod -R 755 public
echo 'permissions set'
""")
print(result["output"])

# 7. Check PHP handler settings
print("\n[6] Checking PHP handler...")
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php -v | head -n 1")
print(result["output"])

# 8. Create index.php in root if needed (for redirection)
print("\n[7] Ensuring proper routing...")
result = execute_ssh(ssh, f"""cd {PROJECT_ROOT}
# Check if we need root index.php
if [ -f public/index.php ]; then
    echo 'Using public/index.php - structure is correct'
else
    echo 'ERROR: public/index.php is missing!'
fi
""")
print(result["output"])

# 9. Test with curl again
print("\n[8] Testing site access after fix...")
result = execute_ssh(ssh, f"curl -s -o /dev/null -w '%{{http_code}}' http://localhost 2>&1")
status_code = result["output"].strip()
print(f"HTTP Status: {status_code}")

# 10. Get actual content
if status_code != "200":
    print("\n[9] Fetching error page...")
    result = execute_ssh(ssh, f"curl -s http://localhost 2>&1 | head -n 40")
    print(result["output"])

    # Try accessing public/index.php directly
    print("\n[10] Trying direct access to public/index.php...")
    result = execute_ssh(ssh, f"curl -s -o /dev/null -w '%{{http_code}}' http://localhost/public/index.php 2>&1")
    print(f"Direct access status: {result['output'].strip()}")

    if result["output"].strip() == "200":
        print("\n[INFO] public/index.php works! Issue is with .htaccess routing")
        result = execute_ssh(ssh, f"curl -s http://localhost/public/index.php 2>&1 | head -n 20")
        print(result["output"])
else:
    print("\n[SUCCESS] Site is now accessible!")
    result = execute_ssh(ssh, f"curl -s http://localhost 2>&1 | head -n 30")
    print(result["output"])

# 11. Check server error logs if still failing
print("\n[11] Checking for server errors...")
result = execute_ssh(ssh, f"tail -n 20 {PROJECT_ROOT}/storage/logs/laravel.log 2>&1 | grep -i error | tail -n 5")
if result["output"].strip():
    print("Recent errors:")
    print(result["output"])
else:
    print("No recent errors in Laravel logs")

ssh.close()

print("\n" + "=" * 70)
print("FIX COMPLETED")
print("=" * 70)
print("If still showing 403, the issue might be:")
print("1. Server-level permissions (need cPanel to fix)")
print("2. Apache configuration (need cPanel/Hostinger support)")
print("3. mod_rewrite not enabled (contact Hostinger)")
print("=" * 70)
