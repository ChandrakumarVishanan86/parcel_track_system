<?php
/**
 * ParcelTrack - global settings.
 * Edit DB_* if your WAMP MySQL user/password/port differ from the defaults.
 */
declare(strict_types=1);

// ---- Database (WAMP default: root / empty password) -------------------
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');            // use 3307 if your WAMP MySQL/MariaDB listens there
define('DB_NAME', 'parcel_track');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---- Application -------------------------------------------------------
define('APP_NAME', 'ParcelTrack');
// URL path of the project (no trailing slash). Auto-detected from the folder name, so
// it works even if the folder is called parcel_track-main, ParcelTrack, etc.
// Behind an Alias / virtual host, hard-code it instead, e.g. define('APP_BASE', '/parcel_track');
$__dr  = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
$__dir = realpath(dirname(__DIR__));
$__dr  = $__dr  ? rtrim(str_replace('\\', '/', $__dr), '/') : '';
$__dir = $__dir ? str_replace('\\', '/', $__dir) : '';
define('APP_BASE', ($__dr !== '' && $__dir !== '' && stripos($__dir . '/', $__dr . '/') === 0)
    ? rtrim(substr($__dir, strlen($__dr)), '/')
    : '/parcel_track');
unset($__dr, $__dir);
define('APP_ROOT', dirname(__DIR__));
define('APP_TIMEZONE', 'Asia/Colombo');

// ---- Company ------------------------------------------------------------
define('CURRENCY', 'Rs.');
define('COMPANY_NAME', 'ParcelTrack Express');
define('COMPANY_ADDRESS', '112 Union Place, Colombo 02');
define('COMPANY_PHONE', '+94 11 245 7788');

// ---- Pricing rules -------------------------------------------------------
define('FUEL_PCT', 8);                // fuel surcharge on freight
define('TAX_PCT', 18);                // VAT on (freight + fuel + fragile + COD fee + insurance)
define('COD_FEE_PCT', 2);             // % of COD amount ...
define('COD_FEE_MIN', 100);           // ... but never below this
define('FRAGILE_FEE', 150);
define('INSURANCE_PCT', 1);           // % of declared value when insured
define('VOL_DIVISOR', 5000);          // volumetric weight = L x W x H (cm) / 5000
define('MAX_WEIGHT_KG', 50);
define('MAX_COD', 500000);
define('MAX_ATTEMPTS', 3);            // failed deliveries before the parcel is returned
define('OTP_MAX_FAILS', 5);           // wrong OTP entries before dispatcher must reset
define('RIDER_MAX_TASKS', 25);        // active pickups + deliveries per rider
define('DEFAULT_CUSTOMER_PASSWORD', 'customer123');

define('APP_DEBUG', true);            // set to false in production

date_default_timezone_set(APP_TIMEZONE);
