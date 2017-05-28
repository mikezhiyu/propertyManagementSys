<?php

session_cache_limiter(false);
session_start();

require_once 'vendor/autoload.php';
require_once 'local.php';

//require_once 'facebook.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// create a log channel
$log = new Logger('main');
$log->pushHandler(new StreamHandler('logs/everything.log', Logger::DEBUG));
$log->pushHandler(new StreamHandler('logs/errors.log', Logger::ERROR));


DB::$error_handler = 'sql_error_handler';
DB::$nonsql_error_handler = 'nonsql_error_handler';

function nonsql_error_handler($params) {
    global $app, $log;
    $log->error("Database error: " . $params['error']);
    http_response_code(500);
    $app->render('error_internal.html.twig');
    die;
}

function sql_error_handler($params) {
    global $app, $log;
    $log->error("SQL error: " . $params['error']);
    $log->error(" in query: " . $params['query']);
    http_response_code(500);
    $app->render('error_internal.html.twig');
    die; // don't want to keep going if a query broke
}

// Slim creation and setup
$app = new \Slim\Slim(array(
    'view' => new \Slim\Views\Twig()
        ));

$view = $app->view();
$view->parserOptions = array(
    'debug' => true,
    'cache' => dirname(__FILE__) . '/cache'
);
$view->setTemplatesDirectory(dirname(__FILE__) . '/templates');

if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = array();
}
$twig = $app->view()->getEnvironment();
$twig->addGlobal('user', $_SESSION['user']);

////============================
//******* Admin PAGE *********
$app->get('/admin', function() use ($app) {

    $app->render("admin_index.html.twig");
});


// Admin_User_List 

$app->get('/admin/userlist', function() use ($app) {
    $userList = DB::query("SELECT * FROM users");
    $app->render("admin_userlist.html.twig", array(
        'userList' => $userList
    ));
});

//amin_user _Delete
$app->get('/admin/userdelete/:id', function($id) use ($app) {
    $users = DB::queryFirstRow('SELECT * FROM users WHERE id=%i', $id);
    $app->render('admin_user_delete.html.twig', array(
        'u' => $users
    ));
});

$app->post('/admin/userdelete/:id', function($id) use ($app) {
    DB::delete('users', 'id=%i', $id);

    $message['link'] = "";
    $message['title'] = "Admin delete successful";
    $message['message'] = "";
    $app->render('message.html.twig', $message);

});

//admin_user_Add_modify

$app->get('/admin/user/:op(/:id)', function($op, $id = 0) use ($app) {
    /* FOR PROJECTS WITH MANY ACCESS LEVELS
      if (($_SESSION['user']) || ($_SESSION['level'] != 'admin')) {
      $app->render('forbidden.html.twig');
      return;
      } */
    if ($op == 'edit') {
        $users = DB::queryFirstRow("SELECT * FROM users WHERE id=%i", $id);
        if (!$users) {
            echo 'Users not found';
            return;
        }
        $app->render("admin_user_add.html.twig", array(
            'v' => $users, 'operation' => 'Update'
        ));
    } else {
        $app->render("admin_user_add.html.twig", array('operation' => 'Add'
        ));
    }
})->conditions(array(
    'op' => '(add|edit)',
    'id' => '[0-9]+'));

$app->post('/admin/user/:op(/:id)', function($op, $id = 0) use ($app) {
    $email = $app->request()->post('email');
    $pass1 = $app->request()->post('password');
    $pass2 = $app->request()->post('password2');
    $firstname = $app->request()->post('firstname');
    $lastname = $app->request()->post('lastname');
    $level = $app->request()->post('level');

// list of values to retain after a failed submission
    $valueList = array(
        'email' => $email,
        'firstname' => $firstname,
        'lastname' => $lastname
    );
// check for errors and collect error messages
    $errorList = array();
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === FALSE) {
        array_push($errorList, "Email is invalid");
    } else {
        $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
        if ($user) {
            array_push($errorList, "Email already in use");
        }
    }
    if (strlen($firstname) < 2 || strlen($firstname) > 50) {
        array_push($errorList, "First Name too short or empty, must be 2 characters or longer");
    }
    if (strlen($lastname) < 2 || strlen($lastname) > 50) {
        array_push($errorList, "Last Name too short or empty, must be 2 characters or longer");
    }

    /* if ($pass1 != $pass2) {
      array_push($errorList, "Passwords do not match");
      } else {
      if (strlen($pass1) < 6) {
      array_push($errorList, "Password too short, must be 6 characters or longer");
      }
      if (preg_match('/[A-Z]/', $pass1) != 1 || preg_match('/[a-z]/', $pass1) != 1 || preg_match('/[0-9]/', $pass1) != 1) {
      array_push($errorList, "Password must contain at least one lowercase, "
      . "one uppercase letter, and a digit");
      }
      } */

//
    if ($errorList) {
        $app->render("admin_user_add.html.twig", array(
            'v' => $valueList,
            "errorList" => $errorList,
            'operation' => ($op == 'edit' ? 'Add' : 'Update')
        ));
    } else {
        if ($op == 'edit') {
            DB::update('users', array(
                "email" => $email,
                "password" => $pass1,
                "name" => $firstname,
                "lastName" => $lastName,
                "level" => $level
                    ), "id=%i", $id);
        } else {
            DB::insert('users', array(
                "email" => $email,
                "password" => $pass1,
                "name" => $firstname,
                "lastName" => $lastName,
                "level" => $level
            ));
        }
        $message['link'] = "";
        $message['title'] = "Admin user add successful";
        $message['message'] = "";
        $app->render('message.html.twig', $message);
    }
})->conditions(array(
    'op' => '(add|edit)',
    'id' => '[0-9]+'));

//admin Message List

$app->get('/admin/msglist', function() use ($app) {
    $msgList = DB::query("SELECT * FROM contactUs");
    $app->render("admin_msglist.html.twig", array(
        'msgList' => $msgList
    ));
});

//amin_user _Delete
$app->get('/admin/msg/:id', function($id) use ($app) {
    $msg = DB::queryFirstRow('SELECT * FROM contactUs WHERE id=%i', $id);
    $app->render('admin_msg_delete.html.twig', array(
        'm' => $msg
    ));
});

$app->post('/admin/msg/:id', function($id) use ($app) {
    DB::delete('users', 'id=%i', $id);
    $app->render('admin_msg_success.html.twig');
});





//============================
>>>>>>> 09b9aee4d2ae85a479ca0b0ed7a9f45b01126401
//******* INDEX PAGE *********
$app->get('/', function() use ($app) {
    $app->render("index.html.twig");
});

$app->get('/index', function() use ($app) {
    $app->render('index.html.twig');
});

$app->post(':op', function($op) use ($app) {

    $search = $app->request()->post('search1');
    $numberOfBedroom = $app->request()->post('numberOfBedroom1');
    $price = $app->request()->post('price1');
    $propertyType = $app->request()->post('propertyType1');

// search function of fields
    $where = new WhereClause('and');
    if ($numberOfBedroom != "Bedrooms") {
        if (strpos($numberOfBedroom, "more")) {
            $where->add('numberOfBedroom>=%i', 4);
        } else {
            $where->add('numberOfBedroom=%i', substr($numberOfBedroom, 0, 1));
        }
    }
    if ($propertyType != "Type") {
        $where->add('propertyType=%s', $propertyType);
    }

    if ($price != "Price") {
        if (strpos($price, "less")) {
            $where->add('price<%s', substr($price, 1, 6));
        } else if (strpos($price, "above")) {
            $where->add('price>%s', substr($price, 1, 6));
        } else {
            $where->add('price between %s and %s', substr($price, 1, 6), substr($price, 11, 6));
        }
    }
    $houseList = DB::query("SELECT * FROM houses WHERE %l", $where);
    $HouseListWithImage = array();
    if ($search) {
//$ci = 0;
        foreach ($houseList as $h) {
            $search = strtolower($search);
            $h_lower = array_map('strtolower', $h);
            $bool = FALSE;
            foreach ($h_lower as $hl) {
                if (stripos($hl, $search) !== false) {
                    $bool = TRUE;
                }
            }
            if ($bool) {
                $houseId = $h['id'];
                $path = DB::queryFirstRow("SELECT imagepath FROM imagepaths WHERE houseId=%i", $houseId);
                $h['imagePath'] = $path['imagepath'];
                array_push($HouseListWithImage, $h);
            }
        }
    } else {
        foreach ($houseList as $h) {
            $houseId = $h['id'];
            $path = DB::queryFirstRow("SELECT imagepath FROM imagepaths WHERE houseId=%i", $houseId);
            $h['imagePath'] = $path['imagepath'];
            array_push($HouseListWithImage, $h);
        }
    }
    $app->render("list_property.html.twig", array(
        'houseList' => $HouseListWithImage
    ));
})->conditions(array(
    'op' => '(/|/index)'));
//============================
//******* REGISTER *********

$app->get('/register', function() use ($app, $log) {
    $app->render('register.html.twig');
});
// Receiving a submission
$app->post('/register', function() use ($app, $log) {
// extract variables
    $email = $app->request()->post('email');
    $pass1 = $app->request()->post('password');
    $pass2 = $app->request()->post('password2');
    $firstname = $app->request()->post('firstname');
    $lastname = $app->request()->post('lastname');
// list of values to retain after a failed submission
    $valueList = array(
        'email' => $email,
        'firstname' => $firstname,
        'lastname' => $lastname
    );
// check for errors and collect error messages
    $errorList = array();
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === FALSE) {
        array_push($errorList, "Email is invalid");
    } else {
        $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
        if ($user) {
            array_push($errorList, "Email already in use");
        }
    }
    if (strlen($firstname) < 2 || strlen($firstname) > 50 || empty($firstname)) {
        array_push($errorList, "First Name too short or empty, must be 2 characters or longer");
    }
    if (strlen($lastname) < 2 || strlen($lastname) > 50 || empty($lastname)) {
        array_push($errorList, "Last Name too short or empty, must be 2 characters or longer");
    }

    $msg = verifyPassword($pass1);
    if ($msg !== TRUE) {
        array_push($errorList, $msg);
    } else if ($pass1 != $pass2) {
        array_push($errorList, "Passwords don't match");
    }
    //
    //
    if ($errorList) {
        $app->render('register.html.twig', array(
            'errorList' => $errorList,
            'v' => $valueList
        ));
    } else {
        DB::insert('users', array(
            'email' => $email,
            'password' => password_hash($pass1, CRYPT_BLOWFISH),
            'name' => $lastname
        ));
        $id = DB::insertId();
        $log->debug(sprintf("User %s created", $id));
        $message['link'] = "/login";
        $message['title'] = "Registration";
        $message['message'] = "you registered successfully, now you can login";
        $message['head'] = '<script type="text/javascript">
        window.setTimeout(function () {window.location.href = /' / login / '; }, 5000);
            </script>';
        $app->render('message.html.twig', $message);
        //  $app->render('register_success.html.twig');
    }
});
// AJAX: Is user with this email already registered?
$app->get('/ajax/emailused/:email', function($email) {
    $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
//echo json_encode($user, JSON_PRETTY_PRINT);
    echo json_encode($user != null);
});

//=======================
//******* Login *********

$app->get('/login_FB', function() use ($app, $log) {

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



$app->post('/login_FB', function() use ($app, $log) {


    /* $email = $app->request()->post('email');
      $pass = $app->request()->post('password');
      // verification
      $error = false;
      $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
      if (!$user) {
      $log->debug(sprintf("User failed for email %s from IP %s", $email, $_SERVER['REMOTE_ADDR']));
      $error = true;
      } else {
      if ($user['password'] != $pass) {
      $log->debug(sprintf("User failed for email %s from IP %s", $email, $_SERVER['REMOTE_ADDR']));
      $error = true;
      }
      }
      if ($error) {
      $log->debug(sprintf("User failed for email %s from IP %s", $email, $_SERVER['REMOTE_ADDR']));
      $app->render('login.html.twig', array("error" => true));

      //
      } else {
      unset($user['password']);
      $_SESSION['user'] = $user;

      $log->debug(sprintf("User failed for email %s from IP %s", $user['id'], $_SERVER['REMOTE_ADDR']));

      $app->render('login_success.html.twig');
      } */

    $email = $app->request->post('email');
    $pass = $app->request->post('password');
    $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
    if (!$user) {
        $log->debug(sprintf("User failed for email %s from IP %s", $email, $_SERVER['REMOTE_ADDR']));
        $app->render('login.html.twig', array('loginFailed' => TRUE));
    } else {

        // password MUST be compared in PHP because SQL is case-insenstive
        //if ($user['password'] == hash('sha256', $pass)) {
        if (password_verify($pass, $user['password'])) {
            // LOGIN successful
            unset($user['password']);
            $_SESSION['user'] = $user;
            $log->debug(sprintf("User %s logged in successfuly from IP %s", $user['id'], $_SERVER['REMOTE_ADDR']));
            $app->render('login_success.html.twig');
        } else {

            $log->debug(sprintf("User failed for email %s from IP %s", $email, $_SERVER['REMOTE_ADDR']));
            $app->render('login.html.twig', array('loginFailed' => TRUE));
        }
    }
});


//=================================
//******* PROPERTY DETAILS *********
$app->get('/property/:id', function($id) use ($app) {
    $houseList = DB::queryFirstRow("SELECT * FROM houses WHERE id=%i", $id);
    $imagePath = DB::query("SELECT imagePath FROM imagepaths WHERE houseid=%i", $id);
    $owner = DB::queryFirstRow("SELECT * FROM users WHERE id=%i", $houseList['ownerId']);
    $address = $houseList['postCode'];
    $coordinates = file_get_contents('http://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&sensor=true');
    $coordinates = json_decode($coordinates);
    $lat = $coordinates->results[0]->geometry->location->lat;
    $lng = $coordinates->results[0]->geometry->location->lng;
    $app->render('propertydetails.html.twig', array('h' => $houseList,
        'images' => $imagePath, 'o' => $owner, 'lat' => $lat, 'lng' => $lng))
    ;
});

//========================
//******* Logout *********
$app->get('/logout', function() use ($app, $log) {
    unset($_SESSION['user']);
    $app->render('logout.html.twig');
});

//=================================================
//******* Users house List to Edit and Delete******
$app->get('/user/house', function() use ($app) {
    if (!$_SESSION['user']) {
        $app->render('first_login.html.twig');
        return;
    }

    $ownerId = $_SESSION['user']['id'];
    if ($_SESSION['user']['level'] == 0) {
        $message['link'] = "/house/add";
        $message['title'] = "Notice";
        $message['message'] = "You have not yet posted any property";
        $app->render('message.html.twig', $message);
        return;
    }

    if ($_SESSION['user']['level'] == 1) {
        $userHouseList = DB::query("SELECT * FROM houses where ownerId=%i", $ownerId);
    }
    if ($_SESSION['user']['level'] == 2) {
        $userHouseList = DB::query("SELECT * FROM houses");
    }

    $HouseListWithImage = array();
    foreach ($userHouseList as $h) {
        $houseId = $h['id'];
        $path = DB::queryFirstRow("SELECT imagepath FROM imagepaths WHERE houseId=%i", $houseId);
        $h['imagePath'] = $path['imagepath'];
        array_push($HouseListWithImage, $h);
    }
    $app->render("user_list_property.html.twig", array(
        'houseList' => $HouseListWithImage
    ));
});


//=============================
//******* HOUSE LIST & SEARCH*********

$app->get('/house/list', function() use ($app) {
    $houseList = DB::query("SELECT * FROM houses");
    $HouseListWithImage = array();
    foreach ($houseList as $h) {
        $houseId = $h['id'];
        $path = DB::queryFirstRow("SELECT imagepath FROM imagepaths WHERE houseId=%i", $houseId);
        $h['imagePath'] = $path['imagepath'];
        array_push($HouseListWithImage, $h);
    }
    $app->render("list_property.html.twig", array(
        'houseList' => $HouseListWithImage
    ));
});


$app->post('/house/list', function() use ($app) {
    $search = $app->request()->post('search');
    $numberOfBedroom = $app->request()->post('numberOfBedroom');
    $price = $app->request()->post('price');
    $propertyType = $app->request()->post('propertyType');
// search function of fields
    $where = new WhereClause('and');
    if ($numberOfBedroom != "Bedrooms") {
        if (strpos($numberOfBedroom, "more")) {
            $where->add('numberOfBedroom>=%i', 4);
        } else {
            $where->add('numberOfBedroom=%i', substr($numberOfBedroom, 0, 1));
        }
    }
    if ($propertyType != "Type") {
        $where->add('propertyType=%s', $propertyType);
    }
    if ($price != "Price") {
        if (strpos($price, "less")) {
            $where->add('price<%s', substr($price, 1, 6));
        } else if (strpos($price, "above")) {
            $where->add('price>%s', substr($price, 1, 6));
        } else {
            $where->add('price between %s and %s', substr($price, 1, 6), substr($price, 11, 6));
        }
    }
    $houseList = DB::query("SELECT * FROM houses WHERE %l", $where);
    $HouseListWithImage = array();
    if ($search) {
//$ci = 0;
        foreach ($houseList as $h) {
            $search = strtolower($search);
            $h_lower = array_map('strtolower', $h);
            $bool = FALSE;
            foreach ($h_lower as $hl) {
                if (stripos($hl, $search) !== false) {
                    $bool = TRUE;
                }
            }
            if ($bool) {
                $houseId = $h['id'];
                $path = DB::queryFirstRow("SELECT imagepath FROM imagepaths WHERE houseId=%i", $houseId);
                $h['imagePath'] = $path['imagepath'];
                array_push($HouseListWithImage, $h);
            }
        }
    } else {
        foreach ($houseList as $h) {
            $houseId = $h['id'];
            $path = DB::queryFirstRow("SELECT imagepath FROM imagepaths WHERE houseId=%i", $houseId);
            $h['imagePath'] = $path['imagepath'];
            array_push($HouseListWithImage, $h);
        }
    }
    $app->render("list_property.html.twig", array(
        'houseList' => $HouseListWithImage
    ));
});

//===================================
//******* DELETE A PROPERTY *********
$app->get('/house/delete/:id', function($id) use ($app) {
    if (!$_SESSION['user']) {
        $app->render('first_login.html.twig');
        return;
    }
    $house = DB::queryFirstRow("SELECT * FROM houses WHERE id=%i", $id);
//
    $image = DB::queryFirstRow("SELECT imagePath,imageMimeType FROM imagepaths WHERE houseId=%i", $id);

    $app->render("property_delete.html.twig", array('h' => $house,
        'i' => $image
    ));

    if (($_SESSION['user']['level']) < 1) {
        $houseList = DB::query("SELECT * FROM houses");
        $HouseListWithImage = array();
        foreach ($houseList as $h) {
            $houseId = $h['id'];
            $path = DB::queryFirstRow("SELECT imagepath FROM imagepaths WHERE houseId=%i", $houseId);
            $h['imagePath'] = $path['imagepath'];
            array_push($HouseListWithImage, $h);
        }
        $app->render("list_property.html.twig", array(
            'houseList' => $HouseListWithImage
        ));
    } else {

        $house = DB::queryFirstRow("SELECT * FROM houses WHERE id=%i", $id);
        $image = DB::queryFirstRow("SELECT imagePath,imageMimeType FROM imagepaths WHERE houseId=%i", $id);

        $app->render("property_delete.html.twig", array('h' => $house,
            'i' => $image
        ));
    }
});

$app->post('/house/delete/:id', function($id = 0) use ($app) {
    if (!$_SESSION['user']) {
        $app->render('first_login.html.twig');
        return;
    }
    $oldImagePathList = DB::query("SELECT imagepath FROM imagepaths WHERE houseId=%i", $id);

    foreach ($oldImagePathList as $oldImagePath) {
        $path = $oldImagePath['imagepath'];
        if (($path) && file_exists($path)) {
            unlink($path);
        }
    }
    DB::delete('imagePaths', 'houseid=%i', $id);
    DB::delete('houses', 'id=%i', $id);
    $message['message'] = "deleted you may continue";
    $message['title'] = "Product deletion successful";
    $message['link'] = "/house/list";
    $app->render('message.html.twig', $message);
});


//=====================================================
//*******  ADD or UPDATE A PROPERTY  *****


$app->get('/house/:op(/:id)', function($op, $id = 0) use ($app) {
    $ownerId = $_SESSION['user']['id'];
    if ($op == 'edit') {
        $_SESSION['user']['houseId'] = $id;
        $properties = DB::queryFirstRow("SELECT * FROM houses WHERE id=%i", $id);
        if (!$properties) {
            echo 'Property not found';
            return;
        }
        $properties['name'] = $_SESSION['user']['name'];
        $app->render("add_property.html.twig", array(
            'v' => $properties, 'operation' => 'Update'
        ));
        return;
    } else {
        $app->render("add_property.html.twig", array('v' => $_SESSION['user'], 'operation' => 'Add'
        ));
    }
})->conditions(array('op' => '(add|edit)', 'id' => '[0-9]+'));

$app->post('/house/:op(/:id)', function($op, $id = 0) use ($app) {
    if (!$_SESSION['user']) {
        $app->render('first_login.html.twig');
        return;
    }
    $ownerId = $_SESSION['user']['id'];
    $owner = $_SESSION['user']['name'];
    $postalcode = $app->request()->post('postCode');
    $address = $app->request()->post('address');
    $city = $app->request()->post('city');
    $phoneNumber = $app->request()->post('phoneNumber');
    $numberOfBedroom = $app->request()->post('numberOfBedroom');
    $price = $app->request()->post('price');
    $year = $app->request()->post('year');
    $propertyType = $app->request()->post('propertyType');
    $area = $app->request()->post('area');
    $status = $app->request()->post('status');
    $description = $app->request()->post('description');
    $valueList = array(
        'ownerId' => $ownerId,
        'postCode' => $postalcode, 'address' => $address, 'city' => $city,
        'phoneNumber' => $phoneNumber, 'numberOfBedroom' => $numberOfBedroom,
        'price' => $price, 'yearOfBuild' => $year,
        'propertyType' => $propertyType, 'area' => $area,
        'status' => $status, 'description' => $description
    );

    $errorList = array();
    if (strlen($address) < 2 || strlen($address) > 300) {
        array_push($errorList, "Address must be 2-300 characters long");
    }
    $expression = '/^([a-zA-Z]\d[a-zA-Z])\ {0,1}(\d[a-zA-Z]\d)$/';
    $valid = (bool) preg_match($expression, $postalcode);
    if (!$valid) {
        array_push($errorList, "postal code is invalid!");
    }
    if (strlen($city) < 2 || strlen($city) > 300) {
        array_push($errorList, "Address must be 2-300 characters long");
    }
    $valid = (bool) preg_match("/^[0-9]{3}-[0-9]{3}-[0-9]{4}$/", $phoneNumber);
    if (!$valid) {
        array_push($errorList, "phone number is invalid!");
    }
    if (empty($price) || $price < 0 || $price > 9999999) {
        array_push($errorList, "Price must be between 0 and 9999999");
    }
    if (empty($numberOfBedroom) || $numberOfBedroom > 100 || $numberOfBedroom < 1) {
        array_push($errorList, "bedrooms must be between 1 and 100");
    }
    if (empty($year) || $year < 1000 || $year > 2020) {
        array_push($errorList, "Building Year must be between 1000 and 2020");
    }
    if (empty($area) || $area < 1 || $area > 1000000) {
        array_push($errorList, "area must be between 1 and 1000000");
    }
    if ($errorList) {
        $app->render("add_property.html.twig", array(
            'v' => $valueList,
            "errorList" => $errorList,
            'operation' => ($op == 'edit' ? 'Add' : 'Update'),
            'v' => $_SESSION['user']
        ));
    } else {
        if ($op == 'edit') {
            DB::update('houses', $valueList, 'id=%i', $id);
            $_SESSION['user']['houseId'] = $id;
        } else {
            DB::insert('houses', $valueList);
            $_SESSION['user']['houseId'] = DB::insertId();
        }
        if ($_SESSION['user']['level'] < 1) {
            $_SESSION['user']['level'] = 1;
        }
        DB::query("update users set level=1 where id=%i", $_SESSION['user']['id']);
        $message['title'] = "Property Added";
        $message['link'] = "/image/add";
        $message['message'] = "add image now";

        $app->render('message.html.twig', $message);
    }
})->conditions(array('op' => '(add|edit)', 'id' => '[0-9]+'));

$app->get('/image/:op(/:id)', function($op, $id = 0) use ($app) {

            if ($op == 'add') {
                $imagePathList = DB::query("SELECT imagePath, id FROM imagepaths WHERE houseid=%i", $_SESSION['user']['houseId']);
                $app->render("edit_image.html.twig", array('images' => $imagePathList, 'operation' => 'Add new image'));
                return;
            }
            if ($op == 'delete') {
                $path = DB::queryFirstField("SELECT imagePath FROM imagepaths WHERE id=%i", $id);
                DB::delete('imagepaths', "id=%i", $id);
                if (file_exists($path)) {
                    unlink($path);
                }
                $imagePathList = DB::query("SELECT imagePath, id FROM imagepaths WHERE houseid=%i", $_SESSION['user']['houseId']);
                $app->render('edit_image.html.twig', array(
                    'images' => $imagePathList, 'operation' => 'Add new image'));
                return;
            }
            if ($op == 'update') {
                $imagePathList = DB::query("SELECT imagePath, id FROM imagepaths WHERE id=%i", $id);
                $app->render("edit_image.html.twig", array('images' => $imagePathList, 'operation' => 'Update image'));
                return;
            }
            $_SESSION['user']['houseId'] = $id;
            $imagePathList = DB::query("SELECT imagePath, id FROM imagepaths WHERE houseid=%i", $id);
            $app->render('edit_image.html.twig', array(
                'images' => $imagePathList, 'operation' => 'Add new image'));
        })
        ->conditions(array('op' => '(add|delete|update|edit)', 'id' => '[0-9]+'));

$app->post('/image/:op(/:id)', function($op, $id = 0) use ($app) {

    $ownerId = $_SESSION['user']['id'];
    $houseId = $_SESSION['user']['houseId'];
    $image = $_FILES['file'];
    $errorList = array();

    if ($image['error']) {
        array_push($errorList, "Image is required to create a house");
    } else {
        $imageInfo = getimagesize($image["tmp_name"]);
        if (!$imageInfo) {
            array_push($errorList, "File does not look like an valid image");
        } else {
            if (strstr($image["name"], "..")) {
                array_push($errorList, "File name invalid");
            }
            $ext = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, array('jpg', 'jpeg', 'gif', 'png'))) {
                array_push($errorList, "File name invalid");
            }
            if (file_exists('uploads/' . $image['name'])) {
                array_push($errorList, "File name already exists. Will not override.");
            }
        }
    }
    if ($errorList) {
        $app->render("edit_image.html.twig", array(
            "errorList" => $errorList, 'operation' => 'Add image'));
    } else {
        $mimeType = mime_content_type($image["tmp_name"]);
        $imagePath = "uploads/" . $image['name'];
        move_uploaded_file($image["tmp_name"], $imagePath);
        if ($op == 'add') {
            DB::insert('imagePaths', array('houseId' => $houseId,
                'imagePath' => $imagePath, 'imageMimeType' => $mimeType));
        }
        if ($op == 'update') {
            $path = DB::queryFirstField("SELECT imagePath FROM imagepaths WHERE id=%i", $id);
            DB::update('imagePaths', array('imagePath' => $imagePath,
                'imageMimeType' => $mimeType), "id=%i", $id);
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $imagePathList = DB::query("SELECT imagePath, id FROM imagepaths WHERE houseid=%i", $_SESSION['user']['houseId']);
        $app->render("edit_image.html.twig", array(
            'operation' => 'Add another image', 'images' => $imagePathList));
    }
})->conditions(array('op' => '(add|update|edit)', 'id' => '[0-9]+'));

//========================
//******* agents *********

$app->get('/agents', function() use ($app) {

    $app->render("agents.html.twig");
});


//******* about us *********

$app->get('/aboutus', function() use ($app) {

    $app->render("aboutus.html.twig");
});

$app->get('/contactus', function() use ($app) {
    $address = '21 275 Lakeshore Road
Sainte-Anne-de-Bellevue, Québec
H9X 3L9 Canada';
    $coordinates = file_get_contents('http://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&sensor=true');
    $coordinates = json_decode($coordinates);
    $lat = $coordinates->results[0]->geometry->location->lat;
    $lng = $coordinates->results[0]->geometry->location->lng;
    $app->render("/contactus.html.twig", array("lat" => $lat, "lng" => $lng));
});

$app->post('/contactus', function() use ($app) {

    if (!$_SESSION['user']) {
        $app->render('first_login.html.twig');
        return;
    }
// extract variables
    $fullName = $app->request()->post('fullName');
    $email = $app->request()->post('email');
    $phoneNumber = $app->request()->post('phoneNumber');
    $message = $app->request()->post('message');
    $userId = $_SESSION['user']['id'];

// list of values to retain after a failed submission
    $valueList = array(
        'fullName' => $fullName,
        'email' => $email,
        'phoneNumber' => $phoneNumber,
        'message' => $message
    );
// check for errors and collect error messages
    $errorList = array();
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === FALSE) {
        array_push($errorList, "Email is invalid");
    } else {
        $user = DB::queryFirstRow("SELECT * FROM contactUs WHERE email=%s", $email);
        if ($user) {
            array_push($errorList, "Email already in use");
        }
    }
    if (strlen($fullName) < 2 || strlen($fullName) > 150 || empty($fullName)) {
        array_push($errorList, "FullName Name too short or empty, must be 2 characters or longer");
    }
//only first 3 charachter are shown up in the database
    $valid = (bool) preg_match("/^[0-9]{3}-[0-9]{3}-[0-9]{4}$/", $phoneNumber);
    if (!$valid) {
        array_push($errorList, "phone number is invalid  => 000-000-0000!");
    }

    if (strlen($message) < 2 || strlen($message) > 1000 || empty($message)) {
        array_push($errorList, "message too short or empty, must be 2 characters or longer");
    }


    if ($errorList) {
        $app->render('contactus.html.twig', array(
            'errorList' => $errorList,
            'v' => $valueList
        ));
    } else {
        DB::insert('contactUs', array(
            'fullName' => $fullName,
            'email' => $email,
            'phoneNumber' => $phoneNumber,
            'message' => $message,
            'userId' => $userId
        ));

        $app->render('contactus_success.html.twig');
    }
});

//================================
//******* Password Reset *********
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

$app->map('/passreset', function () use ($app, $log) {
// Alternative to cron-scheduled cleanup
    if (rand(1, 1000) == 111) {
// TODO: do the cleanup 1 in 1000 accessed to /passreset URL
    }
    if ($app->request()->isGet()) {
        $app->render('passreset.html.twig');
    } else {
        $email = $app->request()->post('email');
        $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
        if ($user) {
            $app->render('passreset_success.html.twig');
            $secretToken = generateRandomString(50);
// VERSION 1: delete and insert

            /* DB::delete('passresets', 'userID=%d', $user['id']);
              DB::insert('passresets', array(
              'userID' => $user['id'],
              'secretToken' => $secretToken,
              'expiryDateTime' => date("Y-m-d H:i:s", strtotime("+5 hours"))
              )); */
// VERSION 2: insert-update TODO
            DB::insertUpdate('passresets', array(
                'userID' => $user['id'],
                'secretToken' => $secretToken,
                'expiryDateTime' => date("Y-m-d H:i:s", strtotime("+5 minutes"))
            ));
// email user
            $url = 'http://' . $_SERVER['SERVER_NAME'] . '/passreset/' . $secretToken;
            $html = $app->view()->render('email_passreset.html.twig', array(
                'name' => $user['name'],
                'url' => $url
            ));
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: Noreply <noreply@ipd9.info>\r\n";
            $headers .= "To: " . htmlentities($user['name']) . " <" . $email . ">\r\n";

            mail($email, "Password reset from E&M Real State", $html, $headers);
        } else {
            $app->render('passreset.html.twig', array('error' => TRUE));
        }
    }
})->via('GET', 'POST');

function debug_sql_handler($params) {
    global $log;

    $log->debug("SQL Command: " . $params['query']);
}

function verifyPassword($pass1) {
    if (!preg_match('/[0-9;\'".,<>`~|!@#$%^&*()_+=-]/', $pass1) || (!preg_match('/[a-z]/', $pass1)) || (!preg_match('/[A-Z]/', $pass1)) || (strlen($pass1) < 8)) {
        return "Password must be at least 8 characters " .
                "long, contain at least one upper case, one lower case, " .
                " one digit or special character";
    }
    return TRUE;
}

$app->map('/passreset/:secretToken', function($secretToken) use ($app) {
    $row = DB::queryFirstRow("SELECT * FROM passresets WHERE secretToken=%s", $secretToken);
    if (!$row) {
        $app->render('passreset_notfound_expired.html.twig');
        return;
    }
    if (strtotime($row['expiryDateTime']) < time()) {
        $app->render('passreset_notfound_expired.html.twig');
        return;
    }
//
    if ($app->request()->isGet()) {
        $app->render('passreset_form.html.twig');
    } else {
        $pass1 = $app->request()->post('password');
        $pass2 = $app->request()->post('pass2');
// TODO: verify password quality and that pass1 matches pass2
        $errorList = array();


        $msg = verifyPassword($pass1);
        if ($msg !== TRUE) {
            array_push($errorList, $msg);
        } else if ($pass1 != $pass2) {
            array_push($errorList, "Passwords don't match");
        }
//
        if ($errorList) {
            $app->render('passreset_form.html.twig', array(
                'errorList' => $errorList
            ));
        } else {
// success - reset the password
            DB::debugMode('debug_sql_handler');
            DB::update('users', array(
//mr mike this part cannot update the current password????!!!!
                'password' => password_hash($pass1, CRYPT_BLOWFISH)
                    ), "id=%d", $row['userID']);
            DB::delete('passresets', 'secretToken=%s', $secretToken);
            $app->render('passreset_form_success.html.twig');
        }
    }
})->via('GET', 'POST');

$app->get('/login', function() use ($app) {
    $app->render('login.html.twig');
});

$app->post('/login', function() use ($app) {
    $email = $app->request()->post('email');
    $pass = $app->request()->post('password');
// verification    
    $error = false;
    $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
    if (!$user) {
        $error = true;
    } else {
        if ($user['password'] != $pass) {
            $error = true;
        }
    }
    if ($error) {
        $app->render('login.html.twig', array("error" => true));
    } else {
        unset($user['password']);
        $_SESSION['user'] = $user;
        $app->render('login_success.html.twig');
    }
});





$app->run();
