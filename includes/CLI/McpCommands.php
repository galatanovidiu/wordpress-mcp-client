<?php
/**
 * WP-CLI Commands for WordPress MCP Client plugin
 *
 * @package WordPressMcpClient\CLI
 */

declare(strict_types=1);

namespace WordPressMcpClient\CLI;

use WordPressMcpClient\Core\Client;
use WordPressMcpClient\Core\Settings;
use WP_CLI;
use WP_CLI_Command;
use Exception;

/**
 * WordPress MCP Client CLI commands
 */
class McpCommands extends WP_CLI_Command {
	/**
	 * Main client instance
	 *
	 * @var Client
	 */
	private Client $client;

	/**
	 * Settings instance
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor
	 *
	 * @param Client $client
	 */
	public function __construct( Client $client ) {
		$this->client = $client;
		$this->settings = new Settings();
		
		// Register WP-CLI commands
		WP_CLI::add_command( 'mcp config', [ $this, 'config' ] );
		WP_CLI::add_command( 'mcp chat', [ $this, 'chat' ] );
		WP_CLI::add_command( 'mcp servers', [ $this, 'servers' ] );
		WP_CLI::add_command( 'mcp test', [ $this, 'test' ] );
		WP_CLI::add_command( 'mcp tools', [ $this, 'tools' ] );
	}

	/**
	 * Manage configuration settings
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : The action to perform. Options: set, get, list, reset
	 *
	 * [<key>]
	 * : The setting key (required for set/get actions)
	 *
	 * [<value>]
	 * : The setting value (required for set action)
	 *
	 * ## EXAMPLES
	 *
	 *     # Set OpenAI API key
	 *     wp mcp config set openai_api_key sk-your-api-key-here
	 *
	 *     # Get current model
	 *     wp mcp config get model
	 *
	 *     # List all settings
	 *     wp mcp config list
	 *
	 *     # Reset all settings
	 *     wp mcp config reset
	 *
	 * @param array<string> $args Positional arguments
	 * @param array<string, mixed> $assoc_args Associative arguments
	 */
	public function config( array $args, array $assoc_args ): void {
		$action = $args[0] ?? '';

		switch ( $action ) {
			case 'set':
				$this->config_set( $args );
				break;
			case 'get':
				$this->config_get( $args );
				break;
			case 'list':
				$this->config_list();
				break;
			case 'reset':
				$this->config_reset();
				break;
			default:
				WP_CLI::error( 'Invalid action. Use: set, get, list, or reset' );
		}
	}

	/**
	 * Set a configuration value
	 *
	 * @param array<string> $args
	 */
	private function config_set( array $args ): void {
		if ( ! isset( $args[1] ) || ! isset( $args[2] ) ) {
			WP_CLI::error( 'Usage: wp mcp config set <key> <value>' );
			return;
		}

		$key = $args[1];
		$value = $args[2];

		// Special handling for API key validation
		if ( $key === 'openai_api_key' && ! $this->settings->validate_openai_api_key( $value ) ) {
			WP_CLI::warning( 'API key format appears invalid. Expected format: sk-[48 chars] or sk-proj-[project key]' );
		}

		if ( $this->settings->update_setting( $key, $value ) ) {
			WP_CLI::success( "Set {$key} successfully." );
		} else {
			WP_CLI::error( "Failed to set {$key}." );
		}
	}

	/**
	 * Get a configuration value
	 *
	 * @param array<string> $args
	 */
	private function config_get( array $args ): void {
		if ( ! isset( $args[1] ) ) {
			WP_CLI::error( 'Usage: wp mcp config get <key>' );
			return;
		}

		$key = $args[1];
		$value = $this->settings->get_setting( $key );

		if ( $value === null ) {
			WP_CLI::warning( "Setting '{$key}' not found." );
		} else {
			// Mask sensitive values
			if ( $key === 'openai_api_key' && ! empty( $value ) ) {
				$masked_value = substr( $value, 0, 10 ) . '...' . substr( $value, -4 );
				WP_CLI::line( "{$key}: {$masked_value}" );
			} else {
				WP_CLI::line( "{$key}: " . ( is_array( $value ) ? json_encode( $value ) : $value ) );
			}
		}
	}

	/**
	 * List all configuration values
	 */
	private function config_list(): void {
		$settings = $this->settings->get_all_settings();
		
		WP_CLI::line( 'Current configuration:' );
		WP_CLI::line( '===================' );

		foreach ( $settings as $key => $value ) {
			if ( $key === 'openai_api_key' && ! empty( $value ) ) {
				$masked_value = substr( $value, 0, 10 ) . '...' . substr( $value, -4 );
				WP_CLI::line( "{$key}: {$masked_value}" );
			} else {
				WP_CLI::line( "{$key}: " . ( is_array( $value ) ? json_encode( $value ) : $value ) );
			}
		}
	}

	/**
	 * Reset all configuration values
	 */
	private function config_reset(): void {
		if ( $this->settings->reset_settings() ) {
			WP_CLI::success( 'All settings reset to defaults.' );
		} else {
			WP_CLI::error( 'Failed to reset settings.' );
		}
	}

	/**
	 * Send a message to OpenAI with MCP integration
	 *
	 * ## OPTIONS
	 *
	 * <message>
	 * : The message to send to the AI
	 *
	 * [--model=<model>]
	 * : Override the default model
	 *
	 * [--servers=<servers>]
	 * : Comma-separated list of MCP server names to use
	 *
	 * ## EXAMPLES
	 *
	 *     # Send a simple message
	 *     wp mcp chat "Hello, can you help me with WordPress?"
	 *
	 *     # Use a specific model
	 *     wp mcp chat "Analyze my site" --model=gpt-4.1-mini
	 *
	 *     # Use specific MCP servers
	 *     wp mcp chat "Get my latest posts" --servers=wordpress-server,another-server
	 *
	 * @param array<string> $args Positional arguments
	 * @param array<string, mixed> $assoc_args Associative arguments
	 */
	public function chat( array $args, array $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Usage: wp mcp chat <message>' );
			return;
		}

		$message = $args[0];
		$model = $assoc_args['model'] ?? null;
		$servers_param = $assoc_args['servers'] ?? '';

		// Get MCP servers
		$mcp_servers = [];
		if ( ! empty( $servers_param ) ) {
			$server_names = array_map( 'trim', explode( ',', $servers_param ) );
			$all_servers = $this->settings->get_mcp_servers();
			
			foreach ( $server_names as $server_name ) {
				$server = array_filter( $all_servers, function( $s ) use ( $server_name ) {
					return $s['name'] === $server_name;
				});
				
				if ( ! empty( $server ) ) {
					$mcp_servers[] = array_values( $server )[0];
				} else {
					WP_CLI::warning( "MCP server '{$server_name}' not found." );
				}
			}
		} else {
			$mcp_servers = $this->settings->get_mcp_servers();
		}

		// Check if client is configured
		if ( ! $this->client->is_configured() ) {
			WP_CLI::error( 'OpenAI API key not configured. Use: wp mcp config set openai_api_key <your-key>' );
			return;
		}

		WP_CLI::line( 'Sending message to OpenAI...' );

		try {
			$openai_client = $this->client->get_openai_client();
			if ( ! $openai_client ) {
				WP_CLI::error( 'OpenAI client not initialized.' );
				return;
			}

			$options = [];
			if ( $model ) {
				$options['model'] = $model;
			}

			$result = $openai_client->send_message( $message, $mcp_servers, $options );

			if ( $result && $result['success'] ) {
				WP_CLI::line( '' );
				WP_CLI::line( 'Response:' );
				WP_CLI::line( '=========' );
				WP_CLI::line( $result['message'] );
				
				if ( isset( $result['usage'] ) && $result['usage'] ) {
					WP_CLI::line( '' );
					WP_CLI::line( 'Usage:' );
					WP_CLI::line( "Tokens: {$result['usage']['total_tokens']}" );
				}
			} else {
				WP_CLI::error( $result['error'] ?? 'Unknown error occurred' );
			}

		} catch ( Exception $e ) {
			WP_CLI::error( 'Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Manage MCP server configurations
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : The action to perform. Options: add, remove, list
	 *
	 * [<name>]
	 * : Server name (required for add/remove)
	 *
	 * [<url>]
	 * : Server URL (required for add)
	 *
	 * [--auth-header=<header>]
	 * : Authentication header in format "Name: Value" (for add action)
	 *
	 * [--allowed-tools=<tools>]
	 * : Comma-separated list of allowed tools (for add action)
	 *
	 * [--require-approval=<approval>]
	 * : Approval requirement: always, never (for add action)
	 *
	 * ## EXAMPLES
	 *
	 *     # Add a new MCP server
	 *     wp mcp servers add wordpress-server http://localhost:3000
	 *
	 *     # Add a server with authentication
	 *     wp mcp servers add protected-server https://api.example.com/mcp --auth-header="Authorization: Bearer token123"
	 *
	 *     # Add a server with API key
	 *     wp mcp servers add secure-server https://secure.example.com/mcp --auth-header="X-API-Key: sk-12345"
	 *
	 *     # Add a server with limited tools
	 *     wp mcp servers add limited-server http://api.example.com/mcp --allowed-tools="search,list" --require-approval=always
	 *
	 *     # Remove a server
	 *     wp mcp servers remove wordpress-server
	 *
	 *     # List all servers
	 *     wp mcp servers list
	 *
	 * @param array<string> $args Positional arguments
	 * @param array<string, mixed> $assoc_args Associative arguments
	 */
	public function servers( array $args, array $assoc_args ): void {
		$action = $args[0] ?? '';

		switch ( $action ) {
			case 'add':
				$this->servers_add( $args, $assoc_args );
				break;
			case 'remove':
				$this->servers_remove( $args );
				break;
			case 'list':
				$this->servers_list();
				break;
			default:
				WP_CLI::error( 'Invalid action. Use: add, remove, or list' );
		}
	}

	/**
	 * Add a new MCP server
	 *
	 * @param array<string> $args
	 * @param array<string, mixed> $assoc_args
	 */
	private function servers_add( array $args, array $assoc_args = [] ): void {
		if ( ! isset( $args[1] ) || ! isset( $args[2] ) ) {
			WP_CLI::error( 'Usage: wp mcp servers add <name> <url> [--auth-header="Name: Value"] [--allowed-tools="tool1,tool2"] [--require-approval=auto]' );
			return;
		}

		$server_config = [
			'name' => $args[1],
			'url' => $args[2],
		];

		// Parse authentication headers
		if ( isset( $assoc_args['auth-header'] ) ) {
			$auth_header = $assoc_args['auth-header'];
			if ( strpos( $auth_header, ':' ) !== false ) {
				list( $header_name, $header_value ) = explode( ':', $auth_header, 2 );
				$server_config['headers'] = [
					trim( $header_name ) => trim( $header_value ),
				];
			} else {
				WP_CLI::warning( 'Invalid auth-header format. Use "Name: Value"' );
			}
		}

		// Parse allowed tools
		if ( isset( $assoc_args['allowed-tools'] ) ) {
			$tools = array_map( 'trim', explode( ',', $assoc_args['allowed-tools'] ) );
			$server_config['allowed_tools'] = array_filter( $tools );
		}

		// Set approval requirement
		if ( isset( $assoc_args['require-approval'] ) ) {
			$approval = $assoc_args['require-approval'];
			if ( in_array( $approval, [ 'always', 'never' ], true ) ) {
				$server_config['require_approval'] = $approval;
			} else {
				WP_CLI::warning( 'Invalid require-approval value. Use always or never' );
			}
		}

		$validation = $this->settings->validate_mcp_server( $server_config );
		if ( ! $validation['success'] ) {
			WP_CLI::error( $validation['message'] );
			return;
		}

		if ( $this->settings->add_mcp_server( $server_config ) ) {
			WP_CLI::success( "Added MCP server '{$args[1]}' successfully." );
			
			// Show configuration details
			WP_CLI::line( '' );
			WP_CLI::line( 'Configuration:' );
			WP_CLI::line( "Name: {$server_config['name']}" );
			WP_CLI::line( "URL: {$server_config['url']}" );
			
			if ( isset( $server_config['headers'] ) ) {
				WP_CLI::line( 'Headers: ' . json_encode( array_keys( $server_config['headers'] ) ) );
			}
			
			if ( isset( $server_config['allowed_tools'] ) ) {
				WP_CLI::line( 'Allowed tools: ' . implode( ', ', $server_config['allowed_tools'] ) );
			}
			
			if ( isset( $server_config['require_approval'] ) ) {
				WP_CLI::line( "Approval: {$server_config['require_approval']}" );
			}
		} else {
			WP_CLI::error( "Failed to add MCP server '{$args[1]}'." );
		}
	}

	/**
	 * Remove an MCP server
	 *
	 * @param array<string> $args
	 */
	private function servers_remove( array $args ): void {
		if ( ! isset( $args[1] ) ) {
			WP_CLI::error( 'Usage: wp mcp servers remove <name>' );
			return;
		}

		$server_name = $args[1];

		if ( $this->settings->remove_mcp_server( $server_name ) ) {
			WP_CLI::success( "Removed MCP server '{$server_name}' successfully." );
		} else {
			WP_CLI::error( "Failed to remove MCP server '{$server_name}' or server not found." );
		}
	}

	/**
	 * List all MCP servers
	 */
	private function servers_list(): void {
		$servers = $this->settings->get_mcp_servers();

		if ( empty( $servers ) ) {
			WP_CLI::line( 'No MCP servers configured.' );
			return;
		}

		WP_CLI::line( 'Configured MCP servers:' );
		WP_CLI::line( '======================' );

		foreach ( $servers as $server ) {
			WP_CLI::line( "Name: {$server['name']}" );
			WP_CLI::line( "URL: {$server['url']}" );
			
			// Show authentication headers (masked for security)
			if ( isset( $server['headers'] ) && is_array( $server['headers'] ) ) {
				$masked_headers = [];
				foreach ( $server['headers'] as $name => $value ) {
					// Mask sensitive values
					if ( strlen( $value ) > 10 ) {
						$masked_value = substr( $value, 0, 4 ) . '...' . substr( $value, -4 );
					} else {
						$masked_value = str_repeat( '*', strlen( $value ) );
					}
					$masked_headers[] = "{$name}: {$masked_value}";
				}
				WP_CLI::line( "Headers: " . implode( ', ', $masked_headers ) );
			}
			
			// Show allowed tools
			if ( isset( $server['allowed_tools'] ) && is_array( $server['allowed_tools'] ) ) {
				WP_CLI::line( "Allowed tools: " . implode( ', ', $server['allowed_tools'] ) );
			}
			
			// Show approval requirement
			if ( isset( $server['require_approval'] ) ) {
				WP_CLI::line( "Approval: {$server['require_approval']}" );
			}
			
			WP_CLI::line( '---' );
		}
	}

	/**
	 * Test the OpenAI connection
	 *
	 * ## EXAMPLES
	 *
	 *     wp mcp test
	 *
	 * @param array<string> $args Positional arguments
	 * @param array<string, mixed> $assoc_args Associative arguments
	 */
	public function test( array $args, array $assoc_args ): void {
		if ( ! $this->client->is_configured() ) {
			WP_CLI::error( 'OpenAI API key not configured. Use: wp mcp config set openai_api_key <your-key>' );
			return;
		}

		WP_CLI::line( 'Testing OpenAI connection...' );

		try {
			$openai_client = $this->client->get_openai_client();
			if ( ! $openai_client ) {
				WP_CLI::error( 'OpenAI client not initialized.' );
				return;
			}

			$result = $openai_client->test_connection();

			if ( $result['success'] ) {
				WP_CLI::success( $result['message'] );
				if ( isset( $result['response'] ) ) {
					WP_CLI::line( 'Test response: ' . $result['response'] );
				}
			} else {
				WP_CLI::error( $result['message'] );
			}

		} catch ( Exception $e ) {
			WP_CLI::error( 'Test failed: ' . $e->getMessage() );
		}
	}

	/**
	 * List available MCP tools
	 *
	 * [--servers=<servers>]
	 * : Comma-separated list of MCP server names to query
	 *
	 * ## EXAMPLES
	 *
	 *     # List tools from all configured servers
	 *     wp mcp tools
	 *
	 *     # List tools from specific servers
	 *     wp mcp tools --servers=wordpress-server
	 *
	 * @param array<string> $args Positional arguments
	 * @param array<string, mixed> $assoc_args Associative arguments
	 */
	public function tools( array $args, array $assoc_args ): void {
		if ( ! $this->client->is_configured() ) {
			WP_CLI::error( 'OpenAI API key not configured. Use: wp mcp config set openai_api_key <your-key>' );
			return;
		}

		$servers_param = $assoc_args['servers'] ?? '';

		// Get MCP servers
		$mcp_servers = [];
		if ( ! empty( $servers_param ) ) {
			$server_names = array_map( 'trim', explode( ',', $servers_param ) );
			$all_servers = $this->settings->get_mcp_servers();
			
			foreach ( $server_names as $server_name ) {
				$server = array_filter( $all_servers, function( $s ) use ( $server_name ) {
					return $s['name'] === $server_name;
				});
				
				if ( ! empty( $server ) ) {
					$mcp_servers[] = array_values( $server )[0];
				} else {
					WP_CLI::warning( "MCP server '{$server_name}' not found." );
				}
			}
		} else {
			$mcp_servers = $this->settings->get_mcp_servers();
		}

		if ( empty( $mcp_servers ) ) {
			WP_CLI::warning( 'No MCP servers configured. Use: wp mcp servers add <name> <url>' );
			return;
		}

		WP_CLI::line( 'Querying available MCP tools...' );

		try {
			$openai_client = $this->client->get_openai_client();
			if ( ! $openai_client ) {
				WP_CLI::error( 'OpenAI client not initialized.' );
				return;
			}

			$result = $openai_client->list_mcp_tools( $mcp_servers );

			if ( $result && $result['success'] ) {
				$tools = $result['tools'];
				
				if ( empty( $tools ) ) {
					WP_CLI::line( 'No tools found or unable to parse tools from response.' );
					if ( isset( $result['response']['choices'][0]['message']['content'] ) ) {
						WP_CLI::line( 'Raw response:' );
						WP_CLI::line( $result['response']['choices'][0]['message']['content'] );
					}
				} else {
					WP_CLI::line( '' );
					WP_CLI::line( 'Available MCP tools:' );
					WP_CLI::line( '===================' );
					
					foreach ( $tools as $tool ) {
						WP_CLI::line( "Name: " . ( $tool['name'] ?? 'Unknown' ) );
						WP_CLI::line( "Description: " . ( $tool['description'] ?? 'No description' ) );
						WP_CLI::line( '---' );
					}
				}
			} else {
				WP_CLI::error( $result['error'] ?? 'Failed to list MCP tools' );
			}

		} catch ( Exception $e ) {
			WP_CLI::error( 'Error: ' . $e->getMessage() );
		}
	}
} 