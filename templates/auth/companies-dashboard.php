<?php
/**
 * Companies management dashboard — wellanc super-admin only.
 *
 * @package OSQ
 */

use OSQ\Auth\NavigationBuilder;
use OSQ\Auth\CompaniesUiHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$companies  = CompaniesUiHandler::get_all_companies();
$ajax_nonce = wp_create_nonce( 'osq_companies_ajax' );
$saved      = isset( $_GET['saved'] );

wp_head();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php esc_html_e( 'Companies (wellanc)', 'osq-stress-check' ); ?> – <?php bloginfo( 'name' ); ?></title>
<?php wp_head(); ?>
<style>
/* ── Layout ─────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f1f5f9; color: #1e293b; }

.osq-app { display: flex; min-height: 100vh; }
.osq-main  { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.osq-header {
	background: #fff; border-bottom: 1px solid #e2e8f0;
	padding: 0 32px; height: 64px; display: flex; align-items: center;
	justify-content: space-between; position: sticky; top: 0; z-index: 10;
}
.osq-header h1 { font-size: 20px; font-weight: 600; color: #1e293b; }
.osq-content { padding: 32px; flex: 1; }

/* ── Notice ─────────────────────────────────────────── */
.osq-notice {
	background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46;
	border-radius: 8px; padding: 12px 16px; margin-bottom: 24px;
	display: flex; align-items: center; gap: 8px;
}

/* ── Companies grid ──────────────────────────────────── */
.osq-companies-grid {
	display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
	gap: 20px; margin-bottom: 32px;
}
.osq-company-card {
	background: #fff; border-radius: 12px; border: 1px solid #e2e8f0;
	padding: 24px; position: relative; transition: box-shadow .15s;
}
.osq-company-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); }
.osq-company-card.inactive { opacity: .55; }

.card-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 12px; }
.card-name { font-size: 17px; font-weight: 600; color: #1e293b; }
.card-slug { font-size: 12px; color: #94a3b8; margin-top: 2px; font-family: monospace; }
.card-badge {
	font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 500;
	background: #ede9fe; color: #6d28d9;
}
.card-badge.inactive-badge { background: #f1f5f9; color: #64748b; }

.card-stats { display: flex; gap: 16px; margin: 12px 0; }
.stat { text-align: center; }
.stat-num { font-size: 22px; font-weight: 700; color: #6366f1; }
.stat-label { font-size: 11px; color: #94a3b8; }

.card-orgs { font-size: 12px; color: #64748b; background: #f8fafc;
	border-radius: 6px; padding: 8px 10px; margin: 8px 0; line-height: 1.7; }

.card-actions { display: flex; gap: 8px; margin-top: 16px; }
.btn { padding: 7px 14px; border-radius: 7px; font-size: 13px; font-weight: 500;
	cursor: pointer; border: none; transition: opacity .15s; }
.btn:hover { opacity: .85; }
.btn-primary { background: #6366f1; color: #fff; }
.btn-secondary { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.btn-danger  { background: #fee2e2; color: #dc2626; }
.btn-switch  { background: #ede9fe; color: #6d28d9; }
.btn-sm { padding: 5px 10px; font-size: 12px; }

/* ── New company card ────────────────────────────────── */
.osq-add-card {
	background: #f8fafc; border: 2px dashed #cbd5e1;
	border-radius: 12px; padding: 24px;
	display: flex; flex-direction: column; align-items: center;
	justify-content: center; gap: 12px; min-height: 200px;
	cursor: pointer; transition: border-color .15s, background .15s;
}
.osq-add-card:hover { border-color: #6366f1; background: #eef2ff; }
.add-icon { font-size: 32px; color: #6366f1; }
.add-label { font-size: 14px; color: #6366f1; font-weight: 500; }

/* ── Modal ───────────────────────────────────────────── */
.osq-modal-bg {
	display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45);
	z-index: 1000; align-items: center; justify-content: center;
}
.osq-modal-bg.open { display: flex; }
.osq-modal {
	background: #fff; border-radius: 16px; padding: 32px;
	width: 100%; max-width: 520px; max-height: 90vh; overflow-y: auto;
	position: relative;
}
.modal-title { font-size: 18px; font-weight: 700; margin-bottom: 24px; color: #1e293b; }
.modal-close {
	position: absolute; top: 20px; right: 20px; background: none; border: none;
	font-size: 20px; cursor: pointer; color: #94a3b8; line-height: 1;
}
.modal-close:hover { color: #1e293b; }

.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 13px; font-weight: 500; color: #475569; margin-bottom: 6px; }
.form-input {
	width: 100%; padding: 9px 12px; border: 1px solid #e2e8f0; border-radius: 8px;
	font-size: 14px; color: #1e293b; outline: none; transition: border-color .15s;
}
.form-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.1); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; padding-top: 20px;
	border-top: 1px solid #f1f5f9; }

.osq-spinner { display: none; width: 16px; height: 16px;
	border: 2px solid #fff; border-top-color: transparent;
	border-radius: 50%; animation: spin .6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Responsive ─────────────────────────────────────── */
@media (max-width: 768px) {
	.osq-content { padding: 16px; }
	.osq-companies-grid { grid-template-columns: 1fr; }
	.form-row { grid-template-columns: 1fr; }
}
</style>
</head>
<body class="osq-body">

<div class="osq-app">
	<?php NavigationBuilder::render_sidebar( 'companies' ); ?>

	<div class="osq-main">
		<header class="osq-header">
			<h1>
				<span class="dashicons dashicons-building" style="color:#6366f1;margin-right:8px;font-size:22px;vertical-align:middle;"></span>
				<?php esc_html_e( 'All Companies (wellanc)', 'osq-stress-check' ); ?>
			</h1>
			<button class="btn btn-primary" id="btn-new-company">
				<span class="dashicons dashicons-plus-alt2" style="font-size:16px;vertical-align:middle;margin-right:4px;"></span>
				<?php esc_html_e( 'New Company', 'osq-stress-check' ); ?>
			</button>
		</header>

		<div class="osq-content">
			<?php if ( $saved ) : ?>
			<div class="osq-notice">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Saved successfully.', 'osq-stress-check' ); ?>
			</div>
			<?php endif; ?>

			<div class="osq-companies-grid" id="companies-grid">
				<?php foreach ( $companies as $co ) : ?>
				<?php $active = ! empty( $co['is_active'] ); ?>
				<div class="osq-company-card <?php echo $active ? '' : 'inactive'; ?>"
					data-id="<?php echo esc_attr( $co['company_id'] ); ?>">

					<div class="card-header">
						<div>
							<div class="card-name"><?php echo esc_html( $co['company_name'] ); ?></div>
							<div class="card-slug">/<?php echo esc_html( $co['company_slug'] ); ?></div>
						</div>
						<span class="card-badge <?php echo $active ? '' : 'inactive-badge'; ?>">
							<?php echo $active ? esc_html__( 'Active', 'osq-stress-check' ) : esc_html__( 'Inactive', 'osq-stress-check' ); ?>
						</span>
					</div>

					<div class="card-stats">
						<div class="stat">
							<div class="stat-num"><?php echo esc_html( $co['employee_count'] ); ?></div>
							<div class="stat-label"><?php esc_html_e( 'Employees', 'osq-stress-check' ); ?></div>
						</div>
						<div class="stat">
							<div class="stat-num"><?php echo esc_html( $co['min_group_size'] ); ?></div>
							<div class="stat-label"><?php esc_html_e( 'Min group', 'osq-stress-check' ); ?></div>
						</div>
					</div>

					<div class="card-orgs">
						<?php echo esc_html( $co['org_label_1'] ); ?> /
						<?php echo esc_html( $co['org_label_2'] ); ?> /
						<?php echo esc_html( $co['org_label_3'] ); ?>
					</div>

					<div class="card-actions">
						<button class="btn btn-secondary btn-sm btn-edit-company"
							data-company='<?php echo esc_attr( wp_json_encode( $co ) ); ?>'>
							<?php esc_html_e( 'Edit', 'osq-stress-check' ); ?>
						</button>
						<button class="btn btn-switch btn-sm btn-switch-company"
							data-id="<?php echo esc_attr( $co['company_id'] ); ?>">
							<?php esc_html_e( 'Switch to', 'osq-stress-check' ); ?>
						</button>
						<?php if ( (int) $co['company_id'] > 1 ) : ?>
						<button class="btn btn-danger btn-sm btn-deactivate-company"
							data-id="<?php echo esc_attr( $co['company_id'] ); ?>"
							data-name="<?php echo esc_attr( $co['company_name'] ); ?>">
							<?php echo $active ? esc_html__( 'Deactivate', 'osq-stress-check' ) : esc_html__( 'Activate', 'osq-stress-check' ); ?>
						</button>
						<?php endif; ?>
					</div>
				</div>
				<?php endforeach; ?>

				<!-- Add new card (clickable) -->
				<div class="osq-add-card" id="add-card-trigger">
					<div class="add-icon dashicons dashicons-plus-alt2"></div>
					<div class="add-label"><?php esc_html_e( 'Add New Company', 'osq-stress-check' ); ?></div>
				</div>
			</div>
		</div><!-- .osq-content -->
	</div><!-- .osq-main -->
</div><!-- .osq-app -->

<!-- ── Company Edit / Create Modal ───────────────────── -->
<div class="osq-modal-bg" id="company-modal">
	<div class="osq-modal">
		<button class="modal-close" id="modal-close-btn">✕</button>
		<div class="modal-title" id="modal-title"><?php esc_html_e( 'New Company', 'osq-stress-check' ); ?></div>

		<input type="hidden" id="modal-company-id" value="">

		<div class="form-group">
			<label class="form-label"><?php esc_html_e( 'Company Name', 'osq-stress-check' ); ?> *</label>
			<input type="text" id="field-name" class="form-input" placeholder="株式会社〇〇">
		</div>

		<div class="form-group">
			<label class="form-label"><?php esc_html_e( 'Slug (URL-safe)', 'osq-stress-check' ); ?> *</label>
			<input type="text" id="field-slug" class="form-input" placeholder="company-slug">
		</div>

		<div class="form-row">
			<div class="form-group">
				<label class="form-label"><?php esc_html_e( 'Org Label 1', 'osq-stress-check' ); ?></label>
				<input type="text" id="field-org1" class="form-input" value="組織1">
			</div>
			<div class="form-group">
				<label class="form-label"><?php esc_html_e( 'Org Label 2', 'osq-stress-check' ); ?></label>
				<input type="text" id="field-org2" class="form-input" value="組織2">
			</div>
		</div>

		<div class="form-row">
			<div class="form-group">
				<label class="form-label"><?php esc_html_e( 'Org Label 3', 'osq-stress-check' ); ?></label>
				<input type="text" id="field-org3" class="form-input" value="組織3">
			</div>
			<div class="form-group">
				<label class="form-label"><?php esc_html_e( 'Min Group Size', 'osq-stress-check' ); ?></label>
				<input type="number" id="field-min-group" class="form-input" value="5" min="1" max="100">
			</div>
		</div>

		<div class="form-group" id="field-active-wrap" style="display:none;">
			<label class="form-label">
				<input type="checkbox" id="field-active" checked style="margin-right:6px;">
				<?php esc_html_e( 'Active', 'osq-stress-check' ); ?>
			</label>
		</div>

		<div class="form-actions">
			<button class="btn btn-secondary" id="modal-cancel-btn"><?php esc_html_e( 'Cancel', 'osq-stress-check' ); ?></button>
			<button class="btn btn-primary" id="modal-save-btn">
				<span class="osq-spinner" id="save-spinner"></span>
				<?php esc_html_e( 'Save', 'osq-stress-check' ); ?>
			</button>
		</div>
	</div>
</div>

<?php wp_footer(); ?>

<script>
(function($) {
	var NONCE = <?php echo wp_json_encode( $ajax_nonce ); ?>;
	var AJAX  = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

	// ── Modal helpers ─────────────────────────────────
	function openModal(company) {
		var isEdit = !!company;
		$('#modal-title').text(isEdit ? '<?php esc_html_e( 'Edit Company', 'osq-stress-check' ); ?>' : '<?php esc_html_e( 'New Company', 'osq-stress-check' ); ?>');
		$('#modal-company-id').val(isEdit ? company.company_id : '');
		$('#field-name').val(isEdit ? company.company_name : '');
		$('#field-slug').val(isEdit ? company.company_slug : '');
		$('#field-org1').val(isEdit ? company.org_label_1 : '組織1');
		$('#field-org2').val(isEdit ? company.org_label_2 : '組織2');
		$('#field-org3').val(isEdit ? company.org_label_3 : '組織3');
		$('#field-min-group').val(isEdit ? company.min_group_size : 5);
		$('#field-active').prop('checked', isEdit ? company.is_active == 1 : true);
		$('#field-active-wrap').toggle(isEdit);
		$('#company-modal').addClass('open');
		$('#field-name').focus();
	}
	function closeModal() {
		$('#company-modal').removeClass('open');
	}

	// Auto-slug from name (create mode only)
	$('#field-name').on('input', function() {
		if ($('#modal-company-id').val()) return;
		var slug = $(this).val()
			.toLowerCase()
			.replace(/[^\w\s-]/g, '')
			.replace(/[\s_]+/g, '-')
			.replace(/^-+|-+$/g, '');
		$('#field-slug').val(slug);
	});

	$('#btn-new-company, #add-card-trigger').on('click', function() { openModal(null); });
	$('#modal-close-btn, #modal-cancel-btn').on('click', closeModal);
	$('#company-modal').on('click', function(e) { if (e.target === this) closeModal(); });

	$(document).on('click', '.btn-edit-company', function() {
		openModal($(this).data('company'));
	});

	// ── Save ──────────────────────────────────────────
	$('#modal-save-btn').on('click', function() {
		var name = $.trim($('#field-name').val());
		var slug = $.trim($('#field-slug').val());
		if (!name || !slug) { alert('<?php esc_html_e( 'Name and slug are required.', 'osq-stress-check' ); ?>'); return; }

		var $btn = $(this);
		$btn.prop('disabled', true);
		$('#save-spinner').show();

		$.post(AJAX, {
			action:       'osq_companies_save',
			nonce:        NONCE,
			company_id:   $('#modal-company-id').val(),
			company_name: name,
			company_slug: slug,
			org_label_1:  $('#field-org1').val(),
			org_label_2:  $('#field-org2').val(),
			org_label_3:  $('#field-org3').val(),
			min_group_size: $('#field-min-group').val(),
			is_active:    $('#field-active').is(':checked') ? 1 : 0,
		}).done(function(res) {
			if (res.success) {
				closeModal();
				location.reload();
			} else {
				alert(res.data || '<?php esc_html_e( 'Error saving.', 'osq-stress-check' ); ?>');
			}
		}).fail(function() {
			alert('<?php esc_html_e( 'Request failed.', 'osq-stress-check' ); ?>');
		}).always(function() {
			$btn.prop('disabled', false);
			$('#save-spinner').hide();
		});
	});

	// ── Deactivate ────────────────────────────────────
	$(document).on('click', '.btn-deactivate-company', function() {
		var id   = $(this).data('id');
		var name = $(this).data('name');
		if (!confirm('「' + name + '」<?php esc_html_e( 'を非アクティブにしますか？', 'osq-stress-check' ); ?>')) return;
		var $btn = $(this);
		$.post(AJAX, { action: 'osq_companies_delete', nonce: NONCE, company_id: id })
			.done(function(res) {
				if (res.success) location.reload();
				else alert(res.data);
			});
	});

	// ── Switch company context ─────────────────────────
	$(document).on('click', '.btn-switch-company', function() {
		var id   = $(this).data('id');
		var $btn = $(this);
		$btn.prop('disabled', true).text('<?php esc_html_e( 'Switching…', 'osq-stress-check' ); ?>');
		$.post(AJAX, { action: 'osq_companies_switch', nonce: NONCE, company_id: id })
			.done(function(res) {
				if (res.success) {
					window.location.href = <?php echo wp_json_encode( home_url( '/osq-dashboard/' ) ); ?>;
				}
			})
			.always(function() { $btn.prop('disabled', false); });
	});

})(jQuery);
</script>
</body>
</html>
