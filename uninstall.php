<?php
/**
 * Uninstall script for Gutenberg Blocks Presets
 *
 * This file is executed when the plugin is deleted via WordPress admin.
 * It cleans up all plugin data including posts, meta, options, and database tables.
 *
 * @package Gutenberg_Blocks_Presets
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Remove all plugin data
 */
function gbp_uninstall_cleanup() {
    global $wpdb;

    // Delete all block preset posts
    $posts = get_posts(array(
        'post_type' => 'gbp_block_preset',
        'numberposts' => -1,
        'post_status' => 'any'
    ));

    foreach ($posts as $post) {
        // Delete post meta
        $wpdb->delete($wpdb->postmeta, array('post_id' => $post->ID));
        
        // Delete the post
        wp_delete_post($post->ID, true);
    }

    // Delete custom taxonomies terms
    $taxonomies = array('gbp_block_category', 'gbp_block_tag');
    foreach ($taxonomies as $taxonomy) {
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false
        ));
        
        foreach ($terms as $term) {
            wp_delete_term($term->term_id, $taxonomy);
        }
    }

    // Delete plugin options
    delete_option('gbp_settings');
    delete_option('gbp_version');
    delete_option('gbp_activation_date');

    // Delete transients
    delete_transient('gbp_block_cache');
    delete_transient('gbp_usage_stats');

    // Delete custom database tables
    $table_name = $wpdb->prefix . 'gbp_block_usage';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");

    // Clean up any remaining meta keys
    $wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '_gbp_%'");
    $wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key LIKE 'gbp_%'");

    // Clean up user meta (if any)
    $wpdb->query("DELETE FROM $wpdb->usermeta WHERE meta_key LIKE '_gbp_%'");
    $wpdb->query("DELETE FROM $wpdb->usermeta WHERE meta_key LIKE 'gbp_%'");

    // Flush rewrite rules
    flush_rewrite_rules();

    // Clear any cached data
    wp_cache_flush();
}

// Execute cleanup
gbp_uninstall_cleanup();

/**
 * Log uninstall event (optional - for debugging)
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Gutenberg Blocks Presets plugin has been uninstalled and all data removed.');
}
