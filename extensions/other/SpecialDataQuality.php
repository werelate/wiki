<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfDataQualitySetup";

function wfDataQualitySetup() {
	global $wgMessageCache, $wgSpecialPages;
	$wgMessageCache->addMessages( array( "dataquality" => "DataQuality" ) );
	$wgSpecialPages['DataQuality'] = array('SpecialPage','DataQuality');
}

/**
 * Entry point
 * @param string $par An article name ??
 */
function wfSpecialDataQuality() {
	global $wgRequest;
	$page = new DataQuality( $wgRequest );
	$page->execute();
}

class DataQuality {
	var $request;
	var $limit, $fromPage, $fromIssue, $dir, $target;
	var $selfTitle, $skin;
 
  private static $DATA_QUALITY_CATEGORIES = array(
//      'all' => '',  
//      'Anomalies' => 'Anomaly',
      'Errors' => 'Error',
//      'Intergenerational' => 'Relationship',
//      'Incomplete' => 'Incomplete',
//      'Living' => 'Living'
   );

	function DataQuality( &$request ) {
		global $wgUser;
		$this->request =& $request;
		$this->skin =& $wgUser->getSkin();
	}

	function execute() {
		global $wgOut, $wgRequest, $wgLang, $wgUser;
		$fname = 'DataQualityPage::execute';

		$this->limit = min( $this->request->getInt( 'limit', 50 ), 500 );
		if ( $this->limit <= 0 ) {
			$this->limit = 50;
		}

		$flds = split(':', $this->request->getText( 'from' ), 3);
    if ( count($flds) == 3 ) {
		  $this->fromPage = $flds[0] . ':' . $flds[1];
		  $this->fromIssue = $flds[2];
    }
    else {
		  $this->fromPage = '';
		  $this->fromIssue = '';
    }
		$this->dir = $this->request->getText( 'dir', 'next' );
		if ( $this->dir != 'prev' ) {
			$this->dir = 'next';
		}

		$this->selfTitle = Title::makeTitleSafe( NS_SPECIAL, 'DataQuality' );
		$wgOut->setPagetitle( wfMsg( 'DQissues' ) );
		$wgOut->setSubtitle( wfMsg( 'unusualsituations' ) );
   
    // Display message that data is cached, along with a link to the Help page 
		$dbr =& wfGetDB( DB_SLAVE );

		# Fetch the timestamp of this update
		$tRes = $dbr->select( 'querycache_info', array( 'qci_timestamp' ), array( 'qci_type' => 'AnalyzeDataQuality' ), $fname );
		$tRow = $dbr->fetchObject( $tRes );
    $dbr->freeResult( $tRes );
				
		if( $tRow ) {
		  $updated = $wgLang->timeAndDate( $tRow->qci_timestamp, true, true );
			$cacheNotice = wfMsg( 'perfcachedts', $updated );
			$wgOut->addMeta( 'Data-Cache-Time', $tRow->qci_timestamp );
			$wgOut->addScript( '<script language="JavaScript">var dataCacheTime = \'' . $tRow->qci_timestamp . '\';</script>' );
		} else {
			$cacheNotice = wfMsg( 'perfcached' );
		}
		$wgOut->addWikiText( $cacheNotice . " See [[Help:Monitoring Data Quality]] for more information, including why you can't select a different category.");

    // Filters
    $this->category = $wgRequest->getVal('category');
    $this->category = 'Error';                              // temporary until other categories allowed
    $this->watched = $wgRequest->getVal('watched');
    if (!$this->watched) {
    	$this->watched = 'wu';
    }
    $this->tree = $wgRequest->getVal('tree');
    
    if ($wgUser->isLoggedIn()) {
      $watchSelectExtra = '';
    }
    else {
	   	$watchSelectExtra = 'disabled';
      $this->watched = 'wu';
	  }
    $myTreeOptions = array();
    $myTreeOptions['Whether or not in'] = '';
    $myTreeOptions['In any of MyTrees'] = 'all';
    if ($wgUser->isLoggedIn()) {
      $conds[] = 'ft_user = "' . $wgUser->getName() . '"';
      $options['ORDER BY'] = 'ft_name';
		  $res = $dbr->select( array( 'familytree' ), array( 'ft_name' , 'ft_tree_id'), $conds, $fname, $options );
		  while ( $row = $dbr->fetchObject( $res ) ) {
			  $myTreeOptions[substr($row->ft_name,0,30)] = $row->ft_tree_id;
		  }
		  $dbr->freeResult( $res );
    } 
     
    $parmForm = '<form method="get" action="/wiki/' . $this->selfTitle->getPrefixedURL() . '">';
    $parmForm .= '<table class="parmform"><tr><td><label for="category">Category: </label>';
    $parmForm .= '<td>' . StructuredData::addSelectToHtml(0, 'category', self::$DATA_QUALITY_CATEGORIES, $this->category, '', false) . '</td>';
    $parmForm .= '<td><label for="tree">MyTrees: </label>';
    $parmForm .= StructuredData::addSelectToHtml(0, 'tree', $myTreeOptions, $this->tree, $watchSelectExtra, false) . '</td>';
    $parmForm .= '<td>' . StructuredData::addSelectToHtml(0, 'watched', SearchForm::$WATCH_OPTIONS, $this->watched, $watchSelectExtra, false) . '</td>';
    $parmForm .= '<input type="hidden" name="limit" value="' . $this->limit . '">';
	  $parmForm .= '<td><input type="submit" value="' . wfMsgExt( 'allpagessubmit', array( 'escape') ) . '" /></td></tr></table>';
  	$parmForm .= '</form>';
  	$wgOut->addHTML( $parmForm );

		$this->showIssues( $this->limit, $this->fromPage, $this->fromIssue, $this->dir, $this->category, $this->tree, $this->watched );
	}
	/**
	 * @param int       $level      Recursion level
	 * @param Title     $target     Target title
	 * @param int       $limit      Number of entries to display
	 * @param Title     $fromPage   Display from this page title
   * @param string    $fromIssue  Display from this issue within the page title
	 * @param string    $dir        'next' or 'prev', whether $fromTitle is the start or end of the list
	 * @private
	 */
	function showIssues( $limit, $fromPage = '', $fromIssue = '', $dir = 'next', $category = '', $tree = '', $watched = 'wu' ) {
		global $wgOut, $wgUser;
		$fname = 'DataQualityPage::showIssues';

		$dbr =& wfGetDB( DB_SLAVE );

		// Some extra validation
		if ( !$fromPage && $dir == 'prev' ) {
			// Before start? No make sense
			$dir = 'next';
		}

		// Make the query - start with basic criteria and join conditions
		$conds = array(
      'dqi_job_id = (select max(dq_job_id) from dq_page)',  // driving table is dq_issue, but dq_page identifies the most recently completed job
      'dq_job_id = dqi_job_id',
			'dq_page_id=dqi_page_id',
		);

		$options = array();
		$pageTitle = "case dq_namespace when 108 then concat(substring_index(substring_index(dq_title, '_', 2), '_', -1), '_', substring_index(dq_title, '_', 1), '_', substring_index(dq_title, '_', -1)) else dq_title end";    // If Person page, sort by surname then given name
   
    // If user selected a MyTree (or all MyTrees), filter based on that and ignore watched filter (it is assumed the user is watching all MyTree pages)
    if ( $tree != '' ) {
      $watchedCond = false;
      if ( $tree == 'all' ) {
        $treeCond = '(dq_namespace, dq_title) IN (SELECT fp_namespace, fp_title FROM familytree_page WHERE fp_user_id = ' . $wgUser->getId() . ')';
      }
      else {
        $treeCond = '(dq_namespace, dq_title) IN (SELECT fp_namespace, fp_title FROM familytree_page WHERE fp_tree_id = ' . $tree . ')';
      }
    }  
    else {
      $treeCond = false; 
      if ( $watched == 'w' ) {
        $watchedSelect = 'IN';
      }
      if ( $watched == 'u' ) {
        $watchedSelect = 'NOT IN';
      }
      if ( $watched == 'wu' ) {
        $watchedCond = false;
      }
      else {
        $watchedCond = '(dq_namespace, dq_title) ' . $watchedSelect . ' (SELECT wl_namespace, wl_title FROM watchlist WHERE wl_user = ' . $wgUser->getID() . ')';
      }
    }
    
    if ( $category != '' ) {
      $catCond = "dqi_category = '$category'";
    }
    else {
      $catCond = false;
    }
    
    if ( $fromPage && strpos($fromPage, ':')) {
			$flds = split(":", $fromPage, 2);
			$fromNamespace = $dbr->addQuotes($flds[0]);
			$fromTitle = $dbr->addQuotes($flds[1]);
			if ($flds[0] == '108') {
			  $pieces = explode('_', $flds[1]);
			  if (count($pieces) >= 2) {
			    $fromTitle = $dbr->addQuotes($pieces[1].'_'.$pieces[0].'_'.$pieces[count($pieces)-1]);
			  }
			}
      $fromDesc = $dbr->addQuotes($fromIssue);
			if ( 'prev' == $dir ) {
				$offsetCond = "(dq_namespace < $fromNamespace OR (dq_namespace = $fromNamespace AND $pageTitle < $fromTitle) " .
              "OR (dq_namespace = $fromNamespace AND $pageTitle = $fromTitle AND dqi_issue_desc < $fromDesc))";
				$options['ORDER BY'] = "dq_namespace DESC, $pageTitle DESC, dqi_issue_desc DESC";
			} else {
				$offsetCond = "(dq_namespace > $fromNamespace OR (dq_namespace = $fromNamespace AND $pageTitle > $fromTitle) " . 
              "OR (dq_namespace = $fromNamespace AND $pageTitle = $fromTitle AND dqi_issue_desc >= $fromDesc))";
				$options['ORDER BY'] = "dq_namespace, $pageTitle, dqi_issue_desc";
			}
		} else {
			$offsetCond = false;
			$options['ORDER BY'] = "dq_namespace, $pageTitle, dqi_issue_desc";
		}
   
		// Read an extra row as an at-end check
		$queryLimit = $limit + 1;
		$options['LIMIT'] = $queryLimit;
		if ( $catCond ) {
			$conds[] = $catCond;
		}
		if ( $treeCond ) {
			$conds[] = $treeCond;
		}
    if ( $watchedCond ) {
      $conds[] = $watchedCond;
    }
		if ( $offsetCond ) {
			$conds[] = $offsetCond;
		}
		$fields = array( 'dq_namespace', 'dq_title', 'dqi_issue_desc' );
 
		$res = $dbr->select( array( 'dq_issue', 'dq_page' ), $fields, $conds, $fname, $options );

		if ( !$dbr->numRows( $res ) ) {
			return;
		}

		// Read the rows into an array
		$rows = array();
		while ( $row = $dbr->fetchObject( $res ) ) {
			$pageTitle = $row->dq_title;
			if ($row->dq_namespace == 108) {
			  $pieces = explode('_', $pageTitle);
			  if (count($pieces) >= 2) {
			    $pageTitle = implode('_',array_slice($pieces, 1, -1)).'_'.$pieces[0].'_'.$pieces[count($pieces)-1];
			  }
			}
			$rows[] = $row;
		}
		$dbr->freeResult( $res );

		$numRows = count( $rows );

		// Work out the start and end IDs, for prev/next links
		if ( $dir == 'prev' ) {
			// Descending order
			if ( $numRows > $limit ) {
				// More rows available before these ones
				// Get the ID from the top row displayed
				$prevId = $rows[$limit-1]->dq_namespace . ':' . $rows[$limit-1]->dq_title . ':' . $rows[$limit-1]->dqi_issue_desc;
				// Remove undisplayed rows
				$rows = array_slice( $rows, 0, $limit );
			} else {
				// No more rows available before
				$prevId = '';
			}
			// Assume that the ID specified in $from exists, so there must be another page
			$nextId = $fromPage . ($fromPage == '' ? '' : ':') . $fromIssue;

			// Reverse order ready for display
			$rows = array_reverse( $rows );

		} else {
			// Ascending
			if ( $numRows > $limit ) {
				// More rows available after these ones
				// Get the ID from the last row in the result set
				$nextId = $rows[$limit]->dq_namespace . ':' . $rows[$limit]->dq_title  . ':' . $rows[$limit]->dqi_issue_desc;
				// Remove undisplayed rows
				$rows = array_slice( $rows, 0, $limit );
			} else {
				// No more rows after
				$nextId = false;
			}
        $prevId = $fromPage . ($fromPage == '' ? '' : ':') . $fromIssue ;
		}

    $otherParms = '';  // add parameters such as filters to prev/next links
    if ( $category != '' ) {
      $otherParms .= '&category=' . $category;
    }
    if ( $tree != '' ) {
      $otherParms .= '&tree=' . $tree;
    }
    $otherParms .= '&watched=' . $watched; 
	  $prevnext = $this->getPrevNext( $limit, $prevId, $nextId, $otherParms );
		$wgOut->addHTML( $prevnext );

		$wgOut->addHTML( '<table>' );
		foreach ( $rows as $row ) {
			$nt = Title::makeTitle( $row->dq_namespace, $row->dq_title );
      $pageTitle = '';
      if ($row->dq_namespace == 108) {
			  $pieces = explode('_', $row->dq_title);
			  if (count($pieces) >= 2) {
			    $pageTitle = 'Person:'.implode(' ',array_slice($pieces, 1, -1)).', '.$pieces[0].' '.$pieces[count($pieces)-1];
			  }
      }
			$link = $this->skin->makeKnownLinkObj( $nt, $pageTitle );
			$wgOut->addHTML( '<tr><td>' . $link . '</td>' );

			// Display issue description
  		$wgOut->addHTML( '<td>' . $row->dqi_issue_desc . '</td>' );
			$wgOut->addHTML( "</tr>\n" );
		}
		$wgOut->addHTML( "</table>\n" );

		$wgOut->addHTML( $prevnext );
	}

	function makeSelfLink( $text, $query ) {
		return $this->skin->makeKnownLinkObj( $this->selfTitle, $text, $query );
	}

  function getPrevNext( $limit, $prevId, $nextId, $otherParms ) {     // other parameters added Sep 2020 by Janet Bjorndahl
		global $wgLang;
		$fmtLimit = $wgLang->formatNum( $limit );
		$prev = wfMsg( 'prevn', $fmtLimit );
		$next = wfMsg( 'nextn', $fmtLimit );

		if ( 0 != $prevId ) {
			$prevLink = $this->makeSelfLink( $prev, "limit={$limit}{$otherParms}&from={$prevId}&dir=prev" );
		} else {
			$prevLink = $prev;
		}
		if ( 0 != $nextId ) {
			$nextLink = $this->makeSelfLink( $next, "limit={$limit}{$otherParms}&from={$nextId}" );
		} else {
			$nextLink = $next;
		}
		$nums = $this->numLink( 20, $prevId, $otherParms ) . ' | ' .
		  $this->numLink( 50, $prevId, $otherParms ) . ' | ' .
		  $this->numLink( 100, $prevId, $otherParms ) . ' | ' .
		  $this->numLink( 250, $prevId, $otherParms ) . ' | ' .
		  $this->numLink( 500, $prevId, $otherParms );

		return wfMsg( 'viewprevnext', $prevLink, $nextLink, $nums );
	}

	function numLink( $limit, $from, $otherParms ) {    // other parameters added Sep 2020 by Janet Bjorndahl
		global $wgLang;
		$query = "limit={$limit}{$otherParms}&from={$from}";
		$fmtLimit = $wgLang->formatNum( $limit );
		return $this->makeSelfLink( $fmtLimit, $query );
	}
}

?>
