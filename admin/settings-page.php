<?php
/**
 * Admin Settings Page Template
 *
 * @package Gutenberg_Blocks_Presets
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors(); ?>
    
    <div class="gbp-admin-container">
        <div class="gbp-admin-main">
            <form method="post" action="options.php">
                <?php
                settings_fields('gbp_settings_group');
                do_settings_sections('gutenberg-blocks-presets');
                submit_button();
                ?>
            </form>
        </div>
        
        <div class="gbp-admin-sidebar">
            <div class="gbp-widget">
                <h3><?php _e('Plugin Information', GBP_TEXT_DOMAIN); ?></h3>
                <ul>
                    <li><strong><?php _e('Version:', GBP_TEXT_DOMAIN); ?></strong> <?php echo GBP_VERSION; ?></li>
                    <li><strong><?php _e('Post Type:', GBP_TEXT_DOMAIN); ?></strong> gbp_block_preset</li>
                    <li><strong><?php _e('Shortcode:', GBP_TEXT_DOMAIN); ?></strong> [gbp_block id="123"]</li>
                </ul>
            </div>
            
            <div class="gbp-widget">
                <h3><?php _e('Quick Actions', GBP_TEXT_DOMAIN); ?></h3>
                <p>
                    <a href="<?php echo admin_url('post-new.php?post_type=gbp_block_preset'); ?>" class="button button-primary">
                        <?php _e('Create New Block Preset', GBP_TEXT_DOMAIN); ?>
                    </a>
                </p>
                <p>
                    <a href="<?php echo admin_url('edit.php?post_type=gbp_block_preset'); ?>" class="button">
                        <?php _e('Manage Block Presets', GBP_TEXT_DOMAIN); ?>
                    </a>
                </p>
                <p>
                    <a href="<?php echo admin_url('edit.php?post_type=gbp_block_preset&page=gbp-statistics'); ?>" class="button">
                        <?php _e('View Statistics', GBP_TEXT_DOMAIN); ?>
                    </a>
                </p>
            </div>
            
            <div class="gbp-widget">
                <h3><?php _e('Documentation', GBP_TEXT_DOMAIN); ?></h3>
                <p><?php _e('Use these functions in your theme:', GBP_TEXT_DOMAIN); ?></p>
                <code>gbp_render_block_preset(123);</code><br>
                <code>do_cpt_block(123); // Legacy</code><br><br>
                
                <p><?php _e('Use shortcodes in content:', GBP_TEXT_DOMAIN); ?></p>
                <code>[gbp_block id="123"]</code><br>
                <code>[block_preset id="123"]</code>
            </div>
        </div>
    </div>
</div>
