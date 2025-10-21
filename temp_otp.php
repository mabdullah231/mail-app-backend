<?php
require __DIR__ . '/vendor/autoload.php';
$secret = getenv('OTP_SECRET') ?: 'PG7REG7EUMV7M7QNS5P2V5IJ2LIGGKV2';
$g = new PragmaRX\Google2FA\Google2FA();
echo $g->getCurrentOtp($secret);