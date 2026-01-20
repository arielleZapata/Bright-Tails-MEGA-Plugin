<?php
/**
 * Pet Profile Feature
 * All PHP functionality for the pet profile block and shortcode
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Register the block type (build output goes to /build at root)
function brighttails_register_pet_profile_block() {
	if (!defined('BRIGHTTAILS_PLUGIN_ROOT')) {
		// Fallback if constant not defined
		$plugin_root = dirname(dirname(dirname(__DIR__)));
	} else {
		$plugin_root = BRIGHTTAILS_PLUGIN_ROOT;
	}
	register_block_type($plugin_root . '/build');
}
add_action('init', 'brighttails_register_pet_profile_block');

// Enqueue frontend scripts and styles for shortcode/block usage
function brighttails_enqueue_pet_profile_frontend_assets() {
	if (!is_admin()) {
		// Use plugin file for plugins_url() base (not directory)
		$plugin_file = defined('BRIGHTTAILS_PLUGIN_FILE') ? BRIGHTTAILS_PLUGIN_FILE : __FILE__;
		$plugin_root = defined('BRIGHTTAILS_PLUGIN_ROOT') ? BRIGHTTAILS_PLUGIN_ROOT : dirname(dirname(dirname(__DIR__))) . '/';
		
		$frontend_asset_file = $plugin_root . 'build/frontend.asset.php';
		if (file_exists($frontend_asset_file)) {
			$frontend_asset = require $frontend_asset_file;
			
			wp_enqueue_script(
				'brighttails-pet-profile-frontend',
				plugins_url('build/frontend.js', $plugin_file),
				$frontend_asset['dependencies'],
				$frontend_asset['version'],
				true
			);
		}
		
		$style_file = $plugin_root . 'build/style-index.css';
		if (file_exists($style_file)) {
			wp_enqueue_style(
				'brighttails-pet-profile-style',
				plugins_url('build/style-index.css', $plugin_file),
				array(),
				filemtime($style_file)
			);
		}
	}
}
add_action('wp_enqueue_scripts', 'brighttails_enqueue_pet_profile_frontend_assets');

// Register shortcode for Elementor and general use
// Usage: [brighttails-pet-profile name="Butters" owneremail="owner@example.com"]
function brighttails_pet_profile_shortcode($atts) {
	// Enqueue assets when shortcode is used (important for Elementor pages)
	brighttails_enqueue_pet_profile_frontend_assets();
	
	// Debug mode - set to false in production
	$debug = false;
	$debug_messages = array();
	
	// Parse shortcode attributes - only name and owneremail
	$atts = shortcode_atts(array(
		'name' => '',
		'owneremail' => ''
	), $atts, 'brighttails-pet-profile');
	
	$dog_name = sanitize_text_field($atts['name']);
	$owner_email = sanitize_email($atts['owneremail']);
	
	// If name or email is missing, return error
	if (empty($dog_name) || empty($owner_email)) {
		$error_msg = 'Bright Tails Pet Profile Error: Missing required attributes. ';
		$error_msg .= 'Name: ' . ($dog_name ? htmlspecialchars($dog_name) : 'EMPTY') . ', ';
		$error_msg .= 'Email: ' . ($owner_email ? htmlspecialchars($owner_email) : 'EMPTY');
		return '<div style="padding: 10px; background: #ffebee; border: 2px solid #f44336; color: #c62828; border-radius: 4px; margin: 10px 0;"><strong>Error:</strong> ' . esc_html($error_msg) . '</div>';
	}
	
	// Initialize attributes with defaults - use "TBD" for missing data
	$attributes = array(
		'name' => $dog_name,
		'age' => 'TBD',
		'breed' => 'TBD',
		'weight' => 'TBD',
		'imageUrl' => '',
		'ownerEmail' => $owner_email
	);
	
	// FIRST: Try to get data from Elementor form submissions
	$submission_result = brighttails_get_elementor_submission($dog_name, $owner_email, $debug_messages);
	$submission = is_array($submission_result) ? $submission_result : null;
	
	if ($submission) {
		// Map Elementor form fields to attributes
		$fields = $submission['fields'];
		
		// Extract data from form submission using specific Elementor field IDs
		// Pet Name: field_cd4e7e2
		// Weight: field_0bd9a2f
		// Breed: field_099ae4c
		// Age: field_b64f13a
		// Image: field_64fc967
		
		// Breed
		if (isset($fields['field_099ae4c']) && !empty($fields['field_099ae4c'])) {
			$attributes['breed'] = sanitize_text_field($fields['field_099ae4c']);
		} elseif (isset($fields['breed']) && !empty($fields['breed'])) {
			$attributes['breed'] = sanitize_text_field($fields['breed']);
		}
		
		// Weight
		if (isset($fields['field_0bd9a2f']) && !empty($fields['field_0bd9a2f'])) {
			$weight_value = sanitize_text_field($fields['field_0bd9a2f']);
			if (strpos(strtolower($weight_value), 'lbs') === false && is_numeric(trim($weight_value))) {
				$attributes['weight'] = trim($weight_value) . ' lbs';
			} else {
				$attributes['weight'] = $weight_value;
			}
		} elseif (isset($fields['weight']) && !empty($fields['weight'])) {
			$attributes['weight'] = sanitize_text_field($fields['weight']);
		}
		
		// Age - convert age in years/months to start date for calculation
		if (isset($fields['field_b64f13a']) && !empty($fields['field_b64f13a'])) {
			$age_value = sanitize_text_field($fields['field_b64f13a']);
			if (is_numeric($age_value)) {
				$years = intval($age_value);
				$start_date = date('Y-m-d', strtotime("-{$years} years"));
				$attributes['age'] = $start_date;
			} else {
				$attributes['age'] = $age_value;
			}
		} elseif (isset($fields['age']) && !empty($fields['age'])) {
			$age_value = sanitize_text_field($fields['age']);
			if (is_numeric($age_value)) {
				$years = intval($age_value);
				$start_date = date('Y-m-d', strtotime("-{$years} years"));
				$attributes['age'] = $start_date;
			} else {
				$attributes['age'] = $age_value;
			}
		} elseif (isset($fields['age_started']) && !empty($fields['age_started'])) {
			$attributes['age'] = sanitize_text_field($fields['age_started']);
		}
		
		// Image
		if (isset($fields['field_64fc967']) && !empty($fields['field_64fc967'])) {
			$image_value = $fields['field_64fc967'];
			if (filter_var($image_value, FILTER_VALIDATE_URL)) {
				$attributes['imageUrl'] = esc_url_raw($image_value);
			} elseif (is_numeric($image_value)) {
				$image_url = wp_get_attachment_image_url($image_value, 'full');
				if ($image_url) {
					$attributes['imageUrl'] = $image_url;
				}
			}
		} elseif (isset($fields['image']) && !empty($fields['image'])) {
			$image_value = $fields['image'];
			if (filter_var($image_value, FILTER_VALIDATE_URL)) {
				$attributes['imageUrl'] = esc_url_raw($image_value);
			} elseif (is_numeric($image_value)) {
				$image_url = wp_get_attachment_image_url($image_value, 'full');
				if ($image_url) {
					$attributes['imageUrl'] = $image_url;
				}
			}
		}
	} else {
		// FALLBACK: Search all posts/pages for matching block
		$query_args = array(
			'post_type' => array('page', 'post'),
			'posts_per_page' => -1,
			'post_status' => array('publish', 'draft', 'private'),
		);
		
		$posts = get_posts($query_args);
		
		$found_count = 0;
		$matched_count = 0;
		
		foreach ($posts as $post) {
			$content = $post->post_content;
			
			if (has_blocks($content)) {
				$blocks = parse_blocks($content);
				
				$found_count += brighttails_count_blocks_recursive($blocks);
				$found_attrs = brighttails_find_block_by_name_email($blocks, $dog_name, $owner_email);
				
				if ($found_attrs) {
					if (!empty($found_attrs['breed'])) {
						$attributes['breed'] = $found_attrs['breed'];
					}
					if (!empty($found_attrs['weight'])) {
						$attributes['weight'] = $found_attrs['weight'];
					}
					if (!empty($found_attrs['age'])) {
						$attributes['age'] = $found_attrs['age'];
					}
					if (!empty($found_attrs['imageUrl'])) {
						$attributes['imageUrl'] = $found_attrs['imageUrl'];
					} elseif (!empty($found_attrs['imageId'])) {
						$image_url = wp_get_attachment_image_url($found_attrs['imageId'], 'full');
						if ($image_url) {
							$attributes['imageUrl'] = $image_url;
						}
					}
					
					$matched_count++;
					break;
				}
			}
		}
	}
	
	// Build output
	$output = '';
	$output .= '<div class="tailwind-update-me"><pre style="display: none;">' . wp_json_encode($attributes) . '</pre></div>';
	
	// Show info message if no submission found (data will show as TBD)
	if (!$submission) {
		$output .= '<div style="padding: 10px; background: #e3f2fd; border: 2px solid #2196f3; color: #1565c0; border-radius: 4px; margin: 10px 0; font-size: 12px;">';
		$output .= '<strong>Info:</strong> No form submission found. Showing default values (TBD for missing data). ';
		$output .= 'Submit the intake form to update this profile.';
		$output .= '</div>';
	}
	
	return $output;
}
add_shortcode('brighttails-pet-profile', 'brighttails_pet_profile_shortcode');

// Helper function to get Elementor form submission by name and email
function brighttails_get_elementor_submission($dog_name, $owner_email, &$debug_messages = array()) {
	global $wpdb;
	
	$submission_table = $wpdb->prefix . 'e_submissions';
	$submission_meta_table = $wpdb->prefix . 'e_submissions_values';
	
	$debug_messages[] = '=== ELEMENTOR SUBMISSION SEARCH DEBUG ===';
	$debug_messages[] = 'Searching for: Name="' . htmlspecialchars($dog_name) . '", Email="' . htmlspecialchars($owner_email) . '"';
	$debug_messages[] = 'Looking for tables: ' . $submission_table . ' and ' . $submission_meta_table;
	
	$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$submission_table'") == $submission_table;
	$meta_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$submission_meta_table'") == $submission_meta_table;
	
	if (!$table_exists || !$meta_table_exists) {
		return null;
	}
	
	$total_submissions = $wpdb->get_var("SELECT COUNT(*) FROM {$submission_table}");
	
	$search_name = strtolower(trim($dog_name));
	$search_email = strtolower(trim($owner_email));
	
	$query = $wpdb->prepare(
		"SELECT DISTINCT s.id, s.element_id, s.form_name, s.created_at
		FROM {$submission_table} s
		INNER JOIN {$submission_meta_table} sv ON s.id = sv.submission_id
		WHERE LOWER(TRIM(sv.value)) = %s
		ORDER BY s.created_at DESC
		LIMIT 50",
		$search_email
	);
	
	$submission_records = $wpdb->get_results($query);
	
	if (empty($submission_records)) {
		$query = $wpdb->prepare(
			"SELECT DISTINCT s.id, s.element_id, s.form_name, s.created_at
			FROM {$submission_table} s
			INNER JOIN {$submission_meta_table} sv ON s.id = sv.submission_id
			WHERE LOWER(TRIM(sv.value)) LIKE %s
			ORDER BY s.created_at DESC
			LIMIT 50",
			'%' . $wpdb->esc_like($search_email) . '%'
		);
		
		$submission_records = $wpdb->get_results($query);
	}
	
	if (empty($submission_records)) {
		return null;
	}
	
	foreach ($submission_records as $index => $submission_record) {
		$fields_query = $wpdb->prepare(
			"SELECT `key`, `value` FROM {$submission_meta_table} WHERE submission_id = %d",
			$submission_record->id
		);
		
		$fields = array();
		$field_rows = $wpdb->get_results($fields_query);
		
		$fields_lower = array();
		foreach ($field_rows as $row) {
			$fields[$row->key] = $row->value;
			$fields_lower[strtolower($row->key)] = $row->value;
		}
		
		$submission_email = '';
		if (isset($fields['email'])) {
			$submission_email = strtolower(trim($fields['email']));
		} elseif (isset($fields['Your Email'])) {
			$submission_email = strtolower(trim($fields['Your Email']));
		} elseif (isset($fields_lower['email'])) {
			$submission_email = strtolower(trim($fields_lower['email']));
		} else {
			foreach ($fields as $key => $value) {
				if (strpos(strtolower($key), 'email') !== false || filter_var($value, FILTER_VALIDATE_EMAIL)) {
					$submission_email = strtolower(trim($value));
					break;
				}
			}
		}
		
		if ($submission_email !== $search_email) {
			continue;
		}
		
		$submission_name = '';
		
		foreach ($fields as $key => $value) {
			if (!empty($value) && strtolower(trim($value)) === $search_name) {
				$submission_name = strtolower(trim($value));
				break;
			}
		}
		
		if (empty($submission_name)) {
			if (isset($fields['Pet Name'])) {
				$submission_name = strtolower(trim($fields['Pet Name']));
			} elseif (isset($fields['dog_name'])) {
				$submission_name = strtolower(trim($fields['dog_name']));
			} elseif (isset($fields['pet_name'])) {
				$submission_name = strtolower(trim($fields['pet_name']));
			} elseif (isset($fields_lower['pet name'])) {
				$submission_name = strtolower(trim($fields_lower['pet name']));
			} elseif (isset($fields_lower['dog_name'])) {
				$submission_name = strtolower(trim($fields_lower['dog_name']));
			} elseif (isset($fields_lower['pet_name'])) {
				$submission_name = strtolower(trim($fields_lower['pet_name']));
			}
		}
		
		if (empty($submission_name)) {
			foreach ($fields as $key => $value) {
				$key_lower = strtolower($key);
				$value_lower = strtolower(trim($value));
				
				if ($key === 'name' || $key_lower === 'name' ||
					strpos($key_lower, 'email') !== false 
					|| strpos($key_lower, 'first') !== false
					|| strpos($key_lower, 'last') !== false
					|| strpos($key_lower, 'phone') !== false
					|| strpos($key_lower, 'address') !== false
					|| strpos($key_lower, 'message') !== false
					|| strpos($key_lower, 'notes') !== false
					|| strpos($key_lower, 'service') !== false
					|| strpos($key_lower, 'time') !== false
					|| strpos($key_lower, 'date') !== false
					|| filter_var($value, FILTER_VALIDATE_EMAIL)
					|| is_numeric($value) || empty($value)) {
					continue;
				}
				
				if (strpos($key_lower, 'pet') !== false || 
					strpos($key_lower, 'dog') !== false ||
					(!empty($value) && strlen($value) > 2 && strlen($value) < 30 && !is_numeric($value))) {
					$submission_name = $value_lower;
					break;
				}
			}
		}
		
		if ($submission_name === $search_name) {
			return array(
				'id' => $submission_record->id,
				'fields' => $fields,
				'created_at' => $submission_record->created_at
			);
		} elseif (empty($submission_name) && !empty($submission_email)) {
			return array(
				'id' => $submission_record->id,
				'fields' => $fields,
				'created_at' => $submission_record->created_at
			);
		}
	}
	
	return null;
}

// Helper function to count blocks recursively
function brighttails_count_blocks_recursive($blocks) {
	$count = 0;
	foreach ($blocks as $block) {
		if ($block['blockName'] === 'brighttails/brighttailsmegaplugin') {
			$count++;
		}
		if (!empty($block['innerBlocks'])) {
			$count += brighttails_count_blocks_recursive($block['innerBlocks']);
		}
	}
	return $count;
}

// Helper function to find block by name and email
function brighttails_find_block_by_name_email($blocks, $dog_name, $owner_email) {
	foreach ($blocks as $block) {
		if ($block['blockName'] === 'brighttails/brighttailsmegaplugin') {
			$attrs = $block['attrs'];
			
			$block_name = isset($attrs['name']) ? strtolower(trim($attrs['name'])) : '';
			$block_email = isset($attrs['ownerEmail']) ? strtolower(trim($attrs['ownerEmail'])) : '';
			$search_name = strtolower(trim($dog_name));
			$search_email = strtolower(trim($owner_email));
			
			if ($block_name === $search_name && $block_email === $search_email) {
				return $attrs;
			}
		}
		
		if (!empty($block['innerBlocks'])) {
			$found = brighttails_find_block_by_name_email($block['innerBlocks'], $dog_name, $owner_email);
			if ($found) {
				return $found;
			}
		}
	}
	
	return null;
}

// Add Google Fonts preconnect for Bowlby One SC
function brighttails_add_font_preconnect() {
	echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
	echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
}
add_action('wp_head', 'brighttails_add_font_preconnect', 1);

// Handle Elementor Pro form submissions to update pet information
add_action('elementor_pro/forms/new_record', function($record, $handler) {
	$raw_fields = $record->get('fields');
	$fields = [];
	foreach ($raw_fields as $id => $field) {
		$fields[$id] = $field['value'];
	}

	$dog_name = isset($fields['dog_name']) ? sanitize_text_field($fields['dog_name']) : '';
	$email = isset($fields['email']) ? sanitize_email($fields['email']) : '';
	$breed = isset($fields['breed']) ? sanitize_text_field($fields['breed']) : '';
	$weight = isset($fields['weight']) ? sanitize_text_field($fields['weight']) : '';
	$age = isset($fields['age']) ? sanitize_text_field($fields['age']) : '';

	if (empty($dog_name) || empty($email)) {
		return;
	}

	$query_args = array(
		'post_type' => array('page', 'post'),
		'posts_per_page' => -1,
		'post_status' => 'publish',
	);

	$posts = get_posts($query_args);

	foreach ($posts as $post) {
		$content = $post->post_content;
		
		if (has_blocks($content)) {
			$blocks = parse_blocks($content);
			
			$updated = false;
			$blocks = brighttails_update_block_recursive($blocks, $dog_name, $email, $breed, $weight, $age, $updated);
			
			if ($updated) {
				$new_content = serialize_blocks($blocks);
				
				wp_update_post(array(
					'ID' => $post->ID,
					'post_content' => $new_content,
				));
			}
		}
	}
}, 10, 2);

// Helper function to recursively update blocks
function brighttails_update_block_recursive($blocks, $dog_name, $email, $breed, $weight, $age, &$updated) {
	foreach ($blocks as &$block) {
		if ($block['blockName'] === 'brighttails/brighttailsmegaplugin') {
			$attrs = $block['attrs'];
			
			$block_name = isset($attrs['name']) ? strtolower(trim($attrs['name'])) : '';
			$block_email = isset($attrs['ownerEmail']) ? strtolower(trim($attrs['ownerEmail'])) : '';
			$search_name = strtolower(trim($dog_name));
			$search_email = strtolower(trim($email));
			
			if ($block_name === $search_name && $block_email === $search_email) {
				if (!empty($breed)) {
					$block['attrs']['breed'] = $breed;
				}
				if (!empty($weight)) {
					$block['attrs']['weight'] = $weight;
				}
				if (!empty($age)) {
					$block['attrs']['age'] = $age;
				} elseif (empty($block['attrs']['age'])) {
					$block['attrs']['age'] = current_time('Y-m-d');
				}
				
				$updated = true;
			}
		}
		
		if (!empty($block['innerBlocks'])) {
			$block['innerBlocks'] = brighttails_update_block_recursive($block['innerBlocks'], $dog_name, $email, $breed, $weight, $age, $updated);
		}
	}
	
	return $blocks;
}
