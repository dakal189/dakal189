### Samp Info Bot (PHP + MySQL)

Bot skeleton implementing: multi-language (FA/EN/RU), force-join, main menu, skins search (ID/Name), inline like/share/favorite, admin panel basics for skins, and DB schema. Extend similarly for vehicles, colors, weathers, objects, weapons, maps.

Requirements:
- PHP 8.1+
- MySQL 8+
- Web server (Nginx/Apache) or PHP built-in server for local dev

Quick start:
1) Copy `.env.example` to `.env` and fill values.
2) Create DB and import `migrations/001_init.sql`.
3) Point your HTTPS webhook URL to `public/index.php` and set webhook:
```
curl -X POST "https://api.telegram.org/bot<YOUR_TOKEN>/setWebhook" \
  -d "url=https://your.domain.com/index.php?secret=<WEBHOOK_SECRET>"
```
4) Add your Telegram numeric ID to `admins` table as super admin:
```sql
INSERT INTO admins (user_id, is_super) VALUES (<YOUR_TELEGRAM_ID>, 1)
ON DUPLICATE KEY UPDATE is_super=1;
```
5) Optionally configure force-join channels:
```sql
INSERT INTO force_channels (chat_id, username, active) VALUES (-1001234567890, 'YourChannel', 1);
```

Local dev (no webhook): you can mimic webhook posts using curl:
```
php -S 0.0.0.0:8080 -t public
# Then POST a sample update to http://localhost:8080/index.php?secret=<WEBHOOK_SECRET>
```

Env vars (`.env`):
- `BOT_TOKEN`, `BOT_USERNAME`, `WEBHOOK_SECRET`
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `DEFAULT_LANG` (fa|en|ru)
- `FORCE_JOIN_REQUIRED` (true|false)

Notes:
- The skeleton fully implements Skins module; others are scaffolded to extend.
- Media is stored by Telegram `file_id` for efficiency.
- Callback data includes a nonce to reduce replay.

Structure:
```
public/          # webhook entry
app/
  Infra/         # DB, Env, Logger
  Telegram/      # Client, handler, keyboards, middleware
  Domain/        # Repos for users/skins/likes/favorites/admin
  I18n/          # fa/en/ru
  Bootstrap.php
config/
  config.php
migrations/
.env.example
```

Extend modules:
- Duplicate Skin repository/handlers pattern for vehicles, colors, etc.

# Telegram Bot Documentation

## Overview

This is a comprehensive Telegram bot written in PHP that provides internet data rewards and user management features. The bot manages user registrations, referral systems, point management, and admin controls for distributing internet data packages.

## Table of Contents

1. [Setup and Configuration](#setup-and-configuration)
2. [Core Functions](#core-functions)
3. [Public APIs](#public-apis)
4. [Bot Commands](#bot-commands)
5. [Admin Panel](#admin-panel)
6. [Data Management](#data-management)
7. [Usage Examples](#usage-examples)
8. [File Structure](#file-structure)

## Setup and Configuration

### Prerequisites

- PHP 7.0 or higher
- cURL extension enabled
- Write permissions for data directory
- Valid Telegram Bot Token

### Configuration

Update the following constants in `bot.php`:

```php
define('API_KEY','YOUR_BOT_TOKEN_HERE');
$Botid = 'YOUR_BOT_USERNAME';
$Channel = 'YOUR_CHANNEL_USERNAME';
$ADMIN = "YOUR_ADMIN_USER_ID";
```

### Required Directories

The bot automatically creates these directories:
- `data/` - User data storage
- `data/{user_id}/` - Individual user directories

## Core Functions

### API Communication

#### `tmsizdah($method, $datas = [])`

Main function for communicating with Telegram Bot API.

**Parameters:**
- `$method` (string): Telegram API method name
- `$datas` (array): Parameters to send with the request

**Returns:**
- `object`: JSON decoded response from Telegram API

**Example:**
```php
$response = tmsizdah('sendMessage', [
    'chat_id' => 123456789,
    'text' => 'Hello World!'
]);
```

### Message Functions

#### `SendMessage($chatid, $text, $parsmde, $disable_web_page_preview, $keyboard)`

Sends a text message to a chat.

**Parameters:**
- `$chatid` (int): Target chat ID
- `$text` (string): Message text
- `$parsmde` (string): Parse mode (HTML, MarkDown)
- `$disable_web_page_preview` (bool): Disable link previews
- `$keyboard` (string): JSON-encoded keyboard markup

**Example:**
```php
SendMessage(123456789, "Welcome to our bot!", "HTML", false, $menu);
```

#### `sendVideo($chat_id, $video, $caption, $keyboard)`

Sends a video message.

**Parameters:**
- `$chat_id` (int): Target chat ID
- `$video` (string): Video file ID or URL
- `$caption` (string): Video caption
- `$keyboard` (string): JSON-encoded keyboard markup

#### `SendPhoto($chat_id, $photo, $caption)`

Sends a photo message.

**Parameters:**
- `$chat_id` (int): Target chat ID
- `$photo` (string): Photo file ID or URL
- `$caption` (string): Photo caption

**Example:**
```php
SendPhoto(123456789, "http://example.com/image.jpg", "Sample image");
```

### Utility Functions

#### `sizdahorgg($KojaShe, $AzKoja, $KodomMSG)`

Forwards a message between chats.

**Parameters:**
- `$KojaShe` (int): Destination chat ID
- `$AzKoja` (int): Source chat ID  
- `$KodomMSG` (int): Message ID to forward

#### `save($filename, $data)`

Saves data to a file.

**Parameters:**
- `$filename` (string): File path
- `$data` (string): Data to save

#### `sendaction($chat_id, $action)`

Sends a chat action (typing, uploading, etc.).

**Parameters:**
- `$chat_id` (int): Target chat ID
- `$action` (string): Action type ('typing', 'upload_photo', etc.)

#### `EditMessageText($chat_id, $message_id, $text, $parse_mode, $disable_web_page_preview, $keyboard)`

Edits an existing message.

**Parameters:**
- `$chat_id` (int): Chat ID
- `$message_id` (int): Message ID to edit
- `$text` (string): New message text
- `$parse_mode` (string): Parse mode
- `$disable_web_page_preview` (bool): Disable link previews
- `$keyboard` (string): JSON-encoded keyboard markup

## Public APIs

### User Registration

The bot automatically registers new users when they send `/start` command.

**Process:**
1. User sends `/start`
2. Bot checks if user exists in `users.txt`
3. Creates user directory and initializes data files
4. Sends welcome message

### Referral System

Users can invite others using referral links.

**URL Format:**
```
https://t.me/{botusername}?start={referrer_user_id}
```

**Rewards:**
- Referrer gets +1 point
- New user gets 10 initial points

### Point System

Users earn and spend points for internet packages.

**Point Sources:**
- Initial registration: 0 points
- Referral registration: 10 points  
- Each successful referral: +1 point

**Point Requirements:**
- Internet package activation: 5 points

## Bot Commands

### User Commands

#### `/start`
Registers user and shows welcome message.

**Usage:**
```
/start
```

**Response:**
Welcome message with internet package instructions.

#### `/start {user_id}` (Referral)
Registers user via referral link.

**Usage:**
```
/start 123456789
```

#### `/internet`
Shows mobile operator selection menu.

**Usage:**
```
/internet
```

**Response:**
Keyboard with operator options: ایرانسل, همراه اول, رایتل

#### `/link`
Generates referral link for user.

**Usage:**
```
/link
```

**Response:**
Image with referral link and instructions.

### Interactive Commands

#### Operator Selection
After `/internet`, users can select their mobile operator.

**Options:**
- `ایرانسل` (Irancell)
- `همراه اول` (Hamrah-e Avval)
- `رایتل` (Rightel)

#### Status Check
`📊 راهنما و وضعیت من 📊` - Shows user status and referral count.

**Requirements:**
- Must be member of specified channel
- Shows current referral count
- Activates internet package if 5+ referrals

## Admin Panel

### Access
Admin panel is accessible only to the configured admin user ID.

#### `/tmsizdah`
Opens admin panel (admin only).

### Admin Commands

#### `امار` (Statistics)
Shows total user count.

**Usage:**
Admin types "امار"

**Response:**
Total registered users count.

#### `ارسال همگانی` (Broadcast Message)
Sends message to all users.

**Process:**
1. Admin selects "ارسال همگانی"
2. Admin types message
3. Bot sends to all registered users

#### `فروارد همگانی` (Broadcast Forward)
Forwards admin's message to all users.

**Process:**
1. Admin selects "فروارد همگانی"  
2. Admin sends/forwards message
3. Bot forwards to all users

#### `شماره کاربر` (Get User Phone)
Retrieves user's phone number.

**Process:**
1. Admin selects "شماره کاربر"
2. Admin enters user ID
3. Bot shows user's phone number

#### `اهدای امتیاز` (Add Points)
Adds points to user account.

**Process:**
1. Admin selects "اهدای امتیاز"
2. Admin enters user ID
3. Admin enters point amount
4. Points added to user

#### `کسر امتیاز` (Subtract Points)
Removes points from user account.

**Process:**
1. Admin selects "کسر امتیاز"
2. Admin enters user ID  
3. Admin enters point amount
4. Points subtracted from user

#### `پیام به کاربر` (Message User)
Sends private message to specific user.

**Process:**
1. Admin selects "پیام به کاربر"
2. Admin enters user ID
3. Admin types message
4. User receives notification with inbox button

## Data Management

### File Structure

```
data/
├── {user_id}/
│   ├── amir.txt        - Admin action state
│   ├── member.txt      - Membership data
│   ├── number.txt      - Phone number
│   ├── membrs.txt      - Referral count
│   ├── coin.txt        - Point balance
│   └── pasokh1.txt     - Admin message
├── pasokh.txt          - Temp admin data
└── users.txt           - All user IDs
```

### Data Files

#### `users.txt`
Contains all registered user IDs, one per line.

#### `data/{user_id}/membrs.txt`  
Stores user's referral count.

#### `data/{user_id}/coin.txt`
Stores user's point balance.

#### `data/{user_id}/number.txt`
Stores user's verified phone number.

#### `data/{user_id}/amir.txt`
Tracks admin panel states for operations.

## Usage Examples

### Basic User Flow

```php
// User starts bot
POST /webhook
{
    "message": {
        "text": "/start",
        "from": {"id": 123456789, "first_name": "John"},
        "chat": {"id": 123456789}
    }
}

// Bot response: Welcome message + registration
```

### Referral Registration

```php
// User clicks referral link
POST /webhook  
{
    "message": {
        "text": "/start 987654321",
        "from": {"id": 123456789},
        "chat": {"id": 123456789}
    }
}

// Bot: Registers user, awards points to referrer
```

### Internet Package Activation

```php
// User checks status with 5+ referrals
POST /webhook
{
    "message": {
        "text": "📊 راهنما و وضعیت من 📊",
        "from": {"id": 123456789},
        "chat": {"id": 123456789}
    }
}

// Bot: Deducts 4 points, notifies admin for activation
```

### Admin Broadcast

```php
// Admin sends broadcast
POST /webhook
{
    "message": {
        "text": "ارسال همگانی",
        "from": {"id": 5641303137}, // Admin ID
        "chat": {"id": 5641303137}
    }
}

// Admin types message
POST /webhook
{
    "message": {
        "text": "Important announcement!",
        "from": {"id": 5641303137},
        "chat": {"id": 5641303137}
    }
}

// Bot: Sends to all users in users.txt
```

## Security Features

### Access Control
- Admin commands restricted to configured admin ID
- Channel membership required for main features
- Phone number verification required

### Data Protection
- User data stored in separate directories
- Automatic file handling with proper permissions
- Error logging disabled in production

## Error Handling

The bot includes error handling for:
- cURL communication failures
- File system operations
- Missing user data
- Invalid admin operations

## API Integration

### External APIs Used

#### Date/Time API
```php
$dt = "http://api.mostafa-am.ir/date-time/";
$jd_dt = json_decode(file_get_contents($dt), true);
$time = $jd_dt['time_en'];
$date = $jd_dt['date_fa_num_en'];
```

#### Telegram Bot API
All bot interactions use official Telegram Bot API endpoints:
- `sendMessage`
- `sendPhoto` 
- `sendVideo`
- `editMessageText`
- `forwardMessage`
- `sendChatAction`
- `getChatMember`

## Deployment

### Webhook Setup

1. Set webhook URL with Telegram:
```bash
curl -X POST "https://api.telegram.org/bot{BOT_TOKEN}/setWebhook" \
     -d "url=https://yourserver.com/bot.php"
```

2. Ensure proper file permissions:
```bash
chmod 755 bot.php
chmod 777 data/
```

3. Test webhook:
```bash
curl -X POST "https://api.telegram.org/bot{BOT_TOKEN}/getWebhookInfo"
```

## Troubleshooting

### Common Issues

1. **Bot not responding**: Check webhook URL and bot token
2. **File permission errors**: Ensure data directory is writable
3. **Admin panel not working**: Verify admin user ID in configuration
4. **Channel check failing**: Confirm channel username is correct

### Debug Mode

To enable debugging, modify the error reporting:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Contributing

When extending this bot:

1. Follow existing function naming patterns
2. Add proper error handling
3. Update documentation for new features
4. Test all user flows thoroughly
5. Maintain security practices

## License

This bot is provided as-is for educational and development purposes. Please ensure compliance with Telegram's Terms of Service and Bot API guidelines.

---

**Telegram Channel**: @tmsizdah  
**YouTube**: @13Learn  
**Contact**: @sizdahorgg