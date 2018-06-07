#! /usr/bin/php
<?php

/*
	FILE:	dphoto_main.php
	AUTHOR: didim99, 05/2018
	DESCRIPTION: Скрипт для загрузки изображений из диалогов и бесед ВКонтакте посредством VK API
*/

require_once __DIR__. "/dphoto_#config.php";
require_once __DIR__. "/dphoto_#functions.php";
$time_start = (float) microtime(true); //debug timer start

if (!is_dir($DPHOTO['log_dir']) && !mkdir($DPHOTO['log_dir']))
	die("ERROR: Incorrect log directory path");

set_log ("D", "VK dialog photos downloader ver.{$DPHOTO['ver']}");
set_log ("D", "Starting...");

if ($DPHOTO['out_dir'] == "")
  set_log ("E", "Output directory not defined");
elseif (!is_dir($DPHOTO['out_dir']) && !mkdir($DPHOTO['out_dir']))
	set_log ("E", "Incorrect output directory path");
else {
	foreach ($DPHOTO['dialogs'] as $id)
		DPHOTO_get_photos($id);
}

$time_end = (float) microtime(true); //debug timer stop
$time = DPHOTO_time_convert ($time_end - $time_start);
set_log ("D", "Executing completed in: $time");

?>
