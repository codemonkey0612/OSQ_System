<?php
/**
 * Organization label service.
 *
 * Phase 3b: resolves per-tenant org_label_1..5 from osq_companies and
 * provides helpers for compact display and CSV header capture.
 *
 * @package OSQ
 */

namespace OSQ\Services;

use OSQ\Database\Schema;
use OSQ\Database\DbManager;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OrgLabelService {

	/**
	 * Get the display label for one org level (1–5).
	 * Falls back to "組織N" if not configured.
	 *
	 * @param int $company_id
	 * @param int $level 1–5
	 * @return string
	 */
	public static function get_label( $company_id, $level ) {
		$labels = self::get_all_labels( $company_id );
		return $labels[ $level ] ?? ( '組織' . $level );
	}

	/**
	 * Get all 5 org labels for a company as [1=>'...', ..., 5=>'...'].
	 *
	 * @param int $company_id
	 * @return array
	 */
	public static function get_all_labels( $company_id ) {
		$company_id = (int) $company_id;
		$cache_key  = 'osq_org_labels_' . $company_id;
		$cached     = wp_cache_get( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . Schema::COMPANIES;
		$row   = $wpdb->get_row( $wpdb->prepare(
			"SELECT org_label_1, org_label_2, org_label_3, org_label_4, org_label_5 FROM {$table} WHERE company_id = %d",
			$company_id
		) );

		$defaults = array(
			1 => '組織1',
			2 => '組織2',
			3 => '組織3',
			4 => '組織4',
			5 => '組織5',
		);

		if ( ! $row ) {
			wp_cache_set( $cache_key, $defaults, '', 300 );
			return $defaults;
		}

		$labels = array(
			1 => $row->org_label_1 ?: '組織1',
			2 => $row->org_label_2 ?: '組織2',
			3 => $row->org_label_3 ?: '組織3',
			4 => $row->org_label_4 ?: '組織4',
			5 => $row->org_label_5 ?: '組織5',
		);

		wp_cache_set( $cache_key, $labels, '', 300 );
		return $labels;
	}

	/**
	 * Save org label strings captured from CSV headers to osq_companies.
	 * Only saves non-empty values; skips levels where the header wasn't found.
	 *
	 * @param int   $company_id
	 * @param array $raw_headers Keyed by level: [1=>'original header text', ...]
	 * @return void
	 */
	public static function save_from_headers( $company_id, $raw_headers ) {
		$company_id = (int) $company_id;
		if ( ! $company_id || empty( $raw_headers ) ) {
			return;
		}

		global $wpdb;
		$table  = $wpdb->prefix . Schema::COMPANIES;
		$update = array();

		foreach ( range( 1, 5 ) as $level ) {
			if ( ! empty( $raw_headers[ $level ] ) ) {
				$update[ 'org_label_' . $level ] = sanitize_text_field( $raw_headers[ $level ] );
			}
		}

		if ( empty( $update ) ) {
			return;
		}

		$wpdb->update( $table, $update, array( 'company_id' => $company_id ) );
		wp_cache_delete( 'osq_org_labels_' . $company_id );
	}

	/**
	 * Compact an employee's org values for display, skipping empty slots.
	 * Returns array of [label, value] pairs for non-empty org fields only.
	 *
	 * @param object $employee  Row from osq_employees.
	 * @param int    $company_id
	 * @return array [ ['label'=>'...', 'value'=>'...'], ... ]
	 */
	public static function compact_for_display( $employee, $company_id ) {
		$labels = self::get_all_labels( $company_id );
		$result = array();

		foreach ( range( 1, 5 ) as $level ) {
			$field = 'organization_' . $level;
			$value = $employee->$field ?? '';
			if ( '' !== $value && null !== $value ) {
				$result[] = array(
					'label' => $labels[ $level ],
					'value' => $value,
				);
			}
		}

		return $result;
	}
}
