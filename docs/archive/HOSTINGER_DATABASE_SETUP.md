# Database Setup on Hostinger

## Steps to Create Database

### 1. Access MySQL Databases
1. Login to Hostinger cPanel
2. Scroll down to **Databases** section
3. Click on **MySQL Databases**

### 2. Create New Database
1. Find the section "Create New Database"
2. In the "New Database" field, enter: `coprra`
3. Click **"Create Database"** button
4. Note the full database name (will be: `username_coprra`)

Example:
```
If your cPanel username is: u123456789
Your database name will be: u123456789_coprra
```

### 3. Create Database User
1. Scroll down to "MySQL Users" section
2. Find "Add New User"
3. Username: enter `coprra_user`
4. Password: Click "Generate Password" (or create strong password)
5. **IMPORTANT:** Copy and save this password securely
6. Click **"Create User"** button

Full username will be: `username_coprra_user`

### 4. Add User to Database
1. Scroll to "Add User To Database" section
2. **User:** Select `username_coprra_user`
3. **Database:** Select `username_coprra`
4. Click **"Add"** button

### 5. Set Privileges
1. A page will appear showing privileges
2. Check **"ALL PRIVILEGES"** checkbox at the top
3. Click **"Make Changes"** button

### 6. Note Down Credentials

**Write these down - you'll need them for .env file:**

```
Database Host: localhost
Database Port: 3306
Database Name: u123456789_coprra (replace with your actual name)
Database User: u123456789_coprra_user (replace with your actual name)
Database Password: [the password you generated]
```

## Update .env File

After creating the database, update your `.env` file with these values:

```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u123456789_coprra
DB_USERNAME=u123456789_coprra_user
DB_PASSWORD=your_generated_password_here
```

## Testing Database Connection

After uploading files and configuring .env, test the connection:

```bash
# Via cPanel Terminal or SSH:
php artisan tinker

# In tinker, run:
DB::connection()->getPdo();

# If successful, you'll see:
# => PDO {#...}

# If error, check your credentials
```

## Common Issues

### Issue 1: "Access denied for user"
**Solution:** Double-check username and password in .env file

### Issue 2: "Unknown database"
**Solution:** Verify database name matches exactly (including prefix)

### Issue 3: "SQLSTATE[HY000] [2002] Connection refused"
**Solution:** Ensure DB_HOST is set to `localhost` (not 127.0.0.1)

### Issue 4: "Too many connections"
**Solution:** Check your Hostinger plan limits, may need to upgrade

## Next Steps

After database is configured:

1. Run migrations:
   ```bash
   php artisan migrate --force
   ```

2. (Optional) Seed database:
   ```bash
   php artisan db:seed --force
   ```

3. Verify tables were created:
   ```bash
   php artisan db:show
   ```

## Security Best Practices

✅ **DO:**
- Use generated strong passwords
- Store credentials in .env file (never commit to git)
- Limit database user to specific database only
- Use localhost for DB_HOST (more secure)

❌ **DON'T:**
- Use weak passwords
- Grant ALL PRIVILEGES to root user
- Share database credentials publicly
- Use default usernames like "root" or "admin"

---

**Status:** Ready for database setup
**Estimated Time:** 5-10 minutes
