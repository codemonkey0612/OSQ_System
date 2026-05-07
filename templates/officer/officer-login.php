<?php
/**
 * Implementation Officer Login Page Template.
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


<div id="osq-officer-login" class="osq-ui-container">
	<div class="osq-login-card osq-login-card--officer">
		<div class="osq-login-lang-switch">
			<a href="?osq_lang=en_US" class="<?php echo get_locale() === 'en_US' ? 'active' : ''; ?>">English</a> | 
			<a href="?osq_lang=ja" class="<?php echo get_locale() === 'ja' ? 'active' : ''; ?>">日本語</a>
		</div>
		<div class="osq-login-header">
			<h1><?php esc_html_e( 'OSQ Stress Check', 'osq-stress-check' ); ?></h1>
			<p class="osq-subtitle"><?php esc_html_e( 'Implementation Officer Portal', 'osq-stress-check' ); ?></p>
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
		<?php elseif ( isset( $_GET['osq_officer_error'] ) ) : ?>
			<div class="osq-alert osq-alert--error">
				<?php
				$error_code = sanitize_text_field( $_GET['osq_officer_error'] );
				switch ( $error_code ) {
					case 'empty_fields':
						esc_html_e( 'ユーザー名とパスワードを入力してください。 (Please enter both your username/email and password.)', 'osq-stress-check' );
						break;
					case 'invalid_credentials':
						esc_html_e( '認証情報が正しくありません。 (Invalid credentials. Please try again.)', 'osq-stress-check' );
						break;
					case 'invalid_role':
						esc_html_e( '実施事務従事者ポータルへのアクセス権限がありません。 (You do not have permission to access the implementation officer portal.)', 'osq-stress-check' );
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
			<?php wp_nonce_field( 'osq_officer_login_action', 'osq_officer_login_nonce' ); ?>
			<input type="hidden" name="osq_officer_login_submit" value="1">

			<div class="osq-form-group">
				<label for="username"><?php esc_html_e( 'Username or Email Address', 'osq-stress-check' ); ?></label>
				<input type="text" name="username" id="username" class="osq-input" required autofocus <?php echo $is_locked ? 'disabled' : ''; ?>>
			</div>

			<div class="osq-form-group">
				<label for="password"><?php esc_html_e( 'Password', 'osq-stress-check' ); ?></label>
				<input type="password" name="password" id="password" class="osq-input" required <?php echo $is_locked ? 'disabled' : ''; ?>>
			</div>

			<div class="osq-form-actions">
				<?php if ( ! $is_locked ) : ?>
				<button type="submit" class="osq-button osq-button--officer osq-button--full">
					<?php esc_html_e( 'Sign In', 'osq-stress-check' ); ?>
				</button>
				<?php else : ?>
				<button type="button" class="osq-button osq-button--officer osq-button--full" disabled style="opacity:0.5;cursor:not-allowed;">
					&#128274; <?php esc_html_e( 'アカウントがロックされています (Account Locked)', 'osq-stress-check' ); ?>
				</button>
				<?php endif; ?>
			</div>
		</form>

		<div class="osq-login-footer">
			<a href="<?php echo esc_url( home_url( '/osq-login/' ) ); ?>" class="osq-back-link">
				&larr; <?php esc_html_e( 'Employee Portal', 'osq-stress-check' ); ?>
			</a>
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
	background: #f0f2f5;
	padding: 20px;
	box-sizing: border-box;
	font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
.osq-login-card {
	background: white;
	padding: 45px;
	border-radius: 16px;
	box-shadow: 0 20px 40px rgba(0,0,0,0.08);
	width: 100%;
	max-width: 480px;
	border-top: 6px solid #8e44ad; /* Distinct color for officer */
	transition: transform 0.3s ease;
}
.osq-login-card--officer {
	border-top-color: #8e44ad;
}
.osq-login-header {
	text-align: center;
	margin-bottom: 35px;
}
.osq-login-header h1 {
	font-size: 32px;
	color: #8e44ad; /* distinct theme */
	margin: 0 0 8px;
	font-weight: 800;
	letter-spacing: -0.5px;
}
.osq-subtitle {
	color: #7f8c8d;
	font-size: 19px;
	margin: 0;
	font-weight: 500;
}
.osq-alert {
	padding: 14px 18px;
	border-radius: 8px;
	margin-bottom: 25px;
	font-size: 15px;
	line-height: 1.5;
}
.osq-alert--error {
	background: #fff5f5;
	color: #e74c3c;
	border: 1px solid #fab1a0;
}
.osq-form-group {
	margin-bottom: 28px;
}
.osq-form-group label {
	display: block;
	font-weight: 700;
	margin-bottom: 10px;
	color: #34495e;
	font-size: 15px;
}
.osq-input {
	width: 100%;
	padding: 16px;
	border: 2px solid #e0e6ed;
	border-radius: 8px;
	font-size: 16px;
	box-sizing: border-box;
	background: #f8fafc;
	transition: all 0.2s ease;
}
.osq-input:focus {
	border-color: #8e44ad;
	background: white;
	box-shadow: 0 0 0 4px rgba(142, 68, 173, 0.15);
	outline: none;
}
.osq-button {
	cursor: pointer;
	font-weight: 700;
	padding: 16px 28px;
	border-radius: 8px;
	border: none;
	transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
	font-size: 17px;
	display: inline-flex;
	justify-content: center;
	align-items: center;
}
.osq-button--officer {
	background: #8e44ad;
	color: white;
}
.osq-button--officer:hover {
	background: #732d91;
	transform: translateY(-2px);
	box-shadow: 0 5px 15px rgba(142, 68, 173, 0.3);
}
.osq-button--full {
	width: 100%;
}
.osq-login-lang-switch {
	text-align: right;
	margin-bottom: 25px;
	font-size: 14px;
}
.osq-login-lang-switch a {
	text-decoration: none;
	color: #95a5a6;
	transition: color 0.2s;
}
.osq-login-lang-switch a.active {
	font-weight: 800;
	color: #8e44ad;
}
.osq-login-lang-switch a:hover:not(.active) {
	color: #7f8c8d;
}
.osq-login-footer {
	margin-top: 35px;
	text-align: center;
	font-size: 15px;
	border-top: 1px solid #ecf0f1;
	padding-top: 25px;
}
.osq-back-link {
	color: #7f8c8d;
	text-decoration: none;
	font-weight: 500;
	transition: color 0.2s;
}
.osq-back-link:hover {
	color: #34495e;
}

@media (max-width: 480px) {
	.osq-login-card {
		padding: 35px 25px;
	}
	.osq-login-header h1 {
		font-size: 26px;
	}
	.osq-subtitle {
		font-size: 17px;
	}
}
</style>

<?php if ( $is_locked ) : ?>
<style>
.osq-lockout-alert { text-align: center; background: #fff5f5; border: 2px solid #e74c3c; padding: 25px 20px; }
.osq-lockout-icon { font-size: 48px; margin-bottom: 15px; }
.osq-countdown-label { margin-top: 12px; font-size: 16px; color: #34495e; }
#osq-countdown-timer { font-size: 24px; color: #e74c3c; font-variant-numeric: tabular-nums; }
.osq-input:disabled { background: #f0f0f0 !important; color: #999 !important; cursor: not-allowed; }
</style>
<script>
(function() {
	var remaining = <?php echo (int) $lockout_remaining; ?>;
	var timerEl = document.getElementById('osq-countdown-timer');
	if (!timerEl || remaining <= 0) return;
	function formatTime(secs) {
		var h = Math.floor(secs / 3600), m = Math.floor((secs % 3600) / 60), s = secs % 60;
		return (h > 0 ? h + '時間 ' : '') + (m > 0 ? m + '分 ' : '') + s + '秒';
	}
	function tick() {
		if (remaining <= 0) { timerEl.textContent = 'ロック解除中... (Unlocking...)'; setTimeout(function() { window.location.reload(); }, 1000); return; }
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
