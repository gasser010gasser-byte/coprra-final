#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Remove fixed entries from Psalm report.
"""

import re
from pathlib import Path

# Get project root
project_root = Path(__file__).parent.parent
report_path = project_root / "تقارير المشكلات والاخطاء بالاختبارات" / "2-psalm-report.txt"

# Verified fixed files
fixed_files = [
    "AgentProposeFixCommand.php",
    "AnalyzeDatabaseCommand.php", 
    "CheckPriceAlerts.php",
    "DbIntegrityCheck.php",
    "EnhanceProductDescriptions.php",
    "CacheManagement.php",
]

def main():
    print("Reading report...")
    
    # Try different encodings
    encodings = ['utf-8', 'latin-1', 'cp1252']
    content = None
    
    for encoding in encodings:
        try:
            with open(report_path, 'r', encoding=encoding, errors='ignore') as f:
                content = f.read()
            print(f"Read with {encoding} encoding")
            break
        except Exception as e:
            print(f"Failed with {encoding}: {e}")
            continue
    
    if content is None:
        print("Could not read file")
        return
    
    original_len = len(content)
    print(f"Original size: {original_len} characters")
    
    # Remove entries for each fixed file
    for file_name in fixed_files:
        # Try multiple patterns
        patterns = [
            # Pattern 1: Match ERROR line with file name anywhere
            rf'(?s)\[0;31mERROR\[0m:.*?{re.escape(file_name)}.*?(?=\[0;31mERROR\[0m:|$)',
            # Pattern 2: Match with file path
            rf'(?s)ERROR.*?{re.escape(file_name)}.*?(?=ERROR|$)',
            # Pattern 3: Simple file name match
            rf'.*?{re.escape(file_name)}.*?\n.*?(?=\[0;31mERROR\[0m:|ERROR|$)',
        ]
        
        before = len(content)
        for i, pattern in enumerate(patterns):
            content_new = re.sub(pattern, '', content, flags=re.MULTILINE | re.DOTALL)
            if len(content_new) < len(content):
                content = content_new
                print(f"  Pattern {i+1} worked for {file_name}")
                break
        
        after = len(content)
        removed = before - after
        
        if removed > 0:
            print(f"Removed {removed} chars for {file_name}")
        else:
            # Try to find the file name in content
            if file_name in content:
                print(f"  Found {file_name} in content but pattern didn't match")
            else:
                print(f"  {file_name} not found in content")
    
    # Clean multiple empty lines
    content = re.sub(r'\n{3,}', '\n\n', content)
    
    # Write back
    with open(report_path, 'w', encoding='utf-8') as f:
        f.write(content)
    
    final_len = len(content)
    total_removed = original_len - final_len
    print(f"Final size: {final_len} characters")
    print(f"Total removed: {total_removed} characters")
    print("Done!")

if __name__ == '__main__':
    main()

