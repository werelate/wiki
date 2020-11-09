<?php
/**
 * @package MediaWiki
 * @subpackage StructuredNamespaces
 */
require_once("$IP/includes/GlobalFunctions.php");
require_once("$IP/includes/Parser.php");
require_once("$IP/includes/Title.php");
require_once("$IP/includes/Article.php");
require_once("$IP/AdminSettings.php");
require_once("$IP/extensions/structuredNamespaces/PropagationManager.php");

// define the edit flags to use for propagating edits
// can't set a const or a static var to an expression, so we have to use a define
define('PROPAGATE_EDIT_FLAGS', EDIT_UPDATE | EDIT_MINOR | EDIT_FORCE_BOT);

/**
 * Abstract class from which structured data handler classes can inherit basic functionality
 */
abstract class StructuredData {
   const PROPAGATE_MESSAGE = 'Changed page link to follow redirection';
   const PROPAGATE_MOVE_MESSAGE = 'Contents updated for the new namespace';
	protected $tagName;  // name of the tag containing the structured data; e.g., name
	protected $titleString; // title->getText() of page
	protected $ns; // namespace of the page
	protected $title; // title object for the page
	protected $footer;
	protected $xml; // holds the parsed XML data (set by loadPage)
	protected $pageContents; // holds the rest of the page contents (set by loadPage)
	protected $isGedcomPage; // true if this is a page from an in-process gedcom
   protected $pageLoaded; // true if the page has been loaded

   /**
    * Return namespace tag name given namespace number
    * @param int $ns namespace number
    * @return string namespace tag name
    */
   public static function getNamespaceTag($ns) {
       global $wgExtraNamespaces;
       return strtolower($wgExtraNamespaces[$ns]);
   }
   
   public static function endsWith($str, $sub, $ignoreCase = false) {
   	$strlen = mb_strlen($str);
   	$sublen = mb_strlen($sub);
   	if ($sublen <= $strlen) {
   		$str = mb_substr($str, $strlen - $sublen);
   		if ($ignoreCase) {
   			$str = mb_strtolower($str);
   			$sub = mb_strtolower($sub);
   		}
   		return ($str == $sub);
   	}
   	else {
   		return false;
   	}
   }

   public static function chomp($str,$sub) {
      $strlen = mb_strlen($str);
      $sublen = mb_strlen($sub);
      if ($sublen <= $strlen && mb_substr($str, $strlen - $sublen) == $sub) {
         return mb_substr($str, 0, $strlen - $sublen);
      }
      return $str;
   }

   public static function mapArraySearch($key, $mapArray, $field) {
   	for ($i = 0; $i < count($mapArray); $i++) {
   		if ((string)$mapArray[$i][$field] == $key) {
   			return $i;
   		}
   	}
   	return false;
   }

	private static $UPPERCASE_WORDS = array(
      "I"=>1, "II"=>1, "III"=>1, "IV"=>1, "V"=>1, "VI"=>1, "VII"=>1, "VIII"=>1, "IX"=>1, "X"=>1);
	private static $LOWERCASE_WORDS = array(
      "a"=>1, "an"=>1, "and"=>1, "at"=>1, "but"=>1, "by"=>1, "for"=>1, "from"=>1, "in"=>1, "into"=>1,
      "nor"=>1, "of"=>1, "on"=>1, "or"=>1, "over"=>1, "the"=>1, "to"=>1, "upon"=>1, "vs"=>1, "with"=>1,
      "against"=>1, "as"=>1, "before"=>1, "between"=>1, "during"=>1, "under"=>1, "versus"=>1, "within"=>1, "through"=>1, "up"=>1,
      // french
      "à"=>1, "apres"=>1, "après"=>1, "avec"=>1, "contre"=>1, "dans"=>1, "dès"=>1, "devant"=>1, "dévant"=>1, "durant"=>1, "de"=>1, "avant"=>1, "des"=>1,
      "du"=>1, "et"=>1, "es"=>1, "jusque"=>1, "le"=>1, "les"=>1, "par"=>1, "passe"=>1, "passé"=>1, "pendant"=>1, "pour"=>1, "pres"=>1, "près"=>1, "la"=>1,
      "sans"=>1, "suivant"=>1, "sur"=>1, "vers"=>1, "un"=>1, "une"=>1,
      // spanish
      "con"=>1, "depuis"=>1, "durante"=>1, "ante"=>1, "antes"=>1, "contra"=>1, "bajo"=>1,
      "en"=>1, "entre"=>1, "mediante"=>1, "para"=>1, "pero"=>1, "por"=>1, "sobre"=>1, "el"=>1, "o"=>1, "y"=>1,
      // dutch
      "aan"=>1, "als"=>1, "bij"=>1, "eer"=>1, "min"=>1, "na"=>1, "naar"=>1, "om"=>1, "op"=>1, "rond"=>1, "te"=>1, "ter"=>1, "tot"=>1, "uit"=>1, "voor"=>1,
      // german
      "auf"=>1, "gegenuber"=>1, "gegenüber"=>1, "gemäss"=>1, "gemass"=>1, "hinter"=>1, "neben"=>1,
      "über"=>1, "uber"=>1, "unter"=>1, "vor"=>1, "zwischen"=>1, "die"=>1, "das"=>1, "ein"=>1, "der"=>1,
      "ans"=>1, "aufs"=>1, "beim"=>1, "für"=>1, "fürs"=>1, "im"=>1, "ins"=>1, "vom"=>1, "zum"=>1, "am"=>1,
      // website extensions
      "com"=>1, "net"=>1, "org"=>1
   );
   private static $NAME_WORDS = array(
      "a"=>1, "à"=>1, "aan"=>1, "af"=>1, "auf"=>1,
      "bei"=>1, "ben"=>1, "bij"=>1,
      "contra"=>1,
      "da"=>1, "das"=>1, "de"=>1, "dei"=>1, "del"=>1, "della"=>1, "dem"=>1, "den"=>1, "der"=>1, "des"=>1, "di"=>1, "die"=>1, "do"=>1, "don"=>1, "du"=>1,
      "ein"=>1, "el"=>1, "en"=>1,
      "het"=>1,
      "im"=>1, "in"=>1,
      "la"=>1, "le"=>1, "les"=>1, "los"=>1,
      "met"=>1,
      "o"=>1, "of"=>1, "op"=>1,
      "'s"=>1, "s'"=>1, "sur"=>1,
      "'t"=>1, "te"=>1, "ten"=>1, "ter"=>1, "tho"=>1, "thoe"=>1, "to"=>1, "toe"=>1, "tot"=>1,
      "uit"=>1,
      "van"=>1, "ver"=>1, "von"=>1, "voor"=>1, "vor"=>1,
      "y"=>1,
      "z"=>1, "zum"=>1, "zur"=>1
   );
   private static $SPLITTER_REGEX = '([ `~!@#$%&_=:;<>,./{}()?+*|"\\-\\[\\]\\\\]+|[^ `~!@#$%&_=:;<>,./{}()?+*|"\\-\\[\\]\\\\]+)';
   
   // keep in sync with wikidata/Util.captitalizeTitleCase and gedcom/Utils.capitalizeTitleCase
   public static function capitalizeTitleCase($str, $isName = false, $mustCap = true) {
		$ret = '';
      if ($str) {
         mb_ereg_search_init($str,StructuredData::$SPLITTER_REGEX);
         $m=mb_ereg_search_regs();
         while ($m) {
           $w = $m[0];
           $ucw = mb_convert_case($w,MB_CASE_UPPER);
           $lcw = mb_convert_case($w,MB_CASE_LOWER);
           if ($isName && mb_strlen($w) > 1 && $w == $ucw) { // turn all-uppercase names into all-lowercase
              if (mb_strlen($w) > 3 && (mb_substr($w, 0, 2) == 'MC' || mb_substr($w,0,2) == "O'")) {
                 $w = mb_substr($w,0,1).mb_substr($lcw,1,1).mb_substr($w,2,1).mb_substr($lcw, 3);
              }
              else {
                 $w = $lcw;
              }
           }
           if (isset(StructuredData::$UPPERCASE_WORDS[$ucw]) || $w == $ucw) { // upper -> upper
             $ret .= $ucw;
           }
           else if (!$mustCap && isset(StructuredData::$NAME_WORDS[$lcw])) { // if w is a name-word, keep as-is
             $ret .= $w;
           }
           else if (!$isName && !$mustCap && isset(StructuredData::$LOWERCASE_WORDS[$lcw])) { // upper/mixed/lower -> lower
             $ret .= $lcw;
           }
           else if ($w == $lcw) { // lower -> mixed
             $ret .= mb_convert_case($w,MB_CASE_TITLE);
           }
           else { // mixed -> mixed
             $ret .= $w;
           }
           $m=mb_ereg_search_regs();
           $w = trim($w);
           $mustCap = !$isName && ($w == ':' || $w == '?' || $w == '!');
         }
      }
		return $ret;
   }

   public static function parseSurnamePieces($surname) {
      $result = array();
      $pieces = mb_split(' ', $surname);
      foreach ($pieces as $piece) {
         if (!isset(StructuredData::$NAME_WORDS[mb_strtolower($piece)])) {
            $result[] = $piece;
         }
      }
      return $result;
   }

   public static function purgeTitle($title, $fudgeSeconds=0) {
      global $wgUseSquid, $wrPurgingTitles;

		$ts = time() + $fudgeSeconds;
		if (!isset($wrPurgingTitles) || @$wrPurgingTitles[$title->getPrefixedText()] != $ts) {
	      $title->invalidateCache($ts);
	   	if ( $wgUseSquid ) {
	   		// Send purge
	   		$update = SquidUpdate::newSimplePurge( $title );
	   		$update->doUpdate();
	   	}
		}
		if (!isset($wrPurgingTitles)) {
			$wrPurgingTitles = array();
		}
		$wrPurgingTitles[$title->getPrefixedText()] = $ts;
   }
   

	public static function requestIndex($title) {
	// ??? Do I need a transaction around this?
//wfDebug("requestIndex title=".$title->getPrefixedText()."\n");
		$pageId = $title->getArticleID();
		if ($pageId > 0) {
			$ts = wfTimestampNow();
			$dbw =& wfGetDB( DB_MASTER );
			$dbw->insert('index_request', array('ir_page_id' => $pageId, 'ir_timestamp' => $ts));
		}
	}

	/**
	 * Replacement for Article:doWatch() that is more efficient because it doesn't save the user settings on every call
	 */
	public static function addWatch(&$user, &$article, $newRevision = false) {
		if( $user->isAnon() ) {
			return false;
		}
		if (wfRunHooks('WatchArticle', array(&$user, &$article))) {
			$user->addWatch( $article->mTitle );
			// $user->saveSettings(); // this seems wasteful; don't do it
			return wfRunHooks('WatchArticleComplete', array(&$user, &$article, $newRevision));
		}

		return false;
	}

	public static function removeWatch(&$user, &$article, $newRevision = false) {
		if( $user->isAnon() ) {
			return false;
		}

		if (wfRunHooks('UnwatchArticle', array(&$user, &$article))) {
			$user->removeWatch( $article->mTitle );
			// $user->saveSettings();
			return wfRunHooks('UnwatchArticleComplete', array(&$user, &$article, $newRevision));
		}

		return false;
	}

   public static function getWatchlistSummary($title, $text) {
      $mainNS = Namespac::getSubject($title->getNamespace());
      if ($mainNS == NS_PERSON) {
         $xml = StructuredData::getXml('person', $text);
         $summary = Person::getSummary($xml, $title);
      }
      else if ($mainNS == NS_FAMILY) {
         $xml = StructuredData::getXml('family', $text);
         $summary = Family::getSummary($xml, $title);
      }
      else {
         $summary = '';
      }
      return $summary;
   }

   public static function updateWatchlistSummary(&$title, $summary) {
      $dbw =& wfGetDB( DB_MASTER );
      $dbw->update( 'watchlist',
         array( /* SET */
            'wr_summary' => $summary
         ), array( /* WHERE */
            'wl_namespace' => $title->getNamespace(),
            'wl_title' => $title->getDBkey()
         ), 'updateWatchlistSummary'
      );
   }

   public static function removeAllWatchers($title) {
      $ns = $title->getNamespace();
      $dbkey = $title->getDBkey();
      $fname = 'removeAllWatchers';

      $dbw =& wfGetDB( DB_MASTER );
      $dbw->delete( 'watchlist',
         array(
            'wl_namespace' => ($ns & ~1),
            'wl_title' => $dbkey
         ), $fname
      );

      $dbw->delete( 'watchlist',
         array(
            'wl_namespace' => ($ns | 1),
            'wl_title' => $dbkey
         ), $fname
      );
   }

   public static function getWatchers($title, $offset=false, $limit=false) {
      global $wgUser;

      if ($offset === false && $limit !== false) $offset = 0;
      if ($limit === false && $offset !== false) $limit = 10000;
      $result = array();
      $skin = $wgUser->getSkin();
      $dbr =& wfGetDB(DB_SLAVE);
      $sql = 'SELECT user_name FROM user, watchlist where wl_namespace=' .  $dbr->addQuotes($title->getNamespace()) .
             ' AND wl_title=' .  $dbr->addQuotes($title->getDBkey()) . ' AND wl_user=user_id' .
             ($limit !== false ? " LIMIT $offset,$limit" : '');
      $rows = $dbr->query($sql);
      $errno = $dbr->lastErrno();
      while ($row = $dbr->fetchObject($rows)) {
         $title = Title::newFromText($row->user_name, NS_USER);
         $result[] = '<li>'.$skin->makeLinkObj($title, htmlspecialchars($title->getText())).'</li>';
      }
      $dbr->freeResult($rows);
      return $result;
   }

	/**
     * Escapes the specified string to prepare it for inclusion into an XML string
     * @param string $text the string to escape
     * @return string the escaped string
     */
	public static function escapeXml($text) {
		// copied to SpecialUpload
		return str_replace(
		array('&', '<', '>', '"', "'"),
		array('&amp;', '&lt;', '&gt;', '&quot;', '&apos;'), $text);
//		StructuredData::unescapeXml($text));
	}

	public static function unescapeXml($text) {
		// Technically, this function should replace &amp; last, so that a user-entered "A&amp;P" doesn't get converted
		// to "A&P", but we've had a bug where we've been double-escaping attribute values, so this bug here actually
		// serves to offset the effect of the other bug, and it's not so bad to have user-entered character entities
		// be converted.
		return str_replace(
		array('&amp;', '&lt;', '&gt;', '&quot;', '&apos;'),
		array('&', '<', '>', '"', "'"),
		$text);
	}

	/**
     * Given a tag name and some text, returns an XML object corresponding to the tag data within the text.
     * Returns null if the tag is not found in the text.
     * @param string $tagName the name of the tag to look for: <tagName>..</tagName>
     * @param string $text the text containing the tag
     * @return string the escaped string
     */
	public static function getXml($tagName, &$text) {
		// copied to SpecialUpload
		$start = strpos($text, "<$tagName>");
		if ($start !== false) {
			$end = strpos($text, "</$tagName>", $start);
			if ($end !== false) {
				// We expect only one tag instance; ignore any extras
				return simplexml_load_string(substr($text, $start, $end + 3 + strlen($tagName) - $start));
			}
		}
		return null;
	}

	/**
	 * Get Xml for a given title object
	 *
	 * @param title $title
	 * @param string $tagName
	 * @return xml object 
	 */
	public static function getXmlForTitle($tagName, $title) {
		if ($title) {
			$revision = Revision::newFromTitle($title);
			if ($revision) {
				$text =& $revision->getText();
				if ($text) {
					return StructuredData::getXml($tagName, $text);
				}
			}
		}
		return null;
	}

   // keep in sync with AutoCompleter.php
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

   // keep in sync with AutoCompleter.php
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

   /**
	 * Standardize case for names, in titles and in surname fields.
	 * If a name is entered as all-caps, turn it into mixed-case
	 *
	 * @param string $name
	 */
	public static function standardizeNameCase($name, $mustCap = true) {
      $name = StructuredData::capitalizeTitleCase($name, true, $mustCap);
      return trim(mb_ereg_replace('[. ]+', ' ', $name));
		// copied to SpecialUpload - used there still, but not here
//      $result = '';
//      $pieces = explode(' ', $name);
//      $cnt = count($pieces);
//      for ($i = 0; $i < $cnt; $i++) {
//         if ($pieces[$i]) {
//            if ($result) {
//               $result .= ' ';
//            }
//
//            if ($i < $cnt - 1 && strcasecmp($pieces[$i], 'and') == 0) {
//               $result .= 'and';
//            }
//            else if ($pieces[$i] == mb_strtoupper($pieces[$i]) || $pieces[$i] == mb_strtolower($pieces[$i])) {  // all uppercase or all lowercase
//               $result .= mb_convert_case($pieces[$i], MB_CASE_TITLE);
//            }
//            else {
//               $result .= $pieces[$i];
//            }
//         }
//      }
//		return $result;
	}

   // name should be in title case
  private static function isUnknownName($name) {
    $charsMeaningUnknown = array('?','_','-');
    return !$name || $name == 'Unknown' || $name == 'Unk' ||
            $name == 'N.N.' || $name == 'N.N' || $name == 'Nn' || $name == 'Nn.' || $name == 'N N' ||
            $name == 'Fnu' || $name == 'Lnu' || $name == 'Father' || $name == 'Mother' ||
            trim(str_replace($charsMeaningUnknown,'',$name)) == '';                                              // this condition added Nov 2020 by Janet Bjorndahl
  }
   
  public static function isUnknownNameValue($name) {                                                             // function added Nov 2020 by Janet Bjorndahl
    return self::isUnknownName($name) && trim($name) != '';
  }
  
  public static function hasUnknownNameValues($xml) {                                                            // function added Nov 2020 by Janet Bjorndahl
    if (isset($xml->name)) {
      if (self::isUnknownNameValue($xml->name['given']) || self::isUnknownNameValue($xml->name['surname'])) {
        return true;
      }
    }
    if (isset($xml->alt_name)) {
      foreach ($xml->alt_name as $name) {
        if (self::isUnknownNameValue($name['given']) || self::isUnknownNameValue($name['surname'])) {
          return true;
        }
      }
    }
  return false;
  }

	public static function constructName($gn, $sn) {
	   $gn = StructuredData::standardizeNameCase(trim(preg_replace('/[. ].*$/', '', $gn)), true);
      $sn = StructuredData::standardizeNameCase(trim(preg_replace('/\(\d+\)\s*$/', '', $sn)), false);
      $isGnUnknown = StructuredData::isUnknownName($gn);
      $isSnUnknown = StructuredData::isUnknownName($sn);
      if ($isGnUnknown && $isSnUnknown) {
         return 'Unknown';
      }
	   else if ($isGnUnknown) {
	      return "Unknown $sn";
	   }
	   else if ($isSnUnknown) {
	      return "$gn Unknown";
	   }
	   else {
	      return "$gn $sn";
	   }
	}
	
	private static function reversePlace($p) {
		$levels = mb_split(',', $p);
		$p = '';
		for ($i = count($levels)-1; $i >= 0; $i--) {
			if ($p) {
				$p .= ', ';
			}
			$p .= trim($levels[$i]);
		}
		return $p;
	}
	
	public static function constructPersonTitle($givenname, $surname) {
		$titleText = StructuredData::constructName($givenname, $surname);
		if ($titleText) {
			return StructuredData::appendUniqueId(Title::newFromText($titleText, NS_PERSON));
		}
		else {
			return null;
		}
	}

   public static function constructFamilyName($husbandName, $wifeName) {
      return "$husbandName and $wifeName";
   }

	public static function constructFamilyTitle($husbandGivenname, $husbandSurname, $wifeGivenname, $wifeSurname) {
		// construct name and append id
      $husbandName = StructuredData::constructName($husbandGivenname, $husbandSurname);
      $wifeName = StructuredData::constructName($wifeGivenname, $wifeSurname);
      $titleText = StructuredData::constructFamilyName($husbandName, $wifeName);
      if ($titleText) {
         return StructuredData::appendUniqueId(Title::newFromText($titleText, NS_FAMILY));
      }
      else {
      	return null;
      }
	}
	
	public static function parsePersonTitle($name) {
		$gn = $sn = '';
		$pos = strrpos($name, '(');
		if ($pos !== false) {
			$name = substr($name, 0, $pos);
		}
		$name = trim($name);
		$pos = strpos($name, ' ');
		if ($pos !== false) {
			$gn = substr($name, 0, $pos);
			$sn = trim(substr($name, $pos+1));
		}
		else {
			$sn = $name;
		}
      if ($gn == 'Unknown') $gn = '';
      if ($sn == 'Unknown') $sn = '';
		return array($gn, $sn);
	}
	
	public static function parseFamilyTitle($title) {
		$hg = $hs = $wg = $ws = '';
		$pieces = explode(' and ', $title);
		list ($hg, $hs) = StructuredData::parsePersonTitle($pieces[0]);
		if (count($pieces) > 1) {
			list ($wg, $ws) = StructuredData::parsePersonTitle($pieces[1]);
		}
		return array($hg, $hs, $wg, $ws);
	}
	
	public static function constructPlaceTitle($placeName, $locatedIn) {
		$placeSuffix = '';
		$placeName = trim($placeName);
		$locatedIn = trim($locatedIn);
		
		$pos = mb_strpos($placeName, '(');
		if ($pos !== false) {
			$placeSuffix = mb_convert_case(mb_substr($placeName, $pos), MB_CASE_LOWER);
			$placeName = trim(mb_substr($placeName, 0, $pos));
		}
		
		if ($placeName) {
			$placeName = StructuredData::capitalizeTitleCase($placeName);
		}
		
		if ($locatedIn) {
			$title = array();
			$title[] = $locatedIn;
			$stdTitle = PlaceSearcher::correctPlaceTitles($title);
			if (@$stdTitle[$locatedIn]) {
				$locatedIn = $stdTitle[$locatedIn];
			}
		}
		
		$stdName = '';
		if ($placeName) $stdName = $placeName;
		if ($placeSuffix) $stdName = ($stdName ? "$stdName $placeSuffix" : $placeSuffix);
		if ($locatedIn) $stdName = ($stdName ? "$stdName, $locatedIn" : $locatedIn);
		return Title::newFromText($stdName, NS_PLACE);
	}
	
   private static function cleanSourcePlace($place) {
   	$title = array();
   	$title[] = $place;
   	$stdTitle = PlaceSearcher::correctPlaceTitles($title);
   	if (@$stdTitle[$place]) {
   		$place = $stdTitle[$place];
   	}
   	return rtrim($place, ' .');
   }

   private static function cleanSourceAuthor($author) {
      $author = trim(preg_replace('/[^'.Title::legalChars().']/', '', $author));

   	// omit anon authors
   	$lcAuthor = mb_convert_case($author,MB_CASE_LOWER);
   	if ($lcAuthor == '' || $lcAuthor == 'anon' || $lcAuthor == 'anon.' || $lcAuthor == 'anonymous') {
   		return '';
   	}
   	
      // remvoe author role suffix
	   $matches = array();
	   if (preg_match('/[,;.]\s*(comp\.?|composer|compiler|translator|trans\.?|editor|ed\.)$/i', $author, $matches)) {
	   	$author = trim(mb_substr($author, 0, mb_strlen($author) - mb_strlen($matches[0])));
	   }
	   
	   // remove stuff within parentheses
	   if (preg_match('/\(.*?\)$/', $author, $matches)) {
	   	$author = trim(mb_substr($author, 0, mb_strlen($author) - mb_strlen($matches[0])));
	   }
		return StructuredData::standardizeNameCase(rtrim($author,' .'));
   }
   
   private static function cleanSourcePlaceIssued($place) {
      $place = trim(preg_replace('/[^'.Title::legalChars().']/', '', $place));
   	$t = Title::newFromText($place, NS_PLACE);
      if ($t) $place = $t->getText();
   	return rtrim($place, ' .');
   }

   private static function cleanSourceTitle($sourceTitle) {
      $sourceTitle = trim(preg_replace('/[^'.Title::legalChars().']/', '', $sourceTitle));

   	$matches = array();
	   if (preg_match('/^(a|an|the)\b/i', $sourceTitle, $matches)) {
	   	$sourceTitle = trim(mb_substr($sourceTitle, mb_strlen($matches[0])));
	   }
		return StructuredData::capitalizeTitleCase(rtrim($sourceTitle,' .'));
   }
   
   private static function cleanSourcePublisher($publisher) {
      $publisher = trim(preg_replace('/[^'.Title::legalChars().']/', '', $publisher));
	   // remove stuff within parentheses
	   $matches = array();
	   if (preg_match('\(.*?\)$/i', $publisher, $matches)) {
	   	$publisher = trim(mb_substr($publisher, 0, mb_strlen($publisher) - mb_strlen($matches[0])));
	   }
		return StructuredData::capitalizeTitleCase(rtrim($publisher,' .'));
   }
   
	public static function constructSourceTitle($sourceType, $sourceTitle, $author='', $place='', $placeIssued = '', $publisher = '') {
		$sourceTitle = StructuredData::cleanSourceTitle($sourceTitle);
		if (!$sourceType || !$sourceTitle) {
			return null;
		}
		
		$pageTitle = $sourceTitle;
		
		// Government / Church records -> place. title
		if ($sourceType == 'Government / Church records') {
			$place = StructuredData::cleanSourcePlace($place);
			if ($place) {
				$pageTitle = "$place. $sourceTitle";
			}
			else {
				return null;
			}
		}
		// Newspaper -> title (place issued)
		else if ($sourceType == 'Newspaper') {
			$placeIssued = StructuredData::cleanSourcePlaceIssued($placeIssued);
			if ($placeIssued) {
				$pageTitle = "$sourceTitle ($placeIssued)";
			}
		}
		// Periodical -> title (publisher)
		else if ($sourceType == 'Periodical') {
			$publisher = StructuredData::cleanSourcePlace($place);
			if ($publisher) $pageTitle = "$sourceTitle ($publisher)";
		}
		// Book or Article or Miscellaneous-> author. title
		else {
			$author = StructuredData::cleanSourceAuthor($author);
			if ($author) $pageTitle = "$author. $sourceTitle";
		}
		return Title::newFromText($pageTitle, NS_SOURCE);
	}
	
	public static function getYear($date, $includeModifiers=false) {
      $matches = array();
      $modifierMatch = '\b(bef|before|bet|btw|between|aft|after|abt|about|calc|calcd|calculated|est|estd|estimated)\b';
      $yearMatch = '(1[5-9]\d\d|20\d\d)';
      $yearSuffixMatch = '/\d{1,2}(?![0-9/])';
      $p = '#\b('.$modifierMatch.')?.*?('.$yearMatch.'('.$yearSuffixMatch.')?(\s*(-|and)\s*'.$yearMatch.'('.$yearSuffixMatch.')?)?)\b#i';
      if ($includeModifiers && preg_match($p, $date, $matches)) {
         return trim($matches[1].' '.$matches[3]);
      }
      else if (preg_match('#(^|\D)'.$yearMatch.'(\D|$)#', $date, $matches)) {
	      return $matches[2];
	   }
	   return false;
	}

	/**
	 * Return fullname constructed from name pieces in the specified XML node
	 *
	 * @param xml/object $name
	 */
	public static function getFullname($name, $addSlashes = false) {        // changed to replace blanks with underscores Nov 2020 by Janet Bjorndahl
    $prefix = (string)@$name['title_prefix'];
    $given = (string)@$name['given'];
    $surname = (string)@$name['surname'];
		$suffix = (string)@$name['title_suffix'];
		if ($suffix) {
			$suffix = ', ' . $suffix;
		}
   // Replace blanks in given name or surname with underscores - but only if there is other data (and thus we don't want to return the page title instead).
    if ( !$given && ($surname || $prefix || $suffix ) ) {
      $given = '_____';
    }
    if ( !$surname && ($given || $prefix || $suffix ) ) {
      $surname = '_____';
    }
		$fullname = trim($prefix . ' ' . $given . ' ' . ($addSlashes ? '/' : '') . $surname . ($addSlashes ? '/' : '') . $suffix);
//		$fullname = trim((string)@$name['title_prefix'] . ' ' . (string)@$name['given'] . ' ' . 
//								($addSlashes ? '/' : '') . (string)@$name['surname'] . ($addSlashes ? '/' : '') . $suffix);
		return $fullname ? $fullname : (string)@$name['title'];
	}
	
	/**
	 * Return the title that is the final redirect for the specified title, or return the specified title if there is no redirect for it
	 *
	 * @param Title $title
	 * @return Title
	 */
	public static function getRedirectToTitle($title, $useMDB = false, $db = null) {
	   global $wgMemc;

	   // lookup  in cache first
	   $cacheKey = 'redir:' . $title->getNamespace() . ':' . $title->getDBkey();
	   if ($useMDB) {
	   	$cacheData = false;
	   }
	   else {
		   $cacheData = $wgMemc->get($cacheKey);
	   }
      $result = $title;
	   if (!isset($cacheData) || $cacheData === false) {
         if (!$db) {
	         $db =& wfGetDB($useMDB ? DB_MASTER : DB_SLAVE);
         }
     	   $pageIsRedirect = $db->selectField('page', 'page_is_redirect', array('page_namespace' => $title->getNamespace(), 'page_title' => $title->getDBkey()));
 	      $redirTitle = null;
     	   if ($pageIsRedirect) {
     	      $revision = StructuredData::getRevision($title, true, $useMDB);
     	      if ($revision) {
     	         $redirTitle = $revision->getTitle();
  	            $result = $redirTitle;
     	      }
     	   }
     	   // store redir result for 1 minute
         $r = $wgMemc->set($cacheKey, 
         						($redirTitle != null && ($redirTitle->getText() != $title->getText() || $redirTitle->getNamespace() != $title->getNamespace())) ? $redirTitle->getPrefixedText() : '',
         						60);
	   }
	   else if ($cacheData != '') {  // title found in cache, and is redirected
	   	$result = Title::newFromText($cacheData);
	   }
	   return $result;
	}
	
	// separate multiple tags with |'s
	// returns true if text was changed
	// Note: this won't detect cases where the same title is used in different tags, because we can't know which one to remove
	public static function removeDuplicateLinks($tags, &$text) {
		$seenLinks = array();
		$found = false;
		if (preg_match_all("$<($tags)[^>]*? title=\"(.+?)\"[^>]*>\n$", $text, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$key = $match[1].'|'.$match[2];
				if (@$seenLinks[$key]) {
					$seenLinks[$key]++;
					$found = true;
				}
				else {
					$seenLinks[$key] = 1;
				}
			}
		}
		if ($found) {
			foreach ($seenLinks as $key => $count) {
				if ($count > 1) {
					$fields = explode('|', $key);
					$text = preg_replace("$<{$fields[0]}[^>]*? title=\"".StructuredData::protectRegexSearch($fields[1])."\"[^>]*>\n$", '', $text, $count - 1);
				}
			}
		}
		return $found;
	}

	/**
     * Returns the current version of the article content for the specified title
     * USE THIS ONLY IF YOU WILL BE UPDATING THE ARTICLE
     * @param Title $title
     * @return Article
     */
	public static function getArticle($title, $followRedirects=false) {
		$article = null;
		if ($title && $title->exists()) {
			// create a new title from the passed-in title because the title is passed by reference, so later changes to it would affect the Article
			$newTitle =& Title::makeTitle($title->getNamespace(), $title->getDBkey());
			$newTitle->getArticleID(GAID_FOR_UPDATE); // make sure you read the master db for the pageid
			$article = new Article($newTitle, 0);
			if ($followRedirects) {
				$text =& $article->fetchContent();
				$error = '';
				$redirArticle = StructuredData::followRedirects($text, $title->getText(), $title->getNamespace(), $error, true, true, true);
				if ($error) {
					error_log("getArticle $error for: ".$title->getPrefixedText()."\n");
					return null;
				}
				if ($redirArticle) {
					$article = $redirArticle;
				}
			}
		}
		return $article;
	}

	public static function getRevision($title, $followRedirects=false, $useMDB=false) {
		$revision = null;
		if ($title) {
			// create a new title from the passed-in title because the title is passed by reference, so later changes to it would affect the Revision
			// !!! we're obviously not doing this; should we?
   		if ($useMDB) {
	   		$dbw =& wfGetDb(DB_MASTER);
   			$revision = Revision::loadFromTitle($dbw, $title);
   		}
   		else {
   			$revision = Revision::newFromTitle($title);
   		}
			if ($revision && $followRedirects) {
				$text =& $revision->getText();
				$error = '';
				$redirRevision = StructuredData::followRedirects($text, $title->getText(), $title->getNamespace(), $error, true, false, $useMDB);
				if ($error) {
					error_log("getRevision $error for: ".$title->getPrefixedText()."\n");
					return null;
				}
				if ($redirRevision) {
					$revision = $redirRevision;
				}
			}
		}
		return $revision;
	}

	/**
	 * Follow redirect (if any) in the text and return the target article.
	 * Set the error if there is an error in the redirects.
	 *
	 * @param String $text
	 * @param int $ns namespace of starting page
	 * @param String $error
	 * @param bool $redirTargetMustExist
	 * @param bool $returnArticle if true, this function returns an article; otherwise it returns a revision
	 * @return Revision/Article the final target of the redirects if there is a redirect and it exists
	 */
	private static function followRedirects($text, $titleString, $ns, &$error, $redirTargetMustExist=false, $returnArticle=false, $useMDB=false) {
		$rt = Title::newFromRedirect( $text );
		$ra = null; // Revision or Article
		if ($rt) {
			$seenTitles = array();
			$t = Title::newFromText($titleString, $ns);
			$seenTitles[] = $t->getPrefixedText();
		}
		// while redirected, follow redir but avoid loops and don't stray outside the namespace (redirecting mysource to source and source to repo is ok)
		while ($rt) {
			if ($rt->getInterwiki() != '' || ($rt->getNamespace() != $ns && !($ns == NS_MYSOURCE && $rt->getNamespace() == NS_SOURCE) 
																							 && !($ns == NS_SOURCE && ($rt->getNamespace() == NS_REPOSITORY || $rt->getNamespace() == NS_MAIN)))) {
				// !!! this is ugly; should check outside of namespace in caller, not here
				$error = "Redirect is outside of namespace";
				return null;
			}
			if (!$rt->exists()) {
				if ($redirTargetMustExist) {
					$error = "Redirect target does not exist";
				}
				return null;
			}
			$rtString = $rt->getPrefixedText();
			if (in_array($rtString, $seenTitles)) {
				$error = "Loop in redirects";
				return null;
			}
			$seenTitles[] = $rtString;
			if ($returnArticle) {
				$newTitle =& Title::makeTitle($rt->getNamespace(), $rt->getDBkey());
				$ra = new Article($newTitle, 0);
				$text =& $ra->fetchContent();
			}
			else {
				if ($useMDB) {
			  		$dbw =& wfGetDb(DB_MASTER);
					$ra = Revision::loadFromTitle($dbw, $rt);
				}
				else {
					$ra = Revision::newFromTitle($rt);
				}
				$text =& $ra->getText();
			}
			$rt = Title::newFromRedirect( $text );
		}
		return $ra;
	}

	/**
	 * Return true if title string ends with an (id)
	 *
	 * @param string $titleString
	 */
	public static function titleStringHasId($titleString) {
		// copied to SpecialUpload
		return preg_match('/\(\d+\)$/', $titleString);
	}
	
	/**
	 * Uses titleids table to append an id to the title that makes it unique
	 *
	 * @param Title $title
	 * @return Title with id appended
	 */
	public static function appendUniqueId($title, $dbw = null) {
	   if ($dbw == null) {
   		$dbw =& wfGetDb(DB_MASTER);
         $dbw->begin();
   		$endTx = true;
   		// don't ignore errors; we want them sent on up if we didn't get a dbw passed in
	   }
	   else {
	      $endTx = false;
	   }
		$conds = array('ti_namespace' => $title->getNamespace(), 'ti_title' => $title->getDBkey());
		$doUpdate = true;
		// read the title to see if we need to insert a new record or update an existing one
		$exists = $dbw->selectField('titleids', 'ti_id', $conds);
		$errno = 0;
		if ($exists === false) {
			// insert
			$doUpdate = false;
			$id = 1;
			$dbw->insert('titleids', array('ti_namespace' => $title->getNamespace(), 'ti_title' => $title->getDBkey(), 'ti_id' => $id));
         $errno = $dbw->lastErrno();
         if ($errno == 1062) { // MYSQL specific
            $doUpdate = true;
            $errno = 0;
         }
		}
		do {
			if ($errno == 0 && $doUpdate) {
				// update followed by select last_insert_id() to get id
				//   can't use the update function because it quotes last_insert_id(ti_id+1)
				$dbw->query('update titleids set ti_id=last_insert_id(ti_id+1) where ti_namespace='.$title->getNamespace().' and ti_title='.$dbw->addQuotes($title->getDBkey()));
            $errno = $dbw->lastErrno();
            if ($errno == 0) {
   				$id = $dbw->selectField('', 'last_insert_id()', null);
   				$errno = $dbw->lastErrno();
            }
			}
			if ($errno == 0) {
   			// given the id, generate a unique title
   			$titleId = Title::newFromText((string)$title->getText()." ($id)", $title->getNamespace());
	        	$doUpdate = true; // just in case we have to loop back around because somebody manually created a title with this id
			}
		} while($errno == 0 && $titleId->exists());

      if ($errno == 0) {
   		if ($endTx) {
 	    	   $dbw->commit();
   		}
 		}
 		else {
 		   if ($endTx) {
    		   $dbw->rollback();
 		   }
 		   $titleId = null;
		}

		return $titleId;
	}

	/**
	 * Return true if the text starts with #redirect
	 * @param text is passed by reference only for performance; don't change it
	 */
	public static function isRedirect(&$text) {
      return (Title::newFromRedirect($text) != null);
	}

	/**
     * Return true if the specified year is valid (3-4 digits or empty)
     * @param string $year
     * @return bool
     */
	public static function isValidYear($year, $allowPlaceholder = false) {
		return empty($year) || preg_match('/^-?\d{3,4}$/', $year) || ($allowPlaceholder && ($year == '?' || strtolower($year) == 'present'));
	}

	/**
     * Return true if the specified url is valid
     * @param string $url
     * @return bool
     */
	public static function isValidUrl($url) {
		return empty($url) || preg_match( '/^((http|https|ftp):\/\/)?[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z0-9]{1,5}((:[0-9]{1,5})?\/.*)?$/i' ,$url);
	}

	/**
	  * Return true if the string is empty, or has just spaces, or is_numeric with a zero value
	  */
	public static function isEmpty($val)
	{
		if (empty($val)) return true;
		if (trim($val) == '') return true;
		if (is_numeric($val) && $val == 0) return true;
		return false;
	}


	/**
     * Return true if the title is either empty or an existing article
     * @param string $name article title (without the namespace)
     * @param string $ns namespace (e.g., NS_PLACE)
     * @return bool
     */
	public static function isEmptyOrExists($name, $ns) {
		if (empty($name)) {
			return true;
		}
		$title = Title::newFromText((string)$name, $ns);
		return $title && $title->exists();
	}

	/**
	  * Return true if the specified title is a valid title
	  * @param string $title
	  * @return bool
	  */
	public static function isValidPageTitle($title) {
		return !preg_match('#^\s*[/.]#', $title) && !preg_match('/[<>{}\[\]|?+#_]/', $title);
	}

	/**
	 * Replaces the old string with the new string one time in the text
	 */
	public static function replaceOnce($old, $new, $text) {
		$pos = mb_strpos($text, $old);
	   if ($pos !== false) {
	      return mb_substr($text, 0, $pos) . $new . mb_substr($text, $pos + mb_strlen($old));
	   }
	   return $text;
	}

	/**
	   * Format a comma or semicolon-separated list as a list of page-local links
	   *
	   * @param String $value
	   * @return String contains comma-separated list of page-local links
	   */
	public static function formatAsLinks($value) {
		$result = '';
		$links = preg_split('/[;,]/', $value, -1, PREG_SPLIT_NO_EMPTY);
		foreach ($links as $link) {
			$link = trim($link);
			if ($link) {
				if ($result) {
					$result .= ', ';
				}
				$result .= "[[#$link|$link]]";
			}
		}
		return $result;
	}

	/**
	   * Convert the string into something that can be used in a regular expression
	   * @param string $s
	   * @return string
	   */
	public static function protectRegexSearch($s) {
		return str_replace(array(  '\\',  '$',  '^',  '.',  '[',  ']',  '|',  '(',  ')',  '?',  '*',  '+',  '{',  '}',  '-'),
		array('\\\\','\\$','\\^','\\.','\\[','\\]','\\|','\\(','\\)','\\?','\\*','\\+','\\{','\\}','\\-'), $s);
	}

	/**
	   * Convert the string into something that can be used in a regular expression
	   * @param string $s
	   * @return string
	   */
	public static function protectRegexReplace($s) {
		return str_replace(array('\\', '$'), array('\\\\', '\\$'), $s);
	}

	/**
	 * Return the title attributes of an xml list as an array
	 *
	 * @param unknown_type $xmlList
	 * @return array
	 */
	public static function getTitlesAsArray($xmlList, $attr='title') {
		$result = array();
		foreach ($xmlList as $e) {
			$result[] = (string)$e[$attr];
		}
		return $result;
	}

	/**
	 * return true if titles having IDs overlap
	 *
	 * @param array(string) $titlesA
	 * @param array(string) $titlesB
	 * @return boolean
	 */
	public static function titlesOverlap($titlesA, $titlesB) {
	   $intersection = array_intersect($titlesA, $titlesB);
	   foreach ($intersection as $title) {
	      if (StructuredData::titleStringHasId($title)) {
	         return true;
	      }
	   }
	   return false;
	}
	
	public static function titlesMissingId($titleArray) {
	   foreach ($titleArray as $t) {
	   	if (!StructuredData::titleStringHasId($t)) {
	   		return true;
	   	}
	   }
	   return false;
	}

   public static function titleExists($ns, $titleString) {
      $title = Title::newFromText($titleString, $ns);
      return $title->exists();
   }

   public static function titlesExist($ns, $titleArray) {
      foreach ($titleArray as $t) {
         if (!StructuredData::titleExists($ns, $t)) {
            return false;
         }
      }
      return true;
   }

	public static function addCategories($surnames, $places, $addPlaceCategories=true, $titleString='', $ns=NS_MAIN, $displayCats=false) {
      global $wrStdSurnames, $wrStdPlaces, $wgParser;
      // TODO figure out how to not disable caching
      // disable caching because we're passing wrStdSurnames and wrStdPlaces as globals
      $wgParser->disableCache();

	   $result = '';
	   $titleString = mb_strtolower($titleString);
	   $wrStdSurnames = array();
	   $wrStdPlaces = array();
	   $hlPlaces = array();
	   // standardize names and make them unique
	   foreach ($surnames as $surname) {
	      $s = (string)$surname;
	      if ($s) {
            // add an entry for the entire surname as well as separate entries for each piece
	         $wrStdSurnames[] = StructuredData::standardizeNameCase($s);
	         $surnamePieces = StructuredData::parseSurnamePieces($s);
	         foreach ($surnamePieces as $surnamePiece) {
	            $wrStdSurnames[] = StructuredData::standardizeNameCase($surnamePiece);
	         }
	      }
	   }
	   $wrStdSurnames = array_unique($wrStdSurnames);

	   // standardize places and make them unique
	   foreach ($places as $place) {
	      $p = (string)$place;
	      if ($p) {
            // if you change the transformation for titleText, change it also in UserPage.formatResearchLinks
            $fields = explode('|', $p);
            $titleText = trim($fields[0]);
            $t = Title::newFromText($titleText, NS_PLACE);
            if ($t) {
               $t = StructuredData::getRedirectToTitle($t);
               $titleText = $t->getText();
            }

	         $wrStdPlaces[] = $titleText;
	         $fields = explode(',', $titleText);
	         $hlPlace = trim($fields[count($fields)-1]);
	         if (count($fields) > 1 && strcasecmp($hlPlace,'united states') == 0) {
	            $hlPlace = trim($fields[count($fields)-2]);
	         }
	         $hlPlaces[] = $hlPlace;
	      }
	   }
	   $wrStdPlaces = array_unique($wrStdPlaces);
	   $hlPlaces = array_unique($hlPlaces);

	   // write out categories
	   $colon = ($displayCats ? ':' : '');
//	   foreach ($stdSurnames as $surname) {
//	      if ($result && $displayCats) {
//	         $result .= ', ';
//	      }
//		   $result .= "[[{$colon}Category:$surname surname" . ($displayCats ? "|$surname surname" : '') ."]]";
//		}
		if ($addPlaceCategories) {
   		foreach ($wrStdPlaces as $place) {
   	      if ($result && $displayCats) {
   	         $result .= ', ';
   	      }
   		   $result .= "[[{$colon}Category:$place" . ($displayCats ? "|$place" : '') ."]]";
   		}
		}
//		foreach ($hlPlaces as $place) {
//		   foreach ($stdSurnames as $surname) {
//		      $catName = "$surname in $place";
//		      if (!$displayCats && $titleString && $ns == NS_MAIN && mb_strtolower($catName) == $titleString) { // main Surname in Place article
//		         $catName .= "|*";
//		      }
//   	      if ($result && $displayCats) {
//   	         $result .= ', ';
//   	      }
//		      $result .= "[[{$colon}Category:$catName" . ($displayCats ? "|$catName" : '') ."]]";
//		   }
//		}
		return $result;
	}

   public static function getMoreLikeThis($title) {
      global $wrStdSurnames, $wrStdPlaces;

      $ns = $title->getNamespace();
      $result = array();
      if ($ns === NS_PERSON || $ns === NS_MAIN || $ns === NS_REPOSITORY || $ns === NS_MYSOURCE || $ns === NS_USER || $ns === NS_SOURCE ||
          $ns === NS_IMAGE  || $ns === NS_FAMILY || $ns === NS_TRANSCRIPT) {
         foreach ($wrStdSurnames as $surname) {
            $places = array();
            foreach ($wrStdPlaces as $place) {
               $link = '/wiki/Special:Search?ecp=e&sort=title&rows=200&cv=true&s='.urlencode($surname).'&p='.urlencode($place);
               $placeLevels = explode(',',$place);
               $places[] = '<a href="'.$link.'">'.htmlspecialchars($placeLevels[0]).'</a>';
            }
            $link = '/wiki/Special:Search?ecp=e&sort=title&rows=200&cv=true&s='.urlencode($surname);
            $a = '<a href="'.$link.'">'.htmlspecialchars($surname).'</a>';
            $result[] = array('link'=>$a, 'places'=>$places);
         }
      }
      return $result;
   }

   private static $MONTHS = array('january'=>1,'february'=>2,'march'=>3,'april'=>4,'may'=>5,'june'=>6,'july'=>7,'august'=>8,'september'=>9,'october'=>10,'november'=>11,'december'=>12,
                                  'jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'may'=>5,'jun'=>6,'jul'=>7,'aug'=>8,'sep'=>9,'oct'=>10,'nov'=>11,'dec'=>12,
                                  'febr'=>2,'sept'=>9);

   private static function isYear($y) {
      return ($y >= 100 && $y <= 5000);
   }

   private static function getAlphaMonth($mon) {
      return @StructuredData::$MONTHS[mb_strtolower($mon)];
   }

   private static function isDay($d) {
      return ($d >= 1 && $d <= 31);
   }

   private static function isNumMonth($m) {
      return ($m >= 1 && $m <= 12);
   }

   private static function isNextYear($year, $field) {
      if (strlen($field) > 4) return false;
      $y = "$year";
      if (($field === "00" && substr($y, 2) == "99") || ($field === "0" && substr($y, 3) == "9")) return true;
      $newYear = substr($y, 0, 4 - strlen($field)) . $field;
      return ($newYear - $year == 1);
   }

   /**
    * Return a date as yyyy-mm-dd
    * @static
    * @param $date in yyyymmdd format, from getDateKey with getNumericKey=false
    * @return string
    */
   public static function getIsoDate($date) {
      if (strlen($date) >= 4) {
         $result = substr($date, 0, 4);
         if (strlen($date) >= 6) {
            $result .= '-'.substr($date, 4, 2);
            if (strlen($date) == 8) {
               $result .= '-'.substr($date, 6, 2);
            }
         }
      }
      else {
         $result = $date;
      }
      return $result;
   }

   /**
    * Return the date as yyyymmdd for sorting
    *
    * @param string $date
    * @param boolean getNumericKey if true, returns an 8-digit numeric key that incorporates the effect of bef/aft date modifiers
    * @return string or int if getNumericKey is used
    */
   public static function getDateKey($date, $getNumericKey=false) {
      $result = '';
      $year = '';
      $month = '';
      $day = '';
      $monthError = $dayError = false; // don't set errors anymore
      $fields = array();
      // this should match 0-9+ or alphabetic(including accented)+
      preg_match_all('/(\d+|[^0-9\s`~!@#%^&*()_+\-={}|:\'<>?;,\/"\[\]\.\\\\]+)/', $date, $fields, PREG_SET_ORDER);
      for ($i = 0; $i < count($fields); $i++) {
         $field = $fields[$i][1];
         $num = $field + 0; // force conversion to number
         if (StructuredData::isYear($num)) {
            if (!$year) $year = $num;  // take the first year and ignore later numbers
         }
         else if ($m = StructuredData::getAlphaMonth($field)) {
            if (!$month) $month = $m; // take the first month and ignore later (in case of date ranges)
         }
         else if ($i > 0 && StructuredData::isYear($fields[$i-1][1] + 0) && StructuredData::isNextYear($year, $field)) {
            $year++; // 1963/4 or 1963/64
         }
         else if (StructuredData::isDay($num) && (!StructuredData::isNumMonth($num) ||
                                                  ($i > 0 && StructuredData::getAlphaMonth($fields[$i-1][1])) ||
                                                  ($i < count($fields)-1 && StructuredData::getAlphaMonth($fields[$i+1][1])))) {
            if (!$day) $day = $num; // take the first day and ignore later
         }
         else if (StructuredData::isNumMonth($num)) {
            if (!$month) $month = $num; // take the first month and ignore later
         }
      }
      if ($year) {
         $result = "$year"; // force conversion back to string
         if ($month && !$monthError) {
            $result .= ($month < 10 ? "0$month" : $month);
            if ($day && !$dayError) {
               $result .= ($day < 10 ? "0$day" : $day);
            }
            else if ($getNumericKey) {
               $result .= "01";
            }
         }
         else if ($getNumericKey) {
            $result .= "0101";
         }
         if ($getNumericKey) {
            $result = (int)$result;
            if (preg_match("/\b(bef|before)\b/i", $date)) {
               $result -= 10000; // subtract a year
            }
            else if (preg_match("/\b(aft|after)\b/i", $date)) {
               $result += 10000; // add a year
            }
         }
      }
      return $result;
   }
   
   public static function getPlaceKey($place) {
		$pos = mb_strpos($place, '|');
		if ($pos !== false) {
			$stdPlace = mb_strtolower(mb_substr($place, 0, $pos));
		}
		else {
			$stdPlace = mb_strtolower($place);
		}
   	return $stdPlace;
   }

   public static function getPlaceLink($place) {
		global $wgUser;
		
		if (!$place) {
			return '';
		}
		$pos = mb_strpos($place, '|');
		if ($pos !== false) {
			$titleString = mb_substr($place, 0, $pos);
			$titleDisplay = mb_substr($place, $pos+1);
		}
		else {
			$titleString = $titleDisplay = $place;
		}
   	$skin =& $wgUser->getSkin();
		return $skin->makeLinkObj(Title::newFromText($titleString, NS_PLACE), htmlspecialchars($titleDisplay));
   }

   public static function addBarToTitle($title) {
	   if (mb_strpos($title, '|') === false) {
         return $title . '|' . $title;
	   }
	   return $title;
   }

   public static function removeBars($s) {
      return mb_ereg_replace('\|', '', $s);
   }

   public static function removePreBar($s) {
      $pos = mb_strrpos($s, '|');
      if ($pos !== false) {
         return mb_substr($s, $pos+1);
      }
      return $s;
   }
   
   public static function addSelectToHtml($tabIndex, $fieldName, $options, $selectedOption, $extra = '', $showSelect=true) {
		$result = "<select id=\"$fieldName\" ".($tabIndex ? "tabindex=$tabIndex " : '')."name=\"$fieldName\" $extra>";
		if ($showSelect) {
			$result .= '<option value=""' . (!$selectedOption ? ' selected' : '') . ">Select</option>\n";
		}
		foreach ($options as $optionString => $optionValue) {
			if (is_integer($optionString)) {
				$optionString = $optionValue;
			}
         $isSelected = is_array($selectedOption) ? in_array($optionValue, $selectedOption) : $optionValue == $selectedOption;
			$result .= "<option value=\"$optionValue\"" . ($isSelected ? ' selected' : '') . ">$optionString</option>\n";
		}
		$result .= '</select>';
   	return $result;
   }

   // this can't be guaranteed to return the correct revid for the title at timestamp, since that can't be reliably determined
   // also, it might return a first revision that wasn't created until later
   public static function getRevidForTimestamp($title, $timestamp) {
   	$origTitle = $title;
		$db =& wfGetDB(DB_SLAVE);
		$revid = 0; 
		$tries = 0;
		while ($revid == 0 && $title != null && $tries < 3) {
			$revid = $db->selectField(array('page', 'revision'), 'rev_id', 
											array('page_namespace' => $title->getNamespace(), 'page_title' => $title->getDBkey(),
													'page_id = rev_page', 'rev_timestamp <= '.$db->addQuotes($timestamp)), 
											'CompareForm::getRevidForTimestamp', array('ORDER BY' => 'rev_timestamp DESC'));
			if (!$revid) {
				// the title may have been renamed; assume that the first revision for the title redirects to the renamed title containing the original revisions
				// get first revision for this title; read it to see if it's a redirect
				$firstRevid = $db->selectField(array('page', 'revision'), 'rev_id',
											array('page_namespace' => $title->getNamespace(), 'page_title' => $title->getDBkey(),
													'page_id = rev_page'), 
											'CompareForm::getRevidForTimestamp', array('ORDER BY' => 'rev_timestamp'));
				if ($firstRevid) {
					$revision = Revision::newFromId($firstRevid);
					if ($revision) {
						$text =& $revision->getText();
						$title = Title::newFromRedirect($text);
						if (!$title) $revid = $firstRevid; // it's possible during a gedcom upload that a related page was added just a short while later
					}
				}
			}
			$tries++;
		}
		if (!$revid) {
			error_log("getRevidForTimestamp not found title={$origTitle->getPrefixedText()} timestamp=$timestamp");
		}
   	return $revid;
	}

   /**
    * Copy page links from old title to new title
    * @static
    * @param  $oldTitle Title
    * @param  $newTitle Title
    * @return boolean true on success
    */
   public static function copyPageLinks($oldTitle, $newTitle) {
      $dbw =& wfGetDB(DB_MASTER);
      $dbw->begin();
      $dbw->ignoreErrors(true);
      $sql = 'insert ignore into pagelinks (select pl_from, '.$dbw->addQuotes($newTitle->getNamespace()).', '.$dbw->addQuotes($newTitle->getDBkey())
               .' from pagelinks where pl_namespace='.$dbw->addQuotes($oldTitle->getNamespace()).' and pl_title='.$dbw->addQuotes($oldTitle->getDBkey()).')';
      $dbw->query($sql, 'copyPageLinks');
	   if ($dbw->lastErrno() == 0) {
         $dbw->commit();
         return true;
      }
      else {
         $dbw->rollback();
         return false;
      }
   }

   public static function getAddThis() {
      return <<<END
<div class="addthis_toolbox"><a class="addthis_button_preferred_1"></a><a class="addthis_button_preferred_2"></a><a class="addthis_button_preferred_3"></a><a class="addthis_button_google_plusone" style="width:24px; overflow:hidden"></a><a class="addthis_button_compact"></a></div>
END;
   }


   public static function json_encode($value) {
      $result = '';
      if (is_array($value)) {
         if (0 !== count(array_diff_key($value, array_keys(array_keys($value))))) {
            $result = "{\n";
            $first = true;
            foreach ($value as $key => $val) {
               if (!$first) {
                  $result .= ",\n";
               }
               $result .= '"'.$key.'":'.StructuredData::json_encode($val);
               $first = false;
            }
            $result .= "\n}";
         }
         else {
            $result = '[';
            for ($i = 0; $i < count($value); $i++) {
               if ($i > 0) {
                  $result .= ",\n";
               }
               $result .= StructuredData::json_encode($value[$i]);
            }
            $result .= ']';
         }
      }
      else {
         $result = '"'.str_replace('"',"\\\"",trim($value)).'"';
      }
      return $result;
   }

   
	/**
     * Construct a StructuredData object
     * @param string $tagName the tag name containing the structured data
     */
	protected function __construct($tagName, $titleString, $ns, $footer = '') {
		$this->tagName = $tagName;
		// note: title is empty for ESIN and ns is NS_MAIN
		$this->titleString = $titleString;
		$this->ns = $ns;
		if ($this->titleString) {
			$this->title = Title::newFromText($titleString, $ns);
		}
		else {
			$this->title = null;
		}
		$this->xml = null;
		$this->pageContents = null;
		$this->footer = $footer;
		$this->isGedcomPage = false;
      $this->pageLoaded = false;
	}
	
	public function getTitle() {
		return $this->title;
	}

	public function loadPage($revid = 0) {
		if ($this->title) {
			if ($revid) {
				$revision = Revision::newFromId($revid);
			}
			else {
				$revision = Revision::newFromTitle($this->title);
			}
			if ($revision) {
				$this->pageContents =& $revision->getText();
				if ($this->pageContents) {
					$this->xml = StructuredData::getXml($this->tagName, $this->pageContents);
					$pos = mb_strpos($this->pageContents, '</'.$this->tagName.'>');
					if ($pos !== false) {
						$this->pageContents = mb_substr($this->pageContents, $pos + strlen("</$this->tagName>\n"));
					}
					if ($this->footer) {
						$this->removeFooter($this->pageContents);
					}
				}
            $this->pageLoaded = true;
			}
			else if ($revid) {
				error_log("loadPage revision not found title={$this->title->getPrefixedText()} revid=$revid");
			}
		}
	}

   // remove footer unless there is text afterward
   private function removeFooter(&$text) {
		$footerPos = mb_strpos($text, $this->footer);
		if ($footerPos !== false && mb_strlen(trim(mb_substr($text, $footerPos + mb_strlen($this->footer)))) == 0) {
			$text = rtrim(mb_substr($text, 0, $footerPos));
		}
   }

   private function getXmlCacheKey() {
      return 'xml:' . $this->title->getNamespace() . ':' . $this->title->getDBkey();
   }
	public function getPageXml($useCache=false) {
      global $wgMemc;

      if ($useCache && $this->xml === null) {
         $cacheKey = $this->getXmlCacheKey();
         $cacheValue = $wgMemc->get($cacheKey);
         if ($cacheValue) $this->xml = simplexml_load_string($cacheValue);
      }
		if ($this->xml == null && !$this->pageLoaded) {
			$this->loadPage();
         if ($useCache) {
            $this->cachePageXml();
         }
		}
		return $this->xml;
	}
   public function cachePageXml() {
      global $wgMemc;

      $cacheKey = $this->getXmlCacheKey();
      $cacheValue = '';
      if (isset($this->xml)) {
         $cacheValue = $this->xml->asXML();
      }
      $wgMemc->set($cacheKey, $cacheValue, 3600);
   }
   public function clearPageXmlCache() {
      global $wgMemc;

      $cacheKey = $this->getXmlCacheKey();
      $wgMemc->delete($cacheKey);
   }
	
	public function getPageContents() {
		if ($this->pageContents == null && !$this->pageLoaded) {
			$this->loadPage();
		}
		return $this->pageContents;
	}

	/**
	 * Return an array of page ids that link to this page
	 */
	public function getWhatLinksHere() {
		// copied to SpecialUpload
		$result = array();
		$db =& wfGetDB(DB_MASTER); // make sure this is the most current set of pages that link here
		if ($this->title->getNamespace() == NS_IMAGE) {
			// imagelinks link to the image ([[Image:...]]); pagelinks would link to the page ([[:Image:...]]), which we don't want
			$rows = $db->select('imagelinks', 'il_from', array('il_to' => $this->title->getDBkey()), 'StructuredData::getWhatLinksHere');
			while ($row = $db->fetchObject($rows)) {
				$result[] = $row->il_from;
			}
		}
		else {
			$rows = $db->select('pagelinks', 'pl_from', array('pl_namespace' => $this->title->getNamespace(), 'pl_title' => $this->title->getDBkey()), 'StructuredData::getWhatLinksHere');
			while ($row = $db->fetchObject($rows)) {
				$result[] = $row->pl_from;
			}
		}
		$db->freeResult($rows);
		return $result;
	}
	
	public function setGedcomPage($isGedcom) {
		$this->isGedcomPage = $isGedcom;
	}
	
	public function isGedcomPage() {
		return $this->isGedcomPage;
	}
	
	public function getTagName() {
		return $this->tagName;
	}

	/**
     * Add a labeled value to an infobox table and return the resulting wiki text
     * Non-static to parallel addValuesToTable
     * @param string $label
     * @param string $value
     * @return string
     */
	protected function addValueToTable($label, $value, $hideTopBorder = false) {
		return '|-' . ($hideTopBorder ? ' style="border-top-style:hidden"' : '') . "\n|$label\n| style=\"border-left-style:hidden\" | $value\n";
	}

	protected function addValueToTableDL($label, $value, $hideTopBorder = false) {
		return '|-' . ($hideTopBorder ? ' style="border-top-style:hidden"' : '') . "\n|<dl><dt>$label<dd>$value</dl>\n";
	}

	/**
     * Add a set of values to an infobox table and return the resulting wiki text
     * Needs to be non-static so we can pass in a formattingFunction
     * @param string $label if null, the formatting function is responsible for printing the first column value: e.g., "col1 || col2"
     * @param array $values
     * @param string $formattingFunction name of the function to call to format each value
     * @param mixed $formattingFunctionParm parameter to pass to the formatting function (2nd position, after value)
     * @param boolean $hideTopBorder
     * @return string
     */
	protected function addValuesToTable($label, $values, $formattingFunction, $formattingFunctionParm, $hideTopBorder = false) {
		$result = '';
		$labelPrinted = false;
		foreach ($values as $value) {
			$formattedValue = $this->$formattingFunction($value, $formattingFunctionParm);
			if ($formattedValue) {
				if (!$labelPrinted || !$label) {
					$labelRow = '';
					if ($label) {
						$labelRow = "|$label\n";
					}
					$result .= '|-' . ($hideTopBorder ? ' style="border-top-style:hidden"' : '') . "\n$labelRow";
					$labelPrinted = true;
				}
				else {
					$result .= "|- style=\"border-top-style:hidden\"\n|\n";
				}
				if ($label) {
					$result .= "| style=\"border-left-style:hidden\" ";
				}
				$result .= "|$formattedValue\n";
			}
		}
		return $result;
	}

	/**
     * Add a set of values to an infobox table and return the resulting wiki text
     * Needs to be non-static so we can pass in a formattingFunction
     * @param string $label if null, the formatting function is responsible for printing the DT
     * @param array $values
     * @param string $formattingFunction name of the function to call to format each value
     * @param mixed $formattingFunctionParm parameter to pass to the formatting function (2nd position, after value)
     * @param boolean $hideTopBorder
     * @return string
     */
	protected function addValuesToTableDL($label, $values, $formattingFunction, $formattingFunctionParm, $hideTopBorder = false) {
		$result = '';
		$labelPrinted = false;
		foreach ($values as $value) {
			$formattedValue = $this->$formattingFunction($value, $formattingFunctionParm);
			if ($formattedValue) {
				if (!$labelPrinted || !$label) {
					$labelHdr = '';
					if ($label) {
						$labelHdr = "<dt>$label"; // not sure why, but the </dt> is converted to &lt;/dt&gt;
					}
					$result .= '|-' . ($hideTopBorder ? ' style="border-top-style:hidden"' : '') . "\n|<dl>$labelHdr";
					$labelPrinted = true;
				}
				if ($label) {
					$result .= "<dd>$formattedValue";  // not sure why, but the </dd> is converted to &lt;/dd&gt;
				}
				else {
					$result .= $formattedValue;
				}
				if (!$label) {
					$result .= "</dl>\n";
				}
			}
		}
		if ($labelPrinted && $label) {
			$result .= "</dl>\n";
		}
		return $result;
	}

	protected function addRowsToTable($header, $values, $formattingFunction, $formattingFunctionParm) {
		$result = '';
		$headerPrinted = false;
		foreach ($values as $value) {
			$formattedValue = $this->$formattingFunction($value, $formattingFunctionParm);
			if ($formattedValue) {
				if (!$headerPrinted) {
					$result .= '|-' . "\n!$header\n";
					$headerPrinted = true;
				}
				$result .= "|- style=\"border-top-style:hidden\"\n|$formattedValue\n";
			}
		}
		return $result;
	}

	/**
     * Add a <tagName>value</tagName> to result
     * Non-static to parallel addMultiLineFieldToXml
     * @param string $value
     * @param string $tagName
     * @return string
     */
	protected function addSingleLineFieldToXml($value, $tagName) {
		if ($value) {
			$escapedValue =& StructuredData::escapeXml($value);
			return "<$tagName>$escapedValue</$tagName>\n";
		}
		return '';
	}

	// nothing is added if all attributes are empty
	protected function addMultiAttrFieldToXml($attrs, $tagName, $contents = '') {
		$attrString = '';
		foreach ($attrs as $attrName => $attrValue) {
			if (strlen($attrValue) > 0) {
				$attrString .= " $attrName=\"" . StructuredData::escapeXml($attrValue) . '"';
			}
		}
		if (empty($attrString) && empty($contents)) {
			return '';
		}
		else if (empty($contents)) {
		   return "<$tagName$attrString/>\n";
		}
		else {
		   return "<$tagName$attrString>".StructuredData::escapeXml($contents)."</$tagName>\n";
		}
	}

	/**
     * Add one value to result for each line in text
     * Must be non-static so we can pass in a formatting function
     * @param string $text
     * @param string $formattingFunction name of the function to call to format each line of text -- must call escapeXml
     * @param string $removeText \n-separated list of strings to remove from this text
     * @return string
     */
	protected function addMultiLineFieldToXml($text, $formattingFunction) {
// TODO could re replace the preg_replace with [\r\n]+ on the split, and trim each escapedValue?
      if (is_array($text)) {
         $values = $text;
      }
      else {
         $text = trim(preg_replace('/ *\r?\n */', "\n", $text));
         $values = preg_split('/[\n]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
         $values = array_unique($values);
      }
		$result = '';
		foreach ($values as $value) {
			$result .= $this->$formattingFunction($value) . "\n";
		}
		return $result;
	}

	protected function formatWatcher($value, $dummy) {
	   return "[[User:$value|$value]]";
	}

	protected function showWatchers() {
	   $dbr =& wfGetDB(DB_SLAVE);
		$sql = 'SELECT user_name FROM user, watchlist where wl_namespace=' .  $dbr->addQuotes($this->title->getNamespace()) .
   		    ' AND wl_title=' .  $dbr->addQuotes($this->title->getDBkey()) . ' AND wl_user=user_id';
		$rows = $dbr->query($sql);
		$users = array();
		while ($row = $dbr->fetchObject($rows)) {
		   $users[] = $row->user_name;
		}
		$dbr->freeResult($rows);

		return "|-\n|\n{| id=\"watchers\"\n|-\n! Watching Page\n".
			$this->addValuesToTableDL(' ', $users, 'formatWatcher', null).  // by passing in a ' ' we trick the function into not printing its own header
			"\n|}\n";
	}
	
	/**
     * Render the input data (found between <tagName>..</tagName>)
     * Sets xml property and calls the abstract function toWikiText()
     * @param string $input
     * @param Parser $parser
     * @return string HTML resulting from parsing the input
     */
	public function renderData(&$input, $parser) {
		$result = '';
		if ($this->tagName && $input) {
			try {
				$this->xml = simplexml_load_string("<$this->tagName>" . $input . "</$this->tagName>");
			}
			catch (Exception $e) {
				$this->xml = null;
				log_error("Caught exception: renderData: {$e->getCode()}: {$e->getMessage()}\n");
				log_error($e->getTraceAsString());
			}
			// must call toWikiText before getHeaderHTML() for Place maps and historical data stuff to work
			$text = $this->toWikiText($parser);
			// must set the last parameter to false, otherwise clearState gets called, which causes ESINHandler to lose its contents
         $parserOutput = $parser->parse($text, $parser->mTitle, $parser->mOptions, true, false);
   		$result = $this->getHeaderHTML() . $parserOutput->getText();
         $result = preg_replace("/<div class=\"wr-infobox( |\")[^>]*>/", "$0".StructuredData::getAddThis(), $result, 1);
		}
		return $result;
	}
	
	//	function parse( $text, &$title, $options, $linestart = true, $clearState = true, $revid = null ) {
	/**
     * Add additional edit fields to the edit form
     * Sets xml property and calls the abstract function toEditFields()
     * @param EditPage $editPage
     */
	public function renderEditFields($editPage, $redirTargetMustExist = false) {
		global $wgRequest, $wgOut;
		// don't display XML fields when editing sections, or if an xml parameter has been passed in
		if ($wgRequest->getVal('xml')) {
			$wgOut->addHTML('<input type="hidden" value="1" name="xml"/>');
		}
		elseif (!$editPage->section) {
			// check redirect
			$error = '';
			StructuredData::followRedirects($editPage->textbox1, $this->titleString, $this->ns, $error, $redirTargetMustExist);
			if ($error) {
				$wgOut->addHTML("<p><font color=red>$error</font></p>");
			}
			// add edit fields
			$this->xml = StructuredData::getXml($this->tagName, $editPage->textbox1);
			$wgOut->addHTML($this->toEditFields($editPage->textbox1));
			// strip tag content from textbox1
			$endPos = mb_strpos($editPage->textbox1, "</$this->tagName>");
			if ($endPos !== false) {
				$start = $endPos + strlen("</$this->tagName>\n");
				$editPage->textbox1 = mb_substr($editPage->textbox1, $start);
			}
			if ($this->footer) {
				$this->removeFooter($editPage->textbox1);
			}
		}
	}

	/**
     * Import data from additional edit fields into the main edit field
     * Calls the abstract function fromEditFields($request)
     * @param EditPage $editPage
     * @param unknown $request
     */
	public function importEditData($editPage, $request) {
		if ($request->wasPosted() && !$editPage->section && !$request->getVal('xml')) {
			$editPage->textbox1 = $this->getEditText($request, $editPage->textbox1);
		}
	}
	
	/**
	 * editPage
	 *
	 * @param unknown_type $request
	 * @param unknown_type $text
	 * @return unknown
	 */
	public function editPage(&$request, &$text, $summary, $flags = 0, $addWatch = false) {
		global $wgUser;

		if (!$this->title || !$this->title->exists()) {
			return false;
		}
		$article = new Article($this->title, 0);
		if (!$article) {
			return false;
		}
		
		$oldContent = $article->getContent();
		$content = $this->getEditText($request, $text);
		
		if ($content != $oldContent) {
			$result = $article->doEdit($content, $summary, $flags);
			if ($result && $addWatch) {
	   		StructuredData::addWatch($wgUser, $article, true);
			}
		}
		else {
			$result = true;
		}
		
		return $result;
	}

	/**
	 * createPage !!! doesn't work for Images
	 *
	 * @param string $summary
	 * @param int $flags
	 * @return true on success
	 */
	public function createPage($summary, $flags = 0) {
		$article = new Article($this->title, 0);
		if (!$article) {
			return false;
		}
		$content = $this->getPageTextFromWLH(false);
		return $article->doEdit($content, $summary, $flags | EDIT_NEW);
	}
	
	private function getEditText(&$request, &$text) {
		// If there is a redirect, don't add anything, even the surrounding tag.
		// This makes #redirect directives still work, since redirect pages don't have any data in the edit fields,
		// so the #redirect directive remains the first (and only) thing on the page
		if (!StructuredData::isRedirect($text)) {
	      if ($this->footer && mb_strpos($text, $this->footer) === false) {
	      	$ftr = ($text && !StructuredData::endsWith($text, "\n") ? "\n" : '') . $this->footer;
	      }
	      else {
	      	$ftr = '';
	      }
			$xmlElementsString = $this->fromEditFields($request);
			return "<{$this->tagName}>\n$xmlElementsString</{$this->tagName}>\n{$text}$ftr";
		}
		else {
			return $text;
		}
	}

	/**
     * Validates the structured data
     * Sets xml property and calls the abstract function validateData($title)
     * @param string $textBox1
     * @param string $hookError
     * textBox1 passed by reference for efficiency; don't change it
     */
	public function validate(&$textBox1, $section, &$hookError, $redirTargetMustExist=false) {
		if (!$section) {
			$error = '';
			StructuredData::followRedirects($textBox1, $this->titleString, $this->ns, $error, $redirTargetMustExist);
			$this->xml = StructuredData::getXml($this->tagName, $textBox1);
			if ($error || !$this->validateData($textBox1)) {
				$hookError = '<b>You must correct the errors before saving</b>';
			}
		}
	}

	/**
     * Propagate edited data
     * Sets xml property and calls the abstract function propagateEditData()
     * @param string $text contains new text
     * @param Article $article
     * @return bool true if propagate succeeded
     */
	public function propagateEdit(&$text, &$article) {
      global $wgArticle, $wgUser, $wrBotUserID, $wgParser;

		$result = true;
		// we don't propagate fixup edits after move, undelete, and revert   (do propagate bot edits)
		if (PropagationManager::isPropagationEnabled()) {           // && $wgUser->getID() != $wrBotUserID) {
			$this->xml = StructuredData::getXml($this->tagName, $text);
			// get the old text
			$oldText = $article->getContent();
			$textChanged = false;
			$result = $this->propagateEditData($oldText, $text, $textChanged);
		}
		return $result;
	}

	/**
     * Propagate an article move
     * Sets xml property and calls the abstract function propagateMoveData($newTitle)
     * @param Title $newTitle title article has been moved to
     * @return bool true if propagate succeeded
     */
	public function propagateMove($newTitle) {
	   global $wgUser, $wrBotUserID;

	   $result = true;
		// we do propagate bot edits
//	   if ($wgUser->getID() != $wrBotUserID) {
	   	// remove the title from the link cache and re-created it so it has the right page id
			//$linkCache =& LinkCache::singleton();
   		//$linkCache->clearBadLink($newTitle->getPrefixedDBkey());
   		//$newTitle = Title::makeTitle($newTitle->getNamespace(), $newTitle->getDBkey());
   		$newTitle->resetArticleID(0); // clears link cache
			$newTitle->getArticleID(GAID_FOR_UPDATE); // make sure you read the master db for the pageid
   		$newTitleString = $newTitle->getText();
   		$newNs = $newTitle->getNamespace();
   		$revision = StructuredData::getRevision($newTitle, false, true);
   		if (!$revision) {
   			error_log("Revision being moved not found!\n");
   			return true;
   		}
   		$text =& $revision->getText();
   		$this->xml = StructuredData::getXml($this->tagName, $text);
   		$textChanged = false;
   		$result = $this->propagateMoveDeleteUndelete($newTitleString, $newNs, $text, $textChanged);
   		if ($result && $textChanged) { // should change only if moving namespaces (which is currently only allowed for source -> repo)
				$article = new Article($newTitle, 0);
   			PropagationManager::enablePropagation(false);
   			$result = $article->doEdit($text, self::PROPAGATE_MOVE_MESSAGE, PROPAGATE_EDIT_FLAGS);
   			PropagationManager::enablePropagation(true);
   		}
			if (!$result) {
	      	error_log("ERROR propagateMove failed for {$this->title->getPrefixedText()} -> {$newTitle->getPrefixedText()}\n");
			}
//	   }
		return true;
	}

	/**
     * Propagate an article deletion
     * Sets xml property and calls the abstract function propagateMoveEditDelete($title)
     * @param Article $article
     * @return bool true if propagate succeeded
     */
	public function propagateDelete($article) {
	   global $wgUser, $wrBotUserID;

	   $result = true;
		// we do propagate bot edits
//	   if ($wgUser->getID() != $wrBotUserID) {
   		if (!$article) {
   			error_log("Deleted article not found: ".$article->getTitle()->getPrefixedText()."\n");
   			return true;
   		}
   //		$titleString = $article->getTitle()->getText();
   //		wfDebug("PROPAGATE DELETE $titleString\n");
   		$text =& $article->fetchContent();
   		$this->xml = StructuredData::getXml($this->tagName, $text);
   		$textChanged = false;
   		$result = $this->propagateMoveDeleteUndelete(null, 0, $text, $textChanged);
   		// text should never be changed in a delete
//	   }
	   return $result;
	}

	/**
     * Propagate an article undelete
     * Sets xml property and calls the abstract function propagateUndeleteData($title)
     * @param Revision $revision being undeleted
     * @return bool true if propagate succeeded
     */
	public function propagateUndelete($revision) {
	   global $wgUser, $wrBotUserID;

	   $result = true;
		// we do propagate bot edits
//	   if ($wgUser->getID() != $wrBotUserID) {
   		if (!$revision) {
   			error_log("Undeleted revision not found: ".$revision->getTitle()->getPrefixedText()."\n");
   			return true;
   		}
   		$text =& $revision->getText();
   		$this->xml = StructuredData::getXml($this->tagName, $text);
   		$titleString = $revision->getTitle()->getText();
   		$ns = $revision->getTitle()->getNamespace();
   		$textChanged = false;
   		// Sometimes the article being undeleted has been re-created, in which case nothing should be changed by undelete
   		// (only previous revisions are restored), so make sure propagateUndeleteData is idempotent
   		$result = $this->propagateMoveDeleteUndelete($titleString, $ns, $text, $textChanged);
   		if ($result && $textChanged) {
		   	// clear the link cache so we get the right page id for the undeleted article
				//$linkCache =& LinkCache::singleton();
	   		//$linkCache->clearBadLink($revision->getTitle()->getPrefixedDBkey());
	   		$newTitle = Title::makeTitle($revision->getTitle()->getNamespace(), $revision->getTitle()->getDBkey());
	   		$newTitle->resetArticleID(0); // clears link cache
				$newTitle->getArticleID(GAID_FOR_UPDATE); // make sure you read the master db for the pageid
				$article = new Article($newTitle, 0);
   			PropagationManager::enablePropagation(false);
   			$result = $article->doEdit($text, self::PROPAGATE_MESSAGE, PROPAGATE_EDIT_FLAGS);
   			PropagationManager::enablePropagation(true);
   		}
//	   }
		return $result;
	}

	/**
	 * Propagate an article rollback
	 * Sets xml property and calls the abstract function propagateRollbackData($title)
	 * @param Article $article contains text being replaced
	 * @return bool true if propagate succeeded
	 */
	public function propagateRollback($article) {
	   global $wgUser, $wrBotUserID;

	   $result = true;
		// we do propagate bot edits
//	   if ($wgUser->getID() != $wrBotUserID) {
   		$title = $article->getTitle();
   		$oldText =& $article->fetchContent();
   		// $article contains the text being replaced; $rollbackRevision contains the new text
   		$rollbackRevision = StructuredData::getRevision($title, false, true);
   		$text =& $rollbackRevision->getText();
   		$this->xml = StructuredData::getXml($this->tagName, $text);
   		$textChanged = false;
   		$result = $this->propagateEditData($oldText, $text, $textChanged);
   		if ($result && $textChanged) {
   			PropagationManager::enablePropagation(false);
   			$result = $article->doEdit($text, self::PROPAGATE_MESSAGE, PROPAGATE_EDIT_FLAGS);
   			PropagationManager::enablePropagation(true);
   		}
//	   }
		return $result;
	}

	/**
	 * Create wiki text from xml property
	 */
	abstract protected function toWikiText($parser);

	/**
     * Create edit fields from xml property
     */
	abstract protected function toEditFields(&$textbox1);

	/**
     * Return xml elements from data in request (return empty string if no data)
     * @param unknown $request
     */
	abstract protected function fromEditFields($request);

	/**
	 * Get HTML text to prepend to the wiki text
	 */
	protected function getHeaderHTML() {
	   return '';
	}
	
	/**
     * Return true if xml property is valid
     * Override to actually validate data
     */
	protected function validateData(&$textbox1) {
		return true;
	}

	/**
     * Propagate data in xml property to other articles on edit
     * Override to actually propagate data
     * @param string $oldText text being replaced
     * @return bool true if propagation was successful
     */
	protected function propagateEditData($oldText, &$text, &$textChanged) {
		return true;
	}

	/**
     * Propagate data in xml property to other articles on move
     * Override to actually propagate data
     * @param String $newTitleString
     * @param int $newNs
     * @param String $text text of the article, can change during undelete
     * @param bool $textChanged return true if text was changed
     * @return bool true if propagation was successful
     */
	protected function propagateMoveDeleteUndelete($newTitleString, $newNs, &$text, &$textChanged) {
		return true;
	}
	
	protected function getPageTextFromWLH($toEditFields, $request=null) {
      $tagName = $this->tagName;
		return "<$tagName></$tagName>\n";
	}
}
?>
