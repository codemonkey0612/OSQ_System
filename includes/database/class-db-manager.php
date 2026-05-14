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
 * Phase 3a: all queries are auto-scoped to the current tenant via company_id.
 */
class DbManager {

	/**
	 * User meta key that stores which company a WP user belongs to.
	 *
	 * @var string
	 */
	const COMPANY_USER_META_KEY = 'osq_company_id';

	/**
	 * Per-request override of the active company. Used by wellanc super-admin
	 * to switch tenant context. NULL means "use the logged-in user's company".
	 *
	 * @var int|null
	 */
	private static $active_company_id = null;

	/**
	 * Per-request flag that disables tenant scoping entirely.
	 * Only wellanc super-admin code paths should enable this.
	 *
	 * @var bool
	 */
	private static $cross_tenant_mode = false;

	/**
	 * Resolve the company_id for the active request.
	 *
	 * Order of precedence:
	 *   1. Explicitly-set active_company_id (super-admin context switch).
	 *   2. Logged-in user's `osq_company_id` user meta.
	 *   3. Schema::DEFAULT_COMPANY_ID (1 = wellanc) as a safe fallback.
	 *
	 * @return int
	 */
	public static function current_company_id() {
		if ( null !== self::$active_company_id ) {
			return (int) self::$active_company_id;
		}

		$user_id = get_current_user_id();
		if ( $user_id ) {
			$meta = (int) get_user_meta( $user_id, self::COMPANY_USER_META_KEY, true );
			if ( $meta > 0 ) {
				return $meta;
			}
		}

		return Schema::DEFAULT_COMPANY_ID;
	}

	/**
	 * Switch the active tenant for the rest of the request.
	 * Only callable by wellanc super-admin code paths.
	 *
	 * @param int|null $company_id Pass null to clear the override.
	 * @return void
	 */
	public static function set_active_company_id( $company_id ) {
		self::$active_company_id = ( null === $company_id ) ? null : (int) $company_id;
	}

	/**
	 * Enable / disable cross-tenant mode. When true, queries do not filter by company_id.
	 * Only wellanc super-admin code paths should enable this.
	 *
	 * @param bool $enabled
	 * @return void
	 */
	public static function set_cross_tenant_mode( $enabled ) {
		self::$cross_tenant_mode = (bool) $enabled;
	}

	/**
	 * @return bool
	 */
	public static function is_cross_tenant_mode() {
		return self::$cross_tenant_mode;
	}

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
	 * Scoped to the current tenant unless cross-tenant mode is active.
	 *
	 * @param string $number Employee number.
	 * @return object|null
	 */
	public function get_employee_by_number( $number ) {
		global $wpdb;
		$table = $wpdb->prefix . Schema::EMPLOYEES;

		if ( self::is_cross_tenant_mode() ) {
			return $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE employee_number = %s", $number )
			);
		}

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE employee_number = %s AND company_id = %d",
			$number,
			self::current_company_id()
		) );
	}

	/**
	 * Get employee by WordPress user ID. `wp_user_id` is unique so no tenant
	 * scope needed for correctness, but we still apply it as defense-in-depth.
	 *
	 * @param int $user_id
	 * @return object|null
	 */
	public function get_employee_by_user_id( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . Schema::EMPLOYEES;

		if ( self::is_cross_tenant_mode() ) {
			return $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE wp_user_id = %d", $user_id )
			);
		}

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE wp_user_id = %d AND company_id = %d",
			$user_id,
			self::current_company_id()
		) );
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

		// Auto-create a temporary employee record under the current tenant.
		global $wpdb;
		$table = $wpdb->prefix . Schema::EMPLOYEES;
		$employee_number = 'ADMIN-' . $user_id;

		$wpdb->insert( $table, array(
			'company_id'      => self::current_company_id(),
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

		// Resolve the tenant for this response. Prefer the employee's company_id over
		// the current user's (so officers acting on behalf of employees stay tenant-correct).
		$company_id = $this->resolve_company_id_for_employee( $employee_id );

		$response = $this->get_response_by_employee( $employee_id );

		$values = array(
			'company_id'    => $company_id,
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
			// Archive the old completed response before overwriting with a new completion.
			if ( $response->is_complete && $is_complete ) {
				$this->archive_response( $response, $employee_id );
			}
			$wpdb->update( $table, $values, array( 'response_id' => $response->response_id ) );
			return $response->response_id;
		} else {
			$values['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $values );
			return $wpdb->insert_id;
		}
	}

	/**
	 * Look up the company_id directly from an employee row. Falls back to the
	 * current tenant if the lookup fails (e.g., during admin auto-create).
	 *
	 * @param int $employee_id
	 * @return int
	 */
	private function resolve_company_id_for_employee( $employee_id ) {
		global $wpdb;
		$table = $wpdb->prefix . Schema::EMPLOYEES;
		$cid   = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT company_id FROM {$table} WHERE employee_id = %d",
			$employee_id
		) );
		return $cid > 0 ? $cid : self::current_company_id();
	}

	/**
	 * Get response for an employee.
	 *
	 * Scoped to the current tenant unless cross-tenant mode is active.
	 *
	 * @param int $employee_id
	 * @return object|null
	 */
	public function get_response_by_employee( $employee_id ) {
		global $wpdb;
		$table = $wpdb->prefix . Schema::RESPONSES;

		if ( self::is_cross_tenant_mode() ) {
			return $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE employee_id = %d", $employee_id )
			);
		}

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE employee_id = %d AND company_id = %d",
			$employee_id,
			self::current_company_id()
		) );
	}

	/*
	|----------------------------------------------------------------------
	| Response History
	|----------------------------------------------------------------------
	*/

	/**
	 * Archive a completed response into osq_response_history before it is overwritten.
	 *
	 * @param object $response  Row from osq_stress_responses.
	 * @param int    $employee_id
	 * @return void
	 */
	private function archive_response( $response, $employee_id ) {
		global $wpdb;
		$table_history   = $wpdb->prefix . Schema::RESPONSE_HISTORY;
		$table_employees = $wpdb->prefix . Schema::EMPLOYEES;

		$employee    = $wpdb->get_row( $wpdb->prepare( "SELECT company_id, organization_1, position FROM {$table_employees} WHERE employee_id = %d", $employee_id ) );
		$fiscal_year = $response->completed_at ? (int) date( 'Y', strtotime( $response->completed_at ) ) : (int) date( 'Y' );

		$wpdb->insert( $table_history, array(
			'company_id'            => $employee ? (int) $employee->company_id : self::current_company_id(),
			'employee_id'           => $employee_id,
			'fiscal_year'           => $fiscal_year,
			'method1_result'        => $response->method1_result,
			'method2_result'        => $response->method2_result,
			'is_high_stress_method1'=> $response->is_high_stress_method1,
			'is_high_stress_method2'=> $response->is_high_stress_method2,
			'completed_at'          => $response->completed_at,
			'org_snapshot'          => $employee ? $employee->organization_1 : null,
			'position_snapshot'     => $employee ? $employee->position : null,
		) );
	}

	/**
	 * Get the most recent archived result for an employee (for YoY radar chart).
	 *
	 * @param int $employee_id
	 * @return object|null
	 */
	public function get_previous_year_result( $employee_id ) {
		global $wpdb;
		$table = $wpdb->prefix . Schema::RESPONSE_HISTORY;

		if ( self::is_cross_tenant_mode() ) {
			return $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$table} WHERE employee_id = %d ORDER BY fiscal_year DESC, archived_at DESC LIMIT 1",
				$employee_id
			) );
		}

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE employee_id = %d AND company_id = %d ORDER BY fiscal_year DESC, archived_at DESC LIMIT 1",
			$employee_id,
			self::current_company_id()
		) );
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
