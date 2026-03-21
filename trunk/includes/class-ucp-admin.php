<?php
/**
 * Backward compatibility loader for admin class.
 *
 * @package UCP_Adapter
 */

if (! defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/admin/class-ucp-admin.php';
