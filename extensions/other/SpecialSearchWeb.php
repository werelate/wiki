<?php
/**
 * @package MediaWiki
 */
$wrSearchWebURL = "/wiki/Special:SearchWeb";

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialSearchWebSetup";

function wfSpecialSearchWebSetup() {
	global $wgMessageCache, $wgSpecialPages, $wgParser;
	$wgMessageCache->addMessages( array( "searchweb" => "Search Web" ) );
	$wgSpecialPages['SearchWeb'] = array('SpecialPage','SearchWeb');
}

/**
 * Called to display the Special:SearchWeb page
 *
 * @param unknown_type $par
 * @param unknown_type $specialPage
 */
function wfSpecialSearchWeb( $par=NULL, $specialPage ) {
	global $wgOut, $wgRequest;
	
	$wgOut->setPageTitle('Search Web');
	$searchForm = new SearchWebForm();

	// read query parameters into variables
	$searchForm->readQueryParms();

	// construct query to send to server
	$searchServerQuery = $searchForm->getSearchServerQuery();
	$formHtml = $searchForm->getFormHtml();
	if ($searchServerQuery) {
		$results = $searchForm->getSearchResultsHtml($searchServerQuery);
		$wgOut->addHTML(<<< END
<table class="fullwidth"><tr><td id="infobox"> </td><td id="contentbox" style="background-color:#fff;">
$results
<p>$formHtml</p>		
</td></tr></table>
END
		);		
	}
	else {
		$sideText = $wgOut->parse(<<< END
This is an experimental Web search engine for genealogy.  It currently contains over 5,000,000 pages that were crawled in 2006.
Our goal is to extend it significantly in the future as time and resources allow.

Unlike a general-purpose search engine, we target only content that is relevant to genealogy.
END
		);
		$endText = $wgOut->parse(<<< END
<font size="+1"><b>Search over 5,000,000 genealogy Web pages</b></font>

==Instructions==
* Enter the terms you are looking for in the "Keywords" field.
* You can limit your search to a particular website by entering ''site:'' followed by the name of the website.  
For example, ''site:familysearch.org'' searches just the items in the family history library catalog.

==Beta Alerts==
Due to a bug in our Web crawl, approximately 25% of the pages you'll get back are ''not'' genealogical.  
We'll never achieve 100% accuracy, but we hope to decrease the number of non-relevant pages significantly over the coming months.

Currently we do not distinguish between words used as names, places, or dictionary terms.  So if you search for the surname ''Brown'' you'll 
also get back pages for ''Brown County'' or even the color brown.  We plan to fix this in the coming months.

We expect to increase the number of web pages we search significantly over the next year.
END
		);
		$wgOut->addHTML(<<< END
<table class="fullwidth"><tr><td id="infobox"><div class="infobox-header">Web Search</div>
<table><tr><td>
$sideText
</td></tr></table>
</td><td id="contentbox">
<p>$formHtml</p><p> </p>
$endText
</td></tr></table>
END
		);
	}
}

/**
 * return the HTML for the search form
 *
 * @param string $givenname
 * @param string $surname
 * @param string $checkedPerson
 * @param string $place
 * @param string $locatedIn
 * @param string $checkedPlace
 * @param string $keywords
 * @return unknown
 */
function getSearchWebForm($keywords = '') {
   global $wrSearchWebURL;

   // set search help url
	$searchHelpUrl = Title::makeTitle(NS_HELP, 'Search')->getLocalUrl();

   // generate form
	$result = <<< END
<div class="searchform"><form name="search" action="$wrSearchWebURL" method="get">
<table><tr>
<td align=right>Keywords: </td><td align=left colspan=3><input type="text" name="keywords" size=42 maxlength=100 value="$keywords" onfocus="select()"/></td>
</tr><tr>
<td align=right colspan=4><input type="submit" value="Search"/></td><td>&nbsp;&nbsp;<a href="$searchHelpUrl">Help</a></td>
</tr></table></form><div>
END;
   return $result;
}

 /**
  * Search form used in Special:Search and <search> hook
  */
class SearchWebForm {
	protected $keywords;
   protected $start;
	protected $rows;
	
   public function __construct() {
   	$this->rows = 10;
	}

   public function readQueryParms() {
      global $wgRequest;

      $this->keywords = $wgRequest->getVal('keywords');
		$this->start = $wgRequest->getVal('start');
   }

	/**
	 * Construct query to send to search server
	 * @return string query
	 */
	public function getSearchServerQuery() {
		global $wrSearchServerURL;

		$query = trim($this->keywords);
		$siteQuery = '';
		if (preg_match('/\bsite:(\S+)/', $query, $matches)) {
			$siteQuery = 'site:'.join('.',array_reverse(explode('.',preg_replace('/^www\./', '', $matches[1])))).'.*';
			$query = trim(preg_replace('/\bsite:(\S+)/', '', $query));
		}
		if ($query) {
		   $query = $wrSearchServerURL . '/web/select?q=' . urlencode($query) . ($siteQuery ? '&fq=' . urlencode($siteQuery) : '') .
                    '&start=' . $this->start . '&rows=' . $this->rows;
		}
      wfDebug("getSearchServerQuery $query\n");
      return $query;
	}

	private function getSelfQuery() {
	   global $wrSearchWebURL;

		return $wrSearchWebURL
         .'?keywords=' . urlencode($this->keywords);  // must be last so that we can append the site: for "more" queries
	}

	private function prepare($value) {
		return str_replace(array('&lt;b&gt;','&lt;/b&gt;'), array('<b>','</b>'), htmlspecialchars($value));
	}

	private function truncate($s, $max = 100) {
		if (strlen($s) > $max) {
			return mb_substr($s, 0, $max-3) . '...';
		}
		return $s;
	}

	private function formatResult($url, $hlUrl, $hlTitle, $hlContent) {
		return "<font size=\"+1\"><a href=\"$url\">" . $this->prepare($hlTitle ? $hlTitle : $hlUrl) . " </a></font><br>" . 
					 ($hlContent ? $this->prepare($hlContent) . '<br>' : '') .
					 "<font color=#008000>" . $this->prepare($this->truncate($hlUrl)) . "</font><br><br>";
	}

	private function formatResults($selfQuery, $result, $start, $end, $numFound) {
		if ($start > 0) {
		   $prevStart = $start - $this->rows;
		   if ($prevStart < 0) {
		       $prevStart = 0;
		   }
		}
      $startPlusOne = $start + 1;
		$prevNextLinks = ($start > 0 ? "<a href=\"$selfQuery&start=$prevStart\">&laquo;&nbsp;Prev</a> |" : '') .
		           " Viewing <b>$startPlusOne-$end</b> of $numFound" .
		           ($end < $numFound ? " | <a href=\"$selfQuery&start=$end\">Next&nbsp;&raquo;</a>" : '');

      // display Viewing x..y
		$output = "<p align=right>$prevNextLinks</p>\n";

  		// do we need to indent?
  		$indent = (stripos($this->keywords, 'site:') === false);

      // display results
      $prevSite = '';
      $docs = $result['response']['docs'];
      $highlighting = $result['highlighting'];
      foreach ($highlighting as $i => $hl) {
      	$i = intval($i);
      	$doc = $docs[$i];
      	$url = $doc['url'];
      	$site = $doc['site'];
      	$hlUrl = $hl['url'][0];
      	$hlTitle = $hl['title_stored'][0];
      	$hlContent = $hl['content_stored'][0];

      	if ($indent && $prevSite == $site) {
				$output .= '<blockquote>';
			}
			$output .= $this->formatResult($url, $hlUrl, $hlTitle, $hlContent) . "\n";
			if ($indent && $prevSite == $site) {
				$output .= '</blockquote>';
			}
			$prevSite = $site;
		}

        // display prev..next navigation
        $output .= "<p align=center>$prevNextLinks</p>";

		// return generated html
		return $output;
    }

	/**
	 * Return HTML for displaying search results
	 * @return string HTML
	 */
	public function getSearchResultsHtml($searchServerQuery) {
		// send the query to the search server
		$resultString = file_get_contents($searchServerQuery);
		if (!$resultString) {
			return '<h1>There was an error processing your search, or the search server is down; please try a different search or try again later.</h1>';
		}
		eval('$result = ' . $resultString . ';');

      // create basic re-query for use in various links
      $selfQuery = $this->getSelfQuery();
      $start = $result['response']['start'];
      $numFound = $result['response']['numFound'];
      $end = $start + $this->rows;
      if ($end > $numFound) {
      	$end = $numFound;
      }
      
      if ($numFound == 0) {
          return '<p><font size=+1>Your search did not match any documents.</font></p>';
      }

      // generate the result list
      return $this->formatResults($selfQuery, $result, $start, $end, $numFound);
	}

   public function getFormHtml() {
		$keywords = htmlspecialchars($this->keywords);

		return '<center>'.getSearchWebForm($keywords).'</center>';
   }
}
?>
