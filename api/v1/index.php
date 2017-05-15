<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Silex\Application;
use Bigcommerce\Api\Client as Bigcommerce;
use Firebase\JWT\JWT;
use Guzzle\Http\Client;
use Handlebars\Handlebars;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Ekomi\DbHandler;
use Ekomi\EkomiHelper;
use Ekomi\ConfigHelper;

$app = new Application();
$app['debug'] = true;


//$ekomi = new ekomiHelper();
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
    $ekomiHelper = new EkomiHelper();

    if ($id && $secret && $ekomiHelper->verifyAccount($config)) {

        $dbHandler = new DbHandler($app['db']);

        if (!$dbHandler->getPrcConfig($storeHash)) {
            $dbHandler->savePrcConfig($config);
        } else {
            $dbHandler->updatePrcConfig($config, $storeHash);
        }

        /**
         * populate the prc_reviews table
         */
        $reviews = $ekomiHelper->getProductReviews($config, $range = "1w");
        $dbHandler->saveReviews($config, $reviews);

        $response = ['config' => $config, 'storeHash' => $storeHash, 'alert' => 'info', 'message' => 'Configuration saved successfully.'];
    } else {
        $response = ['config' => $config, 'storeHash' => $storeHash, 'alert' => 'danger', 'message' => 'Invalid shop id or secret.'];
    }
    return $app['twig']->render('configuration.twig', $response);
});

// Our web handlers
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

$app->get('/oauth', function (Request $request) use ($app) {
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


$app->get('/uninstall', function (Request $request) use ($app) {

    die('uninstall');
});

$app->get('/miniStarsWidget', function (Request $request) use ($app) {
    //$headers = ['Access-Control-Allow-Origin' => '*'];
    $storeHash = $request->get('storeHash');
    $productId = $request->get('productId');

    $dbHandler = new DbHandler($app['db']);



    $config = $dbHandler->getPrcConfig($storeHash);

    $avg = $dbHandler->starsAvg($storeHash, $config['shopId'], $productId);
    $count = $dbHandler->countReviews($storeHash, $config['shopId'], $productId);

    return $app['twig']->render('miniStarsWidget.twig', ['starsAvg' => $avg, 'reviewsCount' => $count, 'articleName' => 'testitem']);
});
$app->get('/reviewsContainerWidget', function (Request $request) use ($app) {
    $dbHandler = new DbHandler($app['db']);
    $storeHash = $request->get('storeHash');
    $productId = $request->get('productId');

    $config = $dbHandler->getPrcConfig($storeHash);
    $orderBy = 'id';
    $offset = 0;
    $limit = 5;
    $avg = $dbHandler->starsAvg($storeHash, $config['shopId'], $productId);

    $reviews = $dbHandler->fetchReviews($storeHash, $config['shopId'], $productId, $orderBy, $offset, $limit);
    $reviewsStarCount = $dbHandler->reviewsStarCount($storeHash, $config['shopId'], $productId);
    $count = $dbHandler->countReviews($storeHash, $config['shopId'], $productId);

//    var_dump($reviewsStarCount);die;
    $data = array(
        'productId' => 123,
        'productName' => 'testitem',
        'reviewsLimit' => $limit,
        'reviewsCountTotal' => $count,
        'reviewsCountPage' => count($reviews),
        'avgStars' => $avg,
        'starsCountArray' => $reviewsStarCount,
        'reviews' => $reviews,
        'noReviewText' => 'no Reviews Available',
    );
    return $app['twig']->render('reviewsContainerWidget.twig', $data);
});

/**
 * GET /storefront/{storeHash}/customers/{jwtToken}/recently_purchased.html
 * Fetches the "Recently Purchased Products" HTML block and displays it in the frontend.
 */
$app->get('/storefront/{storeHash}/customers/{jwtToken}/recently_purchased.html', function ($storeHash, $jwtToken) use ($app) {
    $headers = ['Access-Control-Allow-Origin' => '*'];

    try {
        // First let's get the customer's ID from the token and confirm that they're who they say they are.
        $customerId = getCustomerIdFromToken($jwtToken);

        // Next let's initialize the BigCommerce API for the store requested so we can pull data from it.
        configureBCApi($storeHash);

        // Generate the recently purchased products HTML
        $recentlyPurchasedProductsHtml = getRecentlyPurchasedProductsHtml($storeHash, $customerId);

        // Now respond with the generated HTML
        $response = new Response($recentlyPurchasedProductsHtml, 200, $headers);
    } catch (Exception $e) {
        error_log("Error occurred while trying to get recently purchased items: {$e->getMessage()}");
        $response = new Response("", 500, $headers); // Empty string here to make sure we don't display any errors in the storefront.
    }

    return $response;
});

/**
 * Gets the HTML block that displays the recently purchased products for a store.
 * @param string $storeHash
 * @param string $customerId
 * @return string HTML content to display in the storefront
 */
function getRecentlyPurchasedProductsHtml($storeHash, $customerId) {
    $redis = new Credis_Client('plugindev.coeus-solutions.de');
    $cacheKey = "stores/{$storeHash}/customers/{$customerId}/recently_purchased_products.html";
    $cacheLifetime = 60 * 5; // Set a 5 minute cache lifetime for this HTML block.
    // First let's see if we can find he HTML block in the cache so we don't have to reach out to BigCommerce's servers.
    $cachedContent = json_decode($redis->get($cacheKey));
    if (!empty($cachedContent) && (int) $cachedContent->expiresAt > time()) { // Ensure the cache has not expired as well.
        return $cachedContent->content;
    }

    // Whelp looks like we couldn't find the HTML block in the cache, so we'll have to compile it ourselves.
    // First let's get all the customer's recently purchased products.
    $products = getRecentlyPurchasedProducts($customerId);

    // Render the template with the recently purchased products fetched from the BigCommerce server.
    $htmlContent = (new Handlebars())->render(
            file_get_contents('templates/recently_purchased.html'), ['products' => $products]
    );
    $htmlContent = str_ireplace('http', 'https', $htmlContent); // Ensures we have HTTPS links, which for some reason we don't always get.
    // Save the HTML content in the cache so we don't have to reach out to BigCommece's server too often.
    $redis->set($cacheKey, json_encode(['content' => $htmlContent, 'expiresAt' => time() + $cacheLifetime]));

    return $htmlContent;
}

/**
 * Look at each of the customer's orders, and each of their order products and then pull down each product resource
 * that was purchased.
 * @param string $customerId ID of the customer that we want to retrieve the recently purchased products list for.
 * @return array<Bigcommerce\Resources\Product> An array of products from the BigCommerce API
 */
function getRecentlyPurchasedProducts($customerId) {
    $products = [];

    foreach (Bigcommerce::getOrders(['customer_id' => $customerId]) as $order) {
        foreach (Bigcommerce::getOrderProducts($order->id) as $orderProduct) {
            array_push($products, Bigcommerce::getProduct($orderProduct->product_id));
        }
    }

    return $products;
}

/**
 * Configure the static BigCommerce API client with the authorized app's auth token, the client ID from the environment
 * and the store's hash as provided.
 * @param string $storeHash Store hash to point the BigCommece API to for outgoing requests.
 */
function configureBCApi($storeHash) {
    Bigcommerce::configure(array(
        'client_id' => $configHelper->clientId(),
        'auth_token' => getAuthToken($storeHash),
        'store_hash' => $storeHash
    ));
}

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
    $expectedSignature = hash_hmac('sha256', $jsonStr, $configHelper->clientSecret(), $raw = false);
    if (!hash_equals($expectedSignature, $signature)) {
        error_log('Bad signed request from BigCommerce!');
        return null;
    }
    return $data;
}

function getUserKey($storeHash, $email) {
    return "kitty.php:$storeHash:$email";
}

$app->run();

