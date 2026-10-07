<?php
require /var/www/html/vendor/autoload.php;
 = Dotenv\Dotenv::createImmutable(/var/www/html/);
->load();
var_dump([MAIL_HOST] ?? NA);
