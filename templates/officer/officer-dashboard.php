<?php
/**
 * Implementation Officer Dashboard Template.
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
?>

<div id="osq-officer-dashboard" class="osq-ui-container osq-admin-dashboard osq-officer-theme">
	<div id="osq-sidebar-overlay" class="osq-sidebar-overlay"></div>
	<?php \OSQ\Auth\NavigationBuilder::render_sidebar( 'individual' ); ?>

	<main class="osq-admin-main">
		<header class="osq-admin-header">
			<div class="osq-header-left">
				<button id="osq-mobile-toggle" class="osq-hamburger">
					<span class="dashicons dashicons-menu"></span>
				</button>
				<h2 id="osq-tab-title"><?php esc_html_e( '個人回答', 'osq-stress-check' ); ?></h2>
			</div>
			<div class="osq-header-right">
				<span class="osq-user-welcome"><?php printf( esc_html__( 'ようこそ、%s さん', 'osq-stress-check' ), esc_html( $current_user->display_name ) ); ?></span>
			</div>
		</header>

		<nav class="osq-inner-tab-nav">
			<ul>
				<li class="active" data-tab="responses">
					<span class="dashicons dashicons-id-alt"></span>
					<span><?php esc_html_e( '個人回答', 'osq-stress-check' ); ?></span>
				</li>
				<li data-tab="followup">
					<span class="dashicons dashicons-calendar-alt"></span>
					<span><?php esc_html_e( 'フォローアップ管理', 'osq-stress-check' ); ?></span>
				</li>
				<li data-tab="profile">
					<span class="dashicons dashicons-admin-users"></span>
					<span><?php esc_html_e( 'プロフィール', 'osq-stress-check' ); ?></span>
				</li>
				<li data-tab="settings">
					<span class="dashicons dashicons-admin-settings"></span>
					<span><?php esc_html_e( '設定', 'osq-stress-check' ); ?></span>
				</li>
			</ul>
		</nav>

		<div class="osq-admin-content">
			<!-- Responses Tab -->
			<section id="tab-responses" class="osq-tab-panel active">
				<div class="osq-panel-header">
					<div class="osq-filter-controls">
						<div class="osq-filters">
							<div class="osq-search-box">
								<input type="text" id="osq-employee-search" placeholder="<?php esc_attr_e( '従業員を検索...', 'osq-stress-check' ); ?>" class="osq-input-search">
							</div>
							<select id="osq-org-filter-1" class="osq-select">
								<option value=""><?php esc_html_e( '組織で絞り込む', 'osq-stress-check' ); ?></option>
								<!-- Options will be populated dynamically -->
							</select>
							<select id="osq-status-filter" class="osq-select">
								<option value=""><?php esc_html_e( 'すべてのステータス', 'osq-stress-check' ); ?></option>
								<option value="completed"><?php esc_html_e( '完了のみ', 'osq-stress-check' ); ?></option>
								<option value="pending"><?php esc_html_e( '未完了のみ', 'osq-stress-check' ); ?></option>
								<option value="high_stress"><?php esc_html_e( '高ストレスのみ', 'osq-stress-check' ); ?></option>
							</select>
							<div class="osq-filter-actions">
								<button id="osq-apply-filters" class="osq-button osq-button--primary"><?php esc_html_e( 'フィルター適用', 'osq-stress-check' ); ?></button>
								<button id="osq-clear-filters" class="osq-button"><?php esc_html_e( 'フィルタークリア', 'osq-stress-check' ); ?></button>
							</div>
						</div>
					</div>
					<div class="osq-bulk-actions">
						<label>
							<input type="checkbox" id="osq-select-all"> <?php esc_html_e( 'すべて選択', 'osq-stress-check' ); ?>
						</label>
						<select id="osq-bulk-action" class="osq-select">
							<option value=""><?php esc_html_e( '一括操作', 'osq-stress-check' ); ?></option>
							<option value="schedule_followup"><?php esc_html_e( 'フォローアップを予定する', 'osq-stress-check' ); ?></option>
							<option value="mark_completed"><?php esc_html_e( '完了としてマーク', 'osq-stress-check' ); ?></option>
						</select>
						<button id="osq-execute-bulk" class="osq-button osq-button--secondary" disabled><?php esc_html_e( '実行', 'osq-stress-check' ); ?></button>
					</div>
				</div>
				<div class="osq-table-responsive">
					<table class="osq-admin-table" id="osq-responses-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'ID', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( '氏名', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( '部署', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( 'ストレス状況', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( '完了日', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( '操作', 'osq-stress-check' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td colspan="6" class="osq-empty-table"><?php esc_html_e( '従業員データを読み込み中...', 'osq-stress-check' ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			</section>

			<!-- Profile Tab -->
			<section id="tab-profile" class="osq-tab-panel">
				<div class="osq-profile-form-container" style="max-width: 500px;">
					<form id="osq-officer-password-form" class="osq-admin-form">
						<h3 style="margin-top: 0; margin-bottom: 20px; font-size: 18px; color: #1e293b;"><?php esc_html_e( 'パスワード変更', 'osq-stress-check' ); ?></h3>

						<div class="osq-form-row">
							<label><?php esc_html_e( '現在のパスワード', 'osq-stress-check' ); ?></label>
							<input type="password" name="current_password" required class="osq-input-search" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px;">
						</div>
						<div class="osq-form-row">
							<label><?php esc_html_e( '新しいパスワード', 'osq-stress-check' ); ?></label>
							<input type="password" name="new_password" required minlength="8" class="osq-input-search" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px;">
						</div>
						<div class="osq-form-row">
							<label><?php esc_html_e( '新しいパスワード（確認）', 'osq-stress-check' ); ?></label>
							<input type="password" name="confirm_password" required minlength="8" class="osq-input-search" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px;">
						</div>
						<div class="osq-form-actions">
							<button type="submit" class="osq-button osq-button--primary" style="padding: 10px 20px; background: #38bdf8; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;"><?php esc_html_e( 'パスワードを更新する', 'osq-stress-check' ); ?></button>
							<div id="osq-officer-password-message" class="osq-settings-message" style="display: none; margin-top: 15px;"></div>
						</div>
					</form>
				</div>
			</section>

			<!-- Follow-up Tracking Tab -->
			<section id="tab-followup" class="osq-tab-panel">
				<div class="osq-panel-header">
					<h3><?php esc_html_e( 'フォローアップ管理', 'osq-stress-check' ); ?></h3>
					<div class="osq-search-box">
						<input type="text" id="osq-followup-search" placeholder="<?php esc_attr_e( 'フォローアップを検索...', 'osq-stress-check' ); ?>" class="osq-input-search">
					</div>
				</div>
				<div class="osq-table-responsive">
					<table class="osq-admin-table" id="osq-followup-table">
						<thead>
							<tr>
								<th><?php esc_html_e( '従業員', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( 'ステータス', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( '予定日', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( 'メモ', 'osq-stress-check' ); ?></th>
								<th><?php esc_html_e( '操作', 'osq-stress-check' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td colspan="5" class="osq-empty-table"><?php esc_html_e( 'フォローアップデータを読み込み中...', 'osq-stress-check' ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			</section>

			<!-- Settings Tab -->
			<section id="tab-settings" class="osq-tab-panel">
				<p style="color:#64748b;font-size:14px;"><?php esc_html_e( '現在、変更可能な設定項目はありません。', 'osq-stress-check' ); ?></p>
			</section>
		</div>
	</main>
</div>

<!-- Detailed Response Modal -->
<div id="osq-detailed-response-modal" class="osq-modal" style="display: none;">
	<div class="osq-modal-overlay"></div>
	<div class="osq-modal-content">
		<div class="osq-modal-header">
			<h3><?php esc_html_e( '従業員回答詳細', 'osq-stress-check' ); ?></h3>
			<button class="osq-modal-close">&times;</button>
		</div>
		<div class="osq-modal-body">
			<div class="osq-loading-indicator" id="osq-response-loading">
				<?php esc_html_e( '読み込み中...', 'osq-stress-check' ); ?>
			</div>
			<div id="osq-response-details" style="display: none;">
				<div class="osq-employee-info">
					<h4><?php esc_html_e( '従業員情報', 'osq-stress-check' ); ?></h4>
					<div id="osq-employee-basic-info"></div>
				</div>
				<div class="osq-response-section">
					<h4><?php esc_html_e( '回答詳細', 'osq-stress-check' ); ?></h4>
					<div class="osq-response-tabs">
						<button type="button" class="osq-tab-btn active" data-tab="A"><?php esc_html_e( 'セクションA', 'osq-stress-check' ); ?></button>
						<button type="button" class="osq-tab-btn" data-tab="B"><?php esc_html_e( 'セクションB', 'osq-stress-check' ); ?></button>
						<button type="button" class="osq-tab-btn" data-tab="C"><?php esc_html_e( 'セクションC', 'osq-stress-check' ); ?></button>
						<button type="button" class="osq-tab-btn" data-tab="D"><?php esc_html_e( 'セクションD', 'osq-stress-check' ); ?></button>
					</div>
					<div class="osq-questions-tabs">
						<div class="osq-questions-list osq-questions-tab active" id="osq-questions-tab-a"></div>
						<div class="osq-questions-list osq-questions-tab" id="osq-questions-tab-b"></div>
						<div class="osq-questions-list osq-questions-tab" id="osq-questions-tab-c"></div>
						<div class="osq-questions-list osq-questions-tab" id="osq-questions-tab-d"></div>
					</div>
				</div>
				<div class="osq-scoring-section">
					<h4><?php esc_html_e( '判定結果', 'osq-stress-check' ); ?></h4>
					<div id="osq-scoring-results"></div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Follow-up Modal -->
<div id="osq-followup-modal" class="osq-modal" style="display: none;">
	<div class="osq-modal-overlay"></div>
	<div class="osq-modal-content">
		<div class="osq-modal-header">
			<h3><?php esc_html_e( 'フォローアップ状況の更新', 'osq-stress-check' ); ?></h3>
			<button class="osq-modal-close">&times;</button>
		</div>
		<div class="osq-modal-body">
			<form id="osq-followup-form">
				<input type="hidden" id="osq-followup-employee-id" value="">
				<div class="osq-form-row">
					<label><?php esc_html_e( 'フォローアップステータス', 'osq-stress-check' ); ?></label>
					<select id="osq-followup-status" class="osq-select" required>
						<option value="Scheduled"><?php esc_html_e( '予定済み', 'osq-stress-check' ); ?></option>
						<option value="Completed"><?php esc_html_e( '完了', 'osq-stress-check' ); ?></option>
						<option value="Cancelled"><?php esc_html_e( 'キャンセル', 'osq-stress-check' ); ?></option>
					</select>
				</div>
				<div class="osq-form-row">
					<label><?php esc_html_e( '予定日', 'osq-stress-check' ); ?></label>
					<input type="datetime-local" id="osq-followup-schedule-date" class="osq-input">
				</div>
				<div class="osq-form-row">
					<label><?php esc_html_e( 'メモ', 'osq-stress-check' ); ?></label>
					<textarea id="osq-followup-notes" class="osq-textarea" rows="4"></textarea>
				</div>
				<div class="osq-form-actions">
					<button type="submit" class="osq-button osq-button--primary"><?php esc_html_e( 'フォローアップを更新する', 'osq-stress-check' ); ?></button>
					<button type="button" class="osq-button osq-modal-close-btn"><?php esc_html_e( 'キャンセル', 'osq-stress-check' ); ?></button>
				</div>
			</form>
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
	background: #3c2946; /* Officer purple theme */
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
	color: #d8b4e2;
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
	background: #4a3457;
	border-left: 4px solid #d8b4e2;
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

	.osq-sidebar-header span { display: block; }
	.osq-admin-nav li span:not(.dashicons) { display: block; }
	.osq-admin-nav li .dashicons { margin-right: 12px; }
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
	
	.osq-filter-controls {
		padding: 12px;
	}

	.osq-filters {
		grid-template-columns: 1fr;
		gap: 10px;
	}

	.osq-filter-actions {
		display: flex;
		gap: 10px;
		justify-content: flex-end;
	}

	.osq-filter-actions .osq-button {
		flex: 1;
	}

	.osq-bulk-actions {
		flex-direction: column;
		align-items: stretch;
	}

	.osq-bulk-actions label {
		margin-bottom: 5px;
	}

	.osq-modal-content {
		width: 95vw;
		margin: 20px auto;
	}

	.osq-scoring-results {
		grid-template-columns: 1fr;
	}
}

/* Base admin styles are loaded from above. These are officer-specific overrides. */
.osq-officer-theme .osq-admin-sidebar {
	background: #3c2946; /* Distinct dark purple for officer */
}
.osq-officer-theme .osq-logo {
	color: #d8b4e2;
}
.osq-officer-theme .osq-admin-nav li.active {
	background: #4a3457;
	border-left-color: #d8b4e2;
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
	border-bottom-color: #d8b4e2;
	font-weight: 600;
}
.osq-inner-tab-nav li .dashicons {
	font-size: 18px;
	width: 18px;
	height: 18px;
}
.osq-status-badge--high-stress {
	background: #fee2e2;
	color: #dc2626;
	border: 1px solid #fecaca;
}
.osq-status-badge--normal {
	background: #f0fdf4;
	color: #16a34a;
	border: 1px solid #bbf7d0;
}
.osq-btn-download {
	padding: 6px 12px;
	border-radius: 4px;
	background: #f1f5f9;
	color: #475569;
	text-decoration: none;
	font-size: 13px;
	font-weight: 600;
	transition: all 0.2s;
	display: inline-flex;
	align-items: center;
	border: 1px solid #cbd5e1;
}
.osq-btn-download:hover {
	background: #e2e8f0;
	color: #0f172a;
}
.osq-btn-download.disabled {
	opacity: 0.5;
	pointer-events: none;
}

/* Filter Controls */
.osq-filter-controls {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 16px;
	box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
	margin-bottom: 20px;
}

.osq-filters {
	display: grid;
	grid-template-columns: minmax(220px, 1fr) minmax(220px, 1fr) minmax(180px, 1fr) auto;
	gap: 12px;
	align-items: center;
	width: 100%;
}

.osq-filter-actions {
	display: flex;
	gap: 10px;
	justify-content: flex-end;
}

.osq-bulk-actions {
	display: flex;
	align-items: center;
	gap: 15px;
	margin-top: 15px;
	padding-top: 15px;
	border-top: 1px solid #e2e8f0;
}

/* Modal Styles */
.osq-modal {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	z-index: 10000;
}

.osq-modal-overlay {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background: rgba(0, 0, 0, 0.5);
}

.osq-modal-content {
	position: relative;
	background: white;
	margin: 50px auto;
	max-width: 900px;
	max-height: 80vh;
	border-radius: 8px;
	box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
	display: flex;
	flex-direction: column;
}

.osq-modal-header {
	padding: 20px;
	border-bottom: 1px solid #e2e8f0;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.osq-modal-header h3 {
	margin: 0;
	color: #1e293b;
}

.osq-modal-close {
	background: none;
	border: none;
	font-size: 24px;
	cursor: pointer;
	color: #94a3b8;
	padding: 0;
	width: 30px;
	height: 30px;
	display: flex;
	align-items: center;
	justify-content: center;
}

.osq-modal-close:hover {
	color: #1e293b;
}

.osq-modal-body {
	padding: 20px;
	overflow-y: auto;
	flex-grow: 1;
}

.osq-loading-indicator {
	text-align: center;
	padding: 40px;
	color: #64748b;
}

/* Response Detail Styles */
.osq-employee-info {
	margin-bottom: 30px;
	padding: 20px;
	background: #f8fafc;
	border-radius: 8px;
}

.osq-response-section {
	margin-bottom: 30px;
}

.osq-scoring-section {
	margin-bottom: 20px;
}

.osq-questions-list {
	max-height: 400px;
	overflow-y: auto;
}

.osq-question-item {
	padding: 15px;
	border-bottom: 1px solid #f1f5f9;
}

.osq-question-item:last-child {
	border-bottom: none;
}

.osq-question-text {
	font-weight: 600;
	margin-bottom: 8px;
	color: #1e293b;
}

.osq-answer-display {
	color: #64748b;
	font-size: 14px;
}

.osq-category-tag {
	display: inline-block;
	padding: 4px 8px;
	background: #e2e8f0;
	border-radius: 4px;
	font-size: 12px;
	margin-right: 8px;
}

.osq-scale-tag {
	display: inline-block;
	padding: 4px 8px;
	background: #dbeafe;
	border-radius: 4px;
	font-size: 12px;
}

/* Response Tabs */
.osq-response-tabs {
	display: flex;
	gap: 8px;
	margin-bottom: 12px;
	flex-wrap: wrap;
}
.osq-tab-btn {
	border: 1px solid #cbd5e1;
	background: #f8fafc;
	color: #475569;
	padding: 6px 12px;
	border-radius: 999px;
	font-size: 13px;
	font-weight: 600;
	cursor: pointer;
	transition: all 0.2s;
}
.osq-tab-btn.active {
	background: #e2e8f0;
	color: #1e293b;
	border-color: #94a3b8;
}
.osq-questions-tabs {
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	background: #ffffff;
}
.osq-questions-tab {
	display: none;
	padding: 10px 12px;
}
.osq-questions-tab.active {
	display: block;
}

/* Disabled action buttons */
.osq-btn-download[disabled],
.osq-btn-download.disabled {
	opacity: 0.5;
	pointer-events: none;
	cursor: not-allowed;
}

/* Scoring Results */
.osq-scoring-results {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 20px;
}

.osq-scoring-card {
	background: white;
	padding: 20px;
	border-radius: 8px;
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.osq-scoring-card h5 {
	margin-top: 0;
	color: #1e293b;
	border-bottom: 1px solid #e2e8f0;
	padding-bottom: 10px;
}

.osq-score-item {
	display: flex;
	justify-content: space-between;
	margin-bottom: 10px;
}

/* Status Badges */
.osq-status-badge--scheduled {
	background: #fef3c7;
	color: #d97706;
	border: 1px solid #fde68a;
}

.osq-status-badge--cancelled {
	background: #fee2e2;
	color: #dc2626;
	border: 1px solid #fecaca;
}
.osq-status-badge--completed {
	background: #dcfce7;
	color: #166534;
	border: 1px solid #bbf7d0;
}

/* Form Elements */
.osq-textarea {
	width: 100%;
	padding: 12px;
	border: 1px solid #cbd5e1;
	border-radius: 6px;
	font-family: inherit;
	resize: vertical;
}

.osq-modal-close-btn {
	background: #f1f5f9;
	color: #64748b;
}

.osq-modal-close-btn:hover {
	background: #e2e8f0;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
	.osq-filter-controls {
		flex-direction: column;
		align-items: stretch;
	}
	
	.osq-filters {
		grid-template-columns: 1fr;
	}

	.osq-filter-actions {
		justify-content: stretch;
	}

	.osq-filter-actions .osq-button {
		width: 100%;
	}
	
	.osq-modal-content {
		margin: 20px;
		max-width: calc(100% - 40px);
	}
	
	.osq-scoring-results {
		grid-template-columns: 1fr;
	}
}
</style>

<?php wp_footer(); ?>
</body>
</html>
