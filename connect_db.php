<?php
$db_host = 'localhost';
$db_user = 'testclient';
$db_pass = 'testpwd';
$db_name = 'airbnb_TEST';

error_reporting(E_ALL ^ E_NOTICE);
//ini_set("magic_quotes_runtime","1");
//ini_set('magic_quotes_gpc',"1");
date_default_timezone_set('Asia/Calcutta');  // set the timezone for PHP
if (!mysql_connect($db_host,$db_user,$db_pass))
{
	echo "<br><b>Could not connect to the database: user login error</b><br>";
}

if (!mysql_select_db($db_name))
{
  echo "<br><b>Could not connect to the database: db connection error</b><br>";
}
mysql_query('set time_zone="+5:30"');  // set the timezone for MySQL
?>