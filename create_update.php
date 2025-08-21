<?php
/**
 * اسکریپت ایجاد فایل آپدیت
 * این فایل برای ایجاد فایل ZIP آپدیت استفاده می‌شود
 */

// تنظیمات
$update_version = "1.0.1";
$update_name = "update_v{$update_version}.zip";
$files_to_include = [
    'bot.php',
    'handler.php', 
    'config.php',
    'index.php',
    'version.json'
];

// ایجاد فایل ZIP
$zip = new ZipArchive();
if ($zip->open($update_name, ZipArchive::CREATE) === TRUE) {
    
    echo "🔄 در حال ایجاد فایل آپدیت...\n";
    
    // اضافه کردن فایل‌ها
    foreach ($files_to_include as $file) {
        if (file_exists($file)) {
            $zip->addFile($file, $file);
            echo "✅ فایل $file اضافه شد\n";
        } else {
            echo "❌ فایل $file یافت نشد\n";
        }
    }
    
    // به‌روزرسانی version.json در فایل آپدیت
    $version_data = json_decode(file_get_contents('version.json'), true);
    $version_data['version'] = $update_version;
    $version_data['release_date'] = date('Y-m-d');
    
    $zip->addFromString('version.json', json_encode($version_data, JSON_PRETTY_PRINT));
    
    $zip->close();
    
    echo "\n✅ فایل آپدیت با موفقیت ایجاد شد: $update_name\n";
    echo "📦 نسخه: $update_version\n";
    echo "📅 تاریخ: " . date('Y-m-d H:i:s') . "\n";
    echo "📏 حجم: " . number_format(filesize($update_name) / 1024, 2) . " KB\n";
    
} else {
    echo "❌ خطا در ایجاد فایل آپدیت\n";
}

echo "\n📋 راهنمای استفاده:\n";
echo "1. فایل $update_name را در هاست آپلود کنید\n";
echo "2. آدرس فایل را در version.json تنظیم کنید\n";
echo "3. از پنل مدیریت ربات آپدیت کنید\n";
?>