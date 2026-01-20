<?php
/**
 * Bright Tails Credit Manager
 * 
 * Admin interface for manually managing customer credits/lessons
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Add credit manager menu page
 */
function bt_add_credit_manager_menu() {
	add_submenu_page(
		'bright-tails-settings',        // Parent slug
		'Credit Manager',               // Page title
		'Credit Manager',               // Menu title
		'manage_options',               // Capability
		'bright-tails-credit-manager',  // Menu slug
		'bt_credit_manager_page'        // Callback function
	);
}
add_action('admin_menu', 'bt_add_credit_manager_menu');

/**
 * Handle credit adjustment form submission
 */
function bt_handle_credit_adjustment() {
	if (!current_user_can('manage_options')) {
		wp_die('Unauthorized');
	}
	
	if (!isset($_POST['bt_adjust_credits']) || !wp_verify_nonce($_POST['bt_credit_nonce'], 'bt_adjust_credits')) {
		return;
	}
	
	$email = sanitize_email($_POST['email'] ?? '');
	$delta = intval($_POST['delta'] ?? 0);
	$reason = sanitize_text_field($_POST['reason'] ?? 'Manual adjustment');
	
	if (empty($email) || !is_email($email)) {
		add_settings_error('bt_credit_messages', 'bt_credit_error', 'Invalid email address.', 'error');
		return;
	}
	
	if ($delta === 0) {
		add_settings_error('bt_credit_messages', 'bt_credit_error', 'Credit adjustment cannot be zero.', 'error');
		return;
	}
	
	global $wpdb;
	$ledger_table = $wpdb->prefix . 'bt_credits_ledger';
	
	// Check if table exists
	$table_exists = $wpdb->get_var($wpdb->prepare(
		"SHOW TABLES LIKE %s",
		$wpdb->esc_like($ledger_table)
	)) === $ledger_table;
	
	if (!$table_exists) {
		add_settings_error('bt_credit_messages', 'bt_credit_error', 'Credits ledger table does not exist. Please activate the plugin.', 'error');
		return;
	}
	
	// Insert credit adjustment
	$inserted = $wpdb->insert($ledger_table, [
		'email' => $email,
		'delta' => $delta,
		'source' => 'manual',
		'external_id' => 'admin_' . current_time('timestamp') . '_' . get_current_user_id(),
	]);
	
	if ($inserted) {
		// Get new balance
		$new_balance = $wpdb->get_var($wpdb->prepare(
			"SELECT COALESCE(SUM(delta), 0) FROM $ledger_table WHERE email = %s",
			$email
		));
		
		add_settings_error(
			'bt_credit_messages',
			'bt_credit_success',
			sprintf(
				'Credits adjusted successfully! %s %d credit(s). New balance: %d',
				$delta > 0 ? 'Added' : 'Removed',
				abs($delta),
				(int)$new_balance
			),
			'success'
		);
	} else {
		add_settings_error('bt_credit_messages', 'bt_credit_error', 'Failed to adjust credits. Database error.', 'error');
	}
}

/**
 * Credit Manager page HTML
 */
function bt_credit_manager_page() {
	// Handle form submission
	bt_handle_credit_adjustment();
	
	// Check user capabilities
	if (!current_user_can('manage_options')) {
		return;
	}
	
	// Display any messages
	settings_errors('bt_credit_messages');
	
	global $wpdb;
	$ledger_table = $wpdb->prefix . 'bt_credits_ledger';
	
	// Get recent credit transactions
	$recent_transactions = [];
	$table_exists = $wpdb->get_var($wpdb->prepare(
		"SHOW TABLES LIKE %s",
		$wpdb->esc_like($ledger_table)
	)) === $ledger_table;
	
	if ($table_exists) {
		$recent_transactions = $wpdb->get_results(
			"SELECT email, delta, source, external_id, created_at 
			FROM $ledger_table 
			ORDER BY created_at DESC 
			LIMIT 50",
			ARRAY_A
		);
	}
	
	// Get user balances
	$user_balances = [];
	if ($table_exists) {
		$balances = $wpdb->get_results(
			"SELECT email, SUM(delta) as balance 
			FROM $ledger_table 
			GROUP BY email 
			ORDER BY balance DESC 
			LIMIT 100",
			ARRAY_A
		);
		$user_balances = $balances;
	}
	?>
	<div class="wrap">
		<h1>Credit Manager</h1>
		<p>Manually adjust customer credits/lessons. Positive numbers add credits, negative numbers remove credits.</p>
		
		<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
			<!-- Credit Adjustment Form -->
			<div>
				<h2>Adjust Credits</h2>
				<form method="post" action="">
					<?php wp_nonce_field('bt_adjust_credits', 'bt_credit_nonce'); ?>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label for="email">Customer Email</label>
								</th>
								<td>
									<input 
										type="email" 
										id="email" 
										name="email" 
										required
										class="regular-text"
										placeholder="customer@example.com"
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="delta">Credit Adjustment</label>
								</th>
								<td>
									<input 
										type="number" 
										id="delta" 
										name="delta" 
										required
										class="small-text"
										placeholder="+5 or -3"
									/>
									<p class="description">Positive number adds credits, negative removes credits</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="reason">Reason (Optional)</label>
								</th>
								<td>
									<input 
										type="text" 
										id="reason" 
										name="reason" 
										class="regular-text"
										placeholder="Manual adjustment, refund, etc."
										value="Manual adjustment"
									/>
								</td>
							</tr>
						</tbody>
					</table>
					<?php submit_button('Adjust Credits', 'primary', 'bt_adjust_credits'); ?>
				</form>
			</div>
			
			<!-- User Balances -->
			<div>
				<h2>User Balances</h2>
				<?php if (!empty($user_balances)): ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th>Email</th>
								<th>Balance</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($user_balances as $balance): ?>
								<tr>
									<td><?php echo esc_html($balance['email']); ?></td>
									<td><strong><?php echo esc_html((int)$balance['balance']); ?></strong></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else: ?>
					<p>No user balances found.</p>
				<?php endif; ?>
			</div>
		</div>
		
		<!-- Recent Transactions -->
		<div style="margin-top: 30px;">
			<h2>Recent Transactions</h2>
			<?php if (!empty($recent_transactions)): ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>Date</th>
							<th>Email</th>
							<th>Delta</th>
							<th>Source</th>
							<th>External ID</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($recent_transactions as $txn): ?>
							<tr>
								<td><?php echo esc_html($txn['created_at']); ?></td>
								<td><?php echo esc_html($txn['email']); ?></td>
								<td>
									<strong style="color: <?php echo (int)$txn['delta'] > 0 ? 'green' : 'red'; ?>">
										<?php echo (int)$txn['delta'] > 0 ? '+' : ''; ?><?php echo esc_html($txn['delta']); ?>
									</strong>
								</td>
								<td><?php echo esc_html($txn['source']); ?></td>
								<td><?php echo esc_html($txn['external_id']); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else: ?>
				<p>No transactions found.</p>
			<?php endif; ?>
		</div>
	</div>
	<?php
}
