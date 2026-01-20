<?php
if (!defined('ABSPATH')) exit;

/**
 * Get the last payment for a customer from Stripe
 * 
 * REST endpoint: GET /wp-json/brighttails/v1/me/last-payment
 * 
 * Query params:
 * - email (required for now, for testing)
 * 
 * Returns JSON with:
 * - email
 * - amount (in dollars)
 * - currency
 * - status
 * - created (ISO string)
 * - id (Stripe object id)
 * - source (e.g., "payment_intent" or "charge")
 * - reason (if no payment found)
 * 
 * @param WP_REST_Request $request The REST request object
 * @return WP_REST_Response|WP_Error
 */
function bt_get_last_payment(WP_REST_Request $request) {
	// Ensure we have access to WordPress database
	global $wpdb;
	
	// Get emails from query params - Stripe email and Cal.com email can be different
	$email = $request->get_param('email'); // Stripe email
	$cal_email = $request->get_param('cal_email'); // Cal.com email (optional, defaults to Stripe email)
	
	// For now, allow email param for testing
	// Later: $email = wp_get_current_user()->user_email;
	if (empty($email)) {
		return new WP_REST_Response([
			'error' => 'Missing email parameter',
			'reason' => 'email_required'
		], 400);
	}
	
	// Sanitize emails
	$email = sanitize_email($email);
	if (!is_email($email)) {
		return new WP_REST_Response([
			'error' => 'Invalid email address',
			'reason' => 'invalid_email'
		], 400);
	}
	
	// If Cal.com email not provided, use Stripe email
	if (empty($cal_email)) {
		$cal_email = $email;
	} else {
		$cal_email = sanitize_email($cal_email);
		if (!is_email($cal_email)) {
			return new WP_REST_Response([
				'error' => 'Invalid Cal.com email address',
				'reason' => 'invalid_cal_email'
			], 400);
		}
	}
	
	error_log('BT Last Payment: Stripe email: ' . $email . ', Cal.com email: ' . $cal_email);
	
	// Get Stripe secret key from constant or WP option
	$stripe_secret_key = '';
	
	// Check for constant first
	if (defined('BT_STRIPE_SECRET_KEY') && !empty(BT_STRIPE_SECRET_KEY)) {
		$stripe_secret_key = BT_STRIPE_SECRET_KEY;
	} else {
		// Fall back to WP option
		$stripe_secret_key = get_option('bt_stripe_secret_key', '');
	}
	
	if (empty($stripe_secret_key)) {
		error_log('BT Last Payment: Stripe secret key not configured');
		return new WP_REST_Response([
			'error' => 'Stripe secret key not configured',
			'reason' => 'stripe_key_missing'
		], 500);
	}
	
	// Check if Stripe SDK is available
	if (!class_exists('\Stripe\Stripe')) {
		error_log('BT Last Payment: Stripe SDK not loaded. Check if vendor/autoload.php exists and stripe/stripe-php is installed via Composer.');
		error_log('BT Last Payment: Plugin path: ' . __DIR__);
		error_log('BT Last Payment: Vendor autoload exists: ' . (file_exists(__DIR__ . '/../../vendor/autoload.php') ? 'YES' : 'NO'));
		
		return new WP_REST_Response([
			'error' => 'Stripe SDK not loaded',
			'reason' => 'stripe_sdk_missing',
			'message' => 'Please install Stripe PHP SDK. Run: composer install in the plugin directory.',
			'help' => 'The stripe/stripe-php package is required. Install it via Composer: cd to plugin directory and run "composer require stripe/stripe-php"'
		], 500);
	}
	
	try {
		// Set Stripe API key
		\Stripe\Stripe::setApiKey($stripe_secret_key);
		
		// Debug: Log search start
		error_log('BT Last Payment: Searching for payments with email: ' . $email);
		
		$customer = null;
		$customer_id = null;
		$invoice_list = [];
		$last_payment = null;
		$source = null;
		
		// Strategy 1: Try to find customer by email (for registered customers)
		$customers = \Stripe\Customer::all([
			'email' => $email,
			'limit' => 1
		]);
		
		error_log('BT Last Payment: Found ' . count($customers->data) . ' customer(s) by email search');
		
		if (!empty($customers->data) && count($customers->data) > 0) {
			$customer = $customers->data[0];
			$customer_id = $customer->id;
			
			error_log('BT Last Payment: Found customer ID: ' . $customer_id);
			error_log('BT Last Payment: Customer name: ' . ($customer->name ?? 'N/A'));
			error_log('BT Last Payment: Customer email: ' . ($customer->email ?? 'N/A'));
			
			// Fetch invoices for the customer (increased limit to show all invoices)
			$invoices = \Stripe\Invoice::all([
				'customer' => $customer_id,
				'limit' => 100 // Show more invoices
			]);
			
			error_log('BT Last Payment: Found ' . count($invoices->data) . ' invoice(s) for customer');
			
			// Process invoices for response
			foreach ($invoices->data as $invoice) {
				$invoice_list[] = [
					'id' => $invoice->id,
					'number' => $invoice->number ?? null,
					'amount_due' => round(($invoice->amount_due ?? 0) / 100, 2),
					'amount_paid' => round(($invoice->amount_paid ?? 0) / 100, 2),
					'currency' => strtoupper($invoice->currency ?? 'usd'),
					'status' => $invoice->status ?? 'unknown',
					'created' => date('c', $invoice->created),
					'paid' => $invoice->paid ?? false,
					'hosted_invoice_url' => $invoice->hosted_invoice_url ?? null
				];
			}
			
			// Try to get most recent successful PaymentIntent by customer
			$payment_intents = \Stripe\PaymentIntent::all([
				'customer' => $customer_id,
				'limit' => 10
			]);
			
			error_log('BT Last Payment: Found ' . count($payment_intents->data) . ' payment intent(s) for customer');
			
			// Find the newest successful payment intent
			foreach ($payment_intents->data as $pi) {
				error_log('BT Last Payment: PaymentIntent ' . $pi->id . ' - status: ' . $pi->status . ', amount: $' . ($pi->amount / 100) . ', receipt_email: ' . ($pi->receipt_email ?? 'N/A'));
				if (in_array($pi->status, ['succeeded', 'requires_capture'])) {
					$last_payment = $pi;
					$source = 'payment_intent';
					error_log('BT Last Payment: Using PaymentIntent ' . $pi->id . ' as last payment');
					break;
				}
			}
			
			// If no PaymentIntent found, try Charges by customer
			if (!$last_payment) {
				$charges = \Stripe\Charge::all([
					'customer' => $customer_id,
					'limit' => 10
				]);
				
				error_log('BT Last Payment: Found ' . count($charges->data) . ' charge(s) for customer');
				
				foreach ($charges->data as $charge) {
					error_log('BT Last Payment: Charge ' . $charge->id . ' - paid: ' . ($charge->paid ? 'yes' : 'no') . ', amount: $' . ($charge->amount / 100) . ', receipt_email: ' . ($charge->receipt_email ?? 'N/A'));
					if ($charge->paid === true) {
						$last_payment = $charge;
						$source = 'charge';
						error_log('BT Last Payment: Using Charge ' . $charge->id . ' as last payment');
						break;
					}
				}
			}
		}
		
		// Strategy 2: For guest checkouts, search PaymentIntents directly by receipt_email
		if (!$last_payment) {
			error_log('BT Last Payment: No customer found or no payment via customer. Searching PaymentIntents by receipt_email...');
			
			// Note: Stripe API doesn't support direct filtering by receipt_email in list,
			// but we can search all recent PaymentIntents and filter by receipt_email
			$all_payment_intents = \Stripe\PaymentIntent::all([
				'limit' => 100 // Get more for guest checkout search
			]);
			
			error_log('BT Last Payment: Searching through ' . count($all_payment_intents->data) . ' recent PaymentIntents');
			
			foreach ($all_payment_intents->data as $pi) {
				$pi_email = $pi->receipt_email ?? null;
				if ($pi_email && strtolower($pi_email) === strtolower($email)) {
					error_log('BT Last Payment: Found PaymentIntent ' . $pi->id . ' with matching receipt_email: ' . $pi_email);
					if (in_array($pi->status, ['succeeded', 'requires_capture'])) {
						$last_payment = $pi;
						$source = 'payment_intent_guest';
						error_log('BT Last Payment: Using guest PaymentIntent ' . $pi->id . ' as last payment');
						break;
					}
				}
			}
		}
		
		// Strategy 3: Search Charges directly by receipt_email (for guest checkouts)
		if (!$last_payment) {
			error_log('BT Last Payment: Searching Charges by receipt_email...');
			
			$all_charges = \Stripe\Charge::all([
				'limit' => 100 // Get more for guest checkout search
			]);
			
			error_log('BT Last Payment: Searching through ' . count($all_charges->data) . ' recent Charges');
			
			foreach ($all_charges->data as $charge) {
				$charge_email = $charge->receipt_email ?? $charge->billing_details->email ?? null;
				if ($charge_email && strtolower($charge_email) === strtolower($email)) {
					error_log('BT Last Payment: Found Charge ' . $charge->id . ' with matching email: ' . $charge_email);
					if ($charge->paid === true) {
						$last_payment = $charge;
						$source = 'charge_guest';
						error_log('BT Last Payment: Using guest Charge ' . $charge->id . ' as last payment');
						break;
					}
				}
			}
		}
		
		// Strategy 4: Check Checkout Sessions (common for guest checkouts)
		if (!$last_payment) {
			error_log('BT Last Payment: Searching Checkout Sessions...');
			
			$checkout_sessions = \Stripe\Checkout\Session::all([
				'limit' => 100
			]);
			
			error_log('BT Last Payment: Searching through ' . count($checkout_sessions->data) . ' Checkout Sessions');
			
			foreach ($checkout_sessions->data as $session) {
				$session_email = $session->customer_details->email ?? $session->customer_email ?? null;
				if ($session_email && strtolower($session_email) === strtolower($email)) {
					error_log('BT Last Payment: Found Checkout Session ' . $session->id . ' with matching email: ' . $session_email);
					
					// If session has a payment_intent, retrieve it
					if (!empty($session->payment_intent)) {
						try {
							$pi = \Stripe\PaymentIntent::retrieve($session->payment_intent);
							if (in_array($pi->status, ['succeeded', 'requires_capture'])) {
								$last_payment = $pi;
								$source = 'checkout_session';
								error_log('BT Last Payment: Using PaymentIntent from Checkout Session ' . $session->id);
								break;
							}
						} catch (Exception $e) {
							error_log('BT Last Payment: Error retrieving PaymentIntent from session: ' . $e->getMessage());
						}
					}
				}
			}
		}
		
		// If still no payment found
		if (!$last_payment) {
			error_log('BT Last Payment: No payment found for email: ' . $email);
			return new WP_REST_Response([
				'found' => false,
				'email' => $email,
				'customer_id' => $customer_id,
				'invoices' => $invoice_list,
				'reason' => 'no_payment_found',
				'debug' => [
					'searched_email' => $email,
					'customer_found' => $customer ? true : false,
					'customer_id' => $customer_id,
					'customer_name' => $customer->name ?? null,
					'invoices_count' => count($invoice_list),
					'search_strategies' => 'customer -> payment_intents_by_email -> charges_by_email -> checkout_sessions'
				]
			], 200);
		}
		
		// Extract payment data
		$amount_cents = $last_payment->amount ?? 0;
		$amount_dollars = $amount_cents / 100;
		
		$currency = $last_payment->currency ?? 'usd';
		$status = $last_payment->status ?? ($last_payment->paid ? 'paid' : 'unknown');
		$created = $last_payment->created ?? time();
		$payment_id = $last_payment->id ?? '';
		
		// Convert created timestamp to ISO string
		$created_iso = date('c', $created);
		
		// Extract package/item purchased from Stripe
		$purchased_package = null;
		$package_credits = 0;
		$package_name = null;
		
		// Try to get package from Checkout Session metadata (most reliable)
		if ($source === 'checkout_session' || $source === 'payment_intent' || $source === 'payment_intent_guest') {
			try {
				// If we have a PaymentIntent, try to get the Checkout Session
				if (isset($last_payment->metadata->package)) {
					$purchased_package = $last_payment->metadata->package;
					error_log('BT Last Payment: Found package in PaymentIntent metadata: ' . $purchased_package);
				} else {
					// Try to find the Checkout Session that created this PaymentIntent
					$sessions = \Stripe\Checkout\Session::all([
						'payment_intent' => $payment_id,
						'limit' => 1
					]);
					
					if (!empty($sessions->data)) {
						$session = $sessions->data[0];
						$purchased_package = $session->metadata->package ?? null;
						if ($purchased_package) {
							error_log('BT Last Payment: Found package in Checkout Session metadata: ' . $purchased_package);
						}
					}
				}
				
				// Also check line items for package info
				if (!$purchased_package && isset($last_payment->description)) {
					$desc = strtolower($last_payment->description);
					if (strpos($desc, '8 pack') !== false || strpos($desc, '8-pack') !== false) {
						$purchased_package = '8_pack';
					} elseif (strpos($desc, '4 pack') !== false || strpos($desc, '4-pack') !== false) {
						$purchased_package = '4_pack';
					} elseif (strpos($desc, 'single') !== false || strpos($desc, '1 pack') !== false) {
						$purchased_package = 'single';
					}
					if ($purchased_package) {
						error_log('BT Last Payment: Found package in description: ' . $purchased_package);
					}
				}
			} catch (Exception $e) {
				error_log('BT Last Payment: Error extracting package info: ' . $e->getMessage());
			}
		}
		
		// If no package found from metadata/description, determine from payment amount
		if (!$purchased_package) {
			error_log('BT Last Payment: No package in metadata, checking payment amount: $' . $amount_dollars);
			
			// Map payment amount to package
			// $280 = 8 credits, $150 = 4 credits, $45 = 1 credit
			if (abs($amount_dollars - 280) < 1) {
				$purchased_package = '8_pack';
				error_log('BT Last Payment: Amount $280 detected, assigning 8-pack');
			} elseif (abs($amount_dollars - 150) < 1) {
				$purchased_package = '4_pack';
				error_log('BT Last Payment: Amount $150 detected, assigning 4-pack');
			} elseif (abs($amount_dollars - 45) < 1) {
				$purchased_package = 'single';
				error_log('BT Last Payment: Amount $45 detected, assigning single');
			} else {
				error_log('BT Last Payment: Unknown amount $' . $amount_dollars . ', defaulting to single');
				$purchased_package = 'single'; // Default fallback
			}
		}
		
		// Map package to credits (same as webhook handler)
		if ($purchased_package) {
			switch ($purchased_package) {
				case '8_pack': 
					$package_credits = 8; 
					$package_name = '8-Pack';
					break;
				case '4_pack': 
					$package_credits = 4; 
					$package_name = '4-Pack';
					break;
				case 'single': 
					$package_credits = 1; 
					$package_name = 'Single';
					break;
				default: 
					$package_credits = 1; 
					$package_name = 'Single';
					break;
			}
			error_log('BT Last Payment: Package ' . $purchased_package . ' = ' . $package_credits . ' credits');
		}
		
		// Get remaining credits from ledger
		global $wpdb;
		$remaining_credits = 0;
		$ledger_table = $wpdb->prefix . 'bt_credits_ledger';
		
		$table_exists = $wpdb->get_var($wpdb->prepare(
			"SHOW TABLES LIKE %s",
			$wpdb->esc_like($ledger_table)
		)) === $ledger_table;
		
		if ($table_exists) {
			$remaining_credits = $wpdb->get_var($wpdb->prepare(
				"SELECT COALESCE(SUM(delta), 0) FROM $ledger_table WHERE email = %s",
				sanitize_email($email)
			));
			$remaining_credits = (int) $remaining_credits;
			error_log('BT Last Payment: Remaining credits from ledger: ' . $remaining_credits);
		}
		
		// Query Cal.com API for bookings if API key is available
		$cal_bookings = [];
		$all_cal_bookings = []; // Store all bookings before filtering
		$cal_api_key = get_option('bt_cal_api_key', '');
		$cal_api_error = null;
		$cal_api_response_code = null;
		
		if (!empty($cal_api_key)) {
			try {
				error_log('BT Last Payment: Querying Cal.com API for bookings with email: ' . $cal_email);
				
				// Cal.com API endpoint for getting bookings
				$cal_api_url = 'https://api.cal.com/v2/bookings';
				$cal_headers = [
					'Authorization' => 'Bearer ' . $cal_api_key,
					'Content-Type' => 'application/json'
				];
				
				// Query bookings by attendee email (using Cal.com email, not Stripe email)
				// Note: Cal.com API might need different query parameters - try both attendeeEmail and email
				$cal_query_url = $cal_api_url . '?attendeeEmail=' . urlencode($cal_email);
				error_log('BT Last Payment: Cal.com API URL: ' . $cal_query_url);
				error_log('BT Last Payment: Cal.com email being queried: ' . $cal_email);
				
				// Also try querying without filter to see all bookings (for debugging)
				// But only if we have a purchase date to filter client-side
				if (isset($created_iso) && !empty($created_iso)) {
					// We'll filter by date after getting results, so we can query all bookings
					// Some Cal.com APIs might not support attendeeEmail filter, so try without it first
					$cal_query_url_all = $cal_api_url;
					error_log('BT Last Payment: Also trying Cal.com API without email filter: ' . $cal_query_url_all);
				}
				
				$cal_response = wp_remote_get($cal_query_url, [
					'headers' => $cal_headers,
					'timeout' => 10
				]);
				
				$cal_api_response_code = wp_remote_retrieve_response_code($cal_response);
				error_log('BT Last Payment: Cal.com API response code: ' . $cal_api_response_code);
				
				if (!is_wp_error($cal_response)) {
					$cal_body = wp_remote_retrieve_body($cal_response);
					error_log('BT Last Payment: Cal.com API raw response (first 2000 chars): ' . substr($cal_body, 0, 2000));
					error_log('BT Last Payment: Cal.com API full response length: ' . strlen($cal_body) . ' chars');
					
					$cal_data = json_decode($cal_body, true);
					
					if (json_last_error() !== JSON_ERROR_NONE) {
						error_log('BT Last Payment: Cal.com API JSON decode error: ' . json_last_error_msg());
						$cal_api_error = 'Invalid JSON response: ' . json_last_error_msg();
					} else {
						// Log the structure we received
						if (is_array($cal_data) && !empty($cal_data)) {
							error_log('BT Last Payment: Cal.com API response keys: ' . implode(', ', array_keys($cal_data)));
						} else {
							error_log('BT Last Payment: Cal.com API response is not an array or is empty. Type: ' . gettype($cal_data));
						}
						
						// Cal.com API v2 returns: { "status": "success", "data": [bookings array], "pagination": {...} }
						if (isset($cal_data['status']) && $cal_data['status'] === 'error') {
							// Error response
							if (isset($cal_data['error'])) {
								$cal_api_error = is_string($cal_data['error']) ? $cal_data['error'] : json_encode($cal_data['error']);
							} elseif (isset($cal_data['message'])) {
								$cal_api_error = $cal_data['message'];
							} else {
								$cal_api_error = 'API returned error status';
							}
							error_log('BT Last Payment: Cal.com API returned error status: ' . $cal_api_error);
						} elseif (isset($cal_data['data']) && is_array($cal_data['data'])) {
							// Success response with data array (Cal.com API v2 standard format)
							$all_cal_bookings = $cal_data['data'];
							error_log('BT Last Payment: Found ' . count($all_cal_bookings) . ' total bookings from Cal.com API');
							
							// Log first booking structure for debugging
							if (!empty($all_cal_bookings)) {
								$first_booking = $all_cal_bookings[0];
								error_log('BT Last Payment: First booking structure: ' . print_r($first_booking, true));
								if (is_array($first_booking) && !empty($first_booking)) {
									error_log('BT Last Payment: First booking keys: ' . implode(', ', array_keys($first_booking)));
								} else {
									error_log('BT Last Payment: First booking is not an array. Type: ' . gettype($first_booking));
								}
							}
							
							// Filter bookings to only those after purchase date
							if (!empty($all_cal_bookings)) {
								if (isset($created_iso) && !empty($created_iso)) {
									$purchase_timestamp = strtotime($created_iso);
									error_log('BT Last Payment: Filtering Cal.com bookings after purchase date: ' . $created_iso . ' (timestamp: ' . $purchase_timestamp . ', date: ' . date('Y-m-d H:i:s', $purchase_timestamp) . ')');
									
									$cal_bookings = array_filter($all_cal_bookings, function($booking) use ($purchase_timestamp, $created_iso) {
										$booking_id = $booking['uid'] ?? $booking['id'] ?? 'unknown';
										
										// Cal.com uses 'start' field (ISO 8601 UTC timestamp)
										if (isset($booking['start'])) {
											$booking_start = strtotime($booking['start']);
											$is_after = $booking_start >= $purchase_timestamp;
											error_log('BT Last Payment: Booking ' . $booking_id . ' - start: ' . $booking['start'] . ' (' . $booking_start . ', ' . date('Y-m-d H:i:s', $booking_start) . ') - after purchase (' . $purchase_timestamp . ', ' . date('Y-m-d H:i:s', $purchase_timestamp) . '): ' . ($is_after ? 'YES' : 'NO'));
											return $is_after;
										}
										// Fallback: check 'createdAt' if 'start' not available
										if (isset($booking['createdAt'])) {
											$booking_created = strtotime($booking['createdAt']);
											$is_after = $booking_created >= $purchase_timestamp;
											error_log('BT Last Payment: Booking ' . $booking_id . ' - createdAt: ' . $booking['createdAt'] . ' (' . $booking_created . ', ' . date('Y-m-d H:i:s', $booking_created) . ') - after purchase (' . $purchase_timestamp . ', ' . date('Y-m-d H:i:s', $purchase_timestamp) . '): ' . ($is_after ? 'YES' : 'NO'));
											return $is_after;
										}
										// If no date field, log and exclude it
										if (is_array($booking)) {
											error_log('BT Last Payment: Booking ' . $booking_id . ' - no date field found. Available fields: ' . implode(', ', array_keys($booking)));
										} else {
											error_log('BT Last Payment: Booking ' . $booking_id . ' - booking is not an array. Type: ' . gettype($booking));
										}
										return false;
									});
									
									// Re-index array after filtering
									$cal_bookings = array_values($cal_bookings);
									error_log('BT Last Payment: After filtering by purchase date, found ' . count($cal_bookings) . ' bookings out of ' . count($all_cal_bookings) . ' total');
									
									// If no bookings after filtering, log all booking dates for debugging
									if (count($cal_bookings) === 0 && count($all_cal_bookings) > 0) {
										error_log('BT Last Payment: WARNING - All bookings were filtered out! Listing all booking dates:');
										foreach ($all_cal_bookings as $idx => $booking) {
											$booking_id = $booking['uid'] ?? $booking['id'] ?? 'unknown';
											$start_date = $booking['start'] ?? 'N/A';
											$created_date = $booking['createdAt'] ?? 'N/A';
											error_log('BT Last Payment: Booking #' . ($idx + 1) . ' (ID: ' . $booking_id . ') - start: ' . $start_date . ', createdAt: ' . $created_date);
										}
									}
								} else {
									$cal_bookings = $all_cal_bookings;
									error_log('BT Last Payment: No purchase date available ($created_iso not set), returning all ' . count($cal_bookings) . ' bookings');
								}
							} else {
								$cal_bookings = [];
								error_log('BT Last Payment: No bookings found in Cal.com response data array');
							}
							
							// Log pagination info if available
							if (isset($cal_data['pagination'])) {
								error_log('BT Last Payment: Cal.com pagination - Total: ' . ($cal_data['pagination']['totalItems'] ?? 'N/A') . ', Returned: ' . ($cal_data['pagination']['returnedItems'] ?? 'N/A'));
							}
						} elseif (isset($cal_data['bookings']) && is_array($cal_data['bookings'])) {
							// Fallback: some versions might use 'bookings' field
							$all_cal_bookings = $cal_data['bookings'];
							error_log('BT Last Payment: Found ' . count($all_cal_bookings) . ' bookings in "bookings" field (non-standard format)');
							
							// Filter by purchase date if available
							if (!empty($all_cal_bookings) && isset($created_iso)) {
								$purchase_timestamp = strtotime($created_iso);
								$cal_bookings = array_filter($all_cal_bookings, function($booking) use ($purchase_timestamp) {
									if (isset($booking['start'])) {
										return strtotime($booking['start']) >= $purchase_timestamp;
									}
									if (isset($booking['createdAt'])) {
										return strtotime($booking['createdAt']) >= $purchase_timestamp;
									}
									return false;
								});
								$cal_bookings = array_values($cal_bookings);
								error_log('BT Last Payment: After filtering, found ' . count($cal_bookings) . ' bookings after purchase date');
							} else {
								$cal_bookings = $all_cal_bookings;
							}
						} elseif (is_array($cal_data) && !empty($cal_data) && isset($cal_data[0]) && is_array($cal_data[0])) {
							// Response might be a direct array of bookings (non-standard)
							$all_cal_bookings = $cal_data;
							error_log('BT Last Payment: Found ' . count($all_cal_bookings) . ' bookings as direct array (non-standard format)');
							
							// Filter by purchase date if available
							if (!empty($all_cal_bookings) && isset($created_iso)) {
								$purchase_timestamp = strtotime($created_iso);
								$cal_bookings = array_filter($all_cal_bookings, function($booking) use ($purchase_timestamp) {
									if (isset($booking['start'])) {
										return strtotime($booking['start']) >= $purchase_timestamp;
									}
									if (isset($booking['createdAt'])) {
										return strtotime($booking['createdAt']) >= $purchase_timestamp;
									}
									return false;
								});
								$cal_bookings = array_values($cal_bookings);
								error_log('BT Last Payment: After filtering, found ' . count($cal_bookings) . ' bookings after purchase date');
							} else {
								$cal_bookings = $all_cal_bookings;
							}
						} elseif (isset($cal_data['message'])) {
							$cal_api_error = $cal_data['message'];
							error_log('BT Last Payment: Cal.com API returned message: ' . $cal_api_error);
						} else {
							// Log full structure for debugging
							error_log('BT Last Payment: Cal.com API unexpected response structure. Full response: ' . $cal_body);
							error_log('BT Last Payment: Cal.com API response structure: ' . print_r($cal_data, true));
							if (is_array($cal_data) && !empty($cal_data)) {
								$cal_api_error = 'Unexpected response structure. Check logs for details. Response keys: ' . implode(', ', array_keys($cal_data));
							} else {
								$cal_api_error = 'Unexpected response structure. Response is not an array. Type: ' . gettype($cal_data);
							}
						}
					}
				} else {
					$cal_api_error = $cal_response->get_error_message();
					error_log('BT Last Payment: Cal.com API WP_Error: ' . $cal_api_error);
				}
			} catch (Exception $e) {
				$cal_api_error = $e->getMessage();
				error_log('BT Last Payment: Exception querying Cal.com API: ' . $cal_api_error);
				error_log('BT Last Payment: Exception trace: ' . $e->getTraceAsString());
			}
		} else {
			error_log('BT Last Payment: Cal.com API key not configured, skipping API query');
		}
		
		// Calculate completed bookings since purchase date
		$completed_bookings_count = 0;
		$purchase_date = date('Y-m-d H:i:s', $created);
		
		// $wpdb and $ledger_table already declared above
		$bookings_table = $wpdb->prefix . 'bt_bookings';
		
		// Method 1: Count negative deltas (bookings) in ledger since purchase date
		$table_exists = $wpdb->get_var($wpdb->prepare(
			"SHOW TABLES LIKE %s",
			$wpdb->esc_like($ledger_table)
		)) === $ledger_table;
		
		if ($table_exists) {
			$bookings_from_ledger = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM $ledger_table 
				WHERE email = %s 
				AND delta < 0 
				AND source != 'stripe'
				AND created_at >= %s",
				sanitize_email($email),
				$purchase_date
			));
			
			$completed_bookings_count = (int) $bookings_from_ledger;
			error_log('BT Last Payment: Found ' . $completed_bookings_count . ' bookings in ledger since ' . $purchase_date);
		}
		
		// Method 2: Also check bookings table for completed bookings since purchase
		$bookings_table_exists = $wpdb->get_var($wpdb->prepare(
			"SHOW TABLES LIKE %s",
			$wpdb->esc_like($bookings_table)
		)) === $bookings_table;
		
		$completed_bookings_from_table = 0;
		if ($bookings_table_exists) {
			// Count completed bookings (status = 'completed' or similar)
			$completed_bookings_from_table = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM $bookings_table 
				WHERE email = %s 
				AND status IN ('completed', 'confirmed', 'accepted')
				AND created_at >= %s",
				sanitize_email($email),
				$purchase_date
			));
			
			error_log('BT Last Payment: Found ' . $completed_bookings_from_table . ' completed bookings in bookings table since ' . $purchase_date);
			
			// Use ledger count as primary (more reliable for credit tracking)
			// But if ledger has no bookings and bookings table does, use that
			if ($completed_bookings_count === 0 && $completed_bookings_from_table > 0) {
				$completed_bookings_count = (int) $completed_bookings_from_table;
				error_log('BT Last Payment: Using bookings table count since ledger had none');
			}
		}
		
		// Debug: Log last payment details
		error_log('BT Last Payment: Last payment found - ID: ' . $payment_id . ', Amount: $' . round($amount_dollars, 2) . ', Status: ' . $status . ', Source: ' . $source);
		error_log('BT Last Payment: Completed bookings since purchase: ' . $completed_bookings_count);
		
		return new WP_REST_Response([
			'found' => true,
			'email' => $email,
			'cal_email' => $cal_email,
			'customer_id' => $customer_id,
			'customer_name' => ($customer && isset($customer->name)) ? $customer->name : null,
			'is_guest' => $customer_id ? false : true,
			'amount' => round($amount_dollars, 2),
			'currency' => strtoupper($currency),
			'status' => $status,
			'created' => $created_iso,
			'id' => $payment_id,
			'source' => $source,
			'invoices' => $invoice_list,
			'completed_bookings_since_purchase' => $completed_bookings_count,
			'purchase_date' => $created_iso,
			'purchased_package' => [
				'package_id' => $purchased_package,
				'package_name' => $package_name,
				'credits' => $package_credits
			],
			'remaining_credits' => $remaining_credits,
			'cal_bookings' => $cal_bookings,
			'cal_bookings_count' => count($cal_bookings),
			'cal_api_error' => $cal_api_error,
			'cal_api_response_code' => $cal_api_response_code,
			'cal_all_bookings_count' => isset($all_cal_bookings) ? count($all_cal_bookings) : 0,
			'debug' => [
				'stripe_email' => $email,
				'cal_email' => $cal_email,
				'customer_found' => $customer ? true : false,
				'customer_id' => $customer_id,
				'customer_name' => ($customer && isset($customer->name)) ? $customer->name : null,
				'customer_email' => ($customer && isset($customer->email)) ? $customer->email : null,
				'customer_created' => ($customer && isset($customer->created)) ? date('c', $customer->created) : null,
				'invoices_count' => count($invoice_list),
				'payment_source' => $source,
				'is_guest_checkout' => $customer_id ? false : true,
				'completed_bookings_count' => $completed_bookings_count,
				'purchase_date' => $created_iso,
				'purchased_package' => $purchased_package,
				'package_credits' => $package_credits,
				'remaining_credits' => $remaining_credits,
				'cal_api_queried' => !empty($cal_api_key),
				'cal_api_key_configured' => !empty($cal_api_key),
				'cal_api_error' => $cal_api_error,
				'cal_api_response_code' => $cal_api_response_code
			]
		], 200);
		
	} catch (\Stripe\Exception\ApiErrorException $e) {
		error_log('BT Last Payment: Stripe API error - ' . $e->getMessage());
		error_log('BT Last Payment: Stripe API error trace: ' . $e->getTraceAsString());
		return new WP_REST_Response([
			'error' => 'Stripe API error',
			'reason' => 'stripe_api_error',
			'detail' => $e->getMessage()
		], 500);
	} catch (\Error $e) {
		// Catch PHP 7+ errors (fatal errors, etc.)
		error_log('BT Last Payment: PHP Error - ' . $e->getMessage());
		error_log('BT Last Payment: PHP Error file: ' . $e->getFile() . ' line: ' . $e->getLine());
		error_log('BT Last Payment: PHP Error trace: ' . $e->getTraceAsString());
		return new WP_REST_Response([
			'error' => 'PHP Error',
			'reason' => 'php_error',
			'detail' => $e->getMessage(),
			'file' => $e->getFile(),
			'line' => $e->getLine()
		], 500);
	} catch (Exception $e) {
		error_log('BT Last Payment: General error - ' . $e->getMessage());
		error_log('BT Last Payment: General error trace: ' . $e->getTraceAsString());
		return new WP_REST_Response([
			'error' => 'Internal server error',
			'reason' => 'internal_error',
			'detail' => $e->getMessage()
		], 500);
	}
}
