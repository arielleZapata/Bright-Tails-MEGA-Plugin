<?php

/*
  Plugin Name: Bright Tails MEGA Plugin
  Version: 1.0
  Author: Bright Tails
  Description: Multi-feature plugin for Bright Tails including Pet Profile and Booking Package Tracker
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Define plugin constants
if (!defined('BRIGHTTAILS_PLUGIN_FILE')) {
	define('BRIGHTTAILS_PLUGIN_FILE', __FILE__);
}
if (!defined('BRIGHTTAILS_PLUGIN_ROOT')) {
	define('BRIGHTTAILS_PLUGIN_ROOT', plugin_dir_path(__FILE__)); // ends with trailing slash
}
if (!defined('BRIGHTTAILS_PLUGIN_URL')) {
	define('BRIGHTTAILS_PLUGIN_URL', plugin_dir_url(__FILE__)); // ends with trailing slash
}

// Load Composer autoloader (for Stripe SDK)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Load admin settings
require_once __DIR__ . '/src/admin/settings.php';

// Load credit manager
require_once __DIR__ . '/src/admin/credit-manager.php';

// Load Stripe webhook handler
require_once __DIR__ . '/src/includes/stripe-webhook.php';

// Load Stripe last payment handler
require_once __DIR__ . '/src/includes/stripe-last-payment.php';

// Load webhook routes
require_once __DIR__ . '/src/includes/routes-webhooks.php';

// Load booking package tracker installer
require_once __DIR__ . '/src/features/booking-package-tracker/installer.php';

// Register activation hook
register_activation_hook(__FILE__, 'bt_install_tables');

// Load feature files
require_once __DIR__ . '/src/features/pet-profile/pet-profile.php';
require_once __DIR__ . '/src/features/booking-package-tracker/booking-package-tracker.php';
