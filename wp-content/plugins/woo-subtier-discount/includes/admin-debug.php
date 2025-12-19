<?php
// Add to main plugin file: require_once plugin_dir_path(__FILE__) . 'includes/admin-debug.php';

if (!class_exists('Woo_SubTier_Admin')) :
    class Woo_SubTier_Admin {
        public function __construct() {
            add_action('admin_menu', [$this, 'add_admin_menu']);
        }
        
        public function add_admin_menu() {
            add_submenu_page(
                'woocommerce',
                'Subscription Tier Debug',
                'Tier Debug',
                'manage_woocommerce',
                'woo-subtier-debug',
                [$this, 'debug_page']
            );
        }
        
        public function debug_page() {
            if (!current_user_can('manage_woocommerce')) {
                return;
            }
            
            if (isset($_GET['user_id'])) {
                $user_id = intval($_GET['user_id']);
                $tier = (new Woo_SubTier())->calculate_user_tier($user_id);
                echo '<pre>' . print_r($tier, true) . '</pre>';
            }
            
            echo '<h2>Test Tier Calculation</h2>';
            echo '<form method="get">';
            echo '<input type="hidden" name="page" value="woo-subtier-debug">';
            echo 'User ID: <input type="number" name="user_id" required>';
            echo ' <input type="submit" value="Check Tier">';
            echo '</form>';
        }
    }
    
    new Woo_SubTier_Admin();
endif;
