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
		$definitions = array(
			self::IMPLEMENTATION_OFFICER => array(
				'label' => __( 'OSQ Implementation Officer', 'osq-stress-check' ),
				'caps'  => array(
					'read'                          => true,
					'osq_view_individual_responses' => true,
					'osq_view_pdfs'                 => true,
					'osq_support_high_stress'       => true,
				),
			),
			self::GENERAL_ADMINISTRATOR  => array(
				'label' => __( 'OSQ General Administrator', 'osq-stress-check' ),
				'caps'  => array(
					'read'                    => true,
					'osq_manage_employees'    => true,
					'osq_view_group_analysis' => true,
					'osq_system_config'       => true,
				),
			),
			self::EMPLOYEE               => array(
				'label' => __( 'OSQ Employee', 'osq-stress-check' ),
				'caps'  => array(
					'read'                 => true,
					'osq_take_test'        => true,
					'osq_view_own_results' => true,
					'osq_download_own_pdf' => true,
				),
			),
			self::INDUSTRIAL_PHYSICIAN   => array(
				'label' => __( 'OSQ Industrial Physician', 'osq-stress-check' ),
				'caps'  => array(
					'read'                          => true,
					'osq_view_individual_responses' => true,
					'osq_view_pdfs'                 => true,
					'osq_support_high_stress'       => true,
					'osq_industrial_physician_view' => true,
				),
			),
			self::WELLANC_ADMIN          => array(
				'label' => __( 'OSQ Wellanc Super-Admin', 'osq-stress-check' ),
				'caps'  => array(
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
				),
			),
		);

		foreach ( $definitions as $role_name => $def ) {
			// add_role() is a no-op when the role already exists, so always sync caps explicitly.
			add_role( $role_name, $def['label'], $def['caps'] );
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( $def['caps'] as $cap => $grant ) {
					$role->add_cap( $cap, $grant );
				}
			}
		}

		// Sync capabilities to the standard administrator.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( array( 'osq_manage_employees', 'osq_view_group_analysis', 'osq_system_config', 'osq_manage_all_companies', 'osq_cross_tenant_view' ) as $cap ) {
				$admin->add_cap( $cap );
			}
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
