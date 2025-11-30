# env() Usage Fixes Proposal

## Summary
- **Total Errors Found:** 16
- **Files Affected:** 3
- **Priority:** High (Production breaking issue)

## Detailed Fix Table

| # | File | Line | Problematic Code | Proposed Fix | Config Key |
|---|------|------|------------------|--------------|------------|
| 1 | `app/Services/AI/Services/AIRequestService.php` | 64 | `env('AI_DISABLE_EXTERNAL_CALLS')` | `config('ai.disable_external_calls')` | `config/ai.php` |
| 2 | `app/Services/EnvironmentChecker.php` | 247 | `env($var)` in loop | Keep as-is (checking env vars) | N/A - Special case |
| 3 | `app/Services/EnvironmentChecker.php` | 295 | `env('DB_HOST', '127.0.0.1')` | `config('database.connections.mysql.host', '127.0.0.1')` | `config/database.php` |
| 4 | `app/Services/EnvironmentChecker.php` | 296 | `env('DB_PORT', '3306')` | `config('database.connections.mysql.port', '3306')` | `config/database.php` |
| 5 | `app/Services/EnvironmentChecker.php` | 297 | `env('DB_DATABASE', 'forge')` | `config('database.connections.mysql.database', 'forge')` | `config/database.php` |
| 6 | `app/Services/EnvironmentChecker.php` | 300 | `env('DB_USERNAME')` | `config('database.connections.mysql.username')` | `config/database.php` |
| 7 | `app/Services/EnvironmentChecker.php` | 300 | `env('DB_PASSWORD')` | `config('database.connections.mysql.password')` | `config/database.php` |
| 8 | `app/Services/EnvironmentChecker.php` | 314 | `env('CACHE_DRIVER', 'file')` | `config('cache.default', 'file')` | `config/cache.php` |
| 9 | `app/Services/EnvironmentChecker.php` | 335 | `env('REDIS_HOST', '127.0.0.1')` | `config('database.redis.default.host', '127.0.0.1')` | `config/database.php` |
| 10 | `app/Services/EnvironmentChecker.php` | 335 | `env('REDIS_PORT', '6379')` | `config('database.redis.default.port', '6379')` | `config/database.php` |
| 11 | `app/Services/EnvironmentChecker.php` | 353 | `env('MEMCACHED_HOST', '127.0.0.1')` | `config('cache.stores.memcached.servers.0.host', '127.0.0.1')` | `config/cache.php` |
| 12 | `app/Services/EnvironmentChecker.php` | 353 | `env('MEMCACHED_PORT', '11211')` | `config('cache.stores.memcached.servers.0.port', '11211')` | `config/cache.php` |
| 13 | `app/Services/EnvironmentChecker.php` | 382 | `env('QUEUE_CONNECTION', 'sync')` | `config('queue.default', 'sync')` | `config/queue.php` |
| 14 | `app/Services/EnvironmentChecker.php` | 402 | `env('REDIS_HOST', '127.0.0.1')` | `config('database.redis.default.host', '127.0.0.1')` | `config/database.php` |
| 15 | `app/Services/EnvironmentChecker.php` | 402 | `env('REDIS_PORT', '6379')` | `config('database.redis.default.port', '6379')` | `config/database.php` |
| 16 | `app/Services/Security/VirusScanner.php` | 37 | `env('CLAMAV_PATH', 'clamscan')` | `config('services.clamav.path', 'clamscan')` | `config/services.php` (needs addition) |

## Special Cases

### Line 247 in EnvironmentChecker.php
The `env($var)` call in the loop at line 247 is used to check if environment variables are set. This is a special case where we're checking the actual environment file, not using the value. This should remain as `env()` since it's checking the raw environment, not using configuration.

**Recommendation:** Keep as-is or add a comment explaining why `env()` is acceptable here.

## Implementation Notes

### 1. AI_DISABLE_EXTERNAL_CALLS
- **Current:** `env('AI_DISABLE_EXTERNAL_CALLS')` at line 64
- **Fix:** `config('ai.disable_external_calls')`
- **Note:** The config already exists in `config/ai.php` and is already being used at line 62. The `env()` call at line 64 appears to be redundant and can be removed entirely.

### 2. Database Configuration
- All database-related `env()` calls should use `config('database.connections.mysql.*')`
- Ensure default values match Laravel's defaults

### 3. Cache Configuration
- `CACHE_DRIVER` → `config('cache.default')`
- `MEMCACHED_HOST` and `MEMCACHED_PORT` → `config('cache.stores.memcached.servers.0.*')`

### 4. Queue Configuration
- `QUEUE_CONNECTION` → `config('queue.default')`

### 5. Redis Configuration
- `REDIS_HOST` and `REDIS_PORT` → `config('database.redis.default.*')`

### 6. CLAMAV_PATH
- **Action Required:** Add to `config/services.php`:
  ```php
  'clamav' => [
      'path' => env('CLAMAV_PATH', 'clamscan'),
  ],
  ```
- Then use: `config('services.clamav.path', 'clamscan')`

## Files Requiring Config Additions

1. **config/services.php** - Add ClamAV configuration:
   ```php
   'clamav' => [
       'path' => env('CLAMAV_PATH', 'clamscan'),
   ],
   ```

## Testing Recommendations

After applying fixes:
1. Clear config cache: `php artisan config:clear`
2. Test each service that was modified:
   - AI service external calls
   - Database connections
   - Cache operations
   - Queue operations
   - Redis connections
   - Memcached connections
   - Virus scanning

## Priority Order

1. **High Priority (Production Breaking):**
   - Database connection (lines 295-300)
   - Cache driver (line 314)
   - Queue connection (line 382)

2. **Medium Priority:**
   - Redis connections (lines 335, 402)
   - Memcached connection (line 353)

3. **Low Priority:**
   - AI service (line 64 - appears redundant)
   - ClamAV path (line 37 - optional feature)

