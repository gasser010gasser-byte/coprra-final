#!/usr/bin/env python3
import paramiko

SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USER = "u990109832"
SSH_PASS = "Hamo1510@Rayan146"

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(hostname=SSH_HOST, port=SSH_PORT, username=SSH_USER, password=SSH_PASS)

# Try to list databases using mariadb instead of mysql
stdin, stdout, stderr = ssh.exec_command("mariadb -u u990109832 -p'Hamo1510@Rayan146' -e 'SHOW DATABASES;' 2>&1")
output = stdout.read().decode()
print("Databases:")
print(output)
print("\n")

# Check if coprra database exists
if "u990109832_coprra" in output:
    print("[OK] Database u990109832_coprra already exists!")

    # Try to check if .env file exists
    stdin, stdout, stderr = ssh.exec_command("cd /home/u990109832/domains/coprra.com/public_html && grep 'DB_PASSWORD' .env 2>&1")
    env_output = stdout.read().decode()
    print("\nDatabase password from .env:")
    print(env_output)
else:
    print("[INFO] Database u990109832_coprra does not exist yet")

ssh.close()
