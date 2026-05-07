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
 */
class RoleManager {

	/**
	 * Role identifiers.
	 */
	const IMPLEMENTATION_OFFICER = 'osq_implementation_officer';
	const GENERAL_ADMINISTRATOR  = 'osq_general_administrator';
	const EMPLOYEE               = 'osq_employee';

	/**
	 * Register custom roles and capabilities.
	 *
	 * @return void
	 */
	public static function add_roles() {
		// 1. Implementation Officer (Full health data access)
		add_role( self::IMPLEMENTATION_OFFICER, __( 'OSQ Implementation Officer', 'osq-stress-check' ), array(
			'read'                          => true,
			'osq_view_individual_responses' => true,
			'osq_view_pdfs'                 => true,
			'osq_support_high_stress'       => true,
		) );

		// 2. General Administrator (Management only - NO individual health data)
		add_role( self::GENERAL_ADMINISTRATOR, __( 'OSQ General Administrator', 'osq-stress-check' ), array(
			'read'                   => true,
			'osq_manage_employees'   => true,
			'osq_view_group_analysis' => true,
			'osq_system_config'      => true,
		) );

		// 3. Employee (Mapped to subscriber capabilities)
		add_role( self::EMPLOYEE, __( 'OSQ Employee', 'osq-stress-check' ), array(
			'read'                  => true,
			'osq_take_test'         => true,
			'osq_view_own_results'  => true,
			'osq_download_own_pdf'  => true,
		) );

		// Sync capabilities to standard administrator so they can manage the plugin.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'osq_manage_employees' );
			$admin->add_cap( 'osq_view_group_analysis' );
			$admin->add_cap( 'osq_system_config' );
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
		);

		foreach ( $roles as $role ) {
			remove_role( $role );
		}
	}
}
