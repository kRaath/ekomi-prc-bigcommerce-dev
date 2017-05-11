<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Silex\Application;
use Bigcommerce\Api\Client as Bigcommerce;
use Firebase\JWT\JWT;
use Guzzle\Http\Client;
use Handlebars\Handlebars;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// Load from .env file
$dotenv = new Dotenv\Dotenv(__DIR__ . '/../../');
$dotenv->load();



$app = new Application();
$app['debug'] = true;

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
        'host' => 'localhost',
//        'dbname' => 'ekomi-prc-bigcommerce',
        'dbname' => 'ekomi_prc_bigcommerce',
//        'user' => 'root',
        'user' => 'plugindev',
//        'password' => 'raath',
        'password' => '9kl89ygfgtf',
        'charset' => 'utf8mb4',
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

    if ($id && $secret) {

        if (!$app['db']->fetchAssoc('SELECT * FROM prc_config WHERE storeHash = ? ', array($storeHash))) {
            $app['db']->insert('prc_config', $config);
        } else {
            $app['db']->update('prc_config', $config, array('storeHash' => $storeHash));
        }

        $config = $app['db']->fetchAssoc('SELECT * FROM prc_config WHERE storeHash = ? ', array($storeHash));

        $response = ['config' => $config, 'storeHash' => $storeHash, 'alert' => 'info', 'message' => 'Configuration saved successfully.'];
    } else {
        $response = ['config' => $config, 'storeHash' => $storeHash, 'alert' => 'danger', 'message' => 'Shop id or secret is empty'];
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
    $config = $app['db']->fetchAssoc('SELECT * FROM prc_config WHERE storeHash = ?', array($storeHash));
    return $app['twig']->render('configuration.twig', ['config' => $config, 'storeHash' => $storeHash]);
});

$app->get('/callback', function (Request $request) use ($app) {
    $payload = array(
        'client_id' => clientId(),
        'client_secret' => clientSecret(),
        'redirect_uri' => callbackUrl(),
        'grant_type' => 'authorization_code',
        'code' => $request->get('code'),
        'scope' => $request->get('scope'),
        'context' => $request->get('context'),
    );

    $client = new Client(bcAuthService());
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
        $store = $app['db']->fetchAssoc('SELECT * FROM store_config WHERE storeHash = ?', array($storeHash));
        if (!$store) {
            $app['db']->insert('store_config', $storeConfig);
        } else {
            $app['db']->update('store_config', $storeConfig, array('storeHash' => $storeHash));
        }

        $config = $app['db']->fetchAssoc('SELECT * FROM prc_config WHERE storeHash = ?', array($storeHash));

        return $app['twig']->render('configuration.twig', ['config' => $config, 'storeHash' => $storeHash, 'alert' => 'info', 'message' => 'Please save configuration.']);
    } else {
        return 'Something went wrong... [' . $resp->getStatusCode() . '] ' . $resp->getBody();
    }
});

$app->get('/test', function() use($app) {
//    $temp=$app['db']->fetchAll('SELECT * FROM prc_reviews');

    $storeHash = 'hehe';

    $storeConfig = array(
        'storeHash' => $storeHash,
        'accessToken' => 'wajjj',
        'userId' => 234,
        'username' => 'kraath',
        'email' => 'khadimnu',
        'installed' => 1,
    );
    $store = $app['db']->fetchAssoc('SELECT * FROM store_config WHERE storeHash = ?', array($storeHash));
    if (!$store) {
        $app['db']->insert('store_config', $storeConfig);
    } else {
        $app['db']->update('store_config', $storeConfig, array('storeHash' => $storeHash));
    }//    $temp = $app['db']->fetchAssoc('SELECT * FROM store_config WHERE storeHash = ?', array('jwage'));
//        if($temp['username']){
//        var_dump($temp['username']);die;
//        } else{
//            die('wa');
//        }
    // fetch config from DB and send as param
//	$kedy = getUserKey($data['store_hash'], $data['user']['email']);
    $config = $app['db']->fetchAssoc('SELECT * FROM prc_config WHERE storeHash = ?', array($storeHash));
    return $app['twig']->render('configuration.twig', ['config' => $config]);

    //return $app['twig']->render('configuration.twig', ['alert' => 'success', 'message' => 'Test']);
});


////////////////
$app->get('/removeUser', function (Request $request) use ($app) {
    return TRUE;
});
$app->get('/install', function (Request $request) use ($app) {
    return TRUE;
});
$app->get('/uninstall', function (Request $request) use ($app) {
     $data = verifySignedRequest($request->get('signed_payload'));
    var_dump($data);
    die('uninstall');
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
        'client_id' => clientId(),
        'auth_token' => getAuthToken($storeHash),
        'store_hash' => $storeHash
    ));
}

/**
 * @param string $storeHash store's hash that we want the access token for
 * @return string the oauth Access (aka Auth) Token to use in API requests.
 */
function getAuthToken($storeHash) {
    $redis = new Credis_Client('plugindev.coeus-solutions.de');
    $authData = json_decode($redis->get("stores/{$storeHash}/auth"));
    return $authData->access_token;
}

/**
 * @param string $jwtToken 	customer's JWT token sent from the storefront.
 * @return string customer's ID decoded and verified
 */
function getCustomerIdFromToken($jwtToken) {
    $signedData = JWT::decode($jwtToken, clientSecret(), array('HS256', 'HS384', 'HS512', 'RS256'));
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
    $expectedSignature = hash_hmac('sha256', $jsonStr, clientSecret(), $raw = false);
    if (!hash_equals($expectedSignature, $signature)) {
        error_log('Bad signed request from BigCommerce!');
        return null;
    }
    return $data;
}

/**
 * @return string Get the app's client ID from the environment vars
 */
function clientId() {
    $clientId = getenv('BC_CLIENT_ID');
    return $clientId ?: '';
}

/**
 * @return string Get the app's client secret from the environment vars
 */
function clientSecret() {
    $clientSecret = getenv('BC_CLIENT_SECRET');
    return $clientSecret ?: '';
}

/**
 * @return string Get the callback URL from the environment vars
 */
function callbackUrl() {
    $callbackUrl = getenv('BC_CALLBACK_URL');
    return $callbackUrl ?: '';
}

/**
 * @return string Get auth service URL from the environment vars
 */
function bcAuthService() {
    $bcAuthService = getenv('BC_AUTH_SERVICE');
    return $bcAuthService ?: '';
}

function getUserKey($storeHash, $email) {
    return "kitty.php:$storeHash:$email";
}

$app->run();

