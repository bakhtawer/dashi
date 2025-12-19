jQuery(document).ready(function($) {
    $('#pconf-configurator').on('change input', '.pconf-field input, .pconf-field select', function() {
        updateConditions();
        calculatePrice();
    });
    
    function updateConditions() {
        $('.pconf-field').each(function() {
            const conditions = $(this).data('conditions');
            if (!conditions) return;
            
            let show = true;
            for (let [field, value] of Object.entries(conditions)) {
                const fieldVal = $(`input[name="pconf[${field}]"]:checked, select[name="pconf[${field}]"]`).val();
                if (fieldVal !== value) {
                    show = false;
                    break;
                }
            }
            $(this).toggle(show);
        });
    }
    
    function calculatePrice() {
        const productId = $('#pconf-configurator').data('product');
        const config = {};
        $('input[name^="pconf"], select[name^="pconf"]').each(function() {
            if (this.type === 'checkbox' && !this.checked) return;
            config[this.name.replace('pconf[', '').replace(']', '')] = $(this).val();
        });
        
        $.post(pconf_ajax.url, {
            action: 'pconf_calculate_price',
            product_id: productId,
            config: config,
            base_price: 299.99 // Default base
        }, function(res) {
            $('#pconf-total').text(res.price.toFixed(2));
        });
    }
});
