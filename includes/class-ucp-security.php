<?php
/**
 * Backward compatibility loader for security class.
 *
 * @package UCP_Adapter
 */

if (! defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/core/class-ucp-security.php';
