<?php
/**
 * Questionnaire form template.
 *
 * @package OSQ
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use OSQ\Questionnaire\QuestionDefinitions;

$sections = array(
	'A' => __( 'A. あなたの仕事について', 'osq-stress-check' ),
	'B' => __( 'B. 最近1か月の状態について', 'osq-stress-check' ),
	'C' => __( 'C. 周りの方々について', 'osq-stress-check' ),
	'D' => __( 'D. 満足度について', 'osq-stress-check' ),
);

// Questionnaire content is always displayed in Japanese for this deployment.
$lang        = 'ja';
$text_key    = 'text_ja';
$total_items = count( QuestionDefinitions::get_questions() );
?>

<div id="osq-questionnaire-wrap" class="osq-questionnaire">

	<!-- Progress bar -->
	<div class="osq-progress-bar-wrap">
		<div class="osq-progress-label">
			<span id="osq-answered-count">0</span> / <?php echo esc_html( $total_items ); ?>
			<?php esc_html_e( '問回答済み', 'osq-stress-check' ); ?>
		</div>
		<div class="osq-progress-bar">
			<div id="osq-progress-fill" class="osq-progress-fill" style="width: 0%;"></div>
		</div>
	</div>

	<!-- Section tabs -->
	<div class="osq-section-tabs" role="tablist">
		<?php foreach ( $sections as $key => $label ) : ?>
			<button
				type="button"
				class="osq-tab<?php echo 'A' === $key ? ' osq-tab--active' : ''; ?>"
				role="tab"
				data-section="<?php echo esc_attr( $key ); ?>"
				aria-selected="<?php echo 'A' === $key ? 'true' : 'false'; ?>"
				title="<?php echo esc_attr( $label ); ?>"
			>
				<span class="osq-tab-label"><?php echo esc_html( $key ); ?></span>
				<div class="osq-tab-progress-bar">
					<div class="osq-tab-progress-fill" id="osq-progress-<?php echo esc_attr( $key ); ?>" style="width: 0%;"></div>
				</div>
			</button>
		<?php endforeach; ?>
	</div>

	<form id="osq-questionnaire-form" method="post"
		data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
		data-token="<?php
			$_osq_uid   = get_current_user_id();
			$_osq_token = wp_generate_password( 32, false, false );
			set_transient( 'osq_ajax_token_' . $_osq_uid, $_osq_token, 8 * HOUR_IN_SECONDS );
			echo esc_attr( $_osq_token );
		?>"
		data-uid="<?php echo esc_attr( get_current_user_id() ); ?>"
	>
		<?php wp_nonce_field( 'osq_questionnaire_nonce', 'osq_nonce' ); ?>

		<?php foreach ( $sections as $section_key => $section_title ) : ?>
			<div
				class="osq-section<?php echo 'A' === $section_key ? ' osq-section--active' : ''; ?>"
				id="osq-section-<?php echo esc_attr( $section_key ); ?>"
				role="tabpanel"
			>
				<h2 class="osq-section-title"><?php echo esc_html( $section_title ); ?></h2>

				<?php
				$questions = QuestionDefinitions::get_section_questions( $section_key );
				$options   = QuestionDefinitions::get_response_options( $section_key );
				?>

				<!-- Response option header -->
				<div class="osq-options-header">
					<span class="osq-options-header__label"><?php esc_html_e( '設問', 'osq-stress-check' ); ?></span>
					<?php foreach ( $options as $val => $texts ) : ?>
						<span class="osq-options-header__option">
							<?php echo esc_html( $texts[ $lang ] ); ?>
						</span>
					<?php endforeach; ?>
				</div>

				<?php foreach ( $questions as $q ) : ?>
					<div class="osq-question" data-question-id="<?php echo esc_attr( $q['id'] ); ?>">
						<div class="osq-question__text">
							<span class="osq-question__number"><?php echo esc_html( $q['id'] ); ?></span>
							<?php echo esc_html( $q[ $text_key ] ); ?>
						</div>
						<div class="osq-question__options">
							<?php foreach ( $options as $val => $texts ) : ?>
								<label class="osq-radio-label osq-radio-card">
									<input
										type="radio"
										name="answers[<?php echo esc_attr( $q['id'] ); ?>]"
										value="<?php echo esc_attr( $val ); ?>"
										class="osq-radio osq-radio-hidden"
									/>
									<span class="osq-radio-text"><?php echo esc_html( $texts[ $lang ] ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>

			</div>
		<?php endforeach; ?>

		<!-- Navigation & Action buttons -->
		<div class="osq-form-actions">
			<div class="osq-nav-buttons">
				<button type="button" id="osq-prev-btn" class="osq-btn osq-btn--secondary" style="display: none;">
					<span class="dashicons dashicons-arrow-left-alt2" style="vertical-align: middle;"></span>
					<?php esc_html_e( '前へ', 'osq-stress-check' ); ?>
				</button>
				<button type="button" id="osq-next-btn" class="osq-btn osq-btn--primary">
					<?php esc_html_e( '次へ', 'osq-stress-check' ); ?>
					<span class="dashicons dashicons-arrow-right-alt2" style="vertical-align: middle;"></span>
				</button>
			</div>
			<div class="osq-secondary-actions">
				<button type="button" id="osq-save-btn" class="osq-btn osq-btn--secondary">
					<?php esc_html_e( '一時保存', 'osq-stress-check' ); ?>
				</button>
			</div>
			<button type="submit" id="osq-submit-btn" class="osq-btn osq-btn--primary" style="display: none;">
				<?php esc_html_e( '送信する', 'osq-stress-check' ); ?>
			</button>
		</div>

		<!-- Save status indicator -->
		<div id="osq-save-status" class="osq-save-status" aria-live="polite"></div>

	</form>
</div>
