#!/usr/bin/env node

/**
 * Check PHPStan configuration rules
 * This script validates phpstan.neon configuration file
 */

import { readFileSync } from 'fs';
import { exit } from 'process';

try {
  const content = readFileSync('phpstan.neon', 'utf8');
  
  console.log('✓ Checking phpstan.neon configuration...');
  
  // Basic validation checks
  if (content.trim().length === 0) {
    console.error('✗ Error: phpstan.neon is empty');
    exit(1);
  }
  
  // Check for common configuration sections
  const hasParameters = content.includes('parameters:');
  const hasPaths = content.includes('paths:') || content.includes('scanDirectories:');
  
  if (!hasParameters) {
    console.warn('⚠ Warning: No parameters section found in phpstan.neon');
  }
  
  if (!hasPaths) {
    console.warn('⚠ Warning: No paths/scanDirectories section found in phpstan.neon');
  }
  
  // Check for level configuration
  if (!content.includes('level:')) {
    console.warn('⚠ Warning: No level specified in phpstan.neon');
  }
  
  console.log('✓ phpstan.neon validation passed');
  exit(0);
  
} catch (error) {
  console.error('✗ Error reading phpstan.neon:', error.message);
  exit(1);
}

