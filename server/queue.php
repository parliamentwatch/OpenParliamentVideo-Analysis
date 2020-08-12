<?php

error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
set_time_limit(0);
date_default_timezone_set('CET');
ob_implicit_flush(true);
ob_end_flush();

include_once("config.php");

//processQueue();

function processQueue() {

	global $conf;

	include_once("getMediaInfo.php");

	$response = array('message' => 'Processing queue ...',
		'task' => 'queue',
		'status' => '',
		'progress' => 0);
	echo json_encode($response);

	$queueFiles = array_values(array_diff(scandir($conf["dir"]["input"]), array('.', '..', '.DS_Store', '_index.json', '.gitignore')));

	$error = false;

	/*
	if (!file_exists($conf["dir"]["output"]."/index_media.json")) {
		file_put_contents($conf["dir"]["output"]."/index_media.json", "{}");
	}
	*/

	foreach($queueFiles as $file) {

		//$file_content = simplexml_load_file($conf["dir"]["input"]."/".$file);
		$fileName = preg_replace('/\\.[^.\\s]{3,4}$/', '', $file);
		$file_content = file_get_contents($conf["dir"]["input"]."/".$file);

		rename($conf["dir"]["input"]."/".$file, $conf["dir"]["opendata"]."/".$file);

		ini_set('mbstring.substitute_character', 32);
  		if (mb_detect_encoding($file_content) == 'ISO-8859-1') {
  			$file_content = mb_convert_encoding($file_content, 'UTF-8', 'ISO-8859-1');
  		}
  		
  		/*
  		$file_content = convertAccentsAndSpecialToNormal($file_content);
  		$file_content_unicode = preg_replace('~\x{00a0}~siu', ' ', $file_content_unicode);
  		$file_content_unicode = preg_replace('~\x{00df}~siu', ' ', $file_content_unicode);
  		$file_content_unicode = preg_replace('~\x{00dc}~siu', ' ', $file_content_unicode);
  		$file_content_unicode = preg_replace('~\x{00f6}~siu', ' ', $file_content_unicode);
  		$file_content_unicode = preg_replace('~\x{00e4}~siu', ' ', $file_content_unicode);
  		$file_content_unicode = preg_replace('~\x{00ad}~siu', ' ', $file_content_unicode);
  		$file_content_unicode = preg_replace('~\x{0093}~siu', ' ', $file_content_unicode);
  		$file_content_unicode= mb_convert_encoding($file_content, 'UTF-8', 'ISO-8859-1'); 
		$file_content_unicode = iconv("CP1257", "UTF-8", $file_content);
		*/

		$file_content_xml = new SimpleXMLElement($file_content);

		try {
			$response = getMediaIDs($file_content_xml,$conf["sleep"]);
		} catch (Exception $e) {
			$response = array('message' => 'Error: '.$e->getMessage().', File: '.$e->getFile().', Line: '.$e->getLine().'',
				'task' => 'getmediainfo',
				'status' => 'error',
				'progress' => 0);
			echo json_encode($response);

			$error = true;
		} 
		
		if (count($response["rede_index"]) > 0) {
			
			$index = $response["rede_index"];
			file_put_contents($conf["dir"]["output"]."/index-files/".$fileName."_index.json", json_encode($index, JSON_PRETTY_PRINT));

		} else {
			//print_r($response);
			
			$res = array('message' => 'NO SPEECH FOUND in: '.$file,
				'task' => 'getmediainfo',
				'status' => 'error',
				'progress' => 0);
			echo json_encode($res);
		}

		unlink($conf["dir"]["opendata"]."/".$file);

		/*
		$index_media = file_get_contents($conf["dir"]["output"]."/index_media.json");
		$index_media_json = json_decode($index_media,true);
		*/
		
		//file_put_contents($conf["dir"]["opendata"]."/".$file."_optimised.xml", $response["xml_optimized"]);
	}

	if ($error) {
		$res = array('message' => 'Error: Indexing queue could not be completed',
			'task' => 'getmediainfo',
			'status' => 'error',
			'progress' => 0);
		echo json_encode($res);
	} else {
		$res = array('message' => 'Success: Finished indexing queue',
			'task' => 'getmediainfo',
			'status' => 'success',
			'progress' => 0);
		echo json_encode($res);
	}
	

	sleep(1);

}

function convert_to_UTF8($content) {
    if(!mb_check_encoding($content, 'UTF-8')
        OR !($content === mb_convert_encoding(mb_convert_encoding($content, 'UTF-32', 'UTF-8' ), 'UTF-8', 'UTF-32'))) {

        $content = mb_convert_encoding($content, 'UTF-8');

        if (mb_check_encoding($content, 'UTF-8')) {
            // log('Converted to UTF-8');
        } else {
            // log('Could not converted to UTF-8');
        }
    }
    return $content;
}

function encode_utf8($s) {
    $cp1252_map = array(
    "\xc2\x80" => "\xe2\x82\xac",
    "\xc2\x82" => "\xe2\x80\x9a",
    "\xc2\x83" => "\xc6\x92",
    "\xc2\x84" => "\xe2\x80\x9e",
    "\xc2\x85" => "\xe2\x80\xa6",
    "\xc2\x86" => "\xe2\x80\xa0",
    "\xc2\x87" => "\xe2\x80\xa1",
    "\xc2\x88" => "\xcb\x86",
    "\xc2\x89" => "\xe2\x80\xb0",
    "\xc2\x8a" => "\xc5\xa0",
    "\xc2\x8b" => "\xe2\x80\xb9",
    "\xc2\x8c" => "\xc5\x92",
    "\xc2\x8e" => "\xc5\xbd",
    "\xc2\x91" => "\xe2\x80\x98",
    "\xc2\x92" => "\xe2\x80\x99",
    "\xc2\x93" => "\xe2\x80\x9c",
    "\xc2\x94" => "\xe2\x80\x9d",
    "\xc2\x95" => "\xe2\x80\xa2",
    "\xc2\x96" => "\xe2\x80\x93",
    "\xc2\x97" => "\xe2\x80\x94",
    "\xc2\x98" => "\xcb\x9c",
    "\xc2\x99" => "\xe2\x84\xa2",
    "\xc2\x9a" => "\xc5\xa1",
    "\xc2\x9b" => "\xe2\x80\xba",
    "\xc2\x9c" => "\xc5\x93",
    "\xc2\x9e" => "\xc5\xbe",
    "\xc2\x9f" => "\xc5\xb8"
    );
    $s=strtr(utf8_encode($s), $cp1252_map);
    return $s;
}


?>