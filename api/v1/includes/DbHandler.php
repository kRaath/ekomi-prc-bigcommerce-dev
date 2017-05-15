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
     * prc_reviews operations
     */
    public function getSingleReview($id) {
        $data = $this->conn->fetchAssoc('SELECT * FROM prc_reviews WHERE id = ?', array($id));

        return $data;
    }

    public function isReviewExist($config, $review) {
        $data = $this->conn->fetchAssoc('SELECT id FROM prc_reviews WHERE storeHash = ? AND shopId = ? AND productId = ? AND orderId = ? AND timestamp = ?', array($config['storeHash'], $config['shopId'], $review['product_id'], $review['order_id'], $review['submitted']));
        if ($data) {
            return TRUE;
        }
        return FALSE;
    }

    public function saveReviews($config, $reviews) {
        foreach ($reviews as $review) {
            if (!$this->isReviewExist($config, $review)) {
                $insertData = array(
                    'storeHash' => $config['storeHash'],
                    'shopId' => $config['shopId'],
                    'orderId' => $review['order_id'],
                    'productId' => $review['product_id'],
                    'timestamp' => $review['submitted'],
                    'stars' => $review['rating'],
                    'reviewComment' => $review['review'],
                    'helpful' => 0,
                    'nothelpful' => 0
                );
                $this->conn->insert('prc_reviews', $insertData);
            } else {
                
            }
        }
        return TRUE;
    }

    public function updateReview($data, $id) {
        $value = $this->conn->update('prc_reviews', $data, array('id' => $id));
        return $value;
    }

    public function starsAvg($storeHash, $shopId, $productId) {
        $data = $this->conn->fetchAssoc('SELECT AVG(stars) as avg FROM prc_reviews WHERE storeHash = ? AND shopId = ? AND productId IN (?)', array($storeHash, $shopId, $productId));
        if ($data) {
            return $data['avg'];
        }
        return $data;
    }

    public function countReviews($storeHash, $shopId, $productId) {
        $data = $this->conn->fetchAssoc('SELECT count(id) as count FROM prc_reviews WHERE storeHash = ? AND shopId = ? AND productId IN (?)', array($storeHash, $shopId, $productId));
        if ($data) {
            return $data['count'];
        }
        return $data;
    }

    public function fetchReviews($storeHash, $shopId, $productId, $orderBy, $offset, $limit) {
        $data = $this->conn->fetchAll("SELECT * FROM prc_reviews WHERE storeHash = ? AND  shopId = ? AND productId IN (?)  ORDER BY {$orderBy} LIMIT $offset,{$limit}", array($storeHash, $shopId, $productId));

        return $data;
    }

    /**
     * Counts the stars
     * 
     * @return array The star counts array
     */
    public function reviewsStarCount($storeHash, $shopId, $productId) {

        $starsCount = $this->conn->fetchAll('SELECT productId, stars, count(id) as starsCount FROM prc_reviews WHERE storeHash = ? AND  shopId = ? AND productId IN (?) GROUP BY stars ORDER BY stars DESC', array($storeHash, $shopId, $productId));

        $starsCountArray = array();
        foreach ($starsCount as $key => $value) {
            $starsCountArray[$value['stars'] . 'stars'] = $value['starsCount'];
        }

        // set count for all stars
        for ($i = 1; $i <= 5; $i++) {
            if (!isset($starsCountArray[$i . 'stars'])) {
                $starsCountArray[$i . 'stars'] = 0;
            }
        }
        return $starsCountArray;
    }

}
