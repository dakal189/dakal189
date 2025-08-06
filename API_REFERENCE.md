# API Reference

## Core API Functions

### Communication Layer

#### `tmsizdah($method, $datas = [])`

**Description:** Primary communication interface with Telegram Bot API

**Signature:**
```php
function tmsizdah(string $method, array $datas = []): object|null
```

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$method` | string | Yes | Telegram Bot API method name |
| `$datas` | array | No | Parameters for the API call |

**Return Value:**
- `object`: JSON decoded response from Telegram API
- `null`: On cURL error

**Error Handling:**
- Outputs cURL errors via `var_dump()`
- Returns `null` on communication failure

**Example Usage:**
```php
// Send a simple message
$response = tmsizdah('sendMessage', [
    'chat_id' => 123456789,
    'text' => 'Hello World!'
]);

// Get chat information
$chatInfo = tmsizdah('getChat', [
    'chat_id' => '@channel_username'
]);
```

**Internal Implementation:**
- Uses cURL with POST method
- Sets `CURLOPT_RETURNTRANSFER` to true
- Automatically JSON decodes response

---

## Messaging Functions

### `SendMessage($chatid, $text, $parsmde, $disable_web_page_preview, $keyboard)`

**Description:** Sends text messages with full formatting and keyboard support

**Signature:**
```php
function SendMessage(int $chatid, string $text, string $parsmde, bool $disable_web_page_preview, string $keyboard): void
```

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$chatid` | int | Yes | Target chat/user ID |
| `$text` | string | Yes | Message text (max 4096 chars) |
| `$parsmde` | string | Yes | Parse mode ('HTML', 'MarkDown', or empty) |
| `$disable_web_page_preview` | bool | Yes | Disable link previews |
| `$keyboard` | string | Yes | JSON-encoded keyboard markup |

**Supported Parse Modes:**
- `'HTML'`: HTML formatting
- `'MarkDown'`: Markdown formatting  
- `''`: Plain text

**Keyboard Types:**
- Reply keyboards: `json_encode(['keyboard' => [...]])`
- Inline keyboards: `json_encode(['inline_keyboard' => [...]])`
- Remove keyboard: `json_encode(['remove_keyboard' => true])`

**Example Usage:**
```php
// Simple text message
SendMessage(123456789, "Hello!", "", false, "");

// HTML formatted message with keyboard
$keyboard = json_encode([
    'keyboard' => [
        [['text' => 'Button 1'], ['text' => 'Button 2']]
    ],
    'resize_keyboard' => true
]);
SendMessage(123456789, "<b>Bold text</b>", "HTML", false, $keyboard);

// Inline keyboard message
$inline_kb = json_encode([
    'inline_keyboard' => [
        [['text' => 'Click me', 'callback_data' => 'button_clicked']]
    ]
]);
SendMessage(123456789, "Choose an option:", "", false, $inline_kb);
```

---

### `sendVideo($chat_id, $video, $caption, $keyboard)`

**Description:** Sends video files with optional captions and keyboards

**Signature:**
```php
function sendVideo(int $chat_id, string $video, string $caption, string $keyboard): void
```

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$chat_id` | int | Yes | Target chat/user ID |
| `$video` | string | Yes | Video file ID, URL, or file path |
| `$caption` | string | No | Video caption (max 1024 chars) |
| `$keyboard` | string | No | JSON-encoded keyboard markup |

**Video Sources:**
- File ID: `"BAADBAADrwADBREAAYdaXCvoGt1mAg"`
- HTTP URL: `"https://example.com/video.mp4"`
- Local file: `"@/path/to/video.mp4"`

**Example Usage:**
```php
// Send video with caption
sendVideo(123456789, "BAADBAADrwADBREAAYdaXCvoGt1mAg", "My video", "");

// Send video with inline keyboard
$keyboard = json_encode([
    'inline_keyboard' => [
        [['text' => 'Download', 'url' => 'https://example.com/download']]
    ]
]);
sendVideo(123456789, "https://example.com/video.mp4", "Watch this!", $keyboard);
```

---

### `SendPhoto($chat_id, $photo, $caption)`

**Description:** Sends photo files with optional captions

**Signature:**
```php
function SendPhoto(int $chat_id, string $photo, string $caption): void
```

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$chat_id` | int | Yes | Target chat/user ID |
| `$photo` | string | Yes | Photo file ID, URL, or file path |
| `$caption` | string | No | Photo caption (max 1024 chars) |

**Photo Sources:**
- File ID: `"AgACAgIAAxkBAAICOmF..."`
- HTTP URL: `"https://example.com/photo.jpg"`
- Local file: `"@/path/to/photo.jpg"`

**Example Usage:**
```php
// Send photo with caption
SendPhoto(123456789, "https://example.com/image.jpg", "Beautiful sunset");

// Send photo without caption
SendPhoto(123456789, "AgACAgIAAxkBAAICOmF...", "");
```

---

### `EditMessageText($chat_id, $message_id, $text, $parse_mode, $disable_web_page_preview, $keyboard)`

**Description:** Edits text content of existing messages

**Signature:**
```php
function EditMessageText(int $chat_id, int $message_id, string $text, string $parse_mode, bool $disable_web_page_preview, string $keyboard): void
```

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$chat_id` | int | Yes | Chat ID containing the message |
| `$message_id` | int | Yes | ID of message to edit |
| `$text` | string | Yes | New message text |
| `$parse_mode` | string | No | Parse mode ('HTML', 'MarkDown') |
| `$disable_web_page_preview` | bool | No | Disable link previews |
| `$keyboard` | string | No | JSON-encoded keyboard markup |

**Limitations:**
- Can only edit text messages
- Message must be less than 48 hours old
- Bot must have sent the original message

**Example Usage:**
```php
// Edit message text only
EditMessageText(123456789, 456, "Updated content", "", false, "");

// Edit with new keyboard
$newKeyboard = json_encode([
    'inline_keyboard' => [
        [['text' => 'New Button', 'callback_data' => 'new_action']]
    ]
]);
EditMessageText(123456789, 456, "Choose option:", "HTML", false, $newKeyboard);
```

---

## Utility Functions

### `sizdahorgg($KojaShe, $AzKoja, $KodomMSG)`

**Description:** Forwards messages between chats

**Signature:**
```php
function sizdahorgg(int $KojaShe, int $AzKoja, int $KodomMSG): void
```

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$KojaShe` | int | Yes | Destination chat ID |
| `$AzKoja` | int | Yes | Source chat ID |
| `$KodomMSG` | int | Yes | Message ID to forward |

**Usage Notes:**
- Preserves original message formatting
- Shows "Forwarded from" attribution
- Works with any message type (text, photo, video, etc.)

**Example Usage:**
```php
// Forward message from user to admin
sizdahorgg($ADMIN, $user_id, $message_id);

// Forward to multiple chats
$recipients = [123456789, 987654321];
foreach($recipients as $chat_id) {
    sizdahorgg($chat_id, $source_chat, $message_id);
}
```

---

### `save($filename, $data)`

**Description:** Writes data to file with automatic handling

**Signature:**
```php
function save(string $filename, string $data): void
```

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$filename` | string | Yes | File path (relative or absolute) |
| `$data` | string | Yes | Data to write |

**Behavior:**
- Overwrites existing files
- Creates new files if they don't exist
- Requires write permissions in target directory

**Example Usage:**
```php
// Save user data
save("data/123456789/points.txt", "50");

// Save configuration
save("config.txt", "admin_id=123456789\napi_key=abc123");
```

---

### `sendaction($chat_id, $action)`

**Description:** Sends chat actions to show bot activity

**Signature:**
```php
function sendaction(int $chat_id, string $action): void
```

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$chat_id` | int | Yes | Target chat ID |
| `$action` | string | Yes | Action type |

**Available Actions:**
- `'typing'`: Typing text message
- `'upload_photo'`: Uploading photo
- `'record_video'`: Recording video
- `'upload_video'`: Uploading video
- `'record_voice'`: Recording voice message
- `'upload_voice'`: Uploading voice message
- `'upload_document'`: Uploading document
- `'choose_sticker'`: Choosing sticker
- `'find_location'`: Finding location
- `'record_video_note'`: Recording video note
- `'upload_video_note'`: Uploading video note

**Example Usage:**
```php
// Show typing indicator before sending message
sendaction(123456789, 'typing');
sleep(2); // Simulate processing time
SendMessage(123456789, "Here's your response!", "", false, "");

// Show upload indicator before sending photo
sendaction(123456789, 'upload_photo');
SendPhoto(123456789, "photo.jpg", "Your requested image");
```

---

## Data Access Functions

### File Operations

#### Reading User Data
```php
// Get user referral count
$referrals = file_get_contents("data/$user_id/membrs.txt");

// Get user points
$points = file_get_contents("data/$user_id/coin.txt");

// Get user phone number
$phone = file_get_contents("data/$user_id/number.txt");
```

#### Writing User Data
```php
// Update referral count
file_put_contents("data/$user_id/membrs.txt", $new_count);

// Add points
$current_points = file_get_contents("data/$user_id/coin.txt");
file_put_contents("data/$user_id/coin.txt", $current_points + $bonus);

// Set admin state
file_put_contents("data/$user_id/amir.txt", "broadcast_mode");
```

---

## Error Handling

### cURL Errors
```php
if(curl_error($ch)){
    var_dump(curl_error($ch));
    return null;
}
```

### File Operations
```php
// Safe file reading
$data = @file_get_contents($filename);
if($data === false) {
    $data = "0"; // Default value
}

// Directory creation
@mkdir("data/$user_id", 0777, true);
```

---

## Rate Limiting

### Best Practices
- Maximum 30 messages per second to same chat
- Maximum 20 messages per minute to different chats
- Use `sendaction()` before long operations
- Batch operations when possible

### Implementation Example
```php
function safeSendMessage($chat_id, $text) {
    static $last_send = 0;
    $current_time = microtime(true);
    
    if($current_time - $last_send < 0.1) { // 100ms delay
        usleep(100000);
    }
    
    SendMessage($chat_id, $text, "", false, "");
    $last_send = microtime(true);
}
```

---

## Security Considerations

### Input Validation
```php
// Validate user ID
if(!is_numeric($user_id) || $user_id <= 0) {
    return false;
}

// Sanitize file paths
$safe_filename = preg_replace('/[^a-zA-Z0-9_\-]/', '', $filename);

// Validate admin access
if($from_id != $ADMIN) {
    return; // Exit early
}
```

### Data Protection
```php
// Use absolute paths
$user_dir = __DIR__ . "/data/" . intval($user_id);

// Check file existence before reading
if(!file_exists($filename)) {
    return "0"; // Safe default
}
```