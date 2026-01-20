<?php
if (!defined('ABSPATH')) exit;

/**
 * Handle Stripe webhook requests
 * 
 * Processes checkout.session.completed events and adds credits to the ledger
 * 
 * @param WP_REST_Request $request The REST request object
 * @return WP_REST_Response|WP_Error
 */
function bt_handle_stripe_webhook(WP_REST_Request $request) {
	// 1) Grab raw body + signature header
	$payload = $request->get_body();
	$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

	// Get webhook secret from options
	$webhook_secret = get_option('bt_stripe_webhook_secret', '');
	
	if (empty($webhook_secret)) {
		return new WP_REST_Response(['error' => 'Stripe webhook secret not set'], 500);
	}

	// 2) Verify signature + construct event
	try {
		if (!class_exists('\Stripe\Webhook')) {
			return new WP_REST_Response(['error' => 'Stripe SDK not loaded'], 500);
		}

		$event = \Stripe\Webhook::constructEvent(
			$payload,
			$sig_header,
			$webhook_secret
		);
	} catch (Exception $e) {
		return new WP_REST_Response(['error' => 'Invalid signature', 'detail' => $e->getMessage()], 400);
	}

	// 3) Only handle the event you need for MVP
	if ($event->type !== 'checkout.session.completed') {
		return new WP_REST_Response(['received' => true, 'ignored' => $event->type], 200);
	}

	$session = $event->data->object;

	// 4) Pull email from session
	$email = $session->customer_details->email ?? $session->customer_email ?? null;
	if (!$email) {
		return new WP_REST_Response(['error' => 'No customer email found on session'], 400);
	}

	// 5) Decide credits from metadata or payment amount
	$package = $session->metadata->package ?? null;
	$amount_total = $session->amount_total ?? 0; // in cents
	$amount_dollars = $amount_total / 100;

	// If no package in metadata, determine from payment amount
	if (!$package) {
		// $280 = 8 credits, $150 = 4 credits, $45 = 1 credit
		if (abs($amount_dollars - 280) < 1) {
			$package = '8_pack';
		} elseif (abs($amount_dollars - 150) < 1) {
			$package = '4_pack';
		} elseif (abs($amount_dollars - 45) < 1) {
			$package = 'single';
		} else {
			$package = 'single'; // Default fallback
		}
	}

	$credits = 0;
	switch ($package) {
		case '8_pack': $credits = 8; break;
		case '4_pack': $credits = 4; break;
		case 'single': $credits = 1; break;
		default: $credits = 1; break; // safe default
	}

	// 6) Idempotency: avoid duplicate credits for same Stripe session
	global $wpdb;
	$ledger_table = $wpdb->prefix . 'bt_credits_ledger';

	$external_id = $session->id; // checkout session id

	$already = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM $ledger_table WHERE source=%s AND external_id=%s LIMIT 1",
			'stripe',
			$external_id
		)
	);

	if ($already) {
		return new WP_REST_Response(['received' => true, 'duplicate' => true], 200);
	}

	// 7) Insert ledger row
	$inserted = $wpdb->insert($ledger_table, [
		'email' => sanitize_email($email),
		'delta' => (int) $credits,
		'source' => 'stripe',
		'external_id' => sanitize_text_field($external_id),
	]);

	if (!$inserted) {
		return new WP_REST_Response(['error' => 'DB insert failed'], 500);
	}

	return new WP_REST_Response([
		'received' => true,
		'email' => $email,
		'credits_added' => $credits,
		'session_id' => $external_id
	], 200);
}
