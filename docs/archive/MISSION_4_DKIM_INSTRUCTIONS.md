# Mission 4: DKIM Configuration Instructions

## Status: Partial Complete

**Findings:**
- ✅ Mail server detected: **Exim**
- ❌ OpenDKIM tools not installed (requires root access)
- ❌ DKIM keys not generated (requires root or OpenDKIM tools)

## Solution: Manual DKIM Setup

Since root access is required for DKIM configuration, here are the complete instructions:

### Option 1: Generate Keys Locally (Recommended)

1. **Generate DKIM keys locally** using the provided script:
   ```bash
   bash generate_dkim_keys.sh
   ```

2. **Upload private key to server:**
   ```bash
   scp -P 65002 dkim_keys/coprra.com/default.private u990109832@45.87.81.218:/tmp/
   ```

3. **On server (with root access):**
   ```bash
   sudo mkdir -p /etc/opendkim/keys/coprra.com
   sudo mv /tmp/default.private /etc/opendkim/keys/coprra.com/
   sudo chown opendkim:opendkim /etc/opendkim/keys/coprra.com/default.private
   sudo chmod 600 /etc/opendkim/keys/coprra.com/default.private
   ```

### Option 2: Use Online DKIM Generator

1. Visit: https://www.dnswatch.info/dkim/create
2. Enter domain: `coprra.com`
3. Selector: `default`
4. Download keys
5. Follow upload and configuration steps

### DNS Configuration

Add this TXT record to your DNS provider:

```
Type: TXT
Name: default._domainkey.coprra.com
TTL: 3600
Value: [Generated from keys - see output above]
```

### Exim Configuration

Add to `/etc/exim4/exim4.conf`:

```exim
# DKIM Configuration
dkim_private_key = /etc/opendkim/keys/coprra.com/default.private
dkim_selector = default
dkim_domain = coprra.com
```

Then restart Exim:
```bash
sudo systemctl restart exim4
```

### Verification

After DNS propagation (usually 5-60 minutes), test DKIM:

```bash
# Test DNS record
dig +short TXT default._domainkey.coprra.com

# Test email signing (send test email)
# Check email headers for: DKIM-Signature
```

## Current Status

✅ **Completed:**
- Mail server identified (Exim)
- DKIM key generation script created
- Complete instructions documented

⏳ **Pending (Requires Root Access):**
- Install OpenDKIM tools
- Generate keys on server
- Configure Exim
- Add DNS record

## Recommendation

Since root access is required, this mission should be completed by:
1. Server administrator with root access, OR
2. Using hosting control panel (if available), OR
3. Contacting hosting support (Hostinger) for DKIM setup

