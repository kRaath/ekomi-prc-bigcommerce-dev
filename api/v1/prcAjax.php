<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Ekomi\DbHandler;
use Ekomi\ConfigHelper;
use Ekomi\APIsHanlder;

$app = new Application();
$app['debug'] = true;

$configHelper = new ConfigHelper(new Dotenv\Dotenv(__DIR__ . '/../../'));

// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => 'php://stderr',
));

// Register view rendering
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/../views',
));
$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver' => 'pdo_mysql',
        'host' => $configHelper->dbHost(),
        'dbname' => $configHelper->dbName(),
        'user' => $configHelper->dbUsername(),
        'password' => $configHelper->dbPassword(),
        'charset' => 'utf8mb4'
    ),
));

$app->post('/saveFeedback', function (Request $request) use ($app) {
    $headers = ['Access-Control-Allow-Origin' => '*'];

    $dbHandler = new DbHandler($app['db']);

    $storeHash = $request->get('storeHash');
    $reviewId = $request->get('review_id');
    $helpfulness = $request->get('helpfulness');

    // Check submited data
    if (!$reviewId || is_null($helpfulness)) {
        $response = array(
            'state' => 'error',
            'message' => 'Please provide the review parameters',
            'helpfulness' => $helpfulness . ' ' . gettype($helpfulness)
        );
    } else {
        $rateHelpfulness = $dbHandler->rateReview($storeHash, $reviewId, $helpfulness);

        if ($rateHelpfulness >= 1) {
            $review = $dbHandler->getSingleReview($reviewId);

            $message = ($review['helpful']) . ' people out of ' . ($review['helpful'] + $review['nothelpful']) . ' found this review helpful';
            $response = array(
                'state' => 'success',
                'message' => $message,
                'rateHelpfulness' => $helpfulness == '1' ? 'helpful' : 'nothelpful'
            );
        } else {
            // Return
            $response = array(
                'state' => 'error',
                'message' => 'Could not process the request! ' . $rateHelpfulness['last_error'],
                'rateHelpfulness' => $rateHelpfulness
            );
        }
    }
    return new Response(json_encode($response), 200, $headers);
});
$app->post('/loadReviews', function (Request $request) use ($app) {
    $headers = ['Access-Control-Allow-Origin' => '*'];

    $dbHandler = new DbHandler($app['db']);

    $storeHash = $request->get('storeHash');
    $productId = $request->get('prcProductId');
    $offset = $request->get('prcOffset');
    $prcFilter = $request->get('prcFilter');
    $reviewsLimit = $request->get('reviewsLimit');

    $config = $dbHandler->getPrcConfig($storeHash);

    $apiHanlder = new APIsHanlder();
    $configHelper = new ConfigHelper(new Dotenv\Dotenv(__DIR__ . '/../../'));
    $storeConfig = $dbHandler->getStoreConfig($storeHash);
    $bcProduct = $apiHanlder->getProduct(103, $configHelper->clientId(), $storeConfig);

    if ($bcProduct) {
        $productIDs = "'$productId'";
        // gets variants id
        if ($config['groupReviews'] == '1') {
              $productIDs .= $apiHanlder->getVariantIDs($bcProduct);
        }
    }

    $reviews = $dbHandler->fetchReviews($storeHash, $config['shopId'], $productIDs, $prcFilter, $offset, $reviewsLimit);

    $data = array(
        'reviews' => $reviews,
        'reviewsCountPage' => count($reviews)
    );

    $html = $app['twig']->render('reviewsContainerWidgetPartial.twig', $data);

    return new Response(json_encode(['result' => $html, 'count' => count($reviews)]), 200, $headers);
});
$app->run();

