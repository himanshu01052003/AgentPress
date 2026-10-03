<?php
/**
 * MCP server over the Streamable HTTP transport (stateless, JSON responses).
 *
 * Endpoints:
 *   POST /wp-json/mcp100p/v1/mcp          key in Authorization: Bearer … or X-API-Key header
 *   POST /wp-json/mcp100p/v1/mcp/<key>    key in the URL, for clients that cannot send headers
 *
 * The pre-rename namespace mcp-100pixel/v1 is still registered so existing connections keep working.
 *
 * @package Mcp100p
 */

defined( 'ABSPATH' ) || exit;

class MCP100P_Server {

	const NS        = 'mcp100p/v1';
	const LEGACY_NS = 'mcp-100pixel/v1';
	const PROTOCOLS = array( '2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05' );

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'serve_empty_body' ), 10, 3 );
		add_filter( 'rest_allowed_cors_headers', array( __CLASS__, 'cors_allowed_headers' ) );
		add_filter( 'rest_exposed_cors_headers', array( __CLASS__, 'cors_exposed_headers' ) );
	}

	public static function endpoint( $key = '' ) {
		return rest_url( self::NS . '/mcp' . ( $key ? '/' . $key : '' ) );
	}

	public static function register_routes() {
		$args = array(
			'methods'             => array( 'GET', 'POST', 'DELETE' ),
			'callback'            => array( __CLASS__, 'handle' ),
			'permission_callback' => array( __CLASS__, 'authenticate' ),
		);
		foreach ( array( self::NS, self::LEGACY_NS ) as $namespace ) {
			register_rest_route( $namespace, '/mcp', $args );
			register_rest_route( $namespace, '/mcp/(?P<key>mcp100p_[A-Za-z0-9_]+)', $args );
		}
	}

	/**
	 * Permission callback: find the API key and log in as its owner.
	 */
	public static function authenticate( WP_REST_Request $request ) {
		$settings = MCP100P_Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return new WP_Error( 'mcp100p_disabled', 'MCP access is disabled on this site.', array( 'status' => 503 ) );
		}

		$token = self::token_from_request( $request, $settings );
		if ( '' === $token ) {
			return new WP_Error( 'mcp100p_unauthorized', 'Missing API key. Send it as "Authorization: Bearer <key>" or "X-API-Key: <key>".', array( 'status' => 401 ) );
		}

		$user_id = MCP100P_Auth::verify( $token );
		if ( ! $user_id ) {
			return new WP_Error( 'mcp100p_unauthorized', 'Invalid or revoked API key.', array( 'status' => 401 ) );
		}

		wp_set_current_user( $user_id );
		return true;
	}

	private static function token_from_request( WP_REST_Request $request, $settings ) {
		$url_key = (string) $request->get_param( 'key' );
		if ( '' !== $url_key && self::is_mcp_route( $request->get_route(), true ) ) {
			return empty( $settings['allow_url_key'] ) ? '' : $url_key;
		}

		$header = (string) $request->get_header( 'x_api_key' );
		if ( '' !== $header ) {
			return trim( $header );
		}

		// Some Apache/CGI setups only expose the Authorization header under other names.
		$candidates = array( $request->get_header( 'authorization' ) );
		foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION' ) as $server_key ) {
			if ( ! empty( $_SERVER[ $server_key ] ) ) {
				$candidates[] = sanitize_text_field( wp_unslash( $_SERVER[ $server_key ] ) );
			}
		}
		if ( function_exists( 'getallheaders' ) ) {
			foreach ( (array) getallheaders() as $name => $value ) {
				if ( 'authorization' === strtolower( $name ) ) {
					$candidates[] = sanitize_text_field( (string) $value );
				}
			}
		}
		foreach ( $candidates as $value ) {
			if ( is_string( $value ) && preg_match( '/^\s*Bearer\s+(\S+)\s*$/i', $value, $m ) ) {
				return $m[1];
			}
		}

		return '';
	}

	public static function handle( WP_REST_Request $request ) {
		$method = $request->get_method();

		if ( 'POST' !== $method ) {
			// Stateless server: no SSE stream (GET) and no sessions to terminate (DELETE).
			$response = new WP_REST_Response( array( 'error' => 'Method not allowed. Send JSON-RPC messages with POST.' ), 405 );
			$response->header( 'Allow', 'POST' );
			return $response;
		}

		$body = json_decode( $request->get_body(), true );
		if ( null === $body && JSON_ERROR_NONE !== json_last_error() ) {
			return self::respond( self::rpc_error( null, -32700, 'Parse error: ' . json_last_error_msg() ), 400 );
		}
		if ( ! is_array( $body ) || array() === $body ) {
			return self::respond( self::rpc_error( null, -32600, 'Invalid Request' ), 400 );
		}

		// Batches were removed from newer protocol versions but older clients may still send them.
		if ( wp_is_numeric_array( $body ) ) {
			$replies = array();
			foreach ( $body as $message ) {
				$reply = self::dispatch( $message );
				if ( null !== $reply ) {
					$replies[] = $reply;
				}
			}
			return $replies ? self::respond( $replies ) : self::respond( null, 202 );
		}

		$reply = self::dispatch( $body );
		return null === $reply ? self::respond( null, 202 ) : self::respond( $reply );
	}

	private static function respond( $data, $status = 200 ) {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Handle one JSON-RPC message. Returns the reply, or null for notifications and client responses.
	 */
	private static function dispatch( $message ) {
		if ( ! is_array( $message ) || ! isset( $message['jsonrpc'] ) || '2.0' !== $message['jsonrpc'] ) {
			return self::rpc_error( is_array( $message ) && isset( $message['id'] ) ? $message['id'] : null, -32600, 'Invalid Request' );
		}
		if ( ! isset( $message['method'] ) || ! is_string( $message['method'] ) ) {
			// A response to a server-initiated request; we never send any, so ignore it.
			return null;
		}

		$is_notification = ! array_key_exists( 'id', $message );
		$id              = $is_notification ? null : $message['id'];
		$params          = isset( $message['params'] ) && is_array( $message['params'] ) ? $message['params'] : array();

		switch ( $message['method'] ) {
			case 'initialize':
				$result = self::initialize( $params );
				break;
			case 'ping':
			case 'logging/setLevel':
				$result = new stdClass();
				break;
			case 'tools/list':
				$result = array( 'tools' => MCP100P_Tools::definitions() );
				break;
			case 'tools/call':
				$result = self::call_tool( $params );
				if ( is_wp_error( $result ) ) {
					return $is_notification ? null : self::rpc_error( $id, -32602, $result->get_error_message() );
				}
				break;
			case 'resources/list':
				$result = array( 'resources' => array() );
				break;
			case 'resources/templates/list':
				$result = array( 'resourceTemplates' => array() );
				break;
			case 'prompts/list':
				$result = array( 'prompts' => array() );
				break;
			default:
				if ( $is_notification ) {
					return null; // notifications/initialized, notifications/cancelled, …
				}
				return self::rpc_error( $id, -32601, 'Method not found: ' . $message['method'] );
		}

		if ( $is_notification ) {
			return null;
		}

		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		);
	}

	private static function initialize( $params ) {
		$requested = isset( $params['protocolVersion'] ) ? $params['protocolVersion'] : '';
		$version   = in_array( $requested, self::PROTOCOLS, true ) ? $requested : self::PROTOCOLS[0];
		$user      = wp_get_current_user();

		$instructions = sprintf(
			"You are connected to the WordPress site \"%s\" (%s). Every action runs as the WordPress user \"%s\" (roles: %s) and is limited to what that user may do.\n" .
			'Call wp_site_info first to learn the available post types, taxonomies and theme. Post content accepts HTML or Gutenberg block markup. ' .
			'New posts are created as drafts unless you set a status; confirm with the user before publishing, deleting or changing plugins, themes or settings.',
			get_bloginfo( 'name' ),
			home_url( '/' ),
			$user->user_login,
			implode( ', ', $user->roles )
		);

		return array(
			'protocolVersion' => $version,
			'capabilities'    => array(
				'tools' => array( 'listChanged' => false ),
			),
			'serverInfo'      => array(
				'name'    => '100pixel-ai-agent-connector',
				'title'   => get_bloginfo( 'name' ) . ' (100pixel AI Agent Connector)',
				'version' => MCP100P_VERSION,
			),
			'instructions'    => $instructions,
		);
	}

	/**
	 * Run tools/call. Unknown tools are a protocol error (WP_Error); failures inside a tool are
	 * reported as an isError result so the model can read the message and recover.
	 */
	private static function call_tool( $params ) {
		$name = isset( $params['name'] ) && is_string( $params['name'] ) ? $params['name'] : '';
		$args = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();

		if ( ! MCP100P_Tools::get( $name ) ) {
			return MCP100P_Util::error( "Unknown tool '$name'." );
		}

		$result = MCP100P_Tools::call( $name, $args );

		if ( is_wp_error( $result ) ) {
			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => 'Error: ' . implode( ' ', $result->get_error_messages() ),
					),
				),
				'isError' => true,
			);
		}

		$text = is_string( $result ) ? $result : wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => (string) $text,
				),
			),
			'isError' => false,
		);
	}

	private static function rpc_error( $id, $code, $message ) {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => array(
				'code'    => $code,
				'message' => $message,
			),
		);
	}

	/**
	 * Send an empty body for 202 Accepted replies instead of the JSON "null" WordPress would print.
	 */
	public static function serve_empty_body( $served, $result, $request ) {
		if ( ! $served && 202 === $result->get_status() && self::is_mcp_route( $request->get_route() ) ) {
			return true;
		}
		return $served;
	}

	/**
	 * Whether a REST route is one of ours (current or legacy namespace).
	 */
	private static function is_mcp_route( $route, $with_key = false ) {
		foreach ( array( self::NS, self::LEGACY_NS ) as $namespace ) {
			$base = '/' . $namespace . '/mcp';
			if ( $with_key ? 0 === strpos( $route, $base . '/' ) : ( $route === $base || 0 === strpos( $route, $base . '/' ) ) ) {
				return true;
			}
		}
		return false;
	}

	public static function cors_allowed_headers( $headers ) {
		return array_merge( $headers, array( 'Mcp-Protocol-Version', 'Mcp-Session-Id', 'X-API-Key', 'Last-Event-ID' ) );
	}

	public static function cors_exposed_headers( $headers ) {
		return array_merge( $headers, array( 'Mcp-Session-Id' ) );
	}
}
