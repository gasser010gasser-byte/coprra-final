#!/usr/bin/env python3
"""
Deep debugging for 500 error
"""

import paramiko
import requests

SSH_HOST = "45.87.81.218"
SSH_PORT = 65002
SSH_USERNAME = "u990109832"
SSH_PASSWORD = "Hamo1510@Rayan146"

print("🔍 DEEP DEBUGGING")

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(SSH_HOST, SSH_PORT, SSH_USERNAME, SSH_PASSWORD, timeout=30)

# Enable debug
print("\n🐛 Enabling debug...")
stdin, stdout, stderr = ssh.exec_command("""cd ~/public_html &&
sed -i 's/APP_DEBUG=false/APP_DEBUG=true/' .env &&
sed -i 's/LOG_LEVEL=error/LOG_LEVEL=debug/' .env &&
php artisan config:clear 2>&1
""")
stdout.read()

# Clear view cache specifically
print("\n🗑️  Clearing view cache...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && rm -rf storage/framework/views/* && php artisan view:clear")
print(stdout.read().decode())

# Test with curl to see actual error
print("\n🌐 Fetching actual error with curl...")
stdin, stdout, stderr = ssh.exec_command("curl -k https://coprra.com 2>/dev/null | head -100")
response = stdout.read().decode()
if response:
    print(response[:1000])  # First 1000 chars

# Check for any other missing views/components
print("\n📁 Checking views structure...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && find resources/views -type f -name '*.blade.php' | head -20")
views = stdout.read().decode()
print(views if views.strip() else "⚠️  No blade files found!")

# Create a simple test route
print("\n🧪 Creating test route...")
test_route = """
<?php
Route::get('/test-simple', function() {
    return 'Laravel is working!';
});
"""

stdin, stdout, stderr = ssh.exec_command(f"""cd ~/public_html &&
cat >> routes/web.php << 'TESTEOF'
{test_route}
TESTEOF
php artisan route:clear
echo "✅ Test route added"
""")
print(stdout.read().decode())

# Test the simple route
print("\n🧪 Testing simple route...")
try:
    response = requests.get("https://coprra.com/test-simple", timeout=10, verify=False)
    print(f"   Status: {response.status_code}")
    print(f"   Content: {response.text}")
    if response.status_code == 200:
        print("   ✅ Laravel core is working!")
except Exception as e:
    print(f"   Error: {e}")

# Check if welcome view exists
print("\n📄 Checking for welcome view...")
stdin, stdout, stderr = ssh.exec_command("ls -lh ~/public_html/resources/views/welcome.blade.php 2>&1")
welcome_check = stdout.read().decode()
if "No such file" in welcome_check:
    print("⚠️  Welcome view missing - creating simple one...")

    simple_welcome = """<!DOCTYPE html>
<html>
<head>
    <title>COPRRA</title>
    <style>
        body { font-family: Arial; text-align: center; padding: 50px; }
        h1 { color: #2c3e50; }
    </style>
</head>
<body>
    <h1>Welcome to COPRRA</h1>
    <p>E-Commerce Platform</p>
    <p>Laravel {{ app()->version() }}</p>
</body>
</html>
"""

    stdin, stdout, stderr = ssh.exec_command(f"""cd ~/public_html &&
cat > resources/views/welcome.blade.php << 'WELCOMEOF'
{simple_welcome}
WELCOMEOF
echo "✅ Welcome view created"
""")
    print(stdout.read().decode())
else:
    print(f"   {welcome_check}")

# Clear everything and test again
print("\n🔄 Final cache clear and test...")
stdin, stdout, stderr = ssh.exec_command("cd ~/public_html && php artisan optimize:clear && php artisan config:cache")
stdout.read()

try:
    response = requests.get("https://coprra.com", timeout=10, verify=False)
    print(f"   Status: {response.status_code}")
    if response.status_code == 200:
        print("   ✅ SUCCESS! Website is working!")
        print(f"   Response length: {len(response.text)} bytes")
    else:
        print(f"   Response preview:\n{response.text[:500]}")
except Exception as e:
    print(f"   Error: {e}")

# Check logs one more time
print("\n📋 Final log check...")
stdin, stdout, stderr = ssh.exec_command("tail -20 ~/public_html/storage/logs/laravel.log")
logs = stdout.read().decode()
if logs.strip() and "ERROR" in logs:
    print(logs[-500:])  # Last 500 chars
else:
    print("✅ No recent errors")

ssh.close()

print("\n✅ Debug complete!")
