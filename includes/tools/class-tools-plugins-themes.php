<?php
/**
 * Plugin and theme tools.
 *
 * @package AgentPressMCP
 */

defined( 'ABSPATH' ) || exit;

class MCP100P_Tools_Plugins_Themes {

	public static function available() {
		return true;
	}

	public static function register() {
		MCP100P_Tools::add(
			array(
				'name'        => 'wp_list_plugins',
				'title'       => 'List plugins',
				'group'       => 'plugins_themes',
				'description' => 'List installed plugins with their version, active state and available updates.',
				'read_only'   => true,
				'callback'    => array( __CLASS__, 'list_plugins' ),
			)
		);

		$plugin_input = array(
			'plugin' => array(
				'type'        => 'string',
				'description' => 'Plugin file (e.g. "akismet/akismet.php") or folder slug (e.g. "akismet").',
			),
		);

		MCP100P_Tools::add(
			array(
				'name'        => 'wp_activate_plugin',
				'title'       => 'Activate plugin',
				'group'       => 'plugins_themes',
				'description' => 'Activate an installed plugin.',
				'input'       => $plugin_input,
				'required'    => array( 'plugin' ),
				'idempotent'  => true,
				'callback'    => array( __CLASS__, 'activate_plugin' ),
			)
		);

		MCP100P_Tools::add(
			array(
				'name'        => 'wp_deactivate_plugin',
				'title'       => 'Deactivate plugin',
				'group'       => 'plugins_themes',
				'description' => 'Deactivate an active plugin.',
				'input'       => $plugin_input,
				'required'    => array( 'plugin' ),
				'destructive' => true,
				'idempotent'  => true,
				'callback'    => array( __CLASS__, 'deactivate_plugin' ),
			)
		);

		MCP100P_Tools::add(
			array(
				'name'        => 'wp_list_themes',
				'title'       => 'List themes',
				'group'       => 'plugins_themes',
				'description' => 'List installed themes and show which one is active.',
				'read_only'   => true,
				'callback'    => array( __CLASS__, 'list_themes' ),
			)
		);

		MCP100P_Tools::add(
			array(
				'name'        => 'wp_activate_theme',
				'title'       => 'Activate theme',
				'group'       => 'plugins_themes',
				'description' => 'Switch the site to another installed theme. This changes how the whole site looks.',
				'input'       => array(
					'stylesheet' => array(
						'type'        => 'string',
						'description' => 'Theme folder name, as returned by wp_list_themes.',
					),
				),
				'required'    => array( 'stylesheet' ),
				'destructive' => true,
				'idempotent'  => true,
				'callback'    => array( __CLASS__, 'activate_theme' ),
			)
		);
	}

	private static function load_plugin_api() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	/**
	 * @return string|WP_Error Plugin file relative to the plugins directory.
	 */
	private static function resolve_plugin( $ident ) {
		$plugins = get_plugins();
		if ( isset( $plugins[ $ident ] ) ) {
			return $ident;
		}
		foreach ( array_keys( $plugins ) as $file ) {
			if ( dirname( $file ) === $ident || $file === $ident . '.php' ) {
				return $file;
			}
		}
		return MCP100P_Util::error( "Plugin '$ident' is not installed. Use wp_list_plugins to see installed plugins." );
	}

	public static function list_plugins() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return MCP100P_Util::error( 'You are not allowed to manage plugins.' );
		}
		self::load_plugin_api();

		$updates = get_site_transient( 'update_plugins' );
		$items   = array();
		foreach ( get_plugins() as $file => $data ) {
			$items[] = array(
				'plugin'           => $file,
				'name'             => $data['Name'],
				'version'          => $data['Version'],
				'active'           => is_plugin_active( $file ),
				'author'           => wp_strip_all_tags( $data['Author'] ),
				'description'      => wp_trim_words( wp_strip_all_tags( $data['Description'] ), 25 ),
				'update_available' => isset( $updates->response[ $file ]->new_version ) ? $updates->response[ $file ]->new_version : null,
			);
		}
		return array( 'items' => $items );
	}

	public static function activate_plugin( $args ) {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return MCP100P_Util::error( 'You are not allowed to activate plugins.' );
		}
		self::load_plugin_api();

		$file = self::resolve_plugin( MCP100P_Util::str( $args, 'plugin' ) );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		if ( is_plugin_active( $file ) ) {
			return array(
				'plugin' => $file,
				'active' => true,
				'note'   => 'Already active.',
			);
		}

		$result = activate_plugin( $file );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'plugin' => $file,
			'active' => is_plugin_active( $file ),
		);
	}

	public static function deactivate_plugin( $args ) {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return MCP100P_Util::error( 'You are not allowed to deactivate plugins.' );
		}
		self::load_plugin_api();

		$file = self::resolve_plugin( MCP100P_Util::str( $args, 'plugin' ) );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		if ( plugin_basename( MCP100P_FILE ) === $file ) {
			return MCP100P_Util::error( 'This plugin powers the MCP connection and cannot deactivate itself.' );
		}

		deactivate_plugins( $file );
		return array(
			'plugin' => $file,
			'active' => is_plugin_active( $file ),
		);
	}

	public static function list_themes() {
		if ( ! current_user_can( 'switch_themes' ) ) {
			return MCP100P_Util::error( 'You are not allowed to manage themes.' );
		}

		$updates = get_site_transient( 'update_themes' );
		$active  = get_stylesheet();
		$items   = array();
		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			$items[] = array(
				'stylesheet'       => $stylesheet,
				'name'             => $theme->get( 'Name' ),
				'version'          => $theme->get( 'Version' ),
				'active'           => $active === $stylesheet,
				'parent'           => $theme->get_template() !== $stylesheet ? $theme->get_template() : null,
				'block_theme'      => method_exists( $theme, 'is_block_theme' ) ? $theme->is_block_theme() : false,
				'update_available' => isset( $updates->response[ $stylesheet ]['new_version'] ) ? $updates->response[ $stylesheet ]['new_version'] : null,
			);
		}
		return array( 'items' => $items );
	}

	public static function activate_theme( $args ) {
		if ( ! current_user_can( 'switch_themes' ) ) {
			return MCP100P_Util::error( 'You are not allowed to switch themes.' );
		}

		$stylesheet = MCP100P_Util::str( $args, 'stylesheet' );
		$theme      = wp_get_theme( $stylesheet );
		if ( ! $theme->exists() || ! $theme->is_allowed() ) {
			return MCP100P_Util::error( "Theme '$stylesheet' is not installed or not allowed." );
		}
		if ( $theme->errors() ) {
			return $theme->errors();
		}

		switch_theme( $stylesheet );
		return array(
			'stylesheet' => get_stylesheet(),
			'name'       => wp_get_theme()->get( 'Name' ),
			'active'     => get_stylesheet() === $stylesheet,
		);
	}
}
