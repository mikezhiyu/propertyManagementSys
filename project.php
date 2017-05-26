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

    $app->render("admin_menu.html.twig");
});

//============================
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
            'password' => password_hash($pass1, CRYPT_BLOWFISH),
            'name' => $lastname
        ));
        $log->debug(sprintf("User %s created", $id));
        $app->render('register_success.html.twig');
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
//
//have to ask teache why when I have log it doesnot work on the server?
$app->get('/login', function() use ($app, $log) {
    $app->render('login.html.twig');
});

$app->post('/login', function() use ($app, $log) {
//if the user allready loggedin has to logget out first then login with the other user!!!
//is it correct?
    if ($_SESSION['user']) {
        $app->render('logout.html.twig');
        return;
    }

    $email = $app->request()->post('email');
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

// $log->debug(sprintf("User failed for email %s from IP %s", $user['id'], $_SERVER['REMOTE_ADDR']));
        $app->render('login_success.html.twig');
    }
});


//=================================
//******* PROPERTY DETAILS *********
$app->get('/propertydetail/:id', function($id) use ($app) {
    $houseList = DB::queryFirstRow("SELECT * FROM houses WHERE id=%i", $id);
    $imagePath = DB::query("SELECT imagePath FROM imagepaths WHERE houseid=%i", $id);
    $owner = DB::queryFirstRow("SELECT * FROM users WHERE id=%i", $houseList['ownerId']);
    $app->render('propertydetails.html.twig', array('h' => $houseList,
        'images' => $imagePath, 'o' => $owner)
    );
});

//=================================================
//******* Users house List to Edit and Delete******
$app->get('/user/house', function() use ($app) {
    if (!$_SESSION['user']) {
        $app->render('first_login.html.twig');
        return;
    }
    $ownerId= $_SESSION['user']['id'];
        if ($_SESSION['user']['level']==0) {
        $app->render('message.html.twig',array(
        'message' => "You have not yet posted any property", 'link'=> "/house/add" ));
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

//please fix this part it doesnot show the image and values...
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
    $app->render('property_delete_success.html.twig');
});


//=====================================================
//******* UPDATE UPLOADED HOUSE Or ADD A PROPERTY *****

$app->get('/house/:op(/:id)', function($op, $id = 0) use ($app) {
    if (!$_SESSION['user']) {
        $app->render('first_login.html.twig');
        return;
    }
    $ownerId = $_SESSION['user']['id'];
    
    if ($op == 'edit') {
        $properties = DB::queryFirstRow("SELECT * FROM houses WHERE id=%i", $id);
//  $images = DB::queryFirstRow("SELECT imagePath,imageMimeType FROM imagePaths WHERE houseId=%i", $id);
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
    
})->conditions(array( 'op' => '(add|edit)','id' => '[0-9]+'));
   
    

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
            DB::update('houses', $valueList, 'Id=%i', $id);
            $_SESSION['user']['houseId'] = $id;
        } else {
            DB::insert('houses', $valueList);
            $_SESSION['user']['houseId'] = DB::insertId();
        }
        $app->render("add_property_success.html.twig");
    }
})->conditions(array('op' => '(add|edit)', 'id' => '[0-9]+'));


$app->get('/image/:add(/:id)', function($op, $id = 0) use ($app) {
    if (!$_SESSION['user']) {
        $app->render('first_login.html.twig');
        return;
    }
    $ownerId = $_SESSION['user']['id'];
    if ($op == 'edit') {
        $properties = DB::query("SELECT * FROM houses WHERE ownerId=%i", $ownerId);
        if (!$properties) {
            echo 'You have no Property in our Database';
            return;
        }
// $images = DB::query("SELECT imagePath FROM imagePaths WHERE houseId=%i", $id);
        $app->render("add_image.html.twig", array(
            'v' => $properties, 'operation' => 'Update'
        ));
    } else {
        $app->render("add_image.html.twig", array('operation' => 'Add'
        ));
    }
})->conditions(array(
    'op' => '(add|edit)', 'id' => '[0-9]+'));


$app->get('/image/edit/:id', function( $id = 0) use ($app) {

    if (!$_SESSION['user']) {
        $app->render('first_login.html.twig');
        return;
    }
    $ownerId = $_SESSION['user']['id'];
    $houseId = $_SESSION['user']['houseId'];

    $imagePath = DB::query("SELECT imagePath FROM imagepaths WHERE houseid=%i", $id);
    $app->render('edit_image.html.twig', array(
        'images' => $imagePath));
});

/*
  $errorList = array();
  if ($image['error'] != 0) {
  array_push($errorList, "Image is required to create a house");
  } else {
  $imageInfo = getimagesize($image["tmp_name"]);
  if (!$imageInfo) {
  array_push($errorList, "File does not look like an valid image");
  } else {
  // FIXME: opened a security hole here! .. must be forbidden
  if (strstr($image["name"], "..")) {
  array_push($errorList, "File name invalid");
  }
  // FIXME: only allow select extensions .jpg .gif .png, never .php
  $ext = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, array('jpg', 'jpeg', 'gif', 'png'))) {
  array_push($errorList, "File name invalid");
  }
  // FIXME: do not allow file to override an previous upload
  if (file_exists('uploads/' . $image['name'])) {
  array_push($errorList, "File name already exists. Will not override.");
  }
  }
  }
  if ($errorList) {
  $app->render("add_image.html.twig", array(
  "errorList" => $errorList, 'operation' => 'Add image'
  ));
  } else {
  $oldImagePath = DB::query('SELECT * FROM imagepaths WHERE houseId=%i', $houseId);
  //  $oldImageCounts = count($oldImagePath);
  //  $newImageCounts = count($imageList);
  //  if ($oldImageCounts >= $newImageCounts)
  $c = 0;
  foreach ($imageList as $image) {
  $imagePath = "uploads/" . $image['name'];
  $mimeType = mime_content_type($image["tmp_name"]);
  move_uploaded_file($image["tmp_name"], $imagePath);
  $houseId = $oldImagePath[$c] . ['id'];
  DB::update('imagePaths', array(
  'imagePath' => $imagePath,
  'imageMimeType' => $mimeType), "houseId=%i", $houseId);
  $c++;
  }
  }
  $app->render("edit_image.html.twig", array(
  'images' => $imagePath,
  'operation' => ($op == 'edit' ? 'add' : 'Add another image')));
  });

 */

$app->post('/image/add', function() use ($app) {
    if (!$_SESSION['user']) {
        $app->render('first_login.html.twig');
        return;
    }
    $ownerId = $_SESSION['user']['id'];
    $image = $_FILES['file'];
    $errorList = array();
    if ($image['error'] != 0) {
        array_push($errorList, "Image is required to create a house");
    } else {
        $imageInfo = getimagesize($image["tmp_name"]);
        if (!$imageInfo) {
            array_push($errorList, "File does not look like an valid image");
        } else {
// FIXME: opened a security hole here! .. must be forbidden
            if (strstr($image["name"], "..")) {
                array_push($errorList, "File name invalid");
            }
        }
// FIXME: only allow select extensions .jpg .gif .png, never .php
        $ext = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, array('jpg', 'jpeg', 'gif', 'png'))) {
            array_push($errorList, "File name invalid");
        }
// FIXME: do not allow file to override an previous upload
        if (file_exists('uploads/' . $image['name'])) {
            array_push($errorList, "File name already exists. Will not override.");
        }
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
            DB::update('houses', $valueList, 'Id=%i', $id);
            $_SESSION['user']['houseId'] = $id;
        } else {
            DB::insert('houses', $valueList);
            $_SESSION['user']['houseId'] = DB::insertId();
        }
        $_SESSION['user']['level'] = 1;
        DB::update('users', $_SESSION['user'], 'Id=%i', $_SESSION['user']['id']);
        $app->render("add_property_success.html.twig");
    }
})->conditions(array('op' => '(add|edit)', 'id' => '[0-9]+'));


$app->get('/image/:add(/:id)', function($op, $id = 0) use ($app) {
    if (!$_SESSION['user']) {
        $app->render('first_login.html.twig');
        return;
    }
    $ownerId = $_SESSION['user']['id'];
    if ($op == 'edit') {
        $properties = DB::query("SELECT * FROM houses WHERE ownerId=%i", $ownerId);
        if (!$properties) {
            echo 'You have no Property in our Database';
            return;
        }
// $images = DB::query("SELECT imagePath FROM imagePaths WHERE houseId=%i", $id);
        $app->render("add_image.html.twig", array(
            'v' => $properties, 'operation' => 'Update'
        ));
    } else {
        $app->render("add_image.html.twig", array('operation' => 'Add'
        ));
    }
})->conditions(array(
    'op' => '(add|edit)', 'id' => '[0-9]+'));


$app->get('/image/edit/:id', function( $id = 0) use ($app) {

    if (!$_SESSION['user']) {
        $app->render('first_login.html.twig');
        return;
    }
    $ownerId = $_SESSION['user']['id'];
    $houseId = $_SESSION['user']['houseId'];

    $imagePath = DB::query("SELECT imagePath FROM imagepaths WHERE houseid=%i", $id);
    $app->render('edit_image.html.twig', array(
        'images' => $imagePath));
});

/*
  $errorList = array();
  if ($image['error'] != 0) {
  array_push($errorList, "Image is required to create a house");
  } else {
  $imageInfo = getimagesize($image["tmp_name"]);
  if (!$imageInfo) {
  array_push($errorList, "File does not look like an valid image");
  } else {
  // FIXME: opened a security hole here! .. must be forbidden
  if (strstr($image["name"], "..")) {
  array_push($errorList, "File name invalid");
  }
  // FIXME: only allow select extensions .jpg .gif .png, never .php
  $ext = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, array('jpg', 'jpeg', 'gif', 'png'))) {
  array_push($errorList, "File name invalid");
  }
  // FIXME: do not allow file to override an previous upload
  if (file_exists('uploads/' . $image['name'])) {
  array_push($errorList, "File name already exists. Will not override.");
  }
  }
  }
  if ($errorList) {
  $app->render("add_image.html.twig", array(
  "errorList" => $errorList, 'operation' => 'Add image'
  ));
  } else {
  $oldImagePath = DB::query('SELECT * FROM imagepaths WHERE houseId=%i', $houseId);
  //  $oldImageCounts = count($oldImagePath);
  //  $newImageCounts = count($imageList);
  //  if ($oldImageCounts >= $newImageCounts)
  $c = 0;
  foreach ($imageList as $image) {
  $imagePath = "uploads/" . $image['name'];
  $mimeType = mime_content_type($image["tmp_name"]);
  move_uploaded_file($image["tmp_name"], $imagePath);
  $houseId = $oldImagePath[$c] . ['id'];
  DB::update('imagePaths', array(
  'imagePath' => $imagePath,
  'imageMimeType' => $mimeType), "houseId=%i", $houseId);
  $c++;
  }
  }
  $app->render("edit_image.html.twig", array(
  'images' => $imagePath,
  'operation' => ($op == 'edit' ? 'add' : 'Add another image')));
  });

 */

$app->post('/image/add', function() use ($app) {

    if (!$_SESSION['user']) {
        $app->render('first_login.html.twig');
        return;
    }
    $ownerId = $_SESSION['user']['id'];
    $houseId = $_SESSION['user']['houseId'];
    $image = $_FILES['image'];
    $errorList = array();
    if ($image['error'] != 0) {
        array_push($errorList, "Image is required to create a house");
    } else {
        $imageInfo = getimagesize($image["tmp_name"]);
        if (!$imageInfo) {
            array_push($errorList, "File does not look like an valid image");
        } else {
// FIXME: opened a security hole here! .. must be forbidden
            if (strstr($image["name"], "..")) {
                array_push($errorList, "File name invalid");
            }
// FIXME: only allow select extensions .jpg .gif .png, never .php
            $ext = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, array('jpg', 'jpeg', 'gif', 'png'))) {
                array_push($errorList, "File name invalid");
            }
// FIXME: do not allow file to override an previous upload
            if (file_exists('uploads/' . $image['name'])) {
                array_push($errorList, "File name already exists. Will not override.");
            }
        }
    }

    if ($errorList) {
        $app->render("add_image.html.twig", array(
            "errorList" => $errorList, 'operation' => 'Add image'));
    } else {
        $mimeType = mime_content_type($image["tmp_name"]);
        $imagePath = "uploads/" . $image['name'];
        move_uploaded_file($image["tmp_name"], $imagePath);
        DB::insert('imagePaths', array(
            'houseId' => $houseId,
            'imagePath' => $imagePath,
            'imageMimeType' => $mimeType
        ));
        $app->render("add_image.html.twig", array(
            'operation' => 'Add image'));
    }
});
//========================
//******* Logout *********
$app->get('/logout', function() use ($app, $log) {
    unset($_SESSION['user']);
    $app->render('logout.html.twig');
});

//========================
//******* property details *********
$app->get('/property/:id', function($id) use ($app) {
    $houseList = DB::queryFirstRow("SELECT * FROM houses WHERE id=%i", $id);
    $imagePath = DB::query("SELECT imagePath FROM imagepaths WHERE houseid=%i", $id);
    $owner = DB::queryFirstRow("SELECT * FROM users WHERE id=%i", $houseList['ownerId']);
    $app->render('propertydetails.html.twig', array('h' => $houseList,
        'images' => $imagePath, 'o' => $owner)
    );
});

//========================
//******* agents *********

$app->get('/agents', function() use ($app) {

    $app->render("agents.html.twig");
});


//******* about us *********

$app->get('/aboutus', function() use ($app) {

    $app->render("aboutus.html.twig");
});



//******* contact us *********
$app->get('/contactus', function() use ($app) {

    $app->render("contactus.html.twig");
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





//
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

            DB::delete('passresets', 'userID=%d', $user['id']);
            DB::insert('passresets', array(
                'userID' => $user['id'],
                'secretToken' => $secretToken,
                'expiryDateTime' => date("Y-m-d H:i:s", strtotime("+5 hours"))
            ));
// VERSION 2: insert-update TODO
            /* DB::insertUpdate('passresets', array(
              'userID' => $user['id'],
              'secretToken' => $secretToken,
              'expiryDateTime' => date("Y-m-d H:i:s", strtotime("+5 minutes"))
              )); */
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

$app->run();
