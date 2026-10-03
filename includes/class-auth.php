<?php
/**
 * API key management.
 *
 * Keys look like "mcp100p_<id>_<secret>". Only a SHA-256 hash of the secret is
 * stored; the full key is shown to the user once, right after it is created.
 *
 * @package Mcp100p
 */

defined( 'ABSPATH' ) || exit;

class MCP100P_Auth {

	const OPTION  = 'mcp100p_keys';
	const LATEST  = 'mcp100p_latest_key';
	const PREFIX  = 'mcp100p_';
	const PATTERN = '/^mcp100p_([a-z0-9]{8})_([A-Za-z0-9]{40})$/';

	public static function all() {
		$keys = get_option( self::OPTION, array() );
		return is_array( $keys ) ? $keys : array();
	}

	public static function for_user( $user_id ) {
		return array_filter(
			self::all(),
			function ( $key ) use ( $user_id ) {
				return (int) $key['user_id'] === (int) $user_id;
			}
		);
	}

	/**
	 * Create a key for a user and return the plain-text key.
	 */
	public static function create( $user_id, $label ) {
		$keys = self::all();
		do {
			$id = strtolower( wp_generate_password( 8, false, false ) );
		} while ( isset( $keys[ $id ] ) );

		$secret      = wp_generate_password( 40, false, false );
		$keys[ $id ] = array(
			'user_id'   => (int) $user_id,
			'label'     => $label,
			'hash'      => hash( 'sha256', $secret ),
			'hint'      => substr( $secret, -4 ),
			'created'   => time(),
			'last_used' => 0,
		);
		update_option( self::OPTION, $keys, false );

		$token = self::PREFIX . $id . '_' . $secret;
		self::remember_latest( $user_id, $token );

		return $token;
	}

	/**
	 * Revoke a key. Users can revoke their own keys; administrators can revoke any key.
	 */
	public static function revoke( $id ) {
		$keys = self::all();
		if ( ! isset( $keys[ $id ] ) ) {
			return false;
		}
		if ( get_current_user_id() !== (int) $keys[ $id ]['user_id'] && ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		$owner = (int) $keys[ $id ]['user_id'];
		unset( $keys[ $id ] );
		update_option( self::OPTION, $keys, false );

		if ( '' === self::latest_for_user( $owner ) ) {
			delete_user_meta( $owner, self::LATEST );
		}
		return true;
	}

	/* ---------- Latest key, shown in the setup instructions ---------- */

	/**
	 * Encryption key derived from the site's secret salts, so the stored copy is
	 * useless without wp-config.php.
	 */
	private static function cipher_key() {
		return hash( 'sha256', wp_salt( 'auth' ) . '|mcp100p-latest-key', true );
	}

	/**
	 * Keep an encrypted copy of a user's newest key so the admin screen can keep
	 * filling it into the setup instructions. Skipped when OpenSSL is unavailable.
	 */
	private static function remember_latest( $user_id, $token ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return;
		}
		$iv     = random_bytes( 12 );
		$tag    = '';
		$cipher = openssl_encrypt( $token, 'aes-256-gcm', self::cipher_key(), OPENSSL_RAW_DATA, $iv, $tag );
		if ( false === $cipher ) {
			return;
		}
		update_user_meta( $user_id, self::LATEST, base64_encode( $iv . $tag . $cipher ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * The user's newest key in plain text, or '' if there is none or it was revoked.
	 */
	public static function latest_for_user( $user_id ) {
		$stored = get_user_meta( $user_id, self::LATEST, true );
		if ( ! $stored || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$raw = base64_decode( $stored, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || strlen( $raw ) <= 28 ) {
			return '';
		}
		$token = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', self::cipher_key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
		if ( false === $token || ! preg_match( self::PATTERN, $token, $m ) ) {
			return '';
		}

		// Only return it while the key still exists, belongs to this user and matches.
		$keys = self::all();
		$id   = $m[1];
		if ( ! isset( $keys[ $id ] ) || (int) $keys[ $id ]['user_id'] !== (int) $user_id || ! hash_equals( $keys[ $id ]['hash'], hash( 'sha256', $m[2] ) ) ) {
			return '';
		}
		return $token;
	}

	/**
	 * Key ID of the user's newest key, or ''.
	 */
	public static function latest_id_for_user( $user_id ) {
		$token = self::latest_for_user( $user_id );
		return preg_match( self::PATTERN, $token, $m ) ? $m[1] : '';
	}

	/**
	 * Validate a plain-text key. Returns the owning user ID, or 0.
	 */
	public static function verify( $token ) {
		if ( ! is_string( $token ) || ! preg_match( self::PATTERN, $token, $m ) ) {
			return 0;
		}
		$keys = self::all();
		$id   = $m[1];
		if ( ! isset( $keys[ $id ] ) || ! hash_equals( $keys[ $id ]['hash'], hash( 'sha256', $m[2] ) ) ) {
			return 0;
		}

		$user_id = (int) $keys[ $id ]['user_id'];
		$user    = get_userdata( $user_id );
		if ( ! $user || ! user_can( $user, MCP100P_Settings::key_capability() ) ) {
			return 0;
		}

		// Record usage, at most every five minutes to avoid a write per request.
		if ( time() - (int) $keys[ $id ]['last_used'] > 5 * MINUTE_IN_SECONDS ) {
			$keys[ $id ]['last_used'] = time();
			update_option( self::OPTION, $keys, false );
		}

		return $user_id;
	}
}
