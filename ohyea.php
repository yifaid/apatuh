<?php
// notifier.php - Website Access Notifier with Mozilla User Agent

// Konfigurasi - GANTI DENGAN TOKEN DAN CHAT ID ANDA!
$BOT_TOKEN = "8722546918:AAFUgdwi3fPoV4Pcg9MbjzcVHy-lViu4BFc"; // GANTI INI!
$CHAT_ID = "8600700974"; // GANTI INI!

// Auto detect nama website
$website_name = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'Unknown Website';

// Auto detect URL lengkap
$full_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
            "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

// Kumpulin data
$ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$time = date('Y-m-d H:i:s');
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

// Kirim ke Telegram dengan User Agent Mozilla
$message = "ðŸ”” *Website Access*\n";
$message .= "ðŸ“Œ *Nama:* $website_name\n";
$message .= "ðŸ”— *URL:* $full_url\n";
$message .= "ðŸ–¥ï¸ *IP:* $ip\n";
$message .= "â° *Waktu:* $time\n";
$message .= "ðŸ“± *User Agent:* $user_agent";

$url = "https://api.telegram.org/bot$BOT_TOKEN/sendMessage";
$data = [
    'chat_id' => $CHAT_ID,
    'text' => $message,
    'parse_mode' => 'Markdown'
];

// Setup context dengan User Agent Mozilla
$options = [
    'http' => [
        'header' => "Content-type: application/x-www-form-urlencoded\r\n" .
                    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n",
        'method' => 'POST',
        'content' => http_build_query($data),
        'timeout' => 30
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
];

$context = stream_context_create($options);

// Eksekusi dengan error handling
try {
    $result = file_get_contents($url, false, $context);
    
    if ($result === false) {
        throw new Exception("Gagal mengirim notifikasi");
    }
    
    // Log simpel
    $log = date('Y-m-d H:i:s') . " | $website_name | $ip | $full_url | " . 
           "User-Agent: " . substr($user_agent, 0, 50) . "...\n";
    file_put_contents('access.log', $log, FILE_APPEND);
    
    echo "âœ… Notifikasi terkirim ke Telegram!";
    
} catch (Exception $e) {
    // Log error
    $error_log = date('Y-m-d H:i:s') . " | ERROR: " . $e->getMessage() . " | $full_url\n";
    file_put_contents('error.log', $error_log, FILE_APPEND);
    echo "âŒ Gagal mengirim notifikasi. Cek log untuk detail.";
}
?>
