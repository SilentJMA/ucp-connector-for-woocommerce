<?php
/**
 * Backward compatibility loader for session handler class.
 *
 * @package UCP_Adapter
 */

if (! defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/core/class-ucp-session-handler.php';
