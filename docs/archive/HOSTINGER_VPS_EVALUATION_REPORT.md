# Hostinger VPS Hosting Environment Evaluation Report

**Server:** nl-srv-web480.main-hosting.eu  
**IP Address:** 45.87.81.218  
**SSH Port:** 65002  
**Username:** u990109832  
**Evaluation Date:** November 27, 2025  
**Uptime:** 123 days, 7 hours, 51 minutes

---

## 1. System Information

### 1.1 Operating System

**Kernel Information:**
```
Linux nl-srv-web480.main-hosting.eu 5.14.0-570.28.1.el9_6.x86_64
#1 SMP PREEMPT_DYNAMIC Tue Jul 22 07:56:01 EDT 2025
x86_64 GNU/Linux
```

**Kernel Version:** 5.14.0-570.28.1.el9_6.x86_64  
**Architecture:** x86_64  
**Build Date:** July 22, 2025  
**Distribution:** AlmaLinux 9 (based on kernel and build information)

**Note:** This appears to be a **shared hosting environment** with CageFS (Containerized File System) enabled, which provides user isolation. This is evident from:
- Limited system command access
- No sudo privileges
- CageFS paths visible in mount points
- Restricted access to system-level configurations

### 1.2 Host Information

- **Hostname:** nl-srv-web480.main-hosting.eu
- **Location:** Netherlands (as per config)
- **Server Type:** Shared Hosting (not full VPS)
- **Containerization:** CageFS enabled

---

## 2. Hardware Resources

### 2.1 CPU Information

**Processor:**
- **Model:** Intel(R) Xeon(R) Silver 4214 CPU @ 2.20GHz
- **Cores Available:** 48 CPU cores
- **Load Average:** 14.72, 14.07, 13.14 (1, 5, 15 minutes)
  - **Status:** ⚠️ **High load** - System is under significant load

**Note:** The high load average suggests the server is handling substantial traffic or processes.

### 2.2 Memory (RAM)

**Total RAM:** 250 GiB (256 GB)  
**Used:** 154 GiB  
**Free:** 9.9 GiB  
**Shared:** 50 GiB  
**Buff/Cache:** 139 GiB  
**Available:** 95 GiB  

**Memory Usage:** ~62% utilized  
**Status:** ✅ **Adequate** - Sufficient memory available

**Swap:** 0 B (No swap configured)  
**Note:** No swap space configured, which is typical for shared hosting environments.

### 2.3 Disk Storage

**Primary Filesystem (`/dev/sda4`):**
- **Total:** 874 GB
- **Used:** 587 GB
- **Available:** 279 GB
- **Usage:** 68%
- **Mount Point:** `/`

**Temporary Storage (`/dev/sdb1`):**
- **Total:** 7.0 TB
- **Used:** 4.3 TB
- **Available:** 2.7 TB
- **Usage:** 62%
- **Mount Point:** `/tmp`

**Status:** ✅ **Adequate** - Sufficient disk space available

**Additional Mount Points:**
- `/dev/shm`: 126 GB (tmpfs)
- `/dev/shm/lsws`: 20 GB (tmpfs for LiteSpeed Web Server)
- `/run/systemd/journal/dev-log.cagefs`: 51 GB (tmpfs for CageFS)

---

## 3. Software Inventory

### 3.1 Web Server

**Status:** ⚠️ **Not directly accessible via standard commands**

**Findings:**
- `nginx`: ❌ Not found
- `apache2`: ❌ Not found
- `httpd`: ❌ Not found
- LiteSpeed Web Server: ⚠️ **Likely present** (based on `/dev/shm/lsws` mount point)

**Conclusion:** The server appears to be running **LiteSpeed Web Server** (LSWS), which is common on Hostinger shared hosting. However, direct access to web server commands is restricted due to the shared hosting environment.

**Note:** In shared hosting environments, web server management is typically handled by the hosting provider, and users don't have direct access to web server configuration or commands.

### 3.2 Database Server

**MySQL/MariaDB:**
- **Status:** ✅ **Installed**
- **Version:** MariaDB 11.8.3 (client 15.2)
- **Location:** `/usr/bin/mysql` and `/usr/bin/mariadb`
- **Note:** The `mysql` command is deprecated in favor of `mariadb`

**PostgreSQL:**
- **Status:** ❌ **Not installed**
- **Command:** `psql` not found

**Database Access:** Available via command-line tools, but database management is typically handled through Hostinger's control panel (hPanel).

### 3.3 Containerization & Orchestration

**Docker:**
- **Status:** ❌ **Not installed**
- **Command:** `docker` not found
- **Docker Compose:** ❌ Not installed

**Note:** Docker is not available in this shared hosting environment. This is expected, as shared hosting typically doesn't support Docker due to security and resource isolation requirements.

### 3.4 Version Control

**Git:**
- **Status:** ✅ **Installed**
- **Version:** 2.47.3

### 3.5 PHP

**PHP:**
- **Status:** ✅ **Installed**
- **Version:** 8.3.22 (CLI)
- **Build Date:** June 4, 2025
- **Zend Engine:** v4.3.22
- **OPcache:** ✅ Enabled (v8.3.22)

**PHP Extensions Available:**
- ✅ `curl` - HTTP client
- ✅ `gd` - Image processing
- ✅ `mysqli` - MySQL improved extension
- ✅ `mysqlnd` - MySQL native driver
- ✅ `pdo_mysql` - PDO MySQL driver
- ✅ `pdo_sqlite` - PDO SQLite driver
- ✅ `redis` - Redis extension
- ✅ `zip` - ZIP archive support

**PHP Configuration (from config/hostinger.php):**
- Memory Limit: 2048M
- Max Execution Time: 360 seconds
- Upload Max Filesize: 2048M
- Post Max Size: 2048M
- OPcache: Enabled (256M memory, 16229 max files)

**Status:** ✅ **Excellent** - Modern PHP version with all essential extensions

### 3.6 Package Managers

**Composer:**
- **Status:** ✅ **Installed**
- **Version:** 2.8.11 (2025-08-21)
- **PHP Version:** 8.3.22

**Node.js / NPM:**
- **Status:** ❌ **Not installed**
- **Commands:** `node` and `npm` not found

**Note:** Node.js and NPM are not available in this environment. Frontend build processes would need to be performed locally or through CI/CD pipelines.

---

## 4. Security Assessment

### 4.1 Firewall Status

**UFW (Uncomplicated Firewall):**
- **Status:** ❌ Not available (command not found)

**iptables:**
- **Status:** ❌ Not accessible (permission denied or not available)

**firewall-cmd (firewalld):**
- **Status:** ❌ Not available (command not found)

**Conclusion:** Firewall management is handled at the hosting provider level. Users in shared hosting environments typically don't have direct access to firewall configuration, as this is managed by Hostinger's infrastructure team.

**Security Note:** This is standard for shared hosting - the hosting provider manages network-level security, including firewalls, DDoS protection, and intrusion detection.

### 4.2 System Users

**Users with Login Shells:**

| Username | Shell | Purpose |
|----------|-------|---------|
| `mysql` | `/bin/bash` | MariaDB/MySQL system user |
| `root` | `/bin/bash` | System root user (not accessible to hosting account) |
| `apache` | `/bin/bash` | Web server user (legacy, may be for compatibility) |
| `u990109832` | `/bin/bash` | **Primary hosting account user** |

**Current User:**
- **Username:** u990109832
- **UID:** 990109832
- **GID:** 2007607446
- **Groups:** o1007607446

**Security Assessment:** ✅ **Appropriate** - Standard system users present. The hosting account user has appropriate isolation.

### 4.3 System Updates

**Update Management:**
- **Status:** ⚠️ **Not accessible** (sudo privileges required)

**Note:** System updates are managed by Hostinger's infrastructure team. Shared hosting customers typically don't have access to system-level package management tools like `apt-get` or `yum`.

**Security Updates:** Handled automatically by Hostinger's maintenance team.

---

## 5. Environment Type Analysis

### 5.1 Hosting Model

**Type:** **Shared Hosting** (not a full VPS)

**Evidence:**
1. CageFS (Containerized File System) enabled
2. No sudo/root access
3. Limited system command access
4. Web server managed by provider
5. Firewall managed by provider
6. System updates managed by provider

### 5.2 Resource Allocation

**From Configuration (`config/hostinger.php`):**
- **CPU:** 2 cores (allocated)
- **RAM:** 2GB (allocated)
- **Storage:** 20GB SSD (allocated)

**Actual System Resources:**
- **CPU:** 48 cores (shared across all users)
- **RAM:** 250 GB (shared across all users)
- **Storage:** 874 GB primary + 7 TB temp (shared)

**Note:** The hosting account has access to a portion of the shared server resources, but the exact allocation is managed by Hostinger's resource management system.

---

## 6. Summary & Recommendations

### 6.1 System Health

| Component | Status | Notes |
|-----------|--------|-------|
| **OS** | ✅ Good | AlmaLinux 9, recent kernel |
| **CPU** | ⚠️ High Load | 48 cores, load average 14+ |
| **RAM** | ✅ Adequate | 250 GB total, 95 GB available |
| **Disk** | ✅ Adequate | 279 GB available on primary |
| **PHP** | ✅ Excellent | 8.3.22 with all extensions |
| **Database** | ✅ Available | MariaDB 11.8.3 |
| **Git** | ✅ Installed | Version 2.47.3 |
| **Composer** | ✅ Installed | Version 2.8.11 |

### 6.2 Missing Components

| Component | Status | Impact |
|-----------|--------|--------|
| **Docker** | ❌ Not Available | Cannot run containerized applications |
| **Node.js/NPM** | ❌ Not Available | Cannot run frontend build processes on server |
| **PostgreSQL** | ❌ Not Available | Only MySQL/MariaDB available |
| **Direct Web Server Access** | ⚠️ Restricted | Web server managed by provider |

### 6.3 Key Findings

**Strengths:**
1. ✅ Modern PHP 8.3.22 with all essential extensions
2. ✅ MariaDB 11.8.3 database available
3. ✅ Git and Composer installed
4. ✅ Adequate disk space (279 GB available)
5. ✅ Sufficient RAM (95 GB available)
6. ✅ Redis extension available for caching
7. ✅ OPcache enabled for performance

**Limitations:**
1. ⚠️ Shared hosting environment (not full VPS)
2. ⚠️ No Docker support
3. ⚠️ No Node.js/NPM for frontend builds
4. ⚠️ High CPU load (may affect performance)
5. ⚠️ Limited system-level access (expected for shared hosting)
6. ⚠️ Web server configuration managed by provider

**Security:**
1. ✅ CageFS isolation enabled
2. ✅ Appropriate user permissions
3. ✅ Firewall managed by provider
4. ✅ System updates handled by provider

### 6.4 Recommendations

#### 6.4.1 For Laravel Application Deployment

**Compatible:**
- ✅ PHP 8.3.22 meets Laravel 12 requirements
- ✅ All required PHP extensions available
- ✅ MariaDB available for database
- ✅ Redis available for caching/sessions
- ✅ Composer available for dependency management

**Considerations:**
1. **Frontend Builds:** Build assets locally or via CI/CD before deployment
2. **Docker:** Not available - deploy directly to server
3. **Web Server:** Configure via Hostinger's control panel (hPanel)
4. **Environment:** Use `.env` file for configuration (not Docker Compose)

#### 6.4.2 Performance Optimization

1. **Monitor CPU Load:** Current load average is high (14+). Monitor application performance.
2. **OPcache:** Already enabled - ensure proper configuration
3. **Redis:** Use for caching and sessions to reduce database load
4. **Database Optimization:** Ensure proper indexing and query optimization

#### 6.4.3 Security Best Practices

1. ✅ Keep `.env` file secure (not in version control)
2. ✅ Use strong database passwords
3. ✅ Enable SSL/HTTPS (already configured per config)
4. ✅ Regular application updates via Composer
5. ✅ Monitor application logs for security issues

#### 6.4.4 Upgrade Considerations

If the application requires:
- **Docker support** → Consider upgrading to VPS plan
- **Node.js/NPM on server** → Consider upgrading to VPS plan
- **Full root access** → Consider upgrading to VPS plan
- **Custom web server configuration** → Consider upgrading to VPS plan

**Current Plan Suitability:** ✅ **Suitable for standard Laravel applications** that don't require Docker or Node.js on the server.

---

## 7. Comparison with Local Development Environment

| Component | Local (Docker) | Hostinger (Shared) |
|-----------|---------------|-------------------|
| **PHP** | 8.4.13 | 8.3.22 ✅ Compatible |
| **Database** | MySQL 8.0 (Docker) | MariaDB 11.8.3 ✅ Compatible |
| **Redis** | Available (Docker) | Available ✅ Compatible |
| **Docker** | ✅ Available | ❌ Not Available |
| **Node.js/NPM** | ✅ Available | ❌ Not Available |
| **Web Server** | Nginx (Docker) | LiteSpeed (Provider) |
| **Composer** | ✅ Available | ✅ Available |

**Deployment Strategy:**
- Build frontend assets locally before deployment
- Deploy application files directly (not as Docker containers)
- Configure via `.env` file on server
- Use Hostinger's hPanel for web server configuration

---

## 8. Conclusion

The Hostinger hosting environment is a **shared hosting** setup (not a full VPS) that is **well-suited for Laravel applications** with the following characteristics:

**✅ Suitable For:**
- Standard Laravel/PHP applications
- Applications using MySQL/MariaDB
- Applications using Redis for caching
- Applications with pre-built frontend assets

**⚠️ Not Suitable For:**
- Docker-based deployments
- Applications requiring Node.js/NPM on server
- Applications requiring full root access
- Applications requiring custom web server configuration

**Overall Assessment:** ✅ **Good** - The environment provides all essential components for Laravel deployment, with modern PHP, database, and caching support. The shared hosting model provides managed security and updates, which is appropriate for production deployments.

**Health Score:** 7.5/10
- ✅ Software stack: 9/10
- ✅ Resources: 8/10
- ⚠️ CPU load: 6/10 (high load)
- ✅ Security: 8/10
- ⚠️ Flexibility: 6/10 (shared hosting limitations)

---

**Report Generated:** November 27, 2025  
**Evaluation Type:** Read-only diagnostic  
**Status:** ✅ Complete

