<?php
/**
 * JWT Token Manager for WordPress MCP Client plugin
 *
 * @package WordPressMcpClient\Core
 */

declare(strict_types=1);

namespace WordPressMcpClient\Core;

/**
 * JWT Token Manager class for automatic token handling
 */
class JwtTokenManager {
	/**
	 * Option name for storing current JWT token
	 */
	private const TOKEN_OPTION = 'wordpress_mcp_client_jwt_token';

	/**
	 * Option name for storing token expiration
	 */
	private const TOKEN_EXPIRY_OPTION = 'wordpress_mcp_client_jwt_expiry';

	/**
	 * Token refresh buffer time (refresh token 5 minutes before expiry)
	 */
	private const REFRESH_BUFFER = 300; // 5 minutes

	/**
	 * Token duration (1 hour)
	 */
	private const TOKEN_DURATION = 3600; // 1 hour

	/**
	 * Get the current valid JWT token
	 *
	 * @return string|null
	 */
	public function get_token(): ?string {
		$token  = get_option( self::TOKEN_OPTION, '' );
		$expiry = (int) get_option( self::TOKEN_EXPIRY_OPTION, 0 );

		// Check if token exists and is not expired
		if ( empty( $token ) || $this->is_token_expired( $expiry ) ) {
			return $this->refresh_token();
		}

		// Check if token needs refresh soon
		if ( $this->should_refresh_token( $expiry ) ) {
			$new_token = $this->refresh_token();
			return $new_token ?: $token; // Return new token or fallback to current if refresh fails
		}

		return $token;
	}

	/**
	 * Force refresh the JWT token
	 *
	 * @return string|null
	 */
	public function refresh_token(): ?string {
		try {
 			// Check if user is logged in
			if ( ! is_user_logged_in() ) {
				error_log( 'MCP Client: Cannot generate JWT token - user not logged in' );
				return null;
			}

			$current_user = wp_get_current_user();
			error_log( 'MCP Client: Attempting to generate JWT token for user: ' . $current_user->user_login );

			// Try direct method first if WordPress MCP plugin is available
			$token = $this->generate_token_direct();
			if ( $token ) {
				return $token;
			}

			// Fallback to HTTP request method
			$response = wp_remote_post(
				home_url( '/wp-json/jwt-auth/v1/token' ),
				array(
					'headers' => array(
						'Content-Type' => 'application/json',
						'Cookie'       => $this->get_auth_cookies(),
					),
					'body'    => wp_json_encode(
						array(
							'expires_in' => self::TOKEN_DURATION,
						)
					),
					'timeout' => 30,
				)
			);

			if ( is_wp_error( $response ) ) {
				error_log( 'MCP Client: Failed to refresh JWT token - ' . $response->get_error_message() );
				return null;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			$body          = wp_remote_retrieve_body( $response );

			error_log( 'MCP Client: JWT token response code: ' . $response_code );
			error_log( 'MCP Client: JWT token response body: ' . $body );

			if ( $response_code !== 200 ) {
				error_log( 'MCP Client: JWT token request failed with status: ' . $response_code );
				return null;
			}

			$data = json_decode( $body, true );

			if ( ! isset( $data['token'] ) ) {
				error_log( 'MCP Client: Invalid token response - missing token field' );
				return null;
			}

			// Store the new token and expiry
			$expiry_time = time() + $data['expires_in'];
			update_option( self::TOKEN_OPTION, $data['token'] );
			update_option( self::TOKEN_EXPIRY_OPTION, $expiry_time );

			error_log( 'MCP Client: JWT token generated successfully, expires at: ' . gmdate( 'Y-m-d H:i:s', $expiry_time ) );

			return $data['token'];

		} catch ( \Exception $e ) {
			error_log( 'MCP Client: Exception during token refresh - ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Generate JWT token directly using WordPress MCP plugin classes
	 *
	 * @return string|null
	 */
	private function generate_token_direct(): ?string {
		try {
			// Check if WordPress MCP plugin JWT auth class is available
			if ( ! class_exists( 'Automattic\WordpressMcp\Auth\JwtAuth' ) ) {
				error_log( 'MCP Client: WordPress MCP JwtAuth class not available' );
				return null;
			}

			$current_user = wp_get_current_user();
			if ( ! $current_user || ! $current_user->ID ) {
				error_log( 'MCP Client: No valid user for direct JWT generation' );
				return null;
			}

			// Use reflection to access the private generate_token method
			$jwt_auth   = new \Automattic\WordpressMcp\Auth\JwtAuth();
			$reflection = new \ReflectionClass( $jwt_auth );
			$method     = $reflection->getMethod( 'generate_token' );
			$method->setAccessible( true );

			$token_data = $method->invoke( $jwt_auth, $current_user->ID, self::TOKEN_DURATION );

			if ( isset( $token_data['token'] ) ) {
				// Store the new token and expiry
				$expiry_time = time() + $token_data['expires_in'];
				update_option( self::TOKEN_OPTION, $token_data['token'] );
				update_option( self::TOKEN_EXPIRY_OPTION, $expiry_time );

				error_log( 'MCP Client: JWT token generated directly, expires at: ' . gmdate( 'Y-m-d H:i:s', $expiry_time ) );

				return $token_data['token'];
			}
		} catch ( \Exception $e ) {
			error_log( 'MCP Client: Failed to generate token directly: ' . $e->getMessage() );
		}

		return null;
	}

	/**
	 * Check if token is expired
	 *
	 * @param int $expiry Token expiry timestamp
	 * @return bool
	 */
	private function is_token_expired( int $expiry ): bool {
		return $expiry <= time();
	}

	/**
	 * Check if token should be refreshed soon
	 *
	 * @param int $expiry Token expiry timestamp
	 * @return bool
	 */
	private function should_refresh_token( int $expiry ): bool {
		return $expiry <= ( time() + self::REFRESH_BUFFER );
	}

	/**
	 * Get authentication cookies for the current user
	 *
	 * @return string
	 */
	private function get_auth_cookies(): string {
		$cookies = array();

		// In AJAX context, get cookies from headers if available
		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			if ( isset( $headers['Cookie'] ) ) {
				return $headers['Cookie'];
			}
		}

		// Fallback: get cookies from $_COOKIE superglobal
		foreach ( $_COOKIE as $name => $value ) {
			if ( strpos( $name, 'wordpress_' ) === 0 || strpos( $name, 'wp_' ) === 0 ) {
				$cookies[] = $name . '=' . urlencode( $value );
			}
		}

		$cookie_string = implode( '; ', $cookies );
		error_log( 'MCP Client: Using cookies for authentication: ' . substr( $cookie_string, 0, 100 ) . '...' );

		return $cookie_string;
	}

	/**
	 * Get Authorization header with Bearer token
	 *
	 * @return array
	 */
	public function get_auth_headers(): array {
		$token = $this->get_token();

		if ( ! $token ) {
			return array();
		}

		return array(
			'Authorization' => 'Bearer ' . $token,
		);
	}

	/**
	 * Clear stored token (useful for logout)
	 *
	 * @return void
	 */
	public function clear_token(): void {
		delete_option( self::TOKEN_OPTION );
		delete_option( self::TOKEN_EXPIRY_OPTION );
	}

	/**
	 * Check if we have a valid token
	 *
	 * @return bool
	 */
	public function has_valid_token(): bool {
		return ! empty( $this->get_token() );
	}

	/**
	 * Get token info for debugging
	 *
	 * @return array
	 */
	public function get_token_info(): array {
		$token  = get_option( self::TOKEN_OPTION, '' );
		$expiry = (int) get_option( self::TOKEN_EXPIRY_OPTION, 0 );

		return array(
			'has_token'      => ! empty( $token ),
			'expires_at'     => $expiry,
			'expires_in'     => max( 0, $expiry - time() ),
			'is_expired'     => $this->is_token_expired( $expiry ),
			'should_refresh' => $this->should_refresh_token( $expiry ),
		);
	}
}
