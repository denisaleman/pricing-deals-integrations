<?php
/**
 * This file consists of integration rules for the plugin called
 * 'Pricing Deals for WooCommerce'.
 *
 * 2 global variables are used:
 *
 * $loop_product_discount_info   - stores prepared info about
 *                                 the product discounts
 *                                 within shop and product categories;
 * $single_product_discount_info - stores prepared info about
 *                                 the product discounts
 *                                 within single product template.
 *
 * In order to restrict the access to them from the outside they
 * are used within 2 global funtions:
 *
 * do_vtprd_product_page_integrations() - integrate plugin within single
 *                                        product template.
 * do_vtprd_loop_integrations()         - integrate plugin within shop
 *                                        and product categories.
 *
 * The functions, which are used accordingly to define
 * the scope for previously described variables, must be called, and may be
 * called outside of this file. They can work independently on each other.
 */

/**
 * Integration rules work within the loop(shop, catalog and categories).
 *
 */
function do_vtprd_loop_integrations()
{
    /**
     * Store prepared info about the loop product discount rules
     */
    global $loop_product_discount_info;

    function vtprd_prepare_info_before_the_loop()
    {

        if (!is_shop() && !is_product_category()) {
            return;
        }

        global $wp_query, $vtprd_rules_set, $loop_product_discount_info, $vtprd_cart;

        $posts = $wp_query->posts;

        $products = [];
        foreach ($posts as $k => $post) {
            $product_id = $post->ID;
            $products[] = wc_get_product($product_id);
        }

        $loop_product_discount_info = vtprd_prepare_info_for_products($products);

        // if product has variations
        foreach ($products as $id => $product) {
            # code...
            if( $product->get_type() === 'variable' && 
                !empty($variations_ids = $product->get_children()))
            {
                $variations = [];
                foreach ($variations_ids as $variations_id) {
                    $variations[] = wc_get_product($variations_id);
                }
                
                $product_discount_info = &$loop_product_discount_info[$product->get_id()];
                $product_discount_info['variations'] = vtprd_prepare_info_for_products($variations, $product->get_id());
                
                uasort($product_discount_info['variations'], function($item1, $item2){
                    return $item1['sale_price'] - $item2['sale_price'];
                });
            }
        }
    }
    add_action('woocommerce_before_shop_loop', 'vtprd_prepare_info_before_the_loop', 10);

    ///////////////////////////////
    function vtprd_loop_get_variable_price_html($price, $product) 
    {   
        if(!is_shop() && !is_product_category())
            return $price;
        if($product->get_type() !== 'variable')
            return $price;


        global $loop_product_discount_info;

        $variations = $loop_product_discount_info[$product->get_id()]['variations'];

        $min_price = current($variations)['sale_price'];
        $max_price = end($variations)['sale_price'];

        if ( '' === $product->get_price() ) {
            $price = apply_filters( 'woocommerce_empty_price_html', '', $product );
        } elseif ( $product->is_on_sale() ) {
            //wc_format_price_range( $from, $to );
            $price =  wc_price( wc_get_price_to_display( $product, array( 'price' => $min_price ) ) ) . ' &ndash; ' . wc_price( wc_get_price_to_display( $product, array( 'price' => $max_price ) ) ) . $product->get_price_suffix();
        } else {
            $price = wc_price( wc_get_price_to_display( $product ) ) . $product->get_price_suffix();
        }

        return $price;
    }
    add_filter( 'woocommerce_get_price_html', 'vtprd_loop_get_variable_price_html', 11, 2 );

    /**
     * Replace html price within the loop
     *
     */
    function vtprd_loop_get_price_html($price, $product)
    {
        if (!is_shop() && !is_product_category()) {
            return $price;
        }

        global $product, $loop_product_discount_info;

        $product_id = $product->get_id();

        if ('' === $product->get_price()) {
            $price = apply_filters('woocommerce_empty_price_html', '', $product);
        } else if ($product->is_on_sale()) {

            if(!$loop_product_discount_info[$product_id]['sale_price']) {
                $price = wc_price(wc_get_price_to_display($product, array('price' => $product->get_regular_price()))) . $product->get_price_suffix();
            } else {
                $price = wc_format_sale_price(wc_get_price_to_display($product, array('price' => $product->get_regular_price())), wc_get_price_to_display($product, array('price' => $product->get_sale_price()))) . $product->get_price_suffix();
            }
            
        }

        return $price;
    }
    add_filter('woocommerce_get_price_html', 'vtprd_loop_get_price_html', 10, 2);

    /**
     * Replace sale price within the loop
     *
     */
    function vtprd_loop_get_sale_price($value, $product)
    {
        if (!is_shop() && !is_product_category()) {
            return $value;
        }

        global $loop_product_discount_info;
        $product_id = $product->get_id();

        if(!$loop_product_discount_info[$product_id]['sale_price'])
            return $value;

        return $loop_product_discount_info[$product_id]['sale_price'] ?: $value;
    }
    add_filter('woocommerce_product_get_price', 'vtprd_loop_get_sale_price', 9, 2);
    add_filter('woocommerce_product_get_sale_price', 'vtprd_loop_get_sale_price', 9, 2);

    /**
     * Replace is-on-sale original state of product
     *
     */
    function vtprd_loop_is_on_sale($on_sale, $product)
    {
        if (!is_shop() && !is_product_category()) {
            return $on_sale;
        }

        global $loop_product_discount_info;

        $product_discount_info = $loop_product_discount_info[$product->get_id()];

        if (!$product_discount_info['is_available']) {
            return $on_sale;
        }

        if (!$product_discount_info['is_discounted']) {
            return $on_sale;
        }

        /**
         * Could be a conditional output.
         *
         * like so:
         * if($product_discount_info['rule_template'] === 'C-simpleDiscount') {
         *    ...
         * }
         */
        return true;
    }
    add_filter('woocommerce_product_is_on_sale', 'vtprd_loop_is_on_sale', 10, 2);

    /**
     * Replace sale flash within the loop.
     *
     */
    function vtprd_sale_flash($sale, $post, $product)
    {
        if (!is_shop() && !is_product_category()) {
            return $sale;
        }

        global $loop_product_discount_info;

        $product_discount_info = $loop_product_discount_info[$product->get_id()];

        if (!$product_discount_info['is_available']) {
            return '';
        }

        if (!$product_discount_info['is_discounted']) {
            return '';
        }

        /**
         * Could be a conditional output.
         *
         * like so:
         * if($product_discount_info['rule_template'] === 'C-simpleDiscount') {
         *    ...
         * }
         */
        $sale = sprintf(__('<span class="product-onsale">%s</span>', 'woocommerce'), $product_discount_info['discount_product_short_msg']);

        return $sale;

    }
    add_filter('woocommerce_sale_flash', 'vtprd_sale_flash', 11, 3);

    /**
     * Outputs prepared data for the loop.
     *
     * Only for debugging purposes.
     */
    function vtprd_before_loop_debug_output()
    {
        global $vtprd_cart, $vtprd_cart_item, $vtprd_info, $vtprd_rules_set, $vtprd_rule, $wpsc_coupons, $vtprd_setup_options, $loop_product_discount_info;

        // echo "<pre>";
        // print_r($vtprd_rules_set);
        // echo "</pre>";

        // echo "<pre>";
        // print_r($loop_product_discount_info);
        // echo "</pre>";
    }
    add_action('woocommerce_before_shop_loop', 'vtprd_before_loop_debug_output', 10);
}

function do_vtprd_product_page_integrations()
{

    /**
     * Store prepared info about the single product discount rules
     */
    global $single_product_discount_info; //, $variations_of_variable_product_info;

    

    function vtprd_prepare_info_before_single_product()
    {
        if (!is_product()) {
            return;
        }

        global $product, $vtprd_rules_set, $single_product_discount_info;
        //$variations_of_variable_product_info

        /**
         * If the product is variable add variations to the array
         */
        $products = [];
        $products[] = $product;

        $single_product_discount_info = vtprd_prepare_info_for_products($products);

        // if product has variations
        if( $product->get_type() === 'variable' && 
            !empty($variations_ids = $product->get_children()))
        {
            $variations = [];
            foreach ($variations_ids as $variations_id) {
                $variations[] = wc_get_product($variations_id);
            }
            
            $product_discount_info = &$single_product_discount_info[$product->get_id()];
            $product_discount_info['variations'] = vtprd_prepare_info_for_products($variations, $product->get_id());
            
            uasort($product_discount_info['variations'], function($item1, $item2){
                return $item1['sale_price'] - $item2['sale_price'];
            });
        }
        
    }
    add_action('woocommerce_before_single_product', 'vtprd_prepare_info_before_single_product', 10);

    /**
     * Replace sale flash text.
     *
     */
    function vtprd_single_product_sale_flash($sale, $post, $product)
    {
        if (!is_product()) {
            return $sale;
        }

        global $single_product_discount_info;

        $product_discount_info = $single_product_discount_info[$product->get_id()];

        if (!$product_discount_info['is_available']) {
            return '';
        }

        if (!$product_discount_info['is_discounted']) {
            return '';
        }

        /**
         * Could be a conditional output.
         *
         * like so:
         * if($product_discount_info['rule_template'] === 'C-simpleDiscount') {
         *    ...
         * }
         */

        $sale = sprintf(__('<span class="product-onsale">%s</span>', 'woocommerce'), $product_discount_info['discount_product_short_msg']);

        return $sale;

    }
    add_filter('woocommerce_sale_flash', 'vtprd_single_product_sale_flash', 11, 3);

    function vtprd_single_product_is_on_sale($on_sale, $product)
    {
        if (!is_product()) {
            return $on_sale;
        }

        global $single_product_discount_info;

        if($product->get_type() === 'variation') {
            $product_discount_info = current($single_product_discount_info)['variations'][$product->get_id()];
        } else {
            $product_discount_info = $single_product_discount_info[$product->get_id()];
        }

        if (!$product_discount_info['is_available']) {
            return false;
        }

        //echo count($vtprd_cart->cart_items);
        if (!$product_discount_info['is_discounted']) {
            return false;
        }

        /**
         * Could be a conditional output.
         *
         * like so:
         * if($product_discount_info['rule_template'] === 'C-simpleDiscount') {
         *    ...
         * }
         */

        return true;
    }
    add_filter('woocommerce_product_is_on_sale', 'vtprd_single_product_is_on_sale', 11, 2);

    /**
     * Replace original value of regular price.
     *
     */
    function vtprd_single_product_get_regular_price($value, $product)
    {
        if (!is_product()) {
            return $value;
        }

        global $single_product_discount_info;
        $product_id = $product->get_id();

        $product_discount_info = $single_product_discount_info[$product_id];

        if (!$product_discount_info['is_available']) {
            return $value;
        }

        if (!$product_discount_info['is_discounted']) {
            return $value;
        }

        return $product_discount_info['product_price_with_tax']; 
    }   
    add_filter('woocommerce_product_get_regular_price', 'vtprd_single_product_get_regular_price', 10, 2);

    /**
     * Replace original value of sale price.
     *
     */
    function vtprd_single_product_price($value, $product)
    {
        if (!is_product()) {
            return $value;
        }

        global $single_product_discount_info;
        $product_id = $product->get_id();

        $product_discount_info = $single_product_discount_info[$product_id];

        if (!$product_discount_info['is_available']) {
            return $value;
        }

        if (!$product_discount_info['is_discounted']) {
            return $value;
        }

        if ($product_discount_info['product_price_with_tax'] === $product_discount_info['discount_amount']) {
            return $value;
        }

        return $product_discount_info['sale_price'];
    }
    add_filter('woocommerce_product_get_price', 'vtprd_single_product_price', 10, 2);
    add_filter('woocommerce_product_get_sale_price', 'vtprd_single_product_price', 10, 2);


    ///////////////////////////////
    function vtprd_get_variable_price_html($price, $product) 
    {   
        if(!is_product())
            return $price;
        if($product->get_type() !== 'variable')
            return $price;

        global $single_product_discount_info;

        $variations = current($single_product_discount_info)['variations'];

        $min_price = current($variations)['sale_price'];
        $max_price = end($variations)['sale_price'];

        if ( '' === $product->get_price() ) {
            $price = apply_filters( 'woocommerce_empty_price_html', '', $product );
        } elseif ( $product->is_on_sale() ) {
            //wc_format_price_range( $from, $to );
            $price =  wc_price( wc_get_price_to_display( $product, array( 'price' => $min_price ) ) ) . ' &ndash; ' . wc_price( wc_get_price_to_display( $product, array( 'price' => $max_price ) ) ) . $product->get_price_suffix();
        } else {
            $price = wc_price( wc_get_price_to_display( $product ) ) . $product->get_price_suffix();
        }

        return $price;
    }
    add_filter( 'woocommerce_get_price_html', 'vtprd_get_variable_price_html', 9, 2 );

    function vtprd_single_get_price_html($price, $product)
    {
        if(!is_product())
            return $price;

        global $product, $single_product_discount_info;

        $product_id = $product->get_id();

        if ($product->is_on_sale() && empty($single_product_discount_info[$product_id]['sale_price'])) {
            $price = wc_price(wc_get_price_to_display($product, array('price' => $product->get_regular_price()))) . $product->get_price_suffix();
           
        }

        return $price;
    }
    add_filter('woocommerce_get_price_html', 'vtprd_single_get_price_html', 11, 2);

    function vtprd_get_variation_regular_price($value, $variation) {
        if(!is_product())
            return $value;

        global $single_product_discount_info;

        $variation_id = $variation->get_id();

        $variation_discount_info = current($single_product_discount_info)['variations'][$variation_id];
        
        if (!$variation_discount_info['is_available']) {
            return $value;
        }

        if (!$variation_discount_info['is_discounted']) {
            return $value;
        }

        return $variation_discount_info['sale_price'];
    }
    add_filter( 'woocommerce_product_variation_get_price', 'vtprd_get_variation_regular_price', 9, 2);

    function vtprd_get_variation_price($value, $variation) {
        if(!is_product())
            return $value;

        global $single_product_discount_info;

        $variation_id = $variation->get_id();

        $variation_discount_info = current($single_product_discount_info)['variations'][$variation_id];
        
        if (!$variation_discount_info['is_available']) {
            return $value;
        }

        if (!$variation_discount_info['is_discounted']) {
            return $value;
        }

        return $variation_discount_info['sale_price'];
    }
    add_filter( 'woocommerce_product_variation_get_price', 'vtprd_get_variation_price', 9, 2);
    add_filter('woocommerce_product_variation_get_sale_price', 'vtprd_get_variation_price', 9, 2);

    /**
     * Output advertising message.
     *
     */
    function vtprd_single_product_add_adverticing_message()
    {
        global $product, $single_product_discount_info;

        $product_id = $product->get_id();

        echo '<div class="vtprd_advertising_message">' . $single_product_discount_info[$product_id]['discount_product_full_msg'] . '</div>';
    }
    add_action('woocommerce_before_add_to_cart_form', 'vtprd_single_product_add_adverticing_message', 10);

    /**
     * Outputs prepared data for the product.
     *
     * Only for debugging purposes.
     */
    function vtprd_debug_output(){
        global $single_product_discount_info;
        // echo "<pre>";
        // print_r($single_product_discount_info);
        // echo "</pre>";

        // echo "<pre>";
        // print_r($variations_of_variable_product_info);
        // echo "</pre>";
    }
    add_action( 'woocommerce_before_single_product', 'vtprd_debug_output', 11 );
}

/**
 * Auxiliary function. Remove item from cart by its ID.
 *
 */
function remove_item_from_cart($id, $decrement, $parent_id=false)
{
    global $vtprd_rules_set, $vtprd_cart, $product;

    $cart         = WC()->cart;

    if($parent_id)
    $cart_id      = $cart->generate_cart_id($parent_id, $id);
    else
    $cart_id      = $cart->generate_cart_id($id, 0);

    //echo 'delete:'.$cart_id;

    if(!$cart_id)
        return -1;

    $cart_item_id = $cart->find_product_in_cart($cart_id);

    if(!$cart_item_id)
        return -2;

    // get quantity
    $quantity = $cart->get_cart()[$cart_item_id]['quantity'];

    if ($cart_item_id) {
        $cart->set_quantity($cart_item_id, $quantity - $decrement);

        return 1;
    }

    return 0;
}

//add_filter( 'woocommerce_cart_id', function($a){ echo 'ADDELETE:'.$a; return $a;  }, 10, 1 );

function vtprd_prepare_info_for_products($products, $parent_id = false)
{
    global $vtprd_rules_set;

    $rules = new VTPRD_Apply_Rules();

    $product_discount_info = [];

    foreach ($products as $k => $product) {

        $product_id = $product->get_id();

        //echo $product_id;

        if (!$product->is_purchasable() ||
            !$product->is_in_stock()) {
            $product_discount_info[$product_id]['is_available'] = 0;

            continue;
        } else {
            $product_discount_info[$product_id]['is_available'] = 1;
        }
        //echo $product_id;

        /**
         * Emulate adding to cart in order to use 'vtprd_is_product_in_inPop_group'
         * because the method works with cart items only.
         **/
        if($parent_id){
            WC()->cart->add_to_cart($parent_id, 1, $product_id);
        } else {
            WC()->cart->add_to_cart($product_id, 1);
        }

        // loop all the rules and break on the first found
        foreach ($vtprd_rules_set as $n => $set) {

            /**
             * Zero is the first element in cart, we must check the last added.
             */
            $addedItemIndex = count(WC()->cart->get_cart()) - 1;
            if ($rules->vtprd_is_product_in_inPop_group($n, $addedItemIndex) && vtprd_rule_date_validity_test($n)) {
                $p_di                               = &$product_discount_info[$product_id];
                $p_di['is_discounted']              = 1;
                $p_di['rule_template']              = $set->rule_template;
                $p_di['discount_product_short_msg'] = $set->discount_product_short_msg;
                $p_di['discount_product_full_msg']  = $set->discount_product_full_msg;
                // without taxes
                $p_di['product_price'] = vtprd_get_current_active_price($product_id, $product);
                // with taxes
                $p_di['product_price_with_tax'] = vtprd_maybe_price_incl_tax($product_id, $p_di['product_price']);

                $p_di['discount_amount'] = $rules->vtprd_compute_each_discount($n, 0, $p_di['product_price_with_tax']);
                if ($p_di['product_price_with_tax'] != $p_di['discount_amount']) {
                    $p_di['sale_price'] = $p_di['product_price_with_tax'] - $p_di['discount_amount'];
                } else {
                    $p_di['sale_price'] = '';
                }

            } else {
                if ($p_di['is_discounted'] != 1) {
                    $p_di['is_discounted'] = 0;
                }

            }
        }

        // remove item from the cart
        if($parent_id){
            remove_item_from_cart($product_id, 1, $parent_id);
        } else {
            remove_item_from_cart($product_id, 1);
        }
        
    }
    return $product_discount_info;
}