<?php
/**
 * PHPUnit Bootstrap File
 *
 * This file is loaded before any tests run. It sets up the autoloader
 * and any global configuration needed for testing.
 */

// Define that we are in testing mode
define('EFACLOUD_TESTING', true);

// Load Composer autoloader
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// Load the internationalization stub for testing
// The i() function is used throughout the codebase for translations
if (!function_exists('i')) {
    /**
     * Internationalization stub for testing.
     * Returns the default text (after the | character) or the token if no | exists.
     *
     * @param string $token_and_text Token followed by | and default text
     * @param mixed ...$params Optional parameters for sprintf replacement
     * @return string The translated/default text
     */
    function i(string $token_and_text, ...$params): string
    {
        $parts = explode('|', $token_and_text, 2);
        $text = count($parts) > 1 ? $parts[1] : $parts[0];

        if (!empty($params)) {
            // Replace %1, %2, etc. with parameters
            foreach ($params as $index => $param) {
                $text = str_replace('%' . ($index + 1), (string) $param, $text);
            }
        }

        return $text;
    }
}

// Set up date format globals used by the application
$dfmt_d = 'Y-m-d';
$dfmt_dt = 'Y-m-d H:i:s';

// Ensure error reporting is set appropriately for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set timezone for consistent date handling in tests
date_default_timezone_set('Europe/Berlin');
