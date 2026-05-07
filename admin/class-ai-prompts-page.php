<?php
/**
 * AI Prompts admin page — lists and edits the 15 industry-specific OpenAI prompts.
 *
 * @package OSQ
 */

namespace OSQ\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AiPromptsPage
 *
 * Renders the "業種別AIプロンプト管理 / Industry AI Prompts" WP admin page.
 * Prompts are stored in osq_ai_prompts and editable here without touching code.
 *
 * Save flow: standard WP form POST → admin-post.php → handle_save() → redirect.
 */
class AiPromptsPage {

	/**
	 * The 3 absolute principles text (read-only display; injected by PromptManager::build_system_prompt()).
	 */
	const PRINCIPLES_TEXT = <<<'EOT'
【絶対遵守の3原則】
① 定型・汎用アドバイスの禁止: 「ゆっくり休みましょう」「深呼吸を」等、どの業種にも当てはまる表面的な回答は厳禁。業種×属性から導き出される「明日の現場で実行できる具体的なアクション」を1〜2つ提示すること。
② 過剰なAI敬語の排除: 「誠に恐縮ながら〜」「お辛いとは存じますが〜」などの遜った言葉遣いは禁止。産業カウンセラーとしての専門性を感じさせる「簡潔で、芯のある、対等なプロの言葉」で話すこと。
③ 「人生の文脈」への問いかけ: 属性から推測されるユーザーの心の裏側に触れる問いかけを必ず1つ含めること。例：「〇〇年現場を支えてこられたからこその重圧ではありませんか？」
EOT;

	/**
	 * Register the admin-post action for saving prompts.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_post_osq_save_ai_prompt', array( $this, 'handle_save' ) );
	}

	/**
	 * Main render entry point — delegates to list or edit view.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'osq_system_config' ) ) {
			wp_die( esc_html__( 'Access denied.', 'osq-stress-check' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$industry = isset( $_GET['industry'] ) ? absint( $_GET['industry'] ) : 0;

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( '業種別AIプロンプト管理 / Industry AI Prompts', 'osq-stress-check' ) . '</h1>';

		// Admin notice after save.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			esc_html_e( 'プロンプトを保存しました。/ Prompt saved successfully.', 'osq-stress-check' );
			echo '</p></div>';
		}

		if ( $industry >= 1 && $industry <= 15 ) {
			$this->render_edit( $industry );
		} else {
			$this->render_list();
		}

		echo '</div>';
	}

	/**
	 * Render the table listing all 15 industries.
	 *
	 * @return void
	 */
	public function render_list() {
		// Build a lookup of DB rows keyed by industry_type.
		$db_rows = \OSQ\AI\PromptManager::get_all_prompts();
		$db_map  = array();
		foreach ( $db_rows as $row ) {
			$db_map[ (int) $row->industry_type ] = $row;
		}

		$defaults     = \OSQ\AI\PromptManager::get_default_industry_prompts();
		$page_url     = admin_url( 'admin.php?page=' . AdminMenu::MENU_SLUG . '-ai-prompts' );
		?>
		<p><?php esc_html_e( 'プロンプトはOpenAI APIに送信され、AIアドバイスの文体・視点を制御します。「編集」をクリックして各業種のプロンプトを編集できます。', 'osq-stress-check' ); ?></p>

		<table class="wp-list-table widefat fixed striped" style="margin-top: 16px;">
			<thead>
				<tr>
					<th style="width:40px;">#</th>
					<th><?php esc_html_e( '業種 / Industry', 'osq-stress-check' ); ?></th>
					<th><?php esc_html_e( 'ソース / Source', 'osq-stress-check' ); ?></th>
					<th><?php esc_html_e( '最終更新 / Last Updated', 'osq-stress-check' ); ?></th>
					<th style="width:80px;"><?php esc_html_e( '操作', 'osq-stress-check' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $defaults as $default ) :
				$type    = (int) $default['industry_type'];
				$db_row  = $db_map[ $type ] ?? null;
				$source  = $db_row ? __( 'DB（カスタム）', 'osq-stress-check' ) : __( 'デフォルト', 'osq-stress-check' );
				$updated = $db_row ? esc_html( $db_row->updated_at ) : '—';
				$edit_url = add_query_arg( 'industry', $type, $page_url );
			?>
				<tr>
					<td><?php echo esc_html( $type ); ?></td>
					<td><?php echo esc_html( $default['industry_label'] ); ?></td>
					<td><?php echo esc_html( $source ); ?></td>
					<td><?php echo esc_html( $updated ); ?></td>
					<td>
						<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">
							<?php esc_html_e( '編集', 'osq-stress-check' ); ?>
						</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render the edit form for a specific industry.
	 *
	 * @param int $industry_type Industry type (1–15).
	 * @return void
	 */
	public function render_edit( $industry_type ) {
		// Fetch the current values: DB row first, then static default.
		$current = \OSQ\AI\PromptManager::get_prompt( $industry_type );

		// Also fetch the static default so the "Reset to Default" button can use it.
		$defaults = \OSQ\AI\PromptManager::get_default_industry_prompts();
		$static   = array();
		foreach ( $defaults as $d ) {
			if ( (int) $d['industry_type'] === $industry_type ) {
				$static = $d;
				break;
			}
		}

		$list_url = admin_url( 'admin.php?page=' . AdminMenu::MENU_SLUG . '-ai-prompts' );
		?>
		<h2 style="margin-top: 12px;">
			<?php echo esc_html( $current['industry_label'] ); ?>
			<small style="font-size: 13px; font-weight: normal; color: #666;">
				(industry_type = <?php echo esc_html( $industry_type ); ?>)
			</small>
		</h2>

		<?php /* Collapsed read-only box showing the 3 auto-injected principles */ ?>
		<details style="margin: 12px 0; border: 1px solid #ddd; padding: 10px 14px; background: #f9f9f9; border-radius: 3px;">
			<summary style="cursor: pointer; font-weight: 600; color: #555;">
				<?php esc_html_e( '【参考】自動注入される3原則（編集不可） / Auto-injected principles (read-only)', 'osq-stress-check' ); ?>
			</summary>
			<pre style="margin-top: 10px; font-size: 12px; white-space: pre-wrap; color: #444; background: #fff; padding: 10px; border: 1px solid #e0e0e0;"><?php echo esc_html( self::PRINCIPLES_TEXT ); ?></pre>
			<p class="description" style="margin-top: 6px;">
				<?php esc_html_e( '上記3原則は PromptManager::build_system_prompt() によって自動付加されます。ここで編集するプロンプトとは別に、常にAPIへ送信されます。', 'osq-stress-check' ); ?>
			</p>
		</details>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'osq_save_ai_prompt', 'osq_nonce' ); ?>
			<input type="hidden" name="action" value="osq_save_ai_prompt" />
			<input type="hidden" name="industry_type" value="<?php echo esc_attr( $industry_type ); ?>" />

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="osq_system_prompt">
							<?php esc_html_e( 'システムプロンプト / System Prompt', 'osq-stress-check' ); ?>
						</label>
					</th>
					<td>
						<textarea
							id="osq_system_prompt"
							name="system_prompt"
							rows="15"
							class="large-text"
							style="font-family: monospace; font-size: 13px;"
						><?php echo esc_textarea( $current['system_prompt'] ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'AIに送信される業種別の基本プロンプトです。上記の3原則はこの後に自動付加されます。', 'osq-stress-check' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="osq_background_memo">
							<?php esc_html_e( '管理メモ（AIには送信されません）/ Admin Memo (not sent to AI)', 'osq-stress-check' ); ?>
						</label>
					</th>
					<td>
						<textarea
							id="osq_background_memo"
							name="background_memo"
							rows="5"
							class="large-text"
							style="font-family: monospace; font-size: 13px;"
						><?php echo esc_textarea( $current['background_memo'] ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'このメモはAIへは送信されません。プロンプト設計の意図や注意事項を記録するための管理用フィールドです。', 'osq-stress-check' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p>
				<?php submit_button( __( '保存 / Save', 'osq-stress-check' ), 'primary', 'submit', false ); ?>
				&nbsp;
				<a href="<?php echo esc_url( $list_url ); ?>" class="button">
					<?php esc_html_e( '← 一覧に戻る / Back to List', 'osq-stress-check' ); ?>
				</a>
				&nbsp;
				<button type="button" class="button" id="osq-reset-default">
					<?php esc_html_e( 'デフォルトに戻す / Reset to Default', 'osq-stress-check' ); ?>
				</button>
			</p>
		</form>

		<script>
		(function() {
			var defaultPrompt = <?php echo wp_json_encode( $static['system_prompt'] ?? '' ); ?>;
			var defaultMemo   = <?php echo wp_json_encode( $static['background_memo'] ?? '' ); ?>;

			document.getElementById('osq-reset-default').addEventListener('click', function() {
				if ( ! confirm('<?php echo esc_js( __( 'デフォルト値に戻しますか？保存するまで変更は確定しません。/ Reset to default? Changes are not saved until you click Save.', 'osq-stress-check' ) ); ?>') ) {
					return;
				}
				document.getElementById('osq_system_prompt').value   = defaultPrompt;
				document.getElementById('osq_background_memo').value = defaultMemo;
			});
		})();
		</script>
		<?php
	}

	/**
	 * Handle the POST save from admin-post.php.
	 *
	 * Validates nonce + capability, calls PromptManager::update_prompt(), then redirects.
	 *
	 * @return void
	 */
	public function handle_save() {
		// Capability check.
		if ( ! current_user_can( 'osq_system_config' ) ) {
			wp_die( esc_html__( 'Access denied.', 'osq-stress-check' ) );
		}

		// Nonce check.
		check_admin_referer( 'osq_save_ai_prompt', 'osq_nonce' );

		$industry_type   = isset( $_POST['industry_type'] ) ? absint( $_POST['industry_type'] ) : 0;
		$system_prompt   = isset( $_POST['system_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['system_prompt'] ) ) : '';
		$background_memo = isset( $_POST['background_memo'] ) ? sanitize_textarea_field( wp_unslash( $_POST['background_memo'] ) ) : '';

		if ( $industry_type < 1 || $industry_type > 15 ) {
			wp_die( esc_html__( '無効な業種タイプです。/ Invalid industry type.', 'osq-stress-check' ) );
		}

		\OSQ\AI\PromptManager::update_prompt( $industry_type, $system_prompt, $background_memo );

		$redirect = add_query_arg(
			array(
				'page'    => AdminMenu::MENU_SLUG . '-ai-prompts',
				'saved'   => '1',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
