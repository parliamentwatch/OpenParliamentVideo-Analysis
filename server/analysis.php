<?php

error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

set_time_limit(0);
ini_set('memory_limit', '500M');
date_default_timezone_set('CET');
ob_implicit_flush(true);
ob_end_flush();
//disable_ob();


/**
 * @param $XMLFilePath
 * @param $xPathSelector
 * @return mixed
 */
function forceAlignXMLData($XMLRede, $XMLAll, $sleep=1) {

	global $conf;



	sleep($sleep);

	$xmlDataRede = simplexml_load_string($XMLRede);
	$xmlDataAll = $XMLAll;


	$wahlperiode = sprintf('%02d',(int) $xmlDataAll->xpath('//kopfdaten//wahlperiode')[0]);
	$sitzungsnummer = sprintf('%03d',(int) $xmlDataAll->xpath('//kopfdaten//sitzungsnr')[0]);
	$outputFilePath = $wahlperiode.$sitzungsnummer;


	$currentOutputDir = $conf["dir"]["output"]."/".$wahlperiode;
	if (!is_dir($currentOutputDir)) {
		mkdir($currentOutputDir);
	}
	$currentOutputDir = $conf["dir"]["output"]."/".$wahlperiode."/".$sitzungsnummer;
	if (!is_dir($currentOutputDir)) {
		mkdir($currentOutputDir);
	}

	$rID = 0;

	if (!empty($xmlDataRede)) {


		$sID = 0;

		if ($xmlDataRede->getName() == 'rede') {
			$fileNameSuffix = '-Rede-'.mb_ereg_replace("([^\w\d\-_~,;\[\]\(\).])", '-', $xmlDataRede['id']);
		} else {
			$fileNameSuffix = '';
		}

		$fileNameSuffix = mb_ereg_replace("([\.]{2,})", '', $fileNameSuffix);

		$outputFilePath .= $fileNameSuffix;

		// Use xpath directly on $xmlData ($xPathElement still contains refs for all p nodes)
		$paragraph = "";
		foreach ($xmlDataRede->xpath('//p') as $paragraph) {
			//$paragraph['klasse'] == 'T_NaS' ||
			if ($paragraph['klasse'] == 'T_fett' ||
				$paragraph['klasse'] == 'J' ||
				$paragraph['klasse'] == 'J_1' ||
				$paragraph['klasse'] == 'O' ||
				$paragraph['klasse'] == 'Z' ||
				$paragraph['klasse'] == 'T') {


				//echo "P!--> ".$paragraph."<br>";

				$sentences = preg_split('/([.,:;?!\\-\\-\\–] +)/', $paragraph[0], -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

				$paragraph[0] = '';

				$count = 0;
				$lastChild = null;

				foreach ( $sentences as $sentence ) {

					if ( $count%2 && $lastChild ) {
						$lastChild[0] .= $sentence;
					} else {
						$newChild = $paragraph->addChild('span', $sentence);
						$newChild['class'] = 'timebased';

						$newChild['id'] = 's'.sprintf('%06d', ++$sID);

						$lastChild = $newChild;
					}

					$count++;

				}
			}

		}

		//$cleanXML = convertAccentsAndSpecialToNormal();
		$cleanXML = mb_convert_encoding($xmlDataRede->asXML(), 'UTF-8', 'UFT-8');
		file_put_contents($conf["dir"]["tmp"]."/".$outputFilePath.'_optimised.xml', $cleanXML);


		if ($xmlDataRede['media-id']) {


			$audioFilePath = getAudioSource($xmlDataRede['media-id']);
			$audioFilePathArray = preg_split("/\\//", $audioFilePath);
			$audioFileName = array_pop($audioFilePathArray);

			if (!file_exists($conf["dir"]["cacheaudio"].'/'.$audioFileName)) {
				$response = array(  'message' => 'Audio file not found. Downloading file from Bundestag...',
					'task' => 'startDownload',
					'status' => '',
					'progress' => 0);
				echo json_encode($response);

				getAudioFile($audioFilePath);
			} else {
				/*
				$response = array(  'message' => 'Audio file found ('.$conf["dir"]["cacheaudio"].'/'.$audioFileName.'). No download necessary.',
					'task' => 'download',
					'status' => 'success',
					'progress' => 100);
				echo json_encode($response);
				*/
			}

			if (!file_exists($currentOutputDir.'/'.$outputFilePath.'_timings.json')) {
				$response = array(  'message' => 'Force aligning '.$audioFileName.' with '.$outputFilePath.'_optimised.xml ...',
					'task' => 'startForceAlign',
					'status' => '',
					'progress' => 60);
				echo json_encode($response);

				if (!$xmlDataRede->xpath('//span[@class="timebased"]')) {
					$response = array(  'message' => 'Error: Speech contains no text fragments ('.$conf["dir"]["tmp"]."/".$outputFilePath.'_optimised.xml)',
						'task' => 'forcealign',
						'status' => 'error',
						'progress' => 0);
					echo json_encode($response);
				} else {
					forceAlignAudio($conf["dir"]["cacheaudio"].'/'.$audioFileName, $conf["dir"]["tmp"]."/".$outputFilePath.'_optimised.xml', $currentOutputDir."/".$outputFilePath.'_timings.json');
				}

			} else {
				/*
				$response = array(  'message' => 'JSON timings file found ('.$currentOutputDir.'/'.$outputFilePath.'_timings.json). Force Align not necessary.',
					'task' => 'forcealign',
					'status' => 'success',
					'progress' => 100);
				echo json_encode($response);
				*/

			}

			$xmlDataWithTimingsRAW = getXMLWithTimings($currentOutputDir.'/'.$outputFilePath.'_timings.json', $xmlDataRede->asXML());

			$simpleXMLWithTimings = new SimpleXMLElement($xmlDataWithTimingsRAW["xml"]);
			$selectedXMLPart = $simpleXMLWithTimings;
			$htmlString = getHTMLfromXML($selectedXMLPart->asXML());

			file_put_contents($currentOutputDir."/".$outputFilePath.'.html', $htmlString);

			file_put_contents($currentOutputDir."/".$outputFilePath.'_annotations.json', json_encode($xmlDataWithTimingsRAW["annotations"], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

			$subtitles = getSubtitles(json_decode(file_get_contents($currentOutputDir.'/'.$outputFilePath.'_timings.json'),true));

			file_put_contents($currentOutputDir.'/'.$outputFilePath.'.srt',$subtitles["srt"]);
			file_put_contents($currentOutputDir.'/'.$outputFilePath.'.vtt',$subtitles["vtt"]);

			/*
			$response = array(  'message' => 'HTML with timings saved to: '.$outputFilePath.'.html',
				'task' => '',
				'status' => '',
				'progress' => 0);
			echo json_encode($response);
			*/

			unlink($conf["dir"]["tmp"]."/".$outputFilePath.'_optimised.xml');

			sleep($sleep);


		} else {
			/*
			$response = array(  'message' => 'Selected XML node does not have a Media ID.',
				'task' => '',
				'status' => 'error',
				'progress' => 0);
			echo json_encode($response);
			*/
			sleep($sleep);
		}

	} else {
		$response = array(  'message' => 'Selected XML node does not exist (...).',
			'task' => 'optimise',
			'status' => 'error',
			'progress' => 0);
		echo json_encode($response);
		sleep($sleep);
	}

}

/**
 * @param $audioFilePath
 * @return mixed
 */
function getAudioFile($audioFilePath) {

	global $conf;

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $audioFilePath);
	// set buffer size to 1mb to execute progress function less often
	curl_setopt($ch, CURLOPT_BUFFERSIZE, 600000);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, 'progress');
	curl_setopt($ch, CURLOPT_NOPROGRESS, false);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
	$output = curl_exec($ch);
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	$filePathArray = preg_split("/\\//", $audioFilePath);
	$audioFileName = array_pop($filePathArray);

	if ($status == 200) {
		file_put_contents($conf["dir"]["cacheaudio"]."/".$audioFileName, $output);
	}

	$response = array(  'message' => 'Audio file successfully downloaded.',
		'task' => 'download',
		'status' => 'success',
		'progress' => 100);
	echo json_encode($response);

}

$down = 0;

/**
 * @param $resource
 * @param $download_size
 * @param $downloaded
 * @param $upload_size
 * @param $uploaded
 * @return mixed
 */
function progress($resource,$download_size, $downloaded, $upload_size, $uploaded) {

	global $down;

	if ($download_size > 0 && $downloaded > ($down + 600000)) {

		$down = $downloaded + 600000;

		$response = array(  'message' => 'Audio file downloading',
			'task' => 'download',
			'status' => '',
			'progress' => $downloaded / $download_size  * 100);
		echo json_encode($response);

	}

}

/**
 * @param $audioFilePath
 * @param $optimisedXMLFilePath
 * @param $outputFilePath
 * @return mixed
 */
function forceAlignAudio($audioFilePath, $optimisedXMLFilePath, $outputFilePath,$sleep=1) {

	global $conf;
	
	if ($conf["serverType"] == 'windows') {
		$secureAudioPath = str_replace("/","\\",escapeshellcmd(__DIR__."\\".$audioFilePath));
		$secureXMLPath = str_replace("/","\\",escapeshellcmd(__DIR__."\\".$optimisedXMLFilePath));
		$secureOutputPath = str_replace("/","\\",escapeshellcmd(__DIR__."\\".$outputFilePath));
	} else {
		$secureAudioPath = escapeshellcmd(__DIR__."/".$audioFilePath);
		$secureXMLPath = escapeshellcmd(__DIR__."/".$optimisedXMLFilePath);
		$secureOutputPath = escapeshellcmd(__DIR__."/".$outputFilePath);
	}
	
	$exec_enabled =
		function_exists('exec') &&
		!in_array('exec', array_map('trim', explode(', ', ini_get('disable_functions')))) &&
		strtolower(ini_get('safe_mode')) != 1
	;

	if (!$exec_enabled) {
		$response = array(  'message' => 'PHP shell exec not allowed. Aeneas can not be executed.',
			'task' => 'forcealign',
			'status' => 'error',
			'progress' => 40);
		echo json_encode($response);
		sleep($sleep);
		exit();
	}

	/*
	$response = array('message' => 'Force aligning '.$secureAudioPath.' with '.$secureXMLPath.'...', 
		'task' => 'forcealign',
		'status' => '',
		'progress' => 60);
	echo json_encode($response);
	*/

	if ($conf["serverType"] == 'windows') {
		putenv('PATH=$PATH;'.$conf["ffmpeg"].";".$conf["pythonDir"].";".$conf["pythonDirScripts"].";".$conf["eSpeak"]);
	} else {
		putenv('PATH=$PATH:'.$conf["pythonDir"]);
	}
	
	putenv('set PYTHONIOENCODING="UTF-8"');

	//$command = 'set PYTHONIOENCODING="UTF-8" && python -m aeneas.tools.execute_task '.$secureAudioPath.' '.$secureXMLPath.' "task_language=deu|os_task_file_format=json|is_text_type=unparsed|is_text_unparsed_id_regex=s[0-9]+|is_text_unparsed_id_sort=numeric|task_adjust_boundary_no_zero=true|task_adjust_boundary_nonspeech_min=2|task_adjust_boundary_nonspeech_string=REMOVE" '.$secureOutputPath.' -vv -l --log="F:\webdev\VideoTranscriptGenerator-master\www\admin\tmpaeneas.log" 2>&1';
	
	//$command = 'set PYTHONIOENCODING="UTF-8" && python -m aeneas.tools.execute_task '.$secureAudioPath.' '.$secureXMLPath.' "task_language=deu|os_task_file_format=json|is_text_type=unparsed|is_text_unparsed_id_regex=s[0-9]+|is_text_unparsed_id_sort=numeric|task_adjust_boundary_no_zero=true|task_adjust_boundary_nonspeech_min=2|task_adjust_boundary_nonspeech_string=REMOVE" '.$secureOutputPath.' 2>&1';
	$command = 'set PYTHONIOENCODING="UTF-8" && python -m aeneas.tools.execute_task '.$secureAudioPath.' '.$secureXMLPath.' "task_language=deu|os_task_file_format=json|is_text_type=unparsed|is_text_unparsed_id_regex=s[0-9]+|is_text_unparsed_id_sort=numeric|task_adjust_boundary_no_zero=false|task_adjust_boundary_nonspeech_min=2|task_adjust_boundary_nonspeech_string=REMOVE|task_adjust_boundary_nonspeech_remove=REMOVE|is_audio_file_detect_head_min=0.1|is_audio_file_detect_head_max=3|is_audio_file_detect_tail_min=0.1|is_audio_file_detect_tail_max=3|task_adjust_boundary_algorithm=aftercurrent|task_adjust_boundary_aftercurrent_value=0.5|is_audio_file_head_length=1" '.$secureOutputPath.' 2>&1';

	$output = exec($command,$foo);

	//print_r($foo);

	if (strpos($output, '[INFO] Created file ') !== false) {
		$response = array(  'message' => 'Force align success. Aeneas Output: '.$output,
			'task' => 'forcealign',
			'status' => 'success',
			'progress' => 100);
		echo json_encode($response);
	} else {
		$response = array(  'message' => 'Force align error. Output: '.$output,
			'task' => 'forcealign',
			'status' => 'error',
			'progress' => 40);
		echo json_encode($response);

		sleep($sleep);
		exit();
	}

}

/**
 * @param $timingsFilePath (JSON)
 * @param $optimisedXMLData
 * @return mixed
 */
function getXMLWithTimings($timingsFilePath, $optimisedXMLData) {
	global $conf;

	$timingsString = file_get_contents($timingsFilePath);
	$timingsData = json_decode($timingsString, true);

	$xmlData = new SimpleXMLElement($optimisedXMLData);

	$videoSource = getVideoSource($optimisedXMLData['media-id']);

	$annotations = array();
	foreach ($timingsData['fragments'] as $fragment) {
		$spanElement = $xmlData->xpath('//span[@id="'.$fragment['id'].'"]')[0];
		//$spanElement->parent
		$spanElement['data-start'] = $fragment['begin'];
		$spanElement['data-end'] = $fragment['end'];
		$tmpArr = getAnnotations($spanElement,$fragment['begin'],$fragment['end'],$videoSource,"speech");
		$annotations = array_merge($annotations,$tmpArr);

		unset($spanElement['id']);
	}


	$lastTimes = array("data-start"=>"0.000", "data-end"=>"0.000");
	foreach($xmlData->children() as $childKey=>$childNode) {
		//if ($childNode["klasse"] != "redner") {

			$tmpTimebasedChildren = $childNode->xpath("span[@class='timebased']");

			if (count($tmpTimebasedChildren) > 0) {

				$lastTimes = array(
					"data-start"=>(string)$childNode->xpath("span[@class='timebased']")[0]->attributes()["data-start"],
					"data-end"=>(string)$childNode->xpath("span[@class='timebased'][last()]")[0]->attributes()["data-end"]
				);

				$childNode["data-start"] = $lastTimes["data-start"];
				$childNode["data-end"] = $lastTimes["data-end"];


			} else {

				$tmpItem = $childNode->xpath("./preceding-sibling::*/span[@class='timebased']");

				$lastTimes = array(
					"data-start"=>(string)$tmpItem[count($tmpItem)-1]["data-end"],
					"data-end"=>(string)$childNode->xpath("./following-sibling::*/span[@class='timebased'][1]")[0]["data-start"],
				);

				$childNode["data-start"] = ($lastTimes["data-start"]) ? $lastTimes["data-start"] : "0.000";
				$childNode["data-end"] = ($lastTimes["data-end"]) ? $lastTimes["data-end"] : $lastTimes["data-start"];
				$tmpArr = getAnnotations($childNode,$childNode["data-start"],$childNode["data-end"],$videoSource,"nonspeech");
				$annotations = array_merge($annotations,$tmpArr);

			}

		//}
	}

	/*if ($conf["dbpedia"] === true) {


		$opts = [
			"http" => [
				"method" => "GET",
				"header" => "accept: application/json"
			]
		];

		$context = stream_context_create($opts);
		$dom = new DOMDocument;
		$dom->loadXML($xmlData);
		$xpath = new DOMXPath($dom);
		$content = ($xpath->evaluate("string(//*)"));


		$context = stream_context_create($opts);
		$dbpediajson = file_get_contents("https://api.dbpedia-spotlight.org/de/annotate?text=".urlencode($content)."&types=".urlencode($conf["dbpedia_types"])."&confidence=".$conf["dbpedia_confidence"], false, $context);
		$dbpediafinds = json_decode($dbpediajson,true);
		$tmpArr = array();

		//remove double entries
		foreach ($dbpediafinds["Resources"] as $res) {
			if ($tmpArr[$res["@surfaceForm"]]["URI"] == $res["@URI"]) {
				continue;
			} else {
				$tmpArr[$res["@surfaceForm"]] = $res;
			}
		}

	}*/

	return array("xml"=>$xmlData->asXML(), "annotations"=>$annotations);

}


function getAnnotations($elem,$startTime,$endTime,$videoSource,$foundin="") {
	global $conf;
	$annotations = array();
	foreach ($conf["annotationPattern"] as $tmp) {
		$tmp_match = array();
		if (preg_match_all($tmp["pattern"], $elem, $tmp_match)) {

			foreach ($tmp_match[0] as $tmpResult) {

				$tmpAnnotation = json_decode('{
					"@context": [
						"http://www.w3.org/ns/anno.jsonld",
						{
							"frametrail": "http://frametrail.org/ns/"
						}
					],
					"creator": {
						"nickname": "Script",
						"type": "Agent",
						"id": "0"
					},
					"created": "'.date("D M d Y H:i:s \G\M\TO (T)").'",
					"type": "Annotation",
					"frametrail:type": "Annotation",
					"frametrail:tags": [
						"'.$tmp["kind"].'"
					],
					"target": {
						"type": "Video",
						"source": "'.$videoSource.'",
						"selector": {
							"conformsTo": "http://www.w3.org/TR/media-frags/",
							"type": "FragmentSelector",
							"value": "t='.(string)$startTime.','.(string)$endTime.'"
						}
					}
					}',true);
				$tmpAnnotation["body"] = $tmp["annotationBody"];
				$tmpAnnotation["body"]["frametrail:name"] = $tmp["kind"]." ".(string)$tmpResult;

				if ($tmp["kind"] == "drucksache") {
					$tmpAnnotation["body"]["value"] = getDocumentSource((string)$tmpResult);
				} elseif (($tmp["kind"] == "wahlen") || ($tmp["kind"] == "gesetz")) {
					$tmpAnnotation["body"]["value"] = "https://de.wikipedia.org/wiki/" . (string)$tmpResult;
				}
				array_push($annotations, $tmpAnnotation);

			}

		}
	}

	return $annotations;

}
function getAnnotations_bak($elem,$startTime,$endTime,$foundin="") {
	global $conf;
	$annotations = array();
	foreach ($conf["annotationPattern"] as $tmp) {
		$tmp_match = array();
		if (preg_match_all($tmp["pattern"], $elem, $tmp_match)) {

			foreach ($tmp_match[0] as $tmpResult) {

				array_push($annotations,
					array(
						"type"=>$tmp["type"],
						"kind"=>$tmp["kind"],
						"value"=>(string)$tmpResult,
						"start"=>(string)$startTime,
						"end"=>(string)$endTime,
						"foundin"=>(string)$foundin
					)
				);

			}

		}
	}

	return $annotations;

}

function getSubtitles($data) {

	$return["srt"] = "";
	$return["vtt"] = "WEBVTT\n\n";

	foreach ($data["fragments"] as $k=>$v) {

		$begin = preg_split("~\.~",$v["begin"]);
		$end = preg_split("~\.~",$v["end"]);
		$lines = "";
		foreach ($v["lines"] as $line) {
			$lines .= $line."\n";
		}
		$return["srt"] .= ($k+1)."\n".convertSecondsToTime($v["begin"]).",".$begin[1]." --> ".convertSecondsToTime($v["end"]).",".$end[1]."\n".$lines."\n";
		$return["vtt"] .= ($k+1)."\n".convertSecondsToTime($v["begin"]).".".$begin[1]." --> ".convertSecondsToTime($v["end"]).".".$end[1]."\n".$lines."\n";

	}

	return $return;

}

function convertSecondsToTime($seconds) {
	$time = round($seconds);
	return sprintf('%02d:%02d:%02d', ($time/3600),($time/60%60), $time%60);
}


/**
 * @param $mediaID
 * @return mixed
 */
function getAudioSource($mediaID) {

	$audioPath = 'http://static.cdn.streamfarm.net/1000153copo/ondemand/145293313/'.$mediaID.'/'.$mediaID.'_mp3_128kb_stereo_de_128.mp3';

	return $audioPath;

}

/**
 * @param $audioFilePath
 * @return mixed
 */
function getAudioDuration($audioFilePath) {

	global $conf;

	if ($conf["serverType"] == 'windows') {
		$secureAudioPath = str_replace("/","\\",escapeshellcmd(__DIR__."\\".$audioFilePath));
	} else {
		$secureAudioPath = escapeshellcmd(__DIR__."/".$audioFilePath);
	}
	
	$exec_enabled =
		function_exists('exec') &&
		!in_array('exec', array_map('trim', explode(', ', ini_get('disable_functions')))) &&
		strtolower(ini_get('safe_mode')) != 1
	;

	if (!$exec_enabled) {
		$response = array(  'message' => 'PHP shell exec not allowed. ffprobe can not be executed.',
			'task' => 'getduration',
			'status' => 'error',
			'progress' => 40);
		echo json_encode($response);
		exit();
	}

	if ($conf["serverType"] == 'windows') {
		putenv('PATH=$PATH;'.$conf["ffmpeg"].";".$conf["pythonDir"].";".$conf["pythonDirScripts"].";".$conf["eSpeak"]);
	} else {
		putenv('PATH=$PATH:'.$conf["pythonDir"]);
	}
	
	$command = 'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '.$secureAudioPath.' 2>&1';

	$output = exec($command);

	if ((float) $output > 0) {
		return (float) $output;
	} else {
		$response = array(  'message' => 'Error: Could not retrieve audio duration. Output: '.$output,
			'task' => 'getduration',
			'status' => 'error',
			'progress' => 40);
		echo json_encode($response);

		return null;
	}
}

/**
 * @param $mediaID
 * @return mixed
 */
function getVideoSource($mediaID) {

	$videoPath = 'https://static.p.core.cdn.streamfarm.net/1000153copo/ondemand/145293313/'.$mediaID.'/'.$mediaID.'_h264_1920_1080_5000kb_baseline_de_5000.mp4';

	return $videoPath;

}

function getDocumentSource($docNumber) {
	$docArray = str_split(str_replace('/', '', $docNumber));

	$docPeriod = $docArray[0].$docArray[1];

	if (count($docArray) == 5) {
		$docSession = sprintf('%03d',(int) $docArray[2]);
		$docNumber = sprintf('%02d',(int) $docArray[3].$docArray[4]);
	} else {
		$docSession = sprintf('%03d',(int) $docArray[2].$docArray[3]);
		$docNumber = sprintf('%02d',(int) $docArray[4].$docArray[5]);
	}

	return 'https://dipbt.bundestag.de/doc/btd/'.$docPeriod.'/'.$docSession.'/'.$docPeriod.$docSession.$docNumber.'.pdf';
}

function getWPSource() {

}

/**
 * @param $XMLDataWithTimings
 * @return mixed
 */
function getHTMLfromXML($XMLDataWithTimings) {

	$xmlString = $XMLDataWithTimings;

	$xmlStr = array(
		'<?xml version="1.0" encoding="UTF-8"?>
',
		'<?xml version="1.0"?>
',
		'<!DOCTYPE dbtplenarprotokoll SYSTEM "dbtplenarprotokoll.dtd">',
		'dbtplenarprotokoll',
		'<sitzungsverlauf>',
		'</sitzungsverlauf>',
		'<tagesordnungspunkt ',
		'</tagesordnungspunkt>',
		'<kommentar>',
		'<kommentar ',
		'</kommentar>',
		'<rede ',
		'</rede>'
	);
	$htmlStr = array(
		'<!DOCTYPE html>
<html>
  <body>
',
		'<!DOCTYPE html>
<html>
  <body>
',
		'',
		'body',
		'<div class="sitzungsverlauf">',
		'</div>',
		'    <div class="tagesordnungspunkt" ',
		'</div>',
		'<div class="kommentar">',
		'<div class="kommentar" ',
		'</div>',
		'<div class="rede" ',
		'</div>'
	);

	$htmlString = str_replace($xmlStr, $htmlStr, $xmlString);

	//remove speaker info
	$htmlString = preg_replace('/(<redner)(.|\n)*?(redner>)/', '', $htmlString);

	return '<!DOCTYPE html>
<html>
  <head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  </head>
  <body>
'.$htmlString.'
  </body>
</html>';

}

function disable_ob() {
	// Turn off output buffering
	ini_set('output_buffering', 'off');
	// Turn off PHP output compression
	ini_set('zlib.output_compression', false);
	// Implicitly flush the buffer(s)
	ini_set('implicit_flush', true);
	ob_implicit_flush(true);
	// Clear, and turn off output buffering
	while (ob_get_level() > 0) {
		// Get the curent level
		$level = ob_get_level();
		// End the buffering
		ob_end_clean();
		// If the current level has not changed, abort
		if (ob_get_level() == $level) break;
	}
	// Disable apache output buffering/compression
	if (function_exists('apache_setenv')) {
		apache_setenv('no-gzip', '1');
		apache_setenv('dont-vary', '1');
	}
}

function checkDirectories() {

	global $conf;

	if (!is_writable($conf['inputXML'])) {
		if (!mkdir($conf['inputXML'])) {
			$response = array(  'message' => 'Directory missing: '.$conf['inputXML'].' Please make sure it exists and is writable.',
				'task' => 'Generate Directories',
				'status' => 'error',
				'progress' => 0);
			echo json_encode($response);

			sleep(1);
			exit();
		} else {
			chmod($conf['inputXML'], 0755);
		}
	}

	if (!is_writable($conf['inputAudio'])) {
		if (!mkdir($conf['inputAudio'])) {
			$response = array(  'message' => 'Directory missing: '.$conf['inputAudio'].' Please make sure it exists and is writable.',
				'task' => 'Generate Directories',
				'status' => 'error',
				'progress' => 0);
			echo json_encode($response);

			sleep(1);
			exit();
		} else {
			chmod($conf['inputAudio'], 0755);
		}
	}

	if (!is_writable($conf['output'])) {
		if (!mkdir($conf['output'])) {
			$response = array(  'message' => 'Directory missing: '.$conf['output'].' Please make sure it exists and is writable.',
				'task' => 'Generate Directories',
				'status' => 'error',
				'progress' => 0);
			echo json_encode($response);

			sleep(1);
			exit();
		} else {
			chmod($conf['output'], 0755);
		}
	}

}

?>