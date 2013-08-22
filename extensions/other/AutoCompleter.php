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
	      $title = str_replace(array('&', '<', '>', '"', "'", '_'), array('&amp;', '&lt;', '&gt;', '&quot;', '&apos;', ' '), $row[0]);
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
}
?>
