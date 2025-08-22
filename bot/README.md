# Referral Telegram Bot (PHP + MySQL)

## Setup

1. Copy `.env.example` to your environment and set variables (or export in server):
```
cp .env.example .env
# then edit values
```

2. Configure your web server to serve `public/` and point Telegram webhook to:
```
https://YOUR_DOMAIN/webhook.php?secret=WEBHOOK_SECRET
```

3. Create MySQL database and user, then run migrations (coming next).

4. Set Telegram webhook:
```
curl -s "https://api.telegram.org/bot<YOUR_TOKEN>/setWebhook" \
  -d "url=https://YOUR_DOMAIN/webhook.php?secret=WEBHOOK_SECRET"
```

## Development
- PHP 8.1+
- MySQL 8+

## Notes
- Keep bot token and secrets safe.
- Bot must be in forced channels for membership checks.