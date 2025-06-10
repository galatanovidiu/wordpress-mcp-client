<?php
/**
 * Main Client class for WordPress MCP Client plugin
 *
 * @package WordPressMcpClient\Core
 */

declare(strict_types=1);

namespace WordPressMcpClient\Core;

/**
 * Main Client class - singleton pattern
 */
class Client {
	/**
	 * The single instance of the class.
	 *
	 * @var Client|null
	 */
	private static ?Client $instance = null;

	/**
	 * OpenAI MCP Client instance
	 *
	 * @var OpenAiMcpClient|null
	 */
	private ?OpenAiMcpClient $openai_client = null;

	/**
	 * Plugin settings
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	/**
	 * Private constructor to prevent direct instantiation
	 */
	private function __construct() {
		$this->load_settings();
	}

	/**
	 * Get the singleton instance
	 *
	 * @return Client
	 */
	public static function instance(): Client {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Load plugin settings
	 *
	 * @return void
	 */
	private function load_settings(): void {
		$this->settings = [
			'openai_api_key' => get_option( 'wordpress_mcp_client_openai_api_key', '' ),
			'model' => get_option( 'wordpress_mcp_client_model', 'gpt-4.1' ),
			'mcp_servers' => get_option( 'wordpress_mcp_client_mcp_servers', [] ),
		];
	}

	/**
	 * Get a setting value
	 *
	 * @param string $key The setting key
	 * @param mixed $default Default value if setting doesn't exist
	 * @return mixed
	 */
	public function get_setting( string $key, mixed $default = null ): mixed {
		return $this->settings[ $key ] ?? $default;
	}

	/**
	 * Set a setting value
	 *
	 * @param string $key The setting key
	 * @param mixed $value The setting value
	 * @return bool
	 */
	public function set_setting( string $key, mixed $value ): bool {
		$this->settings[ $key ] = $value;
		return update_option( "wordpress_mcp_client_{$key}", $value );
	}

	/**
	 * Get the OpenAI MCP client instance
	 *
	 * @return OpenAiMcpClient|null
	 */
	public function get_openai_client(): ?OpenAiMcpClient {
		return $this->openai_client;
	}

	/**
	 * Set the OpenAI MCP client instance
	 *
	 * @param OpenAiMcpClient $client
	 * @return void
	 */
	public function set_openai_client( OpenAiMcpClient $client ): void {
		$this->openai_client = $client;
	}

	/**
	 * Check if the client is properly configured
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return ! empty( $this->get_setting( 'openai_api_key' ) );
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization
	 */
	public function __wakeup(): void {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
} 