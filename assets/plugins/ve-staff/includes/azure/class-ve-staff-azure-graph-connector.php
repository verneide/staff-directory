<?php

/** Microsoft Graph transport for the Azure staff add-on. */
final class Ve_Staff_Azure_Graph_Connector {
	private string $tenant_id;
	private string $client_id;
	private string $client_secret;

	public function __construct( string $tenant_id, string $client_id, string $client_secret ) {
		$this->tenant_id     = $tenant_id;
		$this->client_id     = $client_id;
		$this->client_secret = $client_secret;
	}

	/** @return array<string, mixed> */
	public function get_json( string $url ): array {
		return $this->request_json( 'GET', $url, array() );
	}

	/** @param array<string, mixed> $body @return array<string, mixed> */
	public function patch_json( string $url, array $body ): array {
		return $this->request_json( 'PATCH', $url, $body );
	}

	public function put_binary( string $url, string $content_type, string $body ): void {
		$token    = $this->access_token();
		$response = wp_remote_request( $url, array( 'method' => 'PUT', 'timeout' => 30, 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => $content_type ), 'body' => $body ) );
		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( 'Microsoft Graph photo upload failed: ' . $response->get_error_message() );
		}
		$status = wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			throw new RuntimeException( sprintf( 'Microsoft Graph photo upload failed: status=%d body=%s', $status, wp_remote_retrieve_body( $response ) ) );
		}
	}

	/** @return array<int, string> */
	public function test_connection(): array {
		$token = $this->access_token();
		$parts = explode( '.', $token );
		if ( 3 !== count( $parts ) ) {
			throw new RuntimeException( 'Microsoft returned an access token in an unexpected format.' );
		}
		$payload = strtr( $parts[1], '-_', '+/' );
		$payload .= str_repeat( '=', ( 4 - strlen( $payload ) % 4 ) % 4 );
		$decoded = json_decode( (string) base64_decode( $payload, true ), true, 512, JSON_THROW_ON_ERROR );
		$roles      = is_array( $decoded ) && is_array( $decoded['roles'] ?? null ) ? array_map( 'strval', $decoded['roles'] ) : array();
		$recognized = array_values( array_intersect( array( 'User.Read.All', 'User.ReadWrite.All', 'ProfilePhoto.ReadWrite.All' ), $roles ) );
		if ( in_array( 'User.Read.All', $recognized, true ) || in_array( 'User.ReadWrite.All', $recognized, true ) ) {
			$this->get_json( 'https://graph.microsoft.com/v1.0/users?$top=1&$select=id' );
		}
		return $recognized;
	}

	/** @return array<string, mixed> */
	private function request_json( string $method, string $url, array $body ): array {
		$token = $this->access_token();
		$args  = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ),
		);
		if ( 'PATCH' === $method ) {
			$args['body'] = wp_json_encode( $body, JSON_THROW_ON_ERROR );
		}
		return $this->request_with_retries( $url, $args );
	}

	private function access_token(): string {
		$cached = get_transient( 've_staff_azure_access_token' );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}
		$url      = 'https://login.microsoftonline.com/' . rawurlencode( $this->tenant_id ) . '/oauth2/v2.0/token';
		$response = $this->request_with_retries(
			$url,
			array(
				'method'  => 'POST',
				'timeout' => 30,
				'body'    => array(
					'client_id' => $this->client_id, 'client_secret' => $this->client_secret,
					'scope' => 'https://graph.microsoft.com/.default', 'grant_type' => 'client_credentials',
				),
			)
		);
		if ( empty( $response['access_token'] ) ) {
			throw new RuntimeException( 'Microsoft token response did not contain access_token.' );
		}
		set_transient( 've_staff_azure_access_token', (string) $response['access_token'], max( 60, (int) $response['expires_in'] - 120 ) );
		return (string) $response['access_token'];
	}

	/** @param array<string, mixed> $args @return array<string, mixed> */
	private function request_with_retries( string $url, array $args ): array {
		$last_error = '';
		for ( $attempt = 1; $attempt <= 3; $attempt++ ) {
			$response = wp_remote_request( $url, $args );
			if ( ! is_wp_error( $response ) ) {
				$status = wp_remote_retrieve_response_code( $response );
				$raw    = wp_remote_retrieve_body( $response );
				if ( $status >= 200 && $status < 300 ) {
					if ( '' === $raw ) {
						return array();
					}
					$decoded = json_decode( $raw, true, 512, JSON_THROW_ON_ERROR );
					return is_array( $decoded ) ? $decoded : array();
				}
				$last_error = sprintf( 'status=%d body=%s', $status, $raw );
			} else {
				$last_error = $response->get_error_message();
			}
			ve_staff_azure_log( 'warning', 'graph_retry', array( 'attempt' => $attempt, 'url' => $url, 'error' => $last_error ) );
			if ( $attempt < 3 ) {
				sleep( $attempt );
			}
		}
		throw new RuntimeException( sprintf( 'Microsoft Graph request failed after 3 attempts: method=%s url=%s error=%s', (string) $args['method'], $url, $last_error ) );
	}
}
