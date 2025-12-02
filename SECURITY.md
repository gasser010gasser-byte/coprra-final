# 🛡️ Security Policy - COPRRA Project

**Last Updated:** December 1, 2025  
**Version:** 1.0

---

## 🚨 Reporting Vulnerabilities

**DO NOT** open public issues for security vulnerabilities.

**Instead, email:** security@coprra.com

**Include:**
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

**Response Time:**
- Acknowledgment: Within 24 hours
- Initial Assessment: Within 48 hours
- Resolution Target: Critical issues within 30 days

---

## 🔐 Secrets Management

### ✅ ALLOWED:
- Environment variables: `config('services.fixer.api_key')`
- Secret management tools (Vault, AWS Secrets Manager)
- Secure file storage outside repository

### ❌ NEVER:
- Hardcoded in source code
- Committed to Git (no `.env`, `.pem`, `.key` files)
- In comments or documentation

---

## 🔄 Key Rotation Schedule

| Secret Type | Rotation Frequency |
|-------------|-------------------|
| API Keys | Every 90 days |
| Database Passwords | Every 180 days |
| DKIM Keys | Every 365 days |
| SSL Certificates | Before expiry |

### Emergency Rotation
Rotate immediately if:
- Key exposed in Git history
- Suspected compromise
- Team member with access leaves
- Security audit recommends it

---

## 👨‍💻 Developer Guidelines

### Before You Commit:
```bash
# Check what you're committing
git status
git diff --cached

# Scan for secrets
git diff --cached | grep -i -E '(password|secret|token|api_key)'
```

### Pre-commit Hook
Automatically checks for:
- ✅ Private key files
- ✅ Private key patterns in code
- ✅ `.env` files
- ✅ DKIM keys

**Don't bypass it!** Fix the issue instead.

---

## 🆘 Incident Response

### If You Discover a Leaked Secret:

1. **STOP** - Don't commit/push further
2. **ASSESS** - What was leaked? Where? For how long?
3. **CONTAIN** (Within 1 hour):
   - Revoke the exposed secret immediately
   - Generate new secret
   - Remove from Git if not pushed
4. **REPLACE** - Update production with new secret
5. **REVIEW** - How did it happen? How to prevent?

---

## 📚 Resources

- **TruffleHog:** https://github.com/trufflesecurity/trufflehog
- **OWASP Secrets Management:** https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html
- **Laravel Security:** https://laravel.com/docs/security

---

## ✅ Security Checklist

### Daily:
- [ ] Review commit messages for secrets

### Monthly:
- [ ] Review `.env` changes
- [ ] Check for security updates

### Quarterly:
- [ ] Rotate API keys
- [ ] Run TruffleHog scan

### Annually:
- [ ] Rotate DKIM keys
- [ ] Full security audit

---

**All team members are responsible for security.**

**Contact:** security@coprra.com

