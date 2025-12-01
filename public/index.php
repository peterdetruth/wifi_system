<?php

date_default_timezone_set('Africa/Nairobi');

use CodeIgniter\Boot;
use Config\Paths;

/*
 *---------------------------------------------------------------
 * CHECK PHP VERSION
 *---------------------------------------------------------------
 */
$minPhpVersion = '8.1';
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo sprintf(
        'Your PHP version must be %s or higher to run CodeIgniter. Current version: %s',
        $minPhpVersion,
        PHP_VERSION
    );
    exit(1);
}

/*
 *---------------------------------------------------------------
 * SET THE CURRENT DIRECTORY
 *---------------------------------------------------------------
 */
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

/*
 *---------------------------------------------------------------
 * LOAD THE PATHS CONFIG FILE
 *---------------------------------------------------------------
 */
require FCPATH . '../app/Config/Paths.php';

$paths = new Paths();

/*
 *---------------------------------------------------------------
 * LOCAL DEVELOPMENT PATHS
 *---------------------------------------------------------------
 */

// Base path for local environment (no extra subfolder)
$basePath = FCPATH . '../';

// Point to writable folder explicitly
$paths->writableDirectory = $basePath . 'writable';

// Ensure CodeIgniter knows where /app and /system are
$paths->appDirectory = $basePath . 'app';
$paths->systemDirectory = $basePath . 'system';

// Set root directory (public folder)
$paths->rootDirectory = FCPATH;

// Load the framework bootstrap
require $paths->systemDirectory . '/Boot.php';

exit(Boot::bootWeb($paths));
