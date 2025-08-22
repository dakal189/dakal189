## ربات تلگرام: رفرال + امتیاز + فروشگاه آیتم (PHP + MySQL)

این پروژه یک ربات تک‌فایل PHP است که شامل سیستم رفرال، امتیاز، فروشگاه آیتم، مدیریت کانال‌های اجباری، بن/آنبن، جایزه روزانه، لولینگ، مسابقه هفتگی و قرعه‌کشی هفتگی است.

### پیش‌نیازها
- PHP 7.4+ (فعال بودن افزونه‌های curl و pdo_mysql)
- MySQL 5.7+/8+
- یک دامنه HTTPS برای وبهوک تلگرام

### راه‌اندازی دیتابیس
- ایجاد دیتابیس (نام پیش‌فرض: `telegram_referral_bot`):
```bash
mysql -u root -p -e "CREATE DATABASE telegram_referral_bot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```
- جداول به صورت خودکار با اولین درخواست ساخته می‌شوند.

### متغیرهای محیطی
- `BOT_TOKEN` توکن ربات از `@BotFather`
- `BOT_USERNAME` نام کاربری ربات بدون @ (برای ساخت لینک دعوت)
- `DB_HOST` پیش‌فرض `127.0.0.1`
- `DB_PORT` پیش‌فرض `3306`
- `DB_NAME` پیش‌فرض `telegram_referral_bot`
- `DB_USER` پیش‌فرض `root`
- `DB_PASS` پیش‌فرض خالی
- `ADMIN_IDS` شناسه‌های عددی ادمین‌ها، جدا با ویرگول: مثلا `123,456`
- `ADMIN_GROUP_ID` شناسه گروه ادمین برای دریافت درخواست‌ها (اختیاری)
- `PUBLIC_ANNOUNCE_CHANNEL_ID` کانال عمومی برای اعلان‌ها (اختیاری)
- `REFERRAL_REWARD_POINTS` امتیاز پاداش هر دعوت (پیش‌فرض 10)
- `DAILY_BONUS_POINTS` امتیاز جایزه روزانه (پیش‌فرض 5)
- `LOTTERY_TICKET_COST` هزینه بلیت قرعه‌کشی (پیش‌فرض 10)
- `LOTTERY_PRIZE_POINTS` جایزه قرعه‌کشی (پیش‌فرض 200)
- `ANTI_SPAM_MIN_INTERVAL_MS` حداقل فاصله بین درخواست‌های کاربر به میلی‌ثانیه (پیش‌فرض 700)
- `WEEKLY_TOP_REWARDS` قالب `رتبه:امتیاز` مثل `1:300,2:200,3:100`
- `CRON_SECRET` رشته امنیتی برای اجرای کران‌ها از طریق GET (توصیه می‌شود تنظیم شود)

نحوه ست کردن متغیرها بسته به وب‌سرور متفاوته (nginx: fastcgi_param، apache: SetEnv، یا فایل سرویس systemd).

### استقرار
- فایل `bot.php` را روی هاست خود (مسیر عمومی) قرار دهید.
- ربات را در کانال‌هایی که باید عضویت اجباری چک شود، ادمین کنید.
- وبهوک را تنظیم کنید:
```bash
curl -X POST "https://api.telegram.org/bot$BOT_TOKEN/setWebhook" \
  -d "url=https://YOUR-DOMAIN.TLD/bot.php" \
  -d "allowed_updates=[\"message\",\"callback_query\"]"
```
- جهت بررسی وضعیت:
```bash
curl -s "https://api.telegram.org/bot$BOT_TOKEN/getWebhookInfo" | jq .
```

### استفاده کاربر
- استارت: کاربر اگر با لینک `https://t.me/BOT_USERNAME?start=USERID` وارد شود، پس از تایید عضویت در کانال‌های اجباری، امتیاز به دعوت‌کننده داده می‌شود (ضد تقلب).
- منوی کاربر: «امتیاز من»، «لینک دعوت من»، «فروشگاه آیتم‌ها»، «درخواست‌های من»، «عضویت در کانال‌ها»، «جایزه روزانه»، «پروفایل»، «برترین‌های هفته»، «خرید بلیت قرعه‌کشی»، «قرعه‌کشی».

### پنل ادمین (در چت خصوصی با ربات)
- دکمه: «🛠 پنل ادمین» سپس دستورات:
```
/add_item نام | هزینه
/del_item ID
/items_list
/channels_add @username یا -100...
/channels_list
/channels_del chat_id
/users_list [page]
/set_points user_id amount
/add_points user_id amount
/sub_points user_id amount
/ban user_id
/unban user_id
/cron_weekly      # پرداخت پاداش هفته قبل دستی
/cron_lottery     # قرعه‌کشی هفته قبل دستی
```
نکات:
- برای چک عضویت کانال‌ها، ربات باید ادمین کانال باشد.
- برای دریافت درخواست آیتم‌ها، مقدار `ADMIN_GROUP_ID` را تنظیم کنید.

### کران‌های هفتگی (اتوماتیک)
برای اجرای خودکار مسابقه برترین دعوت‌ها و قرعه‌کشی هفته قبل، کران‌جاب روی سرور اضافه کنید. حتما `CRON_SECRET` را تنظیم کنید.

- مسابقه هفتگی (دوشنبه 00:01 UTC یا مطابق منطقه زمانی سرور):
```bash
curl -fsS "https://YOUR-DOMAIN.TLD/bot.php?cron=weekly_referrals&secret=YOUR_SECRET" | logger -t bot-cron
```
- قرعه‌کشی هفتگی (دوشنبه 00:05 UTC):
```bash
curl -fsS "https://YOUR-DOMAIN.TLD/bot.php?cron=weekly_lottery&secret=YOUR_SECRET" | logger -t bot-cron
```

در صورت تنظیم `PUBLIC_ANNOUNCE_CHANNEL_ID`، نتایج در کانال عمومی هم اعلام می‌شود.

### نکات امنیتی
- `CRON_SECRET` را مقداردهی کنید و فقط از طریق HTTPS فراخوانی کنید.
- `ADMIN_IDS` را دقیق تنظیم کنید.
- ربات را فقط در کانال‌های مورد نیاز ادمین کنید.

### توسعه و دیباگ
- بررسی سینتکس:
```bash
php -l /path/to/bot.php
```
- برای تغییر امتیاز دعوت یا سطح‌بندی، متغیرها و توابع در `bot.php` قابل ویرایش هستند.

### لایسنس
این پروژه بدون لایسنس رسمی ارائه شده و استفاده از آن آزاد است. مسئولیت رعایت قوانین تلگرام و قوانین محلی با شماست.