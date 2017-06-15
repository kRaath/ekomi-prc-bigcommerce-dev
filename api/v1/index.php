<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Silex\Application;
use Bigcommerce\Api\Client as Bigcommerce;
use Firebase\JWT\JWT;
use Guzzle\Http\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Ekomi\DbHandler;
use Ekomi\APIsHanlder;
use Ekomi\ConfigHelper;
use Ekomi\BCHanlder;

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
$app->get('/howToInstallWidgets', function (Request $request) use ($app) {
    $storeHash = $request->get('storeHash');
    $response = ['storeHash' => $storeHash];
    return $app['twig']->render('installWidgets.twig', $response);
});

$app->post('/saveConfig', function (Request $request) use ($app) {
    $storeHash = $request->get('storeHash');
    $id = $request->get('shopId');
    $secret = $request->get('shopSecret');

    $config = array(
        'storeHash' => $storeHash,
        'enabled' => $request->get('enabled'),
        'shopId' => $id,
        'shopSecret' => $secret,
        'groupReviews' => $request->get('groupReviews'),
        'noReviewsTxt' => $request->get('noReviewsTxt'));
    
    $apisHanlder = new APIsHanlder();

    if ($id && $secret && $apisHanlder->verifyAccount($config)) {

        $dbHandler = new DbHandler($app['db']);

        if (!$dbHandler->getPrcConfig($storeHash)) {
            $dbHandler->savePrcConfig($config);
        } else {
            $dbHandler->updatePrcConfig($config, $storeHash);
        }

        /**
         * populate the prc_reviews table
         */
        if ($config['enabled'] == '1') {
            $reviews = $apisHanlder->getProductReviews($config, $range = "all");
            $dbHandler->saveReviews($config, $reviews);
        }

        $response = ['storeHash' => $storeHash, 'alert' => 'info', 'message' => 'Configuration saved successfully.'];
        if ($config['enabled'] == '1') {
            return $app['twig']->render('installWidgets.twig', $response);
        }
    } else {
        $response = ['config' => $config, 'storeHash' => $storeHash, 'alert' => 'danger', 'message' => 'Invalid shop id or secret.'];
    }
    return $app['twig']->render('configuration.twig', $response);
});

// Our web handlers
$app->get('/updateProductReviews', function (Request $request) use ($app) {

    $apisHanlder = new APIsHanlder();
    $dbHandler = new DbHandler($app['db']);
    $storesConfig = $dbHandler->getAllPrcConfig();

    /**
     * populate the prc_reviews table
     */
    foreach ($storesConfig as $key => $config) {
        if ($config['enabled'] == '1') {
            $reviews = $apisHanlder->getProductReviews($config, $range = "1w");
            $dbHandler->saveReviews($config, $reviews);
        }
    }
    return "Done";
});
$app->get('/load', function (Request $request) use ($app) {

    $data = verifySignedRequest($request->get('signed_payload'));
    if (empty($data)) {
        return 'Invalid signed_payload.';
    } else {
        
    }

    $storeHash = $data['store_hash'];
    // fetch config from DB and send as param
//	$kedy = getUserKey($data['store_hash'], $data['user']['email']);
    $dbHandler = new DbHandler($app['db']);
    $config = $dbHandler->getPrcConfig($storeHash);

    return $app['twig']->render('configuration.twig', ['config' => $config, 'storeHash' => $storeHash]);
});

/**
 * Called by BC on installing the app
 */
$app->get('/oauth', function (Request $request) use ($app) {
    $configHelper = new ConfigHelper(new Dotenv\Dotenv(__DIR__ . '/../../'));

    $payload = array(
        'client_id' => $configHelper->clientId(),
        'client_secret' => $configHelper->clientSecret(),
        'redirect_uri' => $configHelper->callbackUrl(),
        'grant_type' => 'authorization_code',
        'code' => $request->get('code'),
        'scope' => $request->get('scope'),
        'context' => $request->get('context'),
    );

    $client = new Client($configHelper->bcAuthService());
    $req = $client->post('https://login.bigcommerce.com/oauth2/token', array(), $payload, array(
        'exceptions' => false,
    ));
    $resp = $req->send();

    if ($resp->getStatusCode() == 200) {
        $data = $resp->json();

        list($context, $storeHash) = explode('/', $data['context'], 2);

        $accessToken = $data['access_token'];
        $user = $data['user'];

        $storeConfig = array(
            'storeHash' => $storeHash,
            'accessToken' => $accessToken,
            'userId' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'installed' => 1,
        );

        $dbHandler = new DbHandler($app['db']);

        //removes the existing config and reviews in table
        $dbHandler->removeStoreConfig($storeHash);
        $dbHandler->removePrcConfig($storeHash);
        $dbHandler->removePrcReviews($storeHash);

        $store = $dbHandler->getStoreConfig($storeHash);

        if (!$store) {
            $dbHandler->saveStoreConfig($storeConfig);
        } else {
            $dbHandler->updateStoreConfig($storeConfig, $storeHash);
        }

        $config = $dbHandler->getPrcConfig($storeHash);

        return $app['twig']->render('configuration.twig', ['config' => $config, 'storeHash' => $storeHash, 'alert' => 'info', 'message' => 'Please save configuration.']);
    } else {
        return 'Something went wrong... [' . $resp->getStatusCode() . '] ' . $resp->getBody();
    }
});

/**
 * Calls by BC on un installing the app
 */
$app->get('/uninstall', function (Request $request) use ($app) {

    $data = verifySignedRequest($request->get('signed_payload'));
    if (empty($data)) {
        return 'Invalid signed_payload.';
    } else {
        
    }

    $storeHash = $data['store_hash'];

    $dbHandler = new DbHandler($app['db']);
    $dbHandler->removeStoreConfig($storeHash);
    $dbHandler->removePrcConfig($storeHash);
    $dbHandler->removePrcReviews($storeHash);

    return "uninstalled successfully";
});

/**
 * Returns the mini stars widget
 */
$app->get('/miniStarsWidget', function (Request $request) use ($app) {
    $headers = ['Access-Control-Allow-Origin' => '*'];
    $storeHash = $request->get('storeHash');
    $productId = $request->get('productId');

    $dbHandler = new DbHandler($app['db']);

    $config = $dbHandler->getPrcConfig($storeHash);
    if ($config && $config['enabled'] == '1') {
        $configHelper = new ConfigHelper(new Dotenv\Dotenv(__DIR__ . '/../../'));
        $storeConfig = $dbHandler->getStoreConfig($storeHash);

        $bcHandler = new BCHanlder($storeConfig, $config,$configHelper->clientId());

        $bcProduct = $bcHandler->getProduct($productId);

        if ($bcProduct) {
            $productIDs = "'$productId'";
            // gets variants id
            if ($config['groupReviews'] == '1') {
                $productIDs .= $bcHandler->getVariantIDs($bcProduct);
            }
            $avg = $dbHandler->starsAvg($storeHash, $config['shopId'], $productIDs);
            $count = $dbHandler->countReviews($storeHash, $config['shopId'], $productIDs);
            $data = array(
                'starsAvg' => $avg,
                'reviewsCount' => $count,
                'productName' => $bcProduct->name,
                'baseUrl' => baseUrl()
            );
            $html = $app['twig']->render('miniStarsWidget.twig', $data);
            return new Response(json_encode(['widgetHtml' => $html, 'jsonld' => '']), 200, $headers);
        }
    }
    return '';
});

/**
 * Returns the product review container widget
 */
$app->get('/reviewsContainerWidget', function (Request $request) use ($app) {
    $headers = ['Access-Control-Allow-Origin' => '*'];
    $dbHandler = new DbHandler($app['db']);
    $storeHash = $request->get('storeHash');
    $productId = $request->get('productId');
    $config = $dbHandler->getPrcConfig($storeHash);
    if ($config && $config['enabled'] == '1' && !empty($productId)) {


        $configHelper = new ConfigHelper(new Dotenv\Dotenv(__DIR__ . '/../../'));
        $storeConfig = $dbHandler->getStoreConfig($storeHash);

         $bcHandler = new BCHanlder($storeConfig, $config, $configHelper->clientId());

        $bcProduct = $bcHandler->getProduct($productId);
        
        if ($bcProduct) {
            $productIDs = "'$productId'";
            // gets variants id
            if ($config['groupReviews'] == '1') {
                $productIDs .= $bcHandler->getVariantIDs($bcProduct);
            }

            $offset = 0;
            $limit = 5;
            $avg = $dbHandler->starsAvg($storeHash, $config['shopId'], $productIDs);

            $reviews = $dbHandler->fetchReviews($storeHash, $config['shopId'], $productIDs, $orderBy = '', $offset, $limit);
            $reviewsStarCount = $dbHandler->reviewsStarCount($storeHash, $config['shopId'], $productIDs);
            $count = $dbHandler->countReviews($storeHash, $config['shopId'], $productIDs);

            $data = array(
                'storeHash' => $storeHash,
                'productId' => $productId,
                'productName' => $bcProduct->name,
                'productImage' => $bcProduct->primary_image->standard_url,
                'productSku' => $bcProduct->sku,
                'productDescription' => $bcProduct->description,
                'reviewsLimit' => $limit,
                'reviewsCountTotal' => $count,
                'reviewsCountPage' => count($reviews),
                'avgStars' => $avg,
                'starsCountArray' => $reviewsStarCount,
                'reviews' => $reviews,
                'noReviewText' => 'no Reviews Available',
                'baseUrl' => baseUrl(),
            );

            $html = $app['twig']->render('reviewsContainerWidget.twig', $data);
            $jsonld = $dbHandler->prepareJsonld($data);
            return new Response(json_encode(['widgetHtml' => $html, 'jsonld' => $jsonld]), 200, $headers);
        }
    }
    return '';
});

/**
 * @param string $storeHash store's hash that we want the access token for
 * @return string the oauth Access (aka Auth) Token to use in API requests.
 */
function getAuthToken($storeHash) {
    $dbHandler = new DbHandler($app['db']);

    $config = $dbHandler->getStoreConfig($storeHash);

    return $config['accessToken'];
}

/**
 * @param string $jwtToken 	customer's JWT token sent from the storefront.
 * @return string customer's ID decoded and verified
 */
function getCustomerIdFromToken($jwtToken) {
    $configHelper = new ConfigHelper(new Dotenv\Dotenv(__DIR__ . '/../../'));
    $signedData = JWT::decode($jwtToken, $configHelper->clientSecret(), array('HS256', 'HS384', 'HS512', 'RS256'));
    return $signedData->customer->id;
}

/**
 * This is used by the `GET /load` endpoint to load the app in the BigCommerce control panel
 * @param string $signedRequest Pull signed data to verify it.
 * @return array|null null if bad request, array of data otherwise
 */
function verifySignedRequest($signedRequest) {
    list($encodedData, $encodedSignature) = explode('.', $signedRequest, 2);

    // decode the data
    $signature = base64_decode($encodedSignature);
    $jsonStr = base64_decode($encodedData);
    $data = json_decode($jsonStr, true);

    // confirm the signature
    $configHelper = new ConfigHelper(new Dotenv\Dotenv(__DIR__ . '/../../'));

    $expectedSignature = hash_hmac('sha256', $jsonStr, $configHelper->clientSecret(), $raw = false);
    if (!hash_equals($expectedSignature, $signature)) {
        error_log('Bad signed request from BigCommerce!');
        return null;
    }
    return $data;
}

function baseUrl() {
    $url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'];
    $temp = explode('v1', $_SERVER['REDIRECT_URL']);
    if (isset($temp[0])) {
        return $url . $temp[0];
    } else {
        return $url . 'ekomi-prc-bigcommerce/api/';
    }
}

$app->run();

