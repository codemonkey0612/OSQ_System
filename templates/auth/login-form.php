<?php
/**
 * Employee Login Page Template.
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


<div id="osq-employee-login" class="osq-ui-container">
	<div class="osq-login-card">
		<div class="osq-login-lang-switch">
			<a href="?osq_lang=en_US" class="<?php echo get_locale() === 'en_US' ? 'active' : ''; ?>">English</a> | 
			<a href="?osq_lang=ja" class="<?php echo get_locale() === 'ja' ? 'active' : ''; ?>">日本語</a>
		</div>
		<div class="osq-login-header">
			<h1><?php esc_html_e( 'OSQ Stress Check', 'osq-stress-check' ); ?></h1>
			<p class="osq-subtitle"><?php esc_html_e( 'Employee Login', 'osq-stress-check' ); ?></p>
		</div>

		<?php
		$lockout_remaining = \OSQ\Auth\LoginManager::get_lockout_remaining_seconds();
		$is_locked = $lockout_remaining > 0;
		?>

		<?php if ( $is_locked ) : ?>
			<div class="osq-alert osq-alert--error osq-lockout-alert">
				<div class="osq-lockout-icon">&#128274;</div>
				<p><?php echo esc_html( \OSQ\Auth\LoginManager::get_lockout_message() ); ?></p>
				<p class="osq-countdown-label">ロック解除まで (Unlock in): <strong id="osq-countdown-timer"></strong></p>
			</div>
		<?php elseif ( isset( $_GET['osq_error'] ) ) : ?>
			<div class="osq-alert osq-alert--error">
				<?php
				$error_code = sanitize_text_field( $_GET['osq_error'] );
				switch ( $error_code ) {
					case 'empty_fields':
					case 'empty':
						esc_html_e( '社員番号とパスワードを入力してください。 (Please enter both your employee number and password.)', 'osq-stress-check' );
						break;
					case 'invalid_credentials':
					case 'failed':
						esc_html_e( '社員番号またはパスワードが正しくありません。 (Invalid employee number or password.)', 'osq-stress-check' );
						break;
					case 'locked_out':
						echo esc_html( \OSQ\Auth\LoginManager::get_lockout_message() );
						break;
					case 'unauthorized':
						esc_html_e( 'ダッシュボードにアクセスするにはログインしてください。 (Please login to access the dashboard.)', 'osq-stress-check' );
						break;
					default:
						esc_html_e( 'エラーが発生しました。もう一度お試しください。 (An error occurred. Please try again.)', 'osq-stress-check' );
				}
				?>
			</div>
		<?php endif; ?>

		<form method="post" action="" class="osq-form">
			<?php wp_nonce_field( 'osq_login_action', 'osq_login_nonce' ); ?>
			<input type="hidden" name="osq_employee_login" value="1">

			<div class="osq-form-group">
				<label for="employee_number"><?php esc_html_e( 'Employee Number', 'osq-stress-check' ); ?></label>
				<input type="text" name="employee_number" id="employee_number" class="osq-input" required autofocus <?php echo $is_locked ? 'disabled' : ''; ?>>
			</div>

			<div class="osq-form-group">
				<label for="password"><?php esc_html_e( 'Password', 'osq-stress-check' ); ?></label>
				<input type="password" name="password" id="password" class="osq-input" required <?php echo $is_locked ? 'disabled' : ''; ?>>
			</div>

			<div class="osq-form-actions">
				<?php if ( ! $is_locked ) : ?>
				<button type="submit" class="osq-button osq-button--primary osq-button--full">
					<?php esc_html_e( 'Login', 'osq-stress-check' ); ?>
				</button>
				<?php else : ?>
				<button type="button" class="osq-button osq-button--primary osq-button--full" disabled style="opacity:0.5;cursor:not-allowed;">
					&#128274; <?php esc_html_e( 'アカウントがロックされています (Account Locked)', 'osq-stress-check' ); ?>
				</button>
				<?php endif; ?>
			</div>
		</form>

		<div class="osq-login-footer">
			<p><?php esc_html_e( 'If you have forgotten your password, please contact your administrator.', 'osq-stress-check' ); ?></p>
		</div>
	</div>
</div>

<style>
.osq-ui-container {
	width: 100vw;
	position: relative;
	left: 50%;
	right: 50%;
	margin-left: -50vw;
	margin-right: -50vw;
	display: flex;
	justify-content: center;
	align-items: center;
	min-height: 100vh;
	background: #f4f7f6;
	padding: 20px;
	box-sizing: border-box;
	font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
.osq-login-card {
	background: white;
	padding: 40px;
	border-radius: 12px;
	box-shadow: 0 10px 25px rgba(0,0,0,0.05);
	width: 100%;
	max-width: 450px;
	border-top: 5px solid #007cba;
}
.osq-login-header {
	text-align: center;
	margin-bottom: 30px;
}
.osq-login-header h1 {
	font-size: 28px;
	color: #1d2327;
	margin: 0 0 5px;
}
.osq-subtitle {
	color: #646970;
	font-size: 18px;
	margin: 0;
}
.osq-alert {
	padding: 12px;
	border-radius: 6px;
	margin-bottom: 20px;
	font-size: 14px;
}
.osq-alert--error {
	background: #fcf0f1;
	color: #d63638;
	border: 1px solid #d63638;
}
.osq-form-group {
	margin-bottom: 25px;
}
.osq-form-group label {
	display: block;
	font-weight: 600;
	margin-bottom: 8px;
	color: #1d2327;
	font-size: 15px;
}
.osq-input {
	width: 100%;
	padding: 14px;
	border: 1px solid #dcdcde;
	border-radius: 6px;
	font-size: 16px;
	box-sizing: border-box;
	background: #fcfcfc;
}
.osq-input:focus {
	border-color: #007cba;
	background: white;
	box-shadow: 0 0 0 3px rgba(0, 124, 186, 0.1);
	outline: none;
}
.osq-button {
	cursor: pointer;
	font-weight: 700;
	padding: 14px 24px;
	border-radius: 6px;
	border: none;
	transition: all 0.2s ease;
	font-size: 16px;
}
.osq-button--primary {
	background: #007cba;
	color: white;
}
.osq-button--primary:hover {
	background: #006799;
	transform: translateY(-1px);
}
.osq-button--full {
	width: 100%;
}
.osq-login-lang-switch {
	text-align: right;
	margin-bottom: 20px;
	font-size: 13px;
}
.osq-login-lang-switch a {
	text-decoration: none;
	color: #646970;
}
.osq-login-lang-switch a.active {
	font-weight: bold;
	color: #007cba;
}
.osq-login-footer {
	margin-top: 30px;
	text-align: center;
	font-size: 14px;
	color: #646970;
}

@media (max-width: 480px) {
	.osq-login-card {
		padding: 30px 20px;
	}
	.osq-login-header h1 {
		font-size: 24px;
	}
	.osq-subtitle {
		font-size: 16px;
	}
}
</style>

<?php if ( $is_locked ) : ?>
<style>
.osq-lockout-alert {
	text-align: center;
	background: #fff5f5;
	border: 2px solid #d63638;
	padding: 25px 20px;
}
.osq-lockout-icon {
	font-size: 48px;
	margin-bottom: 15px;
}
.osq-countdown-label {
	margin-top: 12px;
	font-size: 16px;
	color: #1d2327;
}
#osq-countdown-timer {
	font-size: 24px;
	color: #d63638;
	font-variant-numeric: tabular-nums;
}
.osq-input:disabled {
	background: #f0f0f0 !important;
	color: #999 !important;
	cursor: not-allowed;
}
</style>
<script>
(function() {
	var remaining = <?php echo (int) $lockout_remaining; ?>;
	var timerEl = document.getElementById('osq-countdown-timer');
	if (!timerEl || remaining <= 0) return;

	function formatTime(secs) {
		var h = Math.floor(secs / 3600);
		var m = Math.floor((secs % 3600) / 60);
		var s = secs % 60;
		return (h > 0 ? h + '時間 ' : '') +
		       (m > 0 ? m + '分 ' : '') +
		       s + '秒';
	}

	function tick() {
		if (remaining <= 0) {
			timerEl.textContent = 'ロック解除中... (Unlocking...)';
			setTimeout(function() { window.location.reload(); }, 1000);
			return;
		}
		timerEl.textContent = formatTime(remaining);
		remaining--;
		setTimeout(tick, 1000);
	}
	tick();
})();
</script>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>

