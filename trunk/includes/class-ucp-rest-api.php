<?php
/**
 * Backward compatibility loader for REST API class.
 *
 * @package UCP_Adapter
 */

if (! defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/api/class-ucp-rest-api.php';
