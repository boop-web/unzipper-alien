<?php
/**
 * The Unzipper extracts .zip or .rar archives and .gz files on webservers.
 * It's handy if you do not have shell access. E.g. if you want to upload a lot
 * of files (php framework or image collection) as an archive to save time.
 * As of version 0.1.0 it also supports creating archives.
 *
 * @author  Andreas Tasch, at[tec], attec.at
 * @license GNU GPL v3
 * @package attec.toolbox
 * @version 2.1.0 - Modern Alien Edition
 */
define('VERSION', '2.1.0');

$timestart = microtime(TRUE);
$GLOBALS['status'] = array();

$unzipper = new Unzipper;
if (isset($_POST['dounzip'])) {
  $archive = isset($_POST['zipfile']) ? strip_tags($_POST['zipfile']) : '';
  $destination = isset($_POST['extpath']) ? strip_tags($_POST['extpath']) : '';
  
  // Handle file upload
  if (isset($_FILES['uploadfile']) && $_FILES['uploadfile']['error'] == 0) {
    $allowed = array('zip', 'rar', 'gz');
    $filename = $_FILES['uploadfile']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (in_array($ext, $allowed)) {
      $upload_path = $unzipper->localdir . '/' . basename($filename);
      if (move_uploaded_file($_FILES['uploadfile']['tmp_name'], $upload_path)) {
        $archive = basename($filename);
        $GLOBALS['status'] = array('info' => 'File uploaded successfully. Extracting...');
      } else {
        $GLOBALS['status'] = array('error' => 'Error uploading file.');
        $archive = '';
      }
    } else {
      $GLOBALS['status'] = array('error' => 'Invalid file type. Only .zip, .rar, .gz allowed.');
      $archive = '';
    }
  }
  
  if (!empty($archive)) {
    $unzipper->prepareExtraction($archive, $destination);
  }
}

if (isset($_POST['dozip'])) {
  $zippath = !empty($_POST['zippath']) ? strip_tags($_POST['zippath']) : '.';
  $zipfile = 'zipper-' . date("Y-m-d--H-i") . '.zip';
  Zipper::zipDir($zippath, $zipfile);
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
  <title>Unzipper | Alien Edition v2.0</title>
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

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

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
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
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
      position: relative;
    }

    .alien-header h1 {
      font-size: 3rem;
      font-weight: 800;
      background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary), var(--accent-tertiary));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      text-shadow: 0 0 30px rgba(0, 255, 170, 0.5);
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

    .alien-card::before {
      content: '';
      position: absolute;
      top: -2px;
      left: -2px;
      right: -2px;
      bottom: -2px;
      background: linear-gradient(45deg, var(--accent-primary), var(--accent-secondary), var(--accent-tertiary), var(--accent-primary));
      border-radius: 15px;
      opacity: 0;
      z-index: -1;
      transition: opacity 0.3s ease;
      filter: blur(10px);
    }

    .alien-card:hover::before {
      opacity: 0.3;
    }

    .alien-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 40px rgba(0, 255, 170, 0.2), inset 0 0 30px rgba(0, 0, 0, 0.3);
    }

    .card-title {
      color: var(--accent-primary);
      font-size: 1.5rem;
      margin-bottom: 25px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .card-title i {
      font-size: 1.8rem;
    }

    .form-label {
      color: var(--text-secondary);
      font-weight: 500;
      margin-bottom: 10px;
      letter-spacing: 1px;
    }

    .form-control, .form-select {
      background: var(--bg-secondary);
      border: 1px solid var(--border-glow);
      color: var(--text-primary);
      padding: 12px 15px;
      border-radius: 8px;
      transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
      background: var(--bg-secondary);
      border-color: var(--accent-primary);
      color: var(--text-primary);
      box-shadow: 0 0 20px rgba(0, 255, 170, 0.2);
    }

    .form-control::placeholder {
      color: var(--text-secondary);
      opacity: 0.6;
    }

    .form-select option {
      background: var(--bg-secondary);
      color: var(--text-primary);
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
      position: relative;
      overflow: hidden;
    }

    .btn-alien:hover {
      transform: scale(1.05);
      box-shadow: 0 0 30px rgba(0, 255, 170, 0.4);
      color: var(--bg-primary);
    }

    .btn-alien::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.5);
      transform: translate(-50%, -50%);
      transition: width 0.6s, height 0.6s;
    }

    .btn-alien:hover::before {
      width: 300px;
      height: 300px;
      opacity: 0;
    }

    .alert-alien {
      background: var(--bg-secondary);
      border: 1px solid var(--border-glow);
      color: var(--text-primary);
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 30px;
      position: relative;
      overflow: hidden;
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

    .processing-time {
      font-size: 0.85rem;
      color: var(--text-secondary);
      margin-top: 10px;
    }

    .version-badge {
      position: fixed;
      bottom: 20px;
      right: 20px;
      background: var(--bg-card);
      border: 1px solid var(--border-glow);
      padding: 10px 20px;
      border-radius: 20px;
      font-size: 0.85rem;
      color: var(--accent-primary);
      z-index: 100;
    }

    .scan-line {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 2px;
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
      <div class="alien-header">
        <h1><i class="bi bi-box-seam"></i> UNZIPPER</h1>
        <div class="subtitle">Alien Archive Manager v2.0</div>
      </div>

      <?php if (!empty($GLOBALS['status'])): ?>
        <?php $status_type = strtolower(key($GLOBALS['status'])); ?>
        <?php $alert_class = $status_type === 'success' ? 'alert-success' : ($status_type === 'error' ? 'alert-danger' : 'alert-info'); ?>
        <div class="alert-alien alert <?php echo $alert_class; ?>" role="alert">
          <i class="bi bi-<?php echo $status_type === 'success' ? 'check-circle' : ($status_type === 'error' ? 'exclamation-triangle' : 'info-circle'); ?>"></i>
          <?php echo reset($GLOBALS['status']); ?>
          <div class="processing-time">
            <i class="bi bi-clock"></i> Processing Time: <?php echo $time; ?> seconds
          </div>
        </div>
      <?php endif; ?>

      <div class="row">
        <div class="col-lg-6">
          <div class="alien-card">
            <h3 class="card-title">
              <i class="bi bi-box-arrow-in-down pulse"></i> Extract Archive
            </h3>
            <form action="" method="POST" enctype="multipart/form-data">
              <div class="mb-3">
                <label for="zipfile" class="form-label">
                  <i class="bi bi-file-earmark-zip"></i> Select Archive from Server
                </label>
                <select name="zipfile" class="form-select" size="1">
                  <?php foreach ($unzipper->zipfiles as $zip): ?>
                    <option><?php echo htmlspecialchars($zip); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label for="uploadfile" class="form-label">
                  <i class="bi bi-cloud-upload"></i> Or Upload New Archive
                </label>
                <input type="file" name="uploadfile" class="form-control" accept=".zip,.rar,.gz,.tar.gz">
                <div class="info-text">
                  <i class="bi bi-info-circle"></i> Upload .zip, .rar, or .gz files directly
                </div>
              </div>

              <div class="mb-3">
                <label for="extpath" class="form-label">
                  <i class="bi bi-folder2-open"></i> Extraction Path (Optional)
                </label>
                <input type="text" name="extpath" class="form-control" placeholder="e.g., extracted_files">
                <div class="info-text">
                  <i class="bi bi-info-circle"></i> Enter path without leading/trailing slashes. Current directory used if empty.
                </div>
              </div>

              <button type="submit" name="dounzip" class="btn btn-alien">
                <i class="bi bi-rocket-takeoff"></i> Unzip Archive
              </button>
            </form>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="alien-card">
            <h3 class="card-title">
              <i class="bi bi-box-arrow-up pulse"></i> Create Archive
            </h3>
            <form action="" method="POST">
              <div class="mb-3">
                <label for="zippath" class="form-label">
                  <i class="bi bi-folder2"></i> Path to Zip (Optional)
                </label>
                <input type="text" name="zippath" class="form-control" placeholder="e.g., my_folder">
                <div class="info-text">
                  <i class="bi bi-info-circle"></i> Enter path without leading/trailing slashes. Current directory used if empty.
                </div>
              </div>

              <button type="submit" name="dozip" class="btn btn-alien">
                <i class="bi bi-archive"></i> Zip Archive
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="version-badge">
    <i class="bi bi-cpu"></i> v<?php echo VERSION; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
  <script>
    // Three.js Alien Background
    let scene, camera, renderer, particles, connections;
    let mouseX = 0, mouseY = 0;

    function initThree() {
      scene = new THREE.Scene();
      camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
      camera.position.z = 50;

      renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
      renderer.setSize(window.innerWidth, window.innerHeight);
      renderer.setClearColor(0x000000, 0);
      document.getElementById('three-container').appendChild(renderer.domElement);

      // Create floating particles
      const particleCount = 150;
      const geometry = new THREE.BufferGeometry();
      const positions = new Float32Array(particleCount * 3);
      const colors = new Float32Array(particleCount * 3);

      for (let i = 0; i < particleCount * 3; i += 3) {
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

      const material = new THREE.PointsMaterial({
        size: 2,
        vertexColors: true,
        transparent: true,
        opacity: 0.6,
        blending: THREE.AdditiveBlending
      });

      particles = new THREE.Points(geometry, material);
      scene.add(particles);

      // Create wireframe sphere
      const sphereGeometry = new THREE.IcosahedronGeometry(30, 1);
      const sphereMaterial = new THREE.MeshBasicMaterial({
        color: 0x00ffaa,
        wireframe: true,
        transparent: true,
        opacity: 0.1
      });
      const sphere = new THREE.Mesh(sphereGeometry, sphereMaterial);
      scene.add(sphere);

      // Create orbital rings
      for (let i = 0; i < 3; i++) {
        const ringGeometry = new THREE.TorusGeometry(40 + i * 10, 0.5, 8, 100);
        const ringMaterial = new THREE.MeshBasicMaterial({
          color: i === 0 ? 0x00ffaa : (i === 1 ? 0xff00ff : 0x00ffff),
          transparent: true,
          opacity: 0.2
        });
        const ring = new THREE.Mesh(ringGeometry, ringMaterial);
        ring.rotation.x = Math.PI / 2 + i * 0.3;
        ring.rotation.y = i * 0.5;
        scene.add(ring);
      }

      window.addEventListener('resize', onWindowResize);
      document.addEventListener('mousemove', onMouseMove);
    }

    function onWindowResize() {
      camera.aspect = window.innerWidth / window.innerHeight;
      camera.updateProjectionMatrix();
      renderer.setSize(window.innerWidth, window.innerHeight);
    }

    function onMouseMove(event) {
      mouseX = (event.clientX / window.innerWidth) * 2 - 1;
      mouseY = -(event.clientY / window.innerHeight) * 2 + 1;
    }

    function animate() {
      requestAnimationFrame(animate);

      camera.position.x += (mouseX * 10 - camera.position.x) * 0.05;
      camera.position.y += (mouseY * 10 - camera.position.y) * 0.05;
      camera.lookAt(scene.position);

      particles.rotation.y += 0.001;
      particles.rotation.x += 0.0005;

      scene.children.forEach(child => {
        if (child.type === 'Mesh' && child.material.wireframe) {
          child.rotation.y += 0.002;
        }
      });

      renderer.render(scene, camera);
    }

    initThree();
    animate();
  </script>
</body>
</html>
