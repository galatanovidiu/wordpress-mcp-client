<?php
/**
 * OpenAI MCP Client class for WordPress MCP Client plugin
 *
 * @package WordPressMcpClient\Core
 */

declare(strict_types=1);

namespace WordPressMcpClient\Core;

use Exception;

/**
 * OpenAI MCP Client class for handling OpenAI API with MCP server integration
 */
class OpenAiMcpClient {
	/**
	 * Custom OpenAI client instance
	 *
	 * @var CustomOpenAiClient|null
	 */
	private ?CustomOpenAiClient $openai_client = null;

	/**
	 * Main client instance
	 *
	 * @var Client
	 */
	private Client $client;

	/**
	 * JWT Token Manager instance
	 *
	 * @var JwtTokenManager
	 */
	private JwtTokenManager $jwt_manager;

	/**
	 * Constructor
	 *
	 * @param Client $client The main client instance
	 */
	public function __construct( Client $client ) {
		$this->client = $client;
		$this->jwt_manager = new JwtTokenManager();
		$this->initialize_openai_client();
		
		// Set this instance in the main client
		$client->set_openai_client( $this );
	}

	/**
	 * Initialize the OpenAI client
	 *
	 * @return void
	 */
	private function initialize_openai_client(): void {
		$api_key = $this->client->get_setting( 'openai_api_key' );
		
		if ( empty( $api_key ) ) {
			return;
		}

		try {
			$this->openai_client = new CustomOpenAiClient( $api_key );
		} catch ( Exception $e ) {
			error_log( 'WordPress MCP Client: Failed to initialize OpenAI client: ' . $e->getMessage() );
		}
	}

	/**
	 * Send a message to OpenAI with MCP server integration
	 *
	 * @param string $message The message to send
	 * @param array<string, mixed> $mcp_servers List of MCP servers to connect to
	 * @param array<string, mixed> $options Additional options
	 * @return array<string, mixed>|null
	 */
	public function send_message( string $message, array $mcp_servers = [], array $options = [] ): ?array {
		if ( ! $this->openai_client ) {
			throw new Exception( 'OpenAI client not initialized. Please check your API key.' );
		}

		$model = $options['model'] ?? $this->client->get_setting( 'model', 'gpt-4.1' );
		$conversation_history = $options['conversation_history'] ?? [];
		
		try {
			// Prepare the MCP server configurations for OpenAI
			$mcp_config = $this->prepare_mcp_config( $mcp_servers );
			
			// Build messages array with conversation history
			$messages = [];
			
			// Add conversation history first
			if ( ! empty( $conversation_history ) && is_array( $conversation_history ) ) {
				foreach ( $conversation_history as $msg ) {
					if ( isset( $msg['role'] ) && isset( $msg['content'] ) && 
						 in_array( $msg['role'], ['user', 'assistant'], true ) ) {
						$messages[] = [
							'role' => $msg['role'],
							'content' => $msg['content'],
						];
					}
				}
			}
			
			// If no MCP servers are configured, use regular Chat Completions API
			if ( empty( $mcp_config ) ) {
				// Add current message if not already in history
				if ( empty( $messages ) || end( $messages )['content'] !== $message ) {
					$messages[] = [
						'role' => 'user',
						'content' => $message,
					];
				}

				$response = $this->openai_client->createChatCompletion([
					'model' => $model,
					'messages' => $messages,
				]);

				return [
					'success' => true,
					'response' => $response,
					'message' => $response['choices'][0]['message']['content'] ?? '',
					'usage' => $response['usage'] ?? null,
				];
			}

			// Use Responses API for MCP integration
			// Build input with conversation history and system message
			$input = [];

			// Add system message for MCP context first
			$input[] = [
				'role' => 'system',
				'content' => 'You are an AI assistant with access to WordPress management tools through Model Context Protocol (MCP) servers. You can help with content management, site analysis, user management, WooCommerce operations, and general WordPress tasks. Use the available tools to assist users with their WordPress site needs.',
			];

			// Add conversation history
			foreach ( $messages as $msg ) {
				$input[] = $msg;
			}

			// Add current message if not already in history
			if ( empty( $messages ) || end( $messages )['content'] !== $message ) {
				$input[] = [
					'role' => 'user',
					'content' => $message,
				];
			}

			$request_params = [
				'model' => $model,
				'input' => $input,
				'tools' => $mcp_config,
			];

			// Use the Responses API for MCP support
			$response_array = $this->openai_client->createResponse( $request_params );

			// Extract the message content using our custom client method
			$message_content = $this->openai_client->extractTextContent( $response_array );

			return [
				'success' => true,
				'response' => $response_array,
				'message' => $message_content,
				'usage' => $response_array['usage'] ?? null,
			];

		} catch ( Exception $e ) {
			error_log( 'WordPress MCP Client: Error sending message: ' . $e->getMessage() );
			
			return [
				'success' => false,
				'error' => $e->getMessage(),
				'message' => '',
			];
		}
	}

	/**
	 * Prepare MCP server configurations for OpenAI API
	 *
	 * @param array<string, mixed> $mcp_servers List of MCP servers
	 * @return array<string, mixed>
	 */
	private function prepare_mcp_config( array $mcp_servers ): array {
		$config = [];
		
		// Always use the internal WordPress MCP server with JWT authentication
		$auth_headers = $this->jwt_manager->get_auth_headers();
		
		$internal_server_config = [
			'type' => 'mcp',
			'server_label' => 'wordpress-internal',
			'server_url' => home_url( '/wp-json/wp/v2/wpmcp/streamable' ),
			'require_approval' => 'never',
		];

		// Add JWT authentication headers if available
		if ( ! empty( $auth_headers ) ) {
			$internal_server_config['headers'] = $auth_headers;
		}

		$config[] = $internal_server_config;

		return $config;
	}

	/**
	 * List available MCP tools from connected servers
	 *
	 * @param array<string, mixed> $mcp_servers List of MCP servers
	 * @return array<string, mixed>|null
	 */
	public function list_mcp_tools( array $mcp_servers = [] ): ?array {
		if ( ! $this->openai_client ) {
			throw new Exception( 'OpenAI client not initialized. Please check your API key.' );
		}

		try {
			$mcp_config = $this->prepare_mcp_config( $mcp_servers );
			
			if ( empty( $mcp_config ) ) {
				return [
					'success' => true,
					'tools' => [],
					'message' => 'No MCP servers configured.',
				];
			}

			// Send a request to list available tools using Responses API
			$system_message = 'List all available MCP tools and their descriptions. Respond with a JSON array of tools.';
			
			$input = [
				[
					'role' => 'system',
					'content' => $system_message,
				],
				[
					'role' => 'user',
					'content' => 'What MCP tools are available?',
				],
			];

			$request_params = [
				'model' => $this->client->get_setting( 'model', 'gpt-4.1' ),
				'input' => $input,
			];

			// Add MCP tools configuration
			if ( ! empty( $mcp_config ) ) {
				$request_params['tools'] = $mcp_config;
			}
			
			$response_array = $this->openai_client->createResponse( $request_params );

			// Extract message content and MCP tools
			$message_content = $this->openai_client->extractTextContent( $response_array );
			$mcp_tools = $this->openai_client->extractMcpTools( $response_array );

			return [
				'success' => true,
				'response' => $response_array,
				'tools' => ! empty( $mcp_tools ) ? $mcp_tools : $this->extract_tools_from_response( $message_content ),
			];

		} catch ( Exception $e ) {
			error_log( 'WordPress MCP Client: Error listing MCP tools: ' . $e->getMessage() );
			
			return [
				'success' => false,
				'error' => $e->getMessage(),
				'tools' => [],
			];
		}
	}

	/**
	 * Extract tools information from AI response
	 *
	 * @param string $response
	 * @return array<string, mixed>
	 */
	private function extract_tools_from_response( string $response ): array {
		// Try to extract JSON from the response
		$tools = [];
		
		// Look for JSON content in the response
		if ( preg_match( '/\[.*\]/s', $response, $matches ) ) {
			$json_data = json_decode( $matches[0], true );
			
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $json_data ) ) {
				$tools = $json_data;
			}
		}

		return $tools;
	}

	/**
	 * Test the connection to OpenAI
	 *
	 * @return array<string, mixed>
	 */
	public function test_connection(): array {
		if ( ! $this->openai_client ) {
			return [
				'success' => false,
				'message' => 'OpenAI client not initialized. Please check your API key.',
			];
		}

		try {
			return $this->openai_client->testConnection();
		} catch ( Exception $e ) {
			return [
				'success' => false,
				'message' => 'Connection failed: ' . $e->getMessage(),
			];
		}
	}

	/**
	 * Get the custom OpenAI client instance
	 *
	 * @return CustomOpenAiClient|null
	 */
	public function get_openai_client(): ?CustomOpenAiClient {
		return $this->openai_client;
	}
} 