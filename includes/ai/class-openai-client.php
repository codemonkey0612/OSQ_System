<?php
/**
 * OpenAI API client.
 *
 * @package OSQ
 */

namespace OSQ\AI;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OpenaiClient
 *
 * Thin wrapper around the OpenAI Chat Completions API.
 * All communication goes through wp_remote_post() so WordPress HTTP filters apply.
 */
class OpenaiClient {

	const API_URL = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Default request timeout in seconds.
	 */
	const TIMEOUT = 60;

	/**
	 * OpenAI API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Model to use.
	 *
	 * @var string
	 */
	private $model;

	/**
	 * Constructor.
	 *
	 * @param string $api_key OpenAI API key. Falls back to plugin settings if empty.
	 * @param string $model   Model ID. Falls back to plugin settings if empty.
	 */
	public function __construct( $api_key = '', $model = '' ) {
		$settings      = get_option( 'osq_settings', array() );
		$this->api_key = $api_key ?: ( $settings['openai_api_key'] ?? '' );
		$this->model   = $model   ?: ( $settings['openai_model']   ?? 'gpt-4o' );
	}

	/**
	 * Send a chat completion request.
	 *
	 * @param string $system_prompt The system-role prompt.
	 * @param string $user_message  The user-role message.
	 * @param int    $max_tokens    Maximum tokens for the response.
	 * @return string|\WP_Error Generated text or WP_Error on failure.
	 */
	public function complete( $system_prompt, $user_message, $max_tokens = 600 ) {
		if ( empty( $this->api_key ) ) {
			return new \WP_Error( 'osq_openai_no_key', __( 'OpenAI API key is not configured.', 'osq-stress-check' ) );
		}

		$body = wp_json_encode( array(
			'model'      => $this->model,
			'messages'   => array(
				array( 'role' => 'system', 'content' => $system_prompt ),
				array( 'role' => 'user',   'content' => $user_message ),
			),
			'max_tokens'  => $max_tokens,
			'temperature' => 0.8,
		) );

		$response = wp_remote_post( self::API_URL, array(
			'timeout' => self::TIMEOUT,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => $body,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( 200 !== (int) $code ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : "HTTP {$code}";
			return new \WP_Error( 'osq_openai_api_error', $message );
		}

		$text = $data['choices'][0]['message']['content'] ?? '';

		if ( empty( $text ) ) {
			return new \WP_Error( 'osq_openai_empty', __( 'OpenAI returned an empty response.', 'osq-stress-check' ) );
		}

		return trim( $text );
	}

	/**
	 * Verify that the API key is valid by sending a minimal test request.
	 *
	 * @return true|\WP_Error
	 */
	public function test_connection() {
		$result = $this->complete(
			'You are a helpful assistant.',
			'Reply with exactly: OK',
			10
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}
}
