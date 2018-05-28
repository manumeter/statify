<?php
/**
 * PHPUnit Bootstrap file.
 *
 * @package Statify
 */

// Include Composer autoloader.
include_once __DIR__ . '/../vendor/autoload.php';

// Define ABSPATH to emulate presence of WordPress.
define( 'ABSPATH', true );

// Include additional WP mocks.
require_once __DIR__ . '/wp-mocks.php';
