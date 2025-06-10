<?php
/**
 * Custom OpenAI Client for WordPress MCP Client plugin
 *
 * @package WordPressMcpClient\Core
 */

declare(strict_types=1);

namespace WordPressMcpClient\Core;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use Exception;

/**
 * Custom OpenAI Client class with native MCP support
 */
class CustomOpenAiClient {

	/**
	 * OpenAI API base URL
	 */
	private const API_BASE_URL = 'https://api.openai.com/v1';

	/**
	 * HTTP client instance
	 *
	 * @var HttpClient
	 */
	private HttpClient $http_client;

	/**
	 * OpenAI API key
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Constructor
	 *
	 * @param string $api_key OpenAI API key
	 */
	public function __construct( string $api_key ) {
		$this->api_key     = $api_key;
		$this->http_client = new HttpClient(
			array(
				'base_uri' => self::API_BASE_URL . '/',  // Ensure trailing slash for proper URI resolution
				'timeout'  => 120,
				'headers'  => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
					'User-Agent'    => 'WordPress-MCP-Client/1.0',
				),
			)
		);
	}

	/**
	 * Create a chat completion
	 *
	 * @param array<string, mixed> $params Parameters for the chat completion
	 * @return array<string, mixed>
	 * @throws Exception If the request fails
	 */
	public function createChatCompletion( array $params ): array {
		try {
			$response = $this->http_client->post(
				'chat/completions',  // Remove leading slash since base_uri now has trailing slash
				array(
					'json' => $params,
				)
			);

			$body = $response->getBody()->getContents();
			$data = json_decode( $body, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				throw new Exception( 'Invalid JSON response from OpenAI API' );
			}

			return $data;

		} catch ( RequestException $e ) {
			$error_message = 'OpenAI API request failed: ' . $e->getMessage();

			if ( $e->hasResponse() ) {
				$response_body = $e->getResponse()->getBody()->getContents();
				$error_data    = json_decode( $response_body, true );

				if ( isset( $error_data['error']['message'] ) ) {
					$error_message = 'OpenAI API Error: ' . $error_data['error']['message'];
				}
			}

			throw new Exception( $error_message );
		}
	}

	/**
	 * Create a response using the Responses API (with MCP support)
	 *
	 * @param array<string, mixed> $params Parameters for the response creation
	 * @return array<string, mixed>
	 * @throws Exception If the request fails
	 */
	public function createResponse( array $params ): array {
		try {
			$response = $this->http_client->post(
				'responses',  // Remove leading slash since base_uri now has trailing slash
				array(
					'json'    => $params,
					'headers' => array(
						'OpenAI-Beta' => 'responses',  // Required header for Responses API
					),
				)
			);

			$body = $response->getBody()->getContents();
			$data = json_decode( $body, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				throw new Exception( 'Invalid JSON response from OpenAI Responses API' );
			}

			return $this->processResponseOutput( $data );

		} catch ( RequestException $e ) {
			$error_message = 'OpenAI Responses API request failed: ' . $e->getMessage();

			if ( $e->hasResponse() ) {
				$response_body = $e->getResponse()->getBody()->getContents();
				$error_data    = json_decode( $response_body, true );

				if ( isset( $error_data['error']['message'] ) ) {
					$error_message = 'OpenAI Responses API Error: ' . $error_data['error']['message'];
				}
			}

			throw new Exception( $error_message );
		}
	}

	/**
	 * Process the response output to handle MCP types natively
	 *
	 * @param array<string, mixed> $response_data The raw response data
	 * @return array<string, mixed> Processed response data
	 */
	private function processResponseOutput( array $response_data ): array {
		if ( ! isset( $response_data['output'] ) || ! is_array( $response_data['output'] ) ) {
			return $response_data;
		}

		$processed_output = array();

		foreach ( $response_data['output'] as $output_item ) {
			if ( ! is_array( $output_item ) || ! isset( $output_item['type'] ) ) {
				$processed_output[] = $output_item;
				continue;
			}

			$processed_item     = $this->processOutputItem( $output_item );
			$processed_output[] = $processed_item;
		}

		$response_data['output'] = $processed_output;
		return $response_data;
	}

	/**
	 * Process individual output items based on their type
	 *
	 * @param array<string, mixed> $item The output item
	 * @return array<string, mixed> Processed output item
	 */
	private function processOutputItem( array $item ): array {
		switch ( $item['type'] ) {
			case 'message':
				return $this->processMessageOutput( $item );

			case 'mcp_list_tools':
				return $this->processMcpListTools( $item );

			case 'mcp_approval_request':
				return $this->processMcpApprovalRequest( $item );

			case 'mcp_approval_response':
				return $this->processMcpApprovalResponse( $item );

			case 'mcp_call':
				return $this->processMcpCall( $item );

			case 'function_call':
			case 'web_search_call':
			case 'file_search_call':
			case 'computer_call':
			case 'reasoning':
			default:
				return $item;
		}
	}

	/**
	 * Process message output
	 *
	 * @param array<string, mixed> $item The message item
	 * @return array<string, mixed> Processed message item
	 */
	private function processMessageOutput( array $item ): array {
		// Ensure content is properly structured
		if ( isset( $item['content'] ) && is_array( $item['content'] ) ) {
			$processed_content = array();
			foreach ( $item['content'] as $content_item ) {
				if ( is_array( $content_item ) && isset( $content_item['type'] ) ) {
					$processed_content[] = $content_item;
				}
			}
			$item['content'] = $processed_content;
		}

		return $item;
	}

	/**
	 * Process MCP list tools output
	 *
	 * @param array<string, mixed> $item The MCP list tools item
	 * @return array<string, mixed> Processed MCP list tools item
	 */
	private function processMcpListTools( array $item ): array {
		// Ensure tools array is properly structured
		if ( isset( $item['tools'] ) && is_array( $item['tools'] ) ) {
			$processed_tools = array();
			foreach ( $item['tools'] as $tool ) {
				if ( is_array( $tool ) ) {
					// Validate tool structure
					$processed_tool    = array(
						'name'         => $tool['name'] ?? '',
						'description'  => $tool['description'] ?? '',
						'input_schema' => $tool['input_schema'] ?? array(),
						'annotations'  => $tool['annotations'] ?? null,
					);
					$processed_tools[] = $processed_tool;
				}
			}
			$item['tools'] = $processed_tools;
		}

		return $item;
	}

	/**
	 * Process MCP approval request output
	 *
	 * @param array<string, mixed> $item The MCP approval request item
	 * @return array<string, mixed> Processed MCP approval request item
	 */
	private function processMcpApprovalRequest( array $item ): array {
		// Ensure required fields are present
		$item['id']           = $item['id'] ?? '';
		$item['type']         = 'mcp_approval_request';
		$item['arguments']    = $item['arguments'] ?? '';
		$item['name']         = $item['name'] ?? '';
		$item['server_label'] = $item['server_label'] ?? '';

		return $item;
	}

	/**
	 * Process MCP approval response output
	 *
	 * @param array<string, mixed> $item The MCP approval response item
	 * @return array<string, mixed> Processed MCP approval response item
	 */
	private function processMcpApprovalResponse( array $item ): array {
		// Ensure required fields are present
		$item['type']                = 'mcp_approval_response';
		$item['approval_request_id'] = $item['approval_request_id'] ?? '';
		$item['approve']             = isset( $item['approve'] ) ? (bool) $item['approve'] : false;

		return $item;
	}

	/**
	 * Process MCP call output
	 *
	 * @param array<string, mixed> $item The MCP call item
	 * @return array<string, mixed> Processed MCP call item
	 */
	private function processMcpCall( array $item ): array {
		// Ensure required fields are present
		$item['id']                  = $item['id'] ?? '';
		$item['type']                = 'mcp_call';
		$item['approval_request_id'] = $item['approval_request_id'] ?? null;
		$item['arguments']           = $item['arguments'] ?? '';
		$item['error']               = $item['error'] ?? null;
		$item['name']                = $item['name'] ?? '';
		$item['output']              = $item['output'] ?? '';
		$item['server_label']        = $item['server_label'] ?? '';

		return $item;
	}

	/**
	 * Extract text content from processed response output
	 *
	 * @param array<string, mixed> $response_data The processed response data
	 * @return string Extracted text content
	 */
	public function extractTextContent( array $response_data ): string {
		$text_content = '';

		if ( ! isset( $response_data['output'] ) || ! is_array( $response_data['output'] ) ) {
			return $text_content;
		}

		foreach ( $response_data['output'] as $output_item ) {
			if ( ! is_array( $output_item ) || ! isset( $output_item['type'] ) ) {
				continue;
			}

			if ( $output_item['type'] === 'message' && isset( $output_item['content'] ) ) {
				foreach ( $output_item['content'] as $content_item ) {
					if ( isset( $content_item['type'] ) && $content_item['type'] === 'output_text' ) {
						$text_content .= $content_item['text'] ?? '';
					}
				}
			}
		}

		return $text_content;
	}

	/**
	 * Extract MCP tools from response output
	 *
	 * @param array<string, mixed> $response_data The processed response data
	 * @return array<string, mixed> Extracted MCP tools
	 */
	public function extractMcpTools( array $response_data ): array {
		$tools = array();

		if ( ! isset( $response_data['output'] ) || ! is_array( $response_data['output'] ) ) {
			return $tools;
		}

		foreach ( $response_data['output'] as $output_item ) {
			if ( ! is_array( $output_item ) || ! isset( $output_item['type'] ) ) {
				continue;
			}

			if ( $output_item['type'] === 'mcp_list_tools' && isset( $output_item['tools'] ) ) {
				$tools = array_merge( $tools, $output_item['tools'] );
			}
		}

		return $tools;
	}

	/**
	 * Test the connection to OpenAI API
	 *
	 * @return array<string, mixed> Test result
	 */
	public function testConnection(): array {
		try {
			$response = $this->createChatCompletion(
				array(
					'model'      => 'gpt-4.1',
					'messages'   => array(
						array(
							'role'    => 'user',
							'content' => 'Hello, this is a test message.',
						),
					),
					'max_tokens' => 10,
				)
			);

			$message = '';
			if ( isset( $response['choices'][0]['message']['content'] ) ) {
				$message = $response['choices'][0]['message']['content'];
			}

			return array(
				'success'  => true,
				'message'  => 'Connection successful!',
				'response' => $message,
			);

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => 'Connection failed: ' . $e->getMessage(),
			);
		}
	}
}
