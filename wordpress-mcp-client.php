<?php
/**
 * Plugin name:       WordPress MCP Client
 * Description:       A WordPress plugin that acts as an MCP (Model Context Protocol) client using OpenAI to interact with MCP servers and AI assistants.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            WordPress MCP Team
 * License:           GPL-2.0-or-later
 * License URI:       https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain:       wordpress-mcp-client
 * Domain Path:       /languages
 *
 * @package WordPress MCP Client
 */

declare(strict_types=1);

use WordPressMcpClient\Core\Client;
use WordPressMcpClient\Core\OpenAiMcpClient;
use WordPressMcpClient\Core\Settings;
use WordPressMcpClient\CLI\McpCommands;
use WordPressMcpClient\Admin\AdminInterface;

define( 'WORDPRESS_MCP_CLIENT_VERSION', '1.0.0' );
define( 'WORDPRESS_MCP_CLIENT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WORDPRESS_MCP_CLIENT_URL', plugin_dir_url( __FILE__ ) );

// Check if we're in WordPress environment
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if Composer autoloader exists.
if ( ! file_exists( WORDPRESS_MCP_CLIENT_PATH . 'vendor/autoload.php' ) ) {
	add_action( 'admin_notices', function() {
		echo '<div class="notice notice-error"><p>';
		printf(
			'WordPress MCP Client: Please run <code>composer install</code> in the plugin directory: <code>%s</code>',
			esc_html( WORDPRESS_MCP_CLIENT_PATH )
		);
		echo '</p></div>';
	});
	return;
}

require_once WORDPRESS_MCP_CLIENT_PATH . 'vendor/autoload.php';

/**
 * Get the WordPress MCP Client instance.
 *
 * @return Client
 */
function wp_mcp_client(): Client {
	return Client::instance();
}

/**
 * Initialize the plugin.
 */
function init_wordpress_mcp_client(): void {
	// Initialize the main client
	$client = wp_mcp_client();
	
	// Initialize settings
	new Settings();
	
	// Initialize OpenAI MCP client
	new OpenAiMcpClient( $client );
	
	// Initialize admin interface
	if ( is_admin() ) {
		new AdminInterface( $client );
	}
	
	// Initialize CLI commands if WP-CLI is available
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		new McpCommands( $client );
	}
}

// Initialize the plugin on plugins_loaded to ensure all dependencies are available.
add_action( 'plugins_loaded', 'init_wordpress_mcp_client' );

// Register activation hook
register_activation_hook( __FILE__, function() {
	// Add default settings on activation
	add_option( 'wordpress_mcp_client_openai_api_key', '' );
	add_option( 'wordpress_mcp_client_model', 'gpt-4.1' );
	add_option( 'wordpress_mcp_client_mcp_servers', [] );
} );

// Register deactivation hook
register_deactivation_hook( __FILE__, function() {
	// Clean up if needed
} ); 