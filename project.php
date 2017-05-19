<?php

session_cache_limiter(false);
session_start();


require_once 'vendor/autoload.php';
require_once 'local.php';


/*
  DB::$user = 'cp4776_mikez_sf';
  DB::$dbName = 'cp4776_propertymanagement';
  DB::$password = "monk6500";
  DB::$port = 3306;
 * 
  DB::$encoding = 'utf8';
  DB::$user = 'cp4776_pro-em ';
  DB::$dbName = 'cp4776_propertymanagement';
  DB::$password = "rWVaKK@0pETJ";
  DB::$port = 3306;

 */

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

$app->get('/', function() use ($app) {
    if (!$_SESSION['user']) {
        $app->render('index.html.twig');
        return;
    }
    $userId = $_SESSION['user']['id'];
    $houseList = DB::query("SELECT * FROM houses WHERE ownerId=%i", $userId);
    //print_r($todoList);
    // $app->render('index_todolist.html.twig', array('houseList' => $houseList));

    $app->render('index.html.twig');
});

$app->get('/index', function() use ($app) {


    $app->render('index.html.twig');
});

// STATE 1: First show


$app->get('/register', function() use ($app) {
    $app->render('register.html.twig');
});


// Receiving a submission
$app->post('/register', function() use ($app) {
    // extract variables
    $email = $app->request()->post('email');
    $pass1 = $app->request()->post('password1');
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

    if ($pass1 != $pass2) {
        array_push($errorList, "Passwords do not match");
    } else {
        if (strlen($pass1) < 6) {
            array_push($errorList, "Password too short, must be 6 characters or longer");
        }
        if (preg_match('/[A-Z]/', $pass1) != 1 || preg_match('/[a-z]/', $pass1) != 1 || preg_match('/[0-9]/', $pass1) != 1) {
            array_push($errorList, "Password must contain at least one lowercase, "
                    . "one uppercase letter, and a digit");
        }
    }
    //
    if ($errorList) {
        $app->render('register.html.twig', array(
            'errorList' => $errorList,
            'v' => $valueList
        ));
    } else {
        DB::insert('users', array(
            'email' => $email,
            'password' => $pass1,
            'name' => $lastname
        ));
        $app->render('register_success.html.twig');
    }
});

// AJAX: Is user with this email already registered?
$app->get('/ajax/emailused/:email', function($email) {
    $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
    //echo json_encode($user, JSON_PRETTY_PRINT);
    echo json_encode($user != null);
});


//log in

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
    //print_r($user);
    // decide what to render
    if ($error) {
        $app->render('login.html.twig', array("error" => true));
    } else {
        unset($user['password']);
        $_SESSION['user'] = $user;
        $app->render('login_success.html.twig');
    }
});

$app->get('/logout', function() use ($app) {
    unset($_SESSION['user']);
    $app->render('logout.html.twig');
});



//to test
/*
  $app->get('/session', function() {
  print_r($_SESSION);

  }); */



$app->get('/property', function() use ($app) {
    $app->render('propertydetails.html.twig');
});

$app->get('/list', function() use ($app) {
    $app->render('list_property.html.twig');
});









$app->get('/addproperty', function() use ($app) {

    $app->render("add_property.html.twig");
    //  print_r($valueList);
});


$app->post('/addproperty', function() use ($app) {
    $postalcode = $app->request()->post('postCode');
    $address = $app->request()->post('address');
    $city = $app->request()->post('city');
    $phoneNumber = $app->request()->post('phoneNumber');
    $numberOfBedroom = $app->request()->post('numberOfBedroom');
    $price = $app->request()->post('price');
    $year = $app->request()->post('year');
    $propertyType = $app->request()->post('propertyType');
    $area = $app->request()->post('area');

    $valueList = array('ownerId' => '11',
        'postCode' => $postalcode, 'address' => $address,
        'city' => $city, 'phoneNumber' => $phoneNumber,
        'numberOfBedroom' => $numberOfBedroom, 'price' => $price, 'yearOfBuild' => $year,
        'propertyType' => $propertyType, 'area' => $area, 'status' => "sold"
    );

// $image = $_FILES['image'];

    
    $image = isset($_FILES['image']) ? $_FILES['image'] : array();


//    
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
    //if (empty($numberofbedroom) ||  || $numberofbedroom > 100) {
    if (empty($numberOfBedroom) || $numberOfBedroom > 100 || $numberOfBedroom < 1) {
        array_push($errorList, "bedrooms must be between 1 and 100");
    }
    if (empty($year) || $year < 1000 || $year > 2020) {
        array_push($errorList, "Building Year must be between 1000 and 2020");
    }
    if (empty($area) || $area < 1 || $area > 1000000) {
        array_push($errorList, "area must be between 1 and 1000000");
    }

    if ($image) {
        $imageInfo = getimagesize($image["tmp_name"]);
        if (!$imageInfo) {
            array_push($errorList, "File does not look like an valid image");
        } else {
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            if ($width > 300 || $height > 300) {
                array_push($errorList, "Image must at most 300 by 300 pixels");
            }
        }
    }
    
    if ($errorList) {
        $app->render("add_property.html.twig", array(
            'v' => $valueList,
            "errorList" => $errorList));
//  'operation' => ($op == 'edit' ? 'Edit' : 'Update')
    } else {
        DB::insert('houses', $valueList);
        $imageBinaryData = file_get_contents($image['tmp_name']);
        $mimeType = mime_content_type($image['tmp_name']);
        DB::insert('imagePaths', array(
            'houseId' => "2",
            'imageData' => $imageBinaryData,
            'imageMimeType' => $mimeType
        ));  
        $app->render("property_add_success.html.twig");
    }

    /*
      array('ownerId' => '11',
      'postCode' => $postalcode, 'address' => $address,
      'city' => $city, 'phoneNumber' => $phoneNumber,
      'numberofbedroom' => $numberofbedroom, 'price' => $price, 'yearOfBuild' => $year,
      'propertyType' => $propertyType, 'area' => $area, 'status' => "sold"
      ));
      // $app->render("property_add_success.html.twig");
      } */

});



$app->run();
