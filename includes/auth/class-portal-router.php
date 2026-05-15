<?php
/**
 * Unified portal router (/osq-portal/).
 *
 * Phase 3a: a single login + landing URL for all OSQ users. After login,
 * the router resolves the user's primary dashboard based on capabilities
 * and redirects accordingly. Replaces the need for users to remember
 * different URLs for /osq-login, /osq-officer-login, /osq-admin-login.
 *
 * The legacy login URLs continue to work for backward compatibility.
 *
 * @package OSQ
 */

namespace OSQ\Auth;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PortalRouter {

	const PORTAL_SLUG = 'osq-portal';

	public function init() {
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'parse_request', array( $this, 'parse_request' ) );
		add_filter( 'template_include', array( $this, 'render' ), 99 );
	}

	public function register_query_vars( $vars ) {
		$vars[] = 'osq_portal_route';
		return $vars;
	}

	public function parse_request( $wp ) {
		if ( empty( $wp->request ) ) {
			return;
		}
		$request = trim( $wp->request, '/' );
		if ( self::PORTAL_SLUG === $request ) {
			$wp->query_vars['osq_portal_route'] = 'landing';
		}
	}

	public function render( $template ) {
		if ( 'landing' !== get_query_var( 'osq_portal_route' ) ) {
			return $template;
		}

		// Logged-out → send to the existing employee login (it handles all OSQ users).
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( home_url( '/' . EmployeeUiHandler::LOGIN_SLUG . '/' ) );
			exit;
		}

		// Logged-in: send to the unified dashboard.
		wp_safe_redirect( home_url( '/' . UnifiedDashboardHandler::SLUG . '/' ) );
		exit;
	}
}
