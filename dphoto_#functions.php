<?php

/*
	FILE:	dphoto_#functions.php
	AUTHOR: didim99, 05/2018
	DESCRIPTION: Скрипт для загрузки изображений из диалогов и бесед ВКонтакте посредством VK API
*/

/* debug timer example

$time1 = (float) microtime(true); //debug timer start

$time2 = (float) microtime(true); //debug timer stop
$time = number_format($time2-$time1, 6);
print_pre2 ($time, "time");

RAM counter example

$mem0 = memory_get_usage(); //DEBUG: memory usage counter start

$mem1 = memory_get_usage(); //DEBUG: memory usage counter stop
//DEBUG: memory usage counter print
print_pre2 (format_bytes($mem1 - $mem0, 4), "RAM");

*/



// ######## ОБЩЕГО НАЗНАЧЕНИЯ ################################

// Переводит время из числового формата в строковый
function DPHOTO_time_convert ($input) {
	$out = "";
	$sih = 3600;
	$mih = 60;
	$rem = fmod($input, $sih);
	$time['h'] = floor($input/$sih);
	$time['m'] = floor($rem/$mih);
	$time['s'] = number_format(fmod($rem, $mih), 4);

	foreach ($time as $value) {
		if ($value < 10) $value = "0$value";
		$out .= "$value:";
	}

	$out = trim($out, ":");
	return $out;
}



// Выводит/пишет в файл отладочный лог
function set_log ($level, $msg) {
	global $DPHOTO;
	$str = date("Y-m-d H:i:s"). "  [$level]  $msg\n";
	file_put_contents($DPHOTO['log_file'], $str, FILE_APPEND);
	if ($DPHOTO['log_print']) echo $str;
}



// ######## VK API ############################################################

// Отправляет POST-запрос и получает ответ
function DPHOTO_post_req ($url, $post) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url); //урл сайта к которому обращаемся 
  curl_setopt($ch, CURLOPT_HEADER, false); //выводим заголовки
  curl_setopt($ch, CURLOPT_RETURNTRANSFER,true); //теперь curl вернет нам ответ, а не выведет
  curl_setopt($ch, CURLOPT_POST, true); //передача данных методом POST
  curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.106 Safari/537.36');
  curl_setopt($ch, CURLOPT_POSTFIELDS, $post); //тут переменные которые будут переданы методом POST
  $result = curl_exec($ch);
  curl_close($ch);
  
  return $result;
}



// Отправляет запрос VK API и получает ответ
function DPHOTO_get_api_method ($method_name, $params) {
  global $DPHOTO;

  // Сделаем проверки на токен и версию апи, если их не указали, добавим.
  if (!array_key_exists('access_token', $params) && !is_null($DPHOTO['access_token']))
    $params['access_token'] = $DPHOTO['access_token'];

  if (!array_key_exists('v', $params) && !is_null($DPHOTO['api_version']))
    $params['v'] = $DPHOTO['api_version'];
  
  // Сортируем массив по ключам
  ksort($params);
  
  // Отправим запрос
  return json_decode(DPHOTO_post_req ("https://api.vk.com/method/$method_name", $params), true);
}



// Удаляет из строки все символы, запрещенные для имени файла
function DPHOTO_filename_clear ($str) {
	return preg_replace(["`[\/:*?\"<>|$()%]`", "` `"], ["", "_"], $str);
}



// Проверяет успешность выполнения запроса к VK API
function DPHOTO_is_method_success ($response) {
	if (is_null($response['error']))
		return true;
	else {
		$response = $response['error'];
		$code = $response['error_code'];
		$msg = $response['error_msg'];
		foreach ($response['request_params'] as $param) {
			if ($param['key'] == "method") {
				$method = $param['value'];
				break;
			}
		}
		set_log ("W", "Error executing method [$method], code: $code\n  $msg");
		return false;
	}
}



// Получает имя собеседника или название беседы
function DPHOTO_get_dialog_title ($id, $is_chat) {
	$title = NULL;

	if ($is_chat) {
	$response = DPHOTO_get_api_method ("messages.getChat", ['chat_id' => $id]);
	if (DPHOTO_is_method_success ($response))
		$title = $response['response']['title'];
	} else {
		$response = DPHOTO_get_api_method ("users.get", ['user_ids' => $id]);
		if (DPHOTO_is_method_success ($response)) {
			$response = $response['response'][0];
			$title = $response['first_name']. " ". $response['last_name'];
		}
	}
	
	return $title;
}



// Находит и загружает все фотографии из укащзанного диалога/беседы
function DPHOTO_get_photos ($id) {
	global $DPHOTO;
	
	set_log ("D", "Scanning dialog: $id");
	
	// Получаем имя собеседника или название беседы
	$is_chat = (is_string($id) && strpos($id, "c") === 0);
	if ($is_chat)
		$id = (int) substr($id, 1);
	$title = DPHOTO_get_dialog_title ($id, $is_chat);
	
	if ($title) {
		set_log ("D", "Dialog name: $title");
		$title = DPHOTO_filename_clear ($title);
	} else {
		$title = "$id";
	}
	
	// Получаем вложения из диалога
	$count_curr = $DPHOTO['attach_max_cnt'];
	$found = $collected = $total = 0;
	$photo_data = [];
	
	$params['peer_id'] = $is_chat ? $id + $DPHOTO['chat_get_id'] : $id;
	$params['count'] = $DPHOTO['attach_max_cnt'];
	$params['media_type'] = "photo";
	$params['photo_sizes'] = 1;
	
	while ($count_curr == $DPHOTO['attach_max_cnt']) {
		$response = DPHOTO_get_api_method ("messages.getHistoryAttachments", $params);
		if (DPHOTO_is_method_success ($response)) {
			$response = $response['response'];
			$count_curr = count($response['items']);
			set_log("D", "Found dialog attachments: +$count_curr");
			
			// Получаем URL фото максимального размера
			foreach ($response['items'] as $item) {
				$item = $item['attachment']['photo'];
				$max_url = NULL;
				$max_size = 0;
				
				foreach ($item['sizes'] as $size_data) {
					if ($size_data['width'] > $max_size) {
						$max_size = $size_data['width'];
						$max_url = $size_data['url'];
					}
				}
				
				$photo_data[] = [
					'date' => $item['date'],
					'url'  => $max_url
				];
			}
			
			$found += $count_curr;
			usleep($DPHOTO['query_delay']);
			if ($response['next_from'])
				$params['start_from'] = $response['next_from'];
		} else {
			$count_curr = -1;
		}
	}
	
	// Загружаем фотографии
	$out_dir = $DPHOTO['out_dir']. "/$title";
	$collected = count($photo_data);
	$dates = [];
	
	if (!is_dir($out_dir)) {
		set_log("D", "Creating directory: $out_dir");
		mkdir($out_dir);
	}
	
	foreach ($photo_data as $photo) {
		if (array_key_exists($photo['date'], $dates))
			$suffix = sprintf("_%02d", $dates[$photo['date']]++);
		else {
			$dates[$photo['date']] = 1;
			$suffix = "";
		}
		
		set_log("V", "Downloading: {$photo['url']}");
		$rawdata = file_get_contents($photo['url']);
		if ($rawdata) {
			$date = date("Y-m-d_H-i-s", $photo['date']);
			$out_path = $out_dir. "/". $filename. $date. $suffix. ".jpg";
			set_log("V", "Writing file: $out_path");
			file_put_contents($out_path, $rawdata);
			$total++;
		} else {
			set_log("E", "Download error");
		}
		
		usleep($DPHOTO['query_delay']);
	}

	set_log("D", "Processing completed (found: $found/collected: $collected/downloaded: $total)");
}

?>
