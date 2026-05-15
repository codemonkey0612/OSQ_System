<?php
/**
 * Unified Dashboard Template.
 *
 * Single page that shows capability-driven panels for all OSQ user roles.
 * Replaces /osq-employee-dashboard/, /osq-officer-dashboard/, /osq-admin-dashboard/.
 *
 * @package OSQ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Capability flags ──────────────────────────────────────────────────────────
$can_take_test      = \OSQ\Auth\CapabilityMatrix::user_has( \OSQ\Auth\CapabilityMatrix::TAKE_TEST );
$can_view_results   = \OSQ\Auth\CapabilityMatrix::user_has( \OSQ\Auth\CapabilityMatrix::VIEW_OWN_RESULTS );
$can_view_responses = \OSQ\Auth\CapabilityMatrix::user_has( \OSQ\Auth\CapabilityMatrix::VIEW_INDIVIDUAL_RESPONSES );
$can_follow_up      = \OSQ\Auth\CapabilityMatrix::user_has( \OSQ\Auth\CapabilityMatrix::SUPPORT_HIGH_STRESS );
$can_manage_emp     = \OSQ\Auth\CapabilityMatrix::user_has( \OSQ\Auth\CapabilityMatrix::MANAGE_EMPLOYEES );
$can_analysis       = \OSQ\Auth\CapabilityMatrix::user_has( \OSQ\Auth\CapabilityMatrix::VIEW_GROUP_ANALYSIS );
$can_settings       = \OSQ\Auth\CapabilityMatrix::user_has( \OSQ\Auth\CapabilityMatrix::SYSTEM_CONFIG );
$can_companies      = \OSQ\Auth\CapabilityMatrix::user_has( \OSQ\Auth\CapabilityMatrix::MANAGE_ALL_COMPANIES );

// ── Determine accessible panels in display order ──────────────────────────────
$accessible_panels = array();
if ( $can_take_test || $can_view_results ) { $accessible_panels[] = 'my-check'; }
if ( $can_view_responses )                 { $accessible_panels[] = 'responses'; }
if ( $can_follow_up )                      { $accessible_panels[] = 'followup'; }
if ( $can_manage_emp )                     { $accessible_panels[] = 'employees'; $accessible_panels[] = 'import'; }
if ( $can_analysis )                       { $accessible_panels[] = 'analysis'; }
if ( $can_settings )                       { $accessible_panels[] = 'settings'; }
$accessible_panels[] = 'profile';
if ( $can_companies )                      { $accessible_panels[] = 'companies'; }

// Sanitise and resolve the initial panel from ?panel= URL param.
$requested_panel = isset( $_GET['panel'] ) ? sanitize_key( $_GET['panel'] ) : '';
$initial_panel   = ( $requested_panel && in_array( $requested_panel, $accessible_panels, true ) )
	? $requested_panel
	: ( $accessible_panels[0] ?? 'profile' );

// ── Employee data (only needed when user can take test / view own results) ─────
$employee         = null;
$response         = null;
$status           = 'not_started';
$is_high_stress   = false;
$method1_data     = array();
$method2_data     = array();
$prev_year_result = null;
$ai_advice        = null;
$ai_job_status    = null;
$latest_follow_up = null;
$advice_title     = '';
$advice_text      = '';
$prev_year_eval_points = array();

if ( $can_take_test || $can_view_results ) {
	$user_id  = get_current_user_id();
	$db       = \OSQ\Plugin::get_instance()->db();
	$employee = $db->get_employee_by_user_id( $user_id );
	$response = $db->get_response_by_employee( $employee->employee_id ?? 0 );

	$status = 'not_started';
	if ( $response ) {
		$status = $response->is_complete ? 'completed' : 'in_progress';
	}

	if ( $response && $response->is_complete ) {
		$is_high_stress = (bool) ( $response->is_high_stress_method1 || $response->is_high_stress_method2 );
		$advice_title   = $is_high_stress ? __( 'High Stress Detected', 'osq-stress-check' ) : __( 'Normal Stress Levels', 'osq-stress-check' );
		$advice_text    = $is_high_stress
			? __( 'Your results indicate a high level of stress. We recommend reviewing your results and considering a consultation with an industrial physician.', 'osq-stress-check' )
			: __( 'Your results do not indicate a high level of stress at this time. Please continue to monitor your health and maintain a healthy work-life balance.', 'osq-stress-check' );

		$method1_data = maybe_unserialize( $response->method1_result ?? '' );
		$method2_data = maybe_unserialize( $response->method2_result ?? '' );
		if ( ! is_array( $method1_data ) ) { $method1_data = json_decode( $method1_data, true ) ?: array(); }
		if ( ! is_array( $method2_data ) ) { $method2_data = json_decode( $method2_data, true ) ?: array(); }
	}

	if ( $response && $response->is_complete && $employee ) {
		$prev_year_result = $db->get_previous_year_result( $employee->employee_id );
		if ( $prev_year_result ) {
			$pyr_m2 = maybe_unserialize( $prev_year_result->method2_result ?? '' );
			if ( ! is_array( $pyr_m2 ) ) { $pyr_m2 = json_decode( $pyr_m2, true ) ?: array(); }
			$prev_year_eval_points = $pyr_m2['eval_points'] ?? array();
		}

		$ai_generator  = new \OSQ\AI\AdviceGenerator();
		$ai_advice     = $ai_generator->get_cached( $response->response_id );
		if ( ! $ai_advice ) {
			$ai_generator->enqueue( $employee->employee_id, $response->response_id );
			$ai_job_status = $ai_generator->get_job_status( $employee->employee_id );
		}
	}

	if ( $employee && isset( $employee->employee_id ) ) {
		global $wpdb;
		$fu_table         = $wpdb->prefix . \OSQ\Database\Schema::FOLLOW_UP_TRACKING;
		$latest_follow_up = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$fu_table} WHERE employee_id = %d ORDER BY created_at DESC LIMIT 1",
			$employee->employee_id
		) );
	}
}

// ── Admin/analysis settings ───────────────────────────────────────────────────
$osq_settings         = get_option( 'osq_settings', array() );
$enable_group_analysis = isset( $osq_settings['enable_group_analysis'] ) ? (bool) $osq_settings['enable_group_analysis'] : true;

// ── Current logged-in user display ────────────────────────────────────────────
$current_user = wp_get_current_user();

// ── Panel title map ───────────────────────────────────────────────────────────
$panel_titles = array(
	'my-check'  => __( 'My Stress Check', 'osq-stress-check' ),
	'responses' => __( 'Individual Responses', 'osq-stress-check' ),
	'followup'  => __( 'Follow-up Tracking', 'osq-stress-check' ),
	'employees' => __( 'Employees', 'osq-stress-check' ),
	'import'    => __( 'CSV Import', 'osq-stress-check' ),
	'analysis'  => __( 'Group Analysis', 'osq-stress-check' ),
	'settings'  => __( 'Settings', 'osq-stress-check' ),
	'profile'   => __( 'Profile', 'osq-stress-check' ),
	'companies' => __( 'Companies', 'osq-stress-check' ),
);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<div id="osq-unified-dashboard" class="osq-ui-container osq-admin-dashboard">
	<div id="osq-sidebar-overlay" class="osq-sidebar-overlay"></div>

	<!-- ── Sidebar ──────────────────────────────────────────────────────── -->
	<aside class="osq-admin-sidebar">
		<div class="osq-sidebar-header">
			<span class="osq-logo">OSQ</span>
		</div>
		<nav class="osq-admin-nav">
			<ul>
				<?php if ( $can_take_test || $can_view_results ) : ?>
				<li class="<?php echo 'my-check' === $initial_panel ? 'active' : ''; ?>" data-panel="my-check">
					<span class="dashicons dashicons-id-alt"></span>
					<span><?php esc_html_e( 'My Stress Check', 'osq-stress-check' ); ?></span>
				</li>
				<?php endif; ?>

				<?php if ( $can_view_responses ) : ?>
				<li class="<?php echo 'responses' === $initial_panel ? 'active' : ''; ?>" data-panel="responses">
					<span class="dashicons dashicons-list-view"></span>
					<span><?php esc_html_e( 'Responses', 'osq-stress-check' ); ?></span>
				</li>
				<?php endif; ?>

				<?php if ( $can_follow_up ) : ?>
				<li class="<?php echo 'followup' === $initial_panel ? 'active' : ''; ?>" data-panel="followup" data-tab="followup">
					<span class="dashicons dashicons-calendar-alt"></span>
					<span><?php esc_html_e( 'Follow-up', 'osq-stress-check' ); ?></span>
				</li>
				<?php endif; ?>

				<?php if ( $can_manage_emp ) : ?>
				<li class="<?php echo 'employees' === $initial_panel ? 'active' : ''; ?>" data-panel="employees">
					<span class="dashicons dashicons-groups"></span>
					<span><?php esc_html_e( 'Employees', 'osq-stress-check' ); ?></span>
				</li>
				<li class="<?php echo 'import' === $initial_panel ? 'active' : ''; ?>" data-panel="import">
					<span class="dashicons dashicons-upload"></span>
					<span><?php esc_html_e( 'CSV Import', 'osq-stress-check' ); ?></span>
				</li>
				<?php endif; ?>

				<?php if ( $can_analysis ) : ?>
				<li class="<?php echo 'analysis' === $initial_panel ? 'active' : ''; ?>" data-panel="analysis">
					<span class="dashicons dashicons-chart-bar"></span>
					<span><?php esc_html_e( 'Analysis', 'osq-stress-check' ); ?></span>
				</li>
				<?php endif; ?>

				<?php if ( $can_settings ) : ?>
				<li class="<?php echo 'settings' === $initial_panel ? 'active' : ''; ?>" data-panel="settings">
					<span class="dashicons dashicons-admin-settings"></span>
					<span><?php esc_html_e( 'Settings', 'osq-stress-check' ); ?></span>
				</li>
				<?php endif; ?>

				<li class="<?php echo 'profile' === $initial_panel ? 'active' : ''; ?>" data-panel="profile">
					<span class="dashicons dashicons-admin-users"></span>
					<span><?php esc_html_e( 'Profile', 'osq-stress-check' ); ?></span>
				</li>

				<?php if ( $can_companies ) : ?>
				<li class="<?php echo 'companies' === $initial_panel ? 'active' : ''; ?>" data-panel="companies">
					<span class="dashicons dashicons-building"></span>
					<span><?php esc_html_e( 'Companies', 'osq-stress-check' ); ?></span>
				</li>
				<?php endif; ?>

				<li class="osq-nav-logout">
					<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>" class="osq-logout-btn">
						<span class="dashicons dashicons-exit"></span>
						<span><?php esc_html_e( 'Logout', 'osq-stress-check' ); ?></span>
					</a>
				</li>
			</ul>
		</nav>
	</aside>

	<!-- ── Main area ────────────────────────────────────────────────────── -->
	<main class="osq-admin-main">
		<header class="osq-admin-header">
			<div class="osq-header-left">
				<button id="osq-mobile-toggle" class="osq-hamburger">
					<span class="dashicons dashicons-menu"></span>
				</button>
				<h2 id="osq-tab-title"><?php echo esc_html( $panel_titles[ $initial_panel ] ?? '' ); ?></h2>
			</div>
			<div class="osq-header-right">
				<span class="osq-user-welcome"><?php printf( esc_html__( 'Hello, %s', 'osq-stress-check' ), esc_html( $current_user->display_name ) ); ?></span>
			</div>
		</header>

		<div class="osq-admin-content">

			<!-- ── MY STRESS CHECK ─────────────────────────────────────── -->
			<?php if ( $can_take_test || $can_view_results ) : ?>
			<section id="ud-panel-my-check" class="ud-panel <?php echo 'my-check' === $initial_panel ? 'active' : ''; ?>">
				<div class="osq-greeting-hero">
					<h3><?php esc_html_e( 'Your Stress Check Status', 'osq-stress-check' ); ?></h3>
				</div>
				<div class="osq-stats-grid">
					<div class="osq-stat-card">
						<h3><?php esc_html_e( 'Current Status', 'osq-stress-check' ); ?></h3>
						<div class="osq-status-pill osq-status-pill--<?php echo esc_attr( $status ); ?>">
							<?php
							switch ( $status ) {
								case 'completed':   esc_html_e( 'Completed', 'osq-stress-check' ); break;
								case 'in_progress': esc_html_e( 'In Progress', 'osq-stress-check' ); break;
								default:            esc_html_e( 'Not Started', 'osq-stress-check' );
							}
							?>
						</div>
					</div>
				</div>

				<?php if ( $latest_follow_up ) : ?>
				<?php
				$fu_status     = $latest_follow_up->status ?? '';
				$fu_badge_class = 'osq-status-badge--' . strtolower( $fu_status );
				if ( 'Scheduled' === $fu_status ) {
					$fu_message    = __( 'A follow-up interview has been scheduled.', 'osq-stress-check' );
					$fu_badge_text = __( 'Scheduled', 'osq-stress-check' );
				} elseif ( 'Completed' === $fu_status ) {
					$fu_message    = __( 'Your follow-up interview has been completed. Thank you.', 'osq-stress-check' );
					$fu_badge_text = __( 'Completed', 'osq-stress-check' );
				} elseif ( 'Cancelled' === $fu_status ) {
					$fu_message    = __( 'The previously scheduled follow-up interview has been cancelled.', 'osq-stress-check' );
					$fu_badge_text = __( 'Cancelled', 'osq-stress-check' );
				} else {
					$fu_message     = __( 'Your follow-up is currently pending review.', 'osq-stress-check' );
					$fu_badge_text  = __( 'Pending', 'osq-stress-check' );
					$fu_badge_class = 'osq-status-badge--pending';
				}
				?>
				<div class="osq-panel-card osq-follow-up-card">
					<div class="osq-card-header">
						<h3 class="osq-card-title">
							<span class="dashicons dashicons-testimonial"></span>
							<?php esc_html_e( 'Message from HR / Occupational Health', 'osq-stress-check' ); ?>
						</h3>
						<span class="osq-status-pill <?php echo esc_attr( $fu_badge_class ); ?>"><?php echo esc_html( $fu_badge_text ); ?></span>
					</div>
					<p class="osq-follow-up-intro"><?php echo esc_html( $fu_message ); ?></p>
					<?php if ( 'Scheduled' === $fu_status && ! empty( $latest_follow_up->scheduled_date ) ) : ?>
					<div class="osq-scheduled-date-box">
						<span class="dashicons dashicons-calendar-alt"></span>
						<?php printf( esc_html__( 'Scheduled Interview: %s', 'osq-stress-check' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $latest_follow_up->scheduled_date ) ) ); ?>
					</div>
					<?php endif; ?>
					<?php if ( ! empty( $latest_follow_up->notes ) ) : ?>
					<div class="osq-follow-up-notes"><?php echo nl2br( esc_html( $latest_follow_up->notes ) ); ?></div>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<div class="osq-panel-card">
					<?php if ( 'completed' === $status ) : ?>
					<div class="osq-result-display" style="margin-bottom:30px;">
						<h4 class="osq-section-header"><span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( '総合判定 (Result Summary)', 'osq-stress-check' ); ?></h4>
						<div class="osq-result-alert osq-result-alert--<?php echo $is_high_stress ? 'high' : 'normal'; ?>">
							<span class="dashicons dashicons-<?php echo $is_high_stress ? 'warning' : 'id-alt'; ?>"></span>
							<div class="osq-result-text">
								<strong><?php echo esc_html( $advice_title ); ?></strong>
								<p><?php echo esc_html( $advice_text ); ?></p>
							</div>
						</div>
					</div>
					<div class="osq-score-explanation">
						<h4 class="osq-section-header"><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( 'スコア詳細 (Score Details)', 'osq-stress-check' ); ?></h4>
						<div class="osq-score-details-grid">
							<div class="osq-score-method-card">
								<h5><?php esc_html_e( 'Method 1 (Total Points)', 'osq-stress-check' ); ?></h5>
								<div class="osq-score-row"><span><?php esc_html_e( 'Section A', 'osq-stress-check' ); ?></span><strong><?php echo esc_html( $method1_data['section_a_total'] ?? '-' ); ?></strong></div>
								<div class="osq-score-row"><span><?php esc_html_e( 'Section B', 'osq-stress-check' ); ?></span><strong><?php echo esc_html( $method1_data['section_b_total'] ?? '-' ); ?></strong></div>
								<div class="osq-score-row osq-score-row--last"><span><?php esc_html_e( 'Section C', 'osq-stress-check' ); ?></span><strong><?php echo esc_html( $method1_data['section_c_total'] ?? '-' ); ?></strong></div>
								<div class="osq-score-footer">
									<span class="osq-status-pill <?php echo $response->is_high_stress_method1 ? 'osq-status-badge--cancelled' : 'osq-status-badge--completed'; ?>">
										<?php echo $response->is_high_stress_method1 ? esc_html__( 'High', 'osq-stress-check' ) : esc_html__( 'Normal', 'osq-stress-check' ); ?>
									</span>
								</div>
							</div>
							<div class="osq-score-method-card">
								<h5><?php esc_html_e( 'Method 2 (Scale Specific)', 'osq-stress-check' ); ?></h5>
								<div class="osq-score-row"><span><?php esc_html_e( 'Section A', 'osq-stress-check' ); ?></span><strong><?php echo esc_html( $method2_data['section_a_eval'] ?? '-' ); ?></strong></div>
								<div class="osq-score-row"><span><?php esc_html_e( 'Section B', 'osq-stress-check' ); ?></span><strong><?php echo esc_html( $method2_data['section_b_eval'] ?? '-' ); ?></strong></div>
								<div class="osq-score-row osq-score-row--last"><span><?php esc_html_e( 'Section C', 'osq-stress-check' ); ?></span><strong><?php echo esc_html( $method2_data['section_c_eval'] ?? '-' ); ?></strong></div>
								<div class="osq-score-footer">
									<span class="osq-status-pill <?php echo $response->is_high_stress_method2 ? 'osq-status-badge--cancelled' : 'osq-status-badge--completed'; ?>">
										<?php echo $response->is_high_stress_method2 ? esc_html__( 'High', 'osq-stress-check' ) : esc_html__( 'Normal', 'osq-stress-check' ); ?>
									</span>
								</div>
							</div>
						</div>
					</div>
					<div class="osq-radar-chart-section">
						<h4 class="osq-section-header"><span class="dashicons dashicons-chart-line"></span> <?php esc_html_e( 'スコアレーダーチャート (Radar Chart)', 'osq-stress-check' ); ?></h4>
						<div style="max-width:480px;margin:0 auto;">
							<canvas id="osq-radar-chart" width="480" height="480"></canvas>
						</div>
					</div>
					<div class="osq-self-care-advice">
						<h4 class="osq-section-header">
							<span class="dashicons dashicons-heart" style="color:#ec4899;"></span> <?php esc_html_e( 'セルフケアアドバイス (Self-Care Advice)', 'osq-stress-check' ); ?>
							<span class="osq-ai-badge">AI</span>
						</h4>
						<div class="osq-advice-card" id="osq-ai-advice-card">
							<?php if ( $ai_advice ) : ?>
								<div class="osq-advice-content" style="white-space:pre-wrap;"><?php echo esc_html( $ai_advice ); ?></div>
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
												response_id: '<?php echo esc_js( $response->response_id ?? '' ); ?>'
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
					<div class="osq-download-box">
						<p><?php esc_html_e( 'You can download your detailed results as a PDF below.', 'osq-stress-check' ); ?></p>
						<button type="button" class="osq-button osq-button--primary osq-js-download-pdf">
							<span class="dashicons dashicons-pdf"></span> <?php esc_html_e( 'Download Results PDF', 'osq-stress-check' ); ?>
						</button>
					</div>
					<?php else : ?>
					<div class="osq-prompt-box">
						<p><?php esc_html_e( 'Please complete all questions to receive your stress evaluation results.', 'osq-stress-check' ); ?></p>
						<a href="<?php echo esc_url( home_url( '/osq-questionnaire/' ) ); ?>" class="osq-button osq-button--primary">
							<?php echo 'in_progress' === $status ? esc_html__( 'Resume Stress Check', 'osq-stress-check' ) : esc_html__( 'Start Stress Check', 'osq-stress-check' ); ?>
						</a>
					</div>
					<?php endif; ?>
				</div>
			</section>
			<?php endif; ?>

			<!-- ── INDIVIDUAL RESPONSES ────────────────────────────────── -->
			<?php if ( $can_view_responses ) : ?>
			<section id="ud-panel-responses" class="ud-panel <?php echo 'responses' === $initial_panel ? 'active' : ''; ?>">
				<div class="osq-panel-header">
					<div class="osq-filter-controls">
						<div class="osq-filters">
							<div class="osq-search-box">
								<input type="text" id="osq-employee-search" placeholder="<?php esc_attr_e( 'Search employees...', 'osq-stress-check' ); ?>" class="osq-input-search">
							</div>
							<select id="osq-org-filter-1" class="osq-select">
								<option value=""><?php esc_html_e( 'Filter by Organization', 'osq-stress-check' ); ?></option>
							</select>
							<select id="osq-status-filter" class="osq-select">
								<option value=""><?php esc_html_e( 'All Statuses', 'osq-stress-check' ); ?></option>
								<option value="completed"><?php esc_html_e( 'Completed Only', 'osq-stress-check' ); ?></option>
								<option value="pending"><?php esc_html_e( 'Pending Only', 'osq-stress-check' ); ?></option>
								<option value="high_stress"><?php esc_html_e( 'High Stress Only', 'osq-stress-check' ); ?></option>
							</select>
							<div class="osq-filter-actions">
								<button id="osq-apply-filters" class="osq-button osq-button--primary"><?php esc_html_e( 'Apply Filters', 'osq-stress-check' ); ?></button>
								<button id="osq-clear-filters" class="osq-button"><?php esc_html_e( 'Clear Filters', 'osq-stress-check' ); ?></button>
							</div>
						</div>
					</div>
					<div class="osq-bulk-actions">
						<label><input type="checkbox" id="osq-select-all"> <?php esc_html_e( 'Select All', 'osq-stress-check' ); ?></label>
						<select id="osq-bulk-action" class="osq-select">
							<option value=""><?php esc_html_e( 'Bulk Actions', 'osq-stress-check' ); ?></option>
							<option value="schedule_followup"><?php esc_html_e( 'Schedule Follow-up', 'osq-stress-check' ); ?></option>
							<option value="mark_completed"><?php esc_html_e( 'Mark as Completed', 'osq-stress-check' ); ?></option>
						</select>
						<button id="osq-execute-bulk" class="osq-button osq-button--secondary" disabled><?php esc_html_e( 'Execute', 'osq-stress-check' ); ?></button>
					</div>
				</div>
				<div class="osq-table-responsive">
					<table class="osq-admin-table" id="osq-responses-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'ID', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( 'Name', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( 'Department', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( 'Stress Status', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( 'Completion Date', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'osq-stress-check' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr><td colspan="6" class="osq-empty-table"><?php esc_html_e( 'Loading employee data...', 'osq-stress-check' ); ?></td></tr>
						</tbody>
					</table>
				</div>
			</section>
			<?php endif; ?>

			<!-- ── FOLLOW-UP TRACKING ──────────────────────────────────── -->
			<?php if ( $can_follow_up ) : ?>
			<section id="ud-panel-followup" class="ud-panel <?php echo 'followup' === $initial_panel ? 'active' : ''; ?>">
				<div class="osq-panel-header">
					<h3><?php esc_html_e( 'Follow-up Tracking', 'osq-stress-check' ); ?></h3>
					<div class="osq-search-box">
						<input type="text" id="osq-followup-search" placeholder="<?php esc_attr_e( 'Search follow-ups...', 'osq-stress-check' ); ?>" class="osq-input-search">
					</div>
				</div>
				<div class="osq-table-responsive">
					<table class="osq-admin-table" id="osq-followup-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Employee', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( 'Status', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( 'Scheduled Date', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( 'Notes', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'osq-stress-check' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr><td colspan="5" class="osq-empty-table"><?php esc_html_e( 'Loading follow-up data...', 'osq-stress-check' ); ?></td></tr>
						</tbody>
					</table>
				</div>
			</section>
			<?php endif; ?>

			<!-- ── EMPLOYEES ───────────────────────────────────────────── -->
			<?php if ( $can_manage_emp ) : ?>
			<section id="ud-panel-employees" class="ud-panel <?php echo 'employees' === $initial_panel ? 'active' : ''; ?>">
				<div class="osq-stats-grid">
					<div class="osq-stat-card">
						<h3><?php esc_html_e( 'Total Employees', 'osq-stress-check' ); ?></h3>
						<div class="osq-stat-value" id="ud-stat-total">--</div>
					</div>
					<div class="osq-stat-card">
						<h3><?php esc_html_e( 'Completion Rate', 'osq-stress-check' ); ?></h3>
						<div class="osq-stat-value" id="ud-stat-rate">--%</div>
					</div>
					<div class="osq-stat-card">
						<h3><?php esc_html_e( 'Pending Responses', 'osq-stress-check' ); ?></h3>
						<div class="osq-stat-value" id="ud-stat-pending">--</div>
					</div>
				</div>
				<div class="osq-panel-header">
					<div class="osq-search-box">
						<input type="text" id="osq-admin-employee-search" placeholder="<?php esc_attr_e( 'Search employees...', 'osq-stress-check' ); ?>" class="osq-input-search">
					</div>
					<div class="osq-filter-group">
						<select id="osq-employee-status-filter" class="osq-select osq-select--compact">
							<option value="all"><?php esc_html_e( 'All Statuses', 'osq-stress-check' ); ?></option>
							<option value="completed"><?php esc_html_e( 'Completed', 'osq-stress-check' ); ?></option>
							<option value="pending"><?php esc_html_e( 'Pending', 'osq-stress-check' ); ?></option>
						</select>
						<button type="button" id="osq-employee-apply-filters" class="osq-button osq-button--secondary">
							<?php esc_html_e( 'Apply Filters', 'osq-stress-check' ); ?>
						</button>
					</div>
				</div>
				<div class="osq-table-responsive">
					<table class="osq-admin-table" id="osq-employee-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'ID', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( 'Name', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( 'Department', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( 'Status', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( 'Last Active', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'osq-stress-check' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr><td colspan="6" class="osq-empty-table"><?php esc_html_e( 'Loading employee data...', 'osq-stress-check' ); ?></td></tr>
						</tbody>
					</table>
				</div>
			</section>

			<!-- ── CSV IMPORT ──────────────────────────────────────────── -->
			<section id="ud-panel-import" class="ud-panel <?php echo 'import' === $initial_panel ? 'active' : ''; ?>">
				<div class="osq-import-container">
					<div class="osq-import-dropzone" id="osq-csv-dropzone">
						<span class="dashicons dashicons-upload"></span>
						<p><?php esc_html_e( 'Drag and drop your CSV file here or click to browse', 'osq-stress-check' ); ?></p>
						<input type="file" id="osq-csv-file" accept=".csv" style="display:none">
					</div>
					<div id="osq-csv-message" class="osq-settings-message" style="display:none;margin-top:15px;"></div>
					<div class="osq-import-instructions">
						<h4><?php esc_html_e( 'CSV Format Instructions', 'osq-stress-check' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Required columns: employee_number, name, email', 'osq-stress-check' ); ?></li>
							<li><?php esc_html_e( 'Encoding: UTF-8 or Shift-JIS (Japanese)', 'osq-stress-check' ); ?></li>
							<li><?php esc_html_e( 'Max file size: 5MB', 'osq-stress-check' ); ?></li>
						</ul>
					</div>
				</div>
				<div class="osq-imported-users">
					<h4><?php esc_html_e( 'Imported Users (CSV)', 'osq-stress-check' ); ?></h4>
					<p class="osq-import-note"><?php esc_html_e( 'Passwords are shown for users created via CSV import only.', 'osq-stress-check' ); ?></p>
				</div>
				<div class="osq-import-danger-zone" style="margin-top:40px;border-top:2px solid #fee2e2;padding-top:20px;">
					<h4 style="color:#ef4444;display:flex;align-items:center;gap:8px;">
						<span class="dashicons dashicons-warning"></span>
						<?php esc_html_e( 'システムデータのリセット (Reset System Data)', 'osq-stress-check' ); ?>
					</h4>
					<p style="color:#64748b;font-size:14px;margin-bottom:20px;max-width:600px;line-height:1.6;">
						<?php esc_html_e( '警告：これにより、すべての従業員、アンケートの回答、およびログインアカウントが完全に削除されます。 (Warning: This will permanently delete all employees, their questionnaire responses, and their login accounts.)', 'osq-stress-check' ); ?>
					</p>
					<button type="button" id="osq-reset-all-data" class="osq-button osq-button--danger">
						<?php esc_html_e( 'すべての従業員データをリセット (Reset All Employee Data)', 'osq-stress-check' ); ?>
					</button>
					<div id="osq-reset-message" class="osq-settings-message" style="display:none;margin-top:15px;"></div>
				</div>
				<div class="osq-table-responsive" style="margin-top:20px;">
					<table class="osq-admin-table" id="osq-imported-users-table">
						<thead>
							<tr>
								<th><?php esc_html_e( '従業員ID (Employee ID)', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( '氏名 (Name)', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( 'パスワード (Password)', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( '登録日 (Created At)', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( '操作 (Actions)', 'osq-stress-check' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr><td colspan="5" class="osq-empty-table"><?php esc_html_e( 'Loading...', 'osq-stress-check' ); ?></td></tr>
						</tbody>
					</table>
				</div>
			</section>
			<?php endif; ?>

			<!-- ── GROUP ANALYSIS ──────────────────────────────────────── -->
			<?php if ( $can_analysis ) : ?>
			<section id="ud-panel-analysis" class="ud-panel <?php echo 'analysis' === $initial_panel ? 'active' : ''; ?>">
				<?php if ( $enable_group_analysis ) : ?>
				<div class="osq-analysis-section">
					<h4><?php esc_html_e( 'Group Analysis Summary', 'osq-stress-check' ); ?></h4>
					<div class="osq-table-responsive">
						<table class="osq-admin-table" id="osq-analysis-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Group', 'osq-stress-check' ); ?></th>
									<th><?php esc_html_e( 'Respondents', 'osq-stress-check' ); ?></th>
									<th><?php esc_html_e( 'High Stress Count', 'osq-stress-check' ); ?></th>
									<th><?php esc_html_e( 'High Stress Ratio', 'osq-stress-check' ); ?></th>
									<th><?php esc_html_e( 'Completion Rate', 'osq-stress-check' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'osq-stress-check' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr><td colspan="6" class="osq-empty-table"><?php esc_html_e( 'Loading group analysis...', 'osq-stress-check' ); ?></td></tr>
							</tbody>
						</table>
					</div>
					<div class="osq-compliance-alert">
						<span class="dashicons dashicons-warning"></span>
						<?php esc_html_e( 'Results are only shown for groups with 10 or more respondents to ensure privacy.', 'osq-stress-check' ); ?>
					</div>
				</div>
				<div class="osq-analysis-filters">
					<div class="osq-filter-group">
						<select id="osq-analysis-org-level" class="osq-select osq-select--compact">
							<option value="organization_1"><?php esc_html_e( 'Organization Level 1', 'osq-stress-check' ); ?></option>
							<option value="organization_2"><?php esc_html_e( 'Organization Level 2', 'osq-stress-check' ); ?></option>
							<option value="organization_3"><?php esc_html_e( 'Organization Level 3', 'osq-stress-check' ); ?></option>
						</select>
						<button type="button" id="osq-analysis-refresh" class="osq-button osq-button--primary"><?php esc_html_e( 'Refresh', 'osq-stress-check' ); ?></button>
					</div>
					<a href="#" id="osq-export-analysis-csv" class="osq-button osq-button--secondary"><?php esc_html_e( 'Download CSV', 'osq-stress-check' ); ?></a>
				</div>
				<div class="osq-analysis-section">
					<h4><?php esc_html_e( 'Participation Rate by Group', 'osq-stress-check' ); ?></h4>
					<div class="osq-table-responsive">
						<table class="osq-admin-table" id="osq-participation-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Group', 'osq-stress-check' ); ?></th>
									<th><?php esc_html_e( 'Total Employees', 'osq-stress-check' ); ?></th>
									<th><?php esc_html_e( 'Completed', 'osq-stress-check' ); ?></th>
									<th><?php esc_html_e( 'Completion Rate', 'osq-stress-check' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr><td colspan="4" class="osq-empty-table"><?php esc_html_e( 'Loading group analysis...', 'osq-stress-check' ); ?></td></tr>
							</tbody>
						</table>
					</div>
				</div>
				<?php else : ?>
				<p><?php esc_html_e( 'Group Analysis is currently disabled in settings.', 'osq-stress-check' ); ?></p>
				<?php endif; ?>
			</section>
			<?php endif; ?>

			<!-- ── SETTINGS ────────────────────────────────────────────── -->
			<?php if ( $can_settings ) : ?>
			<?php
			$current_language  = $osq_settings['language'] ?? 'ja';
			$session_timeout   = $osq_settings['session_timeout'] ?? 30;
			?>
			<section id="ud-panel-settings" class="ud-panel <?php echo 'settings' === $initial_panel ? 'active' : ''; ?>">
				<form id="osq-settings-form" class="osq-admin-form">
					<div class="osq-form-row">
						<label><?php esc_html_e( 'System Language', 'osq-stress-check' ); ?></label>
						<select name="language" class="osq-select">
							<option value="ja" <?php selected( $current_language, 'ja' ); ?>><?php esc_html_e( 'Japanese', 'osq-stress-check' ); ?></option>
							<option value="en" <?php selected( $current_language, 'en' ); ?>><?php esc_html_e( 'English', 'osq-stress-check' ); ?></option>
						</select>
					</div>
					<div class="osq-form-row">
						<label><?php esc_html_e( 'Session Timeout (Minutes)', 'osq-stress-check' ); ?></label>
						<input type="number" name="session_timeout" value="<?php echo esc_attr( $session_timeout ); ?>" class="osq-input-small">
					</div>
					<div class="osq-form-row osq-toggle-row">
						<div class="osq-toggle-info">
							<span class="osq-toggle-title"><?php esc_html_e( 'グループ分析機能 (Group Analysis Feature)', 'osq-stress-check' ); ?></span>
							<span class="osq-toggle-desc"><?php esc_html_e( 'グループ別のストレスチェック分析結果を有効にします。', 'osq-stress-check' ); ?></span>
						</div>
						<label class="osq-toggle-switch">
							<input type="checkbox" name="enable_group_analysis" value="1" <?php checked( $enable_group_analysis ); ?>>
							<span class="osq-toggle-slider"></span>
							<span class="osq-toggle-status"><?php echo $enable_group_analysis ? 'ON' : 'OFF'; ?></span>
						</label>
					</div>
					<div class="osq-form-actions">
						<button type="submit" class="osq-button osq-button--primary"><?php esc_html_e( 'Save Settings', 'osq-stress-check' ); ?></button>
						<div id="osq-settings-message" class="osq-settings-message" style="display:none;margin-top:15px;"></div>
					</div>
				</form>
			</section>
			<?php endif; ?>

			<!-- ── PROFILE ──────────────────────────────────────────────── -->
			<section id="ud-panel-profile" class="ud-panel <?php echo 'profile' === $initial_panel ? 'active' : ''; ?>">
				<div class="osq-panel-card" style="max-width:500px;">
					<form id="osq-officer-password-form" class="osq-admin-form">
						<h3 style="margin-top:0;margin-bottom:20px;"><?php esc_html_e( 'Change Password', 'osq-stress-check' ); ?></h3>
						<div class="osq-form-row">
							<label><?php esc_html_e( 'Current Password', 'osq-stress-check' ); ?></label>
							<input type="password" name="current_password" required class="osq-input">
						</div>
						<div class="osq-form-row">
							<label><?php esc_html_e( 'New Password', 'osq-stress-check' ); ?></label>
							<input type="password" name="new_password" required minlength="8" class="osq-input">
						</div>
						<div class="osq-form-row">
							<label><?php esc_html_e( 'Confirm New Password', 'osq-stress-check' ); ?></label>
							<input type="password" name="confirm_password" required minlength="8" class="osq-input">
						</div>
						<div class="osq-form-actions">
							<button type="submit" class="osq-button osq-button--primary"><?php esc_html_e( 'Update Password', 'osq-stress-check' ); ?></button>
							<div id="osq-officer-password-message" class="osq-settings-message" style="display:none;margin-top:15px;"></div>
						</div>
					</form>
				</div>
				<div class="osq-panel-card" style="max-width:500px;margin-top:20px;">
					<h3 style="margin-top:0;margin-bottom:20px;"><?php esc_html_e( 'Language Preference', 'osq-stress-check' ); ?></h3>
					<?php
					$cookie_lang = isset( $_COOKIE['osq_lang'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['osq_lang'] ) ) : '';
					$profile_lang = 'en_US' === $cookie_lang ? 'en' : ( 'ja' === $cookie_lang ? 'ja' : ( $osq_settings['language'] ?? 'ja' ) );
					?>
					<form method="get" action="">
						<div class="osq-form-row">
							<label><?php esc_html_e( 'System Language', 'osq-stress-check' ); ?></label>
							<select name="osq_lang" onchange="this.form.submit()" class="osq-select">
								<option value="ja" <?php selected( $profile_lang, 'ja' ); ?>><?php esc_html_e( 'Japanese', 'osq-stress-check' ); ?></option>
								<option value="en" <?php selected( $profile_lang, 'en' ); ?>><?php esc_html_e( 'English', 'osq-stress-check' ); ?></option>
							</select>
						</div>
					</form>
				</div>
			</section>

			<!-- ── COMPANIES ───────────────────────────────────────────── -->
			<?php if ( $can_companies ) : ?>
			<section id="ud-panel-companies" class="ud-panel <?php echo 'companies' === $initial_panel ? 'active' : ''; ?>">
				<div class="osq-panel-card">
					<h3 style="margin-top:0;"><?php esc_html_e( 'Company Management', 'osq-stress-check' ); ?></h3>
					<p><?php esc_html_e( 'Manage tenant companies for the multi-tenant OSQ platform.', 'osq-stress-check' ); ?></p>
					<a href="<?php echo esc_url( home_url( '/osq-companies/' ) ); ?>" class="osq-button osq-button--primary">
						<span class="dashicons dashicons-building"></span>
						<?php esc_html_e( 'Go to Company Manager', 'osq-stress-check' ); ?>
					</a>
				</div>
			</section>
			<?php endif; ?>

		</div><!-- /.osq-admin-content -->
	</main>
</div><!-- /#osq-unified-dashboard -->

<!-- ── Officer modals ──────────────────────────────────────────────────────── -->
<?php if ( $can_view_responses ) : ?>
<div id="osq-detailed-response-modal" class="osq-modal" style="display:none;">
	<div class="osq-modal-overlay"></div>
	<div class="osq-modal-content">
		<div class="osq-modal-header">
			<h3><?php esc_html_e( 'Employee Detailed Responses', 'osq-stress-check' ); ?></h3>
			<button class="osq-modal-close">&times;</button>
		</div>
		<div class="osq-modal-body">
			<div class="osq-loading-indicator" id="osq-response-loading"><?php esc_html_e( 'Loading...', 'osq-stress-check' ); ?></div>
			<div id="osq-response-details" style="display:none;">
				<div class="osq-employee-info">
					<h4><?php esc_html_e( 'Employee Details', 'osq-stress-check' ); ?></h4>
					<div id="osq-employee-basic-info"></div>
				</div>
				<div class="osq-response-section">
					<h4><?php esc_html_e( 'Response Details', 'osq-stress-check' ); ?></h4>
					<div class="osq-response-tabs">
						<button type="button" class="osq-tab-btn active" data-tab="A"><?php esc_html_e( 'Section A', 'osq-stress-check' ); ?></button>
						<button type="button" class="osq-tab-btn" data-tab="B"><?php esc_html_e( 'Section B', 'osq-stress-check' ); ?></button>
						<button type="button" class="osq-tab-btn" data-tab="C"><?php esc_html_e( 'Section C', 'osq-stress-check' ); ?></button>
						<button type="button" class="osq-tab-btn" data-tab="D"><?php esc_html_e( 'Section D', 'osq-stress-check' ); ?></button>
					</div>
					<div class="osq-questions-tabs">
						<div class="osq-questions-list osq-questions-tab active" id="osq-questions-tab-a"></div>
						<div class="osq-questions-list osq-questions-tab" id="osq-questions-tab-b"></div>
						<div class="osq-questions-list osq-questions-tab" id="osq-questions-tab-c"></div>
						<div class="osq-questions-list osq-questions-tab" id="osq-questions-tab-d"></div>
					</div>
				</div>
				<div class="osq-scoring-section">
					<h4><?php esc_html_e( 'Scoring Results', 'osq-stress-check' ); ?></h4>
					<div id="osq-scoring-results"></div>
				</div>
			</div>
		</div>
	</div>
</div>
<div id="osq-followup-modal" class="osq-modal" style="display:none;">
	<div class="osq-modal-overlay"></div>
	<div class="osq-modal-content">
		<div class="osq-modal-header">
			<h3><?php esc_html_e( 'Update Follow-up Status', 'osq-stress-check' ); ?></h3>
			<button class="osq-modal-close">&times;</button>
		</div>
		<div class="osq-modal-body">
			<form id="osq-followup-form">
				<input type="hidden" id="osq-followup-employee-id" value="">
				<div class="osq-form-row">
					<label><?php esc_html_e( 'Follow-up Status', 'osq-stress-check' ); ?></label>
					<select id="osq-followup-status" class="osq-select" required>
						<option value="Scheduled"><?php esc_html_e( 'Scheduled', 'osq-stress-check' ); ?></option>
						<option value="Completed"><?php esc_html_e( 'Completed', 'osq-stress-check' ); ?></option>
						<option value="Cancelled"><?php esc_html_e( 'Cancelled', 'osq-stress-check' ); ?></option>
					</select>
				</div>
				<div class="osq-form-row">
					<label><?php esc_html_e( 'Schedule Date', 'osq-stress-check' ); ?></label>
					<input type="datetime-local" id="osq-followup-schedule-date" class="osq-input">
				</div>
				<div class="osq-form-row">
					<label><?php esc_html_e( 'Notes', 'osq-stress-check' ); ?></label>
					<textarea id="osq-followup-notes" class="osq-textarea" rows="4"></textarea>
				</div>
				<div class="osq-form-actions">
					<button type="submit" class="osq-button osq-button--primary"><?php esc_html_e( 'Update Follow-up', 'osq-stress-check' ); ?></button>
					<button type="button" class="osq-button osq-modal-close-btn"><?php esc_html_e( 'Cancel', 'osq-stress-check' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>
<?php endif; ?>

<!-- ── Group analysis details modal ─────────────────────────────────────────── -->
<?php if ( $can_analysis && $enable_group_analysis ) : ?>
<div id="osq-group-analysis-modal" class="osq-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
	<div class="osq-modal-content" style="background:#fff;border-radius:12px;padding:30px;width:900px;max-width:95vw;max-height:90vh;overflow-y:auto;position:relative;">
		<span class="osq-modal-close dashicons dashicons-no-alt" style="position:absolute;top:20px;right:20px;cursor:pointer;font-size:24px;color:#64748b;"></span>
		<h3 id="osq-group-analysis-title" style="margin-top:0;color:#1e293b;font-size:20px;"><?php esc_html_e( 'Group Analysis Details', 'osq-stress-check' ); ?></h3>
		<div style="display:flex;gap:30px;margin-top:20px;flex-wrap:wrap;">
			<div style="flex:1;min-width:400px;max-width:500px;">
				<canvas id="osq-group-radar-chart"></canvas>
			</div>
			<div style="flex:1;min-width:300px;">
				<h4 style="margin-top:0;color:#475569;"><?php esc_html_e( 'Scale Average Scores', 'osq-stress-check' ); ?></h4>
				<table class="osq-admin-table" id="osq-group-scores-table" style="font-size:13px;">
					<thead><tr><th><?php esc_html_e( 'Scale', 'osq-stress-check' ); ?></th><th><?php esc_html_e( 'Average Score', 'osq-stress-check' ); ?></th></tr></thead>
					<tbody></tbody>
				</table>
			</div>
		</div>
	</div>
</div>
<?php endif; ?>

<!-- ── Employee PDF template (hidden) ────────────────────────────────────────── -->
<?php if ( ( $can_take_test || $can_view_results ) && $response && $response->is_complete ) : ?>
<div id="osq-pdf-template" style="display:none;padding:30px;font-family:'Hiragino Kaku Gothic Pro','Meiryo',sans-serif;color:#333;line-height:1.6;background:white;width:720px;box-sizing:border-box;">
	<div style="border-bottom:2px solid #007cba;padding-bottom:15px;margin-bottom:25px;">
		<table style="width:100%;border-collapse:collapse;">
			<tr>
				<td style="text-align:left;">
					<h1 style="color:#007cba;margin:0;font-size:24px;"><?php esc_html_e( 'Stress Check Results Report', 'osq-stress-check' ); ?></h1>
					<p style="margin:5px 0 0;color:#666;font-size:14px;"><?php esc_html_e( 'ストレスチェック結果報告書', 'osq-stress-check' ); ?></p>
				</td>
				<td style="text-align:right;vertical-align:bottom;">
					<p style="margin:0;color:#666;font-size:14px;">
						<strong><?php esc_html_e( 'Date:', 'osq-stress-check' ); ?></strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $response->completed_at ?? 'now' ) ) ); ?>
					</p>
				</td>
			</tr>
		</table>
	</div>
	<div style="margin-bottom:25px;">
		<p style="margin:0 0 5px;"><strong><?php esc_html_e( 'Name:', 'osq-stress-check' ); ?></strong> <?php echo esc_html( $employee->name ?? '' ); ?></p>
		<p style="margin:0;"><strong><?php esc_html_e( 'Employee ID:', 'osq-stress-check' ); ?></strong> <?php echo esc_html( $employee->employee_number ?? '' ); ?></p>
	</div>
	<div style="background:#f8fafc;padding:20px;border-radius:8px;margin-bottom:25px;border:1px solid #e2e8f0;border-left:5px solid <?php echo $is_high_stress ? '#ef4444' : '#10b981'; ?>;">
		<h2 style="margin-top:0;font-size:18px;color:<?php echo $is_high_stress ? '#b91c1c' : '#15803d'; ?>;border-bottom:1px solid #e2e8f0;padding-bottom:10px;margin-bottom:15px;">
			<?php esc_html_e( 'Overall Determination', 'osq-stress-check' ); ?>
		</h2>
		<p style="font-size:20px;font-weight:bold;margin:0 0 10px;color:<?php echo $is_high_stress ? '#ef4444' : '#10b981'; ?>;"><?php echo esc_html( $advice_title ); ?></p>
		<p style="margin:0;color:#334155;line-height:1.5;"><?php echo esc_html( $advice_text ); ?></p>
	</div>
	<?php if ( ! empty( $ai_advice ) ) :
		$pdf_advice = mb_strlen( $ai_advice ) > 400 ? mb_substr( $ai_advice, 0, 400 ) . '…' : $ai_advice;
	?>
	<div style="margin-bottom:20px;padding:15px;background:#f0f9ff;border-left:4px solid #0369a1;border-radius:4px;">
		<h3 style="margin:0 0 10px;font-size:15px;color:#0369a1;">AIセルフケアアドバイス</h3>
		<p style="margin:0;font-size:13px;line-height:1.7;white-space:pre-wrap;"><?php echo esc_html( $pdf_advice ); ?></p>
	</div>
	<?php endif; ?>
	<div style="font-size:12px;color:#999;margin-top:50px;text-align:center;border-top:1px solid #eee;padding-top:20px;">
		<p><?php printf( esc_html__( 'Generated by %s', 'osq-stress-check' ), get_bloginfo( 'name' ) ); ?></p>
	</div>
</div>
<?php endif; ?>

<style>
/* ── Layout ──────────────────────────────────────────────────────────────── */
.osq-admin-dashboard { display:flex; min-height:100vh; background:#f8fafc; font-family:'Inter',system-ui,sans-serif; margin:0; padding:0; width:100vw; max-width:100vw; }
.osq-admin-sidebar { width:260px; background:#1e293b; color:white; display:flex; flex-direction:column; flex-shrink:0; transition:transform 0.3s ease; }
.osq-sidebar-header { padding:30px 20px; text-align:center; border-bottom:1px solid rgba(255,255,255,0.1); }
.osq-logo { font-size:22px; font-weight:800; color:#38bdf8; letter-spacing:-1px; }
.osq-admin-nav ul { list-style:none; padding:20px 0; margin:0; }
.osq-admin-nav li { padding:14px 24px; cursor:pointer; display:flex; align-items:center; color:#94a3b8; transition:all 0.2s; font-weight:500; }
.osq-admin-nav li .dashicons { margin-right:12px; font-size:20px; }
.osq-admin-nav li:hover { color:white; background:rgba(255,255,255,0.05); }
.osq-admin-nav li.active { color:white; background:#334155; border-left:4px solid #38bdf8; }
.osq-nav-logout { margin-top:auto; padding:20px 24px; border-top:1px solid rgba(255,255,255,0.1); }
.osq-logout-btn { color:#94a3b8; text-decoration:none; display:flex; align-items:center; gap:10px; font-weight:500; transition:color 0.2s; }
.osq-logout-btn:hover { color:white; }

/* ── Main ─────────────────────────────────────────────────────────────────── */
.osq-admin-main { flex-grow:1; display:flex; flex-direction:column; overflow-x:hidden; }
.osq-admin-header { background:white; padding:20px 40px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 1px 3px rgba(0,0,0,0.1); }
.osq-admin-header h2 { margin:0; font-size:24px; color:#1e293b; font-weight:700; }
.osq-header-left { display:flex; align-items:center; gap:15px; }
.osq-user-welcome { color:#64748b; font-weight:500; }
.osq-admin-content { padding:40px; flex-grow:1; }

/* ── Unified panels ───────────────────────────────────────────────────────── */
.ud-panel { display:none; }
.ud-panel.active { display:block; animation:fadeIn 0.3s ease; }
@keyframes fadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }

/* ── Stats grid ───────────────────────────────────────────────────────────── */
.osq-stats-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:25px; margin-bottom:40px; }
.osq-stat-card { background:white; padding:25px; border-radius:12px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); }
.osq-stat-card h3 { margin:0 0 10px; font-size:14px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; }
.osq-stat-value { font-size:32px; font-weight:800; color:#1e293b; }

/* ── Tables ───────────────────────────────────────────────────────────────── */
.osq-admin-table { width:100%; background:white; border-radius:12px; border-collapse:collapse; overflow:hidden; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); }
.osq-admin-table th { text-align:left; padding:16px 24px; background:#f8fafc; color:#475569; font-weight:700; border-bottom:1px solid #e2e8f0; }
.osq-admin-table td { padding:16px 24px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.osq-empty-table { text-align:center; padding:40px !important; color:#94a3b8; }
.osq-table-responsive { overflow-x:auto; margin-bottom:30px; }

/* ── Panel header / filters ───────────────────────────────────────────────── */
.osq-panel-header { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:15px; margin-bottom:20px; }
.osq-search-box { display:flex; align-items:center; }
.osq-input-search { padding:10px 15px; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; width:240px; }
.osq-filter-group { display:flex; gap:10px; align-items:center; }
.osq-filter-controls { display:flex; flex-direction:column; gap:10px; }
.osq-filters { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
.osq-filter-actions { display:flex; gap:8px; }
.osq-bulk-actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.osq-select { padding:8px 12px; border:1px solid #e2e8f0; border-radius:6px; background:white; font-size:14px; }
.osq-select--compact { padding:6px 10px; }

/* ── Buttons ──────────────────────────────────────────────────────────────── */
.osq-button { display:inline-flex; align-items:center; gap:8px; text-decoration:none; font-weight:600; padding:10px 20px; border-radius:8px; transition:all 0.2s; font-size:14px; border:none; cursor:pointer; }
.osq-button--primary { background:#1e40af; color:white; }
.osq-button--primary:hover { background:#1e3a8a; }
.osq-button--secondary { background:#f1f5f9; color:#475569; }
.osq-button--secondary:hover { background:#e2e8f0; }
.osq-button--danger { background:#fee2e2; color:#dc2626; border:1px solid #fecaca; }
.osq-button--danger:hover { background:#fecaca; }
.osq-button--small { padding:6px 12px; font-size:13px; }
.osq-hamburger { background:none; border:none; cursor:pointer; padding:5px; color:#1e293b; }

/* ── Cards ────────────────────────────────────────────────────────────────── */
.osq-panel-card { background:white; padding:30px; border-radius:12px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom:30px; }

/* ── Status pills / badges ────────────────────────────────────────────────── */
.osq-status-pill { display:inline-block; padding:6px 16px; border-radius:20px; font-weight:700; font-size:14px; }
.osq-status-pill--not_started { background:#f1f5f9; color:#475569; }
.osq-status-pill--in_progress { background:#fef3c7; color:#92400e; }
.osq-status-pill--completed { background:#dcfce7; color:#166534; }
.osq-status-badge { background:#f1f5f9; color:#475569; padding:4px 10px; border-radius:12px; font-size:13px; font-weight:600; }
.osq-status-badge--success { background:#dcfce7; color:#166534; }
.osq-status-badge--completed { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
.osq-status-badge--cancelled { background:#fee2e2; color:#dc2626; border:1px solid #fecaca; }
.osq-status-badge--pending { background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; }
.osq-status-badge--scheduled { background:#fef3c7; color:#d97706; border:1px solid #fde68a; }

/* ── My-check panel ───────────────────────────────────────────────────────── */
.osq-greeting-hero { margin-bottom:30px; }
.osq-greeting-hero h3 { margin:0 0 5px; font-size:24px; color:#1e293b; }
.osq-result-alert { padding:25px; border-radius:10px; display:flex; align-items:flex-start; gap:20px; margin-bottom:30px; }
.osq-result-alert span.dashicons { font-size:32px; width:32px; height:32px; }
.osq-result-text strong { display:block; font-size:18px; margin-bottom:8px; }
.osq-result-text p { margin:0; line-height:1.6; }
.osq-result-alert--high { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
.osq-result-alert--normal { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
.osq-section-header { font-size:18px; margin:0 0 15px; color:#1e293b; border-bottom:2px solid #e2e8f0; padding-bottom:10px; display:flex; align-items:center; gap:8px; }
.osq-score-details-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:15px; }
.osq-score-method-card { background:#f8fafc; border-radius:8px; padding:20px; border:1px solid #e2e8f0; }
.osq-score-method-card h5 { margin:0 0 15px; font-size:15px; color:#334155; }
.osq-score-row { display:flex; justify-content:space-between; margin-bottom:8px; font-size:14px; border-bottom:1px dashed #cbd5e1; padding-bottom:4px; }
.osq-score-row--last { border-bottom:none; }
.osq-score-footer { margin-top:15px; padding-top:10px; border-top:1px solid #cbd5e1; text-align:right; }
.osq-ai-badge { background:#fdf2f8; color:#ec4899; font-size:11px; padding:2px 8px; border-radius:12px; border:1px solid #fbcfe8; font-weight:600; }
.osq-advice-card { background:linear-gradient(to right,#ffffff,#fdf2f8); border-radius:8px; padding:25px; border:1px solid #fbcfe8; }
.osq-advice-card p { margin:0; color:#475569; font-size:15px; line-height:1.6; }
.osq-download-box, .osq-prompt-box { text-align:center; padding:20px 0; }
.osq-spin { animation:spin 1s linear infinite; display:inline-block; }
@keyframes spin { from { transform:rotate(0deg); } to { transform:rotate(360deg); } }

/* ── Follow-up card ───────────────────────────────────────────────────────── */
.osq-follow-up-card { border-left:5px solid #0ea5e9; }
.osq-card-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; margin-bottom:10px; }
.osq-card-title { margin:0; color:#0284c7; display:flex; align-items:center; gap:8px; font-size:18px; }
.osq-follow-up-intro { margin-top:5px; margin-bottom:15px; color:#475569; font-weight:500; }
.osq-scheduled-date-box { background:#f0f9ff; padding:12px 16px; border-radius:6px; margin-bottom:15px; font-weight:600; color:#0369a1; display:flex; align-items:center; gap:8px; }
.osq-follow-up-notes { background:#f8fafc; padding:20px; border-radius:8px; color:#334155; line-height:1.6; font-size:15px; border:1px solid #e2e8f0; }

/* ── Import panel ─────────────────────────────────────────────────────────── */
.osq-import-container { margin-bottom:30px; }
.osq-import-dropzone { border:2px dashed #cbd5e1; border-radius:12px; padding:40px; text-align:center; cursor:pointer; transition:all 0.2s; }
.osq-import-dropzone:hover, .osq-import-dropzone.osq-dropzone--hover { border-color:#3b82f6; background:#eff6ff; }
.osq-import-dropzone .dashicons { font-size:36px; width:36px; height:36px; color:#94a3b8; margin-bottom:10px; }
.osq-import-instructions { margin-top:20px; padding:20px; background:#f8fafc; border-radius:8px; }
.osq-import-instructions h4 { margin:0 0 10px; color:#475569; }
.osq-import-instructions ul { margin:0; padding-left:20px; color:#64748b; font-size:14px; line-height:1.8; }
.osq-import-note { color:#94a3b8; font-size:13px; margin:0 0 15px; }

/* ── Analysis panel ───────────────────────────────────────────────────────── */
.osq-analysis-section { margin-bottom:30px; }
.osq-analysis-section h4 { margin:0 0 15px; color:#1e293b; }
.osq-analysis-filters { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:30px; }
.osq-compliance-alert { padding:15px 20px; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; color:#92400e; display:flex; align-items:center; gap:10px; margin-top:15px; font-size:14px; }

/* ── Settings form ────────────────────────────────────────────────────────── */
.osq-admin-form { max-width:600px; }
.osq-form-row { margin-bottom:20px; }
.osq-form-row label { display:block; margin-bottom:8px; font-weight:600; color:#1e293b; }
.osq-input, .osq-input-small { padding:10px 12px; border:1px solid #e2e8f0; border-radius:6px; font-size:14px; }
.osq-input { width:100%; box-sizing:border-box; }
.osq-input-small { width:120px; }
.osq-textarea { width:100%; padding:10px 12px; border:1px solid #e2e8f0; border-radius:6px; font-size:14px; resize:vertical; box-sizing:border-box; }
.osq-form-actions { display:flex; align-items:center; gap:20px; flex-wrap:wrap; }
.osq-settings-message { padding:10px 15px; border-radius:6px; font-size:14px; }
.osq-message--success { background:#dcfce7; color:#166534; }
.osq-message--error { background:#fee2e2; color:#dc2626; }

/* ── Toggle switch ────────────────────────────────────────────────────────── */
.osq-toggle-row { display:flex; justify-content:space-between; align-items:center; padding:15px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0; }
.osq-toggle-info { display:flex; flex-direction:column; gap:4px; }
.osq-toggle-title { font-weight:600; color:#1e293b; }
.osq-toggle-desc { font-size:13px; color:#64748b; }
.osq-toggle-switch { display:flex; align-items:center; gap:10px; cursor:pointer; }
.osq-toggle-switch input { display:none; }
.osq-toggle-slider { width:44px; height:24px; background:#cbd5e1; border-radius:12px; position:relative; transition:background 0.2s; }
.osq-toggle-slider::after { content:''; position:absolute; top:2px; left:2px; width:20px; height:20px; border-radius:50%; background:white; transition:transform 0.2s; }
.osq-toggle-switch input:checked + .osq-toggle-slider { background:#3b82f6; }
.osq-toggle-switch input:checked + .osq-toggle-slider::after { transform:translateX(20px); }
.osq-toggle-status { font-size:13px; font-weight:600; color:#64748b; }

/* ── Modals ───────────────────────────────────────────────────────────────── */
.osq-modal { display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:9999; }
.osq-modal-overlay { position:absolute; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); }
.osq-modal-content { position:relative; background:white; border-radius:12px; padding:30px; width:800px; max-width:95vw; max-height:90vh; overflow-y:auto; margin:5vh auto; z-index:1; }
.osq-modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:15px; }
.osq-modal-header h3 { margin:0; font-size:20px; color:#1e293b; }
.osq-modal-close { background:none; border:none; font-size:24px; cursor:pointer; color:#64748b; line-height:1; }
.osq-modal-close:hover { color:#1e293b; }
.osq-loading-indicator { text-align:center; padding:40px; color:#94a3b8; }
.osq-response-tabs, .osq-filter-actions { display:flex; gap:8px; margin-bottom:15px; flex-wrap:wrap; }
.osq-tab-btn { padding:6px 14px; border:1px solid #e2e8f0; border-radius:6px; background:white; cursor:pointer; font-size:13px; }
.osq-tab-btn.active { background:#1e40af; color:white; border-color:#1e40af; }
.osq-questions-tab { display:none; }
.osq-questions-tab.active { display:block; }
.osq-questions-list { max-height:300px; overflow-y:auto; }

/* ── Sidebar overlay (mobile) ─────────────────────────────────────────────── */
.osq-sidebar-overlay { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:99; }
.osq-sidebar-open .osq-sidebar-overlay { display:block; }
.osq-sidebar-open .osq-admin-sidebar { transform:translateX(0) !important; }

/* ── Responsive ───────────────────────────────────────────────────────────── */
@media (max-width:1024px) {
	.osq-admin-sidebar { width:80px; }
	.osq-admin-nav li span:not(.dashicons) { display:none; }
	.osq-admin-nav li .dashicons { margin-right:0; }
	.osq-logout-btn span:not(.dashicons) { display:none; }
}
@media (max-width:768px) {
	.osq-admin-dashboard { flex-direction:column; }
	.osq-admin-sidebar { width:100%; position:fixed; top:0; left:0; bottom:0; transform:translateX(-100%); z-index:100; }
	.osq-admin-nav ul { flex-direction:column; }
	.osq-admin-nav li span:not(.dashicons) { display:inline-block; }
	.osq-logout-btn span:not(.dashicons) { display:inline-block; }
	.osq-admin-header { padding:15px 20px; flex-direction:column; align-items:flex-start; gap:10px; }
	.osq-admin-content { padding:20px; }
	.osq-stats-grid { grid-template-columns:1fr; gap:15px; }
	.osq-score-details-grid { grid-template-columns:1fr; }
	.osq-panel-header { flex-direction:column; }
}
</style>

<script>
jQuery(document).ready(function($) {

	// ── Panel switching ──────────────────────────────────────────────────────
	var panelTitles = <?php echo wp_json_encode( $panel_titles ); ?>;

	function switchPanel(panelId) {
		$('.ud-panel').removeClass('active');
		$('#ud-panel-' + panelId).addClass('active');
		$('.osq-admin-nav li[data-panel]').removeClass('active');
		$('.osq-admin-nav li[data-panel="' + panelId + '"]').addClass('active');
		$('#osq-tab-title').text(panelTitles[panelId] || '');
		history.replaceState(null, '', '?panel=' + panelId);
	}

	$('.osq-admin-nav li[data-panel]').on('click', function() {
		var panelId = $(this).data('panel');
		if (!panelId) return;
		switchPanel(panelId);
	});

	// ── Mobile hamburger ─────────────────────────────────────────────────────
	$('#osq-mobile-toggle, #osq-sidebar-overlay').on('click', function() {
		$('#osq-unified-dashboard').toggleClass('osq-sidebar-open');
	});

	<?php if ( $can_manage_emp || $can_analysis ) : ?>
	// ── Admin AJAX functions ─────────────────────────────────────────────────
	var ajaxVars = osq_admin_vars;
	var i18n = ajaxVars.i18n;
	var orgLabels = i18n.org_labels || {};

	function translateOrgLabel(label) {
		if (!label) return '-';
		return orgLabels[label] || label;
	}

	<?php if ( $can_manage_emp ) : ?>
	loadStats();
	loadEmployees();
	loadImportedUsers();
	<?php endif; ?>
	<?php if ( $can_analysis && $enable_group_analysis ) : ?>
	loadGroupAnalysis();
	<?php endif; ?>

	<?php if ( $can_manage_emp ) : ?>
	function loadStats() {
		$.ajax({
			url: ajaxVars.ajax_url, type: 'GET',
			data: { action: 'osq_admin_get_stats', nonce: ajaxVars.nonce },
			success: function(response) {
				if (response.success) {
					var data = response.data;
					$('#ud-stat-total').text(data.total_employees);
					$('#ud-stat-rate').text(data.completion_rate + '%');
					$('#ud-stat-pending').text(data.pending);
				}
			}
		});
	}

	function loadEmployees() {
		var $tbody = $('#osq-employee-table tbody');
		var statusFilter = $('#osq-employee-status-filter').val() || 'all';
		$tbody.empty().append('<tr><td colspan="6" class="osq-empty-table">' + i18n.loading + '</td></tr>');
		$.ajax({
			url: ajaxVars.ajax_url, type: 'GET',
			data: { action: 'osq_admin_get_employees', nonce: ajaxVars.nonce, status: statusFilter },
			success: function(response) {
				if (!response.success) return;
				$tbody.empty();
				var employees = response.data.employees;
				if (employees.length === 0) {
					$tbody.append('<tr><td colspan="6" class="osq-empty-table">' + i18n.no_employees + '</td></tr>');
					return;
				}
				employees.forEach(function(emp) {
					var statusLabel = emp.is_complete == 1
						? '<span class="osq-status-badge osq-status-badge--success">' + i18n.completed + '</span>'
						: '<span class="osq-status-badge">' + i18n.pending + '</span>';
					var decodedName = $('<div/>').html(emp.name).text();
					var decodedOrg1 = $('<div/>').html(emp.organization_1 || '-').text();
					$tbody.append('<tr><td>' + emp.employee_number + '</td><td><strong>' + decodedName + '</strong></td><td>' + translateOrgLabel(decodedOrg1) + '</td><td>' + statusLabel + '</td><td>' + (emp.completed_at || '-') + '</td><td><button class="osq-button osq-button--danger osq-button--small osq-delete-employee" data-id="' + emp.employee_id + '">' + i18n.csv_delete + '</button></td></tr>');
				});
			}
		});
	}

	function loadImportedUsers() {
		var $tbody = $('#osq-imported-users-table tbody');
		$tbody.empty().append('<tr><td colspan="5" class="osq-empty-table">' + i18n.loading + '</td></tr>');
		function escHtml(v) { return $('<div/>').text(v != null ? v : '').html(); }
		$.ajax({
			url: ajaxVars.ajax_url, type: 'GET',
			data: { action: 'osq_admin_get_imported_users', nonce: ajaxVars.nonce },
			success: function(response) {
				if (!response.success) { $tbody.empty().append('<tr><td colspan="5" class="osq-empty-table">' + (i18n.csv_import_failed || 'Failed.') + '</td></tr>'); return; }
				var users = response.data.users || [];
				$tbody.empty();
				if (users.length === 0) { $tbody.append('<tr><td colspan="5" class="osq-empty-table">' + (i18n.csv_no_imports || 'No imported users.') + '</td></tr>'); return; }
				users.forEach(function(user) {
					$tbody.append('<tr><td>' + escHtml(user.employee_number || '-') + '</td><td>' + escHtml(user.name || '-') + '</td><td><code>' + escHtml(user.password || '-') + '</code></td><td>' + escHtml(user.created_at || '-') + '</td><td><button class="osq-button osq-button--secondary osq-import-delete" data-user-id="' + user.user_id + '">' + (i18n.csv_delete || 'Delete') + '</button></td></tr>');
				});
			},
			error: function() { $tbody.empty().append('<tr><td colspan="5" class="osq-empty-table">' + (i18n.csv_import_failed || 'Failed.') + '</td></tr>'); }
		});
	}

	$('#osq-employee-apply-filters').on('click', function() { loadEmployees(); });
	$('#osq-employee-status-filter').on('change', function() { loadEmployees(); });
	$('#osq-admin-employee-search').on('input', function() {
		var term = $(this).val().normalize('NFKC').toLowerCase();
		$('#osq-employee-table tbody tr').each(function() {
			if ($(this).find('.osq-empty-table').length > 0) return;
			$(this).toggle($(this).text().normalize('NFKC').toLowerCase().indexOf(term) > -1);
		});
	});

	// CSV Dropzone
	$('#osq-csv-dropzone').on('click', function(e) {
		if (!$(e.target).is('#osq-csv-file')) { $('#osq-csv-file').click(); }
	});
	$('#osq-csv-file').on('change', function(e) {
		if (e.target.files[0]) { uploadCsv(e.target.files[0]); }
	});
	$('#osq-csv-dropzone').on('dragover', function(e) { e.preventDefault(); $(this).addClass('osq-dropzone--hover'); });
	$('#osq-csv-dropzone').on('dragleave drop', function(e) { e.preventDefault(); $(this).removeClass('osq-dropzone--hover'); });
	$('#osq-csv-dropzone').on('drop', function(e) {
		var files = e.originalEvent.dataTransfer.files;
		if (files && files[0]) { uploadCsv(files[0]); }
	});

	function uploadCsv(file) {
		var $message = $('#osq-csv-message');
		var formData = new FormData();
		formData.append('action', 'osq_admin_import_csv');
		formData.append('nonce', ajaxVars.nonce);
		formData.append('csv_file', file);
		$message.removeClass('osq-message--error osq-message--success').text(i18n.csv_uploading || 'Uploading...').show();
		$.ajax({
			url: ajaxVars.ajax_url, type: 'POST', data: formData, processData: false, contentType: false,
			success: function(response) {
				if (response.success) {
					var result = response.data.result || {};
					var errorCount = (result.errors || []).length;
					var summary = (i18n.csv_import_complete || 'Done.') + ' ' + (i18n.csv_import_success || 'Imported') + ': ' + (result.success || 0) + ', ' + (i18n.csv_import_skipped || 'Skipped') + ': ' + (result.skipped || 0) + ', ' + (i18n.csv_import_errors || 'Errors') + ': ' + errorCount;
					var detail = '';
					if (errorCount > 0) {
						var preview = result.errors.slice(0, 5).join(' ');
						var more = Math.max(0, errorCount - 5);
						detail = '\n' + (i18n.csv_error_details || 'Errors:') + ' ' + preview + (more > 0 ? ' (' + (i18n.csv_error_more || 'and') + ' ' + more + ' ' + (i18n.csv_error_more_items || 'more') + ')' : '');
					}
					$message.removeClass('osq-message--error').addClass('osq-message--success').text(summary + detail).show();
					loadStats(); loadEmployees(); loadImportedUsers();
				} else {
					$message.removeClass('osq-message--success').addClass('osq-message--error').text((response.data && response.data.message) || (i18n.csv_import_failed || 'Failed.')).show();
				}
			},
			error: function(xhr) {
				var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
				$message.removeClass('osq-message--success').addClass('osq-message--error').text(msg || (i18n.csv_import_failed || 'Failed.')).show();
			}
		});
	}

	$(document).on('click', '.osq-import-delete', function() {
		var userId = $(this).data('user-id');
		if (!userId || !confirm(i18n.csv_delete_confirm || 'Delete?')) return;
		$.ajax({
			url: ajaxVars.ajax_url, type: 'POST',
			data: { action: 'osq_admin_delete_imported_user', nonce: ajaxVars.nonce, user_id: userId },
			success: function(response) {
				if (response.success) { loadImportedUsers(); }
				else { alert((response.data && response.data.message) || (i18n.csv_delete_failed || 'Delete failed.')); }
			},
			error: function() { alert(i18n.csv_delete_failed || 'Delete failed.'); }
		});
	});

	$(document).on('click', '.osq-delete-employee', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var empId = $btn.data('id');
		if (!confirm('この従業員データを削除しますか？')) return;
		$btn.prop('disabled', true).text('...');
		$.ajax({
			url: ajaxVars.ajax_url, type: 'POST',
			data: { action: 'osq_admin_delete_employee', nonce: ajaxVars.nonce, employee_id: empId },
			success: function(response) {
				if (response.success) { loadEmployees(); loadStats(); }
				else { alert('Error: ' + response.data.message); $btn.prop('disabled', false).text(i18n.csv_delete); }
			},
			error: function() { alert('System error.'); $btn.prop('disabled', false).text(i18n.csv_delete); }
		});
	});
	<?php endif; // can_manage_emp ?>

	<?php if ( $can_analysis && $enable_group_analysis ) : ?>
	function loadGroupAnalysis() {
		var orgLevel = $('#osq-analysis-org-level').val() || 'organization_1';
		var $ab = $('#osq-analysis-table tbody');
		var $pb = $('#osq-participation-table tbody');
		$ab.empty().append('<tr><td colspan="6" class="osq-empty-table">' + (i18n.analysis_loading || 'Loading...') + '</td></tr>');
		$pb.empty().append('<tr><td colspan="4" class="osq-empty-table">' + (i18n.analysis_loading || 'Loading...') + '</td></tr>');
		$.ajax({
			url: ajaxVars.ajax_url, type: 'GET',
			data: { action: 'osq_admin_get_group_analysis', nonce: ajaxVars.nonce, org_level: orgLevel },
			success: function(response) {
				if (!response.success) return;
				window.osq_analysis_data = response.data.analysis || [];
				var aRows = response.data.analysis || [];
				var pRows = response.data.participation || [];
				$ab.empty();
				if (aRows.length === 0) {
					$ab.append('<tr><td colspan="6" class="osq-empty-table">' + (i18n.analysis_empty || 'No groups.') + '</td></tr>');
				} else {
					aRows.forEach(function(row, idx) {
						var rate = Math.round((row.completion_rate || 0) * 1000) / 10;
						$ab.append('<tr><td>' + translateOrgLabel(row.group_label) + '</td><td>' + row.respondent_count + '</td><td>' + row.high_stress_count + '</td><td>' + row.high_stress_ratio + '%</td><td>' + rate + '%</td><td><button class="osq-button osq-button--secondary osq-view-group-details" data-index="' + idx + '">' + (i18n.label_view_details || 'View Details') + '</button></td></tr>');
					});
				}
				$pb.empty();
				if (pRows.length === 0) {
					$pb.append('<tr><td colspan="4" class="osq-empty-table">' + (i18n.participation_empty || 'No data.') + '</td></tr>');
				} else {
					pRows.forEach(function(row) {
						var rate = Math.round((row.completion_rate || 0) * 1000) / 10;
						$pb.append('<tr><td>' + translateOrgLabel(row.group_label) + '</td><td>' + row.total + '</td><td>' + row.completed + '</td><td>' + rate + '%</td></tr>');
					});
				}
			}
		});
	}

	$('#osq-analysis-refresh, #osq-analysis-org-level').on('click change', function() { loadGroupAnalysis(); });

	$('#osq-export-analysis-csv').on('click', function(e) {
		e.preventDefault();
		var orgLevel = $('#osq-analysis-org-level').val() || 'organization_1';
		window.location.href = ajaxVars.ajax_url + '?action=osq_admin_export_group_analysis_csv&nonce=' + ajaxVars.nonce + '&org_level=' + encodeURIComponent(orgLevel);
	});

	// Group analysis radar chart modal
	var groupRadarChart = null;
	$(document).on('click', '.osq-view-group-details', function(e) {
		e.preventDefault();
		var rowData = window.osq_analysis_data[$(this).data('index')];
		if (!rowData) return;
		$('#osq-group-analysis-title').text(translateOrgLabel(rowData.group_label) + ' - Analysis Details');
		var $sb = $('#osq-group-scores-table tbody');
		$sb.empty();
		var scaleOrder = ['quantitative_demands','qualitative_demands','physical_workload','interpersonal_stress','environment_stress','job_control','skill_utilization','job_fit','reward','vigor','irritability','fatigue','anxiety','depression','physical_complaints','supervisor_support','colleague_support','family_support'];
		var scaleLabels = { quantitative_demands:'Quantitative Demands', qualitative_demands:'Qualitative Demands', physical_workload:'Physical Workload', interpersonal_stress:'Interpersonal', environment_stress:'Environment', job_control:'Job Control', skill_utilization:'Skill Use', job_fit:'Job Fit', reward:'Reward', vigor:'Vigor', irritability:'Irritability', fatigue:'Fatigue', anxiety:'Anxiety', depression:'Depression', physical_complaints:'Physical Complaints', supervisor_support:'Supervisor Support', colleague_support:'Colleague Support', family_support:'Family Support' };
		var chartLabels = [], chartData = [];
		scaleOrder.forEach(function(key) {
			var val = rowData.scale_averages[key] !== undefined ? rowData.scale_averages[key] : '-';
			var raw = parseFloat(val);
			$sb.append('<tr><td>' + scaleLabels[key] + '</td><td><strong>' + val + '</strong></td></tr>');
			chartLabels.push(scaleLabels[key]);
			chartData.push(isNaN(raw) ? 0 : raw);
		});
		var ctx = document.getElementById('osq-group-radar-chart').getContext('2d');
		if (groupRadarChart) { groupRadarChart.destroy(); }
		if (typeof Chart !== 'undefined') {
			groupRadarChart = new Chart(ctx, {
				type: 'radar',
				data: { labels: chartLabels, datasets: [{ label: 'Average Score', data: chartData, backgroundColor: 'rgba(56,189,248,0.2)', borderColor: 'rgba(56,189,248,1)', pointBackgroundColor: 'rgba(56,189,248,1)', pointBorderColor: '#fff', pointHoverBackgroundColor: '#fff', pointHoverBorderColor: 'rgba(56,189,248,1)' }] },
				options: { responsive: true, maintainAspectRatio: false, scales: { r: { angleLines: { display: true }, suggestedMin: 1, suggestedMax: 4 } } }
			});
		}
		$('#osq-group-analysis-modal').css('display', 'flex').hide().fadeIn(200);
	});
	$('.osq-modal-close').on('click', function() { $('#osq-group-analysis-modal').fadeOut(200); });
	$('#osq-group-analysis-modal').on('click', function(e) { if (e.target === this) { $(this).fadeOut(200); } });
	<?php endif; // can_analysis ?>

	<?php if ( $can_settings ) : ?>
	// Settings form — saves to DB via AJAX (admin capability)
	$('input[name="enable_group_analysis"]').on('change', function() {
		$(this).closest('.osq-toggle-switch').find('.osq-toggle-status').text($(this).is(':checked') ? 'ON' : 'OFF');
	});
	$('#osq-settings-form').on('submit', function(e) {
		e.preventDefault();
		var $form = $(this);
		var $message = $('#osq-settings-message');
		var $btn = $form.find('button[type="submit"]');
		var origText = $btn.text();
		$btn.prop('disabled', true).text('Saving...');
		$.ajax({
			url: ajaxVars.ajax_url, type: 'POST',
			data: {
				action: 'osq_admin_save_settings', nonce: ajaxVars.nonce,
				language: $form.find('select[name="language"]').val(),
				session_timeout: $form.find('input[name="session_timeout"]').val(),
				enable_group_analysis: $form.find('input[name="enable_group_analysis"]').is(':checked') ? 1 : 0
			},
			success: function(response) {
				if (response.success) {
					$message.removeClass('osq-message--error').addClass('osq-message--success').text(response.data.message).show();
					var lang = response.data.language;
					document.cookie = 'osq_lang=' + (lang === 'ja' ? 'ja' : 'en_US') + '; path=/; max-age=' + (365 * 24 * 60 * 60);
					setTimeout(function() { location.reload(); }, 1500);
				} else {
					$message.removeClass('osq-message--success').addClass('osq-message--error').text((response.data && response.data.message) || 'Error saving settings').show();
				}
			},
			error: function() { $message.removeClass('osq-message--success').addClass('osq-message--error').text('Network error').show(); },
			complete: function() { $btn.prop('disabled', false).text(origText); }
		});
	});
	<?php endif; // can_settings ?>
	<?php endif; // can_manage_emp || can_analysis ?>

	<?php if ( ( $can_take_test || $can_view_results ) && ! empty( $method2_data['eval_points'] ) ) : ?>
	// Employee radar chart
	(function() {
		var labels = ['量的負担','質的負担','身体的負担','対人関係ストレス','職場環境ストレス','仕事のコントロール','技能の活用','仕事の適合性','報酬','活気','イライラ感','疲労感','不安感','抑うつ感','身体愁訴','上司サポート','同僚サポート','家族サポート'];
		var scaleOrder = ['quantitative_demands','qualitative_demands','physical_workload','interpersonal_stress','environment_stress','job_control','skill_utilization','job_fit','reward','vigor','irritability','fatigue','anxiety','depression','physical_complaints','supervisor_support','colleague_support','family_support'];
		var currentPoints = <?php echo wp_json_encode( $method2_data['eval_points'] ?? new stdClass() ); ?>;
		var prevPoints    = <?php echo wp_json_encode( $prev_year_eval_points ?: new stdClass() ); ?>;
		var hasPrev       = <?php echo ! empty( $prev_year_eval_points ) ? 'true' : 'false'; ?>;
		var currentData   = scaleOrder.map(function(k) { return currentPoints[k] !== undefined ? currentPoints[k] : null; });
		var prevData      = hasPrev ? scaleOrder.map(function(k) { return prevPoints[k] !== undefined ? prevPoints[k] : null; }) : [];
		var datasets      = [{ label: '今年度', data: currentData, backgroundColor: 'rgba(59,130,246,0.2)', borderColor: 'rgba(59,130,246,1)', borderWidth: 2, pointBackgroundColor: 'rgba(59,130,246,1)' }];
		if (hasPrev) {
			datasets.push({ label: '前年度', data: prevData, backgroundColor: 'rgba(0,0,0,0)', borderColor: 'rgba(249,115,22,0.85)', borderWidth: 2, borderDash: [6,4], pointBackgroundColor: 'rgba(249,115,22,0.85)' });
		}
		var ctx = document.getElementById('osq-radar-chart');
		if (ctx && typeof Chart !== 'undefined') {
			new Chart(ctx, { type: 'radar', data: { labels: labels, datasets: datasets }, options: { scales: { r: { min: 1, max: 5, ticks: { stepSize: 1 } } }, plugins: { legend: { position: 'bottom' } } } });
		}
	})();
	<?php endif; ?>

});
</script>

<?php wp_footer(); ?>
</body>
</html>
