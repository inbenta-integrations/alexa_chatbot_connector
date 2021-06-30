<?php

require "vendor/autoload.php";

use Inbenta\AlexaConnector\AlexaConnector;


header('Content-Type: application/json');

// Instance new Connector
$appPath = __DIR__ . '/';

$app = new AlexaConnector($appPath);
$inbentaResponse = $app->handleRequest();
if(isset($inbentaResponse["response"])){
	echo json_encode($inbentaResponse);
}
