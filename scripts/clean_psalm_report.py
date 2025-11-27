#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script to remove fixed entries from Psalm report.
Removes entries for verified fixed files.
"""

import re
import sys
from pathlib import Path

# Get the project root directory
project_root = Path(__file__).parent.parent
report_path = project_root / "تقارير المشكلات والاخطاء بالاختبارات" / "2-psalm-report.txt"

# List of verified fixed files (patterns to match)
fixed_files = [
    r"AgentProposeFixCommand\.php",
    r"AnalyzeDatabaseCommand\.php",
    r"CheckPriceAlerts\.php",
    r"DbIntegrityCheck\.php",
    r"EnhanceProductDescriptions\.php",
    r"CacheManagement\.php",
]

def remove_file_entries(content, file_pattern):
    """Remove all error entries for a specific file pattern."""
    # Pattern to match ERROR line containing the file pattern
    # Match from ERROR line until next ERROR line (starting with [0;31mERROR[0m:) or end
    # Use non-greedy matching to stop at next ERROR
    pattern = rf'(?s)\[0;31mERROR\[0m:[^\n]*{file_pattern}[^\n]*\n(?:[^\n]*\n)*?(?=\[0;31mERROR\[0m:|$)'
    matches = re.findall(pattern, content)
    if matches:
        print(f"  Found {len(matches)} matches for {file_pattern}")
    return re.sub(pattern, '', content)

def main():
    if not report_path.exists():
        print("Report not found")
        sys.exit(1)
    
    print("Reading report...")
    # Try different encodings
    encodings = ['utf-8', 'utf-8-sig', 'latin-1', 'cp1252']
    content = None
    for encoding in encodings:
        try:
            with open(report_path, 'r', encoding=encoding) as f:
                content = f.read()
            print(f"Successfully read with {encoding} encoding")
            break
        except UnicodeDecodeError:
            continue
    
    if content is None:
        print("Failed to read report with any encoding")
        sys.exit(1)
    
    original_length = len(content)
    print(f"Original report size: {original_length} characters")
    
    # Remove entries for each fixed file
    for file_pattern in fixed_files:
        before = len(content)
        content = remove_file_entries(content, file_pattern)
        after = len(content)
        removed = before - after
        if removed > 0:
            print(f"Removed {removed} characters for {file_pattern}")
    
    # Clean up multiple consecutive empty lines
    content = re.sub(r'(\r?\n){3,}', '\n\n', content)
    
    # Write back
    with open(report_path, 'w', encoding='utf-8') as f:
        f.write(content)
    
    final_length = len(content)
    removed_total = original_length - final_length
    print(f"Final report size: {final_length} characters")
    print(f"Total removed: {removed_total} characters")
    print("Done!")

if __name__ == '__main__':
    main()

