<?php
/**
 * Data encryption service.
 *
 * @package OSQ
 */

namespace OSQ\Security;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Encryption
 *
 * Handles AES-256-CBC encryption for sensitive health data using WordPress salts.
 */
class Encryption {

	/**
	 * Encryption method.
	 */
	const ENCRYPTION_METHOD = 'AES-256-CBC';

	/**
	 * Encrypts sensitive health data.
	 *
	 * @param mixed $data Data to encrypt (array or string).
	 * @return string|false Encrypted string or false on failure.
	 */
	public function encrypt( $data ) {
		$plaintext = is_array( $data ) ? wp_json_encode( $data ) : $data;
		$key       = $this->get_encryption_key();

		if ( ! $key ) {
			return false;
		}

		$iv_length = openssl_cipher_iv_length( self::ENCRYPTION_METHOD );
		$iv        = openssl_random_pseudo_bytes( $iv_length );
		$encrypted = openssl_encrypt( $plaintext, self::ENCRYPTION_METHOD, $key, 0, $iv );

		if ( false === $encrypted ) {
			return false;
		}

		// Prepend IV for decryption.
		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypts sensitive health data.
	 *
	 * @param string $encrypted_string Data to decrypt.
	 * @param bool   $as_array Whether to return as an associative array.
	 * @return mixed Decrypted data or false on failure.
	 */
	public function decrypt( $encrypted_string, $as_array = true ) {
		$key = $this->get_encryption_key();
		if ( ! $key ) {
			return false;
		}

		$decoded   = base64_decode( $encrypted_string );
		$iv_length = openssl_cipher_iv_length( self::ENCRYPTION_METHOD );
		
		if ( strlen( $decoded ) < $iv_length ) {
			return false;
		}

		$iv        = substr( $decoded, 0, $iv_length );
		$ciphertext = substr( $decoded, $iv_length );

		$decrypted = openssl_decrypt( $ciphertext, self::ENCRYPTION_METHOD, $key, 0, $iv );

		if ( false === $decrypted ) {
			return $as_array ? array() : false;
		}

		if ( ! $as_array ) {
			return $decrypted;
		}

		$decoded = json_decode( $decrypted, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Derives a consistent encryption key from WordPress salts.
	 *
	 * @return string Binary encryption key.
	 */
	private function get_encryption_key() {
		if ( ! defined( 'AUTH_SALT' ) || ! defined( 'SECURE_AUTH_SALT' ) ) {
			return false;
		}
		// Use hash_hkdf for better key derivation if available (PHP 7.1+).
		return hash_hkdf( 'sha256', AUTH_SALT . SECURE_AUTH_SALT, 32, 'osq-stress-check-encryption' );
	}
}
