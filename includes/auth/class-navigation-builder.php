<?php
/**
 * Unified navigation builder.
 *
 * Phase 3a: produces a capability-driven sidebar menu shared across all
 * OSQ dashboards. A user holding multiple roles (e.g., employee + officer)
 * sees menu items for every section they have access to, regardless of which
 * dashboard URL they are on. This delivers the "1画面でシームレス" UX.
 *
 * @package OSQ
 */

namespace OSQ\Auth;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NavigationBuilder {

	/**
	 * Get sidebar menu items the current user is entitled to.
	 *
	 * Each entry: ['key', 'label', 'icon', 'url', 'capability'].
	 *
	 * @return array
	 */
	public static function get_menu_items() {
		$items = array();

		// 1. My stress check (employee feature).
		if ( CapabilityMatrix::user_has( CapabilityMatrix::TAKE_TEST )
			|| CapabilityMatrix::user_has( CapabilityMatrix::VIEW_OWN_RESULTS ) ) {
			$items[] = array(
				'key'        => 'my_check',
				'label'      => __( 'My Stress Check', 'osq-stress-check' ),
				'icon'       => 'dashicons-clipboard',
				'url'        => home_url( '/' . EmployeeUiHandler::DASHBOARD_SLUG . '/' ),
				'capability' => CapabilityMatrix::VIEW_OWN_RESULTS,
			);
		}

		// 2. Individual responses (officer / industrial physician).
		if ( CapabilityMatrix::user_has( CapabilityMatrix::VIEW_INDIVIDUAL_RESPONSES ) ) {
			$items[] = array(
				'key'        => 'individual',
				'label'      => __( 'Individual Responses', 'osq-stress-check' ),
				'icon'       => 'dashicons-id-alt',
				'url'        => home_url( '/' . OfficerUiHandler::DASHBOARD_SLUG . '/' ),
				'capability' => CapabilityMatrix::VIEW_INDIVIDUAL_RESPONSES,
			);
		}

		// 3. Employee management (general admin).
		if ( CapabilityMatrix::user_has( CapabilityMatrix::MANAGE_EMPLOYEES ) ) {
			$items[] = array(
				'key'        => 'manage',
				'label'      => __( 'Manage Employees', 'osq-stress-check' ),
				'icon'       => 'dashicons-groups',
				'url'        => home_url( '/' . AdminUiHandler::DASHBOARD_SLUG . '/?tab=employees' ),
				'capability' => CapabilityMatrix::MANAGE_EMPLOYEES,
			);
		}

		// 4. Group analysis (general admin).
		if ( CapabilityMatrix::user_has( CapabilityMatrix::VIEW_GROUP_ANALYSIS ) ) {
			$items[] = array(
				'key'        => 'analysis',
				'label'      => __( 'Group Analysis', 'osq-stress-check' ),
				'icon'       => 'dashicons-chart-bar',
				'url'        => home_url( '/' . AdminUiHandler::DASHBOARD_SLUG . '/?tab=analysis' ),
				'capability' => CapabilityMatrix::VIEW_GROUP_ANALYSIS,
			);
		}

		// 5. System configuration (general admin).
		if ( CapabilityMatrix::user_has( CapabilityMatrix::SYSTEM_CONFIG ) ) {
			$items[] = array(
				'key'        => 'settings',
				'label'      => __( 'Settings', 'osq-stress-check' ),
				'icon'       => 'dashicons-admin-settings',
				'url'        => home_url( '/' . AdminUiHandler::DASHBOARD_SLUG . '/?tab=settings' ),
				'capability' => CapabilityMatrix::SYSTEM_CONFIG,
			);
		}

		// 6. Companies management (wellanc super-admin only).
		if ( CapabilityMatrix::user_has( CapabilityMatrix::MANAGE_ALL_COMPANIES ) ) {
			$items[] = array(
				'key'        => 'companies',
				'label'      => __( 'All Companies (wellanc)', 'osq-stress-check' ),
				'icon'       => 'dashicons-building',
				'url'        => home_url( '/osq-companies/' ),
				'capability' => CapabilityMatrix::MANAGE_ALL_COMPANIES,
			);
		}

		return $items;
	}

	/**
	 * Render the unified sidebar HTML for inclusion in any dashboard template.
	 *
	 * @param string $active_key Which menu key to mark as active.
	 * @return void Echoes HTML.
	 */
	public static function render_sidebar( $active_key = '' ) {
		$items     = self::get_menu_items();
		$user      = wp_get_current_user();
		$logout_to = home_url( '/' . EmployeeUiHandler::LOGIN_SLUG . '/' );
		?>
		<aside class="osq-admin-sidebar osq-unified-sidebar">
			<div class="osq-sidebar-header">
				<span class="osq-logo"><?php esc_html_e( 'OSQ Portal', 'osq-stress-check' ); ?></span>
			</div>
			<nav class="osq-admin-nav">
				<ul>
					<?php foreach ( $items as $item ) : ?>
						<li class="<?php echo $item['key'] === $active_key ? 'active' : ''; ?>">
							<a href="<?php echo esc_url( $item['url'] ); ?>" style="color:inherit;text-decoration:none;display:flex;align-items:center;width:100%;">
								<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>"></span>
								<span><?php echo esc_html( $item['label'] ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>
			<div class="osq-sidebar-footer" style="margin-top:auto;padding:20px;border-top:1px solid rgba(255,255,255,0.1);">
				<div style="color:#94a3b8;font-size:12px;margin-bottom:8px;">
					<?php echo esc_html( $user->display_name ); ?>
				</div>
				<a href="<?php echo esc_url( wp_logout_url( $logout_to ) ); ?>" style="color:#fca5a5;font-size:13px;text-decoration:none;">
					<span class="dashicons dashicons-exit" style="font-size:16px;"></span>
					<?php esc_html_e( 'Logout', 'osq-stress-check' ); ?>
				</a>
			</div>
		</aside>
		<?php
	}

	/**
	 * Determine the user's "primary" dashboard URL — the first menu item they
	 * have access to. Used by /osq-portal/ landing to auto-route on login.
	 *
	 * @return string URL or empty string if user has no OSQ access.
	 */
	public static function get_primary_dashboard_url() {
		$items = self::get_menu_items();
		return ! empty( $items ) ? $items[0]['url'] : '';
	}
}
