#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import paramiko
import sys

SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USER = "u990109832"
SSH_PASS = "Hamo1510@Rayan146"
DB_NAME = "u990109832_coprra"
DB_USER = "u990109832_coprra_user"
PROJECT_ROOT = "/home/u990109832/domains/coprra.com/public_html"

def execute_ssh(ssh, cmd):
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=60)
    output = stdout.read().decode('utf-8', errors='replace')
    error = stderr.read().decode('utf-8', errors='replace')
    exit_code = stdout.channel.recv_exit_status()
    return {"output": output, "error": error, "exit_code": exit_code}

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(hostname=SSH_HOST, port=SSH_PORT, username=SSH_USER, password=SSH_PASS)

print("=" * 70)
print("DATABASE CREATION ATTEMPTS")
print("=" * 70)

# Attempt 1: Check if database already exists using mariadb
print("\n[1] Checking existing databases with mariadb...")
result = execute_ssh(ssh, "mariadb -u u990109832 -p'Hamo1510@Rayan146' -e 'SHOW DATABASES;' 2>&1")
print(result["output"] if result["output"] else result["error"])

# Attempt 2: Try to access without password (check .my.cnf)
print("\n[2] Checking for .my.cnf auto-login...")
result = execute_ssh(ssh, "cat ~/.my.cnf 2>&1")
if ".my.cnf" not in result["error"]:
    print("Found .my.cnf:")
    print(result["output"])

    # Try connecting without password
    print("\n[2b] Trying connection with .my.cnf...")
    result = execute_ssh(ssh, "mariadb -e 'SHOW DATABASES;' 2>&1")
    print(result["output"] if result["output"] else result["error"])
else:
    print("No .my.cnf file found")

# Attempt 3: Check for uapi (Hostinger/cPanel API)
print("\n[3] Checking for uapi (cPanel API)...")
result = execute_ssh(ssh, "which uapi 2>&1")
if result["exit_code"] == 0:
    print("Found uapi at:", result["output"].strip())

    # Try creating database via uapi
    print("\n[3b] Attempting database creation via uapi...")
    result = execute_ssh(ssh, f"uapi --user=u990109832 Mysql create_database name=coprra 2>&1")
    print(result["output"] if result["output"] else result["error"])

    # Try creating user via uapi
    print("\n[3c] Attempting user creation via uapi...")
    result = execute_ssh(ssh, f"uapi --user=u990109832 Mysql create_user name=coprra_user password='Coprra2025!Secure' 2>&1")
    print(result["output"] if result["output"] else result["error"])
else:
    print("uapi not available")

# Attempt 4: Check for cpapi2
print("\n[4] Checking for cpapi2...")
result = execute_ssh(ssh, "which cpapi2 2>&1")
if result["exit_code"] == 0:
    print("Found cpapi2 at:", result["output"].strip())

    print("\n[4b] Attempting database creation via cpapi2...")
    result = execute_ssh(ssh, f"cpapi2 --user=u990109832 MysqlFE createdb db=coprra 2>&1")
    print(result["output"] if result["output"] else result["error"])
else:
    print("cpapi2 not available")

# Attempt 5: Try mysqladmin
print("\n[5] Trying mysqladmin to create database...")
result = execute_ssh(ssh, f"mysqladmin -u u990109832 -p'Hamo1510@Rayan146' create u990109832_coprra 2>&1")
print(result["output"] if result["output"] else result["error"])

# Attempt 6: Check if database was created by any method
print("\n[6] Final check - listing all databases...")
result = execute_ssh(ssh, "mariadb -u u990109832 -p'Hamo1510@Rayan146' -e 'SHOW DATABASES;' 2>&1")
if "u990109832_coprra" in result["output"]:
    print("[SUCCESS] Database u990109832_coprra exists!")
elif "ERROR 1045" in result["error"] or "ERROR 1045" in result["output"]:
    print("[ACCESS DENIED] Cannot access MySQL/MariaDB")
    print("\n[INFO] Database creation requires cPanel GUI access")
    print("      Hostinger restricts database creation to cPanel interface")
else:
    print("[NOT FOUND] Database u990109832_coprra does not exist")
    print(result["output"])

# Attempt 7: Check .env file current status
print("\n[7] Checking current .env database configuration...")
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && grep '^DB_' .env 2>&1")
print("Current .env DB settings:")
print(result["output"])

# Attempt 8: Check if we can at least verify PHP/Laravel works
print("\n[8] Verifying PHP and Laravel are functional...")
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php -v 2>&1 | head -n 1")
print("PHP Version:", result["output"].strip())

result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php artisan --version 2>&1")
print("Laravel:", result["output"].strip())

ssh.close()

print("\n" + "=" * 70)
print("CONCLUSION")
print("=" * 70)
print("If no database was created above, manual cPanel access is required.")
print("Database: u990109832_coprra")
print("User: u990109832_coprra_user")
print("cPanel: https://cpanel.hostinger.com")
print("=" * 70)
