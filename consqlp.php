<?php

include_once 'ambient.conf';
$date_time = date("Y-m-d H:i:s"); 
	
try {
    $conn = new PDO("mysql:host=$servername;dbname=$db_database", $username, $password);
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	//$myfile = fopen("sqlerror.log", "a");
	//fwrite($myfile, $date_time . " connected\n");
}

catch(PDOException $e) {
	$myfile = fopen("sqlerror.log", "a");
	fwrite($myfile, $date_time . $e->getMessage()."\n");
    echo "Connection failed: $date_time" . $e->getMessage();
}

?>