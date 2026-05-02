# Unzipper - Alien Edition v2.3

A modern, web-based archive manager with an otherworldly twist. Extract and create ZIP, RAR, and GZ archives through an advanced alien-themed GUI powered by Bootstrap 5 and Three.js.

## Why I Made This

This is a tool that I needed for most of my cases. I was always going to GitHub to find one, or sometimes I couldn't even remember the name because I was too lazy to:
- Write the code myself
- Save it properly
- Keep it bookmarked

So I made this copy for myself, and then thought: "Why not share it with everybody?"

If anybody is in need or searching for a quick unzip solution, here it is. No more hunting through GitHub repos or trying to remember that one tool you used months ago.

## Features

- Alien-Themed UI: Dark futuristic interface with neon accents and 3D particle effects
- Archive Extraction: Extract .zip, .rar, and .gz files
- File Upload: Upload and extract archives directly in the browser with progress bar (0-100%)
- Archive Creation: Create .zip archives from directories
- Password Protection: Optional password protection with AES-256 encryption for the tool
- Page Encryption: Lock the entire page with a 6-digit numeric passcode
- Backup Function: One-click backup of entire directory with auto-naming
- Download Manager: Download any archive file directly from the interface
- Modern Design: Built with Bootstrap 5, Three.js, and custom CSS
- Responsive: Works on desktop and mobile devices

## Installation

### Prerequisites
- PHP 7.0 or higher
- Web server (Apache, Nginx, or PHP built-in server)
- PHP extensions: zip, zlib (for GZ), rar (optional)
- PHP openssl extension (for encryption)

### Quick Start
```bash
git clone https://github.com/boop-web/unzipper-alien.git
cd unzipper-alien
cp -r . /var/www/html/unzipper/
# Or use PHP built-in server
php -S localhost:8000
```

## Usage

### Extracting Archives
1. Upload a file using Quick Upload OR select from server
2. Watch progress bar go from 0% to 100%
3. Page auto-reloads after upload
4. Select file from dropdown and click Unzip Archive

### Quick Backup
1. Click "Backup Now" in the Create/Backup section
2. Progress animation shows 0% to 100%
3. Backup saves as: backup_folder_zip_YYYY-MM-DD_HH-II-SS.zip

### Page Encryption (6-Digit Passcode)

What it does:
- Locks the entire page with a 6-digit numeric passcode
- When you visit the page, you must enter the passcode to see the content
- The passcode is encrypted and stored in a lock file

Why I added this:
- I wanted a simple way to lock the page with a numeric code
- No full password setup needed - just 6 digits
- Quick lock/unlock functionality

How to use:
1. Click "Encrypt Page" button at the bottom
2. Enter a 6-digit numeric passcode (e.g., 123456)
3. Confirm the passcode
4. Progress bar shows 0% to 100%
5. Page reloads and shows locked state
6. Next time you open the page, it asks for the 6-digit passcode
7. Enter the correct passcode to decrypt and access
8. Click "Reset Lock" to completely remove the lock

How it works:
- Passcode is hashed with SHA-256 to create an encryption key
- String "LOCKED" is encrypted with AES-256-CBC
- Encrypted data saved to .unzipper_lock file
- On next access, user enters passcode to decrypt
- If decrypted matches "LOCKED", access is granted

## Technical Details

### Security
- Page Encryption: AES-256-CBC with SHA-256 key derivation
- Tool Password: AES-256 encryption for stored passwords
- File Upload Validation: Only allows .zip, .rar, .gz files
- Input Sanitization: Uses strip_tags() and basename()

## Credits

- Author: boop-web
- Alien UI Enhancement: Bootstrap 5 and Three.js
- License: GNU GPL v3

## Changelog

### Version 2.3.0 - Page Encryption Edition
- Added unified page encryption with 6-digit numeric passcode
- Single Encrypt Page button with progress animation
- Decrypt on next visit with passcode verification
- Reset Lock option for emergency removal
- Fixed button interactions and page reloads
- Removed old dual-lock path for simplicity
- Added info text under all buttons

### Version 2.2.0
- Added password protection
- Added one-click backup
- Added download manager

### Version 2.1.0
- Added file upload with progress bar

### Version 2.0.0
- Complete UI overhaul with alien/dark theme

## Disclaimer

This tool is intended for legitimate file management purposes. Ensure you have permission to extract/create archives on the server. The author is not responsible for misuse.

## GitHub

https://github.com/boop-web/unzipper-alien