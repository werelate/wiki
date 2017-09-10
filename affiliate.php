<?php
/**
 * @package MediaWiki
 * @subpackage StructuredNamespaces
 */
require_once('AdminSettings.php');

function utf8_urldecode($str) {
  $str = preg_replace("/%u([0-9a-f]{3,4})/i","&#x\\1;",urldecode($str));
  return html_entity_decode($str,null,'UTF-8');;
}

function queryAdServer($affil, $q) {
	global $wrAdServer;

	$fp = fopen("$wrAdServer/$affil/select?q=$q", 'r');
	$resultString = '';
	if ($fp) {
		while (!feof($fp)) {
			$resultString .= fread($fp, 16384);
		}
		fclose($fp);
	}
	return $resultString;
}
  
$surname = @$_GET['surname'];
$place = @$_GET['place'];
$adkey = @$_GET['adkey'];
$affil = @$_GET['affiliate'];
$results = '<results></results>';

// validate key
if (substr(md5("$surname|$place|$wrAdPassword"), 0, 16) == $adkey) {
	$surname = str_replace(array('?','*',':','+','-','[',']','(',')','~','^'),'', mb_strtolower($surname));
	if ($affil == 'footnote') {
		$results = queryAdServer($affil, urlencode($surname));
	}
	else if ($affil == 'amazon') {
		$place = str_replace(array('?','*',':','+','-','[',']','(',')','~','^'),'', mb_strtolower($place));
		$results = queryAdServer($affil, urlencode("$surname $place")."&qt=dismax&qf=title^1.0&mm=1&bf=recip(salesrank,.0001,10,10)");
	}
}

header('Content-type: text/xml');
print $results;
?>
