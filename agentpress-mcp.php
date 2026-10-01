<?php
/**
 * Plugin Name:       AgentPress MCP – AI Agent Connector for Claude & ChatGPT
 * Description:       MCP server for WordPress by 100pixel.com. Connect Claude, ChatGPT, Cursor, VS Code, Gemini CLI, Windsurf and any AI agent to manage posts, media, settings, plugins and WooCommerce.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            100pixel.com
 * Author URI:        https://100pixel.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       agentpress-mcp
 *
 * @package AgentPressMCP
 */

defined( 'ABSPATH' ) || exit;

// Another copy (e.g. the pre-rename "mcp-100pixel" folder) is already loaded: stop instead of fatally redeclaring classes.
if ( defined( 'MCP100P_VERSION' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Two copies of AgentPress MCP are active. Deactivate the older copy (it may be listed as "100Pixel MCP").', 'agentpress-mcp' ) . '</p></div>';
		}
	);
	return;
}

define( 'MCP100P_VERSION', '1.0.0' );
define( 'MCP100P_FILE', __FILE__ );
define( 'MCP100P_DIR', plugin_dir_path( __FILE__ ) );
define( 'MCP100P_URL', plugin_dir_url( __FILE__ ) );

require_once MCP100P_DIR . 'includes/class-settings.php';
require_once MCP100P_DIR . 'includes/class-auth.php';
require_once MCP100P_DIR . 'includes/class-util.php';
require_once MCP100P_DIR . 'includes/class-tools.php';
require_once MCP100P_DIR . 'includes/class-server.php';
require_once MCP100P_DIR . 'includes/class-admin.php';
require_once MCP100P_DIR . 'includes/tools/class-tools-site.php';
require_once MCP100P_DIR . 'includes/tools/class-tools-content.php';
require_once MCP100P_DIR . 'includes/tools/class-tools-media.php';
require_once MCP100P_DIR . 'includes/tools/class-tools-users.php';
require_once MCP100P_DIR . 'includes/tools/class-tools-settings.php';
require_once MCP100P_DIR . 'includes/tools/class-tools-plugins-themes.php';
require_once MCP100P_DIR . 'includes/tools/class-tools-woocommerce.php';
require_once MCP100P_DIR . 'includes/tools/class-tools-abilities.php';

register_activation_hook(
	__FILE__,
	function () {
		if ( false === get_option( MCP100P_Settings::OPTION ) ) {
			add_option( MCP100P_Settings::OPTION, MCP100P_Settings::defaults() );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		MCP100P_Server::init();
		if ( is_admin() ) {
			MCP100P_Admin::init();
		}
	}
);

// Declare compatibility with WooCommerce High-Performance Order Storage.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', MCP100P_FILE, true );
		}
	}
);
