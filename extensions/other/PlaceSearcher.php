<?php
/**
 * @package MediaWiki
 * @subpackage StructuredNamespaces
 */
require_once('includes/memcached-client.php');
require_once('AdminSettings.php');

class PlaceSearcher {
	const EXP_TIME = 28800; // keep in sync with cache.ccf

	private static function prepareKey($key) {
		// titles don't need to be lowercased or noise-cleaned
//		$key = trim($key, ", \t\n\r");  // remove leading and trailing commas
		return 'pll:'.$key; 
	}

	private static function lookupCache($cache, $place) {
		$key = PlaceSearcher::prepareKey($place);
		$result = $cache->get($key);
		if (!isset($result)) {
			$result = false;
		}
		if ($result !== false) {
			$latlng = explode('|',$result);
			$result = array();
			$result['q'] = $place;
			$result['Latitude'] = $latlng[0];
			$result['Longitude'] = $latlng[1];
		}
		return $result;
	}

	private static function cacheResults($cache, $results) {
		foreach($results as $result) {
			$key = PlaceSearcher::prepareKey($result['q']);
			$value = @$result['Latitude'].'|'.@$result['Longitude'];
			$cache->set($key, $value, self::EXP_TIME);
		}
	}

	private static function postRequest($host, $port, $url, $query) {
	   $timeout = 2;
	   $contentString = 'q='.urlencode($query);
	   $contentLength = strlen($contentString);

	   $requestBody = "POST $url HTTP/1.0\r\nHost: $host\r\nContent-type: application/x-www-form-urlencoded\r\nContent-length: $contentLength\r\n\r\n$contentString";

	   $sh = fsockopen($host, $port, $errno, $errstr, $timeout)
	     or die("can't open socket to $host: $errno $errstr");
	   fputs($sh, $requestBody);
	   $response = '';
	   while (!feof($sh)) {
	      $response .= fread($sh, 16384);
	   }
	   fclose($sh) or die("can't close socket handle: $php_errormsg");
	   list($responseHeaders, $responseBody) = explode("\r\n\r\n", $response,2);
	   $responseHeaderLines = explode("\r\n", $responseHeaders);
	   // first line of headers is the HTTP response code
	   $httpResponseLine = array_shift($responseHeaderLines);
	   if (preg_match('@^HTTP/[0-9]\.[0-9] ([0-9]{3})@', $httpResponseLine, $matches)) {
	      $responseCode = $matches[1];
	   }
	   // put the rest of the headers into an array
//	   $responseHeaderArray = array();
//	   foreach ($responseHeaderLines as $headerLine) {
//	      list ($header, $value) = explode(': ', $headerLine, 2);
//	      $responseHeaderArray[$header] = $value;
//	   }
	   return array($responseCode, $responseBody);
	}

	// $placesQuery is an array of places to look up
	private static function lookupIndex($placesQuery, $resultFunction) {
	   global $wrSearchHost, $wrSearchPort, $wrSearchPath;
		$query = implode('|', $placesQuery);
		list($code, $result) = PlaceSearcher::postRequest($wrSearchHost, $wrSearchPort, "$wrSearchPath/$resultFunction", $query);
		if ($code != 200) {
		   die("search server returned bad response: $code for function: $resultFunction\n");
		}
      return $result;
	}

	private static function getSearchResults($cache, $query, $resultFunction) {
		$results = array();
		$placesQuery = array();

		$places = array_unique(explode('|',$query));
		
		if ($resultFunction == 'placelatlngtitle') { // look up in cache first
			foreach ($places as $place) {
				$cachedResult = PlaceSearcher::lookupCache($cache, $place);
				if ($cachedResult === false) {
					$placesQuery[] = $place;
				}
				else {
					$results[] = $cachedResult;
				}
			}
		}
		else {
			$placesQuery = $places;
		}

		if (count($placesQuery) > 0) {
			$resultString = PlaceSearcher::lookupIndex($placesQuery, $resultFunction);
			eval('$ixResults = ' . $resultString . ';');
			if ($ixResults && count($ixResults['response']) > 0) {
				if ($resultFunction == 'placelatlngtitle') {
					PlaceSearcher::cacheResults($cache, $ixResults['response']);
				}
				if (count($results) > 0) {
					foreach($ixResults['response'] as $r) {
						$results[] = $r;
					}
				}
				else {
					$results = $ixResults['response'];
				}
			}
		}
		return $results;
	}

	private static function placestandardize($result) {
	   return $result['PlaceTitle'];
	}

	private static function placelatlngtitle($result) {
	   return array('lat'=>@$result['Latitude'], 'lon'=>@$result['Longitude']);
	}

	private static function getPlaces($titles, $resultFunction) {
		global $wgMemc;

		$hashResults = array();
		if (count($titles) > 0) {
		   $query = implode('|', $titles);
			$searchResults = PlaceSearcher::getSearchResults($wgMemc, $query, $resultFunction);
			foreach ($searchResults as $result) {
				$title = $result['q'];
				$hashResults[$title] = PlaceSearcher::$resultFunction($result);
			}
		}
		return $hashResults;
	}

	/**
	 * Return a hash of title => corrected title for each passed-in title
	 * The hash is empty for a passed-in title if a unique matching place for the title isn't found
	 *
	 * @param array $titles
	 * @return unknown
	 */
	public static function correctPlaceTitles($titles) {
	   return PlaceSearcher::getPlaces($titles, 'placestandardize');
	}
	
	public static function correctPlaceTitlesMultiLine($text) {
		$text = trim(preg_replace('/ *\r?\n */', "\n", $text));
		$titles = preg_split('/[\n]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
		$lookupTitles = array();
		foreach ($titles as $title) {
			if (mb_strpos($title, '|') === false && !in_array($title, $lookupTitles)) {
				$lookupTitles[] = $title;
			}
		}
		return PlaceSearcher::correctPlaceTitles($lookupTitles);
	}

	/**
	 * Return a hash of title => lat:long for each passed-in title
	 * The hash is empty for a passed-in title if a unique matching place for the title isn't found
	 *
	 * @param array $titles
	 * @return unknown
	 */
	public static function getPlaceTitlesLatLong($titles) {
	   return PlaceSearcher::getPlaces($titles, 'placelatlngtitle');
	}
}
?>
