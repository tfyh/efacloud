<?php
/**
 * PHPUnit Bootstrap File
 *
 * This file is loaded before any tests run. It sets up the autoloader
 * and any global configuration needed for testing.
 */

// Define that we are in testing mode
define('EFACLOUD_TESTING', true);

// Store the repository root for reference
define('EFACLOUD_TEST_ROOT', realpath(__DIR__ . '/..'));

// Load Composer autoloader (using absolute path before changing directory)
$composerAutoload = EFACLOUD_TEST_ROOT . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// ============================================================================
// Simulate web server context for code coverage
// ============================================================================
// efaCloud uses relative paths like '../classes/' throughout the codebase.
// These paths assume scripts run from subdirectories (public/, pages/, forms/, api/).
// By changing to 'public/', these paths resolve correctly:
//   ../classes/init.php -> classes/init.php
//
// This matches how the application runs on a real web server where
// public/index.php is the entry point.
// ============================================================================
chdir(EFACLOUD_TEST_ROOT . '/public');

// Create required directories if they don't exist
$logDir = EFACLOUD_TEST_ROOT . '/log';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

// Create config directory if it doesn't exist
$configDir = EFACLOUD_TEST_ROOT . '/config';
if (!is_dir($configDir)) {
    @mkdir($configDir, 0755, true);
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
