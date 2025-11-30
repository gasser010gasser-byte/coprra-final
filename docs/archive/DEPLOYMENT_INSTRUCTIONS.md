# COPRRA Hostinger Deployment - Quick Start Guide

## 🚀 Execute Deployment in 3 Steps

### Step 1: Connect to SSH

Open your terminal (Command Prompt, PowerShell, or Git Bash) and run:

```bash
ssh -p 65002 u990109832@45.87.81.218
```

When prompted for password, enter:
```
Hamo1510@Rayan146
```

### Step 2: Download and Execute Deployment Script

Once connected to SSH, run these commands:

```bash
# Download the deployment script from your local files
# (You'll need to copy the script content manually - see below)

# Create the script
cat > /home/u990109832/deploy.sh << 'EOF'
# [PASTE THE ENTIRE CONTENT OF deploy-to-hostinger.sh HERE]
EOF

# Make it executable
chmod +x /home/u990109832/deploy.sh

# Run the deployment
bash /home/u990109832/deploy.sh
```

**OR** (Easier Method):

Copy the entire content of `deploy-to-hostinger.sh` and paste it directly into the SSH terminal. It will execute all commands automatically.

### Step 3: Verify Deployment

After the script completes, you'll see:

```
═══════════════════════════════════════════════════════════════════
🎉 DEPLOYMENT COMPLETE!
═══════════════════════════════════════════════════════════════════

✅ All phases completed successfully!

🔐 Database Credentials (SAVE THESE!):
════════════════════════════════════════════════════════════════
Database Name: u990109832_coprra
Database User: u990109832_coprra
Database Password: [AUTO-GENERATED PASSWORD]
════════════════════════════════════════════════════════════════
```

**IMPORTANT:** Copy and save the database password shown in the output!

## 🌐 Test Your Website

1. Open browser: **https://coprra.com**
2. Verify homepage loads
3. Test health endpoint: **https://coprra.com/health**
4. Test status endpoint: **https://coprra.com/status**

## 📋 What the Script Does Automatically

1. ✅ **Extracts vendor.zip** (if present)
2. ✅ **Creates MySQL database** (u990109832_coprra)
3. ✅ **Generates secure database password**
4. ✅ **Configures .env file** for production
5. ✅ **Generates APP_KEY**
6. ✅ **Sets correct permissions** (storage, cache)
7. ✅ **Clears all caches**
8. ✅ **Optimizes Composer**
9. ✅ **Creates production caches**
10. ✅ **Runs database migrations** (creates all tables)
11. ✅ **Configures .htaccess** (Laravel routing + HTTPS)
12. ✅ **Verifies everything works**

## 🔐 Your Credentials

After deployment, you'll have:

- **SSH Access:**
  - Host: `45.87.81.218`
  - Port: `65002`
  - User: `u990109832`
  - Password: `Hamo1510@Rayan146`

- **Database Credentials:**
  - Name: `u990109832_coprra`
  - User: `u990109832_coprra`
  - Password: `[See deployment output]`
  - Saved to: `/home/u990109832/db_credentials.txt`

- **Website:**
  - URL: `https://coprra.com`
  - Environment: `production`
  - Debug: `false`

## 🛠️ Troubleshooting

### Website shows 500 error?

```bash
# SSH into server
ssh -p 65002 u990109832@45.87.81.218

# Check Laravel logs
cd /home/u990109832/public_html
tail -50 storage/logs/laravel.log

# Fix permissions
chmod -R 775 storage bootstrap/cache
```

### Database connection error?

```bash
# Verify database exists
mysql -u u990109832 -p'Hamo1510@Rayan146' -e "SHOW DATABASES LIKE 'u990109832_coprra';"

# Test Laravel connection
cd /home/u990109832/public_html
php artisan db:show
```

### Blank page?

```bash
# Check .htaccess files
cd /home/u990109832/public_html
cat .htaccess
cat public/.htaccess

# Rebuild caches
php artisan optimize:clear
php artisan optimize
```

### Want to check deployment status?

```bash
# Run status check
bash /home/u990109832/check_status.sh
```

## 📞 Need Help?

1. Check the deployment output for specific errors
2. Review `/home/u990109832/public_html/storage/logs/laravel.log`
3. Verify all credentials are correct in `.env`
4. Ensure all migrations ran successfully: `php artisan migrate:status`

## ✅ Success Criteria

Deployment is successful when:

- ✅ `https://coprra.com` loads without errors
- ✅ Database has all tables (check with `php artisan migrate:status`)
- ✅ No errors in `storage/logs/laravel.log`
- ✅ Can access admin panel (if applicable)
- ✅ All features work as expected

## 🎯 Next Steps After Deployment

1. **Test thoroughly:**
   - Homepage
   - Login/Register
   - Product search
   - Shopping cart
   - Price comparison features

2. **Configure email:**
   - Update MAIL_* settings in `.env`
   - Test with: `php artisan tinker` → `Mail::raw('Test', function($m) { $m->to('your@email.com')->subject('Test'); });`

3. **Set up backups:**
   - Database backups via cPanel
   - File backups weekly

4. **Monitor performance:**
   - Enable logging
   - Set up uptime monitoring
   - Review logs regularly

5. **Security:**
   - SSL certificate (should be auto-configured by Hostinger)
   - Set up 2FA for admin accounts
   - Regular security updates

---

**Deployment Date:** _________________
**Deployed By:** _________________
**Production URL:** https://coprra.com
**Status:** ⏳ Ready to Deploy
