
## فرايند نصب ربات

ابتدا فایل config.php را ویرایش نمایید

سپس بر روی فایل های زیر کرون جاب 1 دقیقه ای تنظیم کنید

```
  bots_send2all.php
  send2all.php
  vip.php
```

برای امنیت رباتتان حتما کد زیر را درون فایل .htaccess قرار دهید

```htaccess
  <Files *>
    Order Allow,Deny
    Deny from all
    Allow from 149.154.160.0/16 91.108.4.0/16
  </Files>
```

در انتها وبهوک را بر روی فایل bot.php تنظیم کنید

## توسعه دهنده

- [@oYSoF](https://t.me/oYSoF)


## ربات نمونه

 - [@BuildYourMessengerBot](https://t.me/BuildYourMessengerBot)