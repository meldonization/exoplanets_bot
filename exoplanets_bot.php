<?php

define('BOT_TOKEN', 'XXXXXXX:kjasdnvkjasndakdsjnaskjl');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');

function apiRequestWebhook($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  $parameters["method"] = $method;

  header("Content-Type: application/json");
  echo json_encode($parameters);
  return true;
}

function exec_curl_request($handle) {
  $response = curl_exec($handle);

  if ($response === false) {
    $errno = curl_errno($handle);
    $error = curl_error($handle);
    error_log("Curl returned error $errno: $error\n");
    curl_close($handle);
    return false;
  }

  $http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
  curl_close($handle);

  if ($http_code >= 500) {
    // do not wat to DDOS server if something goes wrong
    sleep(10);
    return false;
  } else if ($http_code != 200) {
    $response = json_decode($response, true);
    error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
    if ($http_code == 401) {
      throw new Exception('Invalid access token provided');
    }
    return false;
  } else {
    $response = json_decode($response, true);
    if (isset($response['description'])) {
      error_log("Request was successfull: {$response['description']}\n");
    }
    $response = $response['result'];
  }

  return $response;
}

function apiRequest($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  foreach ($parameters as $key => &$val) {
    // encoding to JSON array parameters, for example reply_markup
    if (!is_numeric($val) && !is_string($val)) {
      $val = json_encode($val);
    }
  }
  $url = API_URL.$method.'?'.http_build_query($parameters);

  $handle = curl_init($url);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);

  return exec_curl_request($handle);
}

function apiRequestJson($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  $parameters["method"] = $method;

  $handle = curl_init(API_URL);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);
  curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
  curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

  return exec_curl_request($handle);
}

function processMessage($message) {
  // process incoming message
  
  $message_id = $message['message_id'];
  $chat_id = $message['chat']['id'];
  if (isset($message['text'])) {
    // incoming text message
    $text = $message['text'];

    if (strpos($text, "/start") === 0) {
      apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Welcome 欢迎来到系外行星检索库', 'reply_markup' => array(
        'keyboard' => array(array('Hello', '/help', 'WASP-47')),
        'one_time_keyboard' => true,
        'resize_keyboard' => true)));
    } else if ($text === "Hello" || $text === "Hi") {
      apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'Hello, Earthling!'));
    } else if (strpos($text, "/stop") === 0) {
      // stop now
    } else if (strpos($text, "Hello") === 0 || strpos($text, "Hi ") === 0) {
      apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'Hi, Terran!'));
    } else if (strpos($text, "/help") === 0 || strpos($text, "/h") === 0) {
      apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'Type /help for this help info. Or simply enter the name of the exoplanet! 😘 '));
    } else {
      $xml=simplexml_load_file("./open_exoplanet_catalogue/systems/".$text.".xml") or die("Error: No such planet");
      $text1Send = sprintf("%'.-15s", 'Name: ').sprintf("%'.15s", $text)."\n".sprintf("%'.-15s",'Mstar [Msun]: ').sprintf("%'.15s", $xml->star->mass)."\n".sprintf("%'.-15s",'Rstar [Rsun]: ').sprintf("%'.15s", $xml->star->radius)."\n".sprintf("%'.-15s", 'Vmag: ').sprintf("%'.15s", $xml->star->magV)."\n".sprintf("%'.-15s", '[Fe/H]: ').sprintf("%'.15s", $xml->star->metallicity)."\n".sprintf("%'.-15s", 'SpecType: ').sprintf("%'.15s", $xml->star->spectraltype)."\n".sprintf("%'.-15s", 'Teff [K]: ').sprintf("%'.15s", $xml->star->temperature)."\n".sprintf("%'.-15s", 'RA [h m s]: ').sprintf("%'.15s", $xml->rightascension)."\n".sprintf("%'.-15s", 'Dec [d m s] : ').sprintf("%'.15s", $xml->declination)."\n".sprintf("%'.-15s", 'Dist [pc]: ').sprintf("%'.15s", $xml->distance)."\n";
      foreach ($xml->star->planet as $planet) {
        $text1Send = $text1Send.sprintf("%'-30s", '-')."\n".sprintf("%'.-15s", 'Planet: ').sprintf("%'.15s", $planet->name)."\n".sprintf("%'.-15s", 'Mp [Mjup]: ').sprintf("%'.15s", $planet->mass)."\n".sprintf("%'.-15s", 'Rp [Rjup]: ').sprintf("%'.15s", $planet->radius)."\n".sprintf("%'.-15s", 'Per [day]: ').sprintf("%'.15s", $planet->period)."\n".sprintf("%'.-15s", 'A [au]: ').sprintf("%'.15s", $planet->semimajoraxis)."\n".sprintf("%'.-15s", 'ecc: ').sprintf("%'.15s", $planet->eccentricity)."\n".sprintf("%'.-15s", 'DiscMeth: ').sprintf("%'.15s", $planet->discoverymethod)."\n";
      }
      apiRequest("sendMessage", array('chat_id' => $chat_id, 'parse_mode' => 'HTML', "text" => "<pre>".$text1Send."</pre>"));
      //apiRequestWebhook("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "text" => $text1Send));
      $text2Send = str_replace(' ', '%20', $text);
      //apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'http://openexoplanetcatalogue.com/planet/'.$text2Send.'%20b'));
      apiRequestWebhook("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "text" => 'http://openexoplanetcatalogue.com/planet/'.$text2Send.'%20b'));
    }
  } else {
    apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'Sorry, I only understand Gaian Symbols. 😂 '));
  }
}


define('WEBHOOK_URL', 'https://your_ip/exoplanets_bot.php');

if (php_sapi_name() == 'cli') {
  // if run from console, set or delete webhook
  apiRequest('setWebhook', array('url' => isset($argv[1]) && $argv[1] == 'delete' ? '' : WEBHOOK_URL));
  exit;
}


$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
  // receive wrong update, must not happen
  exit;
}

if (isset($update["message"])) {
  processMessage($update["message"]);
}
