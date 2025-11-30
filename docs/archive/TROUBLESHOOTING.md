# COPRRA - Developer Troubleshooting Guide

**Version**: 1.0
**Last Updated**: October 30, 2025

This guide helps you quickly diagnose and fix common development issues.

---

## 🆘 Quick Help

| Issue | Quick Fix |
|-------|-----------|
| 🔴 Setup fails | `make clean-all && make setup` |
| 🔴 Permission errors | `chmod -R 775 storage bootstrap/cache` |
| 🔴 Database errors | `make db-fresh` |
| 🔴 Cache issues | `make cache-clear` |
| 🔴 Tests fail | `make test-quick` |
| 🔴 Docker issues | `make docker-clean && make docker-up` |

---

## 📋 Table of Contents

1. [Installation & Setup Issues](#1-installation--setup-issues)
2. [Environment & Configuration](#2-environment--configuration)
3. [Database Issues](#3-database-issues)
4. [Frontend & Asset Issues](#4-frontend--asset-issues)
5. [Permission Issues](#5-permission-issues)
6. [Docker Issues](#6-docker-issues)
7. [Testing Issues](#7-testing-issues)
8. [Performance Issues](#8-performance-issues)
9. [Security Issues](#9-security-issues)
10. [IDE & Development Tools](#10-ide--development-tools)

---

## 1. Installation & Setup Issues

### 1.1 Composer Install Fails

**Symptom**: `composer install` command fails or hangs

**Causes & Solutions**:

```bash
# Solution 1: Clear Composer cache
composer clear-cache
composer install

# Solution 2: Increase memory limit
php -d memory_limit=-1 /path/to/composer.phar install

# Solution 3: Install without scripts
composer install --no-scripts
php artisan package:discover

# Solution 4: Use alternative repository
composer config repositories.packagist composer https://packagist.org
composer install
```

**Check**:
```bash
# Verify Composer version (requires 2.5+)
composer --version

# Check PHP extensions
php -m | grep -E 'curl|mbstring|xml|zip'
```

---

### 1.2 NPM Install Fails

**Symptom**: `npm install` or `npm ci` fails

**Solutions**:

```bash
# Solution 1: Clear NPM cache
npm cache clean --force
rm -rf node_modules package-lock.json
npm install

# Solution 2: Use legacy peer deps
npm install --legacy-peer-deps

# Solution 3: Update NPM
npm install -g npm@latest
npm install

# Solution 4: Check Node version (requires 18+)
node --version
nvm install 18
nvm use 18
npm install
```

---

### 1.3 "APP_KEY not set" Error

**Symptom**: Application shows "No application encryption key has been specified"

**Solution**:

```bash
# Generate new key
php artisan key:generate

# Or manually set in .env
# APP_KEY=base64:YOUR_32_CHARACTER_BASE64_KEY

# Clear config cache
php artisan config:clear
```

---

### 1.4 "Class not found" Errors

**Symptom**: "Class 'X' not found" errors

**Solutions**:

```bash
# Solution 1: Dump autoload
composer dump-autoload

# Solution 2: Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Solution 3: Reinstall dependencies
rm -rf vendor
composer install --optimize-autoloader

# Solution 4: Check namespace
# Verify class namespace matches file location
```

---

## 2. Environment & Configuration

### 2.1 .env File Issues

**Symptom**: Configuration values not loading

**Solutions**:

```bash
# Solution 1: Check .env exists
ls -la .env
# If not: cp .env.example .env

# Solution 2: Clear config cache
php artisan config:clear

# Solution 3: Check syntax
# .env values should NOT have quotes unless string contains spaces
# GOOD: DB_PASSWORD=mypassword
# BAD:  DB_PASSWORD='mypassword'

# Solution 4: Check for special characters
# Escape special characters or use quotes
# DB_PASSWORD="p@ss#word!"
```

**Validation**:

```bash
# Check current config
php artisan config:show app
php artisan config:show database

# Check env file loading
php artisan tinker
> config('app.name')
> env('DB_DATABASE')
```

---

### 2.2 Wrong Environment Detected

**Symptom**: Application runs in wrong environment (dev/production)

**Solutions**:

```bash
# Check current environment
php artisan env

# Set in .env file
APP_ENV=local      # For development
APP_ENV=production # For production
APP_DEBUG=true     # Development only
APP_DEBUG=false    # Production

# Clear config
php artisan config:clear
```

---

## 3. Database Issues

### 3.1 "Could not connect to database"

**Symptom**: SQLSTATE errors or connection refused

**Diagnosis**:

```bash
# Check MySQL/PostgreSQL is running
# macOS:
brew services list

# Linux:
systemctl status mysql
systemctl status postgresql

# Check connection manually
mysql -h 127.0.0.1 -u coprra -p coprra
# Or
psql -h 127.0.0.1 -U coprra -d coprra
```

**Solutions**:

```bash
# Solution 1: Check .env settings
DB_CONNECTION=mysql
DB_HOST=127.0.0.1       # NOT localhost if using TCP
DB_PORT=3306
DB_DATABASE=coprra
DB_USERNAME=coprra
DB_PASSWORD=your_password

# Solution 2: Create database
mysql -u root -p
CREATE DATABASE coprra CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL ON coprra.* TO 'coprra'@'localhost' IDENTIFIED BY 'password';
FLUSH PRIVILEGES;
EXIT;

# Solution 3: Test connection
php artisan db:show
php artisan migrate:status

# Solution 4: Check port conflicts
lsof -i :3306
```

---

### 3.2 Migration Fails

**Symptom**: Migration errors or timeout

**Solutions**:

```bash
# Solution 1: Check migrations status
php artisan migrate:status

# Solution 2: Rollback and retry
php artisan migrate:rollback
php artisan migrate

# Solution 3: Fresh migration (WARNING: deletes data)
php artisan migrate:fresh --seed

# Solution 4: Run specific migration
php artisan migrate --path=database/migrations/2024_01_01_000000_create_users_table.php

# Solution 5: Increase timeout (in migration file)
Schema::connection('mysql')->getConnection()->statement('SET SESSION wait_timeout=600');

# Solution 6: Check for syntax errors
php artisan migrate --pretend
```

---

### 3.3 "Database is locked" (SQLite)

**Symptom**: "Database is locked" error with SQLite

**Solutions**:

```bash
# Solution 1: Close all connections
php artisan cache:clear
php artisan queue:restart

# Solution 2: Remove lock
rm -f database/database.sqlite-journal

# Solution 3: Use MySQL/PostgreSQL instead
# SQLite is not recommended for development with queues
```

---

## 4. Frontend & Asset Issues

### 4.1 Assets Not Loading

**Symptom**: CSS/JS files return 404 or not applying

**Solutions**:

```bash
# Solution 1: Build assets
npm run build

# Solution 2: Clear public/build
rm -rf public/build
npm run build

# Solution 3: Check Vite config
# Verify vite.config.js exists and is correct

# Solution 4: For development, use dev server
npm run dev
# Access via http://localhost:5173
```

---

### 4.2 "npm run dev" Fails

**Symptom**: Vite dev server won't start

**Solutions**:

```bash
# Solution 1: Kill existing process
lsof -ti:5173 | xargs kill -9

# Solution 2: Clear Vite cache
rm -rf node_modules/.vite
npm run dev

# Solution 3: Check port availability
# Edit vite.config.js to use different port
server: {
    port: 5174
}

# Solution 4: Reinstall dependencies
rm -rf node_modules package-lock.json
npm install
```

---

### 4.3 CSS Not Updating

**Symptom**: CSS changes not reflecting in browser

**Solutions**:

```bash
# Solution 1: Clear view cache
php artisan view:clear

# Solution 2: Hard refresh browser
# Ctrl+Shift+R (Windows/Linux)
# Cmd+Shift+R (macOS)

# Solution 3: Clear browser cache

# Solution 4: Check Tailwind purge
# Verify tailwind.config.js content paths

# Solution 5: Rebuild assets
npm run build
```

---

## 5. Permission Issues

### 5.1 "Permission Denied" Errors

**Symptom**: Laravel can't write to storage/logs/cache

**Solutions**:

```bash
# Solution 1: Fix permissions (Development)
chmod -R 775 storage bootstrap/cache
chown -R $USER:www-data storage bootstrap/cache

# Solution 2: Fix permissions (Production)
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Solution 3: Recreate directories
rm -rf storage/logs/*
rm -rf storage/framework/cache/*
rm -rf storage/framework/sessions/*
rm -rf storage/framework/views/*
php artisan cache:clear

# Solution 4: SELinux (if applicable)
# Check SELinux status
getenforce

# If enforcing, set context
chcon -R -t httpd_sys_rw_content_t storage
chcon -R -t httpd_sys_rw_content_t bootstrap/cache
```

---

### 5.2 "Storage Link Broken"

**Symptom**: Images not displaying from storage

**Solutions**:

```bash
# Solution 1: Remove old link and recreate
rm public/storage
php artisan storage:link

# Solution 2: Check link
ls -la public/storage

# Solution 3: Manual link (if artisan fails)
ln -s ../storage/app/public public/storage

# Solution 4: For Windows
mklink /D public\storage ..\storage\app\public
```

---

## 6. Docker Issues

### 6.1 Docker Containers Won't Start

**Symptom**: `docker-compose up` fails

**Solutions**:

```bash
# Solution 1: Check logs
docker-compose logs
docker-compose logs app

# Solution 2: Rebuild images
docker-compose down
docker-compose build --no-cache
docker-compose up -d

# Solution 3: Check port conflicts
lsof -i :8000
lsof -i :3306
lsof -i :6379

# Solution 4: Remove orphan containers
docker-compose down --remove-orphans
docker-compose up -d

# Solution 5: Clean Docker system
docker system prune -a --volumes
# WARNING: This removes all unused containers, networks, images
```

---

### 6.2 "No space left on device"

**Symptom**: Docker build fails with disk space error

**Solutions**:

```bash
# Solution 1: Clean Docker
docker system df           # Check disk usage
docker system prune -a     # Remove unused data
docker volume prune        # Remove unused volumes

# Solution 2: Increase Docker disk space
# Docker Desktop → Settings → Resources → Disk image size

# Solution 3: Remove old images
docker images
docker rmi <image_id>
```

---

### 6.3 Container Can't Access Database

**Symptom**: App container can't connect to MySQL container

**Solutions**:

```bash
# Solution 1: Check network
docker network ls
docker network inspect coprra_coprra-net

# Solution 2: Use container name as host
# In .env (Docker):
DB_HOST=mysql           # NOT 127.0.0.1
DB_HOST=coprra-mysql    # Or container name

# Solution 3: Check container is running
docker-compose ps
docker-compose logs mysql

# Solution 4: Restart containers
docker-compose restart app
docker-compose restart mysql
```

---

## 7. Testing Issues

### 7.1 Tests Fail

**Symptom**: PHPUnit tests fail unexpectedly

**Solutions**:

```bash
# Solution 1: Refresh test database
php artisan config:clear --env=testing
php artisan migrate:fresh --env=testing --seed

# Solution 2: Clear test cache
php artisan test --clear-cache

# Solution 3: Run single test
php artisan test --filter=testExample

# Solution 4: Check .env.testing exists
cp .env.testing.example .env.testing
php artisan key:generate --env=testing

# Solution 5: Check database connection
# .env.testing should use separate DB:
DB_DATABASE=coprra_test
```

---

### 7.2 "No tests executed"

**Symptom**: PHPUnit runs but no tests execute

**Solutions**:

```bash
# Solution 1: Check test discovery
php artisan test --list-tests

# Solution 2: Verify test class
# Must extend TestCase
# Must have test prefix or @test annotation

# Solution 3: Clear Composer cache
composer dump-autoload
php artisan test

# Solution 4: Check phpunit.xml
# Verify testsuites are configured
```

---

## 8. Performance Issues

### 8.1 Slow Application Response

**Symptom**: Pages load slowly (>1 second)

**Diagnosis**:

```bash
# Check query performance
php artisan telescope:prune # If using Telescope

# Profile with Clockwork
composer require itsgoingd/clockwork --dev

# Check logs
tail -f storage/logs/laravel.log
```

**Solutions**:

```bash
# Solution 1: Enable caching
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Solution 2: Optimize database
php artisan db:show
# Add indexes where needed

# Solution 3: Enable OPcache
# In php.ini:
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60

# Solution 4: Use eager loading
# Avoid N+1 queries:
User::with('orders')->get(); // Good
User::all()->each->orders;   // Bad (N+1)

# Solution 5: Use queue for slow tasks
php artisan queue:work --tries=3
```

---

### 8.2 High Memory Usage

**Symptom**: PHP runs out of memory

**Solutions**:

```bash
# Solution 1: Increase memory limit temporarily
php -d memory_limit=512M artisan command

# Solution 2: Process in chunks
# Use chunk() for large datasets:
User::chunk(200, function ($users) {
    // Process users
});

# Solution 3: Clear memory
gc_collect_cycles();

# Solution 4: Optimize queries
# Use select() to limit columns
User::select('id', 'name')->get();
```

---

## 9. Security Issues

### 9.1 CSRF Token Mismatch

**Symptom**: "CSRF token mismatch" error on form submission

**Solutions**:

```bash
# Solution 1: Clear cookies and cache
# Browser: Clear cookies for localhost

# Solution 2: Check @csrf directive
# In Blade forms:
<form method="POST">
    @csrf
    <!-- form fields -->
</form>

# Solution 3: Verify session config
# config/session.php:
'same_site' => 'lax', // NOT 'strict' for API

# Solution 4: Clear session cache
php artisan session:clear
php artisan cache:clear
```

---

### 9.2 Rate Limit Exceeded

**Symptom**: "Too Many Requests" (429) error

**Solutions**:

```bash
# Solution 1: Clear rate limit cache
php artisan cache:clear

# Solution 2: Adjust rate limits
# routes/api.php:
Route::middleware('throttle:60,1')->group(...);

# Solution 3: Bypass for development
# In .env:
RATE_LIMIT_ENABLED=false
```

---

## 10. IDE & Development Tools

### 10.1 PHPStorm Not Recognizing Laravel Classes

**Symptom**: Autocomplete doesn't work for Laravel classes

**Solutions**:

```bash
# Solution 1: Generate IDE helper
composer require --dev barryvdh/laravel-ide-helper
php artisan ide-helper:generate
php artisan ide-helper:models --nowrite
php artisan ide-helper:meta

# Solution 2: Enable Laravel plugin
# PHPStorm → Settings → Plugins → Laravel

# Solution 3: Reindex
# File → Invalidate Caches / Restart
```

---

### 10.2 VS Code Intelephense Errors

**Symptom**: VS Code shows false PHP errors

**Solutions**:

```json
// .vscode/settings.json
{
    "php.suggest.basic": false,
    "intelephense.files.exclude": [
        "**/vendor/**",
        "**/node_modules/**",
        "**/.git/**",
        "**/.history/**"
    ],
    "intelephense.environment.phpVersion": "8.2.0",
    "intelephense.diagnostics.undefinedTypes": false
}
```

---

## 🆘 Emergency Recovery

If everything fails, try this nuclear option:

```bash
# FULL RESET (saves .env)
make clean-all
rm -rf vendor node_modules public/build
cp .env .env.backup
git clean -fdx
git reset --hard
cp .env.backup .env
make setup
```

---

## 📞 Getting Help

If you're still stuck:

1. **Check Documentation**:
   - [README.md](./README.md)
   - [SETUP_GUIDE.md](./SETUP_GUIDE.md)
   - [docs/](./docs/)

2. **Search Issues**:
   - [GitHub Issues](https://github.com/your-org/coprra/issues)

3. **Ask for Help**:
   - Create a new GitHub issue
   - Include error messages
   - Include steps to reproduce
   - Include system information:
   ```bash
   php --version
   composer --version
   node --version
   npm --version
   ```

4. **Check Logs**:
   ```bash
   # Laravel logs
   tail -f storage/logs/laravel.log

   # PHP-FPM logs (Docker)
   docker-compose logs -f app

   # Nginx logs (Docker)
   docker-compose logs -f nginx
   ```

---

## 🔧 Diagnostic Commands

Run these to gather system information for bug reports:

```bash
# System info
php --version
composer --version
node --version
npm --version
docker --version
docker-compose --version

# Laravel info
php artisan --version
php artisan env
php artisan config:show app

# Check extensions
php -m

# Check Laravel status
php artisan migrate:status
php artisan route:list
php artisan about

# Health check
make health
```

---

**Version**: 1.0
**Last Updated**: October 30, 2025
**Maintainer**: COPRRA Development Team
