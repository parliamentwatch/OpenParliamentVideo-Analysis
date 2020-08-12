<?php

error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

set_time_limit(0);
ini_set('memory_limit', '500M');
date_default_timezone_set('CET');
ob_implicit_flush(true);
ob_end_flush();

/**
 * @param $XMLFilePath
 * @return mixed
 */
function getMediaIDs($XML,$sleep=1) {
		
		global $conf;

		include_once("analysis.php");

		//$xmlData = simplexml_load_string($XML);
		$xmlData = $XML;

		$wahlperiode = $xmlData->xpath('//kopfdaten//wahlperiode')[0];
		$sitzungsnummer = $xmlData->xpath('//kopfdaten//sitzungsnr')[0];
		$sessionDate = date_create_from_format('d.m.Y', $xmlData->xpath('//kopfdaten//datum')[0]["date"])->getTimestamp();
		$docNumber = sprintf('%02d',(int) $wahlperiode).sprintf('%03d',(int) $sitzungsnummer);

		$media_index = json_decode(file_get_contents($conf["dir"]["output"]."/index-files/".$docNumber."-data_index.json"),true);

		$alleTOPs = $xmlData->xpath('//tagesordnungspunkt');
		
		$index_media_json = [];
		$return = [];

		//$prevSpeechID = findPrevSpeechID($media_index, $sitzungsnummer);


		//$lastNameTag = $xmlData->xpath('//tagesordnungspunkt//name[last()]');
		//print_r($lastNameTag)

		if (!empty($alleTOPs)) {
			
			$topCnt = 0;

			foreach ($alleTOPs as $tagesordnungspunktKey=>$tagesordnungspunkt) {
				
				$topCnt++;
				
				$top = $tagesordnungspunkt['top-id'];

				$lastNameTag = $tagesordnungspunkt->xpath('rede/name');
				
				if (isset($lastNameTag[(count($lastNameTag) - 1)])) {
					$lastNameTagFollowing = $lastNameTag[count($lastNameTag) - 1]->xpath("following-sibling::*");
					//unset($lastNameTag[count($lastNameTag)-1]->xpath("following-sibling::*"));
					foreach ($lastNameTagFollowing as $fu) {
						unset($fu[0]);
					}
					unset($lastNameTag[count($lastNameTag) - 1][0]);
				}
				//print_r($lastNameTagFollowing);
				//return;
				//continue;

				
				/*$topString = (string) $tagesordnungspunkt->xpath('p[@klasse="T_NaS"]')[0];

				if (!$topString) {
					$topString = (string) $tagesordnungspunkt->xpath('p[@klasse="T_ZP_NaS"]')[0];
				}

				if (!$topString) {
					$topString = (string) $tagesordnungspunkt->xpath('p[@klasse="T_fett"]')[0];
				}*/

				$topString = $tagesordnungspunkt->xpath('p[@klasse="T_NaS"]');

				if (!$topString[0]) {
					$topString = $tagesordnungspunkt->xpath('p[@klasse="T_ZP_NaS"]');
				}

				if (!$topString[0]) {
					$topString = $tagesordnungspunkt->xpath('p[@klasse="T_fett"]');
				}

				if (!$topString[0]) {
					$topString = $tagesordnungspunkt->xpath('.//p[@klasse="T_fett"]');
				}

				$topArr = array();
				foreach($topString as $topArrItem) {
					$topArr[] = (string)$topArrItem;
				}

				$tryAlignment = true;
				if ($topString[0] && preg_match("/(Eidesleistung )|(Befragung der Bundesregierung)|(Befr agung der Bundesregierung)|(Fragestunde)|(Wahl der )|(Wahl des )/", $topString[0])) {
					
					// doesn't make sense in this context -> fix
					$tryAlignment = false;

					continue;
				}

				$alleReden = $tagesordnungspunkt->xpath('rede');

				$speechCnt = 0;

				if (!empty($alleReden)) {
					
					foreach ($alleReden as $rede) {
						
						$speechCnt++;

						$vorname = $rede->xpath('p//redner//vorname')[0];
						$nachname = $rede->xpath('p//redner//nachname')[0];

						$titel = '';
						$fraktion = '';
						$speakerRole = '';

						if (!empty($rede->xpath('p//redner//titel'))) {
							$titel = $rede->xpath('p//redner//titel')[0];
						}

						if (!empty($rede->xpath('p//redner//fraktion'))) {
							$fraktion = $rede->xpath('p//redner//fraktion')[0];
						}

						if (!empty($rede->xpath('p//redner//rolle//rolle_lang'))) {
							$speakerRole = $rede->xpath('p//redner//rolle//rolle_lang')[0];
						}

						$speakerID = (string)$rede->xpath('p//redner')[0]["id"];

						$response = array(  'message' => "Indexing: Period ".$wahlperiode." | Session: ". $sitzungsnummer." | Agenda Item ".$topCnt."/".count($alleTOPs)." | Speech ".$speechCnt."/".count($alleReden)." ( ".$vorname." ".$nachname." ".$speakerID." ), ".gmdate("Y-m-d", $sessionDate),
							'task' => 'speechStatus',
							'status' => '',
							'progress' => 0);
						echo json_encode($response);

						//print_r($speakerID);
						//$alleRednerReden = $tagesordnungspunkt->xpath("/redner[@id='".$speakerID."']");
						
						unset($alleRednerReden);
						$alleRednerReden = $tagesordnungspunkt->xpath("rede//p[1]//redner[@id='".$speakerID."']");
						//$alleRednerReden = $xmlData->xpath("//tagesordnungspunkt/rede//p//redner[@id='".$speakerID."'][1]");
						//print_r($alleRednerReden);

						$rCnt = 0;
						$redeIndex = 0;
						foreach($alleRednerReden as $rednerRede) {

							//print_r($rednerRede->parent());
							//print_r($rednerRede->xpath("../.."));
							if ( (string)$rednerRede->xpath("../..")[0]["id"] == (string)$rede["id"] ) {
								//echo 'Index der Rede: '.$rCnt;
								$redeIndex = $rCnt;
								break;
							} else {
								//echo "nope!";
							}
							$rCnt++;
						}

						//print_r($alleRednerReden);
						//count($alleRednerReden->)+1;
						

//return;

						if (!isset($rede['media-id'])) {

							$speechInIndex = $media_index[(string) $rede['id']];
							// Request Media ID from Bundestag
							if (!isset($speechInIndex['mediaID']) || strlen((string) $speechInIndex['mediaID']) < 3 || !isset($speechInIndex['timestamp']) || $speechInIndex['agendaItemSecondTitle'] == null) {
								
								sleep($sleep);
								
								$rssContent = getMediaIDfromRSS($wahlperiode, $sitzungsnummer, $top, $vorname, $nachname, $titel, $sleep, $redeIndex);
								$mediaID = $rssContent["mediaID"];

								$headlineInfo = extractInfoFromHeadline($rssContent["headline"]);
								$headline = $headlineInfo["agendaItemTitle"];
								if ((!isset($top) || strlen($top) == 0) && isset($headlineInfo["agendaItem"])) {
									$top = "Tagesordnungspunkt ".$headlineInfo["agendaItem"];
								}

								$speechTimestamp = $rssContent["timestamp"];

								
							} else {
								// Else use Media ID from Index 
								$mediaID = $speechInIndex['mediaID'];

								$headlineInfo = extractInfoFromHeadline($speechInIndex['agendaItemSecondTitle']);
								$headline = $headlineInfo["agendaItemTitle"];
								if ((!isset($top) || strlen($top) == 0) && isset($headlineInfo["agendaItem"])) {
									$top = "Tagesordnungspunkt ".$headlineInfo["agendaItem"];
								}

								$speechTimestamp = $speechInIndex["timestamp"];
							}

							

							// Doublecheck via TOC if no media ID could be found
							if (!$mediaID) {
								$xrefItems = $xmlData->xpath('//ivz-eintrag//xref');
								
								foreach ($xrefItems as $xrefItem) {
									
									//echo (string) $xrefItem['rid'].':';
									//echo (string) $rede['id'].'<br>';
									if ((string) $xrefItem['rid'] == (string) $rede['id']) {

										$correctTOP = $xrefItem->xpath('ancestor::ivz-block/ivz-block-titel');
										$correctTOPString = str_replace(':', '', $correctTOP[0]);

										if ($correctTOPString != (string) $top) {
											//echo 'Incorrent TOP ('.$top.'). Correct TOP: '.$correctTOPString.'<br>';
											
											$response = array(  'message' => "Warning: Incorrect TOP: ".$top.". Correct TOP: ".$correctTOPString." (Period: ".$wahlperiode.", Session: ". $sitzungsnummer.")",
												'task' => 'getmediainfo',
												'status' => 'warning',
												'progress' => 0);
											echo json_encode($response);
											//$return["error"][] = ;

											$rssContent = getMediaIDfromRSS($wahlperiode, $sitzungsnummer, $correctTOPString, $vorname, $nachname, $titel,$sleep, $redeIndex);
											$mediaID = $rssContent["mediaID"];

											$headlineInfo = extractInfoFromHeadline($rssContent["headline"]);
											$headline = $headlineInfo["agendaItemTitle"];
											if ((!isset($top) || strlen($top) == 0) && isset($headlineInfo["agendaItem"])) {
												$top = "Tagesordnungspunkt ".$headlineInfo["agendaItem"];
											}

											$speechTimestamp = $rssContent["timestamp"];
										}
										break;
									}
								}
							}

							if (!$mediaID) {
								
								$response = array('message' => "Error: No MediaID found (Period: ".$wahlperiode.", Session: ". $sitzungsnummer." TOP: ".$top.", RedeID: ".$rede["id"],
												'task' => 'getmediainfo',
												'status' => 'error',
												'progress' => 0);
								echo json_encode($response);

								//$return["error"][] = "Period: ".$wahlperiode.", SN: ". $sitzungsnummer." TOP:". $top.", RedeID: ".$rede["id"]." Info: No MediaID found";
							}

							//echo 'Name: '.$vorname.' '.$nachname.', TOP: '.$top.', MediaID: '.$mediaID.'<br><br>';
							




							if ($mediaID && strlen($mediaID) > 3) {

								$rede['media-id'] = $mediaID;

								$tocItems = $xmlData->xpath('//ivz-eintrag');

								foreach ($tocItems as $tocItem) {
									if (isset($tocItem->xref) && ((string) $tocItem->xref['rid'] == (string) $rede['id'])) {

										$tocItem['media-id'] = $mediaID;


										break;
									}
								}

							}

						}


						forceAlignXMLData($rede->asXML(),$xmlData, $tryAlignment);

						
						unset($tmpObj);

						$tmpDS = [];
						$dsAll = $tagesordnungspunkt->xpath("p[@klasse='T_Drs']");
						foreach ($dsAll as $ds) {
							preg_match_all("/(\d{2}\/\d{2,6})/",(string)$ds[0],$dsArray);
							$tmpDS = array_merge($tmpDS, $dsArray[0]);
						}

						$currMediaID = ((string)$mediaID) ? (string)$mediaID : (string)$rede['media-id'];
						$duration = getAudioDuration($conf["dir"]["cacheaudio"].'/'.$currMediaID.'_mp3_128kb_stereo_de_128.mp3');

						if ($duration == null) {
							$tryAlignment = false;
						}

						$tmpObj["id"] = (string)$rede["id"];
						$tmpObj["mediaID"] = $currMediaID;
						$tmpObj["duration"] = $duration;
						$tmpObj["aligned"] = $tryAlignment;
						$tmpObj["electoralPeriod"] = (string)$wahlperiode;
						$tmpObj["sessionNumber"] = (int)$sitzungsnummer;
						$tmpObj["date"] = gmdate("Y-m-d", $sessionDate);
						$tmpObj["timestamp"] = $speechTimestamp;
						$tmpObj["agendaItemTitle"] = (string)$top;
						$tmpObj["agendaItemSecondTitle"] = $headline;
						$tmpObj["agendaItemThirdTitle"] = $topArr;
						$tmpObj["documents"] = $tmpDS;
						$tmpObj["speakerID"] = $speakerID;
						$tmpObj["speakerDegree"] = (string)$titel;
						$tmpObj["speakerFirstName"] = (string)$vorname;
						$tmpObj["speakerLastName"] = (string)$nachname;
						//TODO: map fraction to party if possible
						$tmpObj["speakerParty"] = (string)$fraktion;
						$tmpObj["speakerRole"] = (string)$speakerRole;

						/*
						$tmpObj["prevSpeechID"] = $prevSpeechID;
						$tmpObj["nextSpeechID"] = "last";						
						if ($prevSpeechID != "first") {
							$index_media_json[$prevSpeechID]["nextSpeechID"] = $tmpObj["id"];
						}
						$prevSpeechID = (string)$rede["id"];
						*/

						$html = file_get_contents($conf["dir"]["output"]."/".sprintf('%02d',(int) $wahlperiode)."/".sprintf('%03d',(int) $sitzungsnummer)."/".$docNumber."-Rede-".$tmpObj["id"].".html");
		
						if ($html) {

							$dom = new DOMDocument('1.0', 'UTF-8');
							$dom->loadHTML($html);
							$xPath = new DOMXPath($dom);
							$speechNode = $xPath->query("//div[@class='rede']")[0];

							$innerHTML = "";
							$children  = $speechNode->childNodes;

							foreach ($children as $child) { 
								$innerHTML .= $speechNode->ownerDocument->saveHTML($child);
							}

							$tmpObj["content"] = htmlspecialchars($innerHTML);

						}

						$index_media_json[$tmpObj["id"]] = $tmpObj;

					}

				} else {
					
					/*
					$response = array('message' => "Error: No speech found (Period: ".$wahlperiode.", Session: ". $sitzungsnummer.", TOP: ". $top,
						'task' => 'getmediainfo',
						'status' => 'error',
						'progress' => 0);
					echo json_encode($response);
					*/
					

				}

			}

		} else {
			
			$response = array('message' => "Error: No TOPs found (Period: ".$wahlperiode.", Session: ". $sitzungsnummer,
				'task' => 'getmediainfo',
				'status' => 'error',
				'progress' => 0);
			echo json_encode($response);

			//$return["error"][] = "Period: ".$wahlperiode.", SN: ". $sitzungsnummer.", Info: No TOPs found";
		}
	$return["rede_index"] = $index_media_json;
	$return["xml_optimized"] = $xmlData->asXML();

	return $return;

	/*
	$response = array('message' => '',
		'task' => 'index',
		'status' => '',
		'progress' => 0,
		'rede_index' => $return["rede_index"],
		'xml_optimized' => $return["xml_optimized"]);
	
	echo '<br><br>REDE INDEX:<br>';
	print_r($response);
	echo '<br><br><br>';

	return;
	*/
	//echo json_encode($response);			

} 

//getMediaIDfromRSS('19', '14', 'Tagesordnungspunkt 7', 'Frauke', 'Petry', '');

/**
 * @param $wahlperiode
 * @param $sitzungsnummer
 * @param $top
 * @param $vorname
 * @param $nachname
 * @param $titel
 * @return string
 */
function getMediaIDfromRSS($wahlperiode, $sitzungsnummer, $top, $vorname, $nachname, $titel, $sleep=1,$redeIndex=0) {

	sleep($sleep);

	/*
	$response = array(  'message' => 'see console',
		'task' => 'console',
		'status' => '',
		'console' => 'VORNAME: '.$vorname.', NACHNAME: '.$nachname.'');
	echo json_encode($response);
	*/

	// Fix Namen
	if ($nachname == 'Bürgermeister') {
		$vornameParts = explode(' ', $vorname, 2);
		$vorname = $vornameParts[0];
		$nachname = $vornameParts[1];
	}
	$vorname = str_replace(',', '', $vorname);
	$nachname = str_replace(',', '', $nachname);
	$vorname = str_replace('Alterspräsident ', '', $vorname);
	$vorname = str_replace('Alterspraesident ', '', $vorname);
	$vorname = str_replace('Altersprasident ', '', $vorname);
	$vorname = str_replace('Dr. ', '', $vorname);
	$vorname = str_replace('Graf ', '', $vorname);
	$vorname = str_replace(' Graf', '', $vorname);
	$nachname = str_replace('Graf ', '', $nachname);
	preg_match('/(in der)/', $nachname, $surnameMatch);
	if (strlen($surnameMatch[0]) == 0) {
		$nachname = str_replace('der ', '', $nachname);
	}
	$vorname = str_replace(' Freiherr von', '', $vorname);
	$nachname = str_replace('Freiherr von', '', $nachname);
	$nachname = str_replace('von ', '', $nachname);
	$nachname = str_replace('de Vries', 'Vries', $nachname);
	$nachname = str_replace('de Maizière', 'Maizière', $nachname);

	$nachnameParts = explode(' ', $nachname);
	if (count($nachnameParts) == 2 
			&& $nachnameParts[0] != 'De'
			&& $nachnameParts[0] != 'Mohamed') {
		$vorname .= ' '.$nachnameParts[0];
		$nachname = $nachnameParts[1];
	}
	$vornameParts = explode(' ', $vorname);
	if (count($vornameParts) == 2 && (
		$vornameParts[1] == 'Mohamed' || 
		$vornameParts[1] == 'de'
	)) {
		$nachname = $vornameParts[1].' '.$nachname;
		$vorname = $vornameParts[0];
	}

	preg_match('/(in der)/', $vorname, $match);
	if (strlen($match[0]) != 0) {
		$vorname = str_replace($match[0], '', $vorname);
		$nachname = $match[0].' '.$nachname;
	}

	if ($vorname == 'Matern' && $nachname == 'Marschall') {
		//$vorname = 'Matern von';
		$vorname = '';
	}
	if ($vorname == 'Norbert Maria' && $nachname == 'Altenkamp') {
		$vorname = 'Norbert';
	}
	if ($vorname == 'Berengar Elsner von' || $vorname == 'Berengar Elsner') {
		$vorname = 'Berengar';
		$nachname = 'Elsner von Gronow';
	}
	if ($vorname == 'Albert H.' && $nachname == 'Weiler') {
		$vorname = 'Albert';
	}
	// Fix Ende

	$top = str_replace('   ', ' ', $top);

	$topShortIDs = getTopShortID($top);
	$searchStrings = [];
	if (preg_match("/(Epl)/", $topShortIDs[0])) {
		$searchStrings[0] = $topShortIDs[0];
		$searchStrings[1] = ($topShortIDs[1]) ? $topShortIDs[1] : null;
	} else {
		$searchStrings[0] = 'TOP: '.$topShortIDs[0];
		$searchStrings[1] = ($topShortIDs[1]) ? 'TOP: '.$topShortIDs[1] : null;
	}

	$nachnameClean = urlencode(convertAccentsAndSpecialToNormal($nachname));
	$vornameClean = urlencode(convertAccentsAndSpecialToNormal($vorname));

	$rssURL = 'http://webtv.bundestag.de/player/bttv/news.rss?lastName='.$nachnameClean.'&firstName='.$vornameClean.'&meetingNumber='.urlencode($sitzungsnummer).'&period='.urlencode($wahlperiode);

	$response = array(  'message' => 'Retrieving Media ID from '.$rssURL.', Index: '.$redeIndex.', Search String: '.$searchStrings[0],
		'task' => 'getmediainfo',
		'status' => '',
		'progress' => 0);
	echo json_encode($response);

	$rssResult = simplexml_load_file($rssURL);
	
	if (!$rssResult) {
		$response = array(  'message' => 'Error: Media ID could not be received from URL (HTTP ERROR).',
			'task' => 'getmediainfo',
			'status' => 'error',
			'progress' => 0);
		echo json_encode($response);
		return null;
	}

	$allItems = $rssResult->xpath('//item');
	
	if (count($allItems) > 1 && strlen($top) > 1) {

		$allItemsReverse = array_reverse($allItems);
		$tmpMatches = array();
		$tmpHeadline = array();

		foreach ($allItemsReverse as $item) {
			
			$description = $item->description[0];
			$description = str_replace('  ', ' ', $description);
			
			$matchingString = null;
			
			if (preg_match("/(".$searchStrings[0].")/", $description)) {
				$matchingString = $searchStrings[0];
			} elseif ($searchStrings[1] != null && preg_match("/(".$searchStrings[1].")/", $description)) {
				$matchingString = $searchStrings[1];
			} elseif (preg_match("/(".$topShortIDs[0].")/", $description)) {
				$matchingString = $topShortIDs[0];
			}

			if ($matchingString) {
				$link = $item->link;
				$tmp = explode('/', $link);
				$tmpArr = preg_split("/".$matchingString."\:\s/",$description);
				$tmpHeadline[] = array_pop($tmpArr);
				$tmpTimestamp[] = strtotime($item->pubDate[0]);
				$mediaID = array_pop($tmp);

				array_push($tmpMatches,$mediaID);
			}

		}
		//print_r($tmpMatches);

		return array("mediaID"=>$tmpMatches[(($redeIndex)?$redeIndex:0)],"headline"=>$tmpHeadline[(($redeIndex)?$redeIndex:0)],"timestamp"=>$tmpTimestamp[(($redeIndex)?$redeIndex:0)]);

	} elseif (count($allItems) == 1 && strlen($top) > 1) {

		$description = $allItems[0]->description[0];
		$description = str_replace('  ', ' ', $description);
		$link = $allItems[0]->link;
		
		$matchingString = null;
		
		if (preg_match("/(".$searchStrings[0].")/", $description)) {
			$matchingString = $searchStrings[0];
		} elseif ($searchStrings[1] != null && preg_match("/(".$searchStrings[1].")/", $description)) {
			$matchingString = $searchStrings[1];
		} elseif (preg_match("/(".$topShortIDs[0].")/", $description)) {
			$matchingString = $topShortIDs[0];
		}

		/*
		$response = array(  'message' => 'Matching Search String: '.$matchingString.', ShortID: '.$topShortIDs[0],
			'task' => 'getmediainfo',
			'status' => '',
			'progress' => 0);
		echo json_encode($response);
		*/

		if ($matchingString) {
			$tmp = explode('/', $link);
			$mediaID = array_pop($tmp);
			$tmpArr = preg_split("/".$matchingString."\:\s/",$description);
			$tmpHeadline = array_pop($tmpArr);
			$tmpTimestamp = strtotime($allItems[0]->pubDate[0]);
			return array("mediaID"=>$mediaID,"headline"=>$tmpHeadline,"timestamp"=>$tmpTimestamp);
		}

	}

	return null;
	
}

/**
 * @param $top
 * @return string
 */
function getTopShortID($top) {
	
	$return = [];
	$topParts = explode(' ', $top);

	if (preg_match('/^I\./', $top)) {
		return $topParts[0];
	}

	$topType = $topParts[0];
	$topID1 = $topParts[1];

	if (preg_match('/-/', $topID1)) {
		$topID2 = $topID1;
		$topIDArray = explode('-', $topID1);
		$topIDStart = (int) $topIDArray[0];
		$topIDEnd = (int) $topIDArray[1];

		$topID1 = $topIDArray[0];
		for($i=$topIDStart+1; $i<=$topIDEnd; $i++) {
			$topID1 .= ','.$i;
		}
	}

	if ($topType == 'Zusatzpunkt' || $topType == 'Zusatztagesordnungspunkt') {
		$return[] = 'ZP '.$topID1;
		if (isset($topID2)) {
			$return[] = 'ZP '.$topID2;
		}
	} else if ($topType == 'Einzelplan') {
		$return[] = 'Epl '.$topID1;
		if (isset($topID2)) {
			$return[] = 'Epl '.$topID2;
		}
	} else {
		$return[] = ''.$topID1;
		if (isset($topID2)) {
			$return[] = ''.$topID2;
		}
	}
	
	return $return;
}

/**
 * @param $headline
 * @return array
 */
function extractInfoFromHeadline($headline) {
	
	$re = '/((?<sessionNumber>\d+)\.\sSitzung)|(TOP:\s(?<agendaItem>\w+\s\d+,\s\d+,\s\d+|\w+\s\d+,\s\d+|\w+\s\d+|\w+\.\d+|\d\.\d|\d))|(?<subAgendaItem>Epl\s\d{2}|Epl\s\d{1}|ZP\s\d\s-\sZP\s\d|ZP\s\d)|(?<agendaItemTitle>:\s.+)/m';
	
	preg_match_all($re, $headline, $matches, 0);

	/*
	echo '<pre>';
	print_r($matches);
	echo '</pre>';
	*/

	$sessionNumber = (isset($matches["sessionNumber"][0])) ? $matches["sessionNumber"][0] : null;
	$agendaItem = (isset($matches["agendaItem"])) ? $matches["agendaItem"][1] : null;
	$subAgendaItem = (isset($matches["subAgendaItem"])) ? $matches["subAgendaItem"][2] : null;
	$agendaItemTitle = (isset($matches["agendaItemTitle"][3])) ? ltrim($matches["agendaItemTitle"][3], ": ") : ltrim($matches["agendaItemTitle"][2], ": ");

	if (!$agendaItemTitle || strlen($agendaItemTitle) < 3) {
		$agendaItemTitle = $headline;
	}

	return array("sessionNumber"=>$sessionNumber, 
		"agendaItem"=>$agendaItem, 
		"subAgendaItem"=>$subAgendaItem, 
		"agendaItemTitle"=>$agendaItemTitle);
}

function findPrevSpeechID($media_index, $currentSessionNumber) {
	
	$currentSessionNumber = (int)$currentSessionNumber;

	$targetSessionNumber = ($currentSessionNumber > 1) ? $currentSessionNumber-1 : 1; 

	$previousSpeech = "first";

	foreach ($media_index as $speechID => $speechData) {
		if ($media_index[$speechID]["sitzungsnummer"] == (string)$targetSessionNumber &&
			$media_index[$speechID]["nextSpeechID"] == 'last') {
			$previousSpeech = $speechID;
			break;
		}
	}

	return $previousSpeech;
}

/*
 * Replaces special characters in a string with their "non-special" counterpart.
 *
 * Useful for friendly URLs.
 *
 * @access public
 * @param string
 * @return string
 */
function convertAccentsAndSpecialToNormal($string) {
	$table = array(
		'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Ă'=>'A', 'Ā'=>'A', 'Ą'=>'A', 'Æ'=>'A', 'Ǽ'=>'A',
		'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'ă'=>'a', 'ā'=>'a', 'ą'=>'a', 'æ'=>'a', 'ǽ'=>'a',

		'Þ'=>'B', 'þ'=>'b', 'ß'=>'s',

		'Ç'=>'C', 'Č'=>'C', 'Ć'=>'C', 'Ĉ'=>'C', 'Ċ'=>'C',
		'ç'=>'c', 'č'=>'c', 'ć'=>'c', 'ĉ'=>'c', 'ċ'=>'c',

		'Đ'=>'Dj', 'Ď'=>'D', 'Đ'=>'D',
		'đ'=>'dj', 'ď'=>'d',

		'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ĕ'=>'E', 'Ē'=>'E', 'Ę'=>'E', 'Ė'=>'E',
		'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ĕ'=>'e', 'ē'=>'e', 'ę'=>'e', 'ė'=>'e',

		'Ĝ'=>'G', 'Ğ'=>'G', 'Ġ'=>'G', 'Ģ'=>'G',
		'ĝ'=>'g', 'ğ'=>'g', 'ġ'=>'g', 'ģ'=>'g',

		'Ĥ'=>'H', 'Ħ'=>'H',
		'ĥ'=>'h', 'ħ'=>'h',

		'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'İ'=>'I', 'Ĩ'=>'I', 'Ī'=>'I', 'Ĭ'=>'I', 'Į'=>'I',
		'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'į'=>'i', 'ĩ'=>'i', 'ī'=>'i', 'ĭ'=>'i', 'ı'=>'i',

		'Ĵ'=>'J',
		'ĵ'=>'j',

		'Ķ'=>'K',
		'ķ'=>'k', 'ĸ'=>'k',

		'Ĺ'=>'L', 'Ļ'=>'L', 'Ľ'=>'L', 'Ŀ'=>'L', 'Ł'=>'L',
		'ĺ'=>'l', 'ļ'=>'l', 'ľ'=>'l', 'ŀ'=>'l', 'ł'=>'l',

		'Ñ'=>'N', 'Ń'=>'N', 'Ň'=>'N', 'Ņ'=>'N', 'Ŋ'=>'N',
		'ñ'=>'n', 'ń'=>'n', 'ň'=>'n', 'ņ'=>'n', 'ŋ'=>'n', 'ŉ'=>'n',

		'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ō'=>'O', 'Ŏ'=>'O', 'Ő'=>'O', 'Œ'=>'O',
		'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ō'=>'o', 'ŏ'=>'o', 'ő'=>'o', 'œ'=>'o', 'ð'=>'o',

		'Ŕ'=>'R', 'Ř'=>'R',
		'ŕ'=>'r', 'ř'=>'r', 'ŗ'=>'r',

		'Š'=>'S', 'Ŝ'=>'S', 'Ś'=>'S', 'Ş'=>'S',
		'š'=>'s', 'ŝ'=>'s', 'ś'=>'s', 'ş'=>'s',

		'Ŧ'=>'T', 'Ţ'=>'T', 'Ť'=>'T',
		'ŧ'=>'t', 'ţ'=>'t', 'ť'=>'t',

		'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ũ'=>'U', 'Ū'=>'U', 'Ŭ'=>'U', 'Ů'=>'U', 'Ű'=>'U', 'Ų'=>'U',
		'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ü'=>'u', 'ũ'=>'u', 'ū'=>'u', 'ŭ'=>'u', 'ů'=>'u', 'ű'=>'u', 'ų'=>'u',

		'Ŵ'=>'W', 'Ẁ'=>'W', 'Ẃ'=>'W', 'Ẅ'=>'W',
		'ŵ'=>'w', 'ẁ'=>'w', 'ẃ'=>'w', 'ẅ'=>'w',

		'Ý'=>'Y', 'Ÿ'=>'Y', 'Ŷ'=>'Y',
		'ý'=>'y', 'ÿ'=>'y', 'ŷ'=>'y',

		'Ž'=>'Z', 'Ź'=>'Z', 'Ż'=>'Z', 'Ž'=>'Z',
		'ž'=>'z', 'ź'=>'z', 'ż'=>'z', 'ž'=>'z'
	);

	$string = strtr($string, $table);
	// Currency symbols: £¤¥€  - we dont bother with them for now
	$string = preg_replace("/[^\x9\xA\xD\x20-\x7F]/u", "", $string);

	return $string;
}

?>