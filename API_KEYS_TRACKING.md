# 🔑 API Keys Tracking - COPRRA

**Last Updated:** December 1, 2025

---

## 📋 Current API Keys

### 1. Fixer.io - Currency Exchange Rates

| Field | Value |
|-------|-------|
| **Environment Variable** | `FIXER_API_KEY` |
| **Dashboard** | https://fixer.io/dashboard |
| **Current Status** | ⚠️ **NEEDS ROTATION** (exposed in Git) |
| **Last Rotation** | Unknown |
| **Next Rotation Due** | IMMEDIATE |

**Rotation History:**
| Date | Action | Reason | Rotated By |
|------|--------|--------|------------|
| 2025-12-01 | FLAGGED | Exposed in Git history | Security Scan |
| TBD | TO ROTATE | Security incident | Pending |

**Notes:** 16 keys found in Git history

---

### 2. ExchangeRatesAPI - Currency Exchange

| Field | Value |
|-------|-------|
| **Environment Variable** | `EXCHANGERATES_API_KEY` |
| **Dashboard** | https://exchangeratesapi.io/dashboard |
| **Current Status** | ⚠️ **NEEDS ROTATION** (exposed in Git) |
| **Last Rotation** | Unknown |
| **Next Rotation Due** | IMMEDIATE |

**Rotation History:**
| Date | Action | Reason | Rotated By |
|------|--------|--------|------------|
| 2025-12-01 | FLAGGED | Exposed in Git history | Security Scan |
| TBD | TO ROTATE | Security incident | Pending |

**Notes:** 32 keys found in Git history

---

## 🔐 Other Credentials

### 3. DKIM Private Key - Email Signing

| Field | Value |
|-------|-------|
| **Type** | RSA Private Key (2048-bit) |
| **Selector** | default |
| **Domain** | coprra.com |
| **DNS Record** | default._domainkey.coprra.com |
| **Current Status** | 🔴 **EXPOSED - IMMEDIATE ROTATION** |
| **Location (OLD)** | ❌ `dkim_keys/coprra.com/default.private` |
| **Location (NEW)** | ✅ Outside repository (to be moved) |

**Rotation History:**
| Date | Action | Reason | Rotated By |
|------|--------|--------|------------|
| 2025-12-01 | FLAGGED | Exposed in Git | Security Scan |
| TBD | TO ROTATE | Security incident | Pending |

---

## 🔄 Rotation Schedule

### Upcoming Rotations

| Service | Due Date | Priority |
|---------|----------|----------|
| DKIM Key | **IMMEDIATE** | 🔴 Critical |
| Fixer.io | **IMMEDIATE** | 🔴 Critical |
| ExchangeRatesAPI | **IMMEDIATE** | 🔴 Critical |

### Regular Schedule (After Incident)

| Service | Frequency | Next Due |
|---------|-----------|----------|
| API Keys | 90 days | 2025-03-01 |
| Database Passwords | 180 days | 2025-06-01 |
| DKIM Keys | 365 days | 2026-12-01 |

---

## 📝 Rotation Procedures

### Rotating an API Key:

1. Login to service dashboard
2. Generate new key
3. Test in staging: `php artisan config:clear`
4. Update production `.env`
5. Clear cache
6. Revoke old key (after 24-48 hours)
7. Document rotation in table above

---

## 🚨 Emergency Procedures

If API key is compromised:
1. Login to dashboard (within 15 minutes)
2. Revoke key immediately
3. Generate new key
4. Update production
5. Document incident

---

## 📚 References

- **SECURITY.md** - Full security policy
- **MANUAL_GUIDE_API_KEYS_REVIEW.md** - Rotation guide
- **containment-phase-report.md** - Incident details

---

**Keep this updated with every key rotation!**

