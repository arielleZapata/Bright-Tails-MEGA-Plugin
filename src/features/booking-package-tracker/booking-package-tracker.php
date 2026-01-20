<?php
/**
 * Booking Package Tracker Feature
 * All PHP functionality for the booking package tracker block and shortcode
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Get user's credit balance from ledger
 * 
 * @param string $email User email address
 * @return int Total credits (sum of all delta values)
 */
function bt_get_user_credits($email) {
	global $wpdb;
	
	if (empty($email)) {
		return 0;
	}
	
	$ledger_table = $wpdb->prefix . 'bt_credits_ledger';
	
	// Check if table exists
	$table_exists = $wpdb->get_var($wpdb->prepare(
		"SHOW TABLES LIKE %s",
		$wpdb->esc_like($ledger_table)
	)) === $ledger_table;
	
	if (!$table_exists) {
		return 0;
	}
	
	// Sum all credits (delta) for this email
	$total_credits = $wpdb->get_var($wpdb->prepare(
		"SELECT COALESCE(SUM(delta), 0) FROM $ledger_table WHERE email = %s",
		sanitize_email($email)
	));
	
	return (int) $total_credits;
}

/**
 * Register the block type
 */
function brighttails_register_booking_tracker_block() {
	if (!defined('BRIGHTTAILS_PLUGIN_ROOT')) {
		$plugin_root = dirname(dirname(dirname(__DIR__)));
	} else {
		$plugin_root = BRIGHTTAILS_PLUGIN_ROOT;
	}
	
	$block_path = $plugin_root . '/build/features/booking-package-tracker';
	
	// Check if build directory exists, otherwise use source
	if (!file_exists($block_path . '/block.json')) {
		$block_path = __DIR__;
	}
	
	// Verify block.json exists before registering
	if (file_exists($block_path . '/block.json')) {
		$result = register_block_type($block_path);
		if (!$result) {
			// Log error for debugging (only in development)
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('Failed to register booking-package-tracker block from: ' . $block_path);
			}
		}
	}
}
add_action('init', 'brighttails_register_booking_tracker_block');

/**
 * Enqueue frontend scripts and styles for booking tracker
 * Called from shortcode render to ensure it runs on Elementor pages
 */
function bt_enqueue_booking_tracker_assets() {
	if (is_admin()) {
		return;
	}
	
	if (!defined('BRIGHTTAILS_PLUGIN_ROOT') || !defined('BRIGHTTAILS_PLUGIN_FILE')) {
		error_log('BT DEBUG: Constants not defined! BRIGHTTAILS_PLUGIN_ROOT or BRIGHTTAILS_PLUGIN_FILE missing.');
		return;
	}
	
	// Use BRIGHTTAILS_PLUGIN_FILE, but verify it's actually a file (not a directory)
	$plugin_file = BRIGHTTAILS_PLUGIN_FILE;
	
	// Verify the plugin file exists and is actually a file
	if (!file_exists($plugin_file) || !is_file($plugin_file)) {
		// Fallback: try to find index.php in the plugin root
		$fallback_file = BRIGHTTAILS_PLUGIN_ROOT . 'index.php';
		if (file_exists($fallback_file)) {
			$plugin_file = $fallback_file;
			error_log('BT DEBUG: Using fallback plugin file: ' . $plugin_file);
		} else {
			error_log('BT DEBUG: ERROR - Plugin file not found! BRIGHTTAILS_PLUGIN_FILE = ' . BRIGHTTAILS_PLUGIN_FILE);
			error_log('BT DEBUG: Fallback also failed: ' . $fallback_file);
		}
	}
	
	// Debug logging
	error_log('BT DEBUG: __FILE__ (current) = ' . __FILE__);
	error_log('BT DEBUG: BRIGHTTAILS_PLUGIN_ROOT = ' . BRIGHTTAILS_PLUGIN_ROOT);
	error_log('BT DEBUG: BRIGHTTAILS_PLUGIN_FILE = ' . BRIGHTTAILS_PLUGIN_FILE);
	error_log('BT DEBUG: plugins_url base file (final) = ' . $plugin_file);
	error_log('BT DEBUG: Plugin file exists = ' . (file_exists($plugin_file) ? 'YES' : 'NO'));
	error_log('BT DEBUG: Plugin file is_file = ' . (is_file($plugin_file) ? 'YES' : 'NO'));
	
	// JS file paths
	$frontend_js_path = BRIGHTTAILS_PLUGIN_ROOT . 'build/features/booking-package-tracker/frontend.js';
	$frontend_asset_path = BRIGHTTAILS_PLUGIN_ROOT . 'build/features/booking-package-tracker/frontend.asset.php';
	
	error_log('BT DEBUG: Checking JS file at: ' . $frontend_js_path);
	error_log('BT DEBUG: JS file exists: ' . (file_exists($frontend_js_path) ? 'YES' : 'NO'));
	
	$deps = [];
	$ver = null;
	
	if (file_exists($frontend_js_path)) {
		$ver = filemtime($frontend_js_path);
		
		if (file_exists($frontend_asset_path)) {
			$asset = require $frontend_asset_path;
			$deps = $asset['dependencies'] ?? [];
			$ver = $asset['version'] ?? $ver;
		}
		
		// Use BRIGHTTAILS_PLUGIN_URL directly to ensure correct plugin folder is included
		$js_url = BRIGHTTAILS_PLUGIN_URL . 'build/features/booking-package-tracker/frontend.js';
		error_log('BT DEBUG: JS URL (using BRIGHTTAILS_PLUGIN_URL) = ' . $js_url);
		
		wp_enqueue_script(
			'bt-booking-tracker',
			$js_url,
			$deps,
			$ver,
			true
		);
	} else {
		error_log('BT DEBUG: JS file NOT found at: ' . $frontend_js_path);
		error_log('BT DEBUG: Attempting to enqueue anyway with computed URL');
		
		// Enqueue anyway - WordPress will handle 404 gracefully
		// Use BRIGHTTAILS_PLUGIN_URL directly to ensure correct plugin folder is included
		$js_url = BRIGHTTAILS_PLUGIN_URL . 'build/features/booking-package-tracker/frontend.js';
		error_log('BT DEBUG: JS URL (computed, using BRIGHTTAILS_PLUGIN_URL) = ' . $js_url);
		
		wp_enqueue_script(
			'bt-booking-tracker',
			$js_url,
			[],
			time(), // Use timestamp for version if file doesn't exist
			true
		);
	}
	
	// Guard CSS filemtime (so it doesn't fatal if build is missing)
	$css_path = BRIGHTTAILS_PLUGIN_ROOT . 'build/style-index.css';
	if (file_exists($css_path)) {
		// Use BRIGHTTAILS_PLUGIN_URL directly to ensure correct plugin folder is included
		$css_url = BRIGHTTAILS_PLUGIN_URL . 'build/style-index.css';
		error_log('BT DEBUG: CSS URL (using BRIGHTTAILS_PLUGIN_URL) = ' . $css_url);
		
		wp_enqueue_style(
			'bt-booking-tracker-style',
			$css_url,
			[],
			filemtime($css_path)
		);
	}
}

/**
 * Legacy global enqueue (kept for backwards compatibility)
 * Note: For Elementor pages, enqueue is called from shortcode render instead
 */
function brighttails_enqueue_booking_tracker_frontend_assets() {
	bt_enqueue_booking_tracker_assets();
}
add_action('wp_enqueue_scripts', 'brighttails_enqueue_booking_tracker_frontend_assets');

/**
 * Add footer debug marker
 */
add_action('wp_footer', function () {
	echo "\n<!-- BT DEBUG: booking tracker footer hook ran -->\n";
}, 9999);

/**
 * Register shortcode for Booking Package Tracker
 * 
 * Usage: [brighttails-booking-tracker email="customer@example.com" credits="5" cal_email="cal@example.com"]
 * 
 * Parameters:
 * - email (required): Customer email address for Stripe lookup
 * - credits (optional): Manual credit override - number of credits remaining (overrides ledger calculation)
 * - cal_email (optional): Cal.com email address (defaults to email if not provided)
 * 
 * Examples:
 * - [brighttails-booking-tracker email="customer@example.com"]
 * - [brighttails-booking-tracker email="customer@example.com" credits="8"]
 * - [brighttails-booking-tracker email="stripe@example.com" cal_email="cal@example.com" credits="5"]
 */
function brighttails_booking_tracker_shortcode($atts) {
	// Enqueue assets here so Elementor pages that use shortcode always get assets
	bt_enqueue_booking_tracker_assets();
	
	// Parse shortcode attributes
	$atts = shortcode_atts(array(
		'email' => '',
		'cal_email' => '', // Optional Cal.com email (defaults to email if not provided)
		'credits' => '' // Optional: manually set credits (overrides ledger calculation)
	), $atts, 'brighttails-booking-tracker');
	
	$email = sanitize_email($atts['email']);
	$cal_email = !empty($atts['cal_email']) ? sanitize_email($atts['cal_email']) : $email;
	
	// If email is missing, return error
	if (empty($email)) {
		return '<div style="padding: 15px; background: #ffebee; border: 2px solid #f44336; border-radius: 4px; color: #c62828;">
			<strong>Error:</strong> Missing required email attribute.<br>
			<strong>Usage:</strong> [brighttails-booking-tracker email="customer@example.com" credits="5" cal_email="cal@example.com"]<br>
			<strong>Parameters:</strong><br>
			- email (required): Customer email address<br>
			- credits (optional): Manual credit override (number)<br>
			- cal_email (optional): Cal.com email (defaults to email)
		</div>';
	}
	
	// Get user credits from ledger, or use manual override
	if (!empty($atts['credits']) && is_numeric($atts['credits'])) {
		// Manual credits override
		$credits = (int) $atts['credits'];
	} else {
		// Get credits from ledger
		$credits = bt_get_user_credits($email);
	}
	
	$has_package = $credits > 0;
	
	// Prepare data for React component
	$tracker_data = array(
		'email' => $email,
		'calEmail' => $cal_email,
		'credits' => $credits,
		'hasPackage' => $has_package
	);
	
	// Build output using same pattern as block render
	ob_start();
	?>
	<div class="bt-booking-tracker-update-me" data-debug="true">
		<pre style="display: none;"><?php echo wp_json_encode($tracker_data); ?></pre>
		<script>
			// Inline debug script to verify HTML is present
			console.log("Booking Tracker: HTML element found", document.querySelector('.bt-booking-tracker-update-me'));
			var preEl = document.querySelector('.bt-booking-tracker-update-me pre');
			if (preEl) {
				console.log("Booking Tracker: Pre element found with data:", preEl.innerText);
			} else {
				console.warn("Booking Tracker: Pre element NOT found");
			}
		</script>
		<noscript>
			<div style="background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; flex-direction: column; max-width: 1024px; margin: 16px auto; padding: 24px;">
				<?php if (!$has_package): ?>
					<div style="text-align: center; padding: 32px 0;">
						<h2 style="color: #000000; font-family: 'Bowlby One SC', sans-serif; font-size: 24px; margin-bottom: 16px; margin-top: 0;">
							WAITING ON PURCHASE
						</h2>
						<p style="color: #000000; font-family: 'Outfit', sans-serif;">
							No booking package has been purchased yet. Purchase a package to start booking appointments.
						</p>
					</div>
				<?php else: ?>
					<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 32px 0;">
						<h2 style="color: #000000; font-family: 'Bowlby One SC', sans-serif; font-size: 24px; margin-bottom: 24px; margin-top: 0;">
							BOOKING PACKAGE
						</h2>
						<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; width: 100%; max-width: 384px;">
							<div style="display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 20px;">
								<strong style="color: #000000; font-family: 'Bowlby One SC', sans-serif;">
									REMAINING CREDITS
								</strong>
								<span style="color: #000000; font-family: 'Outfit', sans-serif; text-align: center; font-size: 30px; font-weight: bold;">
									<?php echo esc_html($credits); ?>
								</span>
							</div>
							<div style="display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 20px;">
								<strong style="color: #000000; font-family: 'Bowlby One SC', sans-serif;">
									STATUS
								</strong>
								<span style="color: #000000; font-family: 'Outfit', sans-serif; text-align: center;">
									Active
								</span>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</noscript>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode('brighttails-booking-tracker', 'brighttails_booking_tracker_shortcode');
