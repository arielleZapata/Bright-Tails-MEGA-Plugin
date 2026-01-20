<?php
/**
 * Booking Package Tracker Block Render
 * 
 * @var array $attributes Block attributes
 * @var string $content Block content
 * @var WP_Block $block Block instance
 */

if (!defined('ABSPATH')) exit;

$email = isset($attributes['email']) ? sanitize_email($attributes['email']) : '';

// Get user credits from ledger
$credits = bt_get_user_credits($email);
$has_package = $credits > 0;

// Prepare data for React component
$tracker_data = array(
	'email' => $email,
	'credits' => $credits,
	'hasPackage' => $has_package
);

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
		
		// Check if external script is loaded
		setTimeout(function() {
			if (typeof window.brighttailsBookingTrackerLoaded === "undefined") {
				console.error("Booking Tracker: External frontend.js script did NOT load!");
				console.log("Booking Tracker: Check Network tab for frontend.js file");
				
				// Try to find the script tag
				var scripts = document.querySelectorAll('script[src*="frontend.js"]');
				console.log("Booking Tracker: Found script tags:", scripts.length);
				scripts.forEach(function(script) {
					console.log("Booking Tracker: Script src:", script.src);
				});
			} else {
				console.log("Booking Tracker: External script loaded successfully");
			}
		}, 2000);
	</script>
	<noscript>
		<div style="background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; flex-direction: column; max-width: 1024px; margin: 16px auto; padding: 24px;">
			<?php if (empty($email)): ?>
				<div style="text-align: center; padding: 32px 0;">
					<p style="color: #dc2626; font-family: 'Outfit', sans-serif;">
						Error: No email address provided. Please configure the block settings.
					</p>
				</div>
			<?php elseif (!$has_package): ?>
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
							<strong style="color: #000000; font-family: 'Bowlby One SC', sans-serif; text-align: center; white-space: nowrap; min-width: 100%;">
								REMAINING CREDITS
							</strong>
							<span style="color: #000000; font-family: 'Outfit', sans-serif; text-align: center; font-size: 30px; font-weight: bold;">
								<?php echo esc_html($credits); ?>
							</span>
						</div>
						<div style="display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 20px;">
							<strong style="color: #000000; font-family: 'Bowlby One SC', sans-serif; text-align: center;">
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
