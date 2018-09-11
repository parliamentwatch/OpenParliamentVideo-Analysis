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

		$alleTOPs = $xmlData->xpath('//tagesordnungspunkt');
		
		$index_media_json = [];
		$return = [];


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

				if ($topString[0] && preg_match("/(Eidesleistung )|(Befragung der Bundesregierung)|(Befr agung der Bundesregierung)|(Fragestunde)|(Wahl der )|(Wahl des )/", $topString[0])) {
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

						if (!empty($rede->xpath('p//redner//titel'))) {
							$titel = $rede->xpath('p//redner//titel')[0];
						}

						$response = array(  'message' => "Indexing: Period ".$wahlperiode." | Session: ". $sitzungsnummer." | Agenda Item ".$topCnt."/".count($alleTOPs)." | Speech ".$speechCnt."/".count($alleReden)." ( ".$vorname." ".$nachname." )",
							'task' => 'speechStatus',
							'status' => '',
							'progress' => 0);
						echo json_encode($response);

						$rednerID = (string)$rede->xpath("p//redner")[0]["id"];
						//print_r($rednerID);
						//$alleRednerReden = $tagesordnungspunkt->xpath("/redner[@id='".$rednerID."']");
						
						unset($alleRednerReden);
						$alleRednerReden = $tagesordnungspunkt->xpath("rede//p[1]//redner[@id='".$rednerID."']");
						//$alleRednerReden = $xmlData->xpath("//tagesordnungspunkt/rede//p//redner[@id='".$rednerID."'][1]");
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
						//if (!isset($rede['media-id'])) {
						if (true) {

							$rssContent = getMediaIDfromRSS($wahlperiode, $sitzungsnummer, $top, $vorname, $nachname, $titel, $sleep, $redeIndex);
							$mediaID = $rssContent["mediaID"];
							$headline = $rssContent["headline"];

							// Doublecheck via TOC if no media ID could be found
							sleep($sleep);
							
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
											$headline = $rssContent["headline"];
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


						forceAlignXMLData($rede->asXML(),$xmlData);




						unset($tmpObj);

						$tmpDS = [];
						$dsAll = $tagesordnungspunkt->xpath("p[@klasse='T_Drs']");
						foreach ($dsAll as $ds) {
							preg_match_all("/(\d{2}\/\d{2,6})/",(string)$ds[0],$dsArray);
							$tmpDS = array_merge($tmpDS, $dsArray[0]);
						}

						$currMediaID = ((string)$mediaID) ? (string)$mediaID : (string)$rede['media-id'];
						$duration = getAudioDuration($conf["dir"]["cacheaudio"].'/'.$currMediaID.'_mp3_128kb_stereo_de_128.mp3');

						$tmpObj["mediaID"] = $currMediaID;
						$tmpObj["duration"] = $duration;
						$tmpObj["id"] = (string)$rede["id"];
						//$tmpObj["vorname"] = (string)$vorname;
						//$tmpObj["nachname"] = (string)$nachname;
						$tmpObj["rednerID"] = (string)$rede->p->redner["id"];
						$tmpObj["wahlperiode"] = (string)$wahlperiode;
						$tmpObj["sitzungsnummer"] = (string)$sitzungsnummer;
						$tmpObj["date"] = (string)$xmlData->xpath('//kopfdaten//datum')[0]["date"];
						$tmpObj["ds"] = $tmpDS;
						$tmpObj["top"] = (string)$top;
						$tmpObj["toptitle"] = $topArr;
						$tmpObj["headline"] = $headline;

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

	// Fix Namen
	$vorname = str_replace('Alterspräsident ', '', $vorname);
	$vorname = str_replace('Alterspraesident ', '', $vorname);
	$vorname = str_replace('Altersprasident ', '', $vorname);
	$vorname = str_replace('Dr. ', '', $vorname);
	$vorname = str_replace('Graf ', '', $vorname);
	$vorname = str_replace(' Graf', '', $vorname);
	$nachname = str_replace('der ', '', $nachname);
	$vorname = str_replace(' Freiherr von', '', $vorname);
	$nachname = str_replace('Freiherr von', '', $nachname);
	$nachname = str_replace('von ', '', $nachname);
	$nachname = str_replace('de Vries', 'Vries', $nachname);

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
	// Fix Ende

	$top = str_replace('   ', ' ', $top);

	$topShortID = getTopShortID($top);
	if (preg_match("/(Epl)/", $topShortID)) {
		$searchString = $topShortID;
	} else {
		$searchString = 'TOP: '.$topShortID;
	}

	$nachnameClean = urlencode(convertAccentsAndSpecialToNormal($nachname));
	$vornameClean = urlencode(convertAccentsAndSpecialToNormal($vorname));

	$rssURL = 'http://webtv.bundestag.de/player/bttv/news.rss?lastName='.$nachnameClean.'&firstName='.$vornameClean.'&meetingNumber='.urlencode($sitzungsnummer).'&period='.urlencode($wahlperiode);

	$response = array(  'message' => 'Retrieving Media ID from '.$rssURL.', Index: '.$redeIndex.', Search String: '.$searchString,
		'task' => 'getmediainfo',
		'status' => '',
		'progress' => 0);
	echo json_encode($response);

	$rssResult = simplexml_load_file($rssURL);
	

	$allItems = $rssResult->xpath('//item');
	
	if (count($allItems) > 1 && strlen($top) > 1) {

		$allItemsReverse = array_reverse($allItems);
		$tmpMatches = array();
		$tmpHeadline = array();

		foreach ($allItemsReverse as $item) {
			
			$description = $item->description[0];
			$description = str_replace('  ', ' ', $description);
			
			if (preg_match("/(".$searchString.")/", $description)) {
				$link = $item->link;
				$tmp = explode('/', $link);
				$tmpArr = preg_split("/".$searchString."\:\s/",$description);
				$tmpHeadline[] = array_pop($tmpArr);
				$mediaID = array_pop($tmp);

				array_push($tmpMatches,$mediaID);
			}

		}
		//print_r($tmpMatches);

		return array("mediaID"=>$tmpMatches[(($redeIndex)?$redeIndex:0)],"headline"=>$tmpHeadline[(($redeIndex)?$redeIndex:0)]);

	} elseif (count($allItems) == 1 && strlen($top) > 1) {

		$description = $allItems[0]->description[0];
		$description = str_replace('  ', ' ', $description);
		$link = $allItems[0]->link;
		
		if (preg_match("/(".$searchString.")/", $description)) {
			$tmp = explode('/', $link);
			$mediaID = array_pop($tmp);
			$tmpArr = preg_split("/".$searchString."\:\s/",$description);
			$tmpHeadline = array_pop($tmpArr);
			return array("mediaID"=>$mediaID,"headline"=>$tmpHeadline);
		}

	}

	return null;
	
}

/**
 * @param $top
 * @return string
 */
function getTopShortID($top) {
	
	$topParts = explode(' ', $top);

	if (preg_match('/^I\./', $top)) {
		return $topParts[0];
	}

	$topType = $topParts[0];
	$topID = $topParts[1];

	if (preg_match('/-/', $topID)) {
		$topIDArray = explode('-', $topID);
		$topIDStart = (int) $topIDArray[0];
		$topIDEnd = (int) $topIDArray[1];

		$count = $topIDStart;
		$topID = $topIDArray[0];
		for($i=$topIDStart+1; $i<$topIDEnd; $i++) {
			$topID .= ','.$i;
		}
	}

	if ($topType == 'Zusatzpunkt') {
		return 'ZP '.$topID;
	} else if ($topType == 'Einzelplan') {
		return 'Epl '.$topID;
	} else {
		return ''.$topID;
	}
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