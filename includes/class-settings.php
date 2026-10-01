<?php
/**
 * Plugin settings.
 *
 * @package AgentPressMCP
 */

defined( 'ABSPATH' ) || exit;

class MCP100P_Settings {

	const OPTION = 'mcp100p_settings';

	/**
	 * Tool groups an administrator can switch on or off.
	 */
	public static function groups() {
		return array(
			'content'        => array(
				'label' => __( 'Posts, pages & taxonomies', 'agentpress-mcp' ),
				'desc'  => __( 'List, read, create, update and delete posts, pages and custom post types; manage categories, tags and terms.', 'agentpress-mcp' ),
			),
			'media'          => array(
				'label' => __( 'Media library', 'agentpress-mcp' ),
				'desc'  => __( 'List media, upload files from a URL or base64 data, edit alt text and captions, delete media.', 'agentpress-mcp' ),
			),
			'users'          => array(
				'label' => __( 'Users (read-only)', 'agentpress-mcp' ),
				'desc'  => __( 'List users and read user profiles.', 'agentpress-mcp' ),
			),
			'settings'       => array(
				'label' => __( 'Site settings', 'agentpress-mcp' ),
				'desc'  => __( 'Read and update general, reading, discussion, media and permalink settings. Admin email, site URL and registration settings are never writable.', 'agentpress-mcp' ),
			),
			'plugins_themes' => array(
				'label' => __( 'Plugins & themes', 'agentpress-mcp' ),
				'desc'  => __( 'List installed plugins and themes, activate/deactivate plugins, switch themes.', 'agentpress-mcp' ),
			),
			'woocommerce'    => array(
				'label' => __( 'WooCommerce', 'agentpress-mcp' ),
				'desc'  => __( 'Manage products and orders. Only active when WooCommerce is installed.', 'agentpress-mcp' ),
			),
			'abilities'      => array(
				'label' => __( 'WordPress Abilities', 'agentpress-mcp' ),
				'desc'  => __( 'Expose abilities registered by other plugins (Abilities API) that are marked as public for MCP.', 'agentpress-mcp' ),
			),
		);
	}

	/**
	 * Which roles may create API keys, keyed by the capability that is checked.
	 */
	public static function access_levels() {
		return array(
			'manage_options'    => __( 'Administrators only', 'agentpress-mcp' ),
			'edit_others_posts' => __( 'Editors and above', 'agentpress-mcp' ),
			'publish_posts'     => __( 'Authors and above', 'agentpress-mcp' ),
			'edit_posts'        => __( 'Contributors and above', 'agentpress-mcp' ),
		);
	}

	public static function defaults() {
		return array(
			'enabled'        => 1,
			'allow_url_key'  => 1,
			'read_only'      => 0,
			'key_capability' => 'manage_options',
			'groups'         => array_fill_keys( array_keys( self::groups() ), 1 ),
		);
	}

	public static function get() {
		$defaults = self::defaults();
		$saved    = get_option( self::OPTION, array() );
		$saved    = is_array( $saved ) ? $saved : array();
		$settings = wp_parse_args( $saved, $defaults );

		$settings['groups'] = wp_parse_args( (array) $settings['groups'], $defaults['groups'] );
		if ( ! isset( self::access_levels()[ $settings['key_capability'] ] ) ) {
			$settings['key_capability'] = $defaults['key_capability'];
		}
		return $settings;
	}

	public static function group_enabled( $group ) {
		$settings = self::get();
		return ! empty( $settings['groups'][ $group ] );
	}

	/**
	 * Capability a user needs to create keys and to use the MCP endpoint.
	 */
	public static function key_capability() {
		return self::get()['key_capability'];
	}
}
