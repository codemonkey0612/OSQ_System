<?php
/**
 * Role and capability management.
 *
 * @package OSQ
 */

namespace OSQ\Auth;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RoleManager
 *
 * Handles registration and removal of custom roles and capabilities.
 * Phase 3a: adds Industrial Physician + Wellanc Super-Admin roles.
 */
class RoleManager {

	/**
	 * Role identifiers.
	 */
	const IMPLEMENTATION_OFFICER = 'osq_implementation_officer';
	const GENERAL_ADMINISTRATOR  = 'osq_general_administrator';
	const EMPLOYEE               = 'osq_employee';
	const INDUSTRIAL_PHYSICIAN   = 'osq_industrial_physician';  // Phase 3a.
	const WELLANC_ADMIN          = 'osq_wellanc_admin';          // Phase 3a (super-admin).

	/**
	 * Register custom roles and capabilities.
	 *
	 * @return void
	 */
	public static function add_roles() {
		// 1. Implementation Officer (Full health data access).
		add_role( self::IMPLEMENTATION_OFFICER, __( 'OSQ Implementation Officer', 'osq-stress-check' ), array(
			'read'                          => true,
			'osq_view_individual_responses' => true,
			'osq_view_pdfs'                 => true,
			'osq_support_high_stress'       => true,
		) );

		// 2. General Administrator (Management only — no individual health data).
		add_role( self::GENERAL_ADMINISTRATOR, __( 'OSQ General Administrator', 'osq-stress-check' ), array(
			'read'                    => true,
			'osq_manage_employees'    => true,
			'osq_view_group_analysis' => true,
			'osq_system_config'       => true,
		) );

		// 3. Employee (Mapped to subscriber capabilities).
		add_role( self::EMPLOYEE, __( 'OSQ Employee', 'osq-stress-check' ), array(
			'read'                 => true,
			'osq_take_test'        => true,
			'osq_view_own_results' => true,
			'osq_download_own_pdf' => true,
		) );

		// 4. Industrial Physician (Phase 3a) — same data access as Implementation Officer,
		//    distinct identity so analytics can separate "supported by officer" vs "consulted by doctor".
		add_role( self::INDUSTRIAL_PHYSICIAN, __( 'OSQ Industrial Physician', 'osq-stress-check' ), array(
			'read'                          => true,
			'osq_view_individual_responses' => true,
			'osq_view_pdfs'                 => true,
			'osq_support_high_stress'       => true,
			'osq_industrial_physician_view' => true,
		) );

		// 5. Wellanc Super-Admin (Phase 3a) — manages all tenants. The ONLY role with cross-tenant access.
		add_role( self::WELLANC_ADMIN, __( 'OSQ Wellanc Super-Admin', 'osq-stress-check' ), array(
			'read'                          => true,
			'osq_take_test'                 => true,
			'osq_view_own_results'          => true,
			'osq_download_own_pdf'          => true,
			'osq_view_individual_responses' => true,
			'osq_view_pdfs'                 => true,
			'osq_support_high_stress'       => true,
			'osq_industrial_physician_view' => true,
			'osq_manage_employees'          => true,
			'osq_view_group_analysis'       => true,
			'osq_system_config'             => true,
			'osq_manage_all_companies'      => true,
			'osq_cross_tenant_view'         => true,
		) );

		// Sync capabilities to the standard administrator so they can manage the plugin
		// (treating WP admin as a system-level super-user separate from OSQ tenants).
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'osq_manage_employees' );
			$admin->add_cap( 'osq_view_group_analysis' );
			$admin->add_cap( 'osq_system_config' );
			$admin->add_cap( 'osq_manage_all_companies' );
			$admin->add_cap( 'osq_cross_tenant_view' );
		}
	}

	/**
	 * Remove custom roles and capabilities.
	 *
	 * @return void
	 */
	public static function remove_roles() {
		$roles = array(
			self::IMPLEMENTATION_OFFICER,
			self::GENERAL_ADMINISTRATOR,
			self::EMPLOYEE,
			self::INDUSTRIAL_PHYSICIAN,
			self::WELLANC_ADMIN,
		);

		foreach ( $roles as $role ) {
			remove_role( $role );
		}
	}

	/**
	 * Does the current (or given) user have cross-tenant access?
	 * Used by DbManager and analyzers to decide whether to apply tenant scoping.
	 *
	 * @param int|null $user_id Defaults to current user.
	 * @return bool
	 */
	public static function user_can_cross_tenant( $user_id = null ) {
		if ( null === $user_id ) {
			return current_user_can( 'osq_cross_tenant_view' );
		}
		return user_can( $user_id, 'osq_cross_tenant_view' );
	}
}
