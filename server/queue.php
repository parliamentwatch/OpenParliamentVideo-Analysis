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

	$index = [];

	if (!file_exists($conf["dir"]["output"]."/index_media.json")) {
		file_put_contents($conf["dir"]["output"]."/index_media.json", "{}");
	}

	foreach($queueFiles as $file) {

		//$file_content = simplexml_load_file($conf["dir"]["input"]."/".$file);
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

		$response = getMediaIDs($file_content_xml,$conf["sleep"]);
		
		if (count($response["rede_index"]) > 0) {
			
			$index = array_merge($index,$response["rede_index"]);

		} else {
			//print_r($response);
			/*
			$res = array('message' => 'NO SPEECH FOUND',
				'task' => 'getmediainfo',
				'status' => 'error',
				'progress' => 0);
			echo json_encode($res);
			*/
		}

		$index_media = file_get_contents($conf["dir"]["output"]."/index_media.json");
		$index_media_json = json_decode($index_media,true);

		$index_merged = array_merge($index_media_json,$index);
		file_put_contents($conf["dir"]["output"]."/index_media.json", json_encode($index_merged, JSON_PRETTY_PRINT));
		
		//file_put_contents($conf["dir"]["opendata"]."/".$file."_optimised.xml", $response["xml_optimized"]);
	}

	/*
	if (!file_exists($conf["dir"]["output"]."/index_media.json")) {
		file_put_contents($conf["dir"]["output"]."/index_media.json", "{}");
	}
	$index_media = file_get_contents($conf["dir"]["output"]."/index_media.json");
	$index_media_json = json_decode($index_media,true);

	$index_merged = array_merge($index_media_json,$index);
	file_put_contents($conf["dir"]["output"]."/index_media.json", json_encode($index_merged, JSON_PRETTY_PRINT));
	*/

	$res = array('message' => 'Success: Finished indexing queue',
		'task' => 'getmediainfo',
		'status' => 'success',
		'progress' => 0);
	echo json_encode($res);

	sleep(1);

}


?>