<?php
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);

	echo "<h1> Testing 'MoodleApiClientTools' </h1>";

	$filename= file_exists("settings.ini.php") ? "settings.ini.php" : "settings.ini.sample.php";
	echo "Try settingsfile '$filename'.<br>\n";
	$settings=parse_ini_file($filename,true);
	
	if( !isset($settings["moodle"]["url"]) OR !isset($settings["moodle"]["token"]) ){
                echo "url or token missing";
                exit(0);
        }

	//A simple Moodle API test. Requires access to the functions 'core_webservice_get_site_info' and 'core_user_get_users_by_field'
	// 1. Try to get API-Information form moodle
	// 2. Try to get information from an user

	$loginname = isset($_GET['loginname']) ? trim($_GET['loginname']) : 'try_a_loginname';
	$url = isset($_GET['url']) ? trim($_GET['url']) : $settings["moodle"]["url"];
	$token = isset($_GET['token']) ? trim($_GET['token']) : $settings["moodle"]["token"];

	echo "
		<form action='' method='get' id='testapi'>
			URL: <input type='text' name='url' size='40' value='$url'><br>
			Token: <input type='text' name='token' size='40'   value='$token'><br>
			Search loginname: <input type='text' name='loginname' size='40'   value='$loginname'><br>
		</form>

		<button type='submit' form='testapi' value='testapi'>Test API</button><br><br>";

	(@include_once ('../../src/MoodleApiClient.php')) OR die("Lib 'MoodleApiClient.php' not found. Download file and copy it to this folder!");

	
	//Try to connect an getting data
	echo "Try connecting to '$url'.<br>\n"; 
	$MoodleApiClient = new MoodleApiClient($url, $token);
		
	//Try to get userdata
	$params= array("field"=>"username", "values"=> array( "$loginname") );
	$responce=$MoodleApiClient->sendRequest("core_user_get_users_by_field",$params);

	echo "Last API-Request url: '".str_replace($token,"(secret_token)",$MoodleApiClient->getLastUrl())."'<br>\n";
	echo "<PRE>";
		print_r($responce);
	echo "</PRE>";			
			
?>
