<?php
/**
 * Admin Interface class for WordPress MCP Client plugin
 *
 * @package WordPressMcpClient\Admin
 */

declare(strict_types=1);

namespace WordPressMcpClient\Admin;

use WordPressMcpClient\Core\Client;
use WordPressMcpClient\Core\JwtTokenManager;

/**
 * Admin Interface class for managing the chat UI and settings
 */
class AdminInterface {
	/**
	 * Main client instance
	 *
	 * @var Client
	 */
	private Client $client;

	/**
	 * Constructor
	 *
	 * @param Client $client
	 */
	public function __construct( Client $client ) {
		$this->client = $client;
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_mcp_chat_message', array( $this, 'handle_chat_message' ) );
		add_action( 'wp_ajax_mcp_get_config', array( $this, 'handle_get_config' ) );
		add_action( 'wp_ajax_mcp_get_jwt_status', array( $this, 'handle_get_jwt_status' ) );
		add_action( 'wp_ajax_mcp_refresh_jwt_token', array( $this, 'handle_refresh_jwt_token' ) );
		add_action( 'wp_ajax_mcp_test_connection', array( $this, 'handle_test_connection' ) );
		add_action( 'wp_ajax_mcp_save_config', array( $this, 'handle_save_config' ) );
	}

	/**
	 * Parse JSON request body
	 *
	 * @return array<string, mixed>|null
	 */
	private function parse_json_request(): ?array {
		$input = file_get_contents( 'php://input' );
		if ( empty( $input ) ) {
			return null;
		}

		$data = json_decode( $input, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}

		return $data;
	}

	/**
	 * Get request data from either JSON body or POST data
	 *
	 * @return array<string, mixed>
	 */
	private function get_request_data(): array {
		// Try to parse JSON body first
		$json_data = $this->parse_json_request();
		if ( $json_data !== null ) {
			return $json_data;
		}

		// Fallback to POST data for backward compatibility
		return $_POST;
	}

	/**
	 * Add admin menu pages
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			'MCP Client',
			'MCP Client',
			'manage_options',
			'mcp-client',
			array( $this, 'render_chat_page' ),
			'dashicons-format-chat',
			30
		);

		add_submenu_page(
			'mcp-client',
			'MCP Chat',
			'Chat',
			'manage_options',
			'mcp-client',
			array( $this, 'render_chat_page' )
		);

		add_submenu_page(
			'mcp-client',
			'MCP Settings',
			'Settings',
			'manage_options',
			'mcp-client-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook_suffix
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook_suffix ): void {
		// Only load on our admin pages
		if ( ! str_contains( $hook_suffix, 'mcp-client' ) ) {
			return;
		}

		// Load asset file for dependencies and version.
		$asset_file = WORDPRESS_MCP_CLIENT_PATH . 'build/index.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
			'version'      => WORDPRESS_MCP_CLIENT_VERSION,
		);

		// Enqueue our main admin script.
		wp_enqueue_script(
			'mcp-client-admin',
			WORDPRESS_MCP_CLIENT_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Enqueue admin styles.
		wp_enqueue_style(
			'mcp-client-admin',
			WORDPRESS_MCP_CLIENT_URL . 'build/index.css',
			array( 'wp-components' ),
			$asset['version']
		);

		// Localize script with data
		wp_localize_script(
			'mcp-client-admin',
			'mcpClientAdmin',
			array(
				'nonce'        => wp_create_nonce( 'mcp_client_nonce' ),
				'apiUrl'       => admin_url( 'admin-ajax.php' ),
				'isConfigured' => $this->client->is_configured(),
				'currentUser'  => wp_get_current_user()->display_name,
			)
		);
	}

	/**
	 * Render the chat page
	 *
	 * @return void
	 */
	public function render_chat_page(): void {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'MCP Client Chat', 'wordpress-mcp-client' ) . '</h1>';
		echo '<div id="mcp-chat-app"></div>';
		echo '</div>';
	}

	/**
	 * Render the settings page
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'MCP Client Settings', 'wordpress-mcp-client' ) . '</h1>';
		echo '<div id="mcp-settings-app"></div>';
		echo '</div>';
	}

	/**
	 * Handle AJAX chat message request
	 *
	 * @return void
	 */
	public function handle_chat_message(): void {
		$request_data = $this->get_request_data();

		// Verify nonce
		if ( ! wp_verify_nonce( $request_data['nonce'] ?? '', 'mcp_client_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		$message              = sanitize_textarea_field( $request_data['message'] ?? '' );
		$model                = sanitize_text_field( $request_data['model'] ?? '' );
		$conversation_history = $request_data['conversation_history'] ?? array();

		if ( empty( $message ) ) {
			wp_send_json_error( 'Message is required' );
			return;
		}

		// Validate conversation history format
		if ( ! is_array( $conversation_history ) ) {
			$conversation_history = array();
		}

		// Sanitize conversation history
		$sanitized_history = array();
		foreach ( $conversation_history as $msg ) {
			if ( isset( $msg['role'] ) && isset( $msg['content'] ) &&
				in_array( $msg['role'], array( 'user', 'assistant' ), true ) ) {
				$sanitized_history[] = array(
					'role'    => sanitize_text_field( $msg['role'] ),
					'content' => sanitize_textarea_field( $msg['content'] ),
				);
			}
		}

		try {
			$openai_client = $this->client->get_openai_client();
			if ( ! $openai_client ) {
				wp_send_json_error( 'OpenAI client not initialized. Please configure your API key.' );
				return;
			}

			// Use internal MCP server with streamable transport
			$internal_server = array(
				'name'      => 'wordpress-internal',
				'url'       => home_url( '/wp-json/wp/v2/wpmcp/streamable' ),
				'transport' => 'streamable',
			);

			$options = array();
			if ( ! empty( $model ) ) {
				$options['model'] = $model;
			}

			// Add conversation history to options
			$options['conversation_history'] = $sanitized_history;

			$result = $openai_client->send_message( $message, array( $internal_server ), $options );

			if ( $result && $result['success'] ) {
				wp_send_json_success( $result );
			} else {
				wp_send_json_error( $result['error'] ?? 'Unknown error occurred' );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( 'Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Handle AJAX get config request
	 *
	 * @return void
	 */
	public function handle_get_config(): void {
		$request_data = $this->get_request_data();

		// Verify nonce
		if ( ! wp_verify_nonce( $request_data['nonce'] ?? '', 'mcp_client_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		$jwt_manager = new JwtTokenManager();
		$jwt_info    = $jwt_manager->get_token_info();

		$config = array(
			'model'              => $this->client->get_setting( 'model', 'gpt-4.1' ),
			'api_key_configured' => ! empty( $this->client->get_setting( 'openai_api_key' ) ),
			'mcp_server_enabled' => is_plugin_active( 'wordpress-mcp/wordpress-mcp.php' ),
			'jwt_token_status'   => array(
				'has_token'      => $jwt_info['has_token'],
				'expires_in'     => $jwt_info['expires_in'],
				'is_expired'     => $jwt_info['is_expired'],
				'should_refresh' => $jwt_info['should_refresh'],
			),
		);

		wp_send_json_success( $config );
	}

	/**
	 * Handle AJAX get JWT status request
	 *
	 * @return void
	 */
	public function handle_get_jwt_status(): void {
		$request_data = $this->get_request_data();

		// Verify nonce
		if ( ! wp_verify_nonce( $request_data['nonce'] ?? '', 'mcp_client_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		$jwt_manager = new JwtTokenManager();
		$jwt_info    = $jwt_manager->get_token_info();

		wp_send_json_success( $jwt_info );
	}

	/**
	 * Handle AJAX refresh JWT token request
	 *
	 * @return void
	 */
	public function handle_refresh_jwt_token(): void {
		$request_data = $this->get_request_data();

		// Verify nonce
		if ( ! wp_verify_nonce( $request_data['nonce'] ?? '', 'mcp_client_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		$jwt_manager = new JwtTokenManager();

		// Clear existing token to force refresh
		$jwt_manager->clear_token();

		// Try to generate a new token
		$token = $jwt_manager->get_token();

		if ( $token ) {
			$jwt_info = $jwt_manager->get_token_info();
			wp_send_json_success(
				array(
					'message'    => 'JWT token generated successfully',
					'token_info' => $jwt_info,
				)
			);
		} else {
			wp_send_json_error( 'Failed to generate JWT token. Check error logs for details.' );
		}
	}

	/**
	 * Handle AJAX test connection request
	 *
	 * @return void
	 */
	public function handle_test_connection(): void {
		$request_data = $this->get_request_data();

		// Verify nonce
		if ( ! wp_verify_nonce( $request_data['nonce'] ?? '', 'mcp_client_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		try {
			$openai_client = $this->client->get_openai_client();
			if ( ! $openai_client ) {
				wp_send_json_error( 'OpenAI client not initialized. Please configure your API key in WP-CLI.' );
				return;
			}

			$result = $openai_client->test_connection();

			if ( $result['success'] ) {
				wp_send_json_success( $result );
			} else {
				wp_send_json_error( $result['message'] );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( 'Test failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Handle AJAX save config request
	 *
	 * @return void
	 */
	public function handle_save_config(): void {
		$request_data = $this->get_request_data();

		// Verify nonce
		if ( ! wp_verify_nonce( $request_data['nonce'] ?? '', 'mcp_client_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		$api_key = sanitize_text_field( $request_data['api_key'] ?? '' );
		$model   = sanitize_text_field( $request_data['model'] ?? '' );

		$updated = false;
		$errors  = array();

		// Validate and save API key
		if ( ! empty( $api_key ) ) {
			if ( preg_match( '/^sk-(proj-)?[a-zA-Z0-9_-]{20,}$/', $api_key ) ) {
				$this->client->set_setting( 'openai_api_key', $api_key );
				$updated = true;
			} else {
				$errors[] = 'Invalid OpenAI API key format';
			}
		}

		// Validate and save model
		if ( ! empty( $model ) ) {
			$allowed_models = array( 'gpt-4.1', 'gpt-4.1-mini', 'gpt-4.1-nano' );
			if ( in_array( $model, $allowed_models, true ) ) {
				$this->client->set_setting( 'model', $model );
				$updated = true;
			} else {
				$errors[] = 'Invalid model selection';
			}
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( implode( ', ', $errors ) );
		} elseif ( $updated ) {
			wp_send_json_success( 'Configuration updated successfully' );
		} else {
			wp_send_json_error( 'No valid configuration provided' );
		}
	}
}
