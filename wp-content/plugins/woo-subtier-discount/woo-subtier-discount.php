<?php
/**
 * Plugin Name: WooCommerce Subscription Tier Discount
 * Description: Dynamic discounts based on customer purchase history
 * Version: 1.0.2
 * Requires at least: 5.0
 * Tested up to: 6.6
 * WC requires at least: 6.0
 * WC tested up to: 9.0
 * Requires PHP: 7.2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

if (!class_exists('Woo_SubTier')) :
class Woo_SubTier {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
    }
    
    public function init() {
        // Only run on frontend cart/checkout
        if (!is_admin() && (is_woocommerce() || is_cart() || is_checkout())) {
            add_action('woocommerce_cart_calculate_fees', [$this, 'apply_discount'], 20, 2);
        }
    }
    
    public function apply_discount($cart) {
        if (!is_user_logged_in() || !WC()->cart) return;
        
        $user_id = get_current_user_id();
        $cache_key = 'subtier_' . $user_id;
        $tier = get_transient($cache_key);
        
        if (false === $tier) {
            $tier = $this->get_tier($user_id);
            set_transient($cache_key, $tier, HOUR_IN_SECONDS);
        }
        
        if ($tier['rate'] > 0) {
            $amount = $cart->get_subtotal() * $tier['rate'];
            $cart->add_fee($tier['label'], -$amount);
        }
    }
    
    private function get_tier($user_id) {
        global $wpdb;
        
        // Simple total spent check (most compatible)
        $total = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(pm.meta_value), 0)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'completed')
            AND p.post_author = %d
            AND pm.meta_key = '_order_total'
        ", $user_id));
        
        $total = (float) $total;
        
        if ($total >= 1000) {
            return ['rate' => 0.25, 'label' => 'Gold Loyalty (25%)'];
        } elseif ($total >= 500) {
            return ['rate' => 0.15, 'label' => 'Silver Loyalty (15%)'];
        }
        
        return ['rate' => 0];
    }
}

Woo_SubTier::get_instance();
endif;
?>
