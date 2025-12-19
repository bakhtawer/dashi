<?php
/**
 * Plugin Name: Dynamic Product Configurator
 * Description: Conditional fields + formula pricing for complex products
 * Version: 1.0.0
 * Requires at least: 5.0
 * WC requires at least: 6.0
 * Authur: Bakhtawer Jabeen
 */

if (!defined('ABSPATH')) exit;

class ProductConfigurator {
    private static $instance = null;
    
    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('woocommerce_before_single_variation', [$this, 'render_configurator']);
        add_action('woocommerce_single_variation', [$this, 'handle_pricing']);
        add_action('wp_ajax_pconf_calculate_price', [$this, 'ajax_calculate_price']);
        add_action('wp_ajax_nopriv_pconf_calculate_price', [$this, 'ajax_calculate_price']);
    }
    
    public function enqueue_assets() {
        if (is_product()) {
            wp_enqueue_script('pconf-js', plugin_dir_url(__FILE__) . 'assets/config.js');
            wp_enqueue_style('pconf-css', plugin_dir_url(__FILE__) . 'assets/config.css', [], '1.0');
            wp_localize_script('pconf-js', 'pconf_ajax', ['url' => admin_url('admin-ajax.php')]);
        }
    }
    
    public function render_configurator() {
        global $product;
        if (!$product->is_type('variable')) return;
        
        echo '<div id="pconf-configurator" data-product="' . $product->get_id() . '">';
        $this->render_fields($product);
        echo '</div>';
    }
    
    private function render_fields($product) {
        $fields = [
            'metal' => [
                'type' => 'radio',
                'label' => 'Metal Type',
                'options' => [
                    'gold' => 'Gold (+$200)',
                    'silver' => 'Silver (+$50)',
                    'platinum' => 'Platinum (+$500)'
                ]
            ],
            'gold_karat|metal=gold' => [
                'type' => 'select',
                'label' => 'Gold Karat',
                'options' => ['14k' => '$80/gram', '18k' => '$100/gram', '24k' => '$130/gram']
            ],
            'weight' => [
                'type' => 'number',
                'label' => 'Weight (grams)',
                'min' => 5, 'max' => 50, 'step' => 0.1
            ],
            'diamond|metal!=silver' => [
                'type' => 'checkbox',
                'label' => 'Add Diamond (+$1200/carat)'
            ],
            'diamond_carat|diamond=1' => [
                'type' => 'number',
                'label' => 'Diamond Carat',
                'min' => 0.1, 'max' => 2, 'step' => 0.01
            ]
        ];
        
        foreach ($fields as $field_id => $config) {
            $conditions = $this->parse_conditions($field_id);
            $clean_id = preg_replace('/\|.*$/', '', $field_id);
            ?>
            <div class="pconf-field" data-conditions='<?= json_encode($conditions) ?>'>
                <label><?= esc_html($config['label']) ?></label>
                <?php $this->render_field($clean_id, $config); ?>
            </div>
            <?php
        }
    }
    
    private function render_field($id, $config) {
        switch ($config['type']) {
            case 'radio':
                foreach ($config['options'] as $val => $label) {
                    echo "<label><input type='radio' name='pconf[{$id}]' value='{$val}' data-price='{$this->get_price_offset($label)}'> {$label}</label>";
                }
                break;
            case 'select':
                echo "<select name='pconf[{$id}]'>";
                foreach ($config['options'] as $val => $label) {
                    echo "<option value='{$val}' data-price='{$this->get_price_offset($label)}'>{$label}</option>";
                }
                echo '</select>';
                break;
            case 'number':
                echo "<input type='number' name='pconf[{$id}]' min='{$config['min']}' max='{$config['max']}' step='{$config['step']}' value='{$config['min']}'>";
                break;
            case 'checkbox':
                echo "<input type='checkbox' name='pconf[{$id}]' value='1'>";
                break;
        }
    }
    
    public function handle_pricing($variation) {
        echo '<div id="pconf-price-display">$<span id="pconf-total">0.00</span></div>';
    }
    
    public function ajax_calculate_price() {
        $product_id = $_POST['product_id'];
        $config = $_POST['config'];
        $base_price = floatval($_POST['base_price']);
        
        $price = $this->calculate_formula_price($product_id, $config, $base_price);
        wp_send_json(['price' => $price]);
    }
    
    private function calculate_formula_price($product_id, $config, $base) {
        $formula = $base;
        
        // Metal pricing
        if (!empty($config['metal'])) {
            $metal_prices = ['gold' => 200, 'silver' => 50, 'platinum' => 500];
            $formula += $metal_prices[$config['metal']] ?? 0;
            
            if ($config['metal'] === 'gold' && !empty($config['gold_karat'])) {
                $karat_prices = ['14k' => 80, '18k' => 100, '24k' => 130];
                $formula += ($karat_prices[$config['gold_karat']] ?? 80) * ($config['weight'] ?? 5);
            }
        }
        
        // Diamond pricing
        if (!empty($config['diamond']) && $config['diamond'] == 1 && !empty($config['diamond_carat'])) {
            $formula += 1200 * floatval($config['diamond_carat']);
        }
        
        // Weight multiplier (if no gold karat)
        if (!empty($config['weight']) && empty($config['gold_karat'])) {
            $formula += floatval($config['weight']) * 10; // Base $10/gram
        }
        
        return round($formula, 2);
    }
    
    private function parse_conditions($field_id) {
        if (strpos($field_id, '|') === false) return [];
        $parts = explode('|', $field_id);
        $cond = explode('=', $parts[1]);
        return [$cond[0] => $cond[1]];
    }
    
    private function get_price_offset($label) {
        if (preg_match('/\$([\d.]+)/', $label, $matches)) {
            return floatval($matches[1]);
        }
        return 0;
    }
}

ProductConfigurator::init();
