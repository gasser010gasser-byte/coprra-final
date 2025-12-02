#!/bin/bash
# COPRRA Security Secrets Scanner
# Usage: ./scripts/check-secrets.sh [directory]

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

ISSUES_FOUND=0
WARNINGS_FOUND=0

echo -e "${BLUE}═══════════════════════════════════════════════════${NC}"
echo -e "${BLUE}     COPRRA Security Secrets Scanner${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════${NC}"
echo ""

TARGET="${1:-.}"
echo -e "${BLUE}📁 Scanning:${NC} $TARGET"
echo ""

# CHECK 1: .env files
echo -e "${BLUE}🔍 Check 1: Looking for .env files...${NC}"
ENV_FILES=$(find "$TARGET" -name ".env" -o -name ".env.*" ! -name ".env.example" ! -name ".env.production.template" 2>/dev/null || true)

if [ -n "$ENV_FILES" ]; then
  echo -e "${RED}❌ CRITICAL: .env files found!${NC}"
  echo "$ENV_FILES"
  ((ISSUES_FOUND++))
else
  echo -e "${GREEN}✅ No .env files found${NC}"
fi
echo ""

# CHECK 2: Private key files
echo -e "${BLUE}🔍 Check 2: Looking for private key files...${NC}"
KEY_FILES=$(find "$TARGET" -type f \( -name "*.pem" -o -name "*.key" -o -name "*.private" -o -name "*.p12" -o -name "*.pfx" -o -name "*.ppk" \) 2>/dev/null || true)

if [ -n "$KEY_FILES" ]; then
  echo -e "${RED}❌ CRITICAL: Private key files found!${NC}"
  echo "$KEY_FILES"
  ((ISSUES_FOUND++))
else
  echo -e "${GREEN}✅ No private key files found${NC}"
fi
echo ""

# CHECK 3: DKIM keys
echo -e "${BLUE}🔍 Check 3: Looking for DKIM keys...${NC}"
DKIM_FILES=$(find "$TARGET" -path "*/dkim_keys/*" -type f 2>/dev/null || true)

if [ -n "$DKIM_FILES" ]; then
  echo -e "${RED}❌ CRITICAL: DKIM key files found!${NC}"
  echo "$DKIM_FILES"
  ((ISSUES_FOUND++))
else
  echo -e "${GREEN}✅ No DKIM key files found${NC}"
fi
echo ""

# CHECK 4: Private key patterns in files
echo -e "${BLUE}🔍 Check 4: Scanning for private key patterns...${NC}"
PRIVATE_KEY_MATCHES=$(grep -r "BEGIN.*PRIVATE KEY" "$TARGET" --include="*.php" --include="*.js" --exclude-dir=vendor --exclude-dir=node_modules 2>/dev/null || true)

if [ -n "$PRIVATE_KEY_MATCHES" ]; then
  echo -e "${RED}❌ CRITICAL: Private key patterns found!${NC}"
  echo "$PRIVATE_KEY_MATCHES" | head -3
  ((ISSUES_FOUND++))
else
  echo -e "${GREEN}✅ No private key patterns found${NC}"
fi
echo ""

# SUMMARY
echo -e "${BLUE}═══════════════════════════════════════════════════${NC}"
echo -e "${BLUE}              SCAN SUMMARY${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════${NC}"
echo ""

if [ $ISSUES_FOUND -eq 0 ]; then
  echo -e "${GREEN}✅ SUCCESS: No security issues found!${NC}"
  exit 0
else
  echo -e "${RED}❌ CRITICAL: $ISSUES_FOUND issue(s) found!${NC}"
  echo -e "${RED}   DO NOT COMMIT until resolved!${NC}"
  echo ""
  echo -e "${BLUE}📚 Resources:${NC}"
  echo -e "   - SECURITY.md"
  echo -e "   - MANUAL_GUIDE_DKIM_ROTATION.md"
  exit 1
fi

