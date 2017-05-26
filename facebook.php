<?php

$app->get('/login', function() use ($app) {

    $app_id = '444190482600133';
    $app_secret = '6173516210c980586572668d509c0fae';

    $fb = new \Facebook\Facebook([
        'app_id' => $app_id,
        'app_secret' => $app_secret,
        'default_graph_version' => 'v2.9',
            //'default_access_token' => '{access-token}', // optional
    ]);

    // Use one of the helper classes to get a Facebook\Authentication\AccessToken entity.
    $helper = $fb->getRedirectLoginHelper();
    //   $helper = $fb->getJavaScriptHelper();
    //   $helper = $fb->getCanvasHelper();
    //   $helper = $fb->getPageTabHelper();

    $permissions = ['email']; // Optional permissions
//    print_r('https://' . $_SERVER['SERVER_NAME'] . '/app/routes/fbcallback.php');
    $FBLoginUrl = $helper->getLoginUrl('https://' . $_SERVER['SERVER_NAME'] . '/fbcallback', $permissions);

    $app->render('login.html.twig', array(
        "FBLoginUrl" => $FBLoginUrl,
        "user" => $_SESSION['user']
    ));
});


$app->get('/fbcallback', function() use ($app) {

    $app_id = '444190482600133';
    $app_secret = '6173516210c980586572668d509c0fae';

    $fb = new \Facebook\Facebook([
        'app_id' => $app_id,
        'app_secret' => $app_secret,
        'default_graph_version' => 'v2.9',
    ]);
//    $log = new Logger('main');
    $helper = $fb->getRedirectLoginHelper();
    try {
        $accessToken = $helper->getAccessToken();
    } catch (Facebook\Exceptions\FacebookResponseException $e) {
        // When Graph returns an error
//        $log->error(sprintf("Graph returned an error: " . $e->getMessage()));
        exit;
    } catch (Facebook\Exceptions\FacebookSDKException $e) {
        // When validation fails or other local issues
//        $log->error(sprintf('Facebook SDK returned an error: ' . $e->getMessage()));
        exit;
    }

    if (!isset($accessToken)) {
        if ($helper->getError()) {
            header('HTTP/1.0 401 Unauthorized');
//            $log->error(sprintf("Error: " . $helper->getError() . "\n"));
//            $log->error(sprintf("Error Code: " . $helper->getErrorCode() . "\n"));
//            $log->error(sprintf("Error Reason: " . $helper->getErrorReason() . "\n"));
//            $log->error(sprintf("Error Description: " . $helper->getErrorDescription() . "\n"));
        } else {
            header('HTTP/1.0 400 Bad Request');
//            $log->error(sprintf('Bad request'));
        }
        exit;
    }

// Logged in
//    $log->debug(sprintf('Access Token: ' . var_dump($accessToken->getValue())));
//    echo '<h3>Access Token</h3>';
//    var_dump($accessToken->getValue());
// The OAuth 2.0 client handler helps us manage access tokens
    $oAuth2Client = $fb->getOAuth2Client();

// Get the access token metadata from /debug_token
    $tokenMetadata = $oAuth2Client->debugToken($accessToken);
//    $log->debug(sprintf('Metadata: ' . var_dump($tokenMetadata)));
//    echo '<h3>Metadata</h3>';
//    var_dump($tokenMetadata);
// Validation (these will throw FacebookSDKException's when they fail)
    $tokenMetadata->validateAppId($app_id);
// If you know the user ID this access token belongs to, you can validate it here
//$tokenMetadata->validateUserId('123');
    $tokenMetadata->validateExpiration();

    if (!$accessToken->isLongLived()) {
        // Exchanges a short-lived access token for a long-lived one
        try {
            $accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
//            $log->error(sprintf("<p>Error getting long-lived access token: " . $e->getMessage() . "</p>\n\n"));
            exit;
        }
//        $log->debug(sprintf('Long-lived: ' . var_dump($accessToken->getValue())));
    }
    try {
        // Returns a `Facebook\FacebookResponse` object
        $response = $fb->get('/me?fields=id,name', (string) $accessToken);
    } catch (Facebook\Exceptions\FacebookResponseException $e) {
//        echo 'Graph returned an error: ' . $e->getMessage();
//        $log->error(sprintf("Graph returned an error: " . $e->getMessage()));
        exit;
    } catch (Facebook\Exceptions\FacebookSDKException $e) {
//        echo 'Facebook SDK returned an error: ' . $e->getMessage();
//        $log->error(sprintf('Facebook SDK returned an error: ' . $e->getMessage()));
        exit;
    }

    $user = $response->getGraphUser();
    $_SESSION['user'] = $user;
//    $log->debug(sprintf("User %s logged in successfuly from IP %s", (string)$accessToken, $_SERVER['REMOTE_ADDR']));
    $msg = new \Plasticbrain\FlashMessages\FlashMessages();
    $msg->success('Welcome ' . $user['name'] . ', you login successfully');
    $msg->display();
    $app->render('index.html.twig', array(
        "user" => $_SESSION['user']
    ));
});

