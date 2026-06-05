<?php
/**
 * Employee Dashboard Template.
 *
 * @package OSQ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id  = get_current_user_id();
$db       = \OSQ\Plugin::get_instance()->db();
$employee = $db->get_employee_by_user_id( $user_id );
$response = $db->get_response_by_employee( $employee->employee_id ?? 0 );

$status = 'not_started';
if ( $response ) {
	$status = $response->is_complete ? 'completed' : 'in_progress';
}

$must_change_password = get_user_meta( $user_id, 'osq_must_change_password', true );

// Fetch HR contact info from the employee's company (shown to high-stress employees).
$hr_contact = null;
if ( isset( $employee->company_id ) && $employee->company_id ) {
	$hr_contact = $wpdb->get_row( $wpdb->prepare(
		"SELECT contact_name, contact_phone, contact_email FROM {$wpdb->prefix}osq_companies WHERE company_id = %d",
		(int) $employee->company_id
	) );
}

// Define dynamic advice based on stress levels
$advice_title = '';
$advice_text  = '';

if ( $response && $response->is_complete ) {
	$is_high_stress = (bool) ( $response->is_high_stress_method1 || $response->is_high_stress_method2 );
	if ( $is_high_stress ) {
		$advice_title = __( '高ストレス検出', 'osq-stress-check' );
		$advice_text  = __( '結果に高いストレスが検出されました。結果を確認し、産業医への相談をご検討ください。', 'osq-stress-check' );
	} else {
		$advice_title = __( 'ストレス正常', 'osq-stress-check' );
		$advice_text  = __( '現時点では高いストレスは検出されていません。引き続き健康管理に努め、ワークライフバランスを保ってください。', 'osq-stress-check' );
	}

	$method1_data = maybe_unserialize( $response->method1_result ?? '' );
	$method2_data = maybe_unserialize( $response->method2_result ?? '' );

	// Ensure they are arrays
	if ( ! is_array( $method1_data ) ) {
		$method1_data = json_decode( $method1_data, true ) ?: array();
	}
	if ( ! is_array( $method2_data ) ) {
		$method2_data = json_decode( $method2_data, true ) ?: array();
	}
}

// Fetch previous year result for YoY radar chart.
$prev_year_result      = null;
$prev_year_eval_points = array();
if ( $response && $response->is_complete && $employee ) {
	$prev_year_result = $db->get_previous_year_result( $employee->employee_id );
	if ( $prev_year_result ) {
		$pyr_m2 = maybe_unserialize( $prev_year_result->method2_result ?? '' );
		if ( ! is_array( $pyr_m2 ) ) {
			$pyr_m2 = json_decode( $pyr_m2, true ) ?: array();
		}
		$prev_year_eval_points = $pyr_m2['eval_points'] ?? array();
	}
}

// Fetch the most recent follow-up record for this employee
global $wpdb;
$follow_up_table = $wpdb->prefix . \OSQ\Database\Schema::FOLLOW_UP_TRACKING;
$latest_follow_up = null;
if ( $employee && isset( $employee->employee_id ) ) {
	$latest_follow_up = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$follow_up_table} WHERE employee_id = %d ORDER BY created_at DESC LIMIT 1",
		$employee->employee_id
	) );
}

// Fetch or enqueue AI advice for completed responses.
$ai_advice    = null;
$ai_job_status = null;
if ( $response && $response->is_complete && $employee ) {
	$ai_generator = new \OSQ\AI\AdviceGenerator();
	$ai_advice    = $ai_generator->get_cached( $response->response_id );
	if ( ! $ai_advice ) {
		$ai_generator->enqueue( $employee->employee_id, $response->response_id );
		$ai_job_status = $ai_generator->get_job_status( $employee->employee_id );
	}
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>


<div id="osq-employee-dashboard" class="osq-ui-container osq-admin-dashboard osq-employee-theme">
	<?php \OSQ\Auth\NavigationBuilder::render_sidebar( 'my_check' ); ?>

	<main class="osq-admin-main">
		<header class="osq-admin-header">
			<div class="osq-header-left">
				<h2 id="osq-tab-title"><?php echo $must_change_password ? esc_html__( 'プロフィール', 'osq-stress-check' ) : esc_html__( 'ダッシュボード', 'osq-stress-check' ); ?></h2>
			</div>
			<div class="osq-header-right">
				<span class="osq-user-welcome"><?php printf( esc_html__( 'ようこそ、%s さん', 'osq-stress-check' ), esc_html( $employee->name ?? '' ) ); ?></span>
			</div>
		</header>

		<nav class="osq-inner-tab-nav">
			<ul>
				<?php if ( ! $must_change_password ) : ?>
				<li class="active" data-tab="dashboard">
					<span class="dashicons dashicons-dashboard"></span>
					<span><?php esc_html_e( 'ダッシュボード', 'osq-stress-check' ); ?></span>
				</li>
				<?php endif; ?>
				<li class="<?php echo $must_change_password ? 'active' : ''; ?>" data-tab="profile">
					<span class="dashicons dashicons-admin-users"></span>
					<span><?php esc_html_e( 'プロフィール', 'osq-stress-check' ); ?></span>
				</li>
				<?php if ( ! $must_change_password ) : ?>
				<li data-tab="settings">
					<span class="dashicons dashicons-admin-settings"></span>
					<span><?php esc_html_e( '設定', 'osq-stress-check' ); ?></span>
				</li>
				<?php endif; ?>
			</ul>
		</nav>

		<div class="osq-admin-content">
			<!-- Dashboard Main Tab -->
			<section id="tab-dashboard" class="osq-tab-panel <?php echo $must_change_password ? '' : 'active'; ?>" <?php echo $must_change_password ? 'style="display: none;"' : ''; ?>>
				<div class="osq-greeting-hero">
					<h3><?php esc_html_e( 'ストレスチェック状況', 'osq-stress-check' ); ?></h3>
					<p><?php esc_html_e( 'ポータルへようこそ。健康状態の確認にご活用ください。', 'osq-stress-check' ); ?></p>
				</div>
				
				<div class="osq-stats-grid">
					<div class="osq-stat-card">
						<h3><?php esc_html_e( '現在の状況', 'osq-stress-check' ); ?></h3>
						<div class="osq-status-pill osq-status-pill--<?php echo esc_attr( $status ); ?>">
							<?php
							switch ( $status ) {
								case 'completed':
									esc_html_e( '完了', 'osq-stress-check' );
									break;
								case 'in_progress':
									esc_html_e( '回答中', 'osq-stress-check' );
									break;
								default:
									esc_html_e( '未開始', 'osq-stress-check' );
							}
							?>
						</div>
					</div>
				</div>

				<?php if ( $latest_follow_up ) : ?>
					<?php
					$fu_status = $latest_follow_up->status ?? '';
					$fu_badge_class = 'osq-status-badge--' . strtolower( $fu_status );
					$fu_message = '';
					$fu_badge_text = '';
					
					if ( 'Scheduled' === $fu_status ) {
						$fu_message = __( 'フォローアップ面談が予定されています。', 'osq-stress-check' );
						$fu_badge_text = __( '予定済み', 'osq-stress-check' );
					} elseif ( 'Completed' === $fu_status ) {
						$fu_message = __( 'フォローアップ面談が完了しました。ありがとうございます。', 'osq-stress-check' );
						$fu_badge_text = __( '完了', 'osq-stress-check' );
					} elseif ( 'Cancelled' === $fu_status ) {
						$fu_message = __( '予定されていたフォローアップ面談がキャンセルされました。', 'osq-stress-check' );
						$fu_badge_text = __( 'キャンセル', 'osq-stress-check' );
					} else {
						$fu_message = __( 'フォローアップは現在確認待ちです。', 'osq-stress-check' );
						$fu_badge_text = __( '保留中', 'osq-stress-check' );
						$fu_badge_class = 'osq-status-badge--pending';
					}
					?>
					<div class="osq-panel-card osq-follow-up-card">
						<div class="osq-card-header">
							<h3 class="osq-card-title">
								<span class="dashicons dashicons-testimonial"></span>
								<?php esc_html_e( '人事・産業保健からのメッセージ', 'osq-stress-check' ); ?>
							</h3>
							<span class="osq-status-pill <?php echo esc_attr( $fu_badge_class ); ?>"><?php echo esc_html( $fu_badge_text ); ?></span>
						</div>
						
						<p class="osq-follow-up-intro">
							<?php echo esc_html( $fu_message ); ?>
						</p>
						
						<?php if ( 'Scheduled' === $fu_status && ! empty( $latest_follow_up->scheduled_date ) ) : ?>
							<div class="osq-scheduled-date-box">
								<span class="dashicons dashicons-calendar-alt"></span>
								<?php printf( esc_html__( '面談予定日: %s', 'osq-stress-check' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $latest_follow_up->scheduled_date ) ) ); ?>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $latest_follow_up->notes ) ) : ?>
							<div class="osq-follow-up-notes">
								<?php echo nl2br( esc_html( $latest_follow_up->notes ) ); ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="osq-panel-card">
					<?php if ( 'completed' === $status ) : ?>
						<div class="osq-result-display" style="margin-bottom: 30px;">
							<h4 style="font-size: 18px; margin-top: 0; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">
								<span class="dashicons dashicons-clipboard" style="vertical-align: middle;"></span> <?php esc_html_e( '総合判定', 'osq-stress-check' ); ?>
							</h4>
							<div class="osq-result-alert osq-result-alert--<?php echo $is_high_stress ? 'high' : 'normal'; ?>">
								<span class="dashicons dashicons-<?php echo $is_high_stress ? 'warning' : 'id-alt'; ?>"></span>
								<div class="osq-result-text">
									<strong><?php echo esc_html( $advice_title ); ?></strong>
									<p><?php echo esc_html( $advice_text ); ?></p>
								</div>
							</div>
						</div>
						
						<div class="osq-score-explanation">
							<h4 class="osq-section-header">
								<span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( 'スコア詳細', 'osq-stress-check' ); ?>
							</h4>
							<div class="osq-score-details-grid">
								<!-- Method 1 -->
								<div class="osq-score-method-card">
									<h5><?php esc_html_e( '判定方法1（合計点数）', 'osq-stress-check' ); ?></h5>
									<div class="osq-score-row">
										<span><?php esc_html_e( 'セクションA', 'osq-stress-check' ); ?></span>
										<strong><?php echo esc_html( $method1_data['section_a_total'] ?? '-' ); ?></strong>
									</div>
									<div class="osq-score-row">
										<span><?php esc_html_e( 'セクションB', 'osq-stress-check' ); ?></span>
										<strong><?php echo esc_html( $method1_data['section_b_total'] ?? '-' ); ?></strong>
									</div>
									<div class="osq-score-row osq-score-row--last">
										<span><?php esc_html_e( 'セクションC', 'osq-stress-check' ); ?></span>
										<strong><?php echo esc_html( $method1_data['section_c_total'] ?? '-' ); ?></strong>
									</div>
									<div class="osq-score-footer">
										<span class="osq-status-pill <?php echo $response->is_high_stress_method1 ? 'osq-status-badge--cancelled' : 'osq-status-badge--completed'; ?>">
											<?php echo $response->is_high_stress_method1 ? esc_html__( '高ストレス', 'osq-stress-check' ) : esc_html__( '正常', 'osq-stress-check' ); ?>
										</span>
									</div>
								</div>
								
								<!-- Method 2 -->
								<div class="osq-score-method-card">
									<h5><?php esc_html_e( '判定方法2（各尺度評価）', 'osq-stress-check' ); ?></h5>
									<div class="osq-score-row">
										<span><?php esc_html_e( 'セクションA', 'osq-stress-check' ); ?></span>
										<strong><?php echo esc_html( $method2_data['section_a_eval'] ?? '-' ); ?></strong>
									</div>
									<div class="osq-score-row">
										<span><?php esc_html_e( 'セクションB', 'osq-stress-check' ); ?></span>
										<strong><?php echo esc_html( $method2_data['section_b_eval'] ?? '-' ); ?></strong>
									</div>
									<div class="osq-score-row osq-score-row--last">
										<span><?php esc_html_e( 'セクションC', 'osq-stress-check' ); ?></span>
										<strong><?php echo esc_html( $method2_data['section_c_eval'] ?? '-' ); ?></strong>
									</div>
									<div class="osq-score-footer">
										<span class="osq-status-pill <?php echo $response->is_high_stress_method2 ? 'osq-status-badge--cancelled' : 'osq-status-badge--completed'; ?>">
											<?php echo $response->is_high_stress_method2 ? esc_html__( '高ストレス', 'osq-stress-check' ) : esc_html__( '正常', 'osq-stress-check' ); ?>
										</span>
									</div>
								</div>
							</div>
						</div>
						
						<!-- Radar Chart (Phase 2) -->
						<div class="osq-radar-chart-section">
							<h4 class="osq-section-header">
								<span class="dashicons dashicons-chart-line"></span> <?php esc_html_e( 'スコアレーダーチャート', 'osq-stress-check' ); ?>
							</h4>
							<div style="max-width:480px;margin:0 auto;">
								<canvas id="osq-radar-chart" width="480" height="480"></canvas>
							</div>
							<?php if ( $prev_year_result ) : ?>
								<p style="font-size:12px;color:#888;text-align:center;margin-top:8px;">
									前回受検（<?php echo esc_html( $prev_year_result->fiscal_year ); ?>年度）：
									<?php echo esc_html( $prev_year_result->org_snapshot ?? '—' ); ?> ／
									<?php echo 1 === (int) $prev_year_result->position_snapshot ? '一般' : '管理職'; ?>
								</p>
							<?php endif; ?>
						</div>

						<div class="osq-self-care-advice">
							<h4 class="osq-section-header">
								<span class="dashicons dashicons-heart" style="color: #ec4899;"></span> <?php esc_html_e( 'セルフケアアドバイス', 'osq-stress-check' ); ?>
								<span class="osq-ai-badge">AI</span>
							</h4>
							<div class="osq-advice-card" id="osq-ai-advice-card">
								<?php if ( $ai_advice ) : ?>
									<div class="osq-advice-content" style="white-space: pre-wrap;"><?php echo esc_html( $ai_advice ); ?></div>
								<?php elseif ( 'failed' === $ai_job_status ) : ?>
									<p><?php esc_html_e( 'AIアドバイスの生成に失敗しました。しばらく後に再度ご確認ください。', 'osq-stress-check' ); ?></p>
								<?php else : ?>
									<p id="osq-ai-advice-loading">
										<span class="dashicons dashicons-update osq-spin"></span>
										<?php esc_html_e( 'AIがあなたの結果を分析中です。しばらくお待ちください…', 'osq-stress-check' ); ?>
									</p>
									<script>
									(function() {
										var card = document.getElementById('osq-ai-advice-card');
										var poll = setInterval(function() {
											fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
												method: 'POST',
												headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
												body: new URLSearchParams({
													action: 'osq_get_ai_advice',
													nonce: '<?php echo esc_js( wp_create_nonce( 'osq_employee_nonce' ) ); ?>',
													response_id: '<?php echo esc_js( $response->response_id ); ?>'
												})
											})
											.then(function(r) { return r.json(); })
											.then(function(data) {
												if ( data.success && data.data.advice ) {
													clearInterval(poll);
													var div = document.createElement('div');
													div.className = 'osq-advice-content';
													div.style.whiteSpace = 'pre-wrap';
													div.textContent = data.data.advice;
													card.innerHTML = '';
													card.appendChild(div);
												}
											})
											.catch(function() {});
										}, 5000);
									})();
									</script>
								<?php endif; ?>
							</div>
						</div>

						<?php if ( $is_high_stress && $hr_contact && ( $hr_contact->contact_name || $hr_contact->contact_phone || $hr_contact->contact_email ) ) : ?>
						<div style="margin-top:24px;padding:20px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;border-left:4px solid #ea580c;">
							<p style="margin:0 0 14px;font-size:14px;font-weight:600;color:#9a3412;">
								<span class="dashicons dashicons-phone" style="margin-right:6px;"></span>
								<?php esc_html_e( '医師による面接指導を希望する場合は、下記まで直接ご連絡ください。', 'osq-stress-check' ); ?>
							</p>
							<table style="font-size:13px;line-height:2;color:#431407;">
								<?php if ( $hr_contact->contact_name ) : ?>
								<tr>
									<td style="padding-right:16px;color:#9a3412;font-weight:500;"><?php esc_html_e( '人事部（総務部）担当', 'osq-stress-check' ); ?></td>
									<td><?php echo esc_html( $hr_contact->contact_name ); ?></td>
								</tr>
								<?php endif; ?>
								<?php if ( $hr_contact->contact_phone ) : ?>
								<tr>
									<td style="padding-right:16px;color:#9a3412;font-weight:500;"><?php esc_html_e( '電話番号', 'osq-stress-check' ); ?></td>
									<td><?php echo esc_html( $hr_contact->contact_phone ); ?></td>
								</tr>
								<?php endif; ?>
								<?php if ( $hr_contact->contact_email ) : ?>
								<tr>
									<td style="padding-right:16px;color:#9a3412;font-weight:500;"><?php esc_html_e( 'メールアドレス', 'osq-stress-check' ); ?></td>
									<td><a href="mailto:<?php echo esc_attr( $hr_contact->contact_email ); ?>" style="color:#ea580c;"><?php echo esc_html( $hr_contact->contact_email ); ?></a></td>
								</tr>
								<?php endif; ?>
							</table>
							<p style="margin:12px 0 0;font-size:12px;color:#9a3412;opacity:0.8;">
								<?php esc_html_e( '※ すぐに連絡できない状況の場合、この画面をスクリーンショット等で保存し、後ほどご連絡ください。', 'osq-stress-check' ); ?>
							</p>
						</div>
						<?php endif; ?>

						<div class="osq-download-box">
							<p><?php esc_html_e( '詳細な結果をPDFでダウンロードできます。', 'osq-stress-check' ); ?></p>
							<button type="button" class="osq-button osq-button--primary osq-js-download-pdf">
								<span class="dashicons dashicons-pdf"></span> <?php esc_html_e( '結果PDFをダウンロード', 'osq-stress-check' ); ?>
							</button>
						</div>
					<?php else : ?>
						<div class="osq-prompt-box">
							<p><?php esc_html_e( 'ストレス評価結果を受け取るには、すべての設問にお答えください。', 'osq-stress-check' ); ?></p>
							<a href="<?php echo esc_url( home_url( '/osq-questionnaire/' ) ); ?>" class="osq-button osq-button--primary">
								<?php echo 'in_progress' === $status ? esc_html__( 'ストレスチェックを再開する', 'osq-stress-check' ) : esc_html__( 'ストレスチェックを開始する', 'osq-stress-check' ); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>
			</section>

			<!-- Profile Tab -->
			<section id="tab-profile" class="osq-tab-panel <?php echo $must_change_password ? 'active' : ''; ?>" <?php echo $must_change_password ? '' : 'style="display: none;"'; ?>>
				<div class="osq-panel-card" style="max-width: 600px;">
					<h3 style="margin-top: 0; margin-bottom: 20px;"><?php esc_html_e( 'パスワード変更', 'osq-stress-check' ); ?></h3>
					
					<?php if ( $must_change_password ) : ?>
						<div class="osq-result-alert osq-result-alert--high" style="margin-bottom: 30px;">
							<span class="dashicons dashicons-warning"></span>
							<div class="osq-result-text">
								<strong><?php esc_html_e( 'パスワードの変更が必要です', 'osq-stress-check' ); ?></strong>
								<p><?php esc_html_e( 'セキュリティ上の理由から、ダッシュボードにアクセスする前に初期パスワードを変更してください。', 'osq-stress-check' ); ?></p>
							</div>
						</div>
					<?php endif; ?>

					<form id="osq-employee-password-form" class="osq-admin-form">
						<div class="osq-form-row">
							<label><?php esc_html_e( '現在のパスワード', 'osq-stress-check' ); ?></label>
							<input type="password" name="current_password" required class="osq-input">
						</div>
						<div class="osq-form-row">
							<label><?php esc_html_e( '新しいパスワード', 'osq-stress-check' ); ?></label>
							<input type="password" name="new_password" required minlength="8" class="osq-input">
						</div>
						<div class="osq-form-row">
							<label><?php esc_html_e( '新しいパスワード（確認）', 'osq-stress-check' ); ?></label>
							<input type="password" name="confirm_password" required minlength="8" class="osq-input">
						</div>
						<div class="osq-form-actions">
							<button type="submit" class="osq-button osq-button--primary"><?php esc_html_e( 'パスワードを更新する', 'osq-stress-check' ); ?></button>
						</div>
						<div id="osq-password-message" class="osq-settings-message" style="display: none; margin-top: 20px;"></div>
					</form>
				</div>
			</section>

			<!-- Settings Tab -->
			<section id="tab-settings" class="osq-tab-panel">
				<div class="osq-panel-card" style="max-width: 600px;">
					<h3 style="margin-top: 0; margin-bottom: 20px;"><?php esc_html_e( '設定', 'osq-stress-check' ); ?></h3>
					<p style="color:#64748b;font-size:14px;"><?php esc_html_e( '現在、変更可能な設定項目はありません。', 'osq-stress-check' ); ?></p>
				</div>
			</section>
		</div>

		<!-- Hidden PDF Template -->
		<div id="osq-pdf-template" style="display: none; padding: 30px; font-family: 'Hiragino Kaku Gothic Pro', 'Meiryo', sans-serif; color: #333; line-height: 1.6; background: white; width: 720px; box-sizing: border-box;">
			<!-- Header Section -->
			<div style="border-bottom: 2px solid #007cba; padding-bottom: 15px; margin-bottom: 25px;">
				<table style="width: 100%; border-collapse: collapse;">
					<tr>
						<td style="text-align: left;">
							<h1 style="color: #007cba; margin: 0; font-size: 24px;"><?php esc_html_e( 'ストレスチェック結果報告書', 'osq-stress-check' ); ?></h1>
						</td>
						<td style="text-align: right; vertical-align: bottom;">
							<p style="margin: 0; color: #666; font-size: 14px;">
								<strong><?php esc_html_e( '日付：', 'osq-stress-check' ); ?></strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $response->completed_at ?? 'now' ) ) ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Employee Info -->
			<div style="margin-bottom: 25px;">
				<p style="margin: 0 0 5px;"><strong><?php esc_html_e( '氏名：', 'osq-stress-check' ); ?></strong> <?php echo esc_html( $employee->name ?? '' ); ?></p>
				<p style="margin: 0;"><strong><?php esc_html_e( '社員番号：', 'osq-stress-check' ); ?></strong> <?php echo esc_html( $employee->employee_number ?? '' ); ?></p>
			</div>

			<!-- Result Summary -->
			<div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #e2e8f0; border-left: 5px solid <?php echo $is_high_stress ? '#ef4444' : '#10b981'; ?>;">
				<h2 style="margin-top: 0; font-size: 18px; color: <?php echo $is_high_stress ? '#b91c1c' : '#15803d'; ?>; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 15px;">
					<?php esc_html_e( '総合判定', 'osq-stress-check' ); ?>
				</h2>
				<p style="font-size: 20px; font-weight: bold; margin: 0 0 10px; color: <?php echo $is_high_stress ? '#ef4444' : '#10b981'; ?>;">
					<?php echo esc_html( $advice_title ); ?>
				</p>
				<p style="margin: 0; color: #334155; line-height: 1.5;">
					<?php echo esc_html( $advice_text ); ?>
				</p>
			</div>

			<!-- Scoring Details -->
			<div style="margin-bottom: 25px;">
				<h3 style="border-bottom: 2px solid #334155; padding-bottom: 8px; color: #1e293b; font-size: 18px; margin-bottom: 20px;">
					<?php esc_html_e( 'スコア詳細', 'osq-stress-check' ); ?>
				</h3>
				
				<!-- Method 1 Table -->
				<div style="margin-bottom: 25px;">
					<h4 style="margin: 0 0 10px; color: #0369a1; font-size: 15px;"><?php esc_html_e( '判定方法1：合計点数による判定', 'osq-stress-check' ); ?></h4>
					<table style="width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #cbd5e1; table-layout: fixed;">
						<thead>
							<tr style="background: #f1f5f9;">
								<th style="padding: 10px; text-align: left; border: 1px solid #cbd5e1; font-size: 12px; width: 70%;"><?php esc_html_e( '判定項目', 'osq-stress-check' ); ?></th>
								<th style="padding: 10px; text-align: center; border: 1px solid #cbd5e1; font-size: 12px; width: 30%;"><?php esc_html_e( '得点', 'osq-stress-check' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td style="padding: 10px; border: 1px solid #cbd5e1; font-size: 13px;"><?php esc_html_e( 'A: 仕事のストレス要因', 'osq-stress-check' ); ?></td>
								<td style="padding: 10px; text-align: center; border: 1px solid #cbd5e1; font-weight: bold; font-size: 14px;"><?php echo esc_html( $method1_data['section_a_total'] ?? '-' ); ?></td>
							</tr>
							<tr>
								<td style="padding: 10px; border: 1px solid #cbd5e1; font-size: 13px;"><?php esc_html_e( 'B: 心身のストレス反応', 'osq-stress-check' ); ?></td>
								<td style="padding: 10px; text-align: center; border: 1px solid #cbd5e1; font-weight: bold; font-size: 14px;"><?php echo esc_html( $method1_data['section_b_total'] ?? '-' ); ?></td>
							</tr>
							<tr>
								<td style="padding: 10px; border: 1px solid #cbd5e1; font-size: 13px;"><?php esc_html_e( 'C: 周囲のサポート', 'osq-stress-check' ); ?></td>
								<td style="padding: 10px; text-align: center; border: 1px solid #cbd5e1; font-weight: bold; font-size: 14px;"><?php echo esc_html( $method1_data['section_c_total'] ?? '-' ); ?></td>
							</tr>
							<tr style="background: <?php echo $response->is_high_stress_method1 ? '#fef2f2' : '#f0fdf4'; ?>;">
								<td style="padding: 12px; border: 1px solid #cbd5e1; font-weight: bold; font-size: 13px;"><?php esc_html_e( '判定結果', 'osq-stress-check' ); ?></td>
								<td style="padding: 12px; text-align: center; border: 1px solid #cbd5e1; font-weight: bold; color: <?php echo $response->is_high_stress_method1 ? '#ef4444' : '#10b981'; ?>; font-size: 14px;">
									<?php echo $response->is_high_stress_method1 ? esc_html__( '高ストレス', 'osq-stress-check' ) : esc_html__( '通常', 'osq-stress-check' ); ?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Method 2 Table -->
				<div>
					<h4 style="margin: 0 0 10px; color: #6d28d9; font-size: 15px;"><?php esc_html_e( '判定方法2：各尺度の評価による判定', 'osq-stress-check' ); ?></h4>
					<table style="width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #cbd5e1; table-layout: fixed;">
						<thead>
							<tr style="background: #f1f5f9;">
								<th style="padding: 10px; text-align: left; border: 1px solid #cbd5e1; font-size: 12px; width: 70%;"><?php esc_html_e( '判定項目', 'osq-stress-check' ); ?></th>
								<th style="padding: 10px; text-align: center; border: 1px solid #cbd5e1; font-size: 12px; width: 30%;"><?php esc_html_e( '評価', 'osq-stress-check' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td style="padding: 10px; border: 1px solid #cbd5e1; font-size: 13px;"><?php esc_html_e( 'A: 仕事のストレス要因', 'osq-stress-check' ); ?></td>
								<td style="padding: 10px; text-align: center; border: 1px solid #cbd5e1; font-weight: bold; font-size: 14px;"><?php echo esc_html( $method2_data['section_a_eval'] ?? '-' ); ?></td>
							</tr>
							<tr>
								<td style="padding: 10px; border: 1px solid #cbd5e1; font-size: 13px;"><?php esc_html_e( 'B: 心身のストレス反応', 'osq-stress-check' ); ?></td>
								<td style="padding: 10px; text-align: center; border: 1px solid #cbd5e1; font-weight: bold; font-size: 14px;"><?php echo esc_html( $method2_data['section_b_eval'] ?? '-' ); ?></td>
							</tr>
							<tr>
								<td style="padding: 10px; border: 1px solid #cbd5e1; font-size: 13px;"><?php esc_html_e( 'C: 周囲のサポート', 'osq-stress-check' ); ?></td>
								<td style="padding: 10px; text-align: center; border: 1px solid #cbd5e1; font-weight: bold; font-size: 14px;"><?php echo esc_html( $method2_data['section_c_eval'] ?? '-' ); ?></td>
							</tr>
							<tr style="background: <?php echo $response->is_high_stress_method2 ? '#fef2f2' : '#f0fdf4'; ?>;">
								<td style="padding: 12px; border: 1px solid #cbd5e1; font-weight: bold; font-size: 13px;"><?php esc_html_e( '判定結果', 'osq-stress-check' ); ?></td>
								<td style="padding: 12px; text-align: center; border: 1px solid #cbd5e1; font-weight: bold; color: <?php echo $response->is_high_stress_method2 ? '#ef4444' : '#10b981'; ?>; font-size: 14px;">
									<?php echo $response->is_high_stress_method2 ? esc_html__( '高ストレス', 'osq-stress-check' ) : esc_html__( '通常', 'osq-stress-check' ); ?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<?php if ( ! empty( $ai_advice ) ) :
				$pdf_advice = mb_strlen( $ai_advice ) > 400 ? mb_substr( $ai_advice, 0, 400 ) . '…' : $ai_advice;
			?>
			<div style="margin-bottom:20px;padding:15px;background:#f0f9ff;border-left:4px solid #0369a1;border-radius:4px;">
				<h3 style="margin:0 0 10px;font-size:15px;color:#0369a1;">AIセルフケアアドバイス</h3>
				<p style="margin:0;font-size:13px;line-height:1.7;white-space:pre-wrap;"><?php echo esc_html( $pdf_advice ); ?></p>
			</div>
			<div style="font-size:11px;color:#555;border:1px solid #e2e8f0;border-radius:4px;padding:10px;margin-bottom:20px;">
				<strong>【免責事項】</strong>
				本アドバイスは、回答内容に基づきAIが生成したものです。特定の疾患を診断するものではなく、あくまでセルフケアのヒントとしてご活用ください。体調に不安がある場合や深刻なストレスを感じる場合は、専門の医療機関や相談窓口へご相談いただくことをお勧めいたします。
			</div>
			<?php endif; ?>

			<div style="font-size: 12px; color: #999; margin-top: 50px; text-align: center; border-top: 1px solid #eee; padding-top: 20px;">
				<p><?php printf( esc_html__( '%s 発行', 'osq-stress-check' ), get_bloginfo( 'name' ) ); ?></p>
			</div>
		</div>
	</main>
</div>

<style>
/* Dashboard Layout Styling - Reusing Admin Dashboard Patterns */
.osq-admin-dashboard {
	display: flex;
	min-height: 100vh;
	background: #f8fafc;
	font-family: 'Inter', system-ui, sans-serif;
	margin: 0;
	padding: 0;
	width: 100vw;
	max-width: 100vw;
}
.osq-admin-sidebar {
	width: 260px;
	background: #1e293b;
	color: white;
	display: flex;
	flex-direction: column;
	flex-shrink: 0;
}
.osq-sidebar-header {
	padding: 30px 20px;
	text-align: center;
	border-bottom: 1px solid rgba(255,255,255,0.1);
}
.osq-logo {
	font-size: 22px;
	font-weight: 800;
	color: #38bdf8;
	letter-spacing: -1px;
}
.osq-admin-nav ul {
	list-style: none;
	padding: 20px 0;
	margin: 0;
}
.osq-admin-nav li {
	padding: 14px 24px;
	cursor: pointer;
	display: flex;
	align-items: center;
	color: #94a3b8;
	transition: all 0.2s;
	font-weight: 500;
}
.osq-admin-nav li .dashicons {
	margin-right: 12px;
	font-size: 20px;
}
.osq-admin-nav li:hover {
	color: white;
	background: rgba(255,255,255,0.05);
}
.osq-admin-nav li.active {
	color: white;
	background: #334155;
	border-left: 4px solid #38bdf8;
}
.osq-logout-btn {
	background: #f1f5f9;
	color: #ef4444;
	text-decoration: none;
	padding: 8px 16px;
	border-radius: 6px;
	font-weight: 600;
	font-size: 14px;
	transition: all 0.2s;
}
.osq-logout-btn:hover {
	background: #fee2e2;
}

.osq-admin-main {
	flex-grow: 1;
	display: flex;
	flex-direction: column;
	overflow-x: hidden;
}
.osq-admin-header {
	background: white;
	padding: 20px 40px;
	display: flex;
	justify-content: space-between;
	align-items: center;
	box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.osq-admin-header h2 {
	margin: 0;
	font-size: 24px;
	color: #1e293b;
	font-weight: 700;
}
.osq-user-welcome {
	color: #64748b;
	font-weight: 500;
}

.osq-admin-content {
	padding: 40px;
	flex-grow: 1;
}
.osq-tab-panel {
	display: none;
}
.osq-tab-panel.active {
	display: block;
	animation: fadeIn 0.3s ease;
}

/* Inner tab nav (Dashboard / Profile / Settings) */
.osq-inner-tab-nav {
	background: white;
	border-bottom: 1px solid #e2e8f0;
	padding: 0 40px;
}
.osq-inner-tab-nav ul {
	list-style: none;
	padding: 0;
	margin: 0;
	display: flex;
}
.osq-inner-tab-nav li {
	padding: 12px 20px;
	cursor: pointer;
	display: flex;
	align-items: center;
	gap: 8px;
	color: #64748b;
	font-weight: 500;
	font-size: 14px;
	border-bottom: 3px solid transparent;
	transition: all 0.2s;
}
.osq-inner-tab-nav li:hover {
	color: #1e293b;
	border-bottom-color: #cbd5e1;
}
.osq-inner-tab-nav li.active {
	color: #1e293b;
	border-bottom-color: #6ee7b7;
	font-weight: 600;
}
.osq-inner-tab-nav li .dashicons {
	font-size: 18px;
	width: 18px;
	height: 18px;
}

/* Employee Specific Styles */
.osq-employee-theme .osq-admin-sidebar {
	background: #064e3b; /* Dark emerald for employee */
}
.osq-employee-theme .osq-logo {
	color: #6ee7b7;
}
.osq-employee-theme .osq-admin-nav li.active {
	background: #065f46;
	border-left-color: #6ee7b7;
}

.osq-greeting-hero {
	margin-bottom: 30px;
}
.osq-greeting-hero h3 {
	margin: 0 0 5px;
	font-size: 24px;
	color: #1e293b;
}
.osq-greeting-hero p {
	margin: 0;
	color: #64748b;
}

.osq-stats-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 25px;
	margin-bottom: 30px;
}
.osq-stat-card {
	background: white;
	padding: 25px;
	border-radius: 12px;
	box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}
.osq-stat-card h3 {
	margin: 0 0 10px;
	font-size: 14px;
	color: #64748b;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}
.osq-status-pill {
	display: inline-block;
	padding: 6px 16px;
	border-radius: 20px;
	font-weight: 700;
	font-size: 14px;
}
.osq-status-pill--not_started { background: #f1f5f9; color: #475569; }
.osq-status-pill--in_progress { background: #fef3c7; color: #92400e; }
.osq-status-pill--completed { background: #dcfce7; color: #166534; }

/* Follow-up Status Badges */
.osq-status-badge--pending { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.osq-status-badge--scheduled { background: #fef3c7; color: #d97706; border: 1px solid #fde68a; }
.osq-status-badge--completed { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.osq-status-badge--cancelled { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }

.osq-panel-card {
	background: white;
	padding: 30px;
	border-radius: 12px;
	box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
	margin-bottom: 30px;
}

/* Follow-up Card Styles */
.osq-follow-up-card {
	border-left: 5px solid #0ea5e9;
}
.osq-card-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 8px;
	margin-bottom: 10px;
}
.osq-card-title {
	margin: 0;
	color: #0284c7;
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 18px;
}
.osq-follow-up-intro {
	margin-top: 5px;
	margin-bottom: 15px;
	color: #475569;
	font-weight: 500;
}
.osq-scheduled-date-box {
	background: #f0f9ff;
	padding: 12px 16px;
	border-radius: 6px;
	margin-bottom: 15px;
	font-weight: 600;
	color: #0369a1;
	display: flex;
	align-items: center;
	gap: 8px;
}
.osq-follow-up-notes {
	background: #f8fafc;
	padding: 20px;
	border-radius: 8px;
	color: #334155;
	line-height: 1.6;
	font-size: 15px;
	border: 1px solid #e2e8f0;
}

/* Result Sections */
.osq-section-header {
	font-size: 18px;
	margin: 0 0 15px;
	color: #1e293b;
	border-bottom: 2px solid #e2e8f0;
	padding-bottom: 10px;
	display: flex;
	align-items: center;
	gap: 8px;
}
.osq-score-details-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 20px;
	margin-top: 15px;
}
.osq-score-method-card {
	background: #f8fafc;
	border-radius: 8px;
	padding: 20px;
	border: 1px solid #e2e8f0;
}
.osq-score-method-card h5 {
	margin: 0 0 15px;
	font-size: 15px;
	color: #334155;
}
.osq-score-row {
	display: flex;
	justify-content: space-between;
	margin-bottom: 8px;
	font-size: 14px;
	border-bottom: 1px dashed #cbd5e1;
	padding-bottom: 4px;
}
.osq-score-row--last {
	border-bottom: none;
}
.osq-score-footer {
	margin-top: 15px;
	padding-top: 10px;
	border-top: 1px solid #cbd5e1;
	text-align: right;
}

/* Advice Card */
.osq-ai-badge {
	background: #fdf2f8;
	color: #ec4899;
	font-size: 11px;
	padding: 2px 8px;
	border-radius: 12px;
	border: 1px solid #fbcfe8;
	font-weight: 600;
}
.osq-advice-card {
	background: linear-gradient(to right, #ffffff, #fdf2f8);
	border-radius: 8px;
	padding: 25px;
	border: 1px solid #fbcfe8;
	position: relative;
	overflow: hidden;
}
.osq-advice-icon {
	position: absolute;
	top: -10px;
	right: -10px;
	opacity: 0.05;
}
.osq-advice-icon .dashicons {
	font-size: 120px;
	width: 120px;
	height: 120px;
}
.osq-advice-card p {
	margin: 0;
	color: #475569;
	font-size: 15px;
	line-height: 1.6;
	position: relative;
	z-index: 1;
}

.osq-result-alert {
	padding: 25px;
	border-radius: 10px;
	display: flex;
	align-items: flex-start;
	gap: 20px;
	margin-bottom: 30px;
}
.osq-result-alert span.dashicons {
	font-size: 32px;
	width: 32px;
	height: 32px;
}
.osq-result-text strong {
	display: block;
	font-size: 18px;
	margin-bottom: 8px;
}
.osq-result-text p {
	margin: 0;
	line-height: 1.6;
}
.osq-result-alert--high {
	background: #fef2f2;
	color: #991b1b;
	border: 1px solid #fecaca;
}
.osq-result-alert--normal {
	background: #f0fdf4;
	color: #166534;
	border: 1px solid #bbf7d0;
}

.osq-download-box, .osq-prompt-box {
	text-align: center;
	padding: 20px 0;
}
.osq-button {
	display: inline-flex;
	align-items: center;
	gap: 10px;
	text-decoration: none;
	font-weight: 700;
	padding: 12px 24px;
	border-radius: 8px;
	transition: all 0.2s;
	font-size: 16px;
	border: none;
	cursor: pointer;
}
.osq-button--primary { background: #166534; color: white; }
.osq-button--primary:hover { background: #065f46; transform: translateY(-1px); }

.osq-form-row {
	margin-bottom: 20px;
}
.osq-form-row label {
	display: block;
	margin-bottom: 8px;
	font-weight: 600;
	color: #1e293b;
}
.osq-input {
	width: 100%;
	padding: 12px;
	border: 1px solid #e2e8f0;
	border-radius: 6px;
	box-sizing: border-box;
}

@keyframes fadeIn {
	from { opacity: 0; transform: translateY(10px); }
	to { opacity: 1; transform: translateY(0); }
}

/* Tablet / Desktop Small */
@media (max-width: 1024px) {
	.osq-admin-sidebar { width: 80px; }
	.osq-sidebar-header span { display: none; }
	.osq-admin-nav li span:not(.dashicons) { display: none; }
	.osq-admin-nav li .dashicons { margin-right: 0; }
	.osq-nav-logout a .dashicons { margin-right: 0; }
}

/* Mobile Responsive Adjustments */
@media (max-width: 768px) {
	.osq-admin-dashboard {
		flex-direction: column;
	}
	.osq-admin-sidebar {
		width: 100%;
		height: auto;
		order: -1;
	}
	.osq-sidebar-header {
		padding: 15px 20px;
		display: flex;
		justify-content: space-between;
		align-items: center;
		border-bottom: 1px solid rgba(255,255,255,0.1);
	}
	.osq-logo {
		font-size: 18px;
	}
	.osq-admin-nav ul {
		display: flex;
		padding: 0;
		overflow-x: auto;
		-webkit-overflow-scrolling: touch;
	}
	.osq-admin-nav li {
		flex: 0 0 auto;
		padding: 15px 20px;
		border-left: none !important;
		border-bottom: 3px solid transparent;
	}
	.osq-admin-nav li.active {
		background: rgba(255,255,255,0.1);
		border-bottom-color: #6ee7b7;
	}
	.osq-admin-nav li span:not(.dashicons) {
		display: inline-block;
		margin-left: 8px;
	}
	.osq-admin-nav li .dashicons {
		margin-right: 5px;
	}

	.osq-admin-header {
		padding: 15px 20px;
		flex-direction: column;
		align-items: flex-start;
		gap: 10px;
	}
	.osq-admin-header h2 {
		font-size: 20px;
	}
	.osq-header-right {
		display: flex;
		width: 100%;
		justify-content: space-between;
		align-items: center;
	}
	.osq-user-welcome {
		font-size: 14px;
	}
	.osq-logout-btn {
		padding: 6px 12px;
		font-size: 13px;
	}

	.osq-inner-tab-nav {
		padding: 0 15px;
		overflow-x: auto;
	}
	.osq-inner-tab-nav li {
		white-space: nowrap;
		padding: 10px 14px;
	}

	.osq-admin-content {
		padding: 20px;
	}
	.osq-stats-grid {
		grid-template-columns: 1fr;
		gap: 15px;
	}
	.osq-panel-card {
		padding: 20px;
	}
	.osq-score-details-grid {
		grid-template-columns: 1fr;
	}
	.osq-result-alert {
		flex-direction: column;
		gap: 10px;
	}
	.osq-result-alert span.dashicons {
		font-size: 24px;
	}
}
@media (max-width: 480px) {
	.osq-admin-nav li span:not(.dashicons) {
		display: none;
	}
	.osq-admin-nav li .dashicons {
		margin-right: 0;
		font-size: 24px;
	}
	.osq-admin-nav li {
		flex: 1;
		justify-content: center;
		padding: 12px 10px;
	}
}
</style>

<?php if ( $response && $response->is_complete && ! empty( $method2_data['eval_points'] ) ) : ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
<script>
(function() {
	var labels = [
		'量的負担', '質的負担', '身体的負担', '対人関係ストレス', '職場環境ストレス',
		'仕事のコントロール', '技能の活用', '仕事の適合性', '報酬', '活気',
		'イライラ感', '疲労感', '不安感', '抑うつ感', '身体愁訴',
		'上司サポート', '同僚サポート', '家族サポート'
	];
	var scaleOrder = [
		'quantitative_demands','qualitative_demands','physical_workload','interpersonal_stress','environment_stress',
		'job_control','skill_utilization','job_fit','reward','vigor',
		'irritability','fatigue','anxiety','depression','physical_complaints',
		'supervisor_support','colleague_support','family_support'
	];

	var currentPoints = <?php echo wp_json_encode( $method2_data['eval_points'] ?? new stdClass() ); ?>;
	var prevPoints    = <?php echo wp_json_encode( $prev_year_eval_points ?: new stdClass() ); ?>;
	var hasPrev       = <?php echo ! empty( $prev_year_eval_points ) ? 'true' : 'false'; ?>;

	var currentData = scaleOrder.map(function(k){ return currentPoints[k] !== undefined ? currentPoints[k] : null; });
	var prevData    = hasPrev ? scaleOrder.map(function(k){ return prevPoints[k] !== undefined ? prevPoints[k] : null; }) : [];

	var datasets = [
		{
			label: '今年度',
			data: currentData,
			backgroundColor: 'rgba(59,130,246,0.2)',
			borderColor: 'rgba(59,130,246,1)',
			borderWidth: 2,
			pointBackgroundColor: 'rgba(59,130,246,1)'
		}
	];
	if ( hasPrev ) {
		datasets.push({
			label: '前年度（<?php echo $prev_year_result ? (int) $prev_year_result->fiscal_year : ''; ?>）',
			data: prevData,
			backgroundColor: 'rgba(0,0,0,0)',
			borderColor: 'rgba(249,115,22,0.85)',
			borderWidth: 2,
			borderDash: [6, 4],
			pointBackgroundColor: 'rgba(249,115,22,0.85)'
		});
	}

	var ctx = document.getElementById('osq-radar-chart');
	if ( ctx ) {
		new Chart(ctx, {
			type: 'radar',
			data: { labels: labels, datasets: datasets },
			options: {
				scales: { r: { min: 1, max: 5, ticks: { stepSize: 1 } } },
				plugins: { legend: { position: 'bottom' } }
			}
		});
	}
})();
</script>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>

