# Guardian PHP Bot + Admin (MySQL)

## Setup
1. Requirements: PHP 8.0+, MySQL 5.7+/8, Composer
2. Copy env: `cp .env.example .env` and fill values
3. Install deps: `composer install`
4. Create DB and user matching `.env` and run bot once to auto-migrate

## Run bot (long polling)
```
php bin/bot.php
```

## Run web (built-in dev server)
```
php -S 0.0.0.0:8080 -t public
```
Open http://localhost:8080

- In group, use `/settings` to get the admin link
- Commands: `/warn`, `/mute 10m`, `/ban`, `/lockdown on|off`

## Notes
- Tables: `settings`, `users`, `sanctions`
- Change bad words list in `src/Filters.php`