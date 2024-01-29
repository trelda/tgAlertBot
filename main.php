<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require realpath(dirname(__FILE__)) . '/vendor/autoload.php';
require realpath(dirname(__FILE__)) . '/connect.php';


$GLOBALS['token'] = 'botToken';
$GLOBALS['adminId'] = '0000000';

$GLOBALS['sendContact'] = new \TelegramBot\Api\Types\ReplyKeyboardMarkup (
	[
		[
			["text" => "Зарегистрироваться", "request_contact" => true]
		]
	],
	true,
	true
);

$GLOBALS['generateReport'] = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup (
	[
		[
			['text' => 'Отчеты', "web_app" => ['url' => "https://сайт_где_лежит_бот/webapp.php"]]
		]
	],
	false,
	true
);

$GLOBALS['addRequest'] = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup (
	[[
		['callback_data' => 'add_request', "text" => "Добавить новое сообщение"]
	]],
	false,
	true
);

$GLOBALS['endRequest'] = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup (
	[[
		['callback_data' => 'end_request', "text" => "Завершить"]
	]],
	false,
	true
);

function my_curl($url) {
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HEADER, false);
	curl_close($curl);
	return curl_exec($curl);
};

function addLog($text, $chatId) {
	$fp = fopen('errors.txt', 'a');
	fwrite($fp, date("Y-m-d H:i:s") . ' ' . $text . ' ' . $chatId . PHP_EOL);
	fclose($fp);
}

function checkUser($userId) {
    global $mysqli;
    $userId = (is_numeric($userId)) ? $userId : null;
    $query = "SELECT * FROM alert_users WHERE chatId='" . $userId . "' LIMIT 1";
    $result = $mysqli->query($query);
    $row = $result->num_rows;
    if ($row==0) {
		return false;
	} else {
		return true;
    }
};

function addUser($userId, $userName, $userFirstName, $bot) {
    global $mysqli;
    $userId = (is_numeric($userId)) ? $userId : null;
    $nameTest = '/^[A-Za-z0-9_-]+$/i';
    $userName = (preg_match($nameTest, $userName)) ? $userName : null ;
    $query ="INSERT INTO alert_users (`chatId`,`userName`,`type`,`date`, `userFirstName`) VALUES ('" . $userId . "', '". $userName . "', '0', '". date("Y-m-d H:i:s") . "', '" . $userFirstName . "')";
    if ($result = $mysqli->query($query)) {
		return true;
    } else {
		return false;
    }
};

function checkAuthorize($userId) {
    $userId = (is_numeric($userId)) ? $userId : null;
    global $mysqli;
    $query = "SELECT type FROM alert_users WHERE chatId='" . $userId . "'";
    $result = $mysqli->query($query);
    $data = $result->fetch_assoc();
    if ($data['type'] < 1) {
		return false;
    } else {
		return true;
    }
};

function authorizeUser($userId, $phone, $bot) {
	if (checkUser($userId)) {
		global $mysqli;
		$userId = (is_numeric($userId)) ? $userId : '' ;
		$query = "UPDATE alert_users SET `type`='1', `contact`='" . $phone . "' WHERE chatId='" . $userId . "'";
		$result = $mysqli->query($query);
		if (!$result) {
			return false;
		} else {
			$reply_markup = array('remove_keyboard' => true);
			$reply = "Авторизация успешна!";
			$url = "https://api.telegram.org/bot" . $GLOBALS['token'] . "/sendmessage?chat_id=" . $userId . "&text=" . urlencode($reply) . "&reply_markup=" . urlencode(json_encode($reply_markup));
			my_curl($url);
			$bot->sendMessage($userId, "Далее точно следуйте инструкции. Нажмите кнопку «Добавить сообщение» 👇", false, null, null, $GLOBALS['addRequest']);
			return true;
		}
	}
};

function setUdata($chatId, $data = array()) {
    global $mysqli;
    $data = json_encode($data, JSON_UNESCAPED_UNICODE);
    $query = "UPDATE alert_users SET mode='" . $data . "' WHERE chatId = '" . $chatId . "'";
    $result = $mysqli->query($query);
};

function getUdata($chatId) {
    global $mysqli;
    $res = array();
    $query = "SELECT * FROM alert_users WHERE chatId = '" . $chatId . "'";
    $result = $mysqli->query($query);
    $arr = mysqli_fetch_assoc($result);
    if(isset($arr['mode'])) {
		$res = json_decode($arr['mode'], true);
    }
    return $res;
};

function clearUdata($chatId) {
    global $mysqli;
    $query = "UPDATE alert_users SET mode='' WHERE chatId = '" . $chatId . "'";
    $mysqli->query($query);
};

function messageToModer($bot, $chatId, $data) {
	if (checkAuthorize($chatId)) {
		global $mysqli;
		$data = getUdata($chatId);
		if ((isset($data['step1'])) && ((isset($data['step2'])) || (isset($data['location']))) && (isset($data['step3']))) {
			addLog('correct data to moder: ', serialize($data));
			$query = "SELECT chatId FROM alert_users WHERE type='2'";
			$result = $mysqli->query($query);
			addLog('author: ', $chatId);
			foreach ($result as $key) {
				addLog('moderator: ', $key['chatId']);
				$queryStep = "SELECT userFirstName, contact FROM alert_users WHERE chatId='" . $chatId . "'";
				$resultStep = $mysqli->query($queryStep)->fetch_row();
				if (!isset($data['location'])) {
					$message = "Сообщение пользователя " . $resultStep[0] . " с телефоном " . $resultStep[1] . " : адрес - " . $data['step2'] . ", описание - " . $data['step3'] . ", файл: ";
					addLog('message: ', $message);
					addlog('data: ', serialize($data));
					try {
						$bot->sendMessage($key['chatId'], $message);
					} catch (Exception $e) {
						addLog('Error send to moderator: ', serialize($e));
					}
				} else {
					$message = "Сообщение пользователя " . $resultStep[0] . " с телефоном " . $resultStep[1] . " : описание - " . $data['step3'] . ", адрес:";
					$bot->sendMessage($key['chatId'], $message);
					$url="https://api.telegram.org/bot" . $GLOBALS['token'] . "/sendLocation?chat_id=" . $key['chatId'] . "&latitude=" . $data['latitude'] . "&longitude=" . $data['longitude']; 
					addLog('data:', serialize($data));
					addLog('geoposition: ', $url);
					my_curl($url);
				}
				if (isset($data['step4'])) {
					if ($data['step5']=='photo') {
						$url = "https://api.telegram.org/bot" . $GLOBALS['token'] . "/sendPhoto?chat_id=" . $key['chatId'] . "&photo=" . $data['step4'];
						addLog('photo url: ', $url);
						my_curl($url);
					} elseif (($data['step5']=='video') || ($data['step5']=='document')) {
						$url = "https://api.telegram.org/bot" . $GLOBALS['token'] . "/sendDocument?chat_id=" . $key['chatId'] . "&document=" . $data['step4'];
						addLog('video url: ', $url);
						my_curl($url);
					} elseif ($data['step5']=='text') {
						$bot->sendMessage($key['chatId'], $data['step4']);
					}
					addlog('file: ', $data['step4']);
				}
				$bot->sendMessage($chatId, "Генерация отчетов:", false, null, null, $GLOBALS['generateReport']);
			}
			createReport($chatId);
			clearUdata($chatId);
			return true;
		} else {
			addLog('incorrect data to moder: ', serialize($data));
			return false;
		}
	}
};

function addModerator($adminId, $newModerId) {
	global $mysqli;
	addLog('adminId:', $adminId);
	addLog('newModerId:', $newModerId);
	$adminId = (is_numeric($adminId)) ? $adminId : null;
	$newModerId = (is_numeric($newModerId)) ? $newModerId : null;
	if ((checkAuthorize($adminId)) && (checkAuthorize($newModerId)) && ($adminId == $GLOBALS['adminId'])) {
		$query = "UPDATE alert_users SET type='2' WHERE chatId='" . $newModerId . "'";
	    if ($result = $mysqli->query($query)) {
			return true;
	    } else {
			return false;
    	}
	}
};

function checkStartCounter($userId) {
	global $mysqli;
	$userId = (is_numeric($userId)) ? $userId : null;
	$query = "SELECT startCounter FROM alert_users WHERE chatId='" . $userId . "'";
	$result = $mysqli->query($query)->fetch_row();
	addLog('Counter: ', $result[0]);
	if ($result[0] <= 10) {
		$queryIncreaseCounter = "UPDATE alert_users SET startCounter='" . ($result[0]+1) . "' WHERE chatId='" . $userId . "'";
		addLog('increase: ', $queryIncreaseCounter);
		$resultIncrease = $mysqli->query($queryIncreaseCounter);
		return true;
	} else {
		addLog('count', 'max');
		return false;
	}
};

function createReport($userId) {
	global $mysqli;
	$userId = (is_numeric($userId)) ? $userId : null;
	if (checkAuthorize($userId)) {
		$data = getUdata($userId);
		addLog($userId, 'create report');
		$query = "SELECT * FROM alert_users WHERE chatId='" . $userId . "'";
		$result = $mysqli->query($query)->fetch_assoc();
		addLog('chatId:', $result['chatId']);
		addLog('userName:', $result['userName']);
		addLog('tel:', $result['contact']);
		addLog('firstName:', $result['userFirstName']);
		if ($data['location']==='yes') {
			$url = "https://yandex.ru/maps/?l=sat%2Cskl&ll=" . $data['longitude'] . "%2C" . $data['latitude'] . "&mode=whatshere&whatshere%5Bpoint%5D=" . $data['longitude'] . "%2C".$data['latitude'] . "&whatshere%5Bzoom%5D=16&z=16";
			addLog('location:', $url);
		} else {
			addLog('location:', $data['step2']);
		}
		$location = ($url != '') ? $url : $data['step2'];
		addLog('description:', $data['step3']);
		$filetype = '';
		if (isset($data['step4'])) {
			if ($data['step5']=='photo') {
				addLog('photo:', $data['step4']);
			} elseif (($data['step5']=='video') || ($data['step5']=='document')) {
				addLog('video/doc:', $data['step4']);
			} elseif ($data['step5']=='text') {
				addLog('text:', $data['step4']);
			}
		}
		addLog('date: ',date("Y-m-d H:i:s"));
		$query = "INSERT INTO alert_report (`chatId`, `userName`, `telephone`, `firstName`, `location`, `description`, `fileType`, `file`, `date`) VALUES 
		('" . $userId . "', '" . $result['userName'] . "', '" . $result['contact'] . "', '" . $result['userFirstName'] . "', '" . $location . "', '" . $data['step3'] . "', '" . $data['step5'] . "','" . $data['step4'] . "', '" . date("Y-m-d H:i:s") . "')";
		addLog('query:', $query);
		$result = $mysqli->query($query);
	}
};

try {
    $bot = new \TelegramBot\Api\Client($GLOBALS['token']);

    $bot->command('start', function ($message) use ($bot) {
		global $mysqli;
		$userId = $message->getChat()->getId();
		if (checkStartCounter($userId)) {
			$userName = $message->getChat()->getUsername();
			$userFirstName = mysqli_real_escape_string($mysqli, $message->getChat()->getFirstName());
			if (!checkUser($userId)) {
				$bot->sendMessage($userId, "Нажмите, пожалуйста, кнопку 'Зарегистрироваться'. Без регистрации мы не сможем прочитать ваши сообщения", false, null, null, $GLOBALS['sendContact']);
				addUser($userId, $userName, $userFirstName, $bot);
			} else {
				clearUdata($userId);
				if (checkAuthorize($userId)) {
					$bot->sendMessage($userId, "Далее точно следуйте инструкции. Нажмите кнопку «Добавить сообщение» 👇", false, null, null, $GLOBALS['addRequest']);
				} else {
					$bot->sendMessage($userId, "Требуется авторизация. Нажмите кнопку 'Зарегистрироваться'", false, null, null, $GLOBALS['sendContact']);
				}
			}
		}
    });

$bot->command('createreport', function ($message) use ($bot) {
	$userId = $message->getChat()->getId();
	createReport($userId);
});
	
$bot->command('moderator', function ($message) use ($bot) {
	$adminId = $message->getChat()->getId();
	if ((checkAuthorize($adminId)) && ($adminId == $GLOBALS['adminId'])) {
		$newModerId = $message->getText();
		$newModerId = intval(preg_replace('/[^0-9]+/', '', $newModerId), 10);
		addLog($adminId, $newModerId);
		addLog('to moder','start');
		addModerator($adminId, $newModerId);
	}
});

	$bot->callbackQuery(function ($callbackQuery) use ($bot) {
		$message = $callbackQuery->getMessage();
		addLog('callback', serialize($message));
		$chatId = $message->getChat()->getId();
		if (checkAuthorize($chatId)) {
			$mId = $message->getMessageId();
			$params = $callbackQuery->getData();
			$data = getUdata($chatId);
			switch ($params) {
				case 'add_request':
					$text = "Новое сообщение";
					$url ="https://api.telegram.org/bot" . $GLOBALS['token'] . "/answerCallbackQuery?callback_query_id=add_request&text=" . $text;
					my_curl($url);
					addLog('createRequest', $chatId);
					clearUdata($chatId);
					if (!isset($data['step1'])) {
						$bot->deleteMessage($chatId, $mId);
						$uData = array('step1' => 'started');
						setUdata($chatId, $uData);
						$bot->sendMessage($chatId, "Где вы заметили подозрительный предмет или событие? Напишите адрес или поставьте геометку (если на вашем телефоне включена геолокация):");
					}
					break;
				case 'end_request':
					$text = "Завершение..";
					$url ="https://api.telegram.org/bot" . $GLOBALS['token'] . "/answerCallbackQuery?callback_query_id=add_request&text=" . $text;
					my_curl($url);
					$bot->deleteMessage($chatId, $mId);
					if (messageToModer($bot, $chatId, $data)) {
						$bot->sendMessage($chatId, "Сообщение отправлено на проверку.");
						$bot->sendMessage($chatId, "Желаете ли сообщить еще о чем-либо?", false, null, null, $GLOBALS['addRequest']);
					}
				break;
			}
		}
	});

	$bot->on(function ($update) use ($bot) {
		global $mysqli;
		$message = $update->getMessage();
		$chatId = $message->getChat()->getId();
		addLog('update message', $chatId);
		if (checkUser($chatId)) {
			addLog('user checked', $chatId);
			$postText=$message->getText();
			$userName = $message->getChat()->getUsername();
			$userFirstName = mysqli_real_escape_string($mysqli,$message->getChat()->getFirstName());
			$data = getUdata($chatId);
			if (($message->getContact()) && (checkAuthorize($chatId) == false)) {
				addLog('sending contact: ', serialize($message));
				$vcard = ($message->getContact()->getVCard()) ? $message->getContact()->getVCard() : null;
				addlog('vcard: ', serialize($vcard));
				if ($vcard) {
					$bot->sendMessage($chatId, "Нажмите кнопку 'Зарегистрироваться', контакт отправлять не нужно.");
				} else {
					$contact = $message->getContact();
					addLog('contact from message', serialize($contact));
					addLog('userId from contact: ', $contact->getUserId());
					addLog('real userId: ', $chatId);
					$userPhone = $message->getContact()->getPhoneNumber();
					addLog('userPhone: ', $userPhone);
					$userPhone = str_replace("+", "", $userPhone);
					$phoneTest = '/^[0-9]+$/i';
					if ($chatId==$contact->getUserId()) {
						addLog('ids the same', ' all correct');
						if (preg_match($phoneTest, $userPhone)) {
							addLog($userPhone, $chatId);
							if (strpos($userPhone, '79') === 0) {
								addLog('russia', $chatId);
								authorizeUser($chatId, $userPhone, $bot);
							} else {
								addLog('not_russia', $chatId);
							}
						}
					}
				}
			}
			if (checkAuthorize($chatId)) {
				addLog('user authorized', $chatId);
				if ((isset($data['step1'])) && (!isset($data['step2'])) && ($data['step5']!='text') && (!isset($data['location']))) {
					if (($message->getVideo()==null) && ($message->getPhoto()==null) && ($message->getDocument()==null)) {
						if ($message->getLocation() && (!isset($data['location']))) {
							addLog('sending location..', '');
							$uData = array('location' => 'yes', 'longitude'=>$message->getLocation()->getLongitude(), 'latitude' => $message->getLocation()->getLatitude());
							$data = array_merge($uData, $data);
							setUdata($chatId, $data);
							$bot->sendMessage($chatId, "Опишите, что вы заметили: ");
						} else {
							addLog('step2', $chatId);
							$uData = array('step2' => mysqli_real_escape_string($mysqli, $postText));
							$data = array_merge($uData, $data);
							setUdata($chatId, $data);
							$bot->sendMessage($chatId, "Опишите, что вы заметили: ");
						}
					} else {
						$bot->sendMessage($chatId, "Где вы заметили подозрительный предмет или событие? Напишите адрес или поставьте геометку (если на вашем телефоне включена геолокация). На данном этапе медиафайл прикреплять не нужно.");
					}
				} elseif (((isset($data['step2'])) || (isset($data['location']))) && (!isset($data['step3'])) && ($data['step5']!='text')) {
					if (($message->getVideo()==null) && ($message->getPhoto()==null) && ($message->getDocument()==null)) {
						addLog('step3', $chatId);
						$uData = array('step3' => mysqli_real_escape_string($mysqli, $postText));
						$data = array_merge($uData, $data);
						setUdata($chatId, $data);
						$bot->sendMessage($chatId, "Приложите одно фото или одно видео с места. Если хотите отправить сообщение без фото или видео, нажмите «Завершить»", false, null, null, $GLOBALS['endRequest']);
					} else {
						$bot->sendMessage($chatId, "Опишите, что вы заметили? На данном этапе медиафайл прикреплять не нужно.");
					}
				} elseif ((isset($data['step3'])) && ((!isset($data['step4'])) || ($data['step5']=='text'))) {
					$sub = time();
					if ($message->getDocument()) {
						addLog('Document: ', serialize($message->getDocument()));
						addLog('mime: ', serialize($message->getDocument()->getMimeType()));
						$accept = array('image/png', 'image/jpeg', 'image/gif', 'image/bmp', 'image/tiff', 'image/tiff', 'video/quicktime', 'video/mp4', 'video/mpeg', 'video/mp4', 'video/webm', 'video/3gpp', 'video/3gpp2');
						if (in_array(strtolower($message->getDocument()->getMimeType()), $accept)) {
							addLog('Document',$chatId);
							$file = $message->getDocument();
							$file_id = $file->getFileId();
							$uData = array('step4' => $file_id);
							$data = array_merge($uData, $data);
							$uData = array('step5' => 'document');
							$data = array_merge($uData, $data);
							setUdata($chatId, $data);
							if (messageToModer($bot, $chatId, $data)) {
								$bot->sendMessage($chatId, "Сообщение отправлено на проверку.");
								$bot->sendMessage($chatId, "Желаете ли сообщить еще о чем-либо?", false, null, null, $GLOBALS['addRequest']);
							}
						}
					} elseif ($message->getPhoto()) {
						addLog('Photo',$chatId);
						$fileC = [];
						$fileC = $message->getPhoto();
						$file_id = $fileC[count($fileC)-1]->getFileId();
						$uData = array('step4' => $file_id);
						$data = array_merge($uData, $data);
						$uData = array('step5' => 'photo');
						$data = array_merge($uData, $data);
						setUdata($chatId, $data);
						if (messageToModer($bot, $chatId, $data)) {
							$bot->sendMessage($chatId, "Сообщение отправлено на проверку.");
							$bot->sendMessage($chatId, "Желаете ли сообщить еще о чем-либо?", false, null, null, $GLOBALS['addRequest']);
						}
					} elseif ($message->getVideo()) {
						addLog('Video', $chatId);
						$file = $message->getVideo();
						$file_id = $file->getFileId();
						$uData = array('step4' => $file_id);
						$data = array_merge($uData, $data);
						$uData = array('step5' => 'video');
						$data = array_merge($uData, $data);
						setUdata($chatId, $data);
						if (messageToModer($bot, $chatId, $data)) {
							$bot->sendMessage($chatId, "Сообщение отправлено на проверку.");
							$bot->sendMessage($chatId, "Желаете ли сообщить еще о чем-либо?", false, null, null, $GLOBALS['addRequest']);
						}
					} else {
						$uData = array('step5' => 'text');
						$data['step4'] = $data['step4'].' '.mysqli_real_escape_string($mysqli, $postText);
						$data = array_merge($uData, $data);
						setUdata($chatId, $data);
						$bot->sendMessage($chatId, "Приложите одно фото или одно видео с места. Если хотите отправить сообщение без фото или видео, нажмите «Завершить»", false, null, null, $GLOBALS['endRequest']);
					}
				} else {
					/**/
				}
			}
		}
	}, function() {
		return true;
		}
	);

	$bot->run();
}
catch (\TelegramBot\Api\Exception $e) {
    file_put_contents('errors.txt', sprintf("[TelegramAPI]\t[%s]\t%s\n", date('Y-m-d H:i:s'), $e->getMessage()), FILE_APPEND);
    return;
}
?>