<?php
/**
 * Legacy Admin Page Template (fallback)
 * 
 * This file is used as a fallback for the old settings page structure.
 * The main settings are now in settings-page.php
 *
 * @package Gutenberg_Blocks_Presets
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Redirect to the proper settings page
wp_redirect(admin_url('edit.php?post_type=gbp_block_preset&page=gbp-settings'));
exit;
