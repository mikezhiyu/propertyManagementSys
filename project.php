<?php

session_cache_limiter(false);
session_start();

require_once 'vendor/autoload.php';
require_once 'local.php';


/* DB::$encoding = 'utf8'; =============
  DB::$user = 'cp4776_pro-em ';
  DB::$dbName = 'cp4776_propertymanagement';
  DB::$password = "rWVaKK@0pETJ";
  DB::$port = 3306; */

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

//============================
//******* INDEX PAGE *********
$app->get('/', function() use ($app) {
    if (!$_SESSION['user']) {
        $app->render('index.html.twig');
        return;
    }
    $userId = $_SESSION['user']['id'];
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

$app->get('/index', function() use ($app) {
    $app->render('index.html.twig');
});



//============================
//******* REGISTER *********

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


//=======================
//******* Login *********

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

//=============================
//******* HOUSE LIST and search*********

$app->get(':op', function($op) use ($app) {

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
})->conditions(array('op' => '(/list|/)'
));

$app->post(':op' ,function($op) use ($app) {

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
})->conditions(array('op' => '(/|/index)'
));

$app->post(':op', function($op) use ($app) {

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
})->conditions(array('op' => '(/list|/|/index)'
));

//===================================
//******* DELETE A PROPERTY *********
$app->get('/delete/:id', function($id) use ($app) {
    if (!$_SESSION['user']) {
        $app->render('first_login.html.twig');
        return;
    }
    //please fix this part it doesnot show the image and values...
    $house = DB::queryFirstRow("SELECT * FROM houses WHERE id=%i", $id);
    //
    $image = DB::query("SELECT imagePath,imageMimeType FROM imagepaths WHERE id=%i", $id);
    $app->render("property_delete.html.twig", array('h' => $house,
        'i' => $image
    ));
});

$app->post('/delete/:id', function($id) use ($app) {
    if (!$_SESSION['user']) {
        $app->render('first_login.html.twig');
        return;
    }
    DB::delete('houses', 'id=%i', $id);
    DB::delete('imagePaths', 'houseid=%i', $id);
    $app->render('property_delete_success.html.twig');
});


$app->post('/index', function($op) use ($app) {

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

$app->post('/list', function($op) use ($app) {

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
$app->get('/delete/:id', function($id) use ($app) {
    if (!$_SESSION['user']) {
        $app->render('first_login.html.twig');
        return;
    }
    //please fix this part it doesnot show the image and values...
    $house = DB::queryFirstRow("SELECT * FROM houses WHERE id=%i", $id);
    //
    $image = DB::query("SELECT imagePath,imageMimeType FROM imagepaths WHERE id=%i", $id);
    $app->render("property_delete.html.twig", array('h' => $house,
        'i' => $image
    ));
});

$app->post('/delete/:id', function($id) use ($app) {
    if (!$_SESSION['user']) {
        $app->render('first_login.html.twig');
        return;
    }
    DB::delete('houses', 'id=%i', $id);
    DB::delete('imagePaths', 'houseid=%i', $id);
    $app->render('property_delete_success.html.twig');
});




//=====================================================
//******* UPDATE UPLOADED HOUSE Or ADD A PROPERTY *****

$app->get('/:op(/:id)', function($op, $id = 0) use ($app) {
    if (!$_SESSION['user']) {
        $app->render('first_login.html.twig');
        return;
    }
    //$userId = $_SESSION['user']['id'];
    if ($op == 'edit') {
        $properties = DB::queryFirstRow("SELECT * FROM houses WHERE id=%i", $id);
        //not working how to  update images?
        $images = DB::queryFirstRow("SELECT imagePath,imageMimeType FROM imagePaths WHERE houseId=%i", $id);
        if (!$properties) {
            echo 'Property not found';
            return;
        }
        $app->render("add_property.html.twig", array(
            'v' => $properties, 'operation' => 'Update'
        ));
    } else {
        $app->render("add_property.html.twig", array('operation' => 'Add'
        ));
    }
})->conditions(array(
    'op' => '(add|edit)',
    'id' => '[0-9]+'));

$app->post('/:op(/:id)', function($op, $id = 0) use ($app) {

    if (!$_SESSION['user']) {
        $app->render('first_login.html.twig');
        return;
    }

    $ownerId = $_SESSION['user']['id'];
    $postalcode = $app->request()->post('postCode');
    $address = $app->request()->post('address');
    $city = $app->request()->post('city');
    $phoneNumber = $app->request()->post('phoneNumber');
    $numberOfBedroom = $app->request()->post('numberOfBedroom');
    $price = $app->request()->post('price');
    $year = $app->request()->post('year');
    $propertyType = $app->request()->post('propertyType');
    $area = $app->request()->post('area');
    $status = "sold";
    $valueList = array('ownerId' => $ownerId,
        'postCode' => $postalcode, 'address' => $address,
        'city' => $city, 'phoneNumber' => $phoneNumber,
        'numberOfBedroom' => $numberOfBedroom, 'price' => $price, 'yearOfBuild' => $year,
        'propertyType' => $propertyType, 'area' => $area, 'status' => $status
    );

    // print_r($image);
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

    $image = $_FILES['image'];

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
        $app->render("add_property.html.twig", array(
            'v' => $valueList,
            "errorList" => $errorList,
            'operation' => ($op == 'edit' ? 'ADD' : 'Update')
        ));
    } else {

        $imagePath = "uploads/" . $image['name'];
        move_uploaded_file($image["tmp_name"], $imagePath);
        if ($op == 'edit') {
            // unlink('') OLD file - requires select            
            $oldImagePath = DB::queryFirstField(
                            'SELECT imagePath FROM imagepaths WHERE id=%i', $id);
            if (($oldImagePath) && file_exists($oldImagePath)) {
                unlink($oldImagePath);
            }
            DB::update('houses', $valueList);
            $houseId = DB::insertId();
            $mimeType = mime_content_type($image["tmp_name"]);
            DB::update('imagePaths', array(
                'houseId' => $houseId,
                'imagePath' => $imagePath,
                'imageMimeType' => $mimeType), "id=%i", $id);
        } else {

            DB::insert('houses', $valueList);
            $houseId = DB::insertId();
            $mimeType = mime_content_type($image["tmp_name"]);
            $imagePath = "uploads/" . $image['name'];
            move_uploaded_file($image["tmp_name"], $imagePath);
            DB::insert('imagePaths', array(
                'houseId' => $houseId,
                'imagePath' => $imagePath,
                'imageMimeType' => $mimeType
            ));
        }
        $app->render("property_add_success.html.twig", array(
            "imagePath" => $imagePath
        ));
    }
})->conditions(array(
    'op' => '(add|edit)',
    'id' => '[0-9]+'));


//========================
//******* Logout *********
$app->get('/logout', function() use ($app) {
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


$app->get('/contactus', function() use ($app) {

    $app->render("contactus.html.twig");
});



$app->run();
