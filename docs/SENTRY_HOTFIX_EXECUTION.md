# Sentry Hotfix Execution Guide

**Date:** 2025-01-27  
**Issue:** `Call to undefined function exec()` error on production  
**Status:** ✅ Fixed locally, ready for production deployment

---

## Problem Summary

The Sentry SDK was attempting to use the `exec()` function to retrieve the git commit hash for release tracking. On Hostinger shared hosting, the `exec()` function is disabled for security reasons, causing the following error:

```
Call to undefined function exec()
```

**Location:** `config/sentry.php` line 9

---

## Solution Applied

### Local Fix (Already Applied)

**File:** `config/sentry.php`

**Changed:**
```php
// Before (line 9):
'release' => env('SENTRY_RELEASE', exec('git log --pretty="%h" -n1 HEAD') ?: null),

// After:
'release' => env('SENTRY_RELEASE', null),
```

**Note:** `send_default_pii` was already set to `false` (line 24), which is correct.

---

## Production Deployment Steps

### Option 1: Automated Script (Recommended)

**SSH into production server:**
```bash
ssh -p 65002 u990109832@45.87.81.218
```

**Navigate to project directory:**
```bash
cd /home/u990109832/domains/coprra.com/public_html
```

**Pull latest changes:**
```bash
git pull origin fix/technical-debt-remediation
# OR if already on main:
git pull origin main
```

**Run the hotfix script:**
```bash
bash scripts/apply-sentry-hotfix-production.sh
```

This script will:
1. ✅ Backup `config/sentry.php`
2. ✅ Remove `exec()` call
3. ✅ Verify `send_default_pii` is false
4. ✅ Run `composer update --no-dev --optimize-autoloader`
5. ✅ Clear all caches

---

### Option 2: Manual Fix

**Step 1: Edit Sentry Config**

```bash
cd /home/u990109832/domains/coprra.com/public_html
nano config/sentry.php
```

**Find line 9 and change:**
```php
// From:
'release' => env('SENTRY_RELEASE', exec('git log --pretty="%h" -n1 HEAD') ?: null),

// To:
'release' => env('SENTRY_RELEASE', null),
```

**Save and exit** (Ctrl+X, then Y, then Enter)

**Step 2: Verify send_default_pii**

Check line 24 should be:
```php
'send_default_pii' => false,
```

**Step 3: Run Composer Update**

```bash
composer update --no-dev --optimize-autoloader
```

**Step 4: Clear Caches**

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## Verification

After applying the fix, verify:

1. **Check Sentry config:**
   ```bash
   grep -n "exec\|send_default_pii" config/sentry.php
   ```
   Should show:
   - No `exec()` calls
   - `send_default_pii => false`

2. **Test Composer:**
   ```bash
   composer --version
   composer update --no-dev --optimize-autoloader --dry-run
   ```

3. **Test Laravel:**
   ```bash
   php artisan --version
   php artisan config:cache
   ```

4. **Check Sentry Integration:**
   - Visit `/debug-sentry` (if `APP_DEBUG=true`)
   - Check Sentry dashboard for test exception

---

## Files Changed

### Local Repository
- ✅ `config/sentry.php` - Removed `exec()` call
- ✅ `scripts/hotfix-sentry-production.sh` - Hotfix script
- ✅ `scripts/apply-sentry-hotfix-production.sh` - Complete execution script

### Production Server
- ⏳ `config/sentry.php` - Needs update (via git pull or manual edit)

---

## Expected Results

After applying the hotfix:

✅ **No more `exec()` errors**  
✅ **Composer update completes successfully**  
✅ **Sentry integration works without git commit hash**  
✅ **All caches cleared and ready**

---

## Rollback (If Needed)

If issues occur, restore from backup:

```bash
# Find backup file
ls -la config/sentry.php.backup.*

# Restore
cp config/sentry.php.backup.YYYYMMDD_HHMMSS config/sentry.php

# Clear cache
php artisan config:clear
```

---

## Notes

- The `exec()` function is disabled on Hostinger shared hosting for security
- Removing git commit hash from release tracking doesn't affect Sentry functionality
- Release can still be set manually via `SENTRY_RELEASE` environment variable if needed
- `send_default_pii => false` is the correct setting for privacy compliance

---

**Status:** ✅ Local fix applied, ready for production deployment  
**Next Step:** Execute hotfix script on production server

