<?php
/**
 * Plugin Name:       OSQ Stress Check System
 * Description:       Ministry of Health, Labour and Welfare compliant stress check system for Japanese companies with bilingual support (Japanese/English).
 * Version:           1.0.0
 * Requires at least: 6.9
 * Requires PHP:      7.2.24
 * Author:            OSQ System
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       osq-stress-check
 * Domain Path:       /languages
 *
 * @package OSQ
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
	exit;
}

/*
 |--------------------------------------------------------------------------
 | Plugin Constants
 |--------------------------------------------------------------------------
 */

define('OSQ_VERSION', '1.0.0');
define('OSQ_PLUGIN_FILE', __FILE__);
define('OSQ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OSQ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OSQ_PLUGIN_BASENAME', plugin_basename(__FILE__));

/*
 |--------------------------------------------------------------------------
 | Activation / Deactivation Hooks (must be at top-level scope)
 |--------------------------------------------------------------------------
 */

register_activation_hook(__FILE__, array('OSQ\\Activator', 'activate'));
register_deactivation_hook(__FILE__, array('OSQ\\Deactivator', 'deactivate'));

/*
 |--------------------------------------------------------------------------
 | Autoloader
 |--------------------------------------------------------------------------
 | Maps the OSQ\ namespace to the includes/ directory.
 | E.g. OSQ\Plugin       => includes/class-osq-plugin.php
 |      OSQ\Activator    => includes/class-activator.php
 |      OSQ\Auth\RoleManager => includes/auth/class-role-manager.php
 */

spl_autoload_register(function ($class) {
	// Only handle classes in the OSQ namespace.
	$prefix = 'OSQ\\';
	$len = strlen($prefix);

	if (strncmp($prefix, $class, $len) !== 0) {
		return;
	}

	// Strip the namespace prefix.
	$relative_class = substr($class, $len);

	// Convert namespace separators to directory separators.
	$parts = explode('\\', $relative_class);
	$class_name = array_pop($parts);

	// Convert class name to file name: Plugin => class-plugin.php
	// Handles multi-word class names: ScoreCalculator => class-score-calculator.php
	// Handles numbers: Method1Calculator => class-method1-calculator.php
	$file_name = 'class-' . strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $class_name)) . '.php';

	// Build the subdirectory path (lowercase).
	$sub_dir = '';
	if (!empty($parts)) {
		$sub_dir = strtolower(implode(DIRECTORY_SEPARATOR, $parts)) . DIRECTORY_SEPARATOR;
	}

	// Try includes/ first (most classes live here).
	$file = OSQ_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $sub_dir . $file_name;

	if (file_exists($file)) {
		require_once $file;
		return;
	}

	// Fallback: try plugin root (e.g. admin/ directory).
	$file = OSQ_PLUGIN_DIR . $sub_dir . $file_name;

	if (file_exists($file)) {
		require_once $file;
	}
});

/*
 |--------------------------------------------------------------------------
 | Boot the Plugin
 |--------------------------------------------------------------------------
 | Defer initialization to the plugins_loaded hook so all plugins are
 | available and WordPress core is fully loaded.
 */

add_action('plugins_loaded', function () {
	OSQ\Plugin::get_instance()->init();
});