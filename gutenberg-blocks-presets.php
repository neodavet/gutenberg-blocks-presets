<?php
/**
 * Plugin Name: Gutenberg Blocks Presets
 * Plugin URI: https://neodavet.github.io/davetportfolio/
 * Description: A powerful plugin for creating and managing reusable Gutenberg block presets. Allows you to create custom block templates and ACF blocks that can be reused across your website.
 * Version: 1.0.0
 * Author: davet86
 * Author URI: https://neodavet.github.io/davetportfolio/
 * Text Domain: gutenberg-blocks-presets
 * Domain Path: /languages
 * Requires at least: 5.9
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// (Removed early logging before dependencies are loaded)

// Define plugin constants
define('GBP_VERSION', '1.0.0');
define('GBP_PLUGIN_FILE', __FILE__);
define('GBP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GBP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GBP_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('GBP_TEXT_DOMAIN', 'gutenberg-blocks-presets');

/**
 * Main Plugin Class
 */
class Gutenberg_Blocks_Presets {

    /**
     * Plugin instance
     * @var Gutenberg_Blocks_Presets
     */
    private static $instance = null;

    /**
     * Get plugin instance
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
        $this->init_hooks();
        $this->load_dependencies();

        // Ensure core components are instantiated before 'init' fires,
        // so their own 'init' callbacks (e.g., CPT registration) are hooked in time.
        if (class_exists('GBP_Post_Types')) {
            GBP_Post_Types::get_instance();
        }
        if (class_exists('GBP_Gutenberg_Blocks')) {
            GBP_Gutenberg_Blocks::get_instance();
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation and deactivation hooks
        register_activation_hook(GBP_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(GBP_PLUGIN_FILE, array($this, 'deactivate'));

        // Initialize plugin
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));

        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'admin_init'));
            // Register privacy policy content
            add_action('admin_init', array($this, 'add_privacy_policy_content'));
        }
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once GBP_PLUGIN_DIR . 'includes/class-gbp-post-types.php';
        require_once GBP_PLUGIN_DIR . 'includes/class-gbp-acf-blocks.php';
        require_once GBP_PLUGIN_DIR . 'includes/class-gbp-helper-functions.php';
        require_once GBP_PLUGIN_DIR . 'includes/class-gbp-admin.php';
        require_once GBP_PLUGIN_DIR . 'includes/class-gbp-gutenberg-blocks.php';
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Debug: Log that plugin is initializing
        GBP_Helper_Functions::log('Initializing plugin components');

        // Initialize components
        GBP_Post_Types::get_instance();
        GBP_ACF_Blocks::get_instance();
        GBP_Helper_Functions::get_instance();
        GBP_Gutenberg_Blocks::get_instance();

        if (is_admin()) {
            GBP_Admin::get_instance();
        }

        // Add admin notice for successful activation
        if (is_admin() && get_transient('gbp_activation_notice')) {
            add_action('admin_notices', array($this, 'activation_notice'));
            delete_transient('gbp_activation_notice');
        }

        // Debug: Check if post type was registered
        add_action('wp_loaded', function() {
            if (!GBP_Helper_Functions::is_debug_enabled()) return;
            $exists = post_type_exists('gbp_block_preset');
            GBP_Helper_Functions::log('Post type gbp_block_preset exists: ' . ($exists ? 'YES' : 'NO'));
            if ($exists) {
                $post_type_obj = get_post_type_object('gbp_block_preset');
                GBP_Helper_Functions::log($post_type_obj->labels);
            }
        });
    }

    /**
     * Add privacy policy content describing data handling
     */
    public function add_privacy_policy_content() {
        if (function_exists('wp_add_privacy_policy_content')) {
            $content  = '<p>' . esc_html__('Gutenberg Blocks Presets stores minimal data locally to provide its functionality.', 'gutenberg-blocks-presets') . '</p>';
            $content .= '<p>' . esc_html__('Specifically, it creates a local database table to track where a block preset is used: block ID, post ID, usage count, and last used date. No personal data is collected by this feature, and no data is transmitted to third parties.', 'gutenberg-blocks-presets') . '</p>';
            $content .= '<p>' . esc_html__('If you uninstall the plugin from the Plugins screen, all plugin data (including this usage table, options, and related metadata) will be removed.', 'gutenberg-blocks-presets') . '</p>';

            wp_add_privacy_policy_content(
                __('Gutenberg Blocks Presets', 'gutenberg-blocks-presets'),
                wp_kses_post($content)
            );
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Set default options
        if (!get_option('gbp_settings')) {
            $default_settings = array(
                'enable_acf_blocks' => false, // Disabled by default - purely native WordPress
                'enable_block_presets' => true,
                'enable_legacy_post_type' => true, // Enable for backward compatibility
                'block_folders' => array(
                    'general/acf-blocks',
                    'public/acf-blocks',
                    'login-register/acf-blocks',
                    'members/acf-blocks'
                )
            );
            update_option('gbp_settings', $default_settings);
        }

        // Create custom tables if needed
        $this->create_custom_tables();

        // Set activation notice
        set_transient('gbp_activation_notice', true, 30);
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create custom tables
     */
    private function create_custom_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Example: Create a table for block usage statistics
        $table_name = $wpdb->prefix . 'gbp_block_usage';

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            block_id bigint(20) NOT NULL,
            post_id bigint(20) NOT NULL,
            usage_count int(11) DEFAULT 0,
            last_used datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY block_id (block_id),
            KEY post_id (post_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Gutenberg Blocks Presets Settings', 'gutenberg-blocks-presets'),
            __('Blocks Presets', 'gutenberg-blocks-presets'),
            'manage_options',
            'gutenberg-blocks-presets',
            array($this, 'admin_page')
        );
    }

    /**
     * Admin page callback
     */
    public function admin_page() {
        include GBP_PLUGIN_DIR . 'admin/admin-page.php';
    }

    /**
     * Initialize admin settings
     */
    public function admin_init() {
        register_setting('gbp_settings_group', 'gbp_settings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_gbp_settings'),
            'default' => array(
                'enable_acf_blocks' => false,
                'enable_block_presets' => true,
                'enable_legacy_post_type' => true,
                'block_folders' => array(
                    'general/acf-blocks',
                    'public/acf-blocks',
                    'login-register/acf-blocks',
                    'members/acf-blocks'
                )
            )
        ));
    }

    /**
     * Sanitize plugin settings
     *
     * @param array $input Raw input data
     * @return array Sanitized settings
     */
    public function sanitize_gbp_settings($input) {
        $sanitized = array();

        // Sanitize boolean values
        $sanitized['enable_acf_blocks'] = isset($input['enable_acf_blocks']) ? (bool) $input['enable_acf_blocks'] : false;
        $sanitized['enable_block_presets'] = isset($input['enable_block_presets']) ? (bool) $input['enable_block_presets'] : true;
        $sanitized['enable_legacy_post_type'] = isset($input['enable_legacy_post_type']) ? (bool) $input['enable_legacy_post_type'] : true;

        // Sanitize block folders array
        if (isset($input['block_folders']) && is_array($input['block_folders'])) {
            $sanitized['block_folders'] = array();
            foreach ($input['block_folders'] as $folder) {
                $folder = sanitize_text_field($folder);
                // Only allow safe folder paths (letters, numbers, hyphens, underscores, forward slashes)
                if (preg_match('/^[a-zA-Z0-9\-_\/]+$/', $folder)) {
                    $sanitized['block_folders'][] = $folder;
                }
            }
        } else {
            $sanitized['block_folders'] = array();
        }

        return $sanitized;
    }

    /**
     * Display activation notice
     */
    public function activation_notice() {
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong><?php esc_html_e('Gutenberg Blocks Presets activated successfully!', 'gutenberg-blocks-presets'); ?></strong>
                <?php esc_html_e('You can now manage your block presets from the', 'gutenberg-blocks-presets'); ?>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=gbp_block_preset')); ?>"><strong><?php esc_html_e('Block Presets', 'gutenberg-blocks-presets'); ?></strong></a>
                <?php esc_html_e('menu.', 'gutenberg-blocks-presets'); ?>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=gbp_block_preset&page=gbp-settings')); ?>"><?php esc_html_e('Configure settings', 'gutenberg-blocks-presets'); ?></a>
            </p>
        </div>
        <?php
    }
}

// Initialize the plugin
function gbp_init() {
    return Gutenberg_Blocks_Presets::get_instance();
}

// Start the plugin after all plugins are loaded
add_action('plugins_loaded', 'gbp_init', 10);
