<?php
/**
 * Site overview tool (always available).
 *
 * @package AgentPressMCP
 */

defined( 'ABSPATH' ) || exit;

class MCP100P_Tools_Site {

	public static function register() {
		MCP100P_Tools::add(
			array(
				'name'        => 'wp_site_info',
				'title'       => 'Get site info',
				'group'       => 'site',
				'description' => 'Overview of the WordPress site: name, URL, WordPress version, language, timezone, the connected user and their roles, available post types and taxonomies, the active theme, and which tool groups are enabled. Call this first.',
				'read_only'   => true,
				'callback'    => array( __CLASS__, 'site_info' ),
			)
		);
	}

	public static function site_info() {
		$user  = wp_get_current_user();
		$theme = wp_get_theme();

		$post_types = array();
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {
			if ( in_array( $type->name, array( 'attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles', 'wp_font_family', 'wp_font_face' ), true ) ) {
				continue;
			}
			$post_types[] = array(
				'name'         => $type->name,
				'label'        => $type->label,
				'hierarchical' => (bool) $type->hierarchical,
				'public'       => (bool) $type->public,
				'can_create'   => current_user_can( $type->cap->create_posts ),
			);
		}

		$taxonomies = array();
		foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $tax ) {
			$taxonomies[] = array(
				'name'         => $tax->name,
				'label'        => $tax->label,
				'hierarchical' => (bool) $tax->hierarchical,
				'post_types'   => array_values( (array) $tax->object_type ),
			);
		}

		$settings = MCP100P_Settings::get();

		return array(
			'name'              => get_bloginfo( 'name' ),
			'description'       => get_bloginfo( 'description' ),
			'url'               => home_url( '/' ),
			'admin_url'         => admin_url(),
			'wordpress_version' => get_bloginfo( 'version' ),
			'language'          => get_locale(),
			'timezone'          => wp_timezone_string(),
			'current_time'      => current_time( 'mysql' ),
			'user'              => array(
				'id'           => (int) $user->ID,
				'username'     => $user->user_login,
				'display_name' => $user->display_name,
				'roles'        => array_values( $user->roles ),
			),
			'post_types'        => $post_types,
			'taxonomies'        => $taxonomies,
			'theme'             => array(
				'name'        => $theme->get( 'Name' ),
				'stylesheet'  => $theme->get_stylesheet(),
				'version'     => $theme->get( 'Version' ),
				'block_theme' => function_exists( 'wp_is_block_theme' ) ? wp_is_block_theme() : false,
			),
			'woocommerce'       => class_exists( 'WooCommerce' ),
			'read_only_mode'    => ! empty( $settings['read_only'] ),
			'enabled_groups'    => array_keys( array_filter( $settings['groups'] ) ),
		);
	}
}
