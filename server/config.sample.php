<?php

$conf["dir"]["input"] = "../input";
$conf["dir"]["output"] = "../output";
$conf["dir"]["opendata"] = "../cache";
$conf["dir"]["tmp"] = "../cache";
$conf["dir"]["cacheaudio"] = "../cache";

$conf["sleep"] = 0;

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    
    $conf["serverType"] = "windows";

    $conf["pythonDir"] = "C:\Python27";
	$conf["pythonDirScripts"] = "C:\Python27\Scripts";
	$conf["eSpeak"] = "C:\Program Files (x86)\\eSpeak\command_line";
	$conf["ffmpeg"] = "C:\Program Files (x86)\FFmpeg\bin";

} else {
    
    $conf["serverType"] = "unix";

    $conf["pythonDir"] = "/usr/local/bin";
	$conf["pythonDirScripts"] = "/usr/local/bin";
	$conf["eSpeak"] = "/usr/local/bin";
	$conf["ffmpeg"] = "/usr/local/bin";

}

/*
$conf["dbpedia"] = true;
$conf["dbpedia_confidence"] = 0.8;
$conf["dbpedia_types"] = "DBpedia:Currency,DBpedia:Device,DBpedia:Disease,DBpedia:Drug,DBpedia:EthnicGroup,DBpedia:Event,DBpedia:Holiday,DBpedia:MeanOfTransportation,DBpedia:Name,DBpedia:Organisation,DBpedia:Person,DBpedia:Place,DBpedia:Project,DBpedia:Work";
*/

$conf["annotationPattern"] =
	array(
		"ds" => array(
			"type"=>"document",
			"kind"=>"drucksache",
			"pattern"=>"/(\d{2}\/\d{1,6})/",
			"annotationBody"=>array("type"=> "Text",
						"frametrail:type"=> "webpage",
						"format"=> "text/html",
						"source"=> "",
						"frametrail:name"=> "",
						"frametrail:thumb"=> null,
						"frametrail:attributes"=> new ArrayObject())
		),
		"gesetz" => array(
			"type"=>"word",
			"kind"=>"gesetz",
			"pattern"=>"/([A-Z]\w*gesetz)/",
			"annotationBody"=>array("type"=> "Text",
				"frametrail:type"=> "wikipedia",
				"format"=> "text/html",
				"source"=> "",
				"frametrail:name"=> "",
				"frametrail:thumb"=> null,
				"frametrail:attributes"=> new ArrayObject())
		),
		"wahl" => array(
			"type"=>"word",
			"kind"=>"wahlen",
			"pattern"=>"/([A-Z]\w*wahl)/",
			"annotationBody"=>array("type"=> "Text",
				"frametrail:type"=> "wikipedia",
				"format"=> "text/html",
				"source"=> "",
				"frametrail:name"=> "",
				"frametrail:thumb"=> null,
				"frametrail:attributes"=> new ArrayObject())
		)
	);

$ppl = file_get_contents(__DIR__."/../index_people.json");

$ppl = json_decode($ppl,true);
foreach($ppl as $pk=>$pv) {
	$conf["annotationPattern"][$pk."_ppl"] = array(
		"type"=>"person",
		"kind"=>"person",
		"pattern"=>"/".$pv["first_name"]."\s".$pv["last_name"]."/",
		"annotationBody"=>array("type"=> "Text",
			"frametrail:type"=> "webpage",
			"format"=> "text/html",
			"value"=> "https://embed.abgeordnetenwatch.de/profile/".$pv["aw_username"],
			"frametrail:name"=> $pv["degree"]." ".$pv["first_name"]." ".$pv["last_name"],
			"frametrail:thumb"=> $pv["picture"]["url"],
			"frametrail:attributes"=> new ArrayObject())
	);
}



?>