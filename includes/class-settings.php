<?php
/**
 * Plugin settings.
 *
 * @package Mcp100p
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
				'label' => __( 'Posts, pages & taxonomies', '100pixel-ai-agent-connector' ),
				'desc'  => __( 'List, read, create, update and delete posts, pages and custom post types; manage categories, tags and terms.', '100pixel-ai-agent-connector' ),
			),
			'media'          => array(
				'label' => __( 'Media library', '100pixel-ai-agent-connector' ),
				'desc'  => __( 'List media, upload files from a URL or base64 data, edit alt text and captions, delete media.', '100pixel-ai-agent-connector' ),
			),
			'users'          => array(
				'label' => __( 'Users (read-only)', '100pixel-ai-agent-connector' ),
				'desc'  => __( 'List users and read user profiles.', '100pixel-ai-agent-connector' ),
			),
			'settings'       => array(
				'label' => __( 'Site settings', '100pixel-ai-agent-connector' ),
				'desc'  => __( 'Read and update general, reading, discussion, media and permalink settings. Admin email, site URL and registration settings are never writable.', '100pixel-ai-agent-connector' ),
			),
			'plugins_themes' => array(
				'label' => __( 'Plugins & themes', '100pixel-ai-agent-connector' ),
				'desc'  => __( 'List installed plugins and themes, activate/deactivate plugins, switch themes.', '100pixel-ai-agent-connector' ),
			),
			'woocommerce'    => array(
				'label' => __( 'WooCommerce', '100pixel-ai-agent-connector' ),
				'desc'  => __( 'Manage products and orders. Only active when WooCommerce is installed.', '100pixel-ai-agent-connector' ),
			),
			'abilities'      => array(
				'label' => __( 'WordPress Abilities', '100pixel-ai-agent-connector' ),
				'desc'  => __( 'Expose abilities registered by other plugins (Abilities API) that are marked as public for MCP.', '100pixel-ai-agent-connector' ),
			),
		);
	}

	/**
	 * Which roles may create API keys, keyed by the capability that is checked.
	 */
	public static function access_levels() {
		return array(
			'manage_options'    => __( 'Administrators only', '100pixel-ai-agent-connector' ),
			'edit_others_posts' => __( 'Editors and above', '100pixel-ai-agent-connector' ),
			'publish_posts'     => __( 'Authors and above', '100pixel-ai-agent-connector' ),
			'edit_posts'        => __( 'Contributors and above', '100pixel-ai-agent-connector' ),
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
