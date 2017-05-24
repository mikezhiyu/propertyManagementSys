<?php

session_start();

session_start();
   session_unset();
   
   $_SESSION['FBID'] = NULL;
   $_SESSION['FULLNAME'] = NULL;
   $_SESSION['EMAIL'] =  NULL;
   header("Location: facebook.php");  
// added in v4.0.0
require_once 'autoload.php';

use Facebook\FacebookSession;
use Facebook\FacebookRedirectLoginHelper;
use Facebook\FacebookRequest;
use Facebook\FacebookResponse;
use Facebook\FacebookSDKException;
use Facebook\FacebookRequestException;
use Facebook\FacebookAuthorizationException;
use Facebook\GraphObject;
use Facebook\Entities\AccessToken;
use Facebook\HttpClients\FacebookCurlHttpClient;
use Facebook\HttpClients\FacebookHttpable;

// init app with app id and secret
FacebookSession::setDefaultApplication('315314182235679', '538ef96aa8ff3f9e1befbfc3bbdec809');
// login helper with redirect_uri
$helper = new FacebookRedirectLoginHelper('http://demos.krizna.com/1353/fbconfig.php');
try {
    $session = $helper->getSessionFromRedirect();
} catch (FacebookRequestException $ex) {
    // When Facebook returns an error
} catch (Exception $ex) {
    // When validation fails or other local issues
}
// see if we have a session
if (isset($session)) {
    // graph api request for user data
    $request = new FacebookRequest($session, 'GET', '/me');
    $response = $request->execute();
    // get response
    $graphObject = $response->getGraphObject();
    $fbid = $graphObject->getProperty('id');              // To Get Facebook ID
    $fbfullname = $graphObject->getProperty('name'); // To Get Facebook full name
    $femail = $graphObject->getProperty('email');    // To Get Facebook email ID
    /* ---- Session Variables ----- */
    $_SESSION['FBID'] = $fbid;
    $_SESSION['FULLNAME'] = $fbfullname;
    $_SESSION['EMAIL'] = $femail;
    /* ---- header location after session ---- */
    header("Location: index.php");
} else {
    $loginUrl = $helper->getLoginUrl();
    header("Location: " . $loginUrl);
}

