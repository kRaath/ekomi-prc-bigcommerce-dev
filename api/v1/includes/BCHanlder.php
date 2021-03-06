<?php

namespace Ekomi;

use Bigcommerce\Api\Client as Bigcommerce;

/**
 * Calls the BigCommerce APIs
 * 
 * This is the class which contains the queries to eKomi Systems.
 * 
 * @since 1.0.0
 */
class BCHanlder {

    private $storeConfig;
    private $prcConfig;

    function __construct($storeConfig, $prcConfig, $clientId) {
        $this->storeConfig = $storeConfig;
        $this->prcConfig = $prcConfig;

        Bigcommerce::useJson();

        Bigcommerce::configure(array(
            'client_id' => $clientId,
            'auth_token' => $storeConfig['accessToken'],
            'store_hash' => $storeConfig['storeHash']
        ));

        Bigcommerce::verifyPeer(false);
    }

    /**
     * Gets product
     * 
     * @param type $productId
     * @return type
     */
    public function getProduct($productId) {
        $product = Bigcommerce::getProduct($productId);
        $data = $this->getObjectField($product);
        return $data;
    }

    /*
     * Gets field from BC object
     * 
     * @access private
     */

    private function getObjectFields($array) {
        $fields = array();
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                $fields[] = $this->getObjectField($value);
            }
        }
        return $fields;
    }

    /*
     * Gets field from BC object
     * 
     * @access private
     */

    private function getObjectField($object) {
        $array = (array) $object;
        return $array[' * fields'];
    }

    /**
     * Gets Varinats of the product
     * 
     * @param type $bcProduct
     * @return string Comma separated product ids.
     */
    public function getVariantIDs($bcProduct) {
        $productId = '';
        if ($bcProduct) {
            foreach ($bcProduct->variants as $key => $variant) {
                $productId .= ',' . "'$variant->id'";
            }
        }
        return $productId;
    }

}
