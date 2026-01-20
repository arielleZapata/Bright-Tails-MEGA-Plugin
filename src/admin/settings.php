<?php
/**
 * Bright Tails Admin Settings Page
 * 
 * Provides an admin interface to manage plugin settings including:
 * - Stripe webhook secret
 * - Cal.com webhook secret
 * - Cal.com booking URL
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Add admin menu page
 */
function bt_add_admin_menu() {
	add_options_page(
		'Bright Tails Settings',           // Page title
		'Bright Tails',                    // Menu title
		'manage_options',                  // Capability
		'bright-tails-settings',           // Menu slug
		'bt_settings_page'                 // Callback function
	);
}
add_action('admin_menu', 'bt_add_admin_menu');

/**
 * Register settings
 */
function bt_register_settings() {
	// Register the settings
	register_setting('bt_settings_group', 'bt_stripe_webhook_secret', array(
		'type' => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default' => ''
	));
	
	register_setting('bt_settings_group', 'bt_stripe_secret_key', array(
		'type' => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default' => ''
	));
	
	register_setting('bt_settings_group', 'bt_cal_webhook_secret', array(
		'type' => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default' => ''
	));
	
	register_setting('bt_settings_group', 'bt_cal_booking_url', array(
		'type' => 'string',
		'sanitize_callback' => 'esc_url_raw',
		'default' => ''
	));
	
	register_setting('bt_settings_group', 'bt_cal_api_key', array(
		'type' => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default' => ''
	));
}
add_action('admin_init', 'bt_register_settings');

/**
 * Settings page HTML
 */
function bt_settings_page() {
	// Check user capabilities
	if (!current_user_can('manage_options')) {
		return;
	}
	
	// Show success message if settings were saved
	if (isset($_GET['settings-updated'])) {
		add_settings_error(
			'bt_settings_messages',
			'bt_settings_message',
			'Settings saved successfully!',
			'success'
		);
	}
	
	// Display any settings errors
	settings_errors('bt_settings_messages');
	?>
	<div class="wrap">
		<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
		
		<form action="options.php" method="post">
			<?php
			settings_fields('bt_settings_group');
			do_settings_sections('bt_settings_group');
			?>
			
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="bt_stripe_webhook_secret">Stripe Webhook Secret</label>
						</th>
						<td>
							<input 
								type="text" 
								id="bt_stripe_webhook_secret" 
								name="bt_stripe_webhook_secret" 
								value="<?php echo esc_attr(get_option('bt_stripe_webhook_secret', '')); ?>" 
								class="regular-text"
								placeholder="whsec_..."
							/>
							<p class="description">Enter your Stripe webhook secret key (starts with whsec_)</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="bt_stripe_secret_key">Stripe Secret Key</label>
						</th>
						<td>
							<input 
								type="text" 
								id="bt_stripe_secret_key" 
								name="bt_stripe_secret_key" 
								value="<?php echo esc_attr(get_option('bt_stripe_secret_key', '')); ?>" 
								class="regular-text"
								placeholder="sk_live_... or sk_test_..."
							/>
							<p class="description">Enter your Stripe secret API key (starts with sk_live_ or sk_test_). Alternatively, define BT_STRIPE_SECRET_KEY constant in wp-config.php</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="bt_cal_webhook_secret">Cal.com Webhook Secret</label>
						</th>
						<td>
							<input 
								type="text" 
								id="bt_cal_webhook_secret" 
								name="bt_cal_webhook_secret" 
								value="<?php echo esc_attr(get_option('bt_cal_webhook_secret', '')); ?>" 
								class="regular-text"
								placeholder="Enter webhook secret"
							/>
							<p class="description">Enter your Cal.com webhook secret</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="bt_cal_booking_url">Cal.com Booking URL</label>
						</th>
						<td>
							<input 
								type="url" 
								id="bt_cal_booking_url" 
								name="bt_cal_booking_url" 
								value="<?php echo esc_url(get_option('bt_cal_booking_url', '')); ?>" 
								class="regular-text"
								placeholder="https://cal.com/..."
							/>
							<p class="description">Enter your Cal.com booking URL</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="bt_cal_api_key">Cal.com API Key</label>
						</th>
						<td>
							<input 
								type="text" 
								id="bt_cal_api_key" 
								name="bt_cal_api_key" 
								value="<?php echo esc_attr(get_option('bt_cal_api_key', '')); ?>" 
								class="regular-text"
								placeholder="cal_live_... or cal_test_..."
							/>
							<p class="description">Enter your Cal.com API key for querying bookings. Get it from Cal.com Settings → API Keys</p>
						</td>
					</tr>
				</tbody>
			</table>
			
			<?php submit_button('Save Settings'); ?>
		</form>
	</div>
	<?php
}
