<?php
/**
 * Plugin Name: WooCommerce Guest Min Order
 * Description: Enforce a minimum order amount for guest users in WooCommerce.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
add_action( 'woocommerce_check_cart_items', 'wc_guest_minimum_order');
function wc_guest_minimum_order()
{
if(is_user_logged_in()){
    return;

}
$minimum=5000;
$cart_total=WC()-cart-subtotal;
if($cart_total<minimum)
{
    wc_add_notice(sprintf("minimun order number for any guest is %s",wc_price($minimum)),'error');

}
}