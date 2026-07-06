<?php
/**
 * App configuration helpers.
 *
 * File ini dipakai agar aplikasi bisa berjalan di localhost subfolder
 * dan di hosting root domain tanpa hardcoded path.
 */

define('APP_PRODUCTION_HOST', 'monitoring-pesantren-rm.arndilhmzbr.engineer');
define('APP_PRODUCTION_URL', 'http://monitoring-pesantren-rm.arndilhmzbr.engineer');
define('APP_LOCAL_PROJECT_DIR', 'SISTEM-IOT-PESANTREN');

function appHost() {
    return strtolower($_SERVER['HTTP_HOST'] ?? '');
}

function isProductionHost() {
    $host = appHost();
    return $host === APP_PRODUCTION_HOST || $host === 'www.' . APP_PRODUCTION_HOST;
}

function appBasePath() {
    if (isProductionHost()) {
        return '';
    }

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $marker = '/' . APP_LOCAL_PROJECT_DIR . '/';

    if (strpos($scriptName, $marker) !== false) {
        return '/' . APP_LOCAL_PROJECT_DIR;
    }

    return '';
}

function appUrl($path = '') {
    if (isProductionHost()) {
        $baseUrl = rtrim(APP_PRODUCTION_URL, '/');
    } else {
        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $protocol = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host . appBasePath();
    }

    $path = ltrim($path, '/');
    return $path === '' ? $baseUrl : $baseUrl . '/' . $path;
}
