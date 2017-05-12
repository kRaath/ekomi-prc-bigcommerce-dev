<?php

require_once 'class-prc-template-handler.php';

/**
 * Returns the reviews to Ajax requests.
 *
 * @since 1.0.0
 */

/**
 * Loads reviews
 */
function load_reviews() {
    // Check if ekomi_interface_id is set $review_id, $column
    $product_id = isset($_POST['product_id']) ? $_POST['product_id'] : null;
    $offset_page = isset($_POST['offset_page']) ? $_POST['offset_page'] : 0;
    $filter_type = isset($_POST['filter_type']) ? $_POST['filter_type'] : 0;

    // Check submited data
    if (is_null($product_id)) {

        echo json_encode(
                array(
                    'state' => 'error',
                    'message' => 'Please provide the review parameters',
                    '_POST' => $_POST,
                )
        );
        
        die();
    }

    $orderBy = resolve_order_by($filter_type);

    $template = new Prc_Template_Handler($product_id);

    $reviews_data = $template->prc_main_widget_partial($offset_page, $orderBy);
    // return
    echo json_encode(
            array(
                'state' => 'success',
                'message' => 'reviews loaded!',
                'reviews_data' => $reviews_data,
                '_POST' => $_POST,
            )
    );
    die();
}

/**
 * 
 * @param int $filter_type The sorting filter value
 * 
 * @return string The Sorting filter
 */
function resolve_order_by($filter_type) {
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
 * Saves the users feedback on reviews
 */
function save_feedback() {

    $product_id = isset($_POST['product_id']) ? $_POST['product_id'] : null;

    $review_id = isset($_POST['review_id']) ? $_POST['review_id'] : null;
    $helpfulness = isset($_POST['helpfulness']) ? $_POST['helpfulness'] : null;

    // Check submited data
    if (!$review_id || is_null($helpfulness)) {
        echo json_encode(
                array(
                    'state' => 'error',
                    'message' => 'Please provide the review parameters',
                    '_POST' => $_POST,
                    'helpfulness' => $helpfulness . ' ' . gettype($helpfulness),
                )
        );
    } else {
        $db = new Prc_Db_Handler($product_id);
        $rate_helpfulness = $db->prc_rate_single_review_helpfulness($review_id, $helpfulness);

        if ($rate_helpfulness >= 1) {
            $review = $db->prc_get_single_review($review_id);

            $message = ($review->helpful) . ' people out of ' . ($review->helpful + $review->nothelpful) . ' found this review helpful';
            echo json_encode(
                    array(
                        'state' => 'success',
                        'message' => $message,
                        '_POST' => $_POST,
                        'rate_helpfulness' => $helpfulness == '1' ? 'helpful' : 'nothelpful',
                    )
            );
        } else {
            // Return
            echo json_encode(
                    array(
                        'state' => 'error',
                        'message' => 'Could not process the request! ' . $rate_helpfulness['last_error'],
                        '_POST' => $_POST,
                        'rate_helpfulness' => $rate_helpfulness,
                    )
            );
        }
    }

    // Return a proper json answer
    die();
}
