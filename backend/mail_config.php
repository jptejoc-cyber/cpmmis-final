<?php
// Gmail SMTP settings for sending password recovery emails.
// Credentials are read from the environment so they are never committed
// or exposed in source / via the web server.
//
// How to configure (Apache / Laragon):
//   1) Enable 2-Step Verification on your Google account.
//   2) https://myaccount.google.com/apppasswords -> create an App Password.
//   3) In httpd-vhosts.conf add, for example:
//        SetEnv CPMMIS_SMTP_USER "yourgmail@gmail.com"
//        SetEnv CPMMIS_SMTP_PASS "your 16 char app password"
//      (replace "yourgmail" — mailer.php treats it as unconfigured)

$smtp_host     = getenv('CPMMIS_SMTP_HOST') ?: 'smtp.gmail.com';
$smtp_port     = (int)(getenv('CPMMIS_SMTP_PORT') ?: 587);
$smtp_username = getenv('CPMMIS_SMTP_USER') ?: 'yourgmail@gmail.com';
$smtp_app_password = getenv('CPMMIS_SMTP_PASS') ?: 'YOUR_APP_PASSWORD';
$from_name     = 'CPMMIS System';
