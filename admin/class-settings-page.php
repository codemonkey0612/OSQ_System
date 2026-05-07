<?php
/**
 * Settings page using WordPress Settings API.
 *
 * @package OSQ
 */

namespace OSQ\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SettingsPage
 *
 * Registers and renders plugin settings using the WordPress Settings API.
 */
class SettingsPage {

	/**
	 * Option group.
	 */
	const OPTION_GROUP = 'osq_settings_group';

	/**
	 * Option name.
	 */
	const OPTION_NAME = 'osq_settings';

	/**
	 * Initialize Settings API hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register settings, sections, and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array( $this, 'sanitize_settings' )
		);

		// General section.
		add_settings_section(
			'osq_general',
			__( '一般設定 / General Settings', 'osq-stress-check' ),
			array( $this, 'render_general_section' ),
			AdminMenu::MENU_SLUG . '-settings'
		);

		add_settings_field(
			'language',
			__( '言語 / Language', 'osq-stress-check' ),
			array( $this, 'render_language_field' ),
			AdminMenu::MENU_SLUG . '-settings',
			'osq_general'
		);

		add_settings_field(
			'company_logo',
			__( '会社ロゴ / Company Logo', 'osq-stress-check' ),
			array( $this, 'render_logo_field' ),
			AdminMenu::MENU_SLUG . '-settings',
			'osq_general'
		);

		// Notification section.
		add_settings_section(
			'osq_notifications',
			__( '通知設定 / Notification Settings', 'osq-stress-check' ),
			array( $this, 'render_notification_section' ),
			AdminMenu::MENU_SLUG . '-settings'
		);

		add_settings_field(
			'email_notifications',
			__( 'メール通知 / Email Notifications', 'osq-stress-check' ),
			array( $this, 'render_email_field' ),
			AdminMenu::MENU_SLUG . '-settings',
			'osq_notifications'
		);

		// Security section.
		add_settings_section(
			'osq_security',
			__( 'セキュリティ設定 / Security Settings', 'osq-stress-check' ),
			array( $this, 'render_security_section' ),
			AdminMenu::MENU_SLUG . '-settings'
		);

		add_settings_field(
			'session_timeout',
			__( 'セッションタイムアウト / Session Timeout', 'osq-stress-check' ),
			array( $this, 'render_timeout_field' ),
			AdminMenu::MENU_SLUG . '-settings',
			'osq_security'
		);

		// Data section.
		add_settings_section(
			'osq_data',
			__( 'データ管理 / Data Management', 'osq-stress-check' ),
			array( $this, 'render_data_section' ),
			AdminMenu::MENU_SLUG . '-settings'
		);

		add_settings_field(
			'backup_enabled',
			__( 'バックアップ / Backup', 'osq-stress-check' ),
			array( $this, 'render_backup_field' ),
			AdminMenu::MENU_SLUG . '-settings',
			'osq_data'
		);

		// AI settings section.
		add_settings_section(
			'osq_ai',
			__( 'AI設定 / AI Settings', 'osq-stress-check' ),
			array( $this, 'render_ai_section' ),
			AdminMenu::MENU_SLUG . '-settings'
		);

		add_settings_field(
			'openai_api_key',
			__( 'OpenAI APIキー / API Key', 'osq-stress-check' ),
			array( $this, 'render_openai_key_field' ),
			AdminMenu::MENU_SLUG . '-settings',
			'osq_ai'
		);

		add_settings_field(
			'openai_model',
			__( 'OpenAI モデル / Model', 'osq-stress-check' ),
			array( $this, 'render_openai_model_field' ),
			AdminMenu::MENU_SLUG . '-settings',
			'osq_ai'
		);

		add_settings_field(
			'openai_connection_test',
			__( '接続テスト / Connection Test', 'osq-stress-check' ),
			array( $this, 'render_openai_test_field' ),
			AdminMenu::MENU_SLUG . '-settings',
			'osq_ai'
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render() {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'OSQ 設定 / OSQ Settings', 'osq-stress-check' ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( self::OPTION_GROUP );
		do_settings_sections( AdminMenu::MENU_SLUG . '-settings' );
		submit_button( __( '設定を保存 / Save Settings', 'osq-stress-check' ) );
		echo '</form>';
		echo '</div>';
	}

	/*
	|----------------------------------------------------------------------
	| Section Descriptions
	|----------------------------------------------------------------------
	*/

	public function render_general_section() {
		echo '<p>' . esc_html__( 'プラグインの基本設定です。', 'osq-stress-check' ) . '</p>';
	}

	public function render_notification_section() {
		echo '<p>' . esc_html__( 'メール通知に関する設定です。', 'osq-stress-check' ) . '</p>';
	}

	public function render_security_section() {
		echo '<p>' . esc_html__( 'セキュリティ関連の設定です。', 'osq-stress-check' ) . '</p>';
	}

	public function render_data_section() {
		echo '<p>' . esc_html__( 'データのバックアップとエクスポートの設定です。', 'osq-stress-check' ) . '</p>';
	}

	public function render_ai_section() {
		echo '<p>' . esc_html__( 'AIアドバイス機能のOpenAI API設定です。APIキーを設定するとストレスチェック完了後に個別アドバイスが生成されます。', 'osq-stress-check' ) . '</p>';
	}

	/*
	|----------------------------------------------------------------------
	| Field Renderers
	|----------------------------------------------------------------------
	*/

	public function render_language_field() {
		$options  = get_option( self::OPTION_NAME, array() );
		$language = $options['language'] ?? 'ja';
		?>
		<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[language]" id="osq_language">
			<option value="ja" <?php selected( $language, 'ja' ); ?>>日本語 (Japanese)</option>
			<option value="en" <?php selected( $language, 'en' ); ?>>English</option>
		</select>
		<?php
	}

	public function render_logo_field() {
		$options  = get_option( self::OPTION_NAME, array() );
		$logo_url = $options['company_logo'] ?? '';
		?>
		<input type="url"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[company_logo]"
			id="osq_company_logo"
			value="<?php echo esc_url( $logo_url ); ?>"
			class="regular-text"
			placeholder="https://"
		/>
		<button type="button" class="button" id="osq-upload-logo">
			<?php esc_html_e( 'メディアから選択 / Select from Media', 'osq-stress-check' ); ?>
		</button>
		<?php if ( $logo_url ) : ?>
			<br/><img src="<?php echo esc_url( $logo_url ); ?>" style="max-height: 50px; margin-top: 8px;" />
		<?php endif; ?>
		<?php
	}

	public function render_email_field() {
		$options = get_option( self::OPTION_NAME, array() );
		$enabled = $options['email_notifications'] ?? '1';
		?>
		<label>
			<input type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME ); ?>[email_notifications]"
				value="1"
				<?php checked( $enabled, '1' ); ?>
			/>
			<?php esc_html_e( 'ストレスチェック完了時にメール通知を送信する / Send email notifications on completion', 'osq-stress-check' ); ?>
		</label>
		<?php
	}

	public function render_timeout_field() {
		$options = get_option( self::OPTION_NAME, array() );
		$timeout = $options['session_timeout'] ?? 30;
		?>
		<input type="number"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[session_timeout]"
			id="osq_session_timeout"
			value="<?php echo esc_attr( $timeout ); ?>"
			min="5"
			max="120"
			step="5"
			class="small-text"
		/>
		<span><?php esc_html_e( '分 / minutes', 'osq-stress-check' ); ?></span>
		<p class="description">
			<?php esc_html_e( '無操作時のセッションタイムアウト時間 / Inactivity timeout duration', 'osq-stress-check' ); ?>
		</p>
		<?php
	}

	public function render_backup_field() {
		$options = get_option( self::OPTION_NAME, array() );
		$enabled = $options['backup_enabled'] ?? '0';
		?>
		<label>
			<input type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME ); ?>[backup_enabled]"
				value="1"
				<?php checked( $enabled, '1' ); ?>
			/>
			<?php esc_html_e( '自動バックアップを有効にする / Enable automatic backups', 'osq-stress-check' ); ?>
		</label>
		<?php
	}

	public function render_openai_key_field() {
		$options = get_option( self::OPTION_NAME, array() );
		$key     = $options['openai_api_key'] ?? '';
		$masked  = ! empty( $key ) ? str_repeat( '*', 20 ) . substr( $key, -4 ) : '';
		?>
		<input type="password"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[openai_api_key]"
			id="osq_openai_api_key"
			value="<?php echo esc_attr( $key ); ?>"
			class="regular-text"
			autocomplete="new-password"
			placeholder="sk-..."
		/>
		<?php if ( ! empty( $masked ) ) : ?>
			<p class="description"><?php echo esc_html( __( '現在のキー: ', 'osq-stress-check' ) . $masked ); ?></p>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'OpenAI APIキー（sk-... 形式）を入力してください。', 'osq-stress-check' ); ?></p>
		<?php
	}

	public function render_openai_model_field() {
		$options = get_option( self::OPTION_NAME, array() );
		$model   = $options['openai_model'] ?? 'gpt-4o';
		$models  = array(
			'gpt-4o'      => 'GPT-4o（推奨）',
			'gpt-4o-mini' => 'GPT-4o mini（低コスト）',
			'gpt-4-turbo' => 'GPT-4 Turbo',
		);
		?>
		<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[openai_model]" id="osq_openai_model">
			<?php foreach ( $models as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $model, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public function render_openai_test_field() {
		?>
		<button type="button" class="button" id="osq-test-openai-connection">
			<?php esc_html_e( '接続テスト実行 / Run Connection Test', 'osq-stress-check' ); ?>
		</button>
		<span id="osq-openai-test-result" style="margin-left: 12px;"></span>
		<script>
		document.getElementById('osq-test-openai-connection').addEventListener('click', function() {
			var btn    = this;
			var result = document.getElementById('osq-openai-test-result');
			btn.disabled = true;
			result.textContent = '<?php echo esc_js( __( 'テスト中...', 'osq-stress-check' ) ); ?>';
			fetch(ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({
					action: 'osq_test_openai_connection',
					nonce: '<?php echo esc_js( wp_create_nonce( 'osq_admin_nonce' ) ); ?>'
				})
			})
			.then(r => r.json())
			.then(data => {
				result.textContent = data.success ? '✅ ' + data.data.message : '❌ ' + (data.data?.message || 'Error');
				result.style.color = data.success ? '#1e7e34' : '#d63638';
			})
			.catch(() => { result.textContent = '❌ Network error'; result.style.color = '#d63638'; })
			.finally(() => { btn.disabled = false; });
		});
		</script>
		<?php
	}

	/*
	|----------------------------------------------------------------------
	| Sanitization
	|----------------------------------------------------------------------
	*/

	/**
	 * Sanitize settings before saving.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized values.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		$sanitized['language'] = in_array( $input['language'] ?? '', array( 'ja', 'en' ), true )
			? $input['language']
			: 'ja';

		$sanitized['company_logo'] = esc_url_raw( $input['company_logo'] ?? '' );

		$sanitized['email_notifications'] = ! empty( $input['email_notifications'] ) ? '1' : '0';

		$timeout = absint( $input['session_timeout'] ?? 30 );
		$sanitized['session_timeout'] = max( 5, min( 120, $timeout ) );

		$sanitized['backup_enabled'] = ! empty( $input['backup_enabled'] ) ? '1' : '0';

		// AI settings — preserve existing key if field is blank (masked display).
		$existing        = get_option( self::OPTION_NAME, array() );
		$submitted_key   = trim( $input['openai_api_key'] ?? '' );
		$sanitized['openai_api_key'] = ! empty( $submitted_key )
			? sanitize_text_field( $submitted_key )
			: ( $existing['openai_api_key'] ?? '' );

		$allowed_models = array( 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo' );
		$sanitized['openai_model'] = in_array( $input['openai_model'] ?? '', $allowed_models, true )
			? $input['openai_model']
			: 'gpt-4o';

		return $sanitized;
	}
}
