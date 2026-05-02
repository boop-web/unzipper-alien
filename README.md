# ðŸ›¸ Unzipper - Alien Edition

A modern, web-based archive manager with an otherworldly twist. Extract and create ZIP, RAR, and GZ archives through an advanced alien-themed GUI powered by Bootstrap 5 and Three.js.

![Version](https://img.shields.io/badge/version-2.0.0-blue)
![PHP](https://img.shields.io/badge/PHP-7.0+-purple)
![License](https://img.shields.io/badge/license-GPL%20v3-green)

## âœ¨ Features

- **ðŸŒŒ Alien-Themed UI**: Dark futuristic interface with neon accents and 3D particle effects
- **ðŸ“¦ Archive Extraction**: Extract `.zip`, `.rar`, and `.gz` files
- **ðŸ“ Archive Creation**: Create `.zip` archives from directories
- **ðŸŽ¨ Modern Design**: Built with Bootstrap 5, Three.js, and custom CSS
- **ðŸ“± Responsive**: Works on desktop and mobile devices
- **âš¡ Real-time Feedback**: Visual status updates with processing time

## ðŸš€ Installation

### Prerequisites

- PHP 7.0 or higher
- Web server (Apache, Nginx, or PHP built-in server)
- PHP extensions: `zip`, `zlib` (for GZ), `rar` (optional, for RAR files)

### Quick Start

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/unzipper-alien.git
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

1. Upload your `.zip`, `.rar`, or `.gz` file to the server
2. Select the archive from the dropdown menu
3. (Optional) Specify extraction path
4. Click "Unzip Archive"

### Creating Archives

1. Enter the path to the directory you want to zip
2. Click "Zip Archive"
3. The archive will be created with timestamp (e.g., `zipper-2026-05-02--14-30.zip`)

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

### Security Notes

- Only extracts files already present on the server
- Basic input sanitization with `strip_tags()`
- Consider adding authentication for production use
- Restrict access via `.htaccess` or server config

## ðŸŒŸ Credits

- **Original Author**: Andreas Tasch (at[tec])
- **Alien UI Enhancement**: Modern redesign with Bootstrap 5 & Three.js
- **License**: GNU GPL v3

## ðŸ“ Changelog

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

## ðŸ› Issues & Contributions

Report issues or contribute at: [GitHub Issues](https://github.com/yourusername/unzipper-alien/issues)

---

**Made with ðŸ’š and alien technology**
