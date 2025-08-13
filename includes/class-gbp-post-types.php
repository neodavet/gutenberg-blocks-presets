<?php
/**
 * Handle Custom Post Types for Gutenberg Blocks Presets
 *
 * @package Gutenberg_Blocks_Presets
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom Post Types Class
 */
class GBP_Post_Types {

    /**
     * Instance of this class
     * @var GBP_Post_Types
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'register_post_types'), 10);
        add_action('admin_menu', array($this, 'adjust_menu_labels'), 999);
    }

    /**
     * Register custom post types
     */
    public function register_post_types() {
        // Debug logging
        GBP_Helper_Functions::log('register_post_types() called');

        $settings = get_option('gbp_settings', array());
        
        // Always register the new post type
        $this->register_block_preset_post_type();
        
        // Register legacy post type for backward compatibility if enabled
        $enable_legacy = isset($settings['enable_legacy_post_type']) ? $settings['enable_legacy_post_type'] : true;
        if ($enable_legacy) {
            $this->register_legacy_block_post_type();
        }

        // Debug: Check if registration was successful
        GBP_Helper_Functions::log('Post types registered. gbp_block_preset exists: ' . (post_type_exists('gbp_block_preset') ? 'YES' : 'NO'));
    }

    /**
     * Register Block Preset custom post type (Native WordPress)
     */
    public function register_block_preset_post_type() {
        // Debug: Check if post type already exists
        $already_exists = post_type_exists('gbp_block_preset');
        GBP_Helper_Functions::log('Post type already exists before registration: ' . ($already_exists ? 'YES' : 'NO'));

        $labels = array(
            'name'                     => __('Block Presets', GBP_TEXT_DOMAIN),
            'singular_name'            => __('Block Preset', GBP_TEXT_DOMAIN),
            'menu_name'                => __('Block Presets', GBP_TEXT_DOMAIN),
            'name_admin_bar'           => __('Block Preset', GBP_TEXT_DOMAIN),
            'add_new'                  => __('Add New', GBP_TEXT_DOMAIN),
            'add_new_item'             => __('Add New Block Preset', GBP_TEXT_DOMAIN),
            'new_item'                 => __('New Block Preset', GBP_TEXT_DOMAIN),
            'edit_item'                => __('Edit Block Preset', GBP_TEXT_DOMAIN),
            'view_item'                => __('View Block Preset', GBP_TEXT_DOMAIN),
            'view_items'               => __('View Block Presets', GBP_TEXT_DOMAIN),
            'all_items'                => __('All Block Presets', GBP_TEXT_DOMAIN),
            'search_items'             => __('Search Block Presets', GBP_TEXT_DOMAIN),
            'not_found'                => __('No block presets found.', GBP_TEXT_DOMAIN),
            'not_found_in_trash'       => __('No block presets found in Trash.', GBP_TEXT_DOMAIN),
        );

        // Minimal args to test basic registration
        $args = array(
            'labels'                => $labels,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'show_in_rest'          => true,
            'menu_icon'             => 'dashicons-block-default',
            'supports'              => array('title', 'editor')
        );

        // Debug: Log before registration
        GBP_Helper_Functions::log('About to register gbp_block_preset post type');
        GBP_Helper_Functions::log($args);

        // Remove debug-only temporary CPT registration used during diagnostics

        // Register the canonical post type slug used throughout the plugin
        $post_type_name = 'gbp_block_preset';
        $result = register_post_type($post_type_name, $args);

        // Debug: Log registration result
        if (is_wp_error($result)) {
            GBP_Helper_Functions::log('Post type registration failed: ' . $result->get_error_message());
        } else {
            GBP_Helper_Functions::log('Post type registered successfully');
            GBP_Helper_Functions::log($result);
        }
        
        // Additional check
        $exists_immediately = post_type_exists($post_type_name);
        GBP_Helper_Functions::log('Post type ' . $post_type_name . ' exists immediately after registration: ' . ($exists_immediately ? 'YES' : 'NO'));
        
        // Check if there are any global $wp_post_types conflicts
        global $wp_post_types;
        if (isset($wp_post_types[$post_type_name])) {
            GBP_Helper_Functions::log('Post type found in global $wp_post_types');
        } else {
            GBP_Helper_Functions::log('Post type NOT found in global $wp_post_types');
            GBP_Helper_Functions::log('Available post types: ' . implode(', ', array_keys($wp_post_types)));
        }

        // Register taxonomies after post type
        $this->register_block_preset_taxonomies();
    }

    /**
     * Register taxonomies for block presets
     */
    public function register_block_preset_taxonomies() {
        // Block Preset Categories
        $category_labels = array(
            'name'                       => _x('Block Categories', 'Taxonomy General Name', GBP_TEXT_DOMAIN),
            'singular_name'              => _x('Block Category', 'Taxonomy Singular Name', GBP_TEXT_DOMAIN),
            'menu_name'                  => __('Categories', GBP_TEXT_DOMAIN),
            'all_items'                  => __('All Categories', GBP_TEXT_DOMAIN),
            'parent_item'                => __('Parent Category', GBP_TEXT_DOMAIN),
            'parent_item_colon'          => __('Parent Category:', GBP_TEXT_DOMAIN),
            'new_item_name'              => __('New Category Name', GBP_TEXT_DOMAIN),
            'add_new_item'               => __('Add New Category', GBP_TEXT_DOMAIN),
            'edit_item'                  => __('Edit Category', GBP_TEXT_DOMAIN),
            'update_item'                => __('Update Category', GBP_TEXT_DOMAIN),
            'view_item'                  => __('View Category', GBP_TEXT_DOMAIN),
            'separate_items_with_commas' => __('Separate categories with commas', GBP_TEXT_DOMAIN),
            'add_or_remove_items'        => __('Add or remove categories', GBP_TEXT_DOMAIN),
            'choose_from_most_used'      => __('Choose from the most used', GBP_TEXT_DOMAIN),
            'popular_items'              => __('Popular Categories', GBP_TEXT_DOMAIN),
            'search_items'               => __('Search Categories', GBP_TEXT_DOMAIN),
            'not_found'                  => __('Not Found', GBP_TEXT_DOMAIN),
            'no_terms'                   => __('No categories', GBP_TEXT_DOMAIN),
            'items_list'                 => __('Categories list', GBP_TEXT_DOMAIN),
            'items_list_navigation'      => __('Categories list navigation', GBP_TEXT_DOMAIN),
        );

        $category_args = array(
            'labels'            => $category_labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud'     => false,
            'show_in_rest'      => true,
        );

        register_taxonomy('gbp_block_category', array('gbp_block_preset'), $category_args);

        // Block Preset Tags
        $tag_labels = array(
            'name'                       => _x('Block Tags', 'Taxonomy General Name', GBP_TEXT_DOMAIN),
            'singular_name'              => _x('Block Tag', 'Taxonomy Singular Name', GBP_TEXT_DOMAIN),
            'menu_name'                  => __('Tags', GBP_TEXT_DOMAIN),
            'all_items'                  => __('All Tags', GBP_TEXT_DOMAIN),
            'new_item_name'              => __('New Tag Name', GBP_TEXT_DOMAIN),
            'add_new_item'               => __('Add New Tag', GBP_TEXT_DOMAIN),
            'edit_item'                  => __('Edit Tag', GBP_TEXT_DOMAIN),
            'update_item'                => __('Update Tag', GBP_TEXT_DOMAIN),
            'view_item'                  => __('View Tag', GBP_TEXT_DOMAIN),
            'separate_items_with_commas' => __('Separate tags with commas', GBP_TEXT_DOMAIN),
            'add_or_remove_items'        => __('Add or remove tags', GBP_TEXT_DOMAIN),
            'choose_from_most_used'      => __('Choose from the most used', GBP_TEXT_DOMAIN),
            'popular_items'              => __('Popular Tags', GBP_TEXT_DOMAIN),
            'search_items'               => __('Search Tags', GBP_TEXT_DOMAIN),
            'not_found'                  => __('Not Found', GBP_TEXT_DOMAIN),
            'no_terms'                   => __('No tags', GBP_TEXT_DOMAIN),
            'items_list'                 => __('Tags list', GBP_TEXT_DOMAIN),
            'items_list_navigation'      => __('Tags list navigation', GBP_TEXT_DOMAIN),
        );

        $tag_args = array(
            'labels'            => $tag_labels,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud'     => true,
            'show_in_rest'      => true,
        );

        register_taxonomy('gbp_block_tag', array('gbp_block_preset'), $tag_args);
    }

    /**
     * Register legacy 'block' post type for backward compatibility
     * This matches the original theme implementation
     */
    public function register_legacy_block_post_type() {
        $labels = array(
            'name' => __('Block Presets (Legacy)', GBP_TEXT_DOMAIN),
            'singular_name' => __('Block Preset', GBP_TEXT_DOMAIN),
            'menu_name' => __('Legacy Blocks', GBP_TEXT_DOMAIN),
            'all_items' => __('All Legacy Blocks', GBP_TEXT_DOMAIN),
            'edit_item' => __('Edit Block Preset', GBP_TEXT_DOMAIN),
            'view_item' => __('View Block Preset', GBP_TEXT_DOMAIN),
            'add_new_item' => __('Add New Block', GBP_TEXT_DOMAIN),
            'add_new' => __('Add New', GBP_TEXT_DOMAIN),
            'new_item' => __('New Block Preset', GBP_TEXT_DOMAIN),
            'search_items' => __('Search Block Presets', GBP_TEXT_DOMAIN),
            'not_found' => __('No block presets found.', GBP_TEXT_DOMAIN),
            'not_found_in_trash' => __('No block presets found in Trash.', GBP_TEXT_DOMAIN),
        );

        $args = array(
            'labels' => $labels,
            'description' => __('Legacy block presets (for backward compatibility only).', GBP_TEXT_DOMAIN),
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => false, // Hide from main menu since we have the new post type
            'show_in_admin_bar' => false,
            'show_in_rest' => true,
            'capability_type' => 'post',
            'supports' => array(
                'title',
                'editor',
                'thumbnail',
                'custom-fields',
            ),
            'exclude_from_search' => true,
            'has_archive' => false,
            'rewrite' => false,
            'query_var' => false,
            'delete_with_user' => false,
            'can_export' => true,
        );

        register_post_type('block', $args);
    }

    /**
     * Adjust menu labels and ensure proper menu visibility
     */
    public function adjust_menu_labels() {
        global $menu, $submenu;
        
        // Make sure the Block Presets menu is visible and properly labeled
        if (post_type_exists('gbp_block_preset')) {
            // Find and adjust the menu item
            foreach ($menu as $key => $item) {
                if (isset($item[2]) && $item[2] === 'edit.php?post_type=gbp_block_preset') {
                    $menu[$key][0] = __('Block Presets', GBP_TEXT_DOMAIN);
                    break;
                }
            }
            
            // Ensure submenu items are properly labeled
            if (isset($submenu['edit.php?post_type=gbp_block_preset'])) {
                foreach ($submenu['edit.php?post_type=gbp_block_preset'] as $key => $item) {
                    if ($item[2] === 'edit.php?post_type=gbp_block_preset') {
                        $submenu['edit.php?post_type=gbp_block_preset'][$key][0] = __('All Block Presets', GBP_TEXT_DOMAIN);
                    } elseif ($item[2] === 'post-new.php?post_type=gbp_block_preset') {
                        $submenu['edit.php?post_type=gbp_block_preset'][$key][0] = __('Add New Preset', GBP_TEXT_DOMAIN);
                    }
                }
            }
        }
    }
}
