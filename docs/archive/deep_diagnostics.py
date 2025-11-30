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
print("DEEP DIAGNOSTICS - FINDING 403 ROOT CAUSE")
print("=" * 70)

# 1. Check actual document root in Apache config
print("\n[1] Checking document root configuration...")
result = execute_ssh(ssh, "pwd && ls -la /home/u990109832/domains/")
print(result["output"])

# 2. Check what Hostinger expects as document root
print("\n[2] Checking domain configuration...")
result = execute_ssh(ssh, "ls -la /home/u990109832/domains/coprra.com/")
print(result["output"])

# 3. Very important - check if there's a .htaccess HIGHER up
print("\n[3] Checking for parent .htaccess files...")
result = execute_ssh(ssh, """
[ -f /home/u990109832/.htaccess ] && echo 'Found in home' && cat /home/u990109832/.htaccess || echo 'No home htaccess'
[ -f /home/u990109832/domains/.htaccess ] && echo 'Found in domains' || echo 'No domains htaccess'
[ -f /home/u990109832/domains/coprra.com/.htaccess ] && echo 'Found in coprra.com' && cat /home/u990109832/domains/coprra.com/.htaccess || echo 'No coprra.com htaccess'
""")
print(result["output"])

# 4. Check permissions on all levels
print("\n[4] Checking permissions on directory tree...")
result = execute_ssh(ssh, """
ls -ld /home/u990109832/
ls -ld /home/u990109832/domains/
ls -ld /home/u990109832/domains/coprra.com/
ls -ld /home/u990109832/domains/coprra.com/public_html/
ls -ld /home/u990109832/domains/coprra.com/public_html/public/
""")
print(result["output"])

# 5. Check ownership
print("\n[5] Verifying file ownership...")
result = execute_ssh(ssh, f"ls -la {PROJECT_ROOT}/ | head -n 5")
print(result["output"])

# 6. Try creating a simple test file
print("\n[6] Creating simple test file...")
result = execute_ssh(ssh, f"""cd {PROJECT_ROOT}
echo '<?php echo "PHP WORKS - " . date("Y-m-d H:i:s"); ?>' > test-direct.php
chmod 644 test-direct.php
ls -la test-direct.php
""")
print(result["output"])

# Test accessing it
result = execute_ssh(ssh, "curl -s http://localhost/test-direct.php 2>&1")
print(f"\nTest file output: {result['output']}")

# 7. Check if index.php can be executed directly
print("\n[7] Testing index.php execution...")
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php index.php 2>&1 | head -n 30")
print("Running index.php via CLI:")
print(result["output"])

# 8. Check Apache error logs if accessible
print("\n[8] Checking for Apache error logs...")
result = execute_ssh(ssh, """
# Common log locations
[ -f ~/logs/error_log ] && tail -n 10 ~/logs/error_log && echo '---' || echo 'No ~/logs/error_log'
[ -f ~/domains/coprra.com/logs/error_log ] && tail -n 10 ~/domains/coprra.com/logs/error_log || echo 'No domain error_log'
""")
print(result["output"])

# 9. Check if there's an index.html interfering
print("\n[9] Checking for interfering index files...")
result = execute_ssh(ssh, f"ls -la {PROJECT_ROOT}/index.* {PROJECT_ROOT}/public/index.*")
print(result["output"])

# 10. Try accessing via PHP CLI server
print("\n[10] Trying PHP built-in server test...")
result = execute_ssh(ssh, f"cd {PROJECT_ROOT}/public && timeout 3 php -S localhost:8888 > /dev/null 2>&1 & sleep 1 && curl -s http://localhost:8888 2>&1 | head -n 20 ; pkill -f 'php -S'")
print("PHP built-in server response:")
print(result["output"])

# 11. Check for .user.ini file
print("\n[11] Checking for .user.ini files...")
result = execute_ssh(ssh, f"""
find {PROJECT_ROOT} -name '.user.ini' -type f 2>/dev/null | head -n 5
""")
if result["output"].strip():
    print("Found .user.ini files:")
    print(result["output"])
else:
    print("No .user.ini files found")

# 12. Final check - what does the domain actually point to?
print("\n[12] Checking actual web root...")
result = execute_ssh(ssh, """
# Hostinger typically uses public_html as document root
# Let's verify the structure
echo "Expected structure for Hostinger:"
echo "/home/u990109832/domains/coprra.com/public_html/ <- THIS should be document root"
echo ""
echo "Current structure:"
ls -la /home/u990109832/domains/coprra.com/ | grep -E 'public|html'
""")
print(result["output"])

ssh.close()

print("\n" + "=" * 70)
print("DIAGNOSIS COMPLETE")
print("=" * 70)
print("\nBased on Hostinger's standard setup:")
print("- Document root should be: /home/u990109832/domains/coprra.com/public_html/")
print("- Laravel's public folder is at: .../public_html/public/")
print("- This creates a nested structure that needs special .htaccess")
print("=" * 70)
