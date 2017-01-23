Test web app to show last 25 tweets in real-time.

### Usage
- `composer install`
- Edit config.php
- Run socket server from command line: `php bin/ratchet.php`

### Heroku
- Add MemCachier addon
- Separate Ratchet socket server to 2nd app. 
- Add file 'Procfile' with contents `web: php -f bin/ratchet.php` to 2nd app
- Both apps must have same MEMCACHIER_* env vars and IS_HEROKU=1

### Demo
https://stream-feed.herokuapp.com/

