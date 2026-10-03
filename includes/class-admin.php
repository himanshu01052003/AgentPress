<?php
/**
 * Admin screen: API keys, client setup instructions and settings.
 *
 * @package Mcp100p
 */

defined( 'ABSPATH' ) || exit;

class MCP100P_Admin {

	const SLUG = '100pixel-ai-agent-connector';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_mcp100p_create_key', array( __CLASS__, 'create_key' ) );
		add_action( 'admin_post_mcp100p_revoke_key', array( __CLASS__, 'revoke_key' ) );
		add_action( 'admin_post_mcp100p_save_settings', array( __CLASS__, 'save_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MCP100P_FILE ), array( __CLASS__, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
	}

	public static function page_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	public static function menu() {
		add_menu_page(
			__( '100pixel AI Agent Connector', '100pixel-ai-agent-connector' ),
			__( 'AI Agent Connector', '100pixel-ai-agent-connector' ),
			MCP100P_Settings::key_capability(),
			self::SLUG,
			array( __CLASS__, 'render' ),
			self::menu_icon(),
			80
		);
	}

	/**
	 * Monochrome plugin mark as a data URI; WordPress recolors it to match the admin color scheme.
	 */
	private static function menu_icon() {
		$svg = (string) file_get_contents( MCP100P_DIR . 'assets/mcp100p-mark.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	public static function row_meta( $links, $file ) {
		if ( plugin_basename( MCP100P_FILE ) === $file ) {
			$links[] = '<a href="https://100pixel.com" target="_blank" rel="noopener">' . esc_html__( 'Visit 100pixel.com', '100pixel-ai-agent-connector' ) . '</a>';
		}
		return $links;
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Connect AI', '100pixel-ai-agent-connector' ) . '</a>' );
		return $links;
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}
		// Version by file time so browsers and caches pick up every change.
		$css = MCP100P_DIR . 'assets/admin.css';
		$js  = MCP100P_DIR . 'assets/admin.js';
		wp_enqueue_style( 'mcp100p-admin', MCP100P_URL . 'assets/admin.css', array(), MCP100P_VERSION . '.' . filemtime( $css ) );
		wp_enqueue_script( 'mcp100p-admin', MCP100P_URL . 'assets/admin.js', array(), MCP100P_VERSION . '.' . filemtime( $js ), true );
		wp_localize_script(
			'mcp100p-admin',
			'mcp100p',
			array(
				'copied' => __( 'Copied!', '100pixel-ai-agent-connector' ),
				'copy'   => __( 'Copy', '100pixel-ai-agent-connector' ),
				'revoke' => __( 'Revoke this key? Assistants using it will be disconnected.', '100pixel-ai-agent-connector' ),
			)
		);
	}

	/* ---------- Form handlers ---------- */

	public static function create_key() {
		check_admin_referer( 'mcp100p_create_key' );
		if ( ! current_user_can( MCP100P_Settings::key_capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to create API keys.', '100pixel-ai-agent-connector' ) );
		}

		$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$label = '' !== $label ? $label : __( 'AI agent', '100pixel-ai-agent-connector' );
		$key   = MCP100P_Auth::create( get_current_user_id(), $label );

		set_transient( 'mcp100p_new_key_' . get_current_user_id(), $key, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( self::page_url( array( 'mcp100p_msg' => 'created' ) ) . '#mcp100p-connect' );
		exit;
	}

	public static function revoke_key() {
		check_admin_referer( 'mcp100p_revoke_key' );
		$id = isset( $_POST['key_id'] ) ? sanitize_key( wp_unslash( $_POST['key_id'] ) ) : '';
		$ok = MCP100P_Auth::revoke( $id );
		wp_safe_redirect( self::page_url( array( 'mcp100p_msg' => $ok ? 'revoked' : 'revoke_failed' ) ) );
		exit;
	}

	public static function save_settings() {
		check_admin_referer( 'mcp100p_save_settings' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', '100pixel-ai-agent-connector' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$groups = array();
		foreach ( array_keys( MCP100P_Settings::groups() ) as $group ) {
			$groups[ $group ] = empty( $_POST['groups'][ $group ] ) ? 0 : 1;
		}
		$capability = isset( $_POST['key_capability'] ) ? sanitize_key( wp_unslash( $_POST['key_capability'] ) ) : 'manage_options';

		update_option(
			MCP100P_Settings::OPTION,
			array(
				'enabled'        => empty( $_POST['enabled'] ) ? 0 : 1,
				'allow_url_key'  => empty( $_POST['allow_url_key'] ) ? 0 : 1,
				'read_only'      => empty( $_POST['read_only'] ) ? 0 : 1,
				'key_capability' => isset( MCP100P_Settings::access_levels()[ $capability ] ) ? $capability : 'manage_options',
				'groups'         => $groups,
			)
		);
		// phpcs:enable

		wp_safe_redirect( self::page_url( array( 'mcp100p_msg' => 'saved' ) ) . '#mcp100p-settings' );
		exit;
	}

	/* ---------- Client setup snippets ---------- */

	/**
	 * Setup instructions for each AI client.
	 */
	private static function clients( $key ) {
		$url     = MCP100P_Server::endpoint();
		$url_key = MCP100P_Server::endpoint( $key );
		$bearer  = 'Bearer ' . $key;
		$json    = function ( $data ) {
			return wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		};
		$headers = array( 'Authorization' => $bearer );

		$clients = array(
			'claude-ai'      => array(
				'label'   => 'Claude',
				'kind'    => 'Web & desktop app',
				'icon'    => 'claude',
				'url_key' => true,
				'steps'   => array(
					'Open Claude → <strong>Settings → Connectors</strong> and click <strong>Add custom connector</strong>.',
					'Name it after your site and paste the URL below as the <strong>Remote MCP server URL</strong>. Leave the OAuth fields empty.',
					'In a chat, open the <strong>+ / tools</strong> menu and enable the connector.',
				),
				'code'    => $url_key,
			),
			'chatgpt'        => array(
				'label'   => 'ChatGPT',
				'kind'    => 'Paste a URL',
				'icon'    => 'openai',
				'url_key' => true,
				'steps'   => array(
					'In ChatGPT, open <strong>Plugins</strong> and click the <strong>Add</strong> button.',
					'Choose <strong>Create MCP app</strong> and enter a name and description.',
					'Under <strong>Authentication</strong>, choose <strong>No authentication</strong>.',
					'Paste the URL below and click <strong>Connect</strong>.',
				),
				'code'    => $url_key,
			),
			'claude-code'    => array(
				'label' => 'Claude Code',
				'kind'  => 'Terminal command',
				'icon'  => 'claude',
				'steps' => array( 'Run this in your terminal:' ),
				'code'  => sprintf( 'claude mcp add --transport http wordpress %s --header "Authorization: %s"', $url, $bearer ),
			),
			'claude-desktop' => array(
				'label' => 'Claude Desktop',
				'kind'  => 'Config file',
				'icon'  => 'claude',
				'steps' => array(
					'Alternative to the Connectors screen. Requires Node.js. Open <strong>Settings → Developer → Edit Config</strong> and add:',
				),
				'code'  => $json(
					array(
						'mcpServers' => array(
							'wordpress' => array(
								'command' => 'npx',
								'args'    => array( '-y', 'mcp-remote', $url, '--header', 'Authorization:${WP_AUTH}' ),
								'env'     => array( 'WP_AUTH' => $bearer ),
							),
						),
					)
				),
			),
			'cursor'         => array(
				'label' => 'Cursor',
				'kind'  => 'Config file',
				'icon'  => 'cursor',
				'steps' => array( 'Add this to <code>~/.cursor/mcp.json</code> (or <code>.cursor/mcp.json</code> in a project):' ),
				'code'  => $json(
					array(
						'mcpServers' => array(
							'wordpress' => array(
								'url'     => $url,
								'headers' => $headers,
							),
						),
					)
				),
			),
			'vscode'         => array(
				'label' => 'VS Code',
				'kind'  => 'Copilot · config file',
				'icon'  => 'visualstudiocode',
				'steps' => array( 'Add this to <code>.vscode/mcp.json</code> in your workspace:' ),
				'code'  => $json(
					array(
						'servers' => array(
							'wordpress' => array(
								'type'    => 'http',
								'url'     => $url,
								'headers' => $headers,
							),
						),
					)
				),
			),
			'gemini'         => array(
				'label' => 'Gemini CLI',
				'kind'  => 'Config file',
				'icon'  => 'googlegemini',
				'steps' => array( 'Add this to <code>~/.gemini/settings.json</code>:' ),
				'code'  => $json(
					array(
						'mcpServers' => array(
							'wordpress' => array(
								'httpUrl' => $url,
								'headers' => $headers,
							),
						),
					)
				),
			),
			'windsurf'       => array(
				'label' => 'Windsurf',
				'kind'  => 'Config file',
				'icon'  => 'windsurf',
				'steps' => array( 'Add this to <code>~/.codeium/windsurf/mcp_config.json</code>:' ),
				'code'  => $json(
					array(
						'mcpServers' => array(
							'wordpress' => array(
								'serverUrl' => $url,
								'headers'   => $headers,
							),
						),
					)
				),
			),
			'other'          => array(
				'label' => 'Other',
				'kind'  => 'Any MCP client · test',
				'icon'  => 'modelcontextprotocol',
				'steps' => array(
					'Any MCP client that supports the <strong>Streamable HTTP</strong> transport works. Send the key as <code>Authorization: Bearer &lt;key&gt;</code> or <code>X-API-Key: &lt;key&gt;</code>. Test the connection with:',
				),
				'code'  => sprintf(
					"curl -s %s \\\n  -H \"Content-Type: application/json\" \\\n  -H \"Accept: application/json, text/event-stream\" \\\n  -H \"X-API-Key: %s\" \\\n  -d '{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/list\"}'",
					$url,
					$key
				),
			),
		);

		// Setup prompts: a chat message the user pastes into an AI app that can run commands or
		// edit files, so the agent connects itself. Not possible for Claude web/desktop or ChatGPT,
		// whose connectors can only be added in their settings screens.
		$verify = 'Once the server is loaded (after any restart or refresh), list the tools the "wordpress" server provides and call wp_site_info to confirm which WordPress site you are connected to.';

		// Node-based clients give each address only 250 ms to connect when a host has both IPv4 and
		// IPv6 records, so an unreachable IPv6 plus a slow IPv4 handshake fails as "fetch failed: AggregateError".
		$host     = wp_parse_url( $url, PHP_URL_HOST );
		$verify  .= "\n\nIf the connection fails with \"fetch failed\", \"AggregateError\", \"ETIMEDOUT\" or \"ENETUNREACH\" even though the URL opens in a browser, the cause is usually on my computer, not the site: "
			. $host . " has an IPv6 address my network cannot reach, and the IPv4 connection takes longer than the 250 ms that Node.js gives each address. Fix it like this:\n"
			. '1. Look up the IPv4 address of ' . $host . " (for example with nslookup, dig or ping -4).\n"
			. "2. Check the diagnosis: a plain Node.js fetch() of the server URL fails, but the same request run with NODE_OPTIONS=--network-family-autoselection-attempt-timeout=2000 gets an HTTP response.\n"
			. '3. Ask me to add the line "<IPv4 address> ' . $host . "\" to my hosts file (/etc/hosts on macOS and Linux, C:\\Windows\\System32\\drivers\\etc\\hosts on Windows). It needs administrator rights, so give me the exact command or steps instead of running sudo yourself.\n"
			. '4. After I have done that, have me restart or reload the app and connect again.';

		$clients['claude-code']['prompt'] = "Connect Claude Code to my WordPress site through its MCP server (100pixel AI Agent Connector).\n\n"
			. "1. Run this command in the terminal:\n\n" . $clients['claude-code']['code'] . "\n\n"
			. "2. Run `claude mcp list` and check that the \"wordpress\" server shows as connected.\n"
			. "3. Tell me to restart Claude Code (or start a new session) so the WordPress tools load.\n\n"
			. $verify;

		$clients['cursor']['prompt'] = "Connect Cursor to my WordPress site through its MCP server (100pixel AI Agent Connector).\n\n"
			. "Create the file .cursor/mcp.json in this project, or update it if it already exists. Keep any servers that are already in it, and add this \"wordpress\" server to \"mcpServers\":\n\n"
			. $clients['cursor']['code'] . "\n\n"
			. "Then tell me to open Cursor Settings → MCP and enable the \"wordpress\" server if it is not already on.\n\n"
			. $verify;

		$clients['vscode']['prompt'] = "Connect VS Code (GitHub Copilot agent mode) to my WordPress site through its MCP server (100pixel AI Agent Connector).\n\n"
			. "Create the file .vscode/mcp.json in this workspace, or update it if it already exists. Keep any servers that are already in it, and add this \"wordpress\" server to \"servers\":\n\n"
			. $clients['vscode']['code'] . "\n\n"
			. "Then tell me to click Start above the \"wordpress\" entry in mcp.json (or run \"MCP: List Servers\" and start it).\n\n"
			. $verify;

		$clients['gemini']['prompt'] = "Connect Gemini CLI to my WordPress site through its MCP server (100pixel AI Agent Connector).\n\n"
			. "Update ~/.gemini/settings.json (create it if it does not exist). Keep every existing setting and server, and add this \"wordpress\" server to \"mcpServers\":\n\n"
			. $clients['gemini']['code'] . "\n\n"
			. "Then tell me to restart Gemini CLI and run /mcp to check that \"wordpress\" is connected.\n\n"
			. $verify;

		$clients['windsurf']['prompt'] = "Connect Windsurf to my WordPress site through its MCP server (100pixel AI Agent Connector).\n\n"
			. "Update ~/.codeium/windsurf/mcp_config.json (create it if it does not exist). Keep any servers that are already in it, and add this \"wordpress\" server to \"mcpServers\":\n\n"
			. $clients['windsurf']['code'] . "\n\n"
			. "Then tell me to refresh the MCP servers in Cascade (Plugins/MCP panel → Refresh) so the WordPress tools load.\n\n"
			. $verify;

		$clients['other']['prompt'] = "Connect to my WordPress site's MCP server (100pixel AI Agent Connector) and add it to your MCP configuration as a server named \"wordpress\".\n\n"
			. "- Transport: Streamable HTTP\n"
			. '- URL: ' . $url . "\n"
			. '- Header: Authorization: ' . $bearer . "\n"
			. '- If your client cannot send headers, use this URL instead (the key is included): ' . $url_key . "\n\n"
			. "Edit your own MCP config file or run your MCP add command, keeping any existing servers. Tell me if I need to restart or refresh anything.\n\n"
			. $verify;

		return $clients;
	}

	/* ---------- Page ---------- */

	/**
	 * Inline a bundled brand icon (assets/icons/<name>.svg).
	 */
	private static function icon( $name ) {
		$file = MCP100P_DIR . 'assets/icons/' . sanitize_file_name( $name ) . '.svg';
		if ( ! is_readable( $file ) ) {
			return '';
		}
		$svg = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$svg = preg_replace( '#<title>.*?</title>#s', '', $svg );
		return str_replace( '<svg ', '<svg aria-hidden="true" focusable="false" fill="currentColor" ', $svg );
	}

	/**
	 * Tags and attributes allowed in the bundled SVGs, for wp_kses().
	 */
	private static function svg_allowed() {
		$shape = array(
			'd'              => true,
			'fill'           => true,
			'stroke'         => true,
			'stroke-width'   => true,
			'stroke-linecap' => true,
			'transform'      => true,
			'points'         => true,
			'cx'             => true,
			'cy'             => true,
			'r'              => true,
			'x'              => true,
			'y'              => true,
			'width'          => true,
			'height'         => true,
			'class'          => true,
		);
		return array(
			'svg'     => array(
				'xmlns'       => true,
				'viewbox'     => true,
				'width'       => true,
				'height'      => true,
				'fill'        => true,
				'role'        => true,
				'aria-hidden' => true,
				'focusable'   => true,
			),
			'g'       => $shape,
			'path'    => $shape,
			'rect'    => $shape,
			'circle'  => $shape,
			'polygon' => $shape,
		);
	}

	/**
	 * Print a bundled SVG safely.
	 */
	private static function print_svg( $svg ) {
		// The icon file carries its own <style> for standalone use; here admin.css animates it instead.
		$svg = preg_replace( '#<style[^>]*>.*?</style>#s', '', $svg );
		echo wp_kses( $svg, self::svg_allowed() );
	}

	/**
	 * Toggle switch markup.
	 */
	private static function toggle( $name, $checked, $label ) {
		?>
		<label class="mcp100p-switch">
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?>>
			<span class="mcp100p-switch__track" aria-hidden="true"></span>
			<span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}

	/**
	 * Code block with a header bar and copy button.
	 */
	private static function code_block( $code, $label, $wrap = false ) {
		?>
		<div class="mcp100p-code<?php echo $wrap ? ' mcp100p-code--wrap' : ''; ?>">
			<div class="mcp100p-code__bar">
				<span><?php echo esc_html( $label ); ?></span>
				<button type="button" class="mcp100p-copy"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><span class="mcp100p-copy__text"><?php esc_html_e( 'Copy', '100pixel-ai-agent-connector' ); ?></span></button>
			</div>
			<pre><code><?php echo esc_html( $code ); ?></code></pre>
		</div>
		<?php
	}

	public static function render() {
		if ( ! current_user_can( MCP100P_Settings::key_capability() ) ) {
			return;
		}

		$user_id  = get_current_user_id();
		$is_admin = current_user_can( 'manage_options' );
		$settings = MCP100P_Settings::get();
		$enabled  = ! empty( $settings['enabled'] );
		$new_key  = get_transient( 'mcp100p_new_key_' . $user_id );
		if ( $new_key ) {
			delete_transient( 'mcp100p_new_key_' . $user_id );
		}
		$latest_key  = MCP100P_Auth::latest_for_user( $user_id );
		$latest_key  = $latest_key ? $latest_key : ( $new_key ? $new_key : '' );
		$latest_id   = preg_match( MCP100P_Auth::PATTERN, $latest_key, $m ) ? $m[1] : '';
		$has_key     = '' !== $latest_key;
		$url_ok      = ! empty( $settings['allow_url_key'] );
		$snippet_key = $has_key ? $latest_key : 'YOUR_API_KEY';
		$own_keys    = MCP100P_Auth::for_user( $user_id );
		$latest_info = $latest_id && isset( $own_keys[ $latest_id ] ) ? $own_keys[ $latest_id ] : null;
		$keys        = $is_admin ? MCP100P_Auth::all() : MCP100P_Auth::for_user( $user_id );
		$tool_count  = count( MCP100P_Tools::definitions() );
		$clients     = self::clients( $snippet_key );
		$msg         = isset( $_GET['mcp100p_msg'] ) ? sanitize_key( wp_unslash( $_GET['mcp100p_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$messages    = array(
			'revoked'       => array( 'success', __( 'API key revoked.', '100pixel-ai-agent-connector' ) ),
			'revoke_failed' => array( 'error', __( 'The key could not be revoked.', '100pixel-ai-agent-connector' ) ),
			'saved'         => array( 'success', __( 'Settings saved.', '100pixel-ai-agent-connector' ) ),
		);
		?>
		<div class="wrap mcp100p">
			<h1 class="screen-reader-text"><?php esc_html_e( '100pixel AI Agent Connector', '100pixel-ai-agent-connector' ); ?></h1>

			<header class="mcp100p-hero">
				<div class="mcp100p-hero__main">
					<div class="mcp100p-hero__icon" aria-hidden="true"><?php self::print_svg( (string) file_get_contents( MCP100P_DIR . 'assets/mcp100p-icon.svg' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file. ?></div>
					<div>
						<div class="mcp100p-hero__title">
							<h2><?php esc_html_e( '100pixel AI Agent Connector', '100pixel-ai-agent-connector' ); ?></h2>
							<span class="mcp100p-status <?php echo $enabled ? 'is-on' : 'is-off'; ?>">
								<?php echo $enabled ? esc_html__( 'Active', '100pixel-ai-agent-connector' ) : esc_html__( 'Disabled', '100pixel-ai-agent-connector' ); ?>
							</span>
						</div>
						<a class="mcp100p-byline" href="https://100pixel.com" target="_blank" rel="noopener"><?php esc_html_e( 'by 100pixel.com', '100pixel-ai-agent-connector' ); ?></a>
						<p><?php esc_html_e( 'Let AI assistants like Claude, ChatGPT, Cursor and Gemini work on this site. Each one connects with an API key and can only do what your WordPress account is allowed to do.', '100pixel-ai-agent-connector' ); ?></p>
					</div>
				</div>
				<div class="mcp100p-endpoint">
					<?php if ( $has_key && $url_ok ) : ?>
						<span class="mcp100p-endpoint__label"><?php esc_html_e( 'Server URL with your latest key', '100pixel-ai-agent-connector' ); ?></span>
					<?php else : ?>
						<span class="mcp100p-endpoint__label"><?php esc_html_e( 'Server URL', '100pixel-ai-agent-connector' ); ?></span>
					<?php endif; ?>
					<div class="mcp100p-endpoint__row">
						<code><?php echo esc_html( $has_key && $url_ok ? MCP100P_Server::endpoint( $latest_key ) : MCP100P_Server::endpoint() ); ?></code>
						<button type="button" class="mcp100p-copy mcp100p-copy--icon" aria-label="<?php esc_attr_e( 'Copy server URL', '100pixel-ai-agent-connector' ); ?>"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><span class="mcp100p-copy__text screen-reader-text"><?php esc_html_e( 'Copy', '100pixel-ai-agent-connector' ); ?></span></button>
					</div>
					<?php if ( ! $has_key ) : ?>
						<a class="mcp100p-endpoint__hint" href="#mcp100p-create"><?php esc_html_e( 'Create an API key to get your ready-to-use connection URL', '100pixel-ai-agent-connector' ); ?> &rarr;</a>
					<?php endif; ?>
				</div>
			</header>

			<div class="mcp100p-stats">
				<div class="mcp100p-stat">
					<span class="mcp100p-stat__value"><?php echo esc_html( number_format_i18n( count( $keys ) ) ); ?></span>
					<span class="mcp100p-stat__label"><?php echo $is_admin ? esc_html__( 'API keys on this site', '100pixel-ai-agent-connector' ) : esc_html__( 'Your API keys', '100pixel-ai-agent-connector' ); ?></span>
				</div>
				<div class="mcp100p-stat">
					<span class="mcp100p-stat__value"><?php echo esc_html( number_format_i18n( $tool_count ) ); ?></span>
					<span class="mcp100p-stat__label"><?php esc_html_e( 'Tools available to AI', '100pixel-ai-agent-connector' ); ?></span>
				</div>
				<div class="mcp100p-stat">
					<span class="mcp100p-stat__value mcp100p-stat__value--text"><?php echo empty( $settings['read_only'] ) ? esc_html__( 'Read & write', '100pixel-ai-agent-connector' ) : esc_html__( 'Read-only', '100pixel-ai-agent-connector' ); ?></span>
					<span class="mcp100p-stat__label"><?php esc_html_e( 'Access mode', '100pixel-ai-agent-connector' ); ?></span>
				</div>
			</div>

			<?php if ( isset( $messages[ $msg ] ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $messages[ $msg ][0] ); ?> is-dismissible"><p><?php echo esc_html( $messages[ $msg ][1] ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! $enabled ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'MCP access is disabled. AI assistants cannot connect until an administrator turns it on in Settings below.', '100pixel-ai-agent-connector' ); ?></p></div>
			<?php endif; ?>

			<?php if ( $new_key ) : ?>
				<section class="mcp100p-newkey" aria-labelledby="mcp100p-newkey-title">
					<div class="mcp100p-newkey__head">
						<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
						<div>
							<h2 id="mcp100p-newkey-title"><?php esc_html_e( 'Your new API key is ready', '100pixel-ai-agent-connector' ); ?></h2>
							<p><?php esc_html_e( 'It is now your latest key: the server URL and every setup instruction below use it.', '100pixel-ai-agent-connector' ); ?></p>
						</div>
					</div>
					<?php self::code_block( $new_key, __( 'API key', '100pixel-ai-agent-connector' ) ); ?>
				</section>
			<?php endif; ?>

			<section class="mcp100p-card" aria-labelledby="mcp100p-step1">
				<div class="mcp100p-card__head">
					<span class="mcp100p-step" aria-hidden="true">1</span>
					<div>
						<h2 id="mcp100p-step1"><?php esc_html_e( 'Create an API key', '100pixel-ai-agent-connector' ); ?></h2>
						<p><?php esc_html_e( 'Create one key per app or device so you can revoke them separately.', '100pixel-ai-agent-connector' ); ?></p>
					</div>
				</div>

				<?php if ( ! $has_key ) : ?>
					<div class="mcp100p-callout is-info">
						<span class="dashicons dashicons-info" aria-hidden="true"></span>
						<p><?php esc_html_e( 'Start here: create an API key first. Your AI assistant needs it to connect, and it will be filled into the connection URL and setup instructions automatically.', '100pixel-ai-agent-connector' ); ?></p>
					</div>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mcp100p-create" id="mcp100p-create">
					<?php wp_nonce_field( 'mcp100p_create_key' ); ?>
					<input type="hidden" name="action" value="mcp100p_create_key">
					<label for="mcp100p-label" class="screen-reader-text"><?php esc_html_e( 'Key name', '100pixel-ai-agent-connector' ); ?></label>
					<input type="text" id="mcp100p-label" name="label" maxlength="80" placeholder="<?php esc_attr_e( 'Key name, e.g. "ChatGPT" or "Claude on my laptop"', '100pixel-ai-agent-connector' ); ?>">
					<button type="submit" class="button button-primary"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e( 'Generate key', '100pixel-ai-agent-connector' ); ?></button>
				</form>

				<?php if ( $keys ) : ?>
					<table class="mcp100p-keys">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Name', '100pixel-ai-agent-connector' ); ?></th>
								<?php if ( $is_admin ) : ?>
									<th scope="col"><?php esc_html_e( 'User', '100pixel-ai-agent-connector' ); ?></th>
								<?php endif; ?>
								<th scope="col"><?php esc_html_e( 'Key', '100pixel-ai-agent-connector' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Created', '100pixel-ai-agent-connector' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Last used', '100pixel-ai-agent-connector' ); ?></th>
								<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', '100pixel-ai-agent-connector' ); ?></span></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $keys as $id => $key ) :
								$owner = get_userdata( (int) $key['user_id'] );
								?>
								<tr>
									<td class="mcp100p-keys__name" data-label="<?php esc_attr_e( 'Name', '100pixel-ai-agent-connector' ); ?>"><span class="mcp100p-keys__label"><strong><?php echo esc_html( $key['label'] ); ?></strong>
									<?php
									if ( $id === $latest_id ) :
										?>
										<span class="mcp100p-tag is-accent"><?php esc_html_e( 'Latest · used in setup', '100pixel-ai-agent-connector' ); ?></span><?php endif; ?></span></td>
									<?php if ( $is_admin ) : ?>
										<td data-label="<?php esc_attr_e( 'User', '100pixel-ai-agent-connector' ); ?>"><?php echo esc_html( $owner ? $owner->user_login : '—' ); ?></td>
									<?php endif; ?>
									<td data-label="<?php esc_attr_e( 'Key', '100pixel-ai-agent-connector' ); ?>"><code>mcp100p_<?php echo esc_html( $id ); ?>_…<?php echo esc_html( isset( $key['hint'] ) ? $key['hint'] : '' ); ?></code></td>
									<td data-label="<?php esc_attr_e( 'Created', '100pixel-ai-agent-connector' ); ?>"><?php echo esc_html( wp_date( get_option( 'date_format' ), (int) $key['created'] ) ); ?></td>
									<td data-label="<?php esc_attr_e( 'Last used', '100pixel-ai-agent-connector' ); ?>">
										<?php if ( $key['last_used'] ) : ?>
											<span class="mcp100p-lastused"><span class="mcp100p-dot is-on" aria-hidden="true"></span>
											<?php
											/* translators: %s: human time difference */
											echo esc_html( sprintf( __( '%s ago', '100pixel-ai-agent-connector' ), human_time_diff( (int) $key['last_used'] ) ) );
											?>
											</span>
										<?php else : ?>
											<span class="mcp100p-lastused"><span class="mcp100p-dot" aria-hidden="true"></span><?php esc_html_e( 'Never', '100pixel-ai-agent-connector' ); ?></span>
										<?php endif; ?>
									</td>
									<td class="mcp100p-keys__actions">
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mcp100p-revoke-form">
											<?php wp_nonce_field( 'mcp100p_revoke_key' ); ?>
											<input type="hidden" name="action" value="mcp100p_revoke_key">
											<input type="hidden" name="key_id" value="<?php echo esc_attr( $id ); ?>">
											<button type="submit" class="mcp100p-revoke"><span class="dashicons dashicons-trash" aria-hidden="true"></span><?php esc_html_e( 'Revoke', '100pixel-ai-agent-connector' ); ?></button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<div class="mcp100p-empty">
						<span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
						<p><?php esc_html_e( 'No API keys yet. Generate one above to connect your first AI assistant.', '100pixel-ai-agent-connector' ); ?></p>
					</div>
				<?php endif; ?>
			</section>

			<section class="mcp100p-card" id="mcp100p-connect" aria-labelledby="mcp100p-step2">
				<div class="mcp100p-card__head">
					<span class="mcp100p-step" aria-hidden="true">2</span>
					<div>
						<h2 id="mcp100p-step2"><?php esc_html_e( 'Connect your AI assistant', '100pixel-ai-agent-connector' ); ?></h2>
						<p>
							<?php
							if ( $latest_info ) {
								/* translators: %s: API key name */
								echo esc_html( sprintf( __( 'Pick your app. The instructions use your latest key, "%s".', '100pixel-ai-agent-connector' ), $latest_info['label'] ) );
							} else {
								esc_html_e( 'You need an API key before you can connect an AI assistant.', '100pixel-ai-agent-connector' );
							}
							?>
						</p>
					</div>
				</div>

				<?php if ( ! $has_key ) : ?>
					<div class="mcp100p-empty mcp100p-empty--cta">
						<span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
						<p><strong><?php esc_html_e( 'Create an API key first', '100pixel-ai-agent-connector' ); ?></strong></p>
						<p>
							<?php
							echo count( $own_keys )
								? esc_html__( 'Your latest key was revoked, or was created before this page could remember keys. Create a new key and it will be filled into the connection URL and the setup steps for every AI app.', '100pixel-ai-agent-connector' )
								: esc_html__( 'Once you have a key, this section shows step-by-step setup for Claude, ChatGPT, Cursor, VS Code, Gemini and more, with your key already filled in.', '100pixel-ai-agent-connector' );
							?>
						</p>
						<a href="#mcp100p-create" class="button button-primary mcp100p-focus-create"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e( 'Create API key', '100pixel-ai-agent-connector' ); ?></a>
					</div>
				<?php else : ?>
				<div class="mcp100p-clients" role="tablist" aria-label="<?php esc_attr_e( 'AI apps', '100pixel-ai-agent-connector' ); ?>">
					<?php
					$first = true;
					foreach ( $clients as $slug => $client ) :
						?>
						<button type="button" class="mcp100p-client<?php echo $first ? ' is-active' : ''; ?>" role="tab" id="mcp100p-tab-<?php echo esc_attr( $slug ); ?>" aria-controls="mcp100p-panel-<?php echo esc_attr( $slug ); ?>" aria-selected="<?php echo $first ? 'true' : 'false'; ?>" tabindex="<?php echo $first ? '0' : '-1'; ?>">
							<span class="mcp100p-client__avatar mcp100p-icon--<?php echo esc_attr( $client['icon'] ); ?>" aria-hidden="true"><?php self::print_svg( self::icon( $client['icon'] ) ); ?></span>
							<span class="mcp100p-client__text">
								<span class="mcp100p-client__name"><?php echo esc_html( $client['label'] ); ?></span>
								<span class="mcp100p-client__kind"><?php echo esc_html( $client['kind'] ); ?></span>
							</span>
						</button>
						<?php
						$first = false;
					endforeach;
					?>
				</div>

					<?php
					$first = true;
					foreach ( $clients as $slug => $client ) :
						?>
					<div class="mcp100p-panel" id="mcp100p-panel-<?php echo esc_attr( $slug ); ?>" role="tabpanel" aria-labelledby="mcp100p-tab-<?php echo esc_attr( $slug ); ?>" <?php echo $first ? '' : 'hidden'; ?>>
						<?php if ( ! empty( $client['url_key'] ) && empty( $settings['allow_url_key'] ) ) : ?>
							<div class="mcp100p-callout is-warning">
								<span class="dashicons dashicons-warning" aria-hidden="true"></span>
								<p><?php esc_html_e( 'This app cannot send custom headers, so it needs the API key inside the URL. An administrator has turned that off in Settings.', '100pixel-ai-agent-connector' ); ?></p>
							</div>
						<?php else : ?>
							<ol class="mcp100p-steps">
								<?php foreach ( $client['steps'] as $step ) : ?>
									<li><?php echo wp_kses_post( $step ); ?></li>
								<?php endforeach; ?>
							</ol>
							<?php self::code_block( $client['code'], ! empty( $client['url_key'] ) ? __( 'Server URL with key', '100pixel-ai-agent-connector' ) : $client['label'] ); ?>
							<?php if ( ! empty( $client['prompt'] ) ) : ?>
								<div class="mcp100p-prompt">
									<p class="mcp100p-prompt__intro">
										<span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
										<span>
											<strong><?php esc_html_e( 'Or let the AI do it:', '100pixel-ai-agent-connector' ); ?></strong>
											<?php
											/* translators: %s: AI app name */
											echo esc_html( sprintf( __( 'copy this setup prompt and paste it into %s\'s chat. It will connect itself.', '100pixel-ai-agent-connector' ), $client['label'] ) );
											?>
										</span>
									</p>
									<?php self::code_block( $client['prompt'], __( 'Setup prompt', '100pixel-ai-agent-connector' ), true ); ?>
									<p class="mcp100p-prompt__note"><?php esc_html_e( 'The prompt contains your API key. Only paste it into your own AI app.', '100pixel-ai-agent-connector' ); ?></p>
								</div>
							<?php endif; ?>
							<?php if ( ! empty( $client['url_key'] ) ) : ?>
								<div class="mcp100p-callout">
									<span class="dashicons dashicons-lock" aria-hidden="true"></span>
									<p><?php esc_html_e( 'This URL contains your API key. Treat it like a password and do not share it.', '100pixel-ai-agent-connector' ); ?></p>
								</div>
							<?php endif; ?>
						<?php endif; ?>
					</div>
						<?php
						$first = false;
				endforeach;
					?>
				<?php endif; ?>

				<details class="mcp100p-help">
					<summary><?php esc_html_e( 'Trouble connecting?', '100pixel-ai-agent-connector' ); ?></summary>
					<ul>
						<li><?php esc_html_e( '"Missing API key" although you send the Authorization header: some Apache/CGI hosts strip that header. Use the X-API-Key header instead, or add this line to .htaccess:', '100pixel-ai-agent-connector' ); ?> <code>SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1</code></li>
						<li><?php esc_html_e( '404 or blocked requests: make sure the REST API (/wp-json/) is reachable and not blocked by a security plugin, firewall or CDN rule.', '100pixel-ai-agent-connector' ); ?></li>
						<li><?php esc_html_e( '"Invalid or revoked API key": the key was revoked, or its user no longer has a role that is allowed to use AI Agent Connector.', '100pixel-ai-agent-connector' ); ?></li>
					</ul>
				</details>

				<div class="mcp100p-support">
					<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
					<p>
						<strong><?php esc_html_e( 'Need help connecting the plugin to your AI / LLM?', '100pixel-ai-agent-connector' ); ?></strong>
						<?php esc_html_e( 'Contact us:', '100pixel-ai-agent-connector' ); ?>
						<a href="mailto:support@100pixel.com">support@100pixel.com</a>
					</p>
				</div>
			</section>

			<?php if ( $is_admin ) : ?>
				<section class="mcp100p-card" id="mcp100p-settings" aria-labelledby="mcp100p-settings-title">
					<div class="mcp100p-card__head">
						<span class="mcp100p-step mcp100p-step--icon" aria-hidden="true"><span class="dashicons dashicons-admin-generic"></span></span>
						<div>
							<h2 id="mcp100p-settings-title"><?php esc_html_e( 'Settings', '100pixel-ai-agent-connector' ); ?></h2>
							<p><?php esc_html_e( 'Control who can connect and what AI assistants are allowed to do.', '100pixel-ai-agent-connector' ); ?></p>
						</div>
					</div>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'mcp100p_save_settings' ); ?>
						<input type="hidden" name="action" value="mcp100p_save_settings">

						<div class="mcp100p-settings">
							<div class="mcp100p-setting">
								<div class="mcp100p-setting__text">
									<strong><?php esc_html_e( 'MCP access', '100pixel-ai-agent-connector' ); ?></strong>
									<span><?php esc_html_e( 'Allow AI assistants to connect to this site.', '100pixel-ai-agent-connector' ); ?></span>
								</div>
								<?php self::toggle( 'enabled', $settings['enabled'], __( 'MCP access', '100pixel-ai-agent-connector' ) ); ?>
							</div>

							<div class="mcp100p-setting">
								<div class="mcp100p-setting__text">
									<strong><?php esc_html_e( 'Read-only mode', '100pixel-ai-agent-connector' ); ?></strong>
									<span><?php esc_html_e( 'Only offer tools that read data. Every tool that creates, changes or deletes is hidden.', '100pixel-ai-agent-connector' ); ?></span>
								</div>
								<?php self::toggle( 'read_only', $settings['read_only'], __( 'Read-only mode', '100pixel-ai-agent-connector' ) ); ?>
							</div>

							<div class="mcp100p-setting">
								<div class="mcp100p-setting__text">
									<strong><?php esc_html_e( 'Key in URL', '100pixel-ai-agent-connector' ); ?></strong>
									<span><?php esc_html_e( 'Needed for Claude web/desktop connectors and ChatGPT, which cannot send custom headers. URLs can end up in server logs, so turn this off if you only use header-based apps.', '100pixel-ai-agent-connector' ); ?></span>
								</div>
								<?php self::toggle( 'allow_url_key', $settings['allow_url_key'], __( 'Key in URL', '100pixel-ai-agent-connector' ) ); ?>
							</div>

							<div class="mcp100p-setting mcp100p-setting--select">
								<div class="mcp100p-setting__text">
									<label for="mcp100p-cap"><strong><?php esc_html_e( 'Who can create keys', '100pixel-ai-agent-connector' ); ?></strong></label>
									<span><?php esc_html_e( 'Keys of users who no longer meet this level stop working.', '100pixel-ai-agent-connector' ); ?></span>
								</div>
								<select id="mcp100p-cap" name="key_capability">
									<?php foreach ( MCP100P_Settings::access_levels() as $cap => $label ) : ?>
										<option value="<?php echo esc_attr( $cap ); ?>" <?php selected( $settings['key_capability'], $cap ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>

						<h3 class="mcp100p-subhead"><?php esc_html_e( 'Tool groups', '100pixel-ai-agent-connector' ); ?></h3>
						<p class="mcp100p-muted"><?php esc_html_e( "Choose what AI assistants can work with. Every tool also checks the connected user's WordPress permissions.", '100pixel-ai-agent-connector' ); ?></p>

						<div class="mcp100p-groups">
							<?php
							foreach ( MCP100P_Settings::groups() as $group => $info ) :
								$note = '';
								if ( 'woocommerce' === $group && ! MCP100P_Tools_WooCommerce::available() ) {
									$note = __( 'WooCommerce not active', '100pixel-ai-agent-connector' );
								} elseif ( 'abilities' === $group && ! MCP100P_Tools_Abilities::available() ) {
									$note = __( 'Requires WordPress 6.9+', '100pixel-ai-agent-connector' );
								}
								?>
								<div class="mcp100p-group<?php echo $note ? ' is-unavailable' : ''; ?>">
									<div class="mcp100p-group__head">
										<strong><?php echo esc_html( $info['label'] ); ?></strong>
										<?php self::toggle( 'groups[' . $group . ']', ! empty( $settings['groups'][ $group ] ), $info['label'] ); ?>
									</div>
									<p><?php echo esc_html( $info['desc'] ); ?></p>
									<?php if ( $note ) : ?>
										<span class="mcp100p-tag"><?php echo esc_html( $note ); ?></span>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>

						<div class="mcp100p-actions">
							<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save settings', '100pixel-ai-agent-connector' ); ?></button>
						</div>
					</form>
				</section>
			<?php endif; ?>
			<footer class="mcp100p-footer">
				<span>
					<?php
					/* translators: %s: plugin version */
					echo esc_html( sprintf( __( '100pixel AI Agent Connector %s', '100pixel-ai-agent-connector' ), MCP100P_VERSION ) );
					?>
				</span>
				<span aria-hidden="true">·</span>
				<span><?php esc_html_e( 'Made by', '100pixel-ai-agent-connector' ); ?> <a href="https://100pixel.com" target="_blank" rel="noopener">100pixel.com</a></span>
				<span aria-hidden="true">·</span>
				<span><?php esc_html_e( 'Support:', '100pixel-ai-agent-connector' ); ?> <a href="mailto:support@100pixel.com">support@100pixel.com</a></span>
			</footer>
		</div>
		<?php
	}
}
