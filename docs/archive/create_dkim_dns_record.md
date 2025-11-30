# DKIM DNS Record Configuration

## DNS TXT Record to Add

**Domain:** coprra.com  
**Record Type:** TXT  
**Name/Host:** `default._domainkey`  
**Full Name:** `default._domainkey.coprra.com`  
**TTL:** 3600 (or default)

**Value:**
```
v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEApmNHsAmJgwbC1Td6UkxaRFpKzJ4IE7wxsTb5rXWvpBcGb7+C5lYxqbnc5RxBxbKXjhT2CqIUqIOxRXa8aXZhYKWj7cG0WuBE5V5q5lKpa75PmLNZKeGqlKAka/foNa3N5Dy+4lD19IBMcXGzt1JLtxM1uU0V+U3WbhK+XJu0+MpsEBycIJvNHNUAh0l7roGhl0t5PzMwqU4BpkvV+y8fOjJ2Fz2o5WyupJHGudEsYyziiODTT7FW1C+3AfwR5OXml3KFZ2SVyxC3gi1nkKgrr2I+C+TLZQbcjd4b9WNK4XrzEgJtH01wzwr4iKNbL8x5N24TjghAL3UUhBelnfox3wIDAQAB
```

## How to Add DNS Record

### Hostinger Control Panel:
1. Login to Hostinger control panel
2. Go to DNS Management
3. Click "Add Record"
4. Select Type: TXT
5. Name: `default._domainkey`
6. Value: Paste the value above
7. TTL: 3600
8. Save

### Other DNS Providers:
1. Login to your DNS provider
2. Navigate to DNS Management
3. Add new TXT record
4. Host: `default._domainkey`
5. Value: Paste the value above
6. Save

## Verification

After adding the DNS record, wait 5-60 minutes for propagation, then verify:

```bash
dig +short TXT default._domainkey.coprra.com
```

Or use online tools:
- https://mxtoolbox.com/dkim.aspx
- https://www.dnswatch.info/dkim/verify

## Exim Configuration (Requires Root)

After DNS is configured, update Exim configuration:

**File:** `/etc/exim4/exim4.conf` or `/etc/exim4/exim4.conf.localmacros`

Add:
```
dkim_private_key = /etc/opendkim/keys/coprra.com/default.private
dkim_selector = default
dkim_domain = coprra.com
```

Then restart Exim:
```bash
sudo systemctl restart exim4
```

## Current Status

✅ DKIM keys generated  
✅ Keys uploaded to server: `/tmp/dkim_keys/`  
✅ Keys copied to: `/home/u990109832/dkim_keys/coprra.com/`  
⏳ DNS record needs to be added  
⏳ Exim configuration needs root access

