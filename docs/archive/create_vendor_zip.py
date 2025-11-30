import zipfile
import os

def create_vendor_zip():
    """Create vendor.zip with correct paths (no nested vendor)"""
    print("Creating vendor.zip with correct paths...")
    
    with zipfile.ZipFile('vendor.zip', 'w', zipfile.ZIP_DEFLATED) as zipf:
        for root, dirs, files in os.walk('vendor'):
            for file in files:
                file_path = os.path.join(root, file)
                # Remove the 'vendor/' prefix to avoid nesting
                relative_path = os.path.relpath(file_path, 'vendor')
                # Convert Windows paths to Unix paths and add vendor/ prefix
                archive_name = 'vendor/' + relative_path.replace('\\', '/')
                print(f"Adding: {archive_name}")
                zipf.write(file_path, archive_name)
    
    print("✅ vendor.zip created successfully")
    
    # Verify the zip contents
    print("\nVerifying zip contents...")
    with zipfile.ZipFile('vendor.zip', 'r') as zipf:
        files = zipf.namelist()
        autoload_found = False
        composer_found = False
        
        for f in files[:10]:  # Show first 10 files
            print(f"  {f}")
            if f == 'vendor/autoload.php':
                autoload_found = True
            if f.startswith('vendor/composer/'):
                composer_found = True
        
        if autoload_found:
            print("✅ vendor/autoload.php found in zip")
        else:
            print("❌ vendor/autoload.php not found in zip")
            
        if composer_found:
            print("✅ vendor/composer/ directory found in zip")
        else:
            print("❌ vendor/composer/ directory not found in zip")
        
        print(f"Total files in zip: {len(files)}")

if __name__ == "__main__":
    create_vendor_zip()