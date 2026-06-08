<?php
/**
 * Email settings + master template admin page (Phase 5, wellanc super-admin).
 *
 * Two tabs:
 *  - SMTP / 送信元設定: server, credentials, From, FAQ URL + test send.
 *  - テンプレート管理: edit the 5 master email templates.
 *
 * @package OSQ
 */

namespace OSQ\Admin;

use OSQ\Email\EmailService;
use OSQ\Email\EmailTemplateManager;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EmailSettingsPage
 */
class EmailSettingsPage {

	/**
	 * Register POST handlers.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_post_osq_save_email_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_osq_save_email_template', array( $this, 'handle_save_template' ) );
		add_action( 'admin_post_osq_send_test_email', array( $this, 'handle_test_email' ) );
	}

	/**
	 * Render the page (tabbed).
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'osq_system_config' ) ) {
			wp_die( esc_html__( 'Access denied.', 'osq-stress-check' ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$tab    = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'smtp';
		$edit   = isset( $_GET['template'] ) ? sanitize_key( $_GET['template'] ) : '';
		$notice = isset( $_GET['saved'] ) ? sanitize_key( $_GET['saved'] ) : '';
		// phpcs:enable

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'メール設定・テンプレート管理', 'osq-stress-check' ) . '</h1>';

		if ( $notice ) {
			$msg = array(
				'settings' => 'SMTP設定を保存しました。',
				'template' => 'テンプレートを保存しました。',
				'test_ok'  => 'テストメールを送信しました。受信をご確認ください。',
				'test_ng'  => 'テストメールの送信に失敗しました。設定をご確認ください。',
			);
			$cls = ( 'test_ng' === $notice ) ? 'notice-error' : 'notice-success';
			echo '<div class="notice ' . esc_attr( $cls ) . ' is-dismissible"><p>' . esc_html( $msg[ $notice ] ?? '保存しました。' ) . '</p></div>';
		}

		$base = admin_url( 'admin.php?page=' . AdminMenu::MENU_SLUG . '-email' );
		echo '<h2 class="nav-tab-wrapper">';
		printf( '<a href="%s" class="nav-tab %s">%s</a>', esc_url( $base ), 'smtp' === $tab ? 'nav-tab-active' : '', esc_html__( 'SMTP・送信元設定', 'osq-stress-check' ) );
		printf( '<a href="%s" class="nav-tab %s">%s</a>', esc_url( add_query_arg( 'tab', 'templates', $base ) ), 'templates' === $tab ? 'nav-tab-active' : '', esc_html__( 'テンプレート管理', 'osq-stress-check' ) );
		echo '</h2>';

		if ( 'templates' === $tab ) {
			if ( $edit ) {
				$this->render_template_edit( $edit );
			} else {
				$this->render_template_list( $base );
			}
		} else {
			$this->render_smtp_form();
		}

		echo '</div>';
	}

	/**
	 * SMTP / sender settings form.
	 *
	 * @return void
	 */
	private function render_smtp_form() {
		$cfg = EmailService::config();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'osq_save_email_settings', 'osq_email_nonce' ); ?>
			<input type="hidden" name="action" value="osq_save_email_settings">
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'SMTP配信を有効化', 'osq-stress-check' ); ?></th>
					<td><label><input type="checkbox" name="smtp_enabled" value="1" <?php checked( ! empty( $cfg['smtp_enabled'] ) ); ?>> <?php esc_html_e( '有効', 'osq-stress-check' ); ?></label>
					<p class="description"><?php esc_html_e( '無効の場合はサーバー標準のmail()送信になります。', 'osq-stress-check' ); ?></p></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'SMTPホスト', 'osq-stress-check' ); ?></th>
					<td><input type="text" name="smtp_host" value="<?php echo esc_attr( $cfg['smtp_host'] ); ?>" class="regular-text"></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'ポート', 'osq-stress-check' ); ?></th>
					<td><input type="number" name="smtp_port" value="<?php echo esc_attr( $cfg['smtp_port'] ); ?>" class="small-text"></td></tr>
				<tr><th scope="row"><?php esc_html_e( '暗号化', 'osq-stress-check' ); ?></th>
					<td><select name="smtp_encryption">
						<?php foreach ( array( 'tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'なし' ) as $v => $l ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $cfg['smtp_encryption'], $v ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'SMTPユーザー名', 'osq-stress-check' ); ?></th>
					<td><input type="text" name="smtp_user" value="<?php echo esc_attr( $cfg['smtp_user'] ); ?>" class="regular-text" autocomplete="off"></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'SMTPパスワード', 'osq-stress-check' ); ?></th>
					<td><input type="password" name="smtp_pass" value="<?php echo esc_attr( $cfg['smtp_pass'] ); ?>" class="regular-text" autocomplete="new-password">
					<p class="description"><?php esc_html_e( 'onamaeメールのパスワードを入力してください。', 'osq-stress-check' ); ?></p></td></tr>
				<tr><th scope="row"><?php esc_html_e( '送信元アドレス (From)', 'osq-stress-check' ); ?></th>
					<td><input type="email" name="mail_from" value="<?php echo esc_attr( $cfg['mail_from'] ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( '従業員への返信は各企業の連絡先（Reply-To）に届きます。', 'osq-stress-check' ); ?></p></td></tr>
				<tr><th scope="row"><?php esc_html_e( '送信者名', 'osq-stress-check' ); ?></th>
					<td><input type="text" name="mail_from_name" value="<?php echo esc_attr( $cfg['mail_from_name'] ); ?>" class="regular-text"></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'FAQ URL', 'osq-stress-check' ); ?></th>
					<td><input type="url" name="faq_url" value="<?php echo esc_attr( $cfg['faq_url'] ?? '' ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'メール内の {FAQ_URL} に挿入されます。', 'osq-stress-check' ); ?></p></td></tr>
			</table>
			<?php submit_button( __( 'SMTP設定を保存', 'osq-stress-check' ) ); ?>
		</form>

		<hr>
		<h2><?php esc_html_e( 'テスト送信', 'osq-stress-check' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'osq_send_test_email', 'osq_test_nonce' ); ?>
			<input type="hidden" name="action" value="osq_send_test_email">
			<input type="email" name="test_to" placeholder="test@example.com" class="regular-text" required>
			<?php submit_button( __( 'テストメールを送信', 'osq-stress-check' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Template list.
	 *
	 * @param string $base
	 * @return void
	 */
	private function render_template_list( $base ) {
		$templates = EmailTemplateManager::get_all();
		?>
		<p><?php esc_html_e( 'メールテンプレートを編集できます。各テンプレートで使用できる変数タグは編集画面に表示されます。', 'osq-stress-check' ); ?></p>
		<table class="wp-list-table widefat fixed striped" style="margin-top:12px;">
			<thead><tr>
				<th><?php esc_html_e( 'テンプレート', 'osq-stress-check' ); ?></th>
				<th><?php esc_html_e( '件名', 'osq-stress-check' ); ?></th>
				<th style="width:90px;"><?php esc_html_e( '状態', 'osq-stress-check' ); ?></th>
				<th style="width:90px;"><?php esc_html_e( '操作', 'osq-stress-check' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $templates as $t ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $t['label'] ); ?></strong></td>
					<td><?php echo esc_html( $t['subject'] ); ?></td>
					<td><?php echo $t['is_active'] ? '<span style="color:#15803d;">有効</span>' : '<span style="color:#b91c1c;">無効</span>'; ?></td>
					<td><a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'tab' => 'templates', 'template' => $t['template_key'] ), $base ) ); ?>"><?php esc_html_e( '編集', 'osq-stress-check' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Template edit form.
	 *
	 * @param string $key
	 * @return void
	 */
	private function render_template_edit( $key ) {
		$tpl = EmailTemplateManager::get_template( $key );
		if ( ! $tpl ) {
			echo '<p>' . esc_html__( 'テンプレートが見つかりません。', 'osq-stress-check' ) . '</p>';
			return;
		}
		$list_url = add_query_arg( 'tab', 'templates', admin_url( 'admin.php?page=' . AdminMenu::MENU_SLUG . '-email' ) );
		?>
		<h2 style="margin-top:16px;"><?php echo esc_html( EmailTemplateManager::label( $key ) ); ?></h2>

		<details style="margin:12px 0;border:1px solid #ddd;background:#f9f9f9;padding:10px 14px;border-radius:3px;">
			<summary style="cursor:pointer;font-weight:600;"><?php esc_html_e( '使用可能な変数タグ', 'osq-stress-check' ); ?></summary>
			<table style="margin-top:10px;font-size:13px;">
				<?php foreach ( EmailTemplateManager::available_tags() as $tag => $desc ) : ?>
					<tr><td style="padding:2px 16px 2px 0;"><code>{<?php echo esc_html( $tag ); ?>}</code></td><td><?php echo esc_html( $desc ); ?></td></tr>
				<?php endforeach; ?>
			</table>
		</details>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'osq_save_email_template', 'osq_tpl_nonce' ); ?>
			<input type="hidden" name="action" value="osq_save_email_template">
			<input type="hidden" name="template_key" value="<?php echo esc_attr( $key ); ?>">
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( '有効', 'osq-stress-check' ); ?></th>
					<td><label><input type="checkbox" name="is_active" value="1" <?php checked( ! empty( $tpl['is_active'] ) ); ?>> <?php esc_html_e( 'このメールを送信する', 'osq-stress-check' ); ?></label></td></tr>
				<tr><th scope="row"><label for="osq-tpl-subject"><?php esc_html_e( '件名', 'osq-stress-check' ); ?></label></th>
					<td><input type="text" id="osq-tpl-subject" name="subject" value="<?php echo esc_attr( $tpl['subject'] ); ?>" class="large-text"></td></tr>
				<tr><th scope="row"><label for="osq-tpl-body"><?php esc_html_e( '本文', 'osq-stress-check' ); ?></label></th>
					<td><textarea id="osq-tpl-body" name="body" rows="18" class="large-text" style="font-family:monospace;font-size:13px;"><?php echo esc_textarea( $tpl['body'] ); ?></textarea></td></tr>
			</table>
			<p>
				<?php submit_button( __( '保存', 'osq-stress-check' ), 'primary', 'submit', false ); ?>
				&nbsp;<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( '← 一覧に戻る', 'osq-stress-check' ); ?></a>
			</p>
		</form>
		<?php
	}

	/*
	|----------------------------------------------------------------------
	| POST handlers
	|----------------------------------------------------------------------
	*/

	/**
	 * Save SMTP / sender settings.
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		if ( ! current_user_can( 'osq_system_config' ) ) {
			wp_die( esc_html__( 'Access denied.', 'osq-stress-check' ) );
		}
		check_admin_referer( 'osq_save_email_settings', 'osq_email_nonce' );

		$settings = array(
			'smtp_enabled'    => isset( $_POST['smtp_enabled'] ) ? 1 : 0,
			'smtp_host'       => sanitize_text_field( wp_unslash( $_POST['smtp_host'] ?? '' ) ),
			'smtp_port'       => absint( $_POST['smtp_port'] ?? 587 ),
			'smtp_encryption' => sanitize_key( $_POST['smtp_encryption'] ?? 'tls' ),
			'smtp_user'       => sanitize_text_field( wp_unslash( $_POST['smtp_user'] ?? '' ) ),
			'smtp_pass'       => (string) wp_unslash( $_POST['smtp_pass'] ?? '' ),
			'mail_from'       => sanitize_email( wp_unslash( $_POST['mail_from'] ?? '' ) ),
			'mail_from_name'  => sanitize_text_field( wp_unslash( $_POST['mail_from_name'] ?? '' ) ),
			'faq_url'         => esc_url_raw( wp_unslash( $_POST['faq_url'] ?? '' ) ),
		);
		update_option( 'osq_email_settings', $settings );

		$this->redirect( array( 'saved' => 'settings' ) );
	}

	/**
	 * Save a template.
	 *
	 * @return void
	 */
	public function handle_save_template() {
		if ( ! current_user_can( 'osq_system_config' ) ) {
			wp_die( esc_html__( 'Access denied.', 'osq-stress-check' ) );
		}
		check_admin_referer( 'osq_save_email_template', 'osq_tpl_nonce' );

		$key     = sanitize_key( $_POST['template_key'] ?? '' );
		$subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$body    = wp_kses_post( wp_unslash( $_POST['body'] ?? '' ) );
		$active  = isset( $_POST['is_active'] ) ? 1 : 0;

		EmailTemplateManager::save( $key, $subject, $body, $active );
		$this->redirect( array( 'tab' => 'templates', 'saved' => 'template' ) );
	}

	/**
	 * Send a test email.
	 *
	 * @return void
	 */
	public function handle_test_email() {
		if ( ! current_user_can( 'osq_system_config' ) ) {
			wp_die( esc_html__( 'Access denied.', 'osq-stress-check' ) );
		}
		check_admin_referer( 'osq_send_test_email', 'osq_test_nonce' );

		$to  = sanitize_email( wp_unslash( $_POST['test_to'] ?? '' ) );
		$ok  = $to && ( new EmailService() )->send_test( $to );
		$this->redirect( array( 'saved' => $ok ? 'test_ok' : 'test_ng' ) );
	}

	/**
	 * Redirect back to the email page with args.
	 *
	 * @param array $args
	 * @return void
	 */
	private function redirect( $args ) {
		$args['page'] = AdminMenu::MENU_SLUG . '-email';
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
