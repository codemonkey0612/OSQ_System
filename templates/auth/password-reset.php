<?php
/**
 * Password reset page (Phase 5).
 *
 * Two modes:
 *  - request: no token in URL → enter employee number to receive a reset link.
 *  - reset:   ?uid=&token= present → set a new password.
 *
 * @package OSQ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'show_admin_bar', '__return_false' );

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$uid   = isset( $_GET['uid'] ) ? absint( $_GET['uid'] ) : 0;
$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
// phpcs:enable
$mode  = ( $uid && $token ) ? 'reset' : 'request';

$nonce     = wp_create_nonce( 'osq_reset_nonce' );
$ajax_url  = admin_url( 'admin-ajax.php' );
$login_url = home_url( '/' . \OSQ\Auth\EmployeeUiHandler::LOGIN_SLUG . '/' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'パスワード再設定', 'osq-stress-check' ); ?></title>
	<?php wp_head(); ?>
	<style>
		* { box-sizing: border-box; }
		html, body { margin: 0 !important; padding: 0 !important; }
		body { font-family: 'Hiragino Kaku Gothic Pro', 'Meiryo', sans-serif; background: #f1f5f9; color: #1e293b; }
		.osq-reset-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
		.osq-reset-card { background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); padding: 40px; width: 100%; max-width: 420px; }
		.osq-reset-card h1 { font-size: 20px; margin: 0 0 8px; color: #166534; }
		.osq-reset-card p.lead { font-size: 14px; color: #64748b; margin: 0 0 24px; line-height: 1.7; }
		.osq-reset-field { margin-bottom: 18px; }
		.osq-reset-field label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
		.osq-reset-field input { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 15px; }
		.osq-reset-btn { width: 100%; padding: 13px; background: #166534; color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer; }
		.osq-reset-btn:disabled { opacity: 0.6; cursor: default; }
		.osq-reset-msg { margin-top: 16px; padding: 12px 14px; border-radius: 8px; font-size: 14px; display: none; }
		.osq-reset-msg--ok { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
		.osq-reset-msg--err { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
		.osq-reset-foot { margin-top: 24px; text-align: center; font-size: 13px; }
		.osq-reset-foot a { color: #166534; text-decoration: none; }
	</style>
</head>
<body <?php body_class(); ?>>
<div class="osq-reset-wrap">
	<div class="osq-reset-card">

	<?php if ( 'request' === $mode ) : ?>
		<h1><?php esc_html_e( 'パスワードをお忘れですか？', 'osq-stress-check' ); ?></h1>
		<p class="lead"><?php esc_html_e( '社員番号を入力してください。ご登録のメールアドレス宛に、パスワード再設定用のリンクをお送りします。', 'osq-stress-check' ); ?></p>
		<form id="osq-request-form">
			<div class="osq-reset-field">
				<label for="osq-emp-number"><?php esc_html_e( '社員番号', 'osq-stress-check' ); ?></label>
				<input type="text" id="osq-emp-number" autocomplete="username" required>
			</div>
			<button type="submit" class="osq-reset-btn"><?php esc_html_e( '再設定リンクを送信', 'osq-stress-check' ); ?></button>
			<div class="osq-reset-msg" id="osq-request-msg"></div>
		</form>
	<?php else : ?>
		<h1><?php esc_html_e( '新しいパスワードの設定', 'osq-stress-check' ); ?></h1>
		<p class="lead"><?php esc_html_e( '新しいパスワード（8文字以上）を入力してください。', 'osq-stress-check' ); ?></p>
		<form id="osq-reset-form">
			<input type="hidden" id="osq-reset-uid" value="<?php echo esc_attr( $uid ); ?>">
			<input type="hidden" id="osq-reset-token" value="<?php echo esc_attr( $token ); ?>">
			<div class="osq-reset-field">
				<label for="osq-new-pass"><?php esc_html_e( '新しいパスワード', 'osq-stress-check' ); ?></label>
				<input type="password" id="osq-new-pass" minlength="8" autocomplete="new-password" required>
			</div>
			<div class="osq-reset-field">
				<label for="osq-new-pass2"><?php esc_html_e( '新しいパスワード（確認）', 'osq-stress-check' ); ?></label>
				<input type="password" id="osq-new-pass2" minlength="8" autocomplete="new-password" required>
			</div>
			<button type="submit" class="osq-reset-btn"><?php esc_html_e( 'パスワードを再設定', 'osq-stress-check' ); ?></button>
			<div class="osq-reset-msg" id="osq-reset-msg"></div>
		</form>
	<?php endif; ?>

		<div class="osq-reset-foot">
			<a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( '← ログイン画面へ戻る', 'osq-stress-check' ); ?></a>
		</div>
	</div>
</div>

<script>
(function() {
	var AJAX  = <?php echo wp_json_encode( $ajax_url ); ?>;
	var NONCE = <?php echo wp_json_encode( $nonce ); ?>;

	function post(data, cb) {
		var x = new XMLHttpRequest();
		x.open('POST', AJAX, true);
		x.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		x.onload = function() {
			try { cb(JSON.parse(x.responseText)); } catch (e) { cb({ success: false, data: { message: '通信エラーが発生しました。' } }); }
		};
		x.onerror = function() { cb({ success: false, data: { message: '通信エラーが発生しました。' } }); };
		var enc = Object.keys(data).map(function(k){ return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]); }).join('&');
		x.send(enc);
	}
	function showMsg(el, ok, text) {
		el.className = 'osq-reset-msg ' + (ok ? 'osq-reset-msg--ok' : 'osq-reset-msg--err');
		el.textContent = text;
		el.style.display = 'block';
	}

	var reqForm = document.getElementById('osq-request-form');
	if (reqForm) {
		reqForm.addEventListener('submit', function(e) {
			e.preventDefault();
			var btn = reqForm.querySelector('button');
			var msg = document.getElementById('osq-request-msg');
			btn.disabled = true;
			post({ action: 'osq_request_password_reset', nonce: NONCE, employee_number: document.getElementById('osq-emp-number').value }, function(res) {
				btn.disabled = false;
				showMsg(msg, res.success, (res.data && res.data.message) || 'エラーが発生しました。');
			});
		});
	}

	var resetForm = document.getElementById('osq-reset-form');
	if (resetForm) {
		resetForm.addEventListener('submit', function(e) {
			e.preventDefault();
			var msg = document.getElementById('osq-reset-msg');
			var p1 = document.getElementById('osq-new-pass').value;
			var p2 = document.getElementById('osq-new-pass2').value;
			if (p1 !== p2) { showMsg(msg, false, 'パスワードが一致しません。'); return; }
			var btn = resetForm.querySelector('button');
			btn.disabled = true;
			post({
				action: 'osq_perform_password_reset', nonce: NONCE,
				uid: document.getElementById('osq-reset-uid').value,
				token: document.getElementById('osq-reset-token').value,
				password: p1
			}, function(res) {
				showMsg(msg, res.success, (res.data && res.data.message) || 'エラーが発生しました。');
				if (res.success) {
					setTimeout(function(){ window.location.href = <?php echo wp_json_encode( $login_url ); ?>; }, 2500);
				} else {
					btn.disabled = false;
				}
			});
		});
	}
})();
</script>
<?php wp_footer(); ?>
</body>
</html>
