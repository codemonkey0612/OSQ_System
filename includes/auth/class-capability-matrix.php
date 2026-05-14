<?php
/**
 * Capability matrix — source of truth for OSQ capabilities and the roles that hold them.
 *
 * Phase 3a introduces this class to decouple feature access from role identity,
 * so a single user can hold multiple roles and see a unified dashboard whose
 * tabs depend on capability grants instead of role membership.
 *
 * @package OSQ
 */

namespace OSQ\Auth;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CapabilityMatrix {

	/**
	 * Capability identifiers used throughout the plugin.
	 */
	const TAKE_TEST              = 'osq_take_test';
	const VIEW_OWN_RESULTS       = 'osq_view_own_results';
	const DOWNLOAD_OWN_PDF       = 'osq_download_own_pdf';
	const VIEW_INDIVIDUAL_RESPONSES = 'osq_view_individual_responses';
	const VIEW_PDFS              = 'osq_view_pdfs';
	const SUPPORT_HIGH_STRESS    = 'osq_support_high_stress';
	const INDUSTRIAL_PHYSICIAN_VIEW = 'osq_industrial_physician_view';
	const MANAGE_EMPLOYEES       = 'osq_manage_employees';
	const VIEW_GROUP_ANALYSIS    = 'osq_view_group_analysis';
	const SYSTEM_CONFIG          = 'osq_system_config';
	const MANAGE_ALL_COMPANIES   = 'osq_manage_all_companies';
	const CROSS_TENANT_VIEW      = 'osq_cross_tenant_view';

	/**
	 * Returns the canonical role → capability mapping.
	 *
	 * @return array
	 */
	public static function get_matrix() {
		return array(
			RoleManager::EMPLOYEE => array(
				self::TAKE_TEST,
				self::VIEW_OWN_RESULTS,
				self::DOWNLOAD_OWN_PDF,
			),
			RoleManager::IMPLEMENTATION_OFFICER => array(
				self::VIEW_INDIVIDUAL_RESPONSES,
				self::VIEW_PDFS,
				self::SUPPORT_HIGH_STRESS,
			),
			RoleManager::INDUSTRIAL_PHYSICIAN => array(
				self::VIEW_INDIVIDUAL_RESPONSES,
				self::VIEW_PDFS,
				self::SUPPORT_HIGH_STRESS,
				self::INDUSTRIAL_PHYSICIAN_VIEW,
			),
			RoleManager::GENERAL_ADMINISTRATOR => array(
				self::MANAGE_EMPLOYEES,
				self::VIEW_GROUP_ANALYSIS,
				self::SYSTEM_CONFIG,
			),
			RoleManager::WELLANC_ADMIN => array(
				self::TAKE_TEST,
				self::VIEW_OWN_RESULTS,
				self::DOWNLOAD_OWN_PDF,
				self::VIEW_INDIVIDUAL_RESPONSES,
				self::VIEW_PDFS,
				self::SUPPORT_HIGH_STRESS,
				self::INDUSTRIAL_PHYSICIAN_VIEW,
				self::MANAGE_EMPLOYEES,
				self::VIEW_GROUP_ANALYSIS,
				self::SYSTEM_CONFIG,
				self::MANAGE_ALL_COMPANIES,
				self::CROSS_TENANT_VIEW,
			),
		);
	}

	/**
	 * Human-readable label + description for each capability, used by the
	 * permissions admin UI when wellanc super-admin assigns capabilities to users.
	 *
	 * @return array
	 */
	public static function get_labels() {
		return array(
			self::TAKE_TEST                  => array(
				'label'       => __( 'ストレスチェック受検', 'osq-stress-check' ),
				'description' => __( '従業員として質問票に回答できる', 'osq-stress-check' ),
			),
			self::VIEW_OWN_RESULTS           => array(
				'label'       => __( '自分の結果閲覧', 'osq-stress-check' ),
				'description' => __( '本人ダッシュボードでスコア・AIアドバイスを閲覧できる', 'osq-stress-check' ),
			),
			self::DOWNLOAD_OWN_PDF           => array(
				'label'       => __( '自分のPDFダウンロード', 'osq-stress-check' ),
				'description' => __( '結果レポートをPDFでダウンロードできる', 'osq-stress-check' ),
			),
			self::VIEW_INDIVIDUAL_RESPONSES  => array(
				'label'       => __( '個別回答の閲覧', 'osq-stress-check' ),
				'description' => __( '従業員一人ひとりの回答内容を閲覧できる（要配慮個人情報）', 'osq-stress-check' ),
			),
			self::VIEW_PDFS                  => array(
				'label'       => __( '個別PDF閲覧', 'osq-stress-check' ),
				'description' => __( '従業員のPDF結果レポートを閲覧できる', 'osq-stress-check' ),
			),
			self::SUPPORT_HIGH_STRESS        => array(
				'label'       => __( '高ストレス者支援', 'osq-stress-check' ),
				'description' => __( '高ストレス判定者への面談計画・フォローアップを記録できる', 'osq-stress-check' ),
			),
			self::INDUSTRIAL_PHYSICIAN_VIEW  => array(
				'label'       => __( '産業医ビュー', 'osq-stress-check' ),
				'description' => __( '産業医として面談記録・所見を作成できる', 'osq-stress-check' ),
			),
			self::MANAGE_EMPLOYEES           => array(
				'label'       => __( '従業員管理', 'osq-stress-check' ),
				'description' => __( '従業員の登録・更新・CSV一括登録ができる（健康データは非表示）', 'osq-stress-check' ),
			),
			self::VIEW_GROUP_ANALYSIS        => array(
				'label'       => __( '集団分析閲覧', 'osq-stress-check' ),
				'description' => __( '組織単位の集計結果を閲覧できる（個人特定不可）', 'osq-stress-check' ),
			),
			self::SYSTEM_CONFIG              => array(
				'label'       => __( 'システム設定', 'osq-stress-check' ),
				'description' => __( 'AIプロンプト・NGワード・組織ラベル等のシステム設定を変更できる', 'osq-stress-check' ),
			),
			self::MANAGE_ALL_COMPANIES       => array(
				'label'       => __( '全企業管理（wellanc専用）', 'osq-stress-check' ),
				'description' => __( '全クライアント企業の作成・無効化・横断管理ができる', 'osq-stress-check' ),
			),
			self::CROSS_TENANT_VIEW          => array(
				'label'       => __( '企業横断ビュー（wellanc専用）', 'osq-stress-check' ),
				'description' => __( '企業の枠を超えて全データを閲覧できる', 'osq-stress-check' ),
			),
		);
	}

	/**
	 * Get capabilities granted to a specific role.
	 *
	 * @param string $role
	 * @return array
	 */
	public static function get_capabilities_for_role( $role ) {
		$matrix = self::get_matrix();
		return $matrix[ $role ] ?? array();
	}

	/**
	 * Get roles that hold a specific capability.
	 *
	 * @param string $capability
	 * @return array
	 */
	public static function get_roles_with_capability( $capability ) {
		$roles = array();
		foreach ( self::get_matrix() as $role => $caps ) {
			if ( in_array( $capability, $caps, true ) ) {
				$roles[] = $role;
			}
		}
		return $roles;
	}

	/**
	 * Does the current (or given) user have the capability?
	 * Thin wrapper around current_user_can() / user_can() that also
	 * honors WP `administrator` as a system-level super-user.
	 *
	 * @param string   $capability
	 * @param int|null $user_id
	 * @return bool
	 */
	public static function user_has( $capability, $user_id = null ) {
		if ( null === $user_id ) {
			if ( current_user_can( 'administrator' ) ) {
				return true;
			}
			return current_user_can( $capability );
		}

		if ( user_can( $user_id, 'administrator' ) ) {
			return true;
		}
		return user_can( $user_id, $capability );
	}
}
