<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 2020 05:00:00 GMT');
header('Content-type: application/json');

error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

include_once("config.php");

$return['status'] = 'fail';
$return['code'] = '404';
$return['string'] = 'No action was taken';

switch($_REQUEST['a']) {

	case "getQueue":
		$queueFiles = array_values(array_diff(scandir($conf["dir"]["input"]), array('.', '..', '.DS_Store', '_index.json', '.gitignore')));

		if ($queueFiles) {
			$return['status'] = 'success';
			$return['code'] = '1';
			$return['string'] = 'Queue attached';
			$return["items"] = $queueFiles;
		} else {
			$return['status'] = 'success';
			$return['code'] = '2';
			$return['string'] = 'Queue empty';
			$return["items"] = array();
		}

		echo json_encode($return,JSON_PRETTY_PRINT);
		
	break;

	case "removeFromQueue":

		$queueFiles = array_values(array_diff(scandir($conf["dir"]["input"]), array('.', '..', '.DS_Store', '_index.json')));
		unlink($conf["dir"]["input"]."/".$queueFiles[$_REQUEST["item"]]);

		echo json_encode($return,JSON_PRETTY_PRINT);

	break;

	case 'uploadProtocol':
		if (count($_FILES["protokoll"]["name"]) < 1) {
			$return['status'] = 'fail';
			$return['code'] = '2';
			$return['string'] = 'No files for upload selected';
		} else {
			$errors = 0;
			foreach ($_FILES["protokoll"]["name"] as $fileKey=>$fileName) {
				if (!move_uploaded_file($_FILES["protokoll"]["tmp_name"][$fileKey], $conf["dir"]["input"]."/".$fileName)) {
					$errors++;
				}
				if ($_FILES["protokoll"][$fileKey]["error"] != 0) {
					$errors++;
				}
			}
			if ($errors===0) {
				$return['status'] = 'success';
				$return['code'] = '1';
				$return['string'] = 'File(s) have been uploaded. Reload queue.';
			} else {
				$return['status'] = 'fail';
				$return['code'] = '2';
				$return['string'] = 'Error occured. '.$errors.' Please check logs and try again.';
			}
		}

		echo json_encode($return,JSON_PRETTY_PRINT);

	break;

	case 'processQueue':
		include_once("queue.php");
		$return = processQueue();
		break;

	default:
		$return['status'] = 'success';
		$return['code'] = 0;
		$return['string'] = 'Action not recognized.';

		echo json_encode($return,JSON_PRETTY_PRINT);

		break;
}

?>