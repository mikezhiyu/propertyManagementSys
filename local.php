<?php

if ($_SERVER['SERVER_NAME'] == 'localhost') {
   DB::$dbName = 'propertysalesys';
   DB::$user = 'root';
   DB::$password = '';
   DB::$host = '127.0.0.1';   // sometimes needed on Mac OSX
   DB::$port = 3333;
} else { // hosted on external server
   DB::$encoding = 'utf8';
   DB::$user = 'cp4776_pro-em';
   DB::$dbName = 'cp4776_propertymanagement';
   DB::$password = "rWVaKK@0pETJ";
   DB::$port = 3306;
}


