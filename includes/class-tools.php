<?php
/**
 * Tool registry.
 *
 * Third-party code can add tools on the `mcp100p_register_tools` action:
 *
 *     add_action( 'mcp100p_register_tools', function () {
 *         MCP100P_Tools::add( array(
 *             'name'        => 'my_tool',
 *             'description' => 'What it does.',
 *             'input'       => array( 'foo' => array( 'type' => 'string' ) ),
 *             'required'    => array( 'foo' ),
 *             'read_only'   => true,
 *             'callback'    => function ( $args ) { return array( 'ok' => true ); },
 *         ) );
 *     } );
 *
 * @package Mcp100p
 */

defined( 'ABSPATH' ) || exit;

class MCP100P_Tools {

	/** @var array|null */
	private static $tools = null;

	/**
	 * Register a tool. Tools that write are skipped while read-only mode is on.
	 */
	public static function add( array $tool ) {
		$tool = wp_parse_args(
			$tool,
			array(
				'name'        => '',
				'title'       => '',
				'description' => '',
				'group'       => 'custom',
				'input'       => array(),
				'required'    => array(),
				'schema'      => null,
				'read_only'   => false,
				'destructive' => false,
				'idempotent'  => false,
				'open_world'  => false,
				'callback'    => null,
			)
		);

		if ( ! preg_match( '/^[A-Za-z0-9_.-]{1,128}$/', $tool['name'] ) || ! is_callable( $tool['callback'] ) ) {
			return;
		}
		if ( ! $tool['read_only'] && ! empty( MCP100P_Settings::get()['read_only'] ) ) {
			return;
		}

		self::$tools[ $tool['name'] ] = $tool;
	}

	private static function load() {
		if ( null !== self::$tools ) {
			return;
		}
		self::$tools = array();

		MCP100P_Tools_Site::register();

		$groups = array(
			'content'        => 'MCP100P_Tools_Content',
			'media'          => 'MCP100P_Tools_Media',
			'users'          => 'MCP100P_Tools_Users',
			'settings'       => 'MCP100P_Tools_Settings',
			'plugins_themes' => 'MCP100P_Tools_Plugins_Themes',
			'woocommerce'    => 'MCP100P_Tools_WooCommerce',
			'abilities'      => 'MCP100P_Tools_Abilities',
		);
		foreach ( $groups as $group => $class ) {
			if ( MCP100P_Settings::group_enabled( $group ) && $class::available() ) {
				$class::register();
			}
		}

		do_action( 'mcp100p_register_tools' );

		self::$tools = apply_filters( 'mcp100p_tools', self::$tools );
	}

	public static function get( $name ) {
		self::load();
		return isset( self::$tools[ $name ] ) ? self::$tools[ $name ] : null;
	}

	/**
	 * Tool definitions in the shape MCP clients expect from tools/list.
	 */
	public static function definitions() {
		self::load();

		$list = array();
		foreach ( self::$tools as $tool ) {
			$list[] = array(
				'name'        => $tool['name'],
				'title'       => $tool['title'] ? $tool['title'] : $tool['name'],
				'description' => $tool['description'],
				'inputSchema' => $tool['schema'] ? $tool['schema'] : MCP100P_Util::schema( $tool['input'], $tool['required'] ),
				'annotations' => array(
					'title'           => $tool['title'] ? $tool['title'] : $tool['name'],
					'readOnlyHint'    => (bool) $tool['read_only'],
					'destructiveHint' => (bool) $tool['destructive'],
					'idempotentHint'  => (bool) ( $tool['read_only'] || $tool['idempotent'] ),
					'openWorldHint'   => (bool) $tool['open_world'],
				),
			);
		}
		return $list;
	}

	/**
	 * Run a tool. Returns the tool's result or a WP_Error.
	 */
	public static function call( $name, $args ) {
		$tool = self::get( $name );
		if ( ! $tool ) {
			return MCP100P_Util::error( "Unknown tool '$name'." );
		}

		foreach ( $tool['required'] as $key ) {
			if ( ! MCP100P_Util::has( $args, $key ) || '' === $args[ $key ] ) {
				return MCP100P_Util::error( "Missing required argument '$key'." );
			}
		}

		try {
			return call_user_func( $tool['callback'], $args );
		} catch ( Throwable $e ) {
			return MCP100P_Util::error( $e->getMessage() );
		}
	}
}
