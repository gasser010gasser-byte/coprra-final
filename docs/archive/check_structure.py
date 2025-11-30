#!/usr/bin/env python3
import paramiko

SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USER = "u990109832"
SSH_PASS = "Hamo1510@Rayan146"

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(hostname=SSH_HOST, port=SSH_PORT, username=SSH_USER, password=SSH_PASS)

# Check Laravel root structure
stdin, stdout, stderr = ssh.exec_command("ls -la /home/u990109832/domains/coprra.com/public_html/")
print("Laravel root (/home/u990109832/domains/coprra.com/public_html/):")
print(stdout.read().decode())
print("\n")

# Check if vendor.zip exists
stdin, stdout, stderr = ssh.exec_command("ls -lh /home/u990109832/domains/coprra.com/public_html/vendor* 2>&1")
print("Vendor files:")
print(stdout.read().decode())
print("\n")

# Check if there's a nested public_html
stdin, stdout, stderr = ssh.exec_command("ls -la /home/u990109832/public_html/public_html/ 2>&1 | head -20")
print("Nested public_html check:")
print(stdout.read().decode())

ssh.close()
