<?php
/**
 * NGWord admin page — lists, adds, toggles, and deletes prohibited words.
 *
 * @package OSQ
 */

namespace OSQ\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NgwordPage
 *
 * Renders the "NGワード管理 / NG Word Management" WP admin page.
 * All mutating operations are handled via AJAX (toggle, delete, add).
 */
class NgwordPage {

	/**
	 * Register the 3 AJAX actions (logged-in users only).
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_ajax_osq_ngword_toggle', array( $this, 'ajax_toggle' ) );
		add_action( 'wp_ajax_osq_ngword_delete', array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_osq_ngword_add',    array( $this, 'ajax_add' ) );
	}

	/**
	 * Render the full page: table of current NGwords + add-new form + inline JS.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'osq_system_config' ) ) {
			wp_die( esc_html__( 'Access denied.', 'osq-stress-check' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . \OSQ\Database\Schema::AI_NGWORDS;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at ASC" );
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$nonce = wp_create_nonce( 'osq_admin_nonce' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'NGワード管理 / NG Word Management', 'osq-stress-check' ); ?></h1>

			<p>
				<?php esc_html_e( 'AIが生成するアドバイスに含まれてはならないワード・フレーズを管理します。NGワードが検出された場合、AIは1回再生成を試み、それでも含まれる場合はセーフフォールバックメッセージが表示されます。', 'osq-stress-check' ); ?>
			</p>

			<?php /* NGword table */ ?>
			<table class="wp-list-table widefat fixed striped" id="osq-ngword-table" style="margin-top: 16px;">
				<thead>
					<tr>
						<th style="width:50px;">ID</th>
						<th><?php esc_html_e( 'ワード / Word', 'osq-stress-check' ); ?></th>
						<th><?php esc_html_e( '理由 / Reason', 'osq-stress-check' ); ?></th>
						<th style="width:90px;"><?php esc_html_e( '有効 / Active', 'osq-stress-check' ); ?></th>
						<th style="width:80px;"><?php esc_html_e( '登録日 / Added', 'osq-stress-check' ); ?></th>
						<th style="width:70px;"><?php esc_html_e( '操作', 'osq-stress-check' ); ?></th>
					</tr>
				</thead>
				<tbody id="osq-ngword-tbody">
				<?php if ( empty( $rows ) ) : ?>
					<tr id="osq-empty-row">
						<td colspan="6" style="text-align:center; color:#888;">
							<?php esc_html_e( 'NGワードが登録されていません。/ No NG words registered.', 'osq-stress-check' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
					<?php echo $this->build_row_html( $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<?php /* Add-new form */ ?>
			<h2 style="margin-top: 28px;"><?php esc_html_e( '新しいNGワードを追加 / Add New NG Word', 'osq-stress-check' ); ?></h2>
			<table class="form-table" role="presentation" style="max-width: 640px;">
				<tr>
					<th scope="row">
						<label for="osq-new-word"><?php esc_html_e( 'ワード / Word', 'osq-stress-check' ); ?></label>
					</th>
					<td>
						<input type="text" id="osq-new-word" class="regular-text" maxlength="255"
							placeholder="<?php esc_attr_e( '例: 死にたい', 'osq-stress-check' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="osq-new-reason"><?php esc_html_e( '理由 / Reason', 'osq-stress-check' ); ?></label>
					</th>
					<td>
						<input type="text" id="osq-new-reason" class="regular-text" maxlength="255"
							placeholder="<?php esc_attr_e( '例: 自傷・自殺念慮の誘発リスク', 'osq-stress-check' ); ?>" />
						<p class="description">
							<?php esc_html_e( '任意。管理者向けの説明メモです。', 'osq-stress-check' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<p>
				<button type="button" class="button button-primary" id="osq-add-ngword">
					<?php esc_html_e( '追加 / Add', 'osq-stress-check' ); ?>
				</button>
				<span id="osq-add-result" style="margin-left: 12px; font-style: italic; color: #666;"></span>
			</p>
		</div><!-- .wrap -->

		<script>
		(function($) {
			var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce   = <?php echo wp_json_encode( $nonce ); ?>;

			/* ── Toggle active state ── */
			$(document).on('click', '.osq-toggle-btn', function() {
				var btn      = $(this);
				var wordId   = btn.closest('tr').data('word-id');
				var isActive = parseInt(btn.data('active'), 10);

				btn.prop('disabled', true);

				$.post(ajaxUrl, {
					action:   'osq_ngword_toggle',
					nonce:    nonce,
					word_id:  wordId
				}, function(response) {
					if (response.success) {
						var newActive = response.data.is_active;
						btn.data('active', newActive);
						btn.text(newActive ? <?php echo wp_json_encode( __( '有効', 'osq-stress-check' ) ); ?> : <?php echo wp_json_encode( __( '無効', 'osq-stress-check' ) ); ?>);
						btn.toggleClass('button-primary', newActive == 1);
						var badge = btn.closest('tr').find('.osq-active-badge');
						badge.text(newActive ? <?php echo wp_json_encode( __( '有効', 'osq-stress-check' ) ); ?> : <?php echo wp_json_encode( __( '無効', 'osq-stress-check' ) ); ?>);
						badge.css('color', newActive ? '#1e7e34' : '#888');
					} else {
						alert(response.data || <?php echo wp_json_encode( __( 'エラーが発生しました。', 'osq-stress-check' ) ); ?>);
					}
				}).fail(function() {
					alert(<?php echo wp_json_encode( __( 'ネットワークエラーが発生しました。', 'osq-stress-check' ) ); ?>);
				}).always(function() {
					btn.prop('disabled', false);
				});
			});

			/* ── Delete word ── */
			$(document).on('click', '.osq-delete-btn', function() {
				if ( ! confirm(<?php echo wp_json_encode( __( 'このNGワードを削除しますか？/ Delete this NG word?', 'osq-stress-check' ) ); ?>) ) {
					return;
				}
				var btn    = $(this);
				var row    = btn.closest('tr');
				var wordId = row.data('word-id');

				btn.prop('disabled', true);

				$.post(ajaxUrl, {
					action:  'osq_ngword_delete',
					nonce:   nonce,
					word_id: wordId
				}, function(response) {
					if (response.success) {
						row.fadeOut(300, function() {
							$(this).remove();
							if ($('#osq-ngword-tbody tr').length === 0) {
								$('#osq-ngword-tbody').html(
									'<tr id="osq-empty-row"><td colspan="6" style="text-align:center;color:#888;">' +
									<?php echo wp_json_encode( __( 'NGワードが登録されていません。/ No NG words registered.', 'osq-stress-check' ) ); ?> +
									'</td></tr>'
								);
							}
						});
					} else {
						alert(response.data || <?php echo wp_json_encode( __( 'エラーが発生しました。', 'osq-stress-check' ) ); ?>);
						btn.prop('disabled', false);
					}
				}).fail(function() {
					alert(<?php echo wp_json_encode( __( 'ネットワークエラーが発生しました。', 'osq-stress-check' ) ); ?>);
					btn.prop('disabled', false);
				});
			});

			/* ── Add new word ── */
			$('#osq-add-ngword').on('click', function() {
				var btn    = $(this);
				var word   = $.trim($('#osq-new-word').val());
				var reason = $.trim($('#osq-new-reason').val());
				var result = $('#osq-add-result');

				if ( ! word ) {
					result.text(<?php echo wp_json_encode( __( 'ワードを入力してください。/ Please enter a word.', 'osq-stress-check' ) ); ?>).css('color', '#d63638');
					return;
				}

				btn.prop('disabled', true);
				result.text(<?php echo wp_json_encode( __( '追加中... / Adding...', 'osq-stress-check' ) ); ?>).css('color', '#666');

				$.post(ajaxUrl, {
					action: 'osq_ngword_add',
					nonce:  nonce,
					word:   word,
					reason: reason
				}, function(response) {
					if (response.success) {
						$('#osq-empty-row').remove();
						$('#osq-ngword-tbody').append(response.data.row_html);
						$('#osq-new-word').val('');
						$('#osq-new-reason').val('');
						result.text(<?php echo wp_json_encode( __( '追加しました。/ Added.', 'osq-stress-check' ) ); ?>).css('color', '#1e7e34');
					} else {
						result.text(response.data || <?php echo wp_json_encode( __( 'エラーが発生しました。', 'osq-stress-check' ) ); ?>).css('color', '#d63638');
					}
				}).fail(function() {
					result.text(<?php echo wp_json_encode( __( 'ネットワークエラーが発生しました。', 'osq-stress-check' ) ); ?>).css('color', '#d63638');
				}).always(function() {
					btn.prop('disabled', false);
				});
			});

			/* Allow Enter key in word/reason fields to trigger add */
			$('#osq-new-word, #osq-new-reason').on('keydown', function(e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					$('#osq-add-ngword').trigger('click');
				}
			});

		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Build the HTML for a single table row.
	 * Used both on initial render and when returning new rows via AJAX.
	 *
	 * @param object $row DB row with ngword_id, word, reason, is_active, created_at.
	 * @return string Escaped HTML.
	 */
	private function build_row_html( $row ) {
		$word_id      = (int) $row->ngword_id;
		$word         = esc_html( $row->word );
		$reason       = esc_html( $row->reason ?? '' );
		$is_active    = (int) $row->is_active;
		$created      = esc_html( substr( $row->created_at, 0, 10 ) );
		$active_label = $is_active
			? esc_html__( '有効', 'osq-stress-check' )
			: esc_html__( '無効', 'osq-stress-check' );
		$active_color = $is_active ? '#1e7e34' : '#888';
		$toggle_class = $is_active ? 'button button-primary osq-toggle-btn' : 'button osq-toggle-btn';
		$toggle_label = $active_label;

		return sprintf(
			'<tr data-word-id="%1$d">
				<td>%1$d</td>
				<td><strong>%2$s</strong></td>
				<td>%3$s</td>
				<td><span class="osq-active-badge" style="color:%5$s">%4$s</span></td>
				<td>%6$s</td>
				<td>
					<button type="button" class="%7$s" data-active="%8$d" style="margin-bottom:4px;">%9$s</button>
					<button type="button" class="button osq-delete-btn" style="color:#d63638;">%10$s</button>
				</td>
			</tr>',
			$word_id,
			$word,
			$reason,
			$active_label,
			$active_color,
			$created,
			$toggle_class,
			$is_active,
			$toggle_label,
			esc_html__( '削除', 'osq-stress-check' )
		);
	}

	/**
	 * AJAX: Toggle is_active for a word.
	 *
	 * @return void
	 */
	public function ajax_toggle() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'osq_system_config' ) ) {
			wp_send_json_error( __( 'Access denied.', 'osq-stress-check' ) );
		}

		$word_id = isset( $_POST['word_id'] ) ? absint( $_POST['word_id'] ) : 0;
		if ( ! $word_id ) {
			wp_send_json_error( __( '無効なIDです。/ Invalid ID.', 'osq-stress-check' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . \OSQ\Database\Schema::AI_NGWORDS;

		$current = $wpdb->get_var( $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT is_active FROM {$table} WHERE ngword_id = %d",
			$word_id
		) );

		if ( null === $current ) {
			wp_send_json_error( __( '該当するワードが見つかりません。/ Word not found.', 'osq-stress-check' ) );
		}

		$new_active = $current ? 0 : 1;
		$wpdb->update(
			$table,
			array( 'is_active' => $new_active ),
			array( 'ngword_id' => $word_id )
		);

		wp_send_json_success( array( 'is_active' => $new_active ) );
	}

	/**
	 * AJAX: Delete a word by ngword_id.
	 *
	 * @return void
	 */
	public function ajax_delete() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'osq_system_config' ) ) {
			wp_send_json_error( __( 'Access denied.', 'osq-stress-check' ) );
		}

		$word_id = isset( $_POST['word_id'] ) ? absint( $_POST['word_id'] ) : 0;
		if ( ! $word_id ) {
			wp_send_json_error( __( '無効なIDです。/ Invalid ID.', 'osq-stress-check' ) );
		}

		global $wpdb;
		$table  = $wpdb->prefix . \OSQ\Database\Schema::AI_NGWORDS;
		$result = $wpdb->delete( $table, array( 'ngword_id' => $word_id ) );

		if ( false === $result ) {
			wp_send_json_error( __( '削除に失敗しました。/ Delete failed.', 'osq-stress-check' ) );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Insert a new NGword row, return the rendered row HTML.
	 *
	 * @return void
	 */
	public function ajax_add() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'osq_system_config' ) ) {
			wp_send_json_error( __( 'Access denied.', 'osq-stress-check' ) );
		}

		$word   = isset( $_POST['word'] )   ? sanitize_text_field( wp_unslash( $_POST['word'] ) )   : '';
		$reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';

		if ( empty( $word ) ) {
			wp_send_json_error( __( 'ワードを入力してください。/ Word is required.', 'osq-stress-check' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . \OSQ\Database\Schema::AI_NGWORDS;

		// Check for duplicate.
		$exists = $wpdb->get_var( $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT ngword_id FROM {$table} WHERE word = %s",
			$word
		) );
		if ( $exists ) {
			wp_send_json_error( __( 'そのワードはすでに登録されています。/ This word already exists.', 'osq-stress-check' ) );
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'word'      => $word,
				'reason'    => $reason,
				'is_active' => 1,
			)
		);

		if ( ! $inserted ) {
			wp_send_json_error( __( '追加に失敗しました。/ Insert failed.', 'osq-stress-check' ) );
		}

		$new_id  = $wpdb->insert_id;
		$new_row = $wpdb->get_row( $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT * FROM {$table} WHERE ngword_id = %d",
			$new_id
		) );

		$row_html = $this->build_row_html( $new_row );

		wp_send_json_success( array( 'row_html' => $row_html ) );
	}
}
