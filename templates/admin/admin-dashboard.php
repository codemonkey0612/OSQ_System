<?php
/**
 * Administrator Dashboard Template.
 *
 * @package OSQ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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

<?php

$current_user = wp_get_current_user();
$db = \OSQ\Plugin::get_instance()->db();
$osq_settings = get_option( 'osq_settings', array() );
$enable_group_analysis = isset($osq_settings['enable_group_analysis']) ? (bool) $osq_settings['enable_group_analysis'] : true;

// Determine which inner tab to open (supports ?tab= URL param from NavigationBuilder links).
$allowed_tabs   = array( 'overview', 'employees', 'import', 'analysis', 'settings' );
$initial_tab    = in_array( $_GET['tab'] ?? '', $allowed_tabs, true ) ? $_GET['tab'] : 'overview';

// Map inner tab → NavigationBuilder sidebar active key.
$sidebar_key_map = array(
	'employees' => 'manage',
	'analysis'  => 'analysis',
	'settings'  => 'settings',
);
$active_sidebar_key = $sidebar_key_map[ $initial_tab ] ?? '';
?>

<div id="osq-admin-dashboard" class="osq-ui-container osq-admin-dashboard">
	<div id="osq-sidebar-overlay" class="osq-sidebar-overlay"></div>
	<?php \OSQ\Auth\NavigationBuilder::render_sidebar( $active_sidebar_key ); ?>

	<main class="osq-admin-main">
		<header class="osq-admin-header">
			<div class="osq-header-left">
				<button id="osq-mobile-toggle" class="osq-hamburger">
					<span class="dashicons dashicons-menu"></span>
				</button>
				<h2 id="osq-tab-title"><?php
					$tab_labels = array(
						'overview'  => __( 'Overview', 'osq-stress-check' ),
						'employees' => __( 'Employees', 'osq-stress-check' ),
						'import'    => __( 'CSV Import', 'osq-stress-check' ),
						'analysis'  => __( 'Group Analysis', 'osq-stress-check' ),
						'settings'  => __( 'Settings', 'osq-stress-check' ),
					);
					echo esc_html( $tab_labels[ $initial_tab ] ?? __( 'Overview', 'osq-stress-check' ) );
				?></h2>
			</div>
			<div class="osq-header-right">
				<span class="osq-user-welcome"><?php printf( esc_html__( 'Hello, %s', 'osq-stress-check' ), esc_html( $current_user->display_name ) ); ?></span>
			</div>
		</header>

		<nav class="osq-inner-tab-nav">
			<ul>
				<li class="<?php echo 'overview' === $initial_tab ? 'active' : ''; ?>" data-tab="overview">
					<span class="dashicons dashicons-dashboard"></span>
					<span><?php esc_html_e( 'Overview', 'osq-stress-check' ); ?></span>
				</li>
				<li class="<?php echo 'employees' === $initial_tab ? 'active' : ''; ?>" data-tab="employees">
					<span class="dashicons dashicons-groups"></span>
					<span><?php esc_html_e( 'Employees', 'osq-stress-check' ); ?></span>
				</li>
				<li class="<?php echo 'import' === $initial_tab ? 'active' : ''; ?>" data-tab="import">
					<span class="dashicons dashicons-upload"></span>
					<span><?php esc_html_e( 'CSV Import', 'osq-stress-check' ); ?></span>
				</li>
				<?php if ( $enable_group_analysis ) : ?>
				<li class="<?php echo 'analysis' === $initial_tab ? 'active' : ''; ?>" data-tab="analysis">
					<span class="dashicons dashicons-chart-bar"></span>
					<span><?php esc_html_e( 'Group Analysis', 'osq-stress-check' ); ?></span>
				</li>
				<?php endif; ?>
				<li class="<?php echo 'settings' === $initial_tab ? 'active' : ''; ?>" data-tab="settings">
					<span class="dashicons dashicons-admin-settings"></span>
					<span><?php esc_html_e( 'Settings', 'osq-stress-check' ); ?></span>
				</li>
			</ul>
		</nav>

		<div class="osq-admin-content">
			<!-- Overview Tab -->
			<section id="tab-overview" class="osq-tab-panel <?php echo 'overview' === $initial_tab ? 'active' : ''; ?>">
				<div class="osq-stats-grid">
					<div class="osq-stat-card">
						<h3><?php esc_html_e( 'Total Employees', 'osq-stress-check' ); ?></h3>
						<div class="osq-stat-value">--</div>
					</div>
					<div class="osq-stat-card">
						<h3><?php esc_html_e( 'Completion Rate', 'osq-stress-check' ); ?></h3>
						<div class="osq-stat-value">--%</div>
					</div>
					<div class="osq-stat-card">
						<h3><?php esc_html_e( 'Pending Responses', 'osq-stress-check' ); ?></h3>
						<div class="osq-stat-value">--</div>
					</div>
				</div>
				<div class="osq-dashboard-placeholder">
					<p><?php esc_html_e( 'System overview and quick actions will appear here.', 'osq-stress-check' ); ?></p>
				</div>
			</section>

			<!-- Employees Tab -->
			<section id="tab-employees" class="osq-tab-panel <?php echo 'employees' === $initial_tab ? 'active' : ''; ?>">
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
							<tr>
								<td colspan="5" class="osq-empty-table"><?php esc_html_e( 'Loading employee data...', 'osq-stress-check' ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			</section>

			<!-- Import Tab -->
			<section id="tab-import" class="osq-tab-panel <?php echo 'import' === $initial_tab ? 'active' : ''; ?>">
				<div class="osq-import-container">
					<div class="osq-import-dropzone" id="osq-csv-dropzone">
						<span class="dashicons dashicons-upload"></span>
						<p><?php esc_html_e( 'Drag and drop your CSV file here or click to browse', 'osq-stress-check' ); ?></p>
						<input type="file" id="osq-csv-file" accept=".csv" style="display:none">
					</div>
					<div id="osq-csv-message" class="osq-settings-message" style="display:none; margin-top: 15px;"></div>
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
				<div class="osq-import-danger-zone" style="margin-top: 40px; border-top: 2px solid #fee2e2; padding-top: 20px;">
					<h4 style="color: #ef4444; display: flex; align-items: center; gap: 8px;">
						<span class="dashicons dashicons-warning"></span>
						<?php esc_html_e( 'システムデータのリセット (Reset System Data)', 'osq-stress-check' ); ?>
					</h4>
					<p style="color: #64748b; font-size: 14px; margin-bottom: 20px; max-width: 600px; line-height: 1.6;">
						<?php esc_html_e( '警告：これにより、すべての従業員、アンケートの回答、およびログインアカウントが完全に削除されます。この操作は新しいテストサイクルを開始するためのもので、取り消すことはできません。 (Warning: This will permanently delete all employees, their questionnaire responses, and their login accounts. This action is intended for starting a new testing cycle and cannot be undone.)', 'osq-stress-check' ); ?>
					</p>
					<button type="button" id="osq-reset-all-data" class="osq-button osq-button--danger">
						<?php esc_html_e( 'すべての従業員データをリセット (Reset All Employee Data)', 'osq-stress-check' ); ?>
					</button>
					<div id="osq-reset-message" class="osq-settings-message" style="display:none; margin-top: 15px;"></div>
					<div class="osq-table-responsive">
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
								<tr>
									<td colspan="5" class="osq-empty-table"><?php esc_html_e( 'Loading...', 'osq-stress-check' ); ?></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</section>

			<?php if ( $enable_group_analysis ) : ?>
			<!-- Analysis Tab -->
			<section id="tab-analysis" class="osq-tab-panel <?php echo 'analysis' === $initial_tab ? 'active' : ''; ?>">
				<div class="osq-analysis-section">
					<h4><?php esc_html_e( 'Group Analysis Summary (10+ Respondents)', 'osq-stress-check' ); ?></h4>
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
								<tr>
									<td colspan="6" class="osq-empty-table"><?php esc_html_e( 'Loading group analysis...', 'osq-stress-check' ); ?></td>
								</tr>
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
						<button type="button" id="osq-analysis-refresh" class="osq-button osq-button--primary">
							<?php esc_html_e( 'Refresh', 'osq-stress-check' ); ?>
						</button>
					</div>
					<a href="#" id="osq-export-analysis-csv" class="osq-button osq-button--secondary">
						<?php esc_html_e( 'Download CSV', 'osq-stress-check' ); ?>
					</a>
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
								<tr>
									<td colspan="4" class="osq-empty-table"><?php esc_html_e( 'Loading group analysis...', 'osq-stress-check' ); ?></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</section>
			<?php endif; ?>

			<!-- Settings Tab -->
			<section id="tab-settings" class="osq-tab-panel <?php echo 'settings' === $initial_tab ? 'active' : ''; ?>">
				<?php 
				$current_language = $osq_settings['language'] ?? 'ja';
				$session_timeout = $osq_settings['session_timeout'] ?? 30;
				?>
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
							<span class="osq-toggle-desc"><?php esc_html_e( 'グループ別のストレスチェック分析結果を有効にします。 (Enable stress check analysis results by group.)', 'osq-stress-check' ); ?></span>
						</div>
						<label class="osq-toggle-switch">
							<input type="checkbox" name="enable_group_analysis" value="1" <?php checked( $enable_group_analysis ); ?>>
							<span class="osq-toggle-slider"></span>
							<span class="osq-toggle-status"><?php echo $enable_group_analysis ? 'ON' : 'OFF'; ?></span>
						</label>
					</div>
					<div class="osq-form-actions">
						<button type="submit" class="osq-button osq-button--primary"><?php esc_html_e( 'Save Settings', 'osq-stress-check' ); ?></button>
						<div id="osq-settings-message" class="osq-settings-message" style="display: none; margin-top: 15px;"></div>
					</div>
				</form>
			</section>
		</div>
	</main>
</div>

<!-- Group Analysis Details Modal -->
<div id="osq-group-analysis-modal" class="osq-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
	<div class="osq-modal-content" style="background:#fff; border-radius:12px; padding:30px; width:900px; max-width:95vw; max-height:90vh; overflow-y:auto; position:relative;">
		<span class="osq-modal-close dashicons dashicons-no-alt" style="position:absolute; top:20px; right:20px; cursor:pointer; font-size:24px; color:#64748b;"></span>
		<h3 id="osq-group-analysis-title" style="margin-top:0; color:#1e293b; font-size:20px;"><?php esc_html_e( 'Group Analysis Details', 'osq-stress-check' ); ?></h3>

		<div style="display:flex; gap:30px; margin-top:20px; flex-wrap:wrap;">
			<div style="flex:1; min-width:400px; max-width:500px;">
				<canvas id="osq-group-radar-chart"></canvas>
			</div>
			<div style="flex:1; min-width:300px;">
				<h4 style="margin-top:0; color:#475569;"><?php esc_html_e( 'Scale Average Scores', 'osq-stress-check' ); ?></h4>
				<table class="osq-admin-table" id="osq-group-scores-table" style="font-size:13px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Scale', 'osq-stress-check' ); ?></th>
							<th><?php esc_html_e( 'Average Score', 'osq-stress-check' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<!-- Populated via JS -->
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<style>
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
	transition: transform 0.3s ease;
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
.osq-header-left {
	display: flex;
	align-items: center;
}
.osq-user-welcome {
	margin-right: 20px;
	color: #64748b;
	font-weight: 500;
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

/* Inner tab nav */
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
	border-bottom-color: #38bdf8;
	font-weight: 600;
}
.osq-inner-tab-nav li .dashicons {
	font-size: 18px;
	width: 18px;
	height: 18px;
}

.osq-stats-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 25px;
	margin-bottom: 40px;
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
.osq-stat-value {
	font-size: 32px;
	font-weight: 800;
	color: #1e293b;
}

.osq-admin-table {
	width: 100%;
	background: white;
	border-radius: 12px;
	border-collapse: collapse;
	overflow: hidden;
	box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}
.osq-admin-table th {
	text-align: left;
	padding: 16px 24px;
	background: #f8fafc;
	color: #475569;
	font-weight: 700;
	border-bottom: 1px solid #e2e8f0;
}
.osq-admin-table td {
	padding: 16px 24px;
	border-bottom: 1px solid #f1f5f9;
	color: #1e293b;
}
.osq-empty-table {
	text-align: center;
	padding: 40px !important;
	color: #94a3b8;
}

.osq-table-responsive {
	width: 100%;
	overflow-x: auto;
	-webkit-overflow-scrolling: touch;
	margin-bottom: 20px;
}
.osq-table-responsive .osq-admin-table {
	min-width: 800px;
}

.osq-panel-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	margin-bottom: 20px;
	flex-wrap: wrap;
}
.osq-filter-group {
	display: flex;
	align-items: center;
	gap: 12px;
}
.osq-select {
	padding: 8px 12px;
	border: 1px solid #e2e8f0;
	border-radius: 6px;
	font-size: 14px;
}
.osq-select--compact {
	min-width: 180px;
	height: 40px;
}
.osq-button {
	padding: 10px 20px;
	border-radius: 6px;
	font-weight: 600;
	cursor: pointer;
	border: none;
	transition: all 0.2s;
}
.osq-button--primary {
	background: #38bdf8;
	color: white;
}
.osq-button--primary:hover {
	background: #0ea5e9;
}
.osq-button--danger {
	background: #ef4444;
	color: white;
	border: none;
}
.osq-button--danger:hover {
	background: #dc2626;
}
.osq-button--secondary {
	background: #e2e8f0;
	color: #1e293b;
	border: 1px solid #cbd5e1;
}
.osq-button--secondary:hover {
	background: #cbd5e1;
}

.osq-import-dropzone {
	border: 3px dashed #e2e8f0;
	border-radius: 16px;
	padding: 60px;
	text-align: center;
	color: #64748b;
	cursor: pointer;
	transition: all 0.2s;
	background: white;
}
.osq-import-dropzone:hover {
	border-color: #38bdf8;
	background: #f0f9ff;
}
.osq-import-dropzone .dashicons {
	font-size: 48px;
	width: 48px;
	height: 48px;
	margin-bottom: 15px;
	color: #38bdf8;
}

.osq-analysis-filters {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	flex-wrap: wrap;
	margin-bottom: 20px;
}
.osq-analysis-section {
	margin-bottom: 30px;
}
.osq-analysis-section h4 {
	margin: 0 0 12px;
	font-size: 16px;
	color: #1e293b;
}

.osq-compliance-alert {
	margin-top: 30px;
	background: #fffbeb;
	border: 1px solid #fde68a;
	color: #92400e;
	padding: 16px;
	border-radius: 8px;
	display: flex;
	align-items: center;
	font-size: 14px;
	font-weight: 500;
}
.osq-compliance-alert .dashicons {
	margin-right: 12px;
}

/* Toggle Switch Styles */
.osq-toggle-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 20px;
	background: #f8fafc;
	border-radius: 10px;
	margin-top: 10px;
	border: 1px solid #e2e8f0;
}
.osq-toggle-info {
	display: flex;
	flex-direction: column;
	gap: 4px;
}
.osq-toggle-title {
	font-weight: 700;
	color: #1e293b;
	font-size: 15px;
}
.osq-toggle-desc {
	font-size: 13px;
	color: #64748b;
}
.osq-toggle-switch {
	position: relative;
	display: flex;
	align-items: center;
	gap: 12px;
	cursor: pointer;
}
.osq-toggle-switch input {
	opacity: 0;
	width: 0;
	height: 0;
}
.osq-toggle-slider {
	position: relative;
	width: 50px;
	height: 26px;
	background-color: #cbd5e1;
	transition: .3s;
	border-radius: 34px;
}
.osq-toggle-slider:before {
	position: absolute;
	content: "";
	height: 20px;
	width: 20px;
	left: 3px;
	bottom: 3px;
	background-color: white;
	transition: .3s;
	border-radius: 50%;
}
input:checked + .osq-toggle-slider {
	background-color: #38bdf8;
}
input:checked + .osq-toggle-slider:before {
	transform: translateX(24px);
}
.osq-toggle-status {
	font-weight: 800;
	font-size: 12px;
	min-width: 25px;
	color: #64748b;
}

@keyframes fadeIn {
	from { opacity: 0; transform: translateY(10px); }
	to { opacity: 1; transform: translateY(0); }
}

/* Hamburger Menu Styles */
.osq-hamburger {
	display: none;
	background: none;
	border: none;
	color: #64748b;
	cursor: pointer;
	padding: 8px;
	margin-right: 15px;
	border-radius: 6px;
	transition: background 0.2s;
}
.osq-hamburger:hover {
	background: #f1f5f9;
}
.osq-hamburger .dashicons {
	font-size: 24px;
	width: 24px;
	height: 24px;
}

/* Sidebar Overlay */
.osq-sidebar-overlay {
	display: none;
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.4);
	backdrop-filter: blur(2px);
	z-index: 1000;
	opacity: 0;
	transition: opacity 0.3s ease;
}

@media (max-width: 1024px) {
	.osq-hamburger {
		display: flex;
		align-items: center;
		justify-content: center;
	}
	
	.osq-admin-sidebar {
		position: fixed;
		left: 0;
		top: 0;
		bottom: 0;
		width: 280px !important;
		z-index: 1001;
		transform: translateX(-100%);
		transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
		box-shadow: 20px 0 25px -5px rgba(0, 0, 0, 0.1), 10px 0 10px -5px rgba(0, 0, 0, 0.04);
	}

	.osq-sidebar-open .osq-admin-sidebar {
		transform: translateX(0);
	}

	.osq-sidebar-open .osq-sidebar-overlay {
		display: block;
		opacity: 1;
	}

	.osq-admin-header {
		padding: 15px 20px;
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
		grid-template-columns: repeat(2, 1fr);
		gap: 15px;
	}
}

@media (max-width: 768px) {
	.osq-stats-grid {
		grid-template-columns: 1fr;
	}

	.osq-header-right {
		display: none;
	}
	
	.osq-admin-header h2 {
		font-size: 18px;
	}
	
	.osq-panel-header {
		flex-direction: column;
		align-items: stretch;
	}
	
	.osq-filter-group {
		flex-direction: column;
		align-items: stretch;
	}
	
	.osq-select--compact {
		width: 100%;
	}

	.osq-modal-content {
		width: 95vw;
		padding: 20px;
	}
}
</style>
</style>

<script>
jQuery(document).ready(function($) {
	const ajaxVars = osq_admin_vars;
	const i18n = ajaxVars.i18n;
	const orgLabels = i18n.org_labels || {};

	function translateOrgLabel(label) {
		if (!label) {
			return '-';
		}
		return orgLabels[label] || label;
	}

	// Initial data load
	loadStats();
	loadEmployees();
	loadGroupAnalysis();
	loadImportedUsers();

	// Hamburger Menu Toggle
	$('#osq-mobile-toggle, #osq-sidebar-overlay').on('click', function(e) {
		if (window.innerWidth <= 1024) {
			$('#osq-admin-dashboard').toggleClass('osq-sidebar-open');
		}
	});

	// Tab switching logic (inner tab bar)
	$('.osq-inner-tab-nav li').on('click', function() {
		const tabId = $(this).data('tab');
		if ( ! tabId ) return;

		$('.osq-inner-tab-nav li').removeClass('active');
		$(this).addClass('active');

		$('.osq-tab-panel').removeClass('active');
		$('#tab-' + tabId).addClass('active');

		const tabNames = {
			'overview': i18n.dash_overview,
			'employees': i18n.dash_employees,
			'import': i18n.dash_import,
			'analysis': i18n.dash_analysis,
			'settings': i18n.dash_settings
		};

		$('#osq-tab-title').text(tabNames[tabId] || $(this).find('span:not(.dashicons)').text().trim());
	});

	function loadStats() {
		$.ajax({
			url: ajaxVars.ajax_url,
			type: 'GET',
			data: {
				action: 'osq_admin_get_stats',
				nonce: ajaxVars.nonce
			},
			success: function(response) {
				if (response.success) {
					const data = response.data;
					$('.osq-stat-card .osq-stat-value').eq(0).text(data.total_employees);
					$('.osq-stat-card .osq-stat-value').eq(1).text(data.completion_rate + '%');
					$('.osq-stat-card .osq-stat-value').eq(2).text(data.pending);
				}
			}
		});
	}

	function loadEmployees() {
		const $tbody = $('#osq-employee-table tbody');
		const statusFilter = $('#osq-employee-status-filter').val() || 'all';
		$tbody.empty().append(`<tr><td colspan="6" class="osq-empty-table">${i18n.loading}</td></tr>`);
		
		$.ajax({
			url: ajaxVars.ajax_url,
			type: 'GET',
			data: {
				action: 'osq_admin_get_employees',
				nonce: ajaxVars.nonce,
				status: statusFilter
			},
			success: function(response) {
				if (response.success) {
					$tbody.empty();
					const employees = response.data.employees;
					
					if (employees.length === 0) {
						$tbody.append(`<tr><td colspan="6" class="osq-empty-table">${i18n.no_employees}</td></tr>`);
						return;
					}

					employees.forEach(function(emp) {
						const statusLabel = emp.is_complete == 1 
							? `<span class="osq-status-badge osq-status-badge--success">${i18n.completed}</span>` 
							: `<span class="osq-status-badge">${i18n.pending}</span>`;
						
						// Decode HTML entities for proper Japanese text display
						const decodedName = $('<div/>').html(emp.name).text();
						const decodedOrg1 = $('<div/>').html(emp.organization_1 || '-').text();
						const localizedOrg1 = translateOrgLabel(decodedOrg1);
						
						const row = `
							<tr>
								<td>${emp.employee_number}</td>
								<td><strong>${decodedName}</strong></td>
								<td>${localizedOrg1}</td>
								<td>${statusLabel}</td>
								<td>${emp.completed_at || '-'}</td>
								<td>
									<button class="osq-button osq-button--danger osq-button--small osq-delete-employee" data-id="${emp.employee_id}">
										${i18n.csv_delete}
									</button>
								</td>
							</tr>
						`;
						$tbody.append(row);
					});
				}
			}
		});
	}

	function loadGroupAnalysis() {
		const orgLevel = $('#osq-analysis-org-level').val() || 'organization_1';
		const $analysisBody = $('#osq-analysis-table tbody');
		const $participationBody = $('#osq-participation-table tbody');

		$analysisBody.empty().append(`<tr><td colspan="6" class="osq-empty-table">${i18n.analysis_loading}</td></tr>`);
		$participationBody.empty().append(`<tr><td colspan="4" class="osq-empty-table">${i18n.analysis_loading}</td></tr>`);

		$.ajax({
			url: ajaxVars.ajax_url,
			type: 'GET',
			data: {
				action: 'osq_admin_get_group_analysis',
				nonce: ajaxVars.nonce,
				org_level: orgLevel
			},
			success: function(response) {
				if (!response.success) {
					return;
				}

				// Store analysis data for chart rendering later
				window.osq_analysis_data = response.data.analysis || [];

				const analysisRows = response.data.analysis || [];
				const participationRows = response.data.participation || [];

				$analysisBody.empty();
				if (analysisRows.length === 0) {
					$analysisBody.append(`<tr><td colspan="6" class="osq-empty-table">${i18n.analysis_empty}</td></tr>`);
				} else {
					analysisRows.forEach(function(row, index) {
						const completionRate = Math.round((row.completion_rate || 0) * 1000) / 10;
						const localizedGroup = translateOrgLabel(row.group_label);
						const analysisRow = `
							<tr>
								<td>${localizedGroup}</td>
								<td>${row.respondent_count}</td>
								<td>${row.high_stress_count}</td>
								<td>${row.high_stress_ratio}%</td>
								<td>${completionRate}%</td>
								<td>
									<button class="osq-button osq-button--secondary osq-view-group-details" data-index="${index}">
										${i18n.label_view_details || 'View Details'}
									</button>
								</td>
							</tr>
						`;
						$analysisBody.append(analysisRow);
					});
				}

				$participationBody.empty();
				if (participationRows.length === 0) {
					$participationBody.append(`<tr><td colspan="4" class="osq-empty-table">${i18n.participation_empty}</td></tr>`);
				} else {
					participationRows.forEach(function(row) {
						const completionRate = Math.round((row.completion_rate || 0) * 1000) / 10;
						const localizedGroup = translateOrgLabel(row.group_label);
						const partRow = `
							<tr>
								<td>${localizedGroup}</td>
								<td>${row.total}</td>
								<td>${row.completed}</td>
								<td>${completionRate}%</td>
							</tr>
						`;
						$participationBody.append(partRow);
					});
				}
			}
		});
	}

	function loadImportedUsers() {
		const $tbody = $('#osq-imported-users-table tbody');
		$tbody.empty().append(`<tr><td colspan="5" class="osq-empty-table">${i18n.loading}</td></tr>`);

		function escapeHtml(value) {
			return $('<div/>').text(value ?? '').html();
		}

		$.ajax({
			url: ajaxVars.ajax_url,
			type: 'GET',
			data: {
				action: 'osq_admin_get_imported_users',
				nonce: ajaxVars.nonce
			},
			success: function(response) {
				if (!response.success) {
					$tbody.empty().append(`<tr><td colspan="5" class="osq-empty-table">${i18n.csv_import_failed || 'CSV import failed.'}</td></tr>`);
					return;
				}

				const users = response.data.users || [];
				$tbody.empty();
				if (users.length === 0) {
					$tbody.append(`<tr><td colspan="5" class="osq-empty-table">${i18n.csv_no_imports || 'No imported users.'}</td></tr>`);
					return;
				}

				users.forEach(function(user) {
					const employeeNumber = escapeHtml(user.employee_number || '-');
					const userName = escapeHtml(user.name || '-');
					const userPassword = escapeHtml(user.password || '-');
					const createdAt = escapeHtml(user.created_at || '-');
					const row = `
						<tr>
							<td>${employeeNumber}</td>
							<td>${userName}</td>
							<td><code>${userPassword}</code></td>
							<td>${createdAt}</td>
							<td>
								<button class="osq-button osq-button--secondary osq-import-delete" data-user-id="${user.user_id}">
									${i18n.csv_delete || 'Delete'}
								</button>
							</td>
						</tr>
					`;
					$tbody.append(row);
				});
			},
			error: function() {
				$tbody.empty().append(`<tr><td colspan="5" class="osq-empty-table">${i18n.csv_import_failed || 'CSV import failed.'}</td></tr>`);
			}
		});
	}

	$('#osq-employee-apply-filters').on('click', function() {
		loadEmployees();
	});

	$('#osq-admin-employee-search').on('input', function() {
		// Uses 'input' instead of 'keyup' for better Japanese IME handling, and normalize('NFKC')
		const searchTerm = $(this).val().normalize('NFKC').toLowerCase();
		$('#osq-employee-table tbody tr').each(function() {
			if ($(this).find('.osq-empty-table').length > 0) return;
			const rowText = $(this).text().normalize('NFKC').toLowerCase();
			$(this).toggle(rowText.indexOf(searchTerm) > -1);
		});
	});

	$('#osq-employee-status-filter').on('change', function() {
		loadEmployees();
	});

	$('#osq-analysis-refresh').on('click', function() {
		loadGroupAnalysis();
	});

	$('#osq-analysis-org-level').on('change', function() {
		loadGroupAnalysis();
	});

	// Group Analysis Detailed View Logic
	let groupRadarChart = null;
	$(document).on('click', '.osq-view-group-details', function(e) {
		e.preventDefault();
		const index = $(this).data('index');
		const rowData = window.osq_analysis_data[index];
		if (!rowData) return;

		const localizedGroup = translateOrgLabel(rowData.group_label);
		$('#osq-group-analysis-title').text(localizedGroup + ' - ' + (i18n.analysis_details || 'Analysis Details'));

		// Populate scores table
		const $scoresBody = $('#osq-group-scores-table tbody');
		$scoresBody.empty();

		const scaleOrder = [
			'quantitative_demands', 'qualitative_demands', 'physical_workload', 'interpersonal_stress',
			'environment_stress', 'job_control', 'skill_utilization', 'job_fit', 'reward',
			'vigor', 'irritability', 'fatigue', 'anxiety', 'depression', 'physical_complaints',
			'supervisor_support', 'colleague_support', 'family_support'
		];

		// If translations for scale names are missing, fallback nicely
		const scaleLabels = {
			'quantitative_demands': i18n.scale_quantitative_demands || 'Quantitative Demands',
			'qualitative_demands': i18n.scale_qualitative_demands || 'Qualitative Demands',
			'physical_workload': i18n.scale_physical_workload || 'Physical Workload',
			'interpersonal_stress': i18n.scale_interpersonal_stress || 'Interpersonal',
			'environment_stress': i18n.scale_environment_stress || 'Environment',
			'job_control': i18n.scale_job_control || 'Job Control',
			'skill_utilization': i18n.scale_skill_utilization || 'Skill Use',
			'job_fit': i18n.scale_job_fit || 'Job Fit',
			'reward': i18n.scale_reward || 'Reward',
			'vigor': i18n.scale_vigor || 'Vigor',
			'irritability': i18n.scale_irritability || 'Irritability',
			'fatigue': i18n.scale_fatigue || 'Fatigue',
			'anxiety': i18n.scale_anxiety || 'Anxiety',
			'depression': i18n.scale_depression || 'Depression',
			'physical_complaints': i18n.scale_physical_complaints || 'Physical Complaints',
			'supervisor_support': i18n.scale_supervisor_support || 'Supervisor Support',
			'colleague_support': i18n.scale_colleague_support || 'Colleague Support',
			'family_support': i18n.scale_family_support || 'Family Support'
		};

		const chartLabels = [];
		const chartData = [];

		scaleOrder.forEach(function(key) {
			const val = rowData.scale_averages[key] !== undefined ? rowData.scale_averages[key] : '-';
			const rawVal = parseFloat(val);
			const label = scaleLabels[key];

			$scoresBody.append(`
				<tr>
					<td>${label}</td>
					<td><strong>${val}</strong></td>
				</tr>
			`);

			chartLabels.push(label);
			chartData.push(isNaN(rawVal) ? 0 : rawVal);
		});

		// Render Radar Chart
		const ctx = document.getElementById('osq-group-radar-chart').getContext('2d');
		
		if (groupRadarChart) {
			groupRadarChart.destroy();
		}

		if (typeof Chart !== 'undefined') {
			groupRadarChart = new Chart(ctx, {
				type: 'radar',
				data: {
					labels: chartLabels,
					datasets: [{
						label: i18n.label_average_score || 'Average Score',
						data: chartData,
						backgroundColor: 'rgba(56, 189, 248, 0.2)',
						borderColor: 'rgba(56, 189, 248, 1)',
						pointBackgroundColor: 'rgba(56, 189, 248, 1)',
						pointBorderColor: '#fff',
						pointHoverBackgroundColor: '#fff',
						pointHoverBorderColor: 'rgba(56, 189, 248, 1)'
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					scales: {
						r: {
							angleLines: { display: true },
							suggestedMin: 1,
							suggestedMax: 4
						}
					}
				}
			});
		}

		$('#osq-group-analysis-modal').css('display', 'flex').hide().fadeIn(200);
	});

	$('.osq-modal-close').on('click', function() {
		$('#osq-group-analysis-modal').fadeOut(200);
	});
	$('#osq-group-analysis-modal').on('click', function(e) {
		if (e.target === this) {
			$(this).fadeOut(200);
		}
	});

	$('#osq-export-analysis-csv').on('click', function(e) {
		e.preventDefault();
		const orgLevel = $('#osq-analysis-org-level').val() || 'organization_1';
		const csvUrl = `${ajaxVars.ajax_url}?action=osq_admin_export_group_analysis_csv&nonce=${ajaxVars.nonce}&org_level=${encodeURIComponent(orgLevel)}`;
		window.location.href = csvUrl;
	});

	// CSV Dropzone logic
	$('#osq-csv-dropzone').on('click', function(e) {
		if ($(e.target).is('#osq-csv-file')) {
			return;
		}
		$('#osq-csv-file').click();
	});

	$('#osq-csv-file').on('change', function(e) {
		const file = e.target.files[0];
		if (file) {
			uploadCsv(file);
		}
	});

	$('#osq-csv-dropzone').on('dragover', function(e) {
		e.preventDefault();
		$(this).addClass('osq-dropzone--hover');
	});

	$('#osq-csv-dropzone').on('dragleave drop', function(e) {
		e.preventDefault();
		$(this).removeClass('osq-dropzone--hover');
	});

	$('#osq-csv-dropzone').on('drop', function(e) {
		const files = e.originalEvent.dataTransfer.files;
		if (files && files[0]) {
			uploadCsv(files[0]);
		}
	});

	function uploadCsv(file) {
		const $message = $('#osq-csv-message');
		const formData = new FormData();
		formData.append('action', 'osq_admin_import_csv');
		formData.append('nonce', ajaxVars.nonce);
		formData.append('csv_file', file);

		$message
			.removeClass('osq-message--error osq-message--success')
			.text(i18n.csv_uploading || 'Uploading CSV...')
			.show();

		$.ajax({
			url: ajaxVars.ajax_url,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				if (response.success) {
					const result = response.data.result || {};
					const errorCount = (result.errors || []).length;
					const summary = `${i18n.csv_import_complete || 'CSV import completed.'} ${i18n.csv_import_success || 'Imported'}: ${result.success || 0}, ${i18n.csv_import_skipped || 'Skipped'}: ${result.skipped || 0}, ${i18n.csv_import_errors || 'Errors'}: ${errorCount}`;
					let detail = '';
					if (errorCount > 0) {
						const errorsPreview = result.errors.slice(0, 5).join(' ');
						const moreCount = Math.max(0, errorCount - 5);
						detail = `\n${i18n.csv_error_details || 'Errors:'} ${errorsPreview}${moreCount > 0 ? ` (${i18n.csv_error_more || 'and'} ${moreCount} ${i18n.csv_error_more_items || 'more'})` : ''}`;
					}
					$message
						.removeClass('osq-message--error')
						.addClass('osq-message--success')
						.text(summary + detail)
						.show();
					loadStats();
					loadEmployees();
					loadImportedUsers();
				} else {
					$message
						.removeClass('osq-message--success')
						.addClass('osq-message--error')
						.text(response.data?.message || (i18n.csv_import_failed || 'CSV import failed.'))
						.show();
				}
			},
			error: function(xhr) {
				const serverMsg = xhr.responseJSON?.data?.message;
				$message
					.removeClass('osq-message--success')
					.addClass('osq-message--error')
					.text(serverMsg || (i18n.csv_import_failed || 'CSV import failed.'))
					.show();
			}
		});
	}

	$(document).on('click', '.osq-import-delete', function() {
		const userId = $(this).data('user-id');
		if (!userId) {
			return;
		}

		if (!confirm(i18n.csv_delete_confirm || 'Delete this imported user?')) {
			return;
		}

		$.ajax({
			url: ajaxVars.ajax_url,
			type: 'POST',
			data: {
				action: 'osq_admin_delete_imported_user',
				nonce: ajaxVars.nonce,
				user_id: userId
			},
			success: function(response) {
				if (response.success) {
					loadImportedUsers();
				} else {
					alert(response.data?.message || (i18n.csv_delete_failed || 'Delete failed.'));
				}
			},
			error: function() {
				alert(i18n.csv_delete_failed || 'Delete failed.');
			}
		});
	});

	// Toggle Switch Handler
	$('input[name="enable_group_analysis"]').on('change', function() {
		const isChecked = $(this).is(':checked');
		$(this).closest('.osq-toggle-switch').find('.osq-toggle-status').text(isChecked ? 'ON' : 'OFF');
	});

	// Settings form submission
	$('#osq-settings-form').on('submit', function(e) {
		e.preventDefault();
		
		const $form = $(this);
		const $message = $('#osq-settings-message');
		const formData = $form.serialize();
		
		// Show loading state
		const $submitBtn = $form.find('button[type="submit"]');
		const originalText = $submitBtn.text();
		$submitBtn.prop('disabled', true).text('Saving...');
		
		$.ajax({
			url: ajaxVars.ajax_url,
			type: 'POST',
			data: {
				action: 'osq_admin_save_settings',
				nonce: ajaxVars.nonce,
				language: $form.find('select[name="language"]').val(),
				session_timeout: $form.find('input[name="session_timeout"]').val(),
				enable_group_analysis: $form.find('input[name="enable_group_analysis"]').is(':checked') ? 1 : 0
			},
			success: function(response) {
				if (response.success) {
					$message
						.removeClass('osq-message--error')
						.addClass('osq-message--success')
						.text(response.data.message)
						.show();
					
					// Update language cookie if changed
					const lang = response.data.language;
					const cookieValue = lang === 'ja' ? 'ja' : 'en_US';
					document.cookie = 'osq_lang=' + cookieValue + '; path=/; max-age=' + (365 * 24 * 60 * 60);
					
					// Reload page to apply language changes
					setTimeout(function() {
						location.reload();
					}, 1500);
				} else {
					$message
						.removeClass('osq-message--success')
						.addClass('osq-message--error')
						.text(response.data?.message || 'Error saving settings')
						.show();
				}
			},
			error: function() {
				$message
					.removeClass('osq-message--success')
					.addClass('osq-message--error')
					.text('Network error occurred')
					.show();
			},
			complete: function() {
				$submitBtn.prop('disabled', false).text(originalText);
			}
		});
	});
	// Individual Employee Delete
	$(document).on('click', '.osq-delete-employee', function(e) {
		e.preventDefault();
		const $btn = $(this);
		const employeeId = $btn.data('id');
		
		if (!confirm('この従業員データを削除してもよろしいですか？\nAre you sure you want to delete this employee?')) {
			return;
		}
		
		$btn.prop('disabled', true).text('...');
		
		$.ajax({
			url: ajaxVars.ajax_url,
			type: 'POST',
			data: {
				action: 'osq_admin_delete_employee',
				nonce: ajaxVars.nonce,
				employee_id: employeeId
			},
			success: function(response) {
				if (response.success) {
					loadEmployees();
					loadStats();
				} else {
					alert('Error: ' + response.data.message);
					$btn.prop('disabled', false).text(i18n.csv_delete);
				}
			},
			error: function() {
				alert('System error occurred.');
				$btn.prop('disabled', false).text(i18n.csv_delete);
			}
		});
	});
});
</script>

<?php wp_footer(); ?>
</body>
</html>
