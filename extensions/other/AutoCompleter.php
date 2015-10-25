<?php
/**
 * @package MediaWiki
 * @subpackage StructuredNamespaces
 */
require_once('includes/memcached-client.php');
require_once('includes/Sanitizer.php');
require_once('includes/normal/UtfNormalUtil.php');

if (!defined('UTF8_REPLACEMENT')) {
	define( 'UTF8_REPLACEMENT', "\xef\xbf\xbd");
}

class AutoCompleter {
	const RESULTS_TAG = 'results';
	const EXP_TIME = 3600;
	const LIMIT = 100;

	public static $nsToNsid = array('(main)' => 0,
         'user' => 2,
	      'image' => 6,
	      'surname' => 102,
	      'source' => 104,
	      'place' => 106,
	      'person' => 108,
	      'family' => 110,
	      'mysource' => 112,
	      'repository' => 114,
	      );

	public static $nsidToNs = array(0 => '(Main)',
	      110 => 'Family',
	      6 => 'Image',
	      112 => 'MySource',
	      114 => 'Repository',
	      108 => 'Person',
	      106 => 'Place',
	      104 => 'Source',
	      102 => 'Surname',
          2 => 'User',
	      );

	private static function generateDBKey($title, $conn) {
      if (get_magic_quotes_gpc()) {
         $title = stripslashes($title);
      }
      $title = Sanitizer::decodeCharReferences($title);
	   $title = mb_strtoupper(mb_substr($title, 0, 1)) . mb_substr($title, 1);
      return mysql_real_escape_string(str_replace(' ', '_', $title), $conn);
	}

	private static function generateCacheKey($userid, $ns, $dbKey) {
  		return "ac:$userid:$ns:$dbKey";
	}

	private static function escape($s) {
	   return str_replace(array('&', '<', '>', '"', "'", '_'), array('&amp;', '&lt;', '&gt;', '&quot;', '&apos;', ' '), $s);
	}

	private static function lookupDB($conn, $userid, $ns, $nsid, $dbKey) {
	   if ($ns) {
	      $ns .= ':';
	   }
	   $likeKey = str_replace(array('%','_'), array('\%','\_'), $dbKey);
	   if ($userid) {
	      $userid = mysql_real_escape_string($userid, $conn);
	      $sql = "select wl_title from watchlist where wl_user=$userid and wl_namespace=$nsid and wl_title like '$likeKey%' limit ".AutoCompleter::LIMIT;
	   }
	   else {
	     $sql = "select page_title from page where page_namespace=$nsid and page_title like '$likeKey%' and page_is_redirect=0 limit ".AutoCompleter::LIMIT;
	   }
	   $res = @mysql_query($sql, $conn);
	   if ($res === false) {
	      return false;
	   }
	   $xml = '';
	   $i = 0;
	   while ($row = mysql_fetch_array($res, MYSQL_NUM)) {
	      $title = AutoCompleter::escape($row[0]);
	      $xml .= "<result><title>$ns$title</title></result>\n";
	      $i++;
	   }
	   if ($i == AutoCompleter::LIMIT) {
	      $status = 'more';
	   }
	   else {
	      $status = 'success';
	   }
	   return '<'.AutoCompleter::RESULTS_TAG." status=\"$status\">\n".$xml.'</'.AutoCompleter::RESULTS_TAG.'>';
   }

	private static function lookupCache($cache, $cacheKey) {
		$result = $cache->get($cacheKey);
		if (!isset($result)) {
			$result = false;
		}
	   return $result;
	}

	private static function cacheResults($cache, $cacheKey, $results, $expTime) {
	   $cache->set($cacheKey, $results, $expTime);
	}

	public static function formatError($error) {
      return "<results status=\"$error\"></results>";
	}

	private static function generatePlaceCacheKey($title) {
  		return "ac:Place:$title";
	}

   // keep in sync with Place.php
   private static function placeAbbrevsClean($s) {
      $s = AutoCompleter::mb_str_replace($s, '_', ' '); // convert _'s back to spaces
      $s = preg_replace_callback(
         '/\(([^)]*)/',
         function ($matches) {
            return "(" . mb_convert_case($matches[1], MB_CASE_TITLE);
         },
         $s);
      $s = preg_replace('/\(Independent City\)|\(City Of\)/', "(City)", $s);
      $s = AutoCompleter::mb_str_replace($s, '(', ' ');
      $s = AutoCompleter::mb_str_replace($s, ')', ' ');
      $s = preg_replace('/\s+/', ' ', $s);
      $s = AutoCompleter::mb_str_replace($s, ' ,', ',');
      $s = trim($s);
      return $s;
    }

   // keep in sync with Place.php - but note commenting out the trim
   private static function placeAbbrevsCleanAbbrev($s) {
      // remove accents must be called before mb_strtolower; otherwise the German SS isn't handled correctly
      $s = AutoCompleter::removeAccents($s);
      $s = AutoCompleter::placeAbbrevsClean($s);
      $s = mb_strtolower($s);
      $s = AutoCompleter::mb_str_replace($s, "'", '');
      $s = preg_replace('/[^a-z0-9]/', ' ', $s);
      $s = preg_replace('/\s+/', ' ', $s);
      //$s = trim($s); // if $s ends in a space or comma we want to retain that as a trailing space for the like
      return $s;
    }

	private static function lookupPlaceDB($conn, $abbrev) {
		$abbrev = mysql_real_escape_string($abbrev, $conn);
		$sql = "SELECT name, title FROM place_abbrevs WHERE abbrev LIKE '$abbrev%' ORDER BY priority, CHAR_LENGTH(name) LIMIT 32";
		$res = @mysql_query($sql, $conn);
		if ($res === false) {
			return false;
		}
		$results = array();
		while ($row = mysql_fetch_array($res, MYSQL_NUM)) {
		   $name = $row[0];
		   $title = $row[1];
		   // has this name-title combination already been found?
		   $found = false;
		   foreach ($results as $result) {
		      if ($result['name'] == $name && $result['title'] == $title) {
		         $found = true;
		         break;
		      }
		   }
		   if (!$found) {
   		   $results[] = array('name' => $name, 'title' => $title);
   	   	if (count($results) == 8) {
      		   break;
      		}
   		}
		}
		$xml = '';
		foreach ($results as $result) {
		   $xml .= '<result><name>'.AutoCompleter::escape($result['name']).'</name><title>'.AutoCompleter::escape($result['title']).'</title></result>';
		}
	   return '<'.AutoCompleter::RESULTS_TAG." status=\"$status\">\n".$xml.'</'.AutoCompleter::RESULTS_TAG.'>';
   }

	public static function getPlaceResults($cache, $conn, $title) {
	   // clean title
		$title = AutoCompleter::placeAbbrevsCleanAbbrev($title);
		// trim title to 100
		$title = mb_substr($title, 0, 100);
		// lookup in cache
		$cacheKey = AutoCompleter::generatePlaceCacheKey($title);
		$results = AutoCompleter::lookupCache($cache, $cacheKey);
		if ($results === false) {
			// lookup in db
			$results = AutoCompleter::lookupPlaceDB($conn, $title);
			// cache results
         AutoCompleter::cacheResults($cache, $cacheKey, $results, AutoCompleter::EXP_TIME);
		}
		return $results;
	}

	/**
	 * Return autocomplete results as an XML string
	 *
	 * @param memcached $cache
	 * @param mysql connection $conn
	 * @param string $title
	 * @param int $userid
	 * @param string $nsDefault
	 * @return false in case of error
	 */
	public static function getResults($cache, $conn, $title, $userid='', $nsDefault='') {
		if ($nsDefault == 'Place') {
			return AutoCompleter::getPlaceResults($cache, $conn, $title);
		}

	   $ns = '';

      // validate title and split
      if (!$title) {
         return AutoCompleter::formatError('invalid title');
      }
      else if (strncasecmp($title, 'user:', 5) == 0) {
         $ns = 'user';
         $title = substr($title, 5);
      }
      else if (strncasecmp($title, 'image:', 6) == 0) {
         $ns = 'image';
         $title = substr($title, 6);
      }
      else if (strncasecmp($title, 'surname:', 8) == 0) {
         $ns = 'surname';
         $title = substr($title, 8);
      }
      else if (strncasecmp($title, 'givenname:', 10) == 0) {
         $ns = 'givenname';
         $title = substr($title, 10);
      }
      else if (strncasecmp($title, 'source:', 7) == 0) {
         $ns = 'source';
         $title = substr($title, 7);
      }
      else if (strncasecmp($title, 'place:', 6) == 0) {
         $ns = 'place';
         $title = substr($title, 6);
      }
      else if (strncasecmp($title, 'person:', 7) == 0) {
         $ns = 'person';
         $title = substr($title, 7);
      }
      else if (strncasecmp($title, 'family:', 7) == 0) {
         $ns = 'family';
         $title = substr($title, 7);
      }
      else if (strncasecmp($title, 'mysource:', 9) == 0) {
         $ns = 'mysource';
         $title = substr($title, 9);
      }
      else if (strncasecmp($title, 'repository:', 11) == 0) {
         $ns = 'repository';
         $title = substr($title, 11);
      }
      else if (strncasecmp($title, 'transcript:', 11) == 0) {
         $ns = 'transcript';
         $title = substr($title, 11);
      }

      // convert and validate ns
      if (!$ns) {
         $ns = $nsDefault;
      }
      $nsid = @AutoCompleter::$nsToNsid[strtolower($ns)];
		if (!$nsid) {
			$nsid = 0;
			$ns = '';
		}
		else {
		   $ns = AutoCompleter::$nsidToNs[$nsid];
		}

	   // convert and validate userid - must have userid unless searching source, repository, or place
      $isGlobal = ($nsid == 104 || $nsid == 106 || $nsid == 114);

	   if ($nsid == 104) { // is this still needed?
	   	$userid = 0;
	   }
	   else {
	   	$userid = intval($userid);
	   }
      if (!$isGlobal && $userid == 0) {
         return AutoCompleter::formatError('invalid userid');
      }

	   // generate db key
	   $dbKey = AutoCompleter::generateDBKey($title, $conn);
	   // look up in cache (sources only)
	   if ($isGlobal && $userid == 0) {
   	   // generate cache key
	     $cacheKey = AutoCompleter::generateCacheKey($userid, $nsid, $dbKey);
	     $results = AutoCompleter::lookupCache($cache, $cacheKey);
	   }
	   else {
	      $results = false;
	   }
	   if ($results === false) {
	     // lookup in db
	     $results = AutoCompleter::lookupDB($conn, $userid, $ns, $nsid, $dbKey);
	     if ($results === false) {
	        $results = AutoCompleter::formatError('mysql error');
	     }
	     else if ($isGlobal && $userid == 0) {
	        // cache results
	        AutoCompleter::cacheResults($cache, $cacheKey, $results, AutoCompleter::EXP_TIME);
	     }
	   }
	   return $results;
	}

   // keep in sync with StructuredData.php
   public static function mb_str_replace($haystack, $search, $replace, $offset=0) {
      $len_sch=mb_strlen($search);
      $len_rep=mb_strlen($replace);

      while (($offset=mb_strpos($haystack,$search,$offset))!==false) {
         $haystack=mb_substr($haystack,0,$offset)
                   .$replace
                   .mb_substr($haystack,$offset+$len_sch);
         $offset=$offset+$len_rep;
         if ($offset>mb_strlen($haystack)) break;
      }
      return $haystack;
   }

	// keep in sync with StructuredData.php
   public static function removeAccents($string) {
       if ( !preg_match('/[\x80-\xff]/', $string) )
           return $string;

       $chars = array(
       // Decompositions for Latin-1 Supplement
       chr(195).chr(128) => 'A', chr(195).chr(129) => 'A',
       chr(195).chr(130) => 'A', chr(195).chr(131) => 'A',
       chr(195).chr(132) => 'A', chr(195).chr(133) => 'A',
       chr(195).chr(135) => 'C', chr(195).chr(136) => 'E',
       chr(195).chr(137) => 'E', chr(195).chr(138) => 'E',
       chr(195).chr(139) => 'E', chr(195).chr(140) => 'I',
       chr(195).chr(141) => 'I', chr(195).chr(142) => 'I',
       chr(195).chr(143) => 'I', chr(195).chr(145) => 'N',
       chr(195).chr(146) => 'O', chr(195).chr(147) => 'O',
       chr(195).chr(148) => 'O', chr(195).chr(149) => 'O',
       chr(195).chr(150) => 'O', chr(195).chr(153) => 'U',
       chr(195).chr(154) => 'U', chr(195).chr(155) => 'U',
       chr(195).chr(156) => 'U', chr(195).chr(157) => 'Y',
       chr(195).chr(159) => 'ss', chr(195).chr(160) => 'a',
       chr(195).chr(161) => 'a', chr(195).chr(162) => 'a',
       chr(195).chr(163) => 'a', chr(195).chr(164) => 'a',
       chr(195).chr(165) => 'a', chr(195).chr(167) => 'c',
       chr(195).chr(168) => 'e', chr(195).chr(169) => 'e',
       chr(195).chr(170) => 'e', chr(195).chr(171) => 'e',
       chr(195).chr(172) => 'i', chr(195).chr(173) => 'i',
       chr(195).chr(174) => 'i', chr(195).chr(175) => 'i',
       chr(195).chr(177) => 'n', chr(195).chr(178) => 'o',
       chr(195).chr(179) => 'o', chr(195).chr(180) => 'o',
       chr(195).chr(181) => 'o', chr(195).chr(182) => 'o',
       chr(195).chr(182) => 'o', chr(195).chr(185) => 'u',
       chr(195).chr(186) => 'u', chr(195).chr(187) => 'u',
       chr(195).chr(188) => 'u', chr(195).chr(189) => 'y',
       chr(195).chr(191) => 'y',
       // Decompositions for Latin Extended-A
       chr(196).chr(128) => 'A', chr(196).chr(129) => 'a',
       chr(196).chr(130) => 'A', chr(196).chr(131) => 'a',
       chr(196).chr(132) => 'A', chr(196).chr(133) => 'a',
       chr(196).chr(134) => 'C', chr(196).chr(135) => 'c',
       chr(196).chr(136) => 'C', chr(196).chr(137) => 'c',
       chr(196).chr(138) => 'C', chr(196).chr(139) => 'c',
       chr(196).chr(140) => 'C', chr(196).chr(141) => 'c',
       chr(196).chr(142) => 'D', chr(196).chr(143) => 'd',
       chr(196).chr(144) => 'D', chr(196).chr(145) => 'd',
       chr(196).chr(146) => 'E', chr(196).chr(147) => 'e',
       chr(196).chr(148) => 'E', chr(196).chr(149) => 'e',
       chr(196).chr(150) => 'E', chr(196).chr(151) => 'e',
       chr(196).chr(152) => 'E', chr(196).chr(153) => 'e',
       chr(196).chr(154) => 'E', chr(196).chr(155) => 'e',
       chr(196).chr(156) => 'G', chr(196).chr(157) => 'g',
       chr(196).chr(158) => 'G', chr(196).chr(159) => 'g',
       chr(196).chr(160) => 'G', chr(196).chr(161) => 'g',
       chr(196).chr(162) => 'G', chr(196).chr(163) => 'g',
       chr(196).chr(164) => 'H', chr(196).chr(165) => 'h',
       chr(196).chr(166) => 'H', chr(196).chr(167) => 'h',
       chr(196).chr(168) => 'I', chr(196).chr(169) => 'i',
       chr(196).chr(170) => 'I', chr(196).chr(171) => 'i',
       chr(196).chr(172) => 'I', chr(196).chr(173) => 'i',
       chr(196).chr(174) => 'I', chr(196).chr(175) => 'i',
       chr(196).chr(176) => 'I', chr(196).chr(177) => 'i',
       chr(196).chr(178) => 'IJ',chr(196).chr(179) => 'ij',
       chr(196).chr(180) => 'J', chr(196).chr(181) => 'j',
       chr(196).chr(182) => 'K', chr(196).chr(183) => 'k',
       chr(196).chr(184) => 'k', chr(196).chr(185) => 'L',
       chr(196).chr(186) => 'l', chr(196).chr(187) => 'L',
       chr(196).chr(188) => 'l', chr(196).chr(189) => 'L',
       chr(196).chr(190) => 'l', chr(196).chr(191) => 'L',
       chr(197).chr(128) => 'l', chr(197).chr(129) => 'L',
       chr(197).chr(130) => 'l', chr(197).chr(131) => 'N',
       chr(197).chr(132) => 'n', chr(197).chr(133) => 'N',
       chr(197).chr(134) => 'n', chr(197).chr(135) => 'N',
       chr(197).chr(136) => 'n', chr(197).chr(137) => 'N',
       chr(197).chr(138) => 'n', chr(197).chr(139) => 'N',
       chr(197).chr(140) => 'O', chr(197).chr(141) => 'o',
       chr(197).chr(142) => 'O', chr(197).chr(143) => 'o',
       chr(197).chr(144) => 'O', chr(197).chr(145) => 'o',
       chr(197).chr(146) => 'OE',chr(197).chr(147) => 'oe',
       chr(197).chr(148) => 'R',chr(197).chr(149) => 'r',
       chr(197).chr(150) => 'R',chr(197).chr(151) => 'r',
       chr(197).chr(152) => 'R',chr(197).chr(153) => 'r',
       chr(197).chr(154) => 'S',chr(197).chr(155) => 's',
       chr(197).chr(156) => 'S',chr(197).chr(157) => 's',
       chr(197).chr(158) => 'S',chr(197).chr(159) => 's',
       chr(197).chr(160) => 'S', chr(197).chr(161) => 's',
       chr(197).chr(162) => 'T', chr(197).chr(163) => 't',
       chr(197).chr(164) => 'T', chr(197).chr(165) => 't',
       chr(197).chr(166) => 'T', chr(197).chr(167) => 't',
       chr(197).chr(168) => 'U', chr(197).chr(169) => 'u',
       chr(197).chr(170) => 'U', chr(197).chr(171) => 'u',
       chr(197).chr(172) => 'U', chr(197).chr(173) => 'u',
       chr(197).chr(174) => 'U', chr(197).chr(175) => 'u',
       chr(197).chr(176) => 'U', chr(197).chr(177) => 'u',
       chr(197).chr(178) => 'U', chr(197).chr(179) => 'u',
       chr(197).chr(180) => 'W', chr(197).chr(181) => 'w',
       chr(197).chr(182) => 'Y', chr(197).chr(183) => 'y',
       chr(197).chr(184) => 'Y', chr(197).chr(185) => 'Z',
       chr(197).chr(186) => 'z', chr(197).chr(187) => 'Z',
       chr(197).chr(188) => 'z', chr(197).chr(189) => 'Z',
       chr(197).chr(190) => 'z', chr(197).chr(191) => 's'
       );

       $string = strtr($string, $chars);

       return $string;
   }

}
?>
