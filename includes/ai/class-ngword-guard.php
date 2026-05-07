<?php
/**
 * NGWord guard for AI-generated advice.
 *
 * @package OSQ
 */

namespace OSQ\AI;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NGWordGuard
 *
 * Checks AI output against a DB-managed prohibited word/phrase list.
 * On detection: triggers one automatic retry, then falls back to a safe default.
 */
class NGWordGuard {

	/**
	 * Maximum automatic regeneration attempts before falling back.
	 */
	const MAX_RETRIES = 1;

	/**
	 * Check text against the active NGword list.
	 *
	 * @param string $text Text to check.
	 * @return array { clean: bool, matched: string[] }
	 */
	public static function check( $text ) {
		$ngwords = self::get_active_ngwords();
		$matched = array();

		foreach ( $ngwords as $word ) {
			if ( mb_stripos( $text, $word ) !== false ) {
				$matched[] = $word;
			}
		}

		return array(
			'clean'   => empty( $matched ),
			'matched' => $matched,
		);
	}

	/**
	 * Validate AI output and retry once if NGwords detected.
	 * Falls back to safe_fallback() if retry also fails.
	 *
	 * @param string   $initial_text   First AI response.
	 * @param callable $regenerate_fn  Callable that returns a new string|\WP_Error.
	 * @param string   $industry_label For the fallback message.
	 * @return string Final safe text.
	 */
	public static function validate_or_retry( $initial_text, callable $regenerate_fn, $industry_label = '' ) {
		$result = self::check( $initial_text );
		if ( $result['clean'] ) {
			return $initial_text;
		}

		// One retry.
		$retry = call_user_func( $regenerate_fn );
		if ( ! is_wp_error( $retry ) && is_string( $retry ) ) {
			$retry_result = self::check( $retry );
			if ( $retry_result['clean'] ) {
				return $retry;
			}
		}

		// Both attempts failed — return generic safe fallback.
		return self::safe_fallback( $industry_label );
	}

	/**
	 * Generic safe fallback message shown when all retries fail.
	 *
	 * @param string $industry_label
	 * @return string
	 */
	public static function safe_fallback( $industry_label = '' ) {
		return __( 'ストレスチェックの結果を確認しました。現在の状況について、産業医や信頼できる上長へ相談することをお勧めします。一人で抱え込まず、サポートを求めることも大切な行動です。', 'osq-stress-check' );
	}

	/**
	 * Get all active NGwords from DB.
	 *
	 * @return string[]
	 */
	public static function get_active_ngwords() {
		global $wpdb;
		$table = $wpdb->prefix . \OSQ\Database\Schema::AI_NGWORDS;

		$rows = $wpdb->get_col( "SELECT word FROM {$table} WHERE is_active = 1" );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Default NGword list seeded on activation.
	 * Covers clinical diagnoses, suicidal ideation triggers, and extreme judgments.
	 *
	 * @return array { word: string, reason: string }[]
	 */
	public static function get_default_ngwords() {
		return array(
			array( 'word' => '死にたい',       'reason' => '自傷・自殺念慮の誘発リスク' ),
			array( 'word' => '消えてしまいたい', 'reason' => '自傷・自殺念慮の誘発リスク' ),
			array( 'word' => '自殺',           'reason' => '直接的な自傷表現' ),
			array( 'word' => 'うつ病',         'reason' => '医療診断は医師のみが行える' ),
			array( 'word' => '統合失調症',      'reason' => '医療診断は医師のみが行える' ),
			array( 'word' => '双極性障害',      'reason' => '医療診断は医師のみが行える' ),
			array( 'word' => 'PTSDです',       'reason' => '医療診断は医師のみが行える' ),
			array( 'word' => '病気です',        'reason' => '断定的な病名付与' ),
			array( 'word' => '異常です',        'reason' => '否定的な断定表現' ),
			array( 'word' => '限界です',        'reason' => '過度に煽る可能性のある表現' ),
			array( 'word' => '薬を飲め',        'reason' => '処方は医師の専権事項' ),
			array( 'word' => '即刻退職',        'reason' => '過激な行動誘導' ),
			array( 'word' => '会社を辞めるべき', 'reason' => '過激な行動誘導' ),
		);
	}
}
