<?php
/**
 * Dedicated page wrapper for the questionnaire virtual route.
 *
 * @package OSQ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Scripts and osq_vars are enqueued by EmployeeUiHandler::enqueue_questionnaire_scripts()
// which is hooked to wp_enqueue_scripts in init() — before this template is loaded.
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>


<div class="osq-ui-container osq-ui-container--questionnaire">
	<div class="osq-dashboard-card osq-questionnaire-card">
		<header class="osq-questionnaire-header">
			<h1><?php esc_html_e( 'OSQ Stress Check', 'osq-stress-check' ); ?></h1>
			<p><?php esc_html_e( '57項目すべてに順番にお答えください。', 'osq-stress-check' ); ?></p>
		</header>
		
		<div class="osq-form-wrap">
			<?php 
			$template = OSQ_PLUGIN_DIR . 'templates/questionnaire/questionnaire-form.php';
			if ( file_exists( $template ) ) {
				include $template;
			}
			?>
		</div>
	</div>
</div>

<style>
.osq-ui-container--questionnaire {
	width: 100vw;
	position: relative;
	left: 50%;
	right: 50%;
	margin-left: -50vw;
	margin-right: -50vw;
	padding: 40px;
	background: #f0f2f1;
	min-height: 100vh;
	font-family: 'Inter', -apple-system, sans-serif;
	box-sizing: border-box;
	display: flex;
	justify-content: center;
}
.osq-questionnaire-card {
	max-width: 1000px;
	margin: 0 auto;
	background: white;
	border-radius: 12px;
	padding: 50px;
	box-shadow: 0 4px 30px rgba(0,0,0,0.06);
	width: 100%;
	box-sizing: border-box;
}
.osq-questionnaire-header {
	text-align: center;
	margin-bottom: 40px;
	border-bottom: 2px solid #f0f2f1;
	padding-bottom: 20px;
}
.osq-questionnaire-header h1 {
	font-size: 32px;
	color: #1d2327;
	margin: 0 0 10px;
}
.osq-questionnaire-header p {
	color: #646970;
	font-size: 16px;
	margin: 0;
}
@media (max-width: 768px) {
	.osq-ui-container--questionnaire {
		padding: 20px;
	}
	.osq-questionnaire-card {
		padding: 25px;
	}
	.osq-questionnaire-header h1 {
		font-size: 26px;
	}
}
</style>

<?php wp_footer(); ?>
</body>
</html>

