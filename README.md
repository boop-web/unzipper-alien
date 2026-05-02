# ðŸ›¸ Unzipper - Alien Edition

A modern, web-based archive manager with an otherworldly twist. Extract and create ZIP, RAR, and GZ archives through an advanced alien-themed GUI powered by Bootstrap 5 and Three.js.

## ðŸ’­ Why I Made This

This is a tool that I needed for most of my cases. I was always going to GitHub to find one, or sometimes I couldn't even remember the name because I was too lazy to:
- Write the code myself
- Save it properly
- Keep it bookmarked

So I made this copy for myself, and then thought: *"Why not share it with everybody?"* 

If anybody is in need or searching for a quick unzip solution, here it is. No more hunting through GitHub repos or trying to remember that one tool you used months ago.

## âœ¨ Features

- **ðŸŒŒ Alien-Themed UI**: Dark futuristic interface with neon accents and 3D particle effects
- **ðŸ“¦ Archive Extraction**: Extract `.zip`, `.rar`, and `.gz` files
- **ðŸ“¤ File Upload**: Upload and extract archives directly in the browser
- **ðŸ“ Archive Creation**: Create `.zip` archives from directories
- **ðŸ” Password Protection**: Optional password protection with AES-256 encryption
- **ðŸ’¾ Backup Function**: One-click backup of entire directory
- **â¬‡ï¸ Download Manager**: Download any archive file directly from the interface
- **ðŸŽ¨ Modern Design**: Built with Bootstrap 5, Three.js, and custom CSS
- **ðŸ“± Responsive**: Works on desktop and mobile devices
- **âš¡ Real-time Feedback**: Visual status updates with processing time

## ðŸš€ Installation

### Prerequisites

- PHP 7.0 or higher
- Web server (Apache, Nginx, or PHP built-in server)
- PHP extensions: `zip`, `zlib` (for GZ), `rar` (optional, for RAR files)
- PHP `openssl` extension (for password encryption)

### Quick Start

1. **Clone the repository**
   ```bash
   git clone https://github.com/boop-web/unzipper-alien.git
   cd unzipper-alien
   ```

2. **Deploy to your web server**
   ```bash
   # Copy to your web server directory
   cp -r . /var/www/html/unzipper/
   
   # Or use PHP built-in server for testing
   php -S localhost:8000
   ```

3. **Access via browser**
   ```
   http://localhost:8000/unzipper.php
   ```

## ðŸ“– Usage

### Extracting Archives

1. Upload your `.zip`, `.rar`, or `.gz` file to the server (or use the upload field)
2. Select the archive from the dropdown menu (if already on server)
3. (Optional) Specify extraction path
4. Click "Unzip Archive"

### Creating Archives

1. Enter the path to the directory you want to zip
2. Click "Zip Archive"
3. The archive will be created with timestamp (e.g., `zipper-2026-05-02--14-30.zip`)

### Quick Backup

1. Click "Backup Now" to create a complete backup of the current directory
2. The backup will be saved as `backup-YYYY-MM-DD--HH-II-SS.zip`
3. Download the backup from the Download Archives section

### Password Protection

1. On first run, set a password to protect the tool
2. The password is encrypted using AES-256-CBC and stored in `.unzipper_auth`
3. Next time you access the tool, you'll need to enter the password
4. Logout/Lock the tool when done

### Downloading Files

- All archive files in the directory are listed in the "Download Archives" section
- Click any file to download it directly

## ðŸŽ¨ Customization

### Changing Colors

Edit the CSS variables in `unzipper.php`:

```css
:root {
    --bg-primary: #0a0a0f;
    --accent-primary: #00ffaa;
    --accent-secondary: #ff00ff;
    --accent-tertiary: #00ffff;
}
```

### Disabling 3D Effects

Remove or comment out the Three.js script section in the HTML footer.

## ðŸ› ï¸ Technical Details

### PHP Classes

- **Unzipper**: Handles archive extraction
  - `extractZipArchive()` - Extracts ZIP files
  - `extractGzipFile()` - Extracts GZ files (including tar.gz)
  - `extractRarArchive()` - Extracts RAR files

- **Zipper**: Handles archive creation
  - `zipDir()` - Creates ZIP from directory

### Security Features

- **Password Protection**: AES-256-CBC encryption for stored passwords
- **File Upload Validation**: Only allows `.zip`, `.rar`, `.gz` files
- **Input Sanitization**: Uses `strip_tags()` and `basename()` for security
- **Session Management**: Password-protected access with session handling
- **File Access Control**: Only allows downloading archive files

### Password Encryption

- Uses PHP's `openssl_encrypt()` with AES-256-CBC
- Encryption key derived from SHA-256 hash
- IV (Initialization Vector) stored with encrypted data
- Password stored in `.unzipper_auth` file (keep this secure!)

## ðŸŒŸ Credits

- **Original Author**: Andreas Tasch (at[tec])
- **Alien UI Enhancement**: Modern redesign with Bootstrap 5 & Three.js
- **Password & Backup Features**: Added in v2.2.0
- **License**: GNU GPL v3

## ðŸ“ Changelog

### Version 2.2.0 - Security & Backup Edition
- Added password protection with AES-256 encryption
- Implemented login system with session management
- Added one-click backup functionality
- Added download manager for archive files
- Users can now backup entire directories quickly
- Password stored with strong encryption in `.unzipper_auth`
- Enhanced security for multi-user environments

### Version 2.1.0 - Upload Feature
- Added file upload capability for direct archive extraction
- Users can now upload and extract in one step
- No need to upload via FTP/SFTP separately
- Updated form to support `multipart/form-data`

### Version 2.0.0 - Alien Edition
- Complete UI overhaul with alien/dark theme
- Added Bootstrap 5 integration
- Integrated Three.js for 3D background effects
- Added scan line animation
- Enhanced form styling and interactions
- Improved responsive design

### Version 0.1.1
- Bug fixes and improvements

### Version 0.1.0
- Initial release
- ZIP extraction and creation
- RAR and GZ support

## âš ï¸ Disclaimer

This tool is intended for legitimate file management purposes. Ensure you have permission to extract/create archives on the server. The author is not responsible for misuse.

**Security Note**: When using password protection, keep the `.unzipper_auth` file secure. This tool is not a replacement for proper server security - use additional measures like `.htaccess` restrictions in production.

## ðŸ› Issues & Contributions

Report issues or contribute at: [GitHub Issues](https://github.com/boop-web/unzipper-alien/issues)

---

**Made with ðŸ’š and alien technology**

*Drop this script in any folder that needs archiving, and you're good to go!*
