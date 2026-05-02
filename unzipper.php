<?php
/**
 * The Unzipper extracts .zip or .rar archives and .gz files on webservers.
 * It's handy if you do not have shell access. E.g. if you want to upload a lot
 * of files (php framework or image collection) as an archive to save time.
 *
 * @author  boop-web
 * @license GNU GPL v3
 * @package attec.toolbox
 * @version 2.3.0 - Modern Alien Edition
 */
define('VERSION', '2.3.0');

// Password protection system
define('PASSWORD_FILE', __DIR__ . '/.unzipper_auth');
define('PAGE_LOCK_FILE', __DIR__ . '/.unzipper_lock');
define('ENCRYPTION_METHOD', 'aes-256-cbc');
define('ENCRYPTION_KEY', hash('sha256', 'unzipper-alien-2026'));

function encrypt_password($password) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
    $encrypted = openssl_encrypt($password, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function decrypt_password($encrypted) {
    $data = base64_decode($encrypted);
    $iv_length = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = substr($data, 0, $iv_length);
    $encrypted_password = substr($data, $iv_length);
    return openssl_decrypt($encrypted_password, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
}

function is_password_set() {
    return file_exists(PASSWORD_FILE);
}

function verify_password($password) {
    if (!is_password_set()) return true;
    $stored = file_get_contents(PASSWORD_FILE);
    $decrypted = decrypt_password($stored);
    return $password === $decrypted;
}

function set_password($password) {
    $encrypted = encrypt_password($password);
    return file_put_contents(PASSWORD_FILE, $encrypted) !== false;
}

// Page encryption functions (6-digit passcode)
function is_page_locked() {
    return file_exists(PAGE_LOCK_FILE);
}

function encrypt_page($passcode) {
    $key = hash('sha256', $passcode);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
    $encrypted = openssl_encrypt('LOCKED', ENCRYPTION_METHOD, $key, 0, $iv);
    return file_put_contents(PAGE_LOCK_FILE, base64_encode($iv . $encrypted)) !== false;
}

function decrypt_page($passcode) {
    if (!is_page_locked()) return true;
    
    $data = base64_decode(file_get_contents(PAGE_LOCK_FILE));
    $iv_length = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = substr($data, 0, $iv_length);
    $encrypted_data = substr($data, $iv_length);
    
    $key = hash('sha256', $passcode);
    $decrypted = openssl_decrypt($encrypted_data, ENCRYPTION_METHOD, $key, 0, $iv);
    
    if ($decrypted === 'LOCKED') {
        unlink(PAGE_LOCK_FILE);
        return true;
    }
    return false;
}

function destroy_lock() {
    if (file_exists(PAGE_LOCK_FILE)) {
        return unlink(PAGE_LOCK_FILE);
    }
    return true;
}

// Handle password setup/login
$login_error = '';
$show_login = false;
$page_locked = false;
$encrypt_error = '';
$encrypt_success = '';

// Check page lock first
if (is_page_locked()) {
    if (isset($_POST['decrypt_page'])) {
        $passcode = $_POST['passcode'] ?? '';
        if (preg_match('/^\d{6}$/', $passcode)) {
            if (decrypt_page($passcode)) {
                $encrypt_success = 'Page unlocked successfully! Reloading...';
                header('Refresh: 1; url=' . $_SERVER['PHP_SELF']);
            } else {
                $encrypt_error = 'Invalid passcode. Please try again.';
            }
        } else {
            $encrypt_error = 'Passcode must be exactly 6 digits.';
        }
    }
    $page_locked = true;
}

// Handle page encryption (when not locked)
if (isset($_POST['encrypt_page']) && !$page_locked) {
    $passcode = $_POST['newpasscode'] ?? '';
    $confirm = $_POST['confirmpasscode'] ?? '';
    
    if (!preg_match('/^\d{6}$/', $passcode)) {
        $encrypt_error = 'Passcode must be exactly 6 digits (numbers only).';
    } elseif ($passcode !== $confirm) {
        $encrypt_error = 'Passcodes do not match.';
    } else {
        if (encrypt_page($passcode)) {
            $encrypt_success = 'Page encrypted successfully! Reloading...';
            header('Refresh: 1; url=' . $_SERVER['PHP_SELF']);
        } else {
            $encrypt_error = 'Failed to encrypt page. Please try again.';
        }
    }
}

// Handle destroy lock (emergency reset)
if (isset($_GET['destroy_lock'])) {
    destroy_lock();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if (is_password_set() && !isset($_SESSION['unzipper_auth'])) {
    if (isset($_POST['login'])) {
        $password = $_POST['password'] ?? '';
        if (verify_password($password)) {
            session_start();
            $_SESSION['unzipper_auth'] = true;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $login_error = 'Invalid password';
        }
    }
    $show_login = true;
}

if (isset($_POST['setpassword'])) {
    $password = $_POST['newpassword'] ?? '';
    $confirm = $_POST['confirmpassword'] ?? '';
    if (strlen($password) < 4) {
        $login_error = 'Password must be at least 4 characters';
    } elseif ($password !== $confirm) {
        $login_error = 'Passwords do not match';
    } else {
        if (set_password($password)) {
            session_start();
            $_SESSION['unzipper_auth'] = true;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

if (isset($_GET['logout'])) {
    session_start();
    unset($_SESSION['unzipper_auth']);
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$timestart = microtime(TRUE);
$GLOBALS['status'] = array();

$unzipper = new Unzipper;

// Handle file download
if (isset($_GET['download']) && !$show_login && !$page_locked) {
    $file = basename($_GET['download']);
    $filepath = $unzipper->localdir . '/' . $file;
    if (file_exists($filepath) && in_array(pathinfo($file, PATHINFO_EXTENSION), array('zip', 'rar', 'gz'))) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
}

if (isset($_POST['dounzip']) && !$show_login && !$page_locked) {
    $archive = isset($_POST['zipfile']) ? strip_tags($_POST['zipfile']) : '';
    $destination = isset($_POST['extpath']) ? strip_tags($_POST['extpath']) : '';
    if (!empty($archive)) {
        $unzipper->prepareExtraction($archive, $destination);
    }
}

// Handle file upload
if (isset($_POST['doupload']) && !$show_login && !$page_locked) {
    if (isset($_FILES['uploadfile']) && $_FILES['uploadfile']['error'] == 0) {
        $allowed = array('zip', 'rar', 'gz');
        $filename = $_FILES['uploadfile']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $upload_path = $unzipper->localdir . '/' . basename($filename);
            if (move_uploaded_file($_FILES['uploadfile']['tmp_name'], $upload_path)) {
                $GLOBALS['status'] = array('success' => 'File uploaded successfully: ' . htmlspecialchars($filename));
            } else {
                $GLOBALS['status'] = array('error' => 'Error uploading file.');
            }
        } else {
            $GLOBALS['status'] = array('error' => 'Invalid file type. Only .zip, .rar, .gz allowed.');
        }
    } else {
        $GLOBALS['status'] = array('error' => 'No file selected or upload error.');
    }
}

if (isset($_POST['dozip']) && !$show_login && !$page_locked) {
    $zippath = !empty($_POST['zippath']) ? strip_tags($_POST['zippath']) : '.';
    $zipfile = 'zipper-' . date("Y-m-d--H-i") . '.zip';
    Zipper::zipDir($zippath, $zipfile);
}

if (isset($_POST['dobackup']) && !$show_login && !$page_locked) {
    $backup_name = 'backup_folder_zip_' . date("Y-m-d_H-i-s") . '.zip';
    Zipper::zipDir('.', $backup_name);
    $GLOBALS['status'] = array('success' => 'Backup created: ' . $backup_name);
}

$timeend = microtime(TRUE);
$time = round($timeend - $timestart, 4);

class Unzipper {
    public $localdir = '.';
    public $zipfiles = array();

    public function __construct() {
        if ($dh = opendir($this->localdir)) {
            while (($file = readdir($dh)) !== FALSE) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'zip'
                    || pathinfo($file, PATHINFO_EXTENSION) === 'gz'
                    || pathinfo($file, PATHINFO_EXTENSION) === 'rar'
                ) {
                    $this->zipfiles[] = $file;
                }
            }
            closedir($dh);

            if (!empty($this->zipfiles)) {
                $GLOBALS['status'] = array('info' => '.zip or .gz or .rar files found, ready for extraction');
            }
            else {
                $GLOBALS['status'] = array('info' => 'No .zip or .gz or rar files found. So only zipping functionality available.');
            }
        }
    }

    public function prepareExtraction($archive, $destination = '') {
        if (empty($destination)) {
            $extpath = $this->localdir;
        }
        else {
            $extpath = $this->localdir . '/' . $destination;
            if (!is_dir($extpath)) {
                mkdir($extpath);
            }
        }
        if (in_array($archive, $this->zipfiles)) {
            self::extract($archive, $extpath);
        }
    }

    public static function extract($archive, $destination) {
        $ext = pathinfo($archive, PATHINFO_EXTENSION);
        switch ($ext) {
            case 'zip':
                self::extractZipArchive($archive, $destination);
                break;
            case 'gz':
                self::extractGzipFile($archive, $destination);
                break;
            case 'rar':
                self::extractRarArchive($archive, $destination);
                break;
        }
    }

    public static function extractZipArchive($archive, $destination) {
        if (!class_exists('ZipArchive')) {
            $GLOBALS['status'] = array('error' => 'Error: Your PHP version does not support unzip functionality.');
            return;
        }

        $zip = new ZipArchive;

        if ($zip->open($archive) === TRUE) {
            if (is_writeable($destination . '/')) {
                $zip->extractTo($destination);
                $zip->close();
                $GLOBALS['status'] = array('success' => 'Files unzipped successfully');
            }
            else {
                $GLOBALS['status'] = array('error' => 'Error: Directory not writeable by webserver.');
            }
        }
        else {
            $GLOBALS['status'] = array('error' => 'Error: Cannot read .zip archive.');
        }
    }

    public static function extractGzipFile($archive, $destination) {
        if (!function_exists('gzopen')) {
            $GLOBALS['status'] = array('error' => 'Error: Your PHP has no zlib support enabled.');
            return;
        }

        $filename = pathinfo($archive, PATHINFO_FILENAME);
        $gzipped = gzopen($archive, "rb");
        $file = fopen($destination . '/' . $filename, "w");

        while ($string = gzread($gzipped, 4096)) {
            fwrite($file, $string, strlen($string));
        }
        gzclose($gzipped);
        fclose($file);

        if (file_exists($destination . '/' . $filename)) {
            $GLOBALS['status'] = array('success' => 'File unzipped successfully.');

            if (pathinfo($destination . '/' . $filename, PATHINFO_EXTENSION) == 'tar') {
                $phar = new PharData($destination . '/' . $filename);
                if ($phar->extractTo($destination)) {
                    $GLOBALS['status'] = array('success' => 'Extracted tar.gz archive successfully.');
                    unlink($destination . '/' . $filename);
                }
            }
        }
        else {
            $GLOBALS['status'] = array('error' => 'Error unzipping file.');
        }
    }

    public static function extractRarArchive($archive, $destination) {
        if (!class_exists('RarArchive')) {
            $GLOBALS['status'] = array('error' => 'Error: Your PHP version does not support .rar archive functionality. <a href="http://php.net/manual/en/rar.installation.php" target="_blank">How to install RarArchive</a>');
            return;
        }
        if ($rar = RarArchive::open($archive)) {
            if (is_writeable($destination . '/')) {
                $entries = $rar->getEntries();
                foreach ($entries as $entry) {
                    $entry->extract($destination);
                }
                $rar->close();
                $GLOBALS['status'] = array('success' => 'Files extracted successfully.');
            }
            else {
                $GLOBALS['status'] = array('error' => 'Error: Directory not writeable by webserver.');
            }
        }
        else {
            $GLOBALS['status'] = array('error' => 'Error: Cannot read .rar archive.');
        }
    }
}

class Zipper {
    private static function folderToZip($folder, &$zipFile, $exclusiveLength) {
        $handle = opendir($folder);

        while (FALSE !== $f = readdir($handle)) {
            if ($f != '.' && $f != '..' && $f != basename(__FILE__)) {
                $filePath = "$folder/$f";
                $localPath = substr($filePath, $exclusiveLength);

                if (is_file($filePath)) {
                    $zipFile->addFile($filePath, $localPath);
                }
                elseif (is_dir($filePath)) {
                    $zipFile->addEmptyDir($localPath);
                    self::folderToZip($filePath, $zipFile, $exclusiveLength);
                }
            }
        }
        closedir($handle);
    }

    public static function zipDir($sourcePath, $outZipPath) {
        $pathInfo = pathinfo($sourcePath);
        $parentPath = $pathInfo['dirname'];
        $dirName = $pathInfo['basename'];

        $z = new ZipArchive();
        $z->open($outZipPath, ZipArchive::CREATE);
        $z->addEmptyDir($dirName);
        if ($sourcePath == $dirName) {
            self::folderToZip($sourcePath, $z, 0);
        }
        else {
            self::folderToZip($sourcePath, $z, strlen("$parentPath/"));
        }
        $z->close();

        $GLOBALS['status'] = array('success' => 'Successfully created archive ' . $outZipPath);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Unzipper | Alien Edition v2.3</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root {
      --bg-primary: #0a0a0f;
      --bg-secondary: #12121a;
      --bg-card: #1a1a2e;
      --accent-primary: #00ffaa;
      --accent-secondary: #ff00ff;
      --accent-tertiary: #00ffff;
      --text-primary: #e0e0ff;
      --text-secondary: #a0a0cc;
      --border-glow: rgba(0, 255, 170, 0.3);
      --danger: #ff3366;
      --success: #00ff88;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      background: var(--bg-primary);
      color: var(--text-primary);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      min-height: 100vh;
      overflow-x: hidden;
      position: relative;
    }

    #three-container {
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      z-index: 0;
      pointer-events: none;
    }

    .main-content {
      position: relative;
      z-index: 10;
      min-height: 100vh;
      padding: 40px 20px;
    }

    .alien-header {
      text-align: center;
      margin-bottom: 50px;
    }

    .alien-header h1 {
      font-size: 3rem;
      font-weight: 800;
      background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary), var(--accent-tertiary));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      letter-spacing: 2px;
      margin-bottom: 10px;
    }

    .alien-header .subtitle {
      color: var(--text-secondary);
      font-size: 1.1rem;
      letter-spacing: 3px;
      text-transform: uppercase;
    }

    .alien-card {
      background: var(--bg-card);
      border: 1px solid var(--border-glow);
      border-radius: 15px;
      padding: 30px;
      margin-bottom: 30px;
      box-shadow: 0 0 30px rgba(0, 255, 170, 0.1), inset 0 0 30px rgba(0, 0, 0, 0.3);
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .alien-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 40px rgba(0, 255, 170, 0.2);
    }

    .card-title {
      color: var(--accent-primary);
      font-size: 1.5rem;
      margin-bottom: 25px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .form-label {
      color: var(--text-secondary);
      font-weight: 500;
      margin-bottom: 10px;
    }

    .form-control, .form-select {
      background: var(--bg-secondary);
      border: 1px solid var(--border-glow);
      color: var(--text-primary);
      padding: 12px 15px;
      border-radius: 8px;
    }

    .form-control:focus, .form-select:focus {
      background: var(--bg-secondary);
      border-color: var(--accent-primary);
      color: var(--text-primary);
      box-shadow: 0 0 20px rgba(0, 255, 170, 0.2);
    }

    .info-text {
      color: var(--text-secondary);
      font-size: 0.85rem;
      margin-top: 8px;
      font-style: italic;
    }

    .btn-alien {
      background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
      border: none;
      color: var(--bg-primary);
      padding: 12px 35px;
      font-weight: 700;
      font-size: 1rem;
      border-radius: 8px;
      text-transform: uppercase;
      letter-spacing: 2px;
      transition: all 0.3s ease;
      cursor: pointer;
    }

    .btn-alien:hover {
      transform: scale(1.05);
      box-shadow: 0 0 30px rgba(0, 255, 170, 0.4);
      color: var(--bg-primary);
    }

    .btn-alien:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .alert-alien {
      background: var(--bg-secondary);
      border: 1px solid var(--border-glow);
      color: var(--text-primary);
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 30px;
    }

    .alert-alien.alert-success {
      border-color: var(--success);
      box-shadow: 0 0 20px rgba(0, 255, 136, 0.2);
    }

    .alert-alien.alert-danger {
      border-color: var(--danger);
      box-shadow: 0 0 20px rgba(255, 51, 102, 0.2);
    }

    .alert-alien.alert-info {
      border-color: var(--accent-tertiary);
      box-shadow: 0 0 20px rgba(0, 255, 255, 0.2);
    }

    .scan-line {
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 2px;
      background: linear-gradient(90deg, transparent, var(--accent-primary), transparent);
      animation: scan 3s linear infinite;
      z-index: 50;
      opacity: 0.5;
    }

    @keyframes scan {
      0% { top: 0; }
      100% { top: 100%; }
    }

    .pulse {
      animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.6; }
    }

    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    .list-group-item {
      transition: all 0.3s ease;
    }

    .list-group-item:hover {
      background: var(--bg-card) !important;
      border-color: var(--accent-primary) !important;
      transform: translateX(5px);
    }

    .progress-bar-animated {
      background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
    }

    .encrypt-panel {
      background: var(--bg-primary);
      border: 2px solid var(--border-glow);
      border-radius: 10px;
      padding: 20px;
      margin-top: 15px;
    }

    .passcode-input {
      letter-spacing: 8px;
      font-size: 1.5rem;
      text-align: center;
      font-weight: bold;
    }

    ::-webkit-scrollbar {
      width: 10px;
    }

    ::-webkit-scrollbar-track {
      background: var(--bg-primary);
    }

    ::-webkit-scrollbar-thumb {
      background: linear-gradient(var(--accent-primary), var(--accent-secondary));
      border-radius: 5px;
    }
  </style>
</head>
<body>
  <div class="scan-line"></div>
  <div id="three-container"></div>

  <div class="main-content">
    <div class="container">
      
      <?php if ($page_locked): ?>
        <!-- DECRYPT PANEL -->
        <div class="row justify-content-center">
          <div class="col-lg-5">
            <div class="alien-card" style="text-align: center;">
              <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2" style="margin-bottom: 20px;">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0110 0v4"></path>
              </svg>
              <h3 style="color: var(--danger);">Page is Locked</h3>
              <p class="info-text">This page is encrypted. Enter your 6-digit passcode to unlock.</p>
              
              <?php if ($encrypt_error): ?>
                <div class="alert-alien alert-danger"><?php echo $encrypt_error; ?></div>
              <?php endif; ?>
              
              <?php if ($encrypt_success): ?>
                <div class="alert-alien alert-success"><?php echo $encrypt_success; ?></div>
              <?php endif; ?>
              
              <form action="" method="POST">
                <div class="mb-3">
                  <input type="password" name="passcode" class="form-control passcode-input" 
                         placeholder="000000" maxlength="6" pattern="\d{6}" required
                         style="font-size: 1.8rem; letter-spacing: 12px;">
                  <div class="info-text">Enter your 6-digit passcode</div>
                </div>
                <button type="submit" name="decrypt_page" class="btn btn-alien" style="width: 100%;">
                  <i class="bi bi-unlock"></i> Decrypt & Unlock
                </button>
              </form>
              
              <div class="mt-3">
                <a href="?destroy_lock=1" class="btn btn-alien" style="background: linear-gradient(135deg, var(--danger), #ff0066); font-size: 0.8rem; padding: 8px 20px;">
                  <i class="bi bi-trash"></i> Reset Lock
                </a>
                <div class="info-text" style="margin-top: 5px;">Warning: This will remove the lock completely!</div>
              </div>
            </div>
          </div>
        </div>

      <?php elseif ($show_login): ?>
        <!-- LOGIN PANEL -->
        <div class="row justify-content-center">
          <div class="col-lg-5">
            <div class="alien-card">
              <h3 class="card-title">
                <i class="bi bi-shield-lock pulse"></i> 
                <?php echo is_password_set() ? 'Login Required' : 'Set Password Protection'; ?>
              </h3>
              <?php if ($login_error): ?>
                <div class="alert-alien alert-danger"><?php echo $login_error; ?></div>
              <?php endif; ?>
              
              <?php if (!is_password_set()): ?>
                <form action="" method="POST">
                  <div class="mb-3">
                    <label class="form-label"><i class="bi bi-key"></i> New Password</label>
                    <input type="password" name="newpassword" class="form-control" placeholder="Enter password (min 4 chars)" required>
                    <div class="info-text">Password will be encrypted with AES-256</div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label"><i class="bi bi-key-fill"></i> Confirm Password</label>
                    <input type="password" name="confirmpassword" class="form-control" placeholder="Confirm password" required>
                  </div>
                  <button type="submit" name="setpassword" class="btn btn-alien">
                    <i class="bi bi-shield-check"></i> Set Password
                  </button>
                </form>
              <?php else: ?>
                <form action="" method="POST">
                  <div class="mb-3">
                    <label class="form-label"><i class="bi bi-lock"></i> Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                  </div>
                  <button type="submit" name="login" class="btn btn-alien">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>

      <?php else: ?>

      <!-- MAIN UI -->
      <div class="alien-header">
        <h1><i class="bi bi-box-seam"></i> UNZIPPER</h1>
        <div class="subtitle">Alien Archive Manager v2.3</div>
      </div>

      <?php if (!empty($GLOBALS['status'])): ?>
        <?php $status_type = strtolower(key($GLOBALS['status'])); ?>
        <?php $alert_class = $status_type === 'success' ? 'alert-success' : ($status_type === 'error' ? 'alert-danger' : 'alert-info'); ?>
        <div class="alert-alien alert <?php echo $alert_class; ?>" role="alert">
          <i class="bi bi-<?php echo $status_type === 'success' ? 'check-circle' : ($status_type === 'error' ? 'exclamation-triangle' : 'info-circle'); ?>"></i>
          <?php echo reset($GLOBALS['status']); ?>
          <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 10px;">
            Processing Time: <?php echo $time; ?> seconds
          </div>
        </div>
      <?php endif; ?>

      <div class="row">
        <!-- EXTRACT SECTION -->
        <div class="col-lg-6">
          <div class="alien-card">
            <h3 class="card-title">
              <i class="bi bi-box-arrow-in-down pulse"></i> Extract Archive
            </h3>
            
            <!-- Upload Section -->
            <div class="mb-4" style="padding: 20px; background: var(--bg-primary); border-radius: 10px; border: 1px solid var(--border-glow);">
              <h5 class="card-title" style="font-size: 1.1rem;">
                <i class="bi bi-cloud-upload"></i> Quick Upload
              </h5>
              <form id="uploadForm" action="" method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                  <input type="file" name="uploadfile" id="uploadfile" class="form-control" accept=".zip,.rar,.gz,.tar.gz" onchange="toggleSelectArchive()">
                </div>
                <button type="button" class="btn btn-alien" id="uploadBtn" onclick="uploadFile()" disabled>
                  <i class="bi bi-upload"></i> Upload File
                </button>
              </form>
              
              <div id="progressContainer" style="display: none; margin-top: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                  <span style="color: var(--accent-primary);">Uploading...</span>
                  <span id="progressPercent" style="color: var(--accent-secondary);">0%</span>
                </div>
                <div style="width: 100%; height: 8px; background: var(--bg-primary); border-radius: 4px; overflow: hidden; border: 1px solid var(--border-glow);">
                  <div id="progressBar" style="width: 0%; height: 100%; background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));"></div>
                </div>
              </div>
            </div>
            
            <hr style="border-color: var(--border-glow); margin: 20px 0;">
            
            <!-- Extract Form -->
            <form action="" method="POST">
              <div class="mb-3">
                <label class="form-label"><i class="bi bi-file-earmark-zip"></i> Select Archive from Server</label>
                <select name="zipfile" id="zipfile" class="form-select" size="1" onchange="toggleUpload()">
                  <option value="">-- Select an archive --</option>
                  <?php foreach ($unzipper->zipfiles as $zip): ?>
                    <option><?php echo htmlspecialchars($zip); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label"><i class="bi bi-folder2-open"></i> Extraction Path (Optional)</label>
                <input type="text" name="extpath" class="form-control" placeholder="e.g., extracted_files">
              </div>

              <button type="submit" name="dounzip" class="btn btn-alien">
                <i class="bi bi-rocket-takeoff"></i> Unzip Archive
              </button>
            </form>
          </div>
        </div>

        <!-- CREATE/BACKUP SECTION -->
        <div class="col-lg-6">
          <div class="alien-card">
            <h3 class="card-title">
              <i class="bi bi-box-arrow-up pulse"></i> Create / Backup
            </h3>
            <form action="" method="POST">
              <div class="mb-3">
                <label class="form-label"><i class="bi bi-folder2"></i> Path to Zip (Optional)</label>
                <input type="text" name="zippath" class="form-control" placeholder="e.g., my_folder">
              </div>
              <button type="submit" name="dozip" class="btn btn-alien">
                <i class="bi bi-archive"></i> Zip Archive
              </button>
            </form>
            
            <hr style="border-color: var(--border-glow); margin: 20px 0;">
            
            <!-- Backup -->
            <form action="" method="POST" id="backupForm">
              <h5 class="card-title" style="font-size: 1.1rem;">
                <i class="bi bi-hdd-network"></i> Quick Backup
              </h5>
              <p class="info-text">Creates a complete backup of the current directory</p>
              <div style="background: var(--bg-primary); padding: 12px; border-radius: 8px; margin-bottom: 15px; border: 1px solid var(--border-glow);">
                <span style="color: var(--text-secondary); font-size: 0.85rem;">
                  Backup: <code style="color: var(--accent-primary);">backup_folder_zip_YYYY-MM-DD_HH-II-SS.zip</code>
                </span>
              </div>
              <button type="button" class="btn btn-alien" id="backupBtn" onclick="startBackup()">
                <i class="bi bi-cloud-download"></i> Backup Now
              </button>
              
              <div id="backupProgress" style="display: none; margin-top: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                  <span style="color: var(--accent-primary);"><i class="bi bi-gear" style="animation: spin 1s linear infinite;"></i> Creating backup...</span>
                  <span id="backupPercent" style="color: var(--accent-secondary);">0%</span>
                </div>
                <div style="width: 100%; height: 8px; background: var(--bg-primary); border-radius: 4px; overflow: hidden; border: 1px solid var(--border-glow);">
                  <div id="backupProgressBar" style="width: 0%; height: 100%; background: linear-gradient(90deg, var(--accent-tertiary), var(--accent-primary));"></div>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- DOWNLOAD SECTION -->
      <?php if (!empty($unzipper->zipfiles)): ?>
      <div class="row mt-4">
        <div class="col-12">
          <div class="alien-card">
            <h3 class="card-title">
              <i class="bi bi-download pulse"></i> Download Archives
            </h3>
            <div class="list-group">
              <?php foreach ($unzipper->zipfiles as $zip): ?>
                <a href="?download=<?php echo urlencode($zip); ?>" class="list-group-item list-group-item-action" 
                   style="background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-glow); margin-bottom: 5px; border-radius: 8px;">
                  <i class="bi bi-file-earmark-zip"></i> <?php echo htmlspecialchars($zip); ?>
                  <span class="badge bg-success" style="float: right;">Download</span>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- ENCRYPT PAGE BUTTON -->
      <div class="row mt-3">
        <div class="col-12 text-center">
          <button class="btn btn-alien" style="background: linear-gradient(135deg, #666, #333);" onclick="showEncryptPanel()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 5px;">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0110 0v4"></path>
            </svg>
            Encrypt Page
          </button>
          <a href="?logout=1" class="btn btn-alien" style="background: linear-gradient(135deg, var(--danger), #ff0066); margin-left: 10px;">
            <i class="bi bi-box-arrow-right"></i> Logout
          </a>
          
          <!-- Encrypt Panel (Hidden by default) -->
          <div id="encryptPanel" class="encrypt-panel" style="display: none; max-width: 400px; margin: 20px auto;">
            <?php if ($encrypt_error): ?>
              <div class="alert-alien alert-danger"><?php echo $encrypt_error; ?></div>
            <?php endif; ?>
            
            <h5 style="color: var(--accent-primary); margin-bottom: 15px;">
              <i class="bi bi-lock"></i> Set 6-Digit Passcode
            </h5>
            <form action="" method="POST">
              <div class="mb-3">
                <input type="password" name="newpasscode" class="form-control passcode-input" 
                       placeholder="000000" maxlength="6" pattern="\d{6}" required
                       style="font-size: 1.5rem; letter-spacing: 10px;">
                <div class="info-text">Enter 6-digit passcode</div>
              </div>
              <div class="mb-3">
                <input type="password" name="confirmpasscode" class="form-control" 
                       placeholder="Confirm passcode" maxlength="6" pattern="\d{6}" required>
                <div class="info-text">Confirm your passcode</div>
              </div>
              
              <!-- Progress -->
              <div id="encryptProgressContainer" style="display: none; margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                  <span style="color: var(--accent-primary);">Encrypting...</span>
                  <span id="encryptPercent" style="color: var(--accent-secondary);">0%</span>
                </div>
                <div style="width: 100%; height: 8px; background: var(--bg-primary); border-radius: 4px; overflow: hidden;">
                  <div id="encryptProgressBar" style="width: 0%; height: 100%; background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));"></div>
                </div>
              </div>
              
              <button type="submit" name="encrypt_page" class="btn btn-alien" onclick="startEncryptProgress()" style="width: 100%;">
                <i class="bi bi-shield-lock"></i> Encrypt Page
              </button>
            </form>
            <button class="btn" style="color: var(--text-secondary); margin-top: 10px; width: 100%;" onclick="hideEncryptPanel()">Cancel</button>
          </div>
        </div>
      </div>

      <?php endif; ?>
    </div>
  </div>

  <div class="version-badge">
    v<?php echo VERSION; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
  <script>
    // Three.js Background
    let scene, camera, renderer, particles, mouseX = 0, mouseY = 0;

    function initThree() {
      scene = new THREE.Scene();
      camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
      camera.position.z = 50;

      renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
      renderer.setSize(window.innerWidth, window.innerHeight);
      renderer.setClearColor(0x000000, 0);
      document.getElementById('three-container').appendChild(renderer.domElement);

      const geometry = new THREE.BufferGeometry();
      const positions = new Float32Array(150 * 3);
      const colors = new Float32Array(150 * 3);

      for (let i = 0; i < 150 * 3; i += 3) {
        positions[i] = (Math.random() - 0.5) * 200;
        positions[i + 1] = (Math.random() - 0.5) * 200;
        positions[i + 2] = (Math.random() - 0.5) * 200;

        const color = new THREE.Color();
        color.setHSL(Math.random() * 0.3 + 0.4, 1, 0.5);
        colors[i] = color.r;
        colors[i + 1] = color.g;
        colors[i + 2] = color.b;
      }

      geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
      geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));

      const material = new THREE.PointsMaterial({ size: 2, vertexColors: true, transparent: true, opacity: 0.6, blending: THREE.AdditiveBlending });
      particles = new THREE.Points(geometry, material);
      scene.add(particles);

      const sphere = new THREE.Mesh(new THREE.IcosahedronGeometry(30, 1), new THREE.MeshBasicMaterial({ color: 0x00ffaa, wireframe: true, transparent: true, opacity: 0.1 }));
      scene.add(sphere);

      window.addEventListener('resize', () => {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
      });

      document.addEventListener('mousemove', (e) => {
        mouseX = (e.clientX / window.innerWidth) * 2 - 1;
        mouseY = -(e.clientY / window.innerHeight) * 2 + 1;
      });
    }

    function animate() {
      requestAnimationFrame(animate);
      camera.position.x += (mouseX * 10 - camera.position.x) * 0.05;
      camera.position.y += (mouseY * 10 - camera.position.y) * 0.05;
      camera.lookAt(scene.position);
      particles.rotation.y += 0.001;
      renderer.render(scene, camera);
    }

    initThree();
    animate();

    // Toggle functions
    function toggleUpload() {
      const zipSelect = document.getElementById('zipfile');
      const fileInput = document.getElementById('uploadfile');
      const uploadBtn = document.getElementById('uploadBtn');
      
      if (zipSelect.value !== '') {
        fileInput.value = '';
        uploadBtn.disabled = true;
        fileInput.disabled = true;
        fileInput.style.opacity = '0.5';
      } else {
        fileInput.disabled = false;
        fileInput.style.opacity = '1';
      }
    }

    function toggleSelectArchive() {
      const zipSelect = document.getElementById('zipfile');
      const fileInput = document.getElementById('uploadfile');
      const uploadBtn = document.getElementById('uploadBtn');
      
      if (fileInput.files.length > 0) {
        zipSelect.value = '';
        zipSelect.disabled = true;
        zipSelect.style.opacity = '0.5';
        uploadBtn.disabled = false;
      } else {
        zipSelect.disabled = false;
        zipSelect.style.opacity = '1';
        uploadBtn.disabled = true;
      }
    }

    // Upload with progress
    function uploadFile() {
      const fileInput = document.getElementById('uploadfile');
      const progressContainer = document.getElementById('progressContainer');
      const progressBar = document.getElementById('progressBar');
      const progressPercent = document.getElementById('progressPercent');
      
      if (!fileInput.files.length) return;
      
      const formData = new FormData();
      formData.append('uploadfile', fileInput.files[0]);
      formData.append('doupload', '1');
      
      const xhr = new XMLHttpRequest();
      
      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
          const percent = Math.round((e.loaded / e.total) * 100);
          progressBar.style.width = percent + '%';
          progressPercent.textContent = percent + '%';
        }
      });
      
      xhr.addEventListener('load', () => {
        if (xhr.status === 200) {
          progressBar.style.width = '100%';
          progressPercent.textContent = '100%';
          setTimeout(() => window.location.reload(), 1500);
        }
      });
      
      xhr.open('POST', '', true);
      progressContainer.style.display = 'block';
      xhr.send(formData);
    }

    // Backup with progress
    function startBackup() {
      const backupBtn = document.getElementById('backupBtn');
      const progressDiv = document.getElementById('backupProgress');
      const progressBar = document.getElementById('backupProgressBar');
      const progressPercent = document.getElementById('backupPercent');
      
      progressDiv.style.display = 'block';
      backupBtn.disabled = true;
      
      let progress = 0;
      const interval = setInterval(() => {
        progress += Math.random() * 15;
        if (progress > 90) progress = 90;
        progressBar.style.width = progress + '%';
        progressPercent.textContent = Math.round(progress) + '%';
      }, 200);
      
      const formData = new FormData();
      formData.append('dobackup', '1');
      
      const xhr = new XMLHttpRequest();
      xhr.addEventListener('load', () => {
        clearInterval(interval);
        progressBar.style.width = '100%';
        progressPercent.textContent = '100%';
        setTimeout(() => window.location.reload(), 1500);
      });
      
      xhr.open('POST', '', true);
      xhr.send(formData);
    }

    // Encrypt Panel functions
    function showEncryptPanel() {
      document.getElementById('encryptPanel').style.display = 'block';
    }

    function hideEncryptPanel() {
      document.getElementById('encryptPanel').style.display = 'none';
    }

    function startEncryptProgress() {
      const container = document.getElementById('encryptProgressContainer');
      const progressBar = document.getElementById('encryptProgressBar');
      const progressPercent = document.getElementById('encryptPercent');
      
      container.style.display = 'block';
      
      let progress = 0;
      const interval = setInterval(() => {
        progress += Math.random() * 20;
        if (progress > 95) progress = 95;
        progressBar.style.width = progress + '%';
        progressPercent.textContent = Math.round(progress) + '%';
      }, 150);
      
      setTimeout(() => {
        clearInterval(interval);
        progressBar.style.width = '100%';
        progressPercent.textContent = '100%';
      }, 2000);
    }

    // Passcode validation
    const passcodeInput = document.querySelector('.passcode-input');
    if (passcodeInput) {
      passcodeInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
      });
    }
  </script>
</body>
</html>