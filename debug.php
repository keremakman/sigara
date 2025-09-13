<?php
// Debug dosyası - HTTP 500 hatasını bulmak için
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug Bilgileri</h1>";

// PHP versiyonu
echo "<h2>PHP Versiyonu:</h2>";
echo phpversion() . "<br>";

// Gerekli uzantılar
echo "<h2>Gerekli Uzantılar:</h2>";
echo "PDO: " . (extension_loaded('pdo') ? '✓' : '✗') . "<br>";
echo "PDO MySQL: " . (extension_loaded('pdo_mysql') ? '✓' : '✗') . "<br>";
echo "Session: " . (extension_loaded('session') ? '✓' : '✗') . "<br>";

// Config dosyası test
echo "<h2>Config Dosyası Test:</h2>";
try {
    $config = require 'config.php';
    echo "Config dosyası başarıyla yüklendi ✓<br>";
    echo "Veritabanı host: " . $config['db']['host'] . "<br>";
    echo "Veritabanı adı: " . $config['db']['name'] . "<br>";
    echo "Veritabanı kullanıcı: " . $config['db']['user'] . "<br>";
} catch (Exception $e) {
    echo "Config hatası: " . $e->getMessage() . " ✗<br>";
}

// Veritabanı bağlantı testi
echo "<h2>Veritabanı Bağlantı Testi:</h2>";
try {
    $config = require 'config.php';
    $db = $config['db'];
    $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset={$db['charset']}";
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "Veritabanı bağlantısı başarılı ✓<br>";
    
    // Tabloları kontrol et
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Mevcut tablolar: " . implode(', ', $tables) . "<br>";
    
} catch (Exception $e) {
    echo "Veritabanı hatası: " . $e->getMessage() . " ✗<br>";
}

// Bootstrap dosyası test
echo "<h2>Bootstrap Dosyası Test:</h2>";
try {
    require_once 'bootstrap.php';
    echo "Bootstrap dosyası başarıyla yüklendi ✓<br>";
} catch (Exception $e) {
    echo "Bootstrap hatası: " . $e->getMessage() . " ✗<br>";
}

// Dosya izinleri
echo "<h2>Dosya İzinleri:</h2>";
$files = ['config.php', 'bootstrap.php', 'db.php', 'index.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "$file: " . substr(sprintf('%o', fileperms($file)), -4) . " ✓<br>";
    } else {
        echo "$file: Dosya bulunamadı ✗<br>";
    }
}

echo "<h2>Sonuç:</h2>";
echo "Debug tamamlandı. Yukarıdaki hataları kontrol edin.<br>";
?>
