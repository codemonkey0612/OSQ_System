<?php
/**
 * Email template manager — CRUD + built-in defaults (Phase 5).
 *
 * Templates are stored in osq_email_templates and edited by wellanc from the
 * master admin screen. Each template supports {tag} variables substituted at
 * send time by EmailService::render().
 *
 * @package OSQ
 */

namespace OSQ\Email;

use OSQ\Database\Schema;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EmailTemplateManager
 */
class EmailTemplateManager {

	/**
	 * Template keys.
	 */
	const COMPANY_WELCOME    = 'company_welcome';   // New company admin: login URL + initial ID/PASS.
	const SURVEY_INVITE      = 'survey_invite';      // Employee: stress check invitation.
	const SURVEY_REMINDER    = 'survey_reminder';    // Employee: non-respondent reminder.
	const PASSWORD_RESET     = 'password_reset';     // Any user: password reset link.
	const SURVEY_COMPLETE    = 'survey_complete';    // Employee: completion thank-you.

	/**
	 * Variable tags available to template authors (for UI hints).
	 *
	 * @return array key => description
	 */
	public static function available_tags() {
		return array(
			'会社名'         => '企業名',
			'氏名'           => '受信者の氏名',
			'受検URL'        => '従業員専用の受検ページURL',
			'ログインURL'    => 'ログインページURL',
			'ID'             => 'ログインID（社員番号）',
			'初期パスワード' => '自動生成された初期パスワード',
			'FAQ_URL'        => 'よくある質問ページURL（マスター設定）',
			'締切日'         => '受検締切日',
			'問い合わせ先'   => '企業の担当者連絡先',
		);
	}

	/**
	 * Get a template by key (DB row, falling back to built-in default).
	 *
	 * @param string $key
	 * @return array|null { template_key, subject, body, is_active }
	 */
	public static function get_template( $key ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT template_key, subject, body, is_active FROM {$wpdb->prefix}" . Schema::EMAIL_TEMPLATES . " WHERE template_key = %s",
			$key
		), ARRAY_A );

		if ( $row ) {
			$row['is_active'] = (int) $row['is_active'];
			return $row;
		}

		$defaults = self::defaults();
		if ( isset( $defaults[ $key ] ) ) {
			return array(
				'template_key' => $key,
				'subject'      => $defaults[ $key ]['subject'],
				'body'         => $defaults[ $key ]['body'],
				'is_active'    => 1,
			);
		}
		return null;
	}

	/**
	 * Get all templates (merged: DB overrides over defaults), in display order.
	 *
	 * @return array list of { template_key, label, subject, body, is_active, source }
	 */
	public static function get_all() {
		$defaults = self::defaults();
		$out      = array();
		foreach ( $defaults as $key => $def ) {
			$row = self::get_template( $key );
			global $wpdb;
			$has_db = (bool) $wpdb->get_var( $wpdb->prepare(
				"SELECT template_id FROM {$wpdb->prefix}" . Schema::EMAIL_TEMPLATES . " WHERE template_key = %s",
				$key
			) );
			$out[] = array(
				'template_key' => $key,
				'label'        => $def['label'],
				'subject'      => $row['subject'],
				'body'         => $row['body'],
				'is_active'    => (int) $row['is_active'],
				'source'       => $has_db ? 'db' : 'default',
			);
		}
		return $out;
	}

	/**
	 * Create or update a template.
	 *
	 * @param string $key
	 * @param string $subject
	 * @param string $body
	 * @param int    $is_active
	 * @return bool
	 */
	public static function save( $key, $subject, $body, $is_active = 1 ) {
		if ( ! array_key_exists( $key, self::defaults() ) ) {
			return false;
		}
		global $wpdb;
		$table = $wpdb->prefix . Schema::EMAIL_TEMPLATES;
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT template_id FROM {$table} WHERE template_key = %s", $key ) );

		$data = array(
			'subject'   => $subject,
			'body'      => $body,
			'is_active' => $is_active ? 1 : 0,
		);

		if ( $exists ) {
			return false !== $wpdb->update( $table, $data, array( 'template_key' => $key ) );
		}
		$data['template_key'] = $key;
		return false !== $wpdb->insert( $table, $data );
	}

	/**
	 * The label of a template key.
	 *
	 * @param string $key
	 * @return string
	 */
	public static function label( $key ) {
		$d = self::defaults();
		return $d[ $key ]['label'] ?? $key;
	}

	/**
	 * Built-in default templates.
	 *
	 * @return array key => { label, subject, body }
	 */
	public static function defaults() {
		return array(
			self::COMPANY_WELCOME => array(
				'label'   => '新規企業登録通知（企業管理者向け）',
				'subject' => '【wellanc】ストレスチェックシステム アカウント発行のお知らせ',
				'body'    => "{会社名} ご担当者様\n\n"
					. "この度はwellancストレスチェックシステムをご利用いただき、誠にありがとうございます。\n"
					. "貴社の管理者アカウントを発行いたしましたので、下記の情報にてログインしてください。\n\n"
					. "■ ログインURL\n{ログインURL}\n\n"
					. "■ ログインID\n{ID}\n\n"
					. "■ 初期パスワード\n{初期パスワード}\n\n"
					. "※ セキュリティのため、初回ログイン後に必ずパスワードのご変更をお願いいたします。\n\n"
					. "ご不明な点がございましたら、お気軽にお問い合わせください。\n"
					. "wellanc ストレスチェック事務局",
			),
			self::SURVEY_INVITE => array(
				'label'   => '受検案内（従業員向け）',
				'subject' => '【{会社名}】ストレスチェック受検のお願い',
				'body'    => "{氏名} 様\n\n"
					. "{会社名}では、従業員の皆様の心身の健康管理のため、ストレスチェックを実施しております。\n"
					. "下記のURLより、ご都合のよいときに受検をお願いいたします（所要時間：約10分）。\n\n"
					. "■ 受検ページ\n{受検URL}\n\n"
					. "■ 受検締切\n{締切日}\n\n"
					. "※ 回答内容は法律により保護され、人事評価に影響することは一切ありません。\n"
					. "※ よくある質問はこちら：{FAQ_URL}\n\n"
					. "ご不明な点は下記までお問い合わせください。\n{問い合わせ先}",
			),
			self::SURVEY_REMINDER => array(
				'label'   => '未受検リマインド（従業員向け）',
				'subject' => '【{会社名}】ストレスチェック未受検のお知らせ（受検のお願い）',
				'body'    => "{氏名} 様\n\n"
					. "先日ご案内いたしましたストレスチェックについて、まだ受検が確認できておりません。\n"
					. "お忙しいところ恐れ入りますが、下記より受検をお願いいたします（所要時間：約10分）。\n\n"
					. "■ 受検ページ\n{受検URL}\n\n"
					. "■ 受検締切\n{締切日}\n\n"
					. "※ 回答内容は法律により保護され、人事評価に影響することは一切ありません。\n\n"
					. "ご不明な点は下記までお問い合わせください。\n{問い合わせ先}",
			),
			self::PASSWORD_RESET => array(
				'label'   => 'パスワード再発行（全ユーザー向け）',
				'subject' => '【wellanc】パスワード再設定のご案内',
				'body'    => "{氏名} 様\n\n"
					. "パスワード再設定のリクエストを受け付けました。\n"
					. "下記のURLにアクセスし、新しいパスワードを設定してください。\n\n"
					. "■ パスワード再設定URL\n{受検URL}\n\n"
					. "※ このURLの有効期限は60分です。期限が切れた場合は、再度お手続きをお願いいたします。\n"
					. "※ お心当たりがない場合は、このメールを破棄してください。\n\n"
					. "wellanc ストレスチェック事務局",
			),
			self::SURVEY_COMPLETE => array(
				'label'   => '受検完了お礼（従業員向け・任意）',
				'subject' => '【{会社名}】ストレスチェック受検完了のお知らせ',
				'body'    => "{氏名} 様\n\n"
					. "ストレスチェックの受検が完了いたしました。ご協力ありがとうございました。\n"
					. "結果はマイページよりご確認いただけます。\n\n"
					. "■ ログインURL\n{ログインURL}\n\n"
					. "高ストレスと判定された場合は、産業医による面接指導を受けることができます。\n"
					. "詳しくは結果画面の案内、または下記担当者までご相談ください。\n{問い合わせ先}",
			),
		);
	}
}
