<?php

namespace Ekomi;

/**
 * Calls the eKomi APIs
 * 
 * This is the class which contains the queries to eKomi Systems.
 * 
 * @since 1.0.0
 */
class APIsHanlder {

    function __construct() {
        
    }

    /**
     * @param $configData array
     */
    public function verifyAccount($configData) {
        $ApiUrl = 'http://api.ekomi.de/v3/getSettings';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $ApiUrl . "?auth=" . $configData['shopId'] . "|" . $configData['shopSecret'] . "&version=cust-1.0.0&type=request&charset=iso");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($ch);
        curl_close($ch);
        if ($server_output == 'Access denied') {
            return FALSE;
        } else {
            return TRUE;
        }
    }

    /**
     * Loads product reviews
     * 
     * @param $conf
     * @param $range
     *
     * @return array|mixed|object
     */
    public function getProductReviews($conf, $range) {
        $ekomi_api_url = 'http://api.ekomi.de/v3/getProductfeedback?interface_id=' .
                $conf['shopId'] . '&interface_pw=' . $conf['shopSecret'] .
                '&type=json&charset=utf-8&range=' . $range;
        // Get the reviews
        $product_reviews = file_get_contents($ekomi_api_url);

        // log the results
        if (!$product_reviews) {
            
        }

        return json_decode($product_reviews, true);
    }

    public function getProduct($productId, $clinetId, $storeConfig) {
        $headers = [
            "X-Auth-Client: {$clinetId}",
            "X-Auth-Token: {$storeConfig['accessToken']}",
            'Accept:application/json',
            'Content-Type:application/json'
        ];

        $uri = "https://api.bigcommerce.com/stores/{$storeConfig['storeHash']}/v3/catalog/products/{$productId}?include=variants";
        $ch = curl_init($uri);
        curl_setopt_array($ch, array(
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => 1
        ));
        $output = curl_exec($ch);
        curl_close($ch);

        $product = json_decode($output);
        if ($product) {
            return $product->data;
        }
        return NULL;
    }

    public function getVariantIDs($bcProduct) {
        $productId = '';
        if ($bcProduct) {
            foreach ($bcProduct->variants as $key => $variant) {
                $productId .= ',' . $variant->id;
            }
        }
        return $productId;
    }

}
