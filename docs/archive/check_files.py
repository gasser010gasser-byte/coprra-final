#!/usr/bin/env python3
import paramiko

SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USER = "u990109832"
SSH_PASS = "Hamo1510@Rayan146"

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(hostname=SSH_HOST, port=SSH_PORT, username=SSH_USER, password=SSH_PASS)

# Check what's in home directory
stdin, stdout, stderr = ssh.exec_command("ls -la /home/u990109832/")
print("Home directory contents:")
print(stdout.read().decode())

# Check public_html
stdin, stdout, stderr = ssh.exec_command("ls -la /home/u990109832/public_html/")
print("\npublic_html contents:")
print(stdout.read().decode())

# Find Laravel files
stdin, stdout, stderr = ssh.exec_command("find /home/u990109832 -name 'artisan' -type f 2>/dev/null")
print("\nFound artisan file at:")
print(stdout.read().decode())

ssh.close()
