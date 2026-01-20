<?php
if (!defined('ABSPATH')) exit;

/**
 * Register REST API routes for webhooks
 */
add_action('rest_api_init', function () {
	register_rest_route('brighttails/v1', '/webhooks/stripe', [
		'methods'  => 'POST',
		'callback' => 'bt_handle_stripe_webhook',
		'permission_callback' => '__return_true', // Stripe can't auth like WP users
	]);
	
	// Register last payment endpoint
	register_rest_route('brighttails/v1', '/me/last-payment', [
		'methods'  => 'GET',
		'callback' => 'bt_get_last_payment',
		'permission_callback' => '__return_true', // For now, allow email param for testing
	]);
});
