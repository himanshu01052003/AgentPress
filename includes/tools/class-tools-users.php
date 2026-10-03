<?php
/**
 * User tools (read-only).
 *
 * @package Mcp100p
 */

defined( 'ABSPATH' ) || exit;

class MCP100P_Tools_Users {

	public static function available() {
		return true;
	}

	public static function register() {
		MCP100P_Tools::add(
			array(
				'name'        => 'wp_list_users',
				'title'       => 'List users',
				'group'       => 'users',
				'description' => 'List or search users.',
				'input'       => array(
					'role'     => array(
						'type'        => 'string',
						'description' => 'e.g. "administrator", "editor", "author", "customer".',
					),
					'search'   => array(
						'type'        => 'string',
						'description' => 'Matches username, email, display name or URL.',
					),
					'per_page' => array(
						'type'        => 'integer',
						'description' => '1–100, default 20.',
					),
					'page'     => array( 'type' => 'integer' ),
				),
				'read_only'   => true,
				'callback'    => array( __CLASS__, 'list_users' ),
			)
		);

		MCP100P_Tools::add(
			array(
				'name'        => 'wp_get_user',
				'title'       => 'Get user',
				'group'       => 'users',
				'description' => 'Get a user profile by ID. Omit id to get the connected user.',
				'input'       => array( 'id' => array( 'type' => 'integer' ) ),
				'read_only'   => true,
				'callback'    => array( __CLASS__, 'get_user' ),
			)
		);
	}

	public static function list_users( $args ) {
		if ( ! current_user_can( 'list_users' ) ) {
			return MCP100P_Util::error( 'You are not allowed to list users.' );
		}

		$per_page = MCP100P_Util::int( $args, 'per_page', 20, 1, 100 );
		$page     = MCP100P_Util::int( $args, 'page', 1, 1 );
		$query    = array(
			'number'      => $per_page,
			'paged'       => $page,
			'orderby'     => 'registered',
			'order'       => 'DESC',
			'count_total' => true,
		);
		if ( MCP100P_Util::has( $args, 'role' ) ) {
			$query['role'] = sanitize_key( MCP100P_Util::str( $args, 'role' ) );
		}
		if ( MCP100P_Util::has( $args, 'search' ) ) {
			$query['search'] = '*' . MCP100P_Util::str( $args, 'search' ) . '*';
		}

		$result = new WP_User_Query( $query );

		return array(
			'total' => (int) $result->get_total(),
			'page'  => $page,
			'items' => array_map(
				function ( $user ) {
					return MCP100P_Util::user_item( $user );
				},
				$result->get_results()
			),
		);
	}

	public static function get_user( $args ) {
		$user_id = MCP100P_Util::int( $args, 'id', get_current_user_id() );
		if ( get_current_user_id() !== $user_id && ! current_user_can( 'list_users' ) ) {
			return MCP100P_Util::error( 'You are not allowed to view other users.' );
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return MCP100P_Util::error( 'User not found.' );
		}
		return MCP100P_Util::user_item( $user, true );
	}
}
