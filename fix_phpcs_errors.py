#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Fix PHPCS indentation errors systematically.
Processes errors one by one from the report.
"""
import re

report_file = 'تقارير المشكلات والاخطاء بالاختبارات/3-phpcs-report.txt'
changelog_file = 'changelog.md'

def find_next_error():
    """Find the first error in the report."""
    with open(report_file, 'r', encoding='utf-8', errors='ignore') as f:
        lines = f.readlines()
    
    current_file = None
    for i, line in enumerate(lines, 1):
        if line.startswith('FILE:'):
            # Extract file path
            match = re.search(r'FILE:.*\\([^\\]+\.php)', line)
            if match:
                current_file = match.group(1)
        elif 'ERROR' in line and current_file:
            # Parse error line: " 207 | ERROR   | [x] Line indented incorrectly; expected 16 spaces, found 20"
            match = re.search(r'^\s*(\d+)\s*\|\s*ERROR', line)
            if match:
                line_num = int(match.group(1))
                # Extract expected and found spaces
                space_match = re.search(r'expected (\d+) spaces, found (\d+)', line)
                if space_match:
                    expected = int(space_match.group(1))
                    found = int(space_match.group(2))
                    return i, current_file, line_num, expected, found, line.strip()
    return None, None, None, None, None, None

def fix_indentation(file_path, line_num, expected_spaces):
    """Fix indentation for a specific line in a file."""
    try:
        full_path = f'app/Console/Commands/{file_path}'
        with open(full_path, 'r', encoding='utf-8', errors='ignore') as f:
            lines = f.readlines()
        
        if line_num <= len(lines):
            # Replace leading whitespace with correct number of spaces
            line = lines[line_num - 1]
            # Remove all leading whitespace
            content = line.lstrip()
            # Add correct indentation
            lines[line_num - 1] = ' ' * expected_spaces + content
        
        with open(full_path, 'w', encoding='utf-8', errors='ignore') as f:
            f.writelines(lines)
        return True
    except Exception as e:
        print(f"Error fixing {file_path}:{line_num}: {e}")
        return False

def remove_error_from_report(line_num):
    """Remove the error line from report."""
    with open(report_file, 'r', encoding='utf-8', errors='ignore') as f:
        lines = f.readlines()
    
    if line_num <= len(lines):
        result = lines[:line_num-1] + lines[line_num:]
    else:
        result = lines
    
    with open(report_file, 'w', encoding='utf-8', errors='ignore') as f:
        f.writelines(result)

def update_changelog(issue_num, file_path, line_num, issue_desc):
    """Update changelog."""
    try:
        with open(changelog_file, 'r', encoding='utf-8') as f:
            content = f.read()
        
        entry = f"""
### Issue {issue_num}: {file_path} - Line {line_num} Indentation Fix
- **File:** `app/Console/Commands/{file_path}`
- **Issue:** {issue_desc}
- **Fix:** Corrected indentation on line {line_num} to match PSR-12 standards
- **Status:** Fixed
"""
        content = content.rstrip() + entry
        with open(changelog_file, 'w', encoding='utf-8') as f:
            f.write(content)
    except:
        pass

# Process first error
line_num, file_path, code_line, expected, found, error_line = find_next_error()
if line_num:
    print(f"Processing: {file_path}:{code_line} - expected {expected} spaces, found {found}")
    if fix_indentation(file_path, code_line, expected):
        remove_error_from_report(line_num)
        # Get next issue number
        try:
            with open(changelog_file, 'r', encoding='utf-8') as f:
                content = f.read()
            matches = re.findall(r'### Issue (\d+):', content)
            issue_num = max([int(m) for m in matches]) + 1 if matches else 1
        except:
            issue_num = 1
        update_changelog(issue_num, file_path, code_line, error_line)
        print("Fixed!")
    else:
        print("Failed to fix")
else:
    print("No errors found")

