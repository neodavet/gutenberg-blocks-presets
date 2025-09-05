<?php
/**
 * Admin Interface for Gutenberg Blocks Presets
 *
 * @package Gutenberg_Blocks_Presets
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Class
 */
class GBP_Admin {

    /**
     * Instance of this class
     * @var GBP_Admin
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
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        
        // Add custom columns to post list
        add_filter('manage_gbp_block_preset_posts_columns', array($this, 'add_custom_columns'));
        add_action('manage_gbp_block_preset_posts_custom_column', array($this, 'render_custom_columns'), 10, 2);
        
        // Add usage statistics
        add_action('admin_notices', array($this, 'display_usage_notices'));
    }

    /**
     * Add admin menus
     */
    public function add_admin_menus() {
        // Settings submenu under Block Presets (main location)
        add_submenu_page(
            'edit.php?post_type=gbp_block_preset',
            __('Block Presets Settings', 'gutenberg-blocks-presets'),
            __('Settings', 'gutenberg-blocks-presets'),
            'manage_options',
            'gbp-settings',
            array($this, 'settings_page')
        );

        // Tools submenu under Block Presets
        add_submenu_page(
            'edit.php?post_type=gbp_block_preset',
            __('Block Presets Tools', 'gutenberg-blocks-presets'),
            __('Tools', 'gutenberg-blocks-presets'),
            'manage_options',
            'gbp-tools',
            array($this, 'tools_page')
        );

        // Usage Statistics submenu
        add_submenu_page(
            'edit.php?post_type=gbp_block_preset',
            __('Usage Statistics', 'gutenberg-blocks-presets'),
            __('Statistics', 'gutenberg-blocks-presets'),
            'manage_options',
            'gbp-statistics',
            array($this, 'statistics_page')
        );

        // Also add to Settings menu for discoverability
        add_options_page(
            __('Gutenberg Blocks Presets Settings', 'gutenberg-blocks-presets'),
            __('Blocks Presets', 'gutenberg-blocks-presets'),
            'manage_options',
            'gutenberg-blocks-presets',
            array($this, 'settings_page')
        );
    }

    /**
     * Initialize admin settings
     */
    public function admin_init() {
        register_setting('gbp_settings_group', 'gbp_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));

        // General Settings Section
        add_settings_section(
            'gbp_general_settings',
            __('General Settings', 'gutenberg-blocks-presets'),
            array($this, 'general_settings_callback'),
            'gutenberg-blocks-presets'
        );

        add_settings_field(
            'enable_acf_blocks',
            __('Enable ACF Blocks (Optional)', 'gutenberg-blocks-presets'),
            array($this, 'checkbox_field_callback'),
            'gutenberg-blocks-presets',
            'gbp_general_settings',
            array('field' => 'enable_acf_blocks', 'description' => __('Enable automatic ACF blocks registration from theme folders. Requires Advanced Custom Fields Pro plugin.', 'gutenberg-blocks-presets'))
        );

        add_settings_field(
            'enable_block_presets',
            __('Enable Block Presets', 'gutenberg-blocks-presets'),
            array($this, 'checkbox_field_callback'),
            'gutenberg-blocks-presets',
            'gbp_general_settings',
            array('field' => 'enable_block_presets', 'description' => __('Enable the Block Presets custom post type.', 'gutenberg-blocks-presets'))
        );

        add_settings_field(
            'enable_legacy_post_type',
            __('Enable Legacy Post Type', 'gutenberg-blocks-presets'),
            array($this, 'checkbox_field_callback'),
            'gutenberg-blocks-presets',
            'gbp_general_settings',
            array('field' => 'enable_legacy_post_type', 'description' => __('Keep the old "block" post type for backward compatibility. Disable this after migration.', 'gutenberg-blocks-presets'))
        );

        add_settings_field(
            'enable_debug_logging',
            __('Enable Test Log Mode', 'gutenberg-blocks-presets'),
            array($this, 'checkbox_field_callback'),
            'gutenberg-blocks-presets',
            'gbp_general_settings',
            array('field' => 'enable_debug_logging', 'description' => __('Write diagnostic logs to debug.log for troubleshooting. Disable in production.', 'gutenberg-blocks-presets'))
        );

        // Block Folders Section
        add_settings_section(
            'gbp_folder_settings',
            __('Block Folders', 'gutenberg-blocks-presets'),
            array($this, 'folder_settings_callback'),
            'gutenberg-blocks-presets'
        );

        add_settings_field(
            'block_folders',
            __('ACF Block Folders', 'gutenberg-blocks-presets'),
            array($this, 'textarea_field_callback'),
            'gutenberg-blocks-presets',
            'gbp_folder_settings',
            array(
                'field' => 'block_folders',
                'description' => __('Enter theme folder paths (relative to theme root) where ACF blocks are located. One per line.', 'gutenberg-blocks-presets'),
                'placeholder' => "general/acf-blocks\npublic/acf-blocks\nlogin-register/acf-blocks\nmembers/acf-blocks"
            )
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_scripts($hook) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $is_gbp_screen = ($screen && isset($screen->post_type) && $screen->post_type === 'gbp_block_preset');
        if (strpos($hook, 'gutenberg-blocks-presets') !== false ||
            strpos($hook, 'gbp-') !== false ||
            $is_gbp_screen) {

            wp_enqueue_style('gbp-admin', GBP_PLUGIN_URL . 'admin/css/admin.css', array(), GBP_VERSION);
            wp_enqueue_script('gbp-admin', GBP_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), GBP_VERSION, true);

            wp_localize_script('gbp-admin', 'gbp_admin', array(
                'ajax_url' => esc_url(admin_url('admin-ajax.php')),
                'nonce' => wp_create_nonce('gbp_admin_nonce'),
                'strings' => array(
                    'confirm_delete' => __('Are you sure you want to delete this item?', 'gutenberg-blocks-presets'),
                    'processing' => __('Processing...', 'gutenberg-blocks-presets')
                )
            ));
        }
    }

    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'gbp_block_info',
            __('Block Information', 'gutenberg-blocks-presets'),
            array($this, 'block_info_meta_box'),
            'gbp_block_preset',
            'side',
            'high'
        );

        add_meta_box(
            'gbp_block_usage',
            __('Usage Statistics', 'gutenberg-blocks-presets'),
            array($this, 'block_usage_meta_box'),
            'gbp_block_preset',
            'side',
            'default'
        );

        add_meta_box(
            'gbp_block_shortcode',
            __('Shortcode & Functions', 'gutenberg-blocks-presets'),
            array($this, 'block_shortcode_meta_box'),
            'gbp_block_preset',
            'side',
            'default'
        );
    }

    /**
     * Block information meta box
     */
    public function block_info_meta_box($post) {
        wp_nonce_field('gbp_meta_box_nonce', 'gbp_meta_box_nonce');
        
        $block_type = get_post_meta($post->ID, '_gbp_block_type', true);
        $block_description = get_post_meta($post->ID, '_gbp_block_description', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="gbp_block_type"><?php esc_html_e('Block Type', 'gutenberg-blocks-presets'); ?></label></th>
                <td>
                    <select name="gbp_block_type" id="gbp_block_type" class="widefat">
                        <option value="content" <?php selected($block_type, 'content'); ?>><?php esc_html_e('Content Block', 'gutenberg-blocks-presets'); ?></option>
                        <option value="template" <?php selected($block_type, 'template'); ?>><?php esc_html_e('Template Block', 'gutenberg-blocks-presets'); ?></option>
                        <option value="component" <?php selected($block_type, 'component'); ?>><?php esc_html_e('Component Block', 'gutenberg-blocks-presets'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="gbp_block_description"><?php esc_html_e('Description', 'gutenberg-blocks-presets'); ?></label></th>
                <td>
                    <textarea name="gbp_block_description" id="gbp_block_description" class="widefat" rows="3"><?php echo esc_textarea($block_description); ?></textarea>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Block usage meta box
     */
    public function block_usage_meta_box($post) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'gbp_block_usage';
        $usage_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, usage_count, last_used FROM $table_name WHERE block_id = %d ORDER BY usage_count DESC LIMIT 10",
            $post->ID
        ));

        if ($usage_stats) {
            echo '<h4>' . esc_html(__('Most Used On:', 'gutenberg-blocks-presets')) . '</h4>';
            echo '<ul>';
            foreach ($usage_stats as $stat) {
                $used_post = get_post($stat->post_id);
                if ($used_post) {
                    echo '<li>';
                    echo '<a href="' . esc_url(get_edit_post_link($stat->post_id)) . '">' . esc_html($used_post->post_title) . '</a>';
                    echo ' (' . esc_html($stat->usage_count) . ' times)';
                    /* translators: %s: Formatted date when the block preset was last used */
                    echo '<br><small>' . esc_html(sprintf(__('Last used: %s', 'gutenberg-blocks-presets'), date_i18n(get_option('date_format'), strtotime($stat->last_used)))) . '</small>';
                    echo '</li>';
                }
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html(__('This block preset has not been used yet.', 'gutenberg-blocks-presets')) . '</p>';
        }
    }

    /**
     * Block shortcode meta box
     */
    public function block_shortcode_meta_box($post) {
        ?>
        <h4><?php esc_html_e('Shortcode', 'gutenberg-blocks-presets'); ?></h4>
        <input type="text" readonly value="[gbp_block id=&quot;<?php echo esc_attr($post->ID); ?>&quot;]" class="widefat" onclick="this.select();">
        
        <h4><?php esc_html_e('PHP Function', 'gutenberg-blocks-presets'); ?></h4>
        <input type="text" readonly value="gbp_render_block_preset(<?php echo esc_html($post->ID); ?>);" class="widefat" onclick="this.select();">
        
        <h4><?php esc_html_e('Legacy Function', 'gutenberg-blocks-presets'); ?></h4>
        <input type="text" readonly value="do_cpt_block(<?php echo esc_html($post->ID); ?>);" class="widefat" onclick="this.select();">
        
        <p><small><?php esc_html_e('Click on the code to select it for copying.', 'gutenberg-blocks-presets'); ?></small></p>
        <?php
    }

    /**
     * Save meta boxes
     */
    public function save_meta_boxes($post_id) {
        if (!isset($_POST['gbp_meta_box_nonce']) || !wp_verify_nonce($_POST['gbp_meta_box_nonce'], 'gbp_meta_box_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['gbp_block_type'])) {
            update_post_meta($post_id, '_gbp_block_type', sanitize_text_field($_POST['gbp_block_type']));
        }

        if (isset($_POST['gbp_block_description'])) {
            update_post_meta($post_id, '_gbp_block_description', sanitize_textarea_field($_POST['gbp_block_description']));
        }
    }

    /**
     * Add custom columns to post list
     */
    public function add_custom_columns($columns) {
        $new_columns = array();
        foreach ($columns as $key => $title) {
            $new_columns[$key] = $title;
            if ($key === 'title') {
                $new_columns['gbp_usage'] = __('Usage Count', 'gutenberg-blocks-presets');
                $new_columns['gbp_shortcode'] = __('Shortcode', 'gutenberg-blocks-presets');
            }
        }
        return $new_columns;
    }

    /**
     * Render custom columns
     */
    public function render_custom_columns($column, $post_id) {
        switch ($column) {
            case 'gbp_usage':
                global $wpdb;
                $table_name = $wpdb->prefix . 'gbp_block_usage';
                $total_usage = $wpdb->get_var($wpdb->prepare(
                    "SELECT SUM(usage_count) FROM $table_name WHERE block_id = %d",
                    $post_id
                ));
                echo esc_html($total_usage ? $total_usage : '0');
                break;
                
            case 'gbp_shortcode':
                echo '<code>[gbp_block id="' . esc_attr($post_id) . '"]</code>';
                break;
        }
    }

    /**
     * Settings page
     */
    public function settings_page() {
        include GBP_PLUGIN_DIR . 'admin/settings-page.php';
    }

    /**
     * Tools page
     */
    public function tools_page() {
        include GBP_PLUGIN_DIR . 'admin/tools-page.php';
    }

    /**
     * Statistics page
     */
    public function statistics_page() {
        include GBP_PLUGIN_DIR . 'admin/statistics-page.php';
    }

    /**
     * General settings section callback
     */
    public function general_settings_callback() {
        echo '<p>' . esc_html(__('Configure general settings for the Gutenberg Blocks Presets plugin. The plugin works with native WordPress functionality - ACF integration is optional.', 'gutenberg-blocks-presets')) . '</p>';
    }

    /**
     * Folder settings section callback
     */
    public function folder_settings_callback() {
        echo '<p>' . esc_html(__('Configure which theme folders should be scanned for ACF blocks.', 'gutenberg-blocks-presets')) . '</p>';
    }

    /**
     * Checkbox field callback
     */
    public function checkbox_field_callback($args) {
        $settings = get_option('gbp_settings', array());
        $value = isset($settings[$args['field']]) ? $settings[$args['field']] : false;
        
        echo '<label>';
        echo '<input type="checkbox" name="gbp_settings[' . esc_attr($args['field']) . ']" value="1" ' . checked($value, true, false) . '>';
        echo ' ' . esc_html($args['description']);
        echo '</label>';
    }

    /**
     * Textarea field callback
     */
    public function textarea_field_callback($args) {
        $settings = get_option('gbp_settings', array());
        $value = isset($settings[$args['field']]) ? $settings[$args['field']] : '';
        
        if (is_array($value)) {
            $value = implode("\n", $value);
        }
        
        echo '<textarea name="gbp_settings[' . esc_attr($args['field']) . ']" rows="6" class="large-text" placeholder="' . esc_attr($args['placeholder']) . '">';
        echo esc_textarea($value);
        echo '</textarea>';
        
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['enable_acf_blocks'])) {
            $sanitized['enable_acf_blocks'] = (bool) $input['enable_acf_blocks'];
        }
        
        if (isset($input['enable_block_presets'])) {
            $sanitized['enable_block_presets'] = (bool) $input['enable_block_presets'];
        }
        
        if (isset($input['enable_legacy_post_type'])) {
            $sanitized['enable_legacy_post_type'] = (bool) $input['enable_legacy_post_type'];
        }

        if (isset($input['enable_debug_logging'])) {
            $sanitized['enable_debug_logging'] = (bool) $input['enable_debug_logging'];
        }
        
        if (isset($input['block_folders'])) {
            $folders = explode("\n", $input['block_folders']);
            $folders = array_map('trim', $folders);
            $folders = array_filter($folders);
            $sanitized['block_folders'] = $folders;
        }
        
        return $sanitized;
    }

    /**
     * Display usage notices
     */
    public function display_usage_notices() {
        $screen = get_current_screen();
        if ($screen->post_type === 'gbp_block_preset') {
            // Could add notices about block usage, updates, etc.
        }
    }
}
