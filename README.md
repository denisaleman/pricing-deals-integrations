# Integrations of Pricing Deals for WooCommerce
Oficial plugin page in Wordpress repository - https://ru.wordpress.org/plugins/pricing-deals-for-woocommerce/

Plugin Author website - https://www.varktech.com/

## How to use it
1. Place the package in the root of the theme;

2. Add within functions.php:
```
if ( is_plugin_active( 'pricing-deals-for-woocommerce/vt-pricing-deals.php' ) &&
     is_plugin_active( 'pricing-deals-pro-for-woocommerce/vt-pricing-deals-pro.php' ) ) {

	require_once 'pricing-deals-integrations/pricing-deals-integrations.php';

	do_vtprd_product_page_integrations();

	do_vtprd_loop_integrations();

}
```
That's all.