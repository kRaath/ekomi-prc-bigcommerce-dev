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

    /**
     * Saves the config values to DB
     * 
     * @param type $storeConfig
     * @return type
     */
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

    public function getAllPrcConfig() {
        $data = $this->conn->fetchAll('SELECT * FROM prc_config');

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

    public function removePrcReviews($storeHash) {
        $val = $this->conn->delete('prc_reviews', array('storeHash' => $storeHash));
        return $val;
    }

    public function rateReview($storeHash, $id, $helpfulness) {
        // sanitize data
        $helpfulness = trim($helpfulness);
        $id = trim($id);

        // get the right column
        $column = $helpfulness == '1' ? 'helpful' : 'nothelpful';
        $review = $this->getSingleReview($id);

        if ($review) {
            $review[$column] = $review[$column] + 1;
        }

        // Do the query
        return $this->conn->update('prc_reviews', $review, array('id' => $id, 'storeHash' => $storeHash));
    }

    public function starsAvg($storeHash, $shopId, $productId) {
        $data = $this->conn->fetchAssoc("SELECT AVG(stars) as avg FROM prc_reviews WHERE storeHash = ? AND shopId = ? AND productId IN ({$productId})", array($storeHash, $shopId));
        if ($data) {
            return $data['avg'];
        }
        return $data;
    }

    public function countReviews($storeHash, $shopId, $productId) {
        $data = $this->conn->fetchAssoc("SELECT count(id) as count FROM prc_reviews WHERE storeHash = ? AND shopId = ? AND productId IN ({$productId})", array($storeHash, $shopId));
        if ($data) {
            return $data['count'];
        }
        return $data;
    }

    public function fetchReviews($storeHash, $shopId, $productId, $filterType, $offset, $limit) {
        $orderBy = $this->resolveOrderBy($filterType);
        $data = $this->conn->fetchAll("SELECT * FROM prc_reviews WHERE storeHash = ? AND  shopId = ? AND productId IN ({$productId})  ORDER BY {$orderBy} LIMIT $offset,{$limit}", array($storeHash, $shopId));

        return $data;
    }

    /**
     * Counts the stars
     * 
     * @return array The star counts array
     */
    public function reviewsStarCount($storeHash, $shopId, $productId) {

        $starsCount = $this->conn->fetchAll("SELECT productId, stars, count(id) as starsCount FROM prc_reviews WHERE storeHash = ? AND  shopId = ? AND productId IN ({$productId}) GROUP BY stars ORDER BY stars DESC", array($storeHash, $shopId));

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

    /**
     * 
     * @param int $filter_type The sorting filter value
     * 
     * @return string The Sorting filter
     */
    protected function resolveOrderBy($filter_type) {
        $orderBy = '';
        switch ($filter_type) {
            case 1:
                $orderBy = 'id DESC';
                break;
            case 2:
                $orderBy = 'id ASC';
                break;
            case 3:
                $orderBy = 'helpful DESC';
                break;
            case 4:
                $orderBy = 'stars DESC';
                break;
            case 5:
                $orderBy = 'stars ASC';
                break;

            default:
                $orderBy = 'id';
                break;
        }
        return $orderBy;
    }

    /**
     * Prepares the json string to show up in google structured data.
     * @param type $data
     * @return string
     */
    function prepareJsonld($data) {
        $jsonld = '';
        if ($data['reviewsCountTotal'] > 0) {
            $jsonld .= '{';
            $jsonld .= '"@context": "http://schema.org",
        "@type": "Product",
        "aggregateRating": {
          "@type": "AggregateRating",
          "ratingValue": "' . number_format($data['avgStars'], 1) . '",
          "reviewCount": "' . $data['reviewsCountTotal'] . '"
        },
        "productID": "' . $data['productId'] . '",
        "sku": "' . $data['productSku'] . '",
        "name": "' . $data['productName'] . '",
        "image": "' . $data['productImage'] . '",
        "description": "' . strip_tags($data['productDescription']) . '"';

            $jsonld .= ',"review": [';
            foreach ($data['reviews'] as $key => $value) {
                $jsonld .= ($key != 0) ? ',' : '';
                $jsonld .= '{
            "@type": "Review",
            "datePublished": "' . date('m.d.Y H:i:s', $value['timestamp']) . '",
            "reviewBody": "' . $value['reviewComment'] . '",
            "author": {
                "@type": "Organization",
                "name": "eKomi"
                },
            "reviewRating": {
                "@type": "Rating",
                "worstRating": "1",
                "ratingValue": "' . $value['stars'] . '",
                "bestRating": "5"
              }
            }';
            }
            $jsonld .= ']';

            $jsonld .= '}';
        }
        return $jsonld;
    }

}
