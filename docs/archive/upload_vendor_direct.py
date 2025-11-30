import ftplib
import os
import zipfile

def create_vendor_zip():
    """Create vendor.zip with proper structure"""
    print("Creating vendor.zip with proper structure...")
    
    vendor_dir = 'vendor'
    
    if not os.path.exists(vendor_dir):
        print(f"Error: {vendor_dir} directory not found")
        return False
    
    # Remove old zip if exists
    if os.path.exists('vendor.zip'):
        os.remove('vendor.zip')
        
    with zipfile.ZipFile('vendor.zip', 'w', zipfile.ZIP_DEFLATED) as zipf:
        for root, dirs, files in os.walk(vendor_dir):
            for file in files:
                file_path = os.path.join(root, file)
                # Convert Windows paths to Unix paths for the archive
                archive_name = file_path.replace('\\', '/')
                zipf.write(file_path, archive_name)
                
    # Verify autoload.php is in the zip
    try:
        with zipfile.ZipFile('vendor.zip', 'r') as verify_zip:
            files_in_zip = verify_zip.namelist()
            if 'vendor/autoload.php' in files_in_zip:
                print("✅ vendor/autoload.php found in zip")
                print(f"Total files in zip: {len(files_in_zip)}")
                return True
            else:
                print("❌ vendor/autoload.php NOT found in zip")
                print("Files in zip:", files_in_zip[:10])  # Show first 10 files
                return False
    except Exception as e:
        print(f"Error verifying zip: {e}")
        return False

def upload_to_ftp():
    """Upload vendor.zip to FTP server"""
    try:
        ftp = ftplib.FTP()
        ftp.connect('ftp.coprra.com', 21)
        ftp.login('u990109832.GASSER', 'Hamo1510@Rayan146')
        ftp.set_pasv(True)
        
        print('Uploading vendor.zip...')
        with open('vendor.zip', 'rb') as f:
            ftp.storbinary('STOR vendor.zip', f)
        print('✅ Upload complete')
        
        ftp.quit()
        return True
    except Exception as e:
        print(f"❌ Upload failed: {e}")
        return False

if __name__ == "__main__":
    if create_vendor_zip():
        upload_to_ftp()
    else:
        print("Failed to create vendor.zip")