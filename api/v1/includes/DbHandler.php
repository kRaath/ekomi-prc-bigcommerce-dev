<?php

namespace Ekomi;

/**
 * Handles the database related functionality
 * 
 * This is the class which contains the queries to products reviews.
 * 
 * @since 1.0.0
 */
class DbHandler {

    protected $conn;

    function __construct($db) {
        echo "hello, i am a handler.";
        $this->conn = $db;
    }

    /**
     * store_config CRUD
     */
    public function getStoreConfig($storeHash) {
        $data = $this->conn->fetchAssoc('SELECT * FROM store_config WHERE storeHash = ?', array($storeHash));

        return $data;
    }

    public function saveStoreConfig($storeConfig) {
        return $this->conn->insert('store_config', $storeConfig);
    }

    public function updateStoreConfig($storeConfig, $storeHash) {
        return $this->conn->update('store_config', $storeConfig, array('storeHash' => $storeHash));
    }

    public function removeStoreConfig($storeHash) {
        $val = $this->conn->delete('store_config', array('storeHash' => $storeHash));

        return $val;
    }

    /**
     * prc_config CRUD
     */
    public function getPrcConfig($storeHash) {
        $data = $this->conn->fetchAssoc('SELECT * FROM prc_config WHERE storeHash = ?', array($storeHash));

        return $data;
    }

    public function savePrcConfig($config) {
        $data = $this->conn->insert('prc_config', $config);

        return $data;
    }

    public function updatePrcConfig($config, $storeHash) {
        $value = $this->conn->update('prc_config', $config, array('storeHash' => $storeHash));
        return $value;
    }

    public function removePrcConfig($storeHash) {
        $val = $this->conn->delete('prc_config', array('storeHash' => $storeHash));

        return $val;
    }

    /**
     * prc_reviews CRUD
     */
    public function getReview($storeHash) {
        $data = $this->conn->fetchAssoc('SELECT * FROM prc_reviews WHERE storeHash = ?', array($storeHash));

        return $data;
    }

    public function saveReview($config) {
        $data = $this->conn->insert('prc_reviews', $config);

        return $data;
    }

    public function updateReview($config, $storeHash) {
        $value = $this->conn->update('prc_reviews', $config, array('storeHash' => $storeHash));
        return $value;
    }

    public function removeReview($storeHash) {
        $val = $this->conn->delete('prc_reviews', array('storeHash' => $storeHash));

        return $val;
    }

    public function starsAvg($storeHash, $shopId, $productId) {
        $data = $this->conn->fetchAssoc('SELECT AVG(stars) as avg FROM prc_reviews WHERE storeHash = ?', array($storeHash));
        if ($data) {
            return $data['avg'];
        }
        return $data;
    }
    public function countReviews($storeHash, $shopId, $productId) {
        $data = $this->conn->fetchAssoc('SELECT count(id) as count FROM prc_reviews WHERE storeHash = ?', array($storeHash));
        if ($data) {
            return $data['count'];
        }
        return $data;
    }

}
