# Deployment and Configuration Guide

## Prerequisites

### System Requirements

**Minimum Requirements:**
- PHP 7.4 or higher
- Web server (Apache/Nginx)
- SSL certificate (HTTPS required for webhooks)
- 512MB RAM
- 1GB disk space
- cURL extension enabled

**Recommended Requirements:**
- PHP 8.0+
- 2GB RAM
- 5GB disk space  
- Redis/Memcached for caching
- Backup solution

### PHP Extensions

Ensure these PHP extensions are installed:
```bash
# Ubuntu/Debian
sudo apt-get install php-curl php-json php-mbstring php-xml

# CentOS/RHEL
sudo yum install php-curl php-json php-mbstring php-xml
```

## Installation

### 1. Server Setup

#### Apache Configuration
```apache
<VirtualHost *:443>
    ServerName yourdomain.com
    DocumentRoot /var/www/html/bot
    
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    
    <Directory "/var/www/html/bot">
        AllowOverride All
        Require all granted
    </Directory>
    
    # Security headers
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
</VirtualHost>
```

#### Nginx Configuration
```nginx
server {
    listen 443 ssl;
    server_name yourdomain.com;
    
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    
    root /var/www/html/bot;
    index bot.php;
    
    location / {
        try_files $uri $uri/ =404;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index bot.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Security
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
}
```

### 2. File Deployment

```bash
# Create project directory
sudo mkdir -p /var/www/html/bot
cd /var/www/html/bot

# Copy bot files
sudo cp /path/to/bot.php .
sudo cp /path/to/config.php .

# Create data directory with proper permissions
sudo mkdir -p data backups logs
sudo chown -R www-data:www-data data backups logs
sudo chmod -R 755 data backups logs

# Set file permissions
sudo chown www-data:www-data *.php
sudo chmod 644 *.php
```

### 3. Configuration

#### Create config.php
```php
<?php
// config.php - Separate configuration file

// Bot Configuration
define('API_KEY', 'YOUR_BOT_TOKEN_HERE');
define('BOT_USERNAME', 'YOUR_BOT_USERNAME');
define('ADMIN_ID', 'YOUR_ADMIN_USER_ID');
define('CHANNEL_USERNAME', 'YOUR_CHANNEL_USERNAME');

// Database Configuration (if using database)
define('DB_HOST', 'localhost');
define('DB_NAME', 'bot_database');
define('DB_USER', 'bot_user');
define('DB_PASS', 'secure_password');

// Security Settings
define('WEBHOOK_SECRET', 'random_secret_key_here');
define('RATE_LIMIT_ENABLED', true);
define('DEBUG_MODE', false);

// File Paths
define('DATA_DIR', __DIR__ . '/data/');
define('LOG_DIR', __DIR__ . '/logs/');
define('BACKUP_DIR', __DIR__ . '/backups/');

// External APIs
define('DATE_API_URL', 'http://api.mostafa-am.ir/date-time/');

// Feature Flags
define('REFERRAL_ENABLED', true);
define('POINTS_ENABLED', true);
define('CHANNEL_CHECK_ENABLED', true);

// Point System Configuration
define('REFERRAL_BONUS', 10);
define('NEW_USER_BONUS', 5);
define('ACTIVATION_COST', 5);

// Rate Limiting
define('MAX_MESSAGES_PER_MINUTE', 20);
define('MAX_MESSAGES_PER_HOUR', 100);
?>
```

#### Update bot.php
```php
<?php
// Include configuration
require_once 'config.php';

// Override hardcoded values with config
// Replace: define('API_KEY','7485518963:AAHJVhgBR49wXP0LiIn5-m5ta1bgl8qnefI');
// With: (already defined in config.php)

// Replace: $ADMIN = "5641303137";
// With: $ADMIN = ADMIN_ID;

// Continue with existing bot.php code...
?>
```

## Webhook Setup

### 1. Set Webhook URL

```bash
# Set webhook
curl -X POST "https://api.telegram.org/bot{YOUR_BOT_TOKEN}/setWebhook" \
     -d "url=https://yourdomain.com/bot.php" \
     -d "secret_token=YOUR_SECRET_TOKEN"

# Verify webhook
curl -X POST "https://api.telegram.org/bot{YOUR_BOT_TOKEN}/getWebhookInfo"
```

### 2. Webhook Security

Add webhook verification to bot.php:
```php
<?php
// Verify webhook secret
if (defined('WEBHOOK_SECRET')) {
    $secret_token = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if ($secret_token !== WEBHOOK_SECRET) {
        http_response_code(403);
        exit('Forbidden');
    }
}

// Continue with existing code...
?>
```

## Security Configuration

### 1. File Permissions

```bash
# Secure file permissions
find /var/www/html/bot -type f -name "*.php" -exec chmod 644 {} \;
find /var/www/html/bot -type d -exec chmod 755 {} \;

# Secure data directory
chmod 700 /var/www/html/bot/data
chown -R www-data:www-data /var/www/html/bot/data
```

### 2. Apache Security (.htaccess)

```apache
# .htaccess in project root
RewriteEngine On

# Prevent access to sensitive files
<FilesMatch "\.(txt|log|json)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Prevent access to data directory
RedirectMatch 403 ^/bot/data/.*$

# Rate limiting (if mod_security is available)
<IfModule mod_security.c>
    SecRule IP:REQUEST_COUNT "@gt 100" \
        "phase:1,block,msg:'Rate limit exceeded',id:123"
</IfModule>

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=31536000"
```

### 3. Input Validation

Add to bot.php:
```php
<?php
// Input validation functions
function validateUserId($user_id) {
    return is_numeric($user_id) && $user_id > 0 && strlen($user_id) <= 15;
}

function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $filename);
}

function validateChatId($chat_id) {
    return is_numeric($chat_id) && abs($chat_id) <= 1000000000000000;
}

// Usage throughout the code
if (!validateUserId($from_id)) {
    exit('Invalid user ID');
}

$safe_filename = sanitizeFilename("user_$from_id.txt");
?>
```

## Database Setup (Optional)

### 1. MySQL Schema

```sql
-- Create database
CREATE DATABASE bot_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER 'bot_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON bot_database.* TO 'bot_user'@'localhost';
FLUSH PRIVILEGES;

-- Use database
USE bot_database;

-- Users table
CREATE TABLE users (
    id BIGINT PRIMARY KEY,
    first_name VARCHAR(255) NOT NULL,
    username VARCHAR(255),
    phone VARCHAR(20),
    points INT DEFAULT 0,
    referrals INT DEFAULT 0,
    status ENUM('active', 'blocked', 'banned') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

-- Referrals table
CREATE TABLE referrals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_id BIGINT NOT NULL,
    referred_id BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES users(id),
    FOREIGN KEY (referred_id) REFERENCES users(id),
    UNIQUE KEY unique_referral (referrer_id, referred_id)
);

-- Transactions table
CREATE TABLE point_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    amount INT NOT NULL,
    type ENUM('earn', 'spend', 'bonus', 'referral') NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_date (user_id, created_at)
);

-- Settings table
CREATE TABLE bot_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO bot_settings (setting_key, setting_value) VALUES
('referral_bonus', '10'),
('new_user_bonus', '5'),
('activation_cost', '5'),
('maintenance_mode', 'false');
```

### 2. Database Integration

Create database.php:
```php
<?php
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function getUser($user_id) {
        $stmt = $this->connection->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }
    
    public function createUser($user_data) {
        $stmt = $this->connection->prepare(
            "INSERT INTO users (id, first_name, username, phone) VALUES (?, ?, ?, ?)"
        );
        return $stmt->execute([
            $user_data['id'],
            $user_data['first_name'],
            $user_data['username'],
            $user_data['phone']
        ]);
    }
    
    public function addPoints($user_id, $amount, $type, $description = '') {
        $this->connection->beginTransaction();
        
        try {
            // Update user points
            $stmt = $this->connection->prepare(
                "UPDATE users SET points = points + ? WHERE id = ?"
            );
            $stmt->execute([$amount, $user_id]);
            
            // Log transaction
            $stmt = $this->connection->prepare(
                "INSERT INTO point_transactions (user_id, amount, type, description) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$user_id, $amount, $type, $description]);
            
            $this->connection->commit();
            return true;
        } catch (Exception $e) {
            $this->connection->rollback();
            error_log("Transaction failed: " . $e->getMessage());
            return false;
        }
    }
}
?>
```

## Monitoring and Logging

### 1. Error Logging

Create logging.php:
```php
<?php
class Logger {
    private static $log_file;
    
    public static function init($log_file = null) {
        self::$log_file = $log_file ?: LOG_DIR . 'bot_' . date('Y-m-d') . '.log';
    }
    
    public static function log($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] [$level] $message";
        
        if (!empty($context)) {
            $log_entry .= ' ' . json_encode($context);
        }
        
        $log_entry .= PHP_EOL;
        
        file_put_contents(self::$log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    public static function info($message, $context = []) {
        self::log('INFO', $message, $context);
    }
    
    public static function error($message, $context = []) {
        self::log('ERROR', $message, $context);
    }
    
    public static function warning($message, $context = []) {
        self::log('WARNING', $message, $context);
    }
}

// Initialize logger
Logger::init();
?>
```

### 2. Health Check Endpoint

Create health.php:
```php
<?php
require_once 'config.php';

header('Content-Type: application/json');

$health = [
    'status' => 'ok',
    'timestamp' => time(),
    'checks' => []
];

// Check data directory
$health['checks']['data_directory'] = is_writable(DATA_DIR) ? 'ok' : 'fail';

// Check bot token
$test_response = @file_get_contents("https://api.telegram.org/bot" . API_KEY . "/getMe");
$health['checks']['telegram_api'] = $test_response !== false ? 'ok' : 'fail';

// Check disk space
$free_bytes = disk_free_space('.');
$health['checks']['disk_space'] = $free_bytes > 100000000 ? 'ok' : 'warn'; // 100MB

// Overall status
$health['status'] = in_array('fail', $health['checks']) ? 'fail' : 'ok';

http_response_code($health['status'] === 'ok' ? 200 : 503);
echo json_encode($health, JSON_PRETTY_PRINT);
?>
```

### 3. Monitoring Script

Create monitor.sh:
```bash
#!/bin/bash

# Monitoring script for bot health
BOT_URL="https://yourdomain.com/health.php"
LOG_FILE="/var/log/bot_monitor.log"
ALERT_EMAIL="admin@yourdomain.com"

# Check bot health
response=$(curl -s -w "%{http_code}" "$BOT_URL")
http_code="${response: -3}"

timestamp=$(date '+%Y-%m-%d %H:%M:%S')

if [ "$http_code" -eq 200 ]; then
    echo "[$timestamp] Bot health check: OK" >> "$LOG_FILE"
else
    echo "[$timestamp] Bot health check: FAILED (HTTP $http_code)" >> "$LOG_FILE"
    
    # Send alert email
    echo "Bot health check failed at $timestamp. HTTP code: $http_code" | \
        mail -s "Bot Alert: Health Check Failed" "$ALERT_EMAIL"
fi

# Check disk usage
disk_usage=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
if [ "$disk_usage" -gt 80 ]; then
    echo "[$timestamp] Disk usage warning: ${disk_usage}%" >> "$LOG_FILE"
    echo "Disk usage is at ${disk_usage}%" | \
        mail -s "Bot Alert: High Disk Usage" "$ALERT_EMAIL"
fi

# Check log file size
log_size=$(stat -f%z "$LOG_FILE" 2>/dev/null || stat -c%s "$LOG_FILE" 2>/dev/null)
if [ "$log_size" -gt 10485760 ]; then  # 10MB
    # Rotate log
    mv "$LOG_FILE" "${LOG_FILE}.$(date +%Y%m%d)"
    touch "$LOG_FILE"
fi
```

Add to crontab:
```bash
# Check every 5 minutes
*/5 * * * * /path/to/monitor.sh

# Daily backup
0 2 * * * /path/to/backup.sh
```

## Backup and Recovery

### 1. Backup Script

Create backup.sh:
```bash
#!/bin/bash

BACKUP_DIR="/var/backups/bot"
BOT_DIR="/var/www/html/bot"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Backup files
tar -czf "$BACKUP_DIR/bot_files_$DATE.tar.gz" \
    --exclude="$BOT_DIR/logs/*" \
    --exclude="$BOT_DIR/backups/*" \
    -C "$(dirname $BOT_DIR)" \
    "$(basename $BOT_DIR)"

# Backup database (if using MySQL)
if command -v mysqldump &> /dev/null; then
    mysqldump -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" | \
        gzip > "$BACKUP_DIR/bot_database_$DATE.sql.gz"
fi

# Keep only last 7 days of backups
find "$BACKUP_DIR" -name "bot_*" -mtime +7 -delete

echo "Backup completed: $DATE"
```

### 2. Recovery Procedure

```bash
# Restore files
cd /var/www/html
sudo tar -xzf /var/backups/bot/bot_files_YYYYMMDD_HHMMSS.tar.gz

# Restore database
gunzip < /var/backups/bot/bot_database_YYYYMMDD_HHMMSS.sql.gz | \
    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME"

# Set permissions
sudo chown -R www-data:www-data bot/
sudo chmod -R 755 bot/
```

## Performance Optimization

### 1. PHP Configuration

php.ini optimizations:
```ini
; Memory and execution
memory_limit = 256M
max_execution_time = 30
max_input_time = 30

; File uploads (if needed)
upload_max_filesize = 10M
post_max_size = 10M

; Error reporting (production)
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

; Session (if using sessions)
session.gc_maxlifetime = 1440
session.gc_probability = 1
session.gc_divisor = 100

; OPcache (if available)
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 2
```

### 2. Rate Limiting

Add rate limiting to bot.php:
```php
<?php
function checkRateLimit($user_id) {
    $rate_file = DATA_DIR . "rate_$user_id.txt";
    $current_time = time();
    
    // Read existing rate data
    $rate_data = [];
    if (file_exists($rate_file)) {
        $rate_data = json_decode(file_get_contents($rate_file), true) ?: [];
    }
    
    // Clean old entries (older than 1 hour)
    $rate_data = array_filter($rate_data, function($timestamp) use ($current_time) {
        return ($current_time - $timestamp) < 3600;
    });
    
    // Check limits
    $messages_last_minute = count(array_filter($rate_data, function($timestamp) use ($current_time) {
        return ($current_time - $timestamp) < 60;
    }));
    
    $messages_last_hour = count($rate_data);
    
    if ($messages_last_minute > MAX_MESSAGES_PER_MINUTE || 
        $messages_last_hour > MAX_MESSAGES_PER_HOUR) {
        return false;
    }
    
    // Add current request
    $rate_data[] = $current_time;
    file_put_contents($rate_file, json_encode($rate_data));
    
    return true;
}

// Use at the beginning of message processing
if (RATE_LIMIT_ENABLED && !checkRateLimit($from_id)) {
    SendMessage($chat_id, "Too many requests. Please wait.", "", false, "");
    exit;
}
?>
```

## Maintenance

### 1. Regular Tasks

Create maintenance.php:
```php
<?php
require_once 'config.php';
require_once 'logging.php';

// Clean old log files
function cleanOldLogs() {
    $log_files = glob(LOG_DIR . '*.log');
    $cutoff_time = time() - (30 * 24 * 60 * 60); // 30 days
    
    foreach ($log_files as $file) {
        if (filemtime($file) < $cutoff_time) {
            unlink($file);
            Logger::info("Deleted old log file: $file");
        }
    }
}

// Clean old rate limit files
function cleanRateLimits() {
    $rate_files = glob(DATA_DIR . 'rate_*.txt');
    $cutoff_time = time() - (24 * 60 * 60); // 24 hours
    
    foreach ($rate_files as $file) {
        if (filemtime($file) < $cutoff_time) {
            unlink($file);
        }
    }
}

// Optimize data files
function optimizeDataFiles() {
    // Remove empty user directories
    $user_dirs = glob(DATA_DIR . '*/');
    
    foreach ($user_dirs as $dir) {
        if (count(scandir($dir)) <= 2) { // Only . and ..
            rmdir($dir);
        }
    }
}

// Run maintenance tasks
if (php_sapi_name() === 'cli') {
    Logger::info("Starting maintenance tasks");
    
    cleanOldLogs();
    cleanRateLimits();
    optimizeDataFiles();
    
    Logger::info("Maintenance tasks completed");
} else {
    echo "This script must be run from command line.";
}
?>
```

### 2. Update Procedure

```bash
#!/bin/bash
# update.sh - Safe update procedure

BOT_DIR="/var/www/html/bot"
BACKUP_DIR="/tmp/bot_backup_$(date +%Y%m%d_%H%M%S)"

echo "Starting bot update..."

# Create backup
echo "Creating backup..."
cp -r "$BOT_DIR" "$BACKUP_DIR"

# Download new version
echo "Downloading updates..."
# wget -O new_bot.php https://example.com/updates/bot.php

# Test configuration
echo "Testing configuration..."
php -l new_bot.php
if [ $? -ne 0 ]; then
    echo "Syntax error in new version. Aborting."
    exit 1
fi

# Deploy new version
echo "Deploying update..."
cp new_bot.php "$BOT_DIR/bot.php"

# Test webhook
echo "Testing webhook..."
response=$(curl -s -o /dev/null -w "%{http_code}" "https://yourdomain.com/health.php")
if [ "$response" -eq 200 ]; then
    echo "Update successful!"
    rm -rf "$BACKUP_DIR"
else
    echo "Update failed. Rolling back..."
    cp "$BACKUP_DIR/bot.php" "$BOT_DIR/bot.php"
    echo "Rollback completed."
fi
```

This comprehensive deployment guide covers all aspects of setting up, securing, monitoring, and maintaining the Telegram bot in a production environment.