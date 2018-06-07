<?php

/*
	FILE:	dphoto_#config.php
	AUTHOR: didim99, 05/2018
	DESCRIPTION: Скрипт для загрузки изображений из диалогов и бесед ВКонтакте посредством VK API
*/

error_reporting(E_ALL & ~E_NOTICE);

// основные переменные
$datetime = date("Y-m-d H:i:s");
$DPHOTO['ver'] = "0.2"; // didim99 2018-06-07
$DPHOTO['rootdir'] = __DIR__;
$DPHOTO['log_dir'] = $DPHOTO['rootdir']. "/log";
$DPHOTO['log_file'] = $DPHOTO['log_dir']. "/$datetime.log";

// Конфигурация скрипта
$DPHOTO['out_dir'] = $DPHOTO['rootdir']. "/out";
$DPHOTO['log_print'] = 1; // Вывод лога на экран
$DPHOTO['query_delay'] = 200000; // микросекунды

// VK API
$DPHOTO['api_version'] = "5.78";
$DPHOTO['chat_max_id'] = 100000000;
$DPHOTO['chat_get_id'] = 2000000000;
$DPHOTO['attach_max_cnt'] = 200;

// настройки VK API
/*
  Ключ доступа пользователя, как его получить описано здесь:
  https://www.pandoge.com/stati_i_sovety/poluchenie-klyucha-dostupa-access_token-dlya-api-vkontakte
  Достаточно прав доступа (scope): photos,docs,pages,wall,groups,messages,offline
  (возможно и меньше, но с этими точно работает.)
*/
$DPHOTO['access_token'] = "";
/*
  id собеседника или беседы (параметр sel в адресной строке, для бесед - без первого имвола 'c')
  Несколько значений перечисляются через запятую.
    пример: $DPHOTO['dialogs'] = [15, 47, 175568347];
*/
$DPHOTO['dialogs'] = [];

?>
