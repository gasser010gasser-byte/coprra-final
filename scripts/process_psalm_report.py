#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Process Psalm report to remove fixed entries.
"""

import re
from pathlib import Path

# Project root
root = Path(__file__).parent.parent
report_file = root / "تقارير المشكلات والاخطاء بالاختبارات" / "2-psalm-report.txt"

# Verified fixed files - simple names to match
fixed_files = [
    "AgentProposeFixCommand.php",
    "AnalyzeDatabaseCommand.php",
    "CheckPriceAlerts.php", 
    "DbIntegrityCheck.php",
    "EnhanceProductDescriptions.php",
    "CacheManagement.php",
]

def process_report():
    print("Processing Psalm report...")
    
    # Read as binary first to handle encoding
    try:
        with open(report_file, 'rb') as f:
            raw_content = f.read()
        print(f"Read {len(raw_content)} bytes")
    except Exception as e:
        print(f"Error reading file: {e}")
        return False
    
    # Try to decode
    content = None
    for encoding in ['utf-8', 'latin-1', 'cp1252']:
        try:
            content = raw_content.decode(encoding, errors='ignore')
            print(f"Decoded with {encoding}")
            break
        except:
            continue
    
    if content is None:
        print("Could not decode file")
        return False
    
    original_size = len(content)
    print(f"Content size: {original_size} characters")
    
    # Count occurrences of each file
    for file_name in fixed_files:
        count = content.count(file_name)
        print(f"  {file_name}: {count} occurrences")
    
    # Remove entries for fixed files
    # Pattern: Match from ERROR line containing file name until next ERROR line
    removed_total = 0
    
    for file_name in fixed_files:
        # Escape for regex
        escaped = re.escape(file_name)
        
        # Multiple patterns to try
        patterns = [
            # Pattern 1: ERROR line with file, then content until next ERROR
            rf'\[0;31mERROR\[0m:[^\n]*{escaped}[^\n]*\n.*?(?=\[0;31mERROR\[0m:|$)',
            # Pattern 2: Any line with ERROR and file name
            rf'.*?ERROR.*?{escaped}.*?\n.*?(?=ERROR|$)',
            # Pattern 3: Simple - lines containing the file
            rf'.*?{escaped}.*?\n',
        ]
        
        for pattern in patterns:
            before = len(content)
            try:
                new_content = re.sub(pattern, '', content, flags=re.MULTILINE | re.DOTALL)
                if len(new_content) < before:
                    content = new_content
                    removed = before - len(new_content)
                    removed_total += removed
                    print(f"  Removed {removed} chars for {file_name} using pattern")
                    break
            except Exception as e:
                print(f"  Pattern error for {file_name}: {e}")
                continue
    
    # Clean multiple empty lines
    content = re.sub(r'\n{3,}', '\n\n', content)
    
    # Write back
    try:
        with open(report_file, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"\nWritten {len(content)} characters")
        print(f"Total removed: {removed_total} characters")
        return True
    except Exception as e:
        print(f"Error writing file: {e}")
        return False

if __name__ == '__main__':
    success = process_report()
    if success:
        print("Done!")
    else:
        print("Failed!")







