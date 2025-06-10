<?php
/**
 * Settings class for WordPress MCP Client plugin
 *
 * @package WordPressMcpClient\Core
 */

declare(strict_types=1);

namespace WordPressMcpClient\Core;

/**
 * Settings class for managing plugin configuration
 */
class Settings {
	/**
	 * Settings page slug
	 */
	private const SETTINGS_PAGE = 'wordpress-mcp-client-settings';

	/**
	 * Settings group
	 */
	private const SETTINGS_GROUP = 'wordpress_mcp_client_settings';

	/**
	 * Constructor
	 */
	public function __construct() {
		// Note: For now, this is a minimal settings class since the requirement is for CLI-only
		// We'll keep settings in options but won't create admin interface yet
		$this->init_default_settings();
	}

	/**
	 * Initialize default settings
	 *
	 * @return void
	 */
	private function init_default_settings(): void {
		// Set default values if they don't exist
		if ( ! get_option( 'wordpress_mcp_client_openai_api_key' ) ) {
			add_option( 'wordpress_mcp_client_openai_api_key', '' );
		}

		if ( ! get_option( 'wordpress_mcp_client_model' ) ) {
			add_option( 'wordpress_mcp_client_model', 'gpt-4.1' );
		}

		if ( ! get_option( 'wordpress_mcp_client_mcp_servers' ) ) {
			add_option( 'wordpress_mcp_client_mcp_servers', [] );
		}
	}

	/**
	 * Get all settings
	 *
	 * @return array<string, mixed>
	 */
	public function get_all_settings(): array {
		return [
			'openai_api_key' => get_option( 'wordpress_mcp_client_openai_api_key', '' ),
			'model' => get_option( 'wordpress_mcp_client_model', 'gpt-4.1' ),
			'mcp_servers' => get_option( 'wordpress_mcp_client_mcp_servers', [] ),
		];
	}

	/**
	 * Update a setting
	 *
	 * @param string $key The setting key
	 * @param mixed $value The setting value
	 * @return bool
	 */
	public function update_setting( string $key, mixed $value ): bool {
		update_option( "wordpress_mcp_client_{$key}", $value );
		$option = get_option( "wordpress_mcp_client_{$key}" );
		return $option === $value;
	}

	/**
	 * Get a specific setting
	 *
	 * @param string $key The setting key
	 * @param mixed $default Default value
	 * @return mixed
	 */
	public function get_setting( string $key, mixed $default = null ): mixed {
		return get_option( "wordpress_mcp_client_{$key}", $default );
	}

	/**
	 * Delete a setting
	 *
	 * @param string $key The setting key
	 * @return bool
	 */
	public function delete_setting( string $key ): bool {
		return delete_option( "wordpress_mcp_client_{$key}" );
	}

	/**
	 * Add MCP server configuration
	 *
	 * @param array<string, mixed> $server_config Server configuration
	 * @return bool
	 */
	public function add_mcp_server( array $server_config ): bool {
		$servers = $this->get_setting( 'mcp_servers', [] );
		$servers[] = $server_config;
		return $this->update_setting( 'mcp_servers', $servers );
	}

	/**
	 * Add MCP server configuration with authentication
	 *
	 * @param string $name Server name
	 * @param string $url Server URL
	 * @param array<string, mixed> $auth_headers Authentication headers
	 * @param array<string, mixed> $options Additional options
	 * @return bool
	 */
	public function add_mcp_server_with_auth( string $name, string $url, array $auth_headers = [], array $options = [] ): bool {
		$server_config = [
			'name' => $name,
			'url' => $url,
		];

		if ( ! empty( $auth_headers ) ) {
			$server_config['headers'] = $auth_headers;
		}

		if ( ! empty( $options ) ) {
			$server_config = array_merge( $server_config, $options );
		}

		return $this->add_mcp_server( $server_config );
	}

	/**
	 * Remove MCP server configuration
	 *
	 * @param string $server_name Server name to remove
	 * @return bool
	 */
	public function remove_mcp_server( string $server_name ): bool {
		$servers = $this->get_setting( 'mcp_servers', [] );
		$servers = array_filter( $servers, function( $server ) use ( $server_name ) {
			return $server['name'] !== $server_name;
		});
		return $this->update_setting( 'mcp_servers', array_values( $servers ) );
	}

	/**
	 * Get MCP server configurations
	 *
	 * @return array<string, mixed>
	 */
	public function get_mcp_servers(): array {
		return $this->get_setting( 'mcp_servers', [] );
	}

	/**
	 * Reset all settings to defaults
	 *
	 * @return bool
	 */
	public function reset_settings(): bool {
		$success = true;
		
		$success = $success && $this->update_setting( 'openai_api_key', '' );
		$success = $success && $this->update_setting( 'model', 'gpt-4.1' );
		$success = $success && $this->update_setting( 'mcp_servers', [] );

		return $success;
	}

	/**
	 * Validate OpenAI API key format
	 *
	 * @param string $api_key
	 * @return bool
	 */
	public function validate_openai_api_key( string $api_key ): bool {
		// Support both legacy and project-based API key formats
		// Legacy: sk-[48 chars]
		// Project: sk-proj-[random string]
		$legacy_pattern = '/^sk-[a-zA-Z0-9]{48}$/';
		$project_pattern = '/^sk-proj-[a-zA-Z0-9_-]{20,}$/';
		
		return preg_match( $legacy_pattern, $api_key ) === 1 || preg_match( $project_pattern, $api_key ) === 1;
	}

	/**
	 * Validate MCP server configuration
	 *
	 * @param array<string, mixed> $server_config
	 * @return array<string, mixed> Validation result with success and message
	 */
	public function validate_mcp_server( array $server_config ): array {
		$required_fields = [ 'name', 'url' ];
		$missing_fields = [];

		foreach ( $required_fields as $field ) {
			if ( empty( $server_config[ $field ] ) ) {
				$missing_fields[] = $field;
			}
		}

		if ( ! empty( $missing_fields ) ) {
			return [
				'success' => false,
				'message' => 'Missing required fields: ' . implode( ', ', $missing_fields ),
			];
		}

		// Validate URL format
		if ( ! filter_var( $server_config['url'], FILTER_VALIDATE_URL ) ) {
			return [
				'success' => false,
				'message' => 'Invalid URL format',
			];
		}

		return [
			'success' => true,
			'message' => 'Server configuration is valid',
		];
	}
} 