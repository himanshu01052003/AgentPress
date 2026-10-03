<?php
/**
 * Remove plugin data on uninstall.
 *
 * @package Mcp100p
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'mcp100p_settings' );
delete_option( 'mcp100p_keys' );
delete_metadata( 'user', 0, 'mcp100p_latest_key', '', true );
