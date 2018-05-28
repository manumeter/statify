<?php
/**
 * Some global WP function mocks.
 *
 * @package Statify
 */

/**
 * Remove slashes from string.
 *
 * Mocked implemetation always reflects input.
 *
 * @param string $value Input value.
 * @return string Output value.
 */
function wp_unslash( $value ) {
	return $value;
}

/**
 * Valudate redirect URL.
 *
 * Mocked implementation reflects input or default if NULL.
 *
 * @param string $location Location.
 * @param string $default  Fallback.
 * @return string Sanitized location.
 */
function wp_validate_redirect( $location, $default = '' ) {
	return empty( $location ) ? $default : $location;
}

$mock = new stdClass();
$mock->is_trackback = false;
$mock->is_robots = false;
$mock->is_user_logged_in = false;
$mock->is_feed = false;
$mock->is_preview = false;
$mock->is_404 = false;
$mock->is_search = false;

/**
 * Determine if current request is trackback (mocked implementation).
 *
 * @return boolean
 */
function is_trackback() {
	global $mock;
	return $mock->is_trackback;
}


/**
 * Determine if current request is robot request (mocked implementation).
 *
 * @return boolean
 */
function is_robots() {
	global $mock;
	return $mock->is_robots;
}

/**
 * Determine if user is currently logged in (mocked implementation).
 *
 * @return boolean
 */
function is_user_logged_in() {
	global $mock;
	return $mock->is_user_logged_in;
}

/**
 * Determine requested page is feed (mocked implementation).
 *
 * @return boolean
 */
function is_feed() {
	global $mock;
	return $mock->is_feed;
}

/**
 * Determine requested page is preview (mocked implementation).
 *
 * @return boolean
 */
function is_preview() {
	global $mock;
	return $mock->is_preview;
}

/**
 * Determine requested page is 404 (mocked implementation).
 *
 * @return boolean
 */
function is_404() {
	global $mock;
	return $mock->is_404;
}

/**
 * Determine requested page is 404 (mocked implementation).
 *
 * @return boolean
 */
function is_search() {
	global $mock;
	return $mock->is_search;
}

/**
 * Mock for current_time(). Always returns timestamp.
 *
 * @param string $type Type.
 *
 * @return mixed
 */
function current_time( $type ) {
	return time();
}

$mock_home_url = 'https://example.com/';

/**
 * Get home URL (mocked implementation).
 *
 * @return string
 */
function home_url() {
	global $mock_home_url;
	return $mock_home_url;
}

$mock_network_admin_url = 'https://example.com/';

/**
 * Get network admin URL (mocked implementation).
 *
 * @return string
 */
function network_admin_url() {
	global $mock_network_admin_url;
	return $mock_network_admin_url;
}

/**
 * Mocked user_trailingslashit.
 *
 * @param string $string      The string.
 * @param string $type_of_url URL type.
 *
 * @return string
 */
function user_trailingslashit( $string, $type_of_url = '' ) {
	return $string;
}

/**
 * Mocked esc url raw.
 *
 * @param string $url       Original URL.
 * @param array  $protocols Acceptable protocols (optional).
 * @return string Sanitized URL (mock: original).
 */
function esc_url_raw( $url, $protocols = null ) {
	return $url;
}
