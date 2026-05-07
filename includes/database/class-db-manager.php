<?php
/**
 * Database manager for CRUD and encryption.
 *
 * @package OSQ
 */

namespace OSQ\Database;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DbManager
 * 
 * Abstraction layer for database interactions and sensitive data encryption.
 */
class DbManager {

	/**
	 * Encrypts sensitive health data.
	 *
	 * @param mixed $data Data to encrypt (array or string).
	 * @return string|false Encrypted string or false on failure.
	 */
	public function encrypt_data( $data ) {
		return ( new \OSQ\Security\Encryption() )->encrypt( $data );
	}

	/**
	 * Decrypts sensitive health data.
	 *
	 * @param string $encrypted_string Data to decrypt.
	 * @param bool   $as_array Whether to return as an associative array.
	 * @return mixed Decrypted data or false on failure.
	 */
	public function decrypt_data( $encrypted_string, $as_array = true ) {
		return ( new \OSQ\Security\Encryption() )->decrypt( $encrypted_string, $as_array );
	}

	/*
	|----------------------------------------------------------------------
	| Employee CRUD
	|----------------------------------------------------------------------
	*/

	/**
	 * Get employee by their unique number.
	 *
	 * @param string $number Employee number.
	 * @return object|null
	 */
	public function get_employee_by_number( $number ) {
		global $wpdb;
		$table = $wpdb->prefix . Schema::EMPLOYEES;
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE employee_number = %s", $number )
		);
	}

	/**
	 * Get employee by WordPress user ID.
	 *
	 * @param int $user_id
	 * @return object|null
	 */
	public function get_employee_by_user_id( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . Schema::EMPLOYEES;
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE wp_user_id = %d", $user_id )
		);
	}

	/**
	 * Get employee by user ID, auto-creating a temporary record for admin/officer users.
	 *
	 * This allows administrators and officers to test the questionnaire
	 * without requiring a CSV import.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return object|null
	 */
	public function get_or_create_employee_for_user( $user_id ) {
		$employee = $this->get_employee_by_user_id( $user_id );
		if ( $employee ) {
			return $employee;
		}

		// Only auto-create for admin/officer users who can access the questionnaire for testing.
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return null;
		}

		$allowed_roles = array( 'administrator', 'osq_general_admin', 'osq_implementation_officer' );
		$has_allowed   = false;
		foreach ( $allowed_roles as $role ) {
			if ( in_array( $role, $user->roles, true ) ) {
				$has_allowed = true;
				break;
			}
		}

		if ( ! $has_allowed ) {
			return null;
		}

		// Auto-create a temporary employee record.
		global $wpdb;
		$table = $wpdb->prefix . Schema::EMPLOYEES;
		$employee_number = 'ADMIN-' . $user_id;

		$wpdb->insert( $table, array(
			'wp_user_id'      => $user_id,
			'employee_number' => $employee_number,
			'name'            => $user->display_name ?: $user->user_login,
			'email'           => $user->user_email,
			'created_at'      => current_time( 'mysql' ),
			'updated_at'      => current_time( 'mysql' ),
		) );

		if ( $wpdb->insert_id ) {
			return $this->get_employee_by_user_id( $user_id );
		}

		return null;
	}

	/*
	|----------------------------------------------------------------------
	| Response CRUD
	|----------------------------------------------------------------------
	*/

	/**
	 * Save or update a response.
	 *
	 * @param int   $employee_id
	 * @param array $data Questionnaire data (will be encrypted).
	 * @param bool  $is_complete
	 * @param array $results Optional scoring results.
	 * @return int|false Response ID or false on failure.
	 */
	public function save_response( $employee_id, $data, $is_complete = false, $results = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . Schema::RESPONSES;

		$encrypted = $this->encrypt_data( $data );
		if ( false === $encrypted ) {
			return false;
		}

		$response = $this->get_response_by_employee( $employee_id );

		$values = array(
			'employee_id'   => $employee_id,
			'response_data' => $encrypted,
			'is_complete'   => $is_complete ? 1 : 0,
			'completed_at'  => $is_complete ? current_time( 'mysql' ) : null,
			'updated_at'    => current_time( 'mysql' ),
		);

		// Add scoring results if provided.
		if ( ! empty( $results ) ) {
			$values['method1_result']         = maybe_serialize( $results['method1_result'] ?? null );
			$values['method2_result']         = maybe_serialize( $results['method2_result'] ?? null );
			$values['is_high_stress_method1'] = isset( $results['is_high_stress_method1'] ) ? ( $results['is_high_stress_method1'] ? 1 : 0 ) : null;
			$values['is_high_stress_method2'] = isset( $results['is_high_stress_method2'] ) ? ( $results['is_high_stress_method2'] ? 1 : 0 ) : null;
		}

		if ( $response ) {
			$wpdb->update( $table, $values, array( 'response_id' => $response->response_id ) );
			return $response->response_id;
		} else {
			$values['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $values );
			return $wpdb->insert_id;
		}
	}

	/**
	 * Get response for an employee.
	 *
	 * @param int $employee_id
	 * @return object|null
	 */
	public function get_response_by_employee( $employee_id ) {
		global $wpdb;
		$table = $wpdb->prefix . Schema::RESPONSES;
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE employee_id = %d", $employee_id )
		);
	}

	/*
	|----------------------------------------------------------------------
	| Session Management
	|----------------------------------------------------------------------
	*/

	/**
	 * Create a new secure session.
	 *
	 * @param int $employee_id
	 * @param int $expiry_minutes
	 * @return string|false Session token or false.
	 */
	public function create_session( $employee_id, $expiry_minutes = 30 ) {
		global $wpdb;
		$table = $wpdb->prefix . Schema::SESSIONS;

		$token  = bin2hex( openssl_random_pseudo_bytes( 32 ) );
		$expiry = gmdate( 'Y-m-d H:i:s', time() + ( $expiry_minutes * 60 ) );

		$result = $wpdb->insert( $table, array(
			'employee_id'      => $employee_id,
			'session_token'    => $token,
			'expires_at'       => $expiry,
			'last_accessed_at' => current_time( 'mysql' ),
		) );

		return $result ? $token : false;
	}
}
