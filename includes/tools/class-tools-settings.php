<?php
/**
 * Site settings tools.
 *
 * @package AgentPressMCP
 */

defined( 'ABSPATH' ) || exit;

class MCP100P_Tools_Settings {

	/**
	 * Settings that can be read.
	 */
	const READABLE = array(
		'blogname',
		'blogdescription',
		'siteurl',
		'home',
		'admin_email',
		'timezone_string',
		'gmt_offset',
		'date_format',
		'time_format',
		'start_of_week',
		'WPLANG',
		'users_can_register',
		'default_role',
		'posts_per_page',
		'posts_per_rss',
		'rss_use_excerpt',
		'show_on_front',
		'page_on_front',
		'page_for_posts',
		'blog_public',
		'default_category',
		'default_post_format',
		'default_comment_status',
		'default_ping_status',
		'comment_registration',
		'comment_moderation',
		'comments_per_page',
		'thumbnail_size_w',
		'thumbnail_size_h',
		'medium_size_w',
		'medium_size_h',
		'large_size_w',
		'large_size_h',
		'uploads_use_yearmonth_folders',
		'permalink_structure',
		'category_base',
		'tag_base',
	);

	/**
	 * Settings that can be changed. Site URL, admin email, registration and default
	 * role are deliberately excluded because changing them can lock people out or
	 * open the site to account abuse.
	 */
	const WRITABLE = array(
		'blogname',
		'blogdescription',
		'timezone_string',
		'date_format',
		'time_format',
		'start_of_week',
		'posts_per_page',
		'posts_per_rss',
		'rss_use_excerpt',
		'show_on_front',
		'page_on_front',
		'page_for_posts',
		'blog_public',
		'default_category',
		'default_post_format',
		'default_comment_status',
		'default_ping_status',
		'comment_registration',
		'comment_moderation',
		'comments_per_page',
		'thumbnail_size_w',
		'thumbnail_size_h',
		'medium_size_w',
		'medium_size_h',
		'large_size_w',
		'large_size_h',
		'uploads_use_yearmonth_folders',
		'permalink_structure',
		'category_base',
		'tag_base',
	);

	public static function available() {
		return true;
	}

	public static function register() {
		MCP100P_Tools::add(
			array(
				'name'        => 'wp_get_settings',
				'title'       => 'Get site settings',
				'group'       => 'settings',
				'description' => 'Read general, reading, discussion, media and permalink settings.',
				'read_only'   => true,
				'callback'    => array( __CLASS__, 'get_settings' ),
			)
		);

		MCP100P_Tools::add(
			array(
				'name'        => 'wp_update_settings',
				'title'       => 'Update site settings',
				'group'       => 'settings',
				'description' => 'Update site settings. Pass {"settings": {"option_name": value}}. Writable options: ' . implode( ', ', self::WRITABLE ) . '.',
				'input'       => array(
					'settings' => array(
						'type'        => 'object',
						'description' => 'Map of option name to new value.',
					),
				),
				'required'    => array( 'settings' ),
				'idempotent'  => true,
				'callback'    => array( __CLASS__, 'update_settings' ),
			)
		);
	}

	public static function get_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return MCP100P_Util::error( 'You are not allowed to view site settings.' );
		}
		$out = array();
		foreach ( self::READABLE as $name ) {
			$out[ $name ] = get_option( $name );
		}
		return $out;
	}

	public static function update_settings( $args ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return MCP100P_Util::error( 'You are not allowed to change site settings.' );
		}
		if ( ! is_array( $args['settings'] ) || ! $args['settings'] ) {
			return MCP100P_Util::error( 'settings must be a non-empty object.' );
		}

		$updated   = array();
		$rejected  = array();
		$permalink = false;

		foreach ( $args['settings'] as $name => $value ) {
			if ( ! in_array( $name, self::WRITABLE, true ) || ! is_scalar( $value ) ) {
				$rejected[] = $name;
				continue;
			}
			if ( 'timezone_string' === $name && '' !== $value && ! in_array( $value, timezone_identifiers_list(), true ) ) {
				$rejected[] = $name;
				continue;
			}
			if ( in_array( $name, array( 'permalink_structure', 'category_base', 'tag_base' ), true ) ) {
				$permalink = true;
			}
			update_option( $name, sanitize_option( $name, $value ) );
			$updated[ $name ] = get_option( $name );
		}

		if ( $permalink ) {
			flush_rewrite_rules( false );
		}

		return array(
			'updated'  => $updated ? $updated : new stdClass(),
			'rejected' => $rejected,
		);
	}
}
