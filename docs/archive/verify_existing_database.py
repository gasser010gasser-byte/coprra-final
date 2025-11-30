#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import paramiko
import sys

SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USER = "u990109832"
SSH_PASS = "Hamo1510@Rayan146"
PROJECT_ROOT = "/home/u990109832/domains/coprra.com/public_html"

# Database credentials from .env file
DB_NAME = "u990109832_coprra_db"
DB_USER = "u990109832_gasser"
DB_PASS = "Hamo1510@Rayan146"

def execute_ssh(ssh, cmd):
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=120)
    output = stdout.read().decode('utf-8', errors='replace')
    error = stderr.read().decode('utf-8', errors='replace')
    exit_code = stdout.channel.recv_exit_status()
    return {"output": output, "error": error, "exit_code": exit_code}

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(hostname=SSH_HOST, port=SSH_PORT, username=SSH_USER, password=SSH_PASS)

print("=" * 70)
print("VERIFYING EXISTING DATABASE FROM .ENV FILE")
print("=" * 70)

# Try connecting with existing credentials
print(f"\n[1] Testing connection with existing credentials...")
print(f"    Database: {DB_NAME}")
print(f"    User: {DB_USER}")

result = execute_ssh(ssh, f"mariadb -u {DB_USER} -p'{DB_PASS}' -e 'SHOW DATABASES;' 2>&1")

if "ERROR" in result["output"] or "ERROR" in result["error"]:
    print("[FAILED] Cannot connect with existing credentials")
    print(result["output"])
    print(result["error"])
else:
    print("[SUCCESS] Connected successfully!")
    print("\nAvailable databases:")
    print(result["output"])

    # Check if the specific database exists
    if DB_NAME in result["output"]:
        print(f"\n[SUCCESS] Database '{DB_NAME}' exists!")

        # Check tables in the database
        print(f"\n[2] Checking tables in {DB_NAME}...")
        result = execute_ssh(ssh, f"mariadb -u {DB_USER} -p'{DB_PASS}' -D {DB_NAME} -e 'SHOW TABLES;' 2>&1")

        if "Empty set" in result["output"]:
            print("[INFO] Database exists but has NO TABLES")
            print("      Migrations need to be run")
        elif "ERROR" in result["output"]:
            print("[ERROR] Cannot access database")
            print(result["output"])
        else:
            print("[SUCCESS] Database has tables:")
            print(result["output"])

            # Count tables
            table_count = result["output"].count('\n') - 1  # Subtract header
            print(f"\nTotal tables: {table_count}")

            # Check migration status using Laravel
            print(f"\n[3] Checking Laravel migration status...")
            result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php artisan migrate:status 2>&1")
            print(result["output"])

            if "Migration table not found" in result["output"]:
                print("\n[ACTION NEEDED] Migrations table doesn't exist")
                print("                Need to run: php artisan migrate")
            elif result["exit_code"] == 0:
                print("\n[SUCCESS] Migrations table exists!")

    else:
        print(f"\n[NOT FOUND] Database '{DB_NAME}' does not exist in available databases")

# Test Laravel database connection
print(f"\n[4] Testing Laravel database connection...")
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php artisan db:show 2>&1")

if result["exit_code"] == 0:
    print("[SUCCESS] Laravel can connect to database!")
    print(result["output"])
else:
    print("[FAILED] Laravel cannot connect to database")
    print(result["output"])

# Check if migrations can be run
print(f"\n[5] Checking if migrations can be executed...")
result = execute_ssh(ssh, f"cd {PROJECT_ROOT} && php artisan migrate --pretend 2>&1 | head -n 20")

if "Nothing to migrate" in result["output"]:
    print("[INFO] All migrations already applied")
elif "Migration table not found" in result["output"] or "SQLSTATE" in result["output"]:
    print("[INFO] Migrations can be run - database is ready")
    print(result["output"])
else:
    print(result["output"])

ssh.close()

print("\n" + "=" * 70)
print("SUMMARY")
print("=" * 70)
