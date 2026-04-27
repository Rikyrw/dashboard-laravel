<?php
// Detect if running locally or on production
$host = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = strpos($host, 'localhost') === 0 || strpos($host, '127.0.0.1') === 0 || $host === '';

if ($isLocal) {
    // Local development - construct using protocol and host
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $BASE_URL = $protocol . '://' . $host . '/BankSampah-GreenPoint-main/BankSampah-GreenPoint-main/';
} else {
    // Production (Heroku)
    $BASE_URL = 'https://greenpoint-c4d5801f13c5.herokuapp.com/';
}
