<?php
define('__ROOT__',dirname(dirname( __FILE__)));
require_once(__ROOT__.'/inc/listController.php'); 
require_once(__ROOT__.'/inc/virtualdirectory.php'); 
$virtualDirectory = new VirtualDirectory();
$baseUrl = $virtualDirectory->baseurl;
$baseUrl = $virtualDirectory->upFromLevel($baseUrl,1);
ListController::LoadAll($baseUrl);
?>