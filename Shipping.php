<?php

/*
Plugin Name: Viva Shipping Modifications
Plugin URI:
Description: This is a custom plugin for a client who required modifications to FedEx shipping methods at checkout.
Version:     1.0.0
Author:      SeanfitzGeek
Author URI:  https://bitbucket.org/SeanfitzGeek/
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:
*/

class ShippingModifications {
    private static $cache;

    /** @var self $instance */
    private static $instance = null;

    /** @var bool $perishable */
    public static $perishable = false;

    public static $zipCache = null;

    public static $shippingMethods = [
        'PRIORITY_OVERNIGHT' => [
            'perishable' => true,
            'local' => false,
            'deliveryTime' => 1,

        ],
        'STANDARD_OVERNIGHT' => [
            'perishable' => true,
            'local' => false,
            'deliveryTime' => 1,

        ],
        'GROUND_HOME_DELIVERY' => [
            'perishable' => false,
            'local' => true,
            'deliveryTime' => 3,

        ],
        'FEDEX_2_DAY_AM' => [
            'perishable' => false,
            'local' => false,
            'deliveryTime' => 2,

        ],
        'FEDEX_2_DAY' => [
            'perishable' => false,
            'local' => false,
            'deliveryTime' => 2,

        ],
    ];

    const SHIPPING_INFO = 'Estimated Shipping Date: ';
    const LOCAL_SHIPPING_LABEL = 'Free Local Shipping &nbsp;';
    const SHIPPING_ICON = '<span title="%s" data-toggle="tooltip" data-placement="top" data-shipping="%s" 
        style="display: inline;" class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>';

    public static function instance() {
        if (!self::$instance)
            self::$instance = new static();

        return self::$instance;
    }

    public function run() {
        add_filter('woocommerce_package_rates', [$this, 'getCustomShippingRates'], 100);
        add_filter('woocommerce_cart_shipping_method_full_label', [$this, 'getShippingInfo'], 2, 100);
        add_filter('woocommerce_cart_shipping_method_full_label', [$this, 'modifyShippingLabel'], 2, 100);
    }

    public function getShippingInfo($label, $rate) {
        foreach (self::$shippingMethods as $method => $option) {
            if ($rate->id === $rate->method_id . ':' . $method) {
                $time = $this->getDeliveryDate($option['deliveryTime']);

                $label .= sprintf(self::SHIPPING_ICON, self::SHIPPING_INFO . $time->format('m/d/Y'), $option['deliveryTime']);

                return $label;
            }
        }

        return $label;
    }

    public function getCustomShippingRates($rates) {
        foreach ($rates as $key => $rate) {
            $rate->cost = $rate->cost - $this->getReducedShippingAmount();

            if ((float)$rate->cost < 0)
                $rate->cost = 0.00;

            foreach (self::$shippingMethods as $method => $option) {
                if (self::checkLocalZip())
                    if ($rate->id === $rate->method_id . ':' . $method && !$option['local'])
                        unset($rates[$key]);
                    else {
                        $rate->label = self::LOCAL_SHIPPING_LABEL;
                        $rate->cost = 0;
                    }
                else if (self::$perishable)
                    if ($rate->id === $rate->method_id . ':' . $method && !$option['perishable'])
                        unset($rates[$key]);
            }
        }

        return $rates;
    }

    public function getDeliveryDate($shipping) {
        /** @var DateTime $date */
        $date = new DateTime();

        // Shop closes at 5, all orders prior are moved forward a day
        if ($date->format('h') >= 17)
            $shipping += 1;

        return new DateTime(sprintf('+ %s day', $shipping));
    }

    public function getReducedShippingAmount() {
        global $woocommerce;

        if (self::$cache)
            return self::$cache;

        $cache = [];

        foreach ($woocommerce->cart->get_cart() as $item => $values) {
            if (!self::$perishable)
                if (get_post_meta($values['product_id'], '_perishable', true))
                    self::$perishable = true;

            $reducedRate = get_post_meta($values['product_id'], '_rateReduction', true);

            $cache[] = (float)$reducedRate * $values['quantity'];
        }

        return (self::$cache = array_sum($cache));
    }

    private function checkLocalZip() {
        global $woocommerce;
        $zipcode = $woocommerce->customer->get_shipping_postcode();

        if (self::$zipCache !== null)
            return self::$zipCache;

        $range = range('29900', '29999');
        $flRange = range('32004', '34997');

        if (in_array($zipcode, $flRange) || in_array($zipcode, $range))
            return (self::$zipCache = true);

        return (self::$zipCache = false);
    }

    public function modifyShippingLabel($label, $method) {
        if ((float)$method->cost <= 0)
            return sprintf('%s <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">$</span>0.00</span>', $label);

        return $label;
    }
}

ShippingModifications::instance()->run();