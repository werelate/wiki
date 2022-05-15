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
	var $limit, $fromOrder, $dir, $target;
	var $selfTitle, $skin;
 
  private static $DATA_QUALITY_CATEGORIES = array(
      'all' => '',  
      'Anomalies' => 'Anomaly',
      'Errors' => 'Error',
//      'Intergenerational' => 'Relationship',
      'Incomplete' => 'Incomplete',
//      'Living' => 'Living'
   );
   
   private static $VERIFIED_OPTIONS = array(
     'Exclude verified' => 'u',
     'Include verified' => 'vu',
     'Only verified' => 'v'
   );  
   
   private static $DATA_QUALITY_TEMPLATES = array(
     'Wife younger than' => 'UnusuallyYoungWife',
     'Husband younger than' => 'UnusuallyYoungHusband',
     'Wife older than' => 'UnusuallyOldWife',
     'Husband older than' => 'UnusuallyOldHusband',
     "Born before parents' marriage" => 'BirthBeforeParentsMarriage',
     'Born over' => 'BirthLongAfterParentsMarriage',
     'Born before mother was' => 'UnusuallyYoungMother',
     'Born before father was' => 'UnusuallyYoungFather',
     'Born after mother was' => 'UnusuallyOldMother',
     'Born after father was' => 'UnusuallyOldFather'
   );

	function DataQuality( &$request ) {
		global $wgUser;
		$this->request =& $request;
		$this->skin =& $wgUser->getSkin();
	}

	function execute() {
		global $wgOut, $wgRequest, $wgLang, $wgUser, $wgScriptPath;
		$fname = 'DataQualityPage::execute';

		$this->limit = min( $this->request->getInt( 'limit', 50 ), 500 );
		if ( $this->limit <= 0 ) {
			$this->limit = 50;
		}

    $this->fromOrder = $this->request->getText( 'from' );
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
		$wgOut->addWikiText( $cacheNotice . " View the [[Talk:Data Quality Issues|talk page]]. See [[Help:Monitoring Data Quality]] for more information.");

    // Filters
    $this->category = $wgRequest->getVal('category');
    $this->verified = $wgRequest->getVal('verified');
    if (!$this->verified) {
      $this->verified = 'u';
    }
    $this->watched = $wgRequest->getVal('watched');
    if (!$this->watched) {
    	$this->watched = 'wu';
    }
    $this->tree = $wgRequest->getVal('tree');
    
    if ($wgUser->isLoggedIn()) {
      // If user selected watched or unwatched, disable MyTrees dropdown. If user selected MyTree(s), disable watched/unwatched dropdown.
      if ($this->watched == 'wu') {
        $treeSelectExtra = '';
      }
      else {
        $treeSelectExtra = 'disabled';
      }
      if ($this->tree == '') {
        $watchSelectExtra = '';
      }
      else {
        $watchSelectExtra = 'disabled';
      }
    }
    else {
	   	$watchSelectExtra = 'disabled';
	   	$treeSelectExtra = 'disabled';
      $this->watched = 'wu';
	  }
    // Set up javascript to toggle enabling/disabling one dropdown depending on selection in another dropdown. 
    $treeSelectExtra .= ' onchange="toggleEnabled(\'tree\', \'\', \'watched\')"';
    $watchSelectExtra .= ' onchange="toggleEnabled(\'watched\', \'wu\', \'tree\')"';

    $myTreeOptions = array();
    $myTreeOptions['Whether or not in'] = '';
    $treeCounter = 0;
    if ($wgUser->isLoggedIn()) {
      $conds[] = 'ft_user = "' . $wgUser->getName() . '"';
      $options['ORDER BY'] = 'ft_name';
		  $res = $dbr->select( array( 'familytree' ), array( 'ft_name' , 'ft_tree_id'), $conds, $fname, $options );
		  while ( $row = $dbr->fetchObject( $res ) ) {
        $treeCounter++;
        /* Searching in all of a users' trees times out (even after 5 minutes) - don't add this option back unless an indexing solution is found
        if ($treeCounter==1) {
          $myTreeOptions['In any of MyTrees'] = 'all';
        }
        */
			  $myTreeOptions[substr($row->ft_name,0,30)] = $row->ft_tree_id;
		  }
		  $dbr->freeResult( $res );
    } 

 		$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/report.2.js\"></script>");

    $parmForm = '<form method="get" action="/wiki/' . $this->selfTitle->getPrefixedURL() . '">';
    $parmForm .= '<table class="parmform"><tr><td><label for="category">Category: </label>';
    $parmForm .= StructuredData::addSelectToHtml(0, 'category', self::$DATA_QUALITY_CATEGORIES, $this->category, '', false) . '</td>';
    $parmForm .= '<td>' . StructuredData::addSelectToHtml(0, 'verified', self::$VERIFIED_OPTIONS, $this->verified, '', false) . '</td>';
    $parmForm .= '<td><label for="tree">MyTrees: </label>';
    $parmForm .= StructuredData::addSelectToHtml(0, 'tree', $myTreeOptions, $this->tree, $treeSelectExtra, false) . '</td>';
    $parmForm .= '<td>' . StructuredData::addSelectToHtml(0, 'watched', SearchForm::$WATCH_OPTIONS, $this->watched, $watchSelectExtra, false) . '</td>';
    $parmForm .= '<input type="hidden" name="limit" value="' . $this->limit . '">';
	  $parmForm .= '<td><input type="submit" value="' . wfMsgExt( 'allpagessubmit', array( 'escape') ) . '" /></td></tr></table>';
  	$parmForm .= '</form>';
  	$wgOut->addHTML( $parmForm );
   
    // If user is filtering on watchlist or a MyTree, limit the number of rows that will be returned, for performance reasons.   
    if ($this->watched=='w' && $this->limit > 50) {
      $wgOut->addHTML('<p><span class="attn">Note</span>: When selecting only watched pages, scrolling is limited to 50 issues at a time, for performance reasons.</p>');
      $this->limit = 50;
    }
    if ($this->tree!='' && $this->limit > 20) {
      $wgOut->addHTML('<p>Note: When filtering on a MyTree, scrolling is limited to 20 issues at a time, for performance reasons.</p>');
      $this->limit = 20;
    }

		$this->showIssues( $this->limit, $this->fromOrder, $this->dir, $this->category, $this->tree, $this->verified, $this->watched );
	}
	/**
	 * @param int       $level      Recursion level
	 * @param Title     $target     Target title
	 * @param int       $limit      Number of entries to display
	 * @param Title     $fromOrder  Display from this issue order number
	 * @param string    $dir        'next' or 'prev', whether $fromOrder is the start or end of the list
	 * @private
	 */
	function showIssues( $limit, $fromOrder = '', $dir = 'next', $category = '', $tree = '', $verified = 'u', $watched = 'wu' ) {
		global $wgOut, $wgUser;
		$fname = 'DataQualityPage::showIssues';

		$dbr =& wfGetDB( DB_SLAVE );

		// Some extra validation
		if ( !$fromOrder && $dir == 'prev' ) {
			// Before start? No make sense
			$dir = 'next';
		}

		// Make the query - start with basic criteria and join conditions
		$conds = array(
      'dqi_job_id = (SELECT max(dq_job_id) FROM dq_page)',  // driving table is dq_issue, but dq_page identifies the most recently completed job
      'dq_job_id = dqi_job_id',
			'dq_page_id = dqi_page_id',
		);

		$options = array();
   
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
    
    if ( $verified == 'v' ) {
      $verifiedCond = 'dqi_verified_by IS NOT NULL';
    }
    if ( $verified == 'u' ) {
      $verifiedCond = 'dqi_verified_by IS NULL';
    }
    if ( $verified == 'vu' ) {
      $verifiedCond = false;
    }
        
    if ( $fromOrder ) {
			if ( 'prev' == $dir ) {
				$offsetCond = "dqi_order < $fromOrder";
				$options['ORDER BY'] = "dqi_order DESC";
			} else {
				$offsetCond = "dqi_order >= $fromOrder";
				$options['ORDER BY'] = "dqi_order";
			}
		} else {
			$offsetCond = false;
			$options['ORDER BY'] = "dqi_order";
		}
   
		// Read an extra row as an at-end check
		$queryLimit = $limit + 1;
		$options['LIMIT'] = $queryLimit;
		if ( $catCond ) {
			$conds[] = $catCond;
		}
    if ( $verifiedCond ) {
      $conds[] = $verifiedCond;
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
		$fields = array( 'dqi_order', 'dq_page_id', 'dq_namespace', 'dq_title', 'dqi_category', 'dqi_issue_desc', 'dqi_verified_by', 'dq_viewed_by' );
 
		$res = $dbr->select( array( 'dq_issue', 'dq_page' ), $fields, $conds, $fname, $options );

		if ( !$dbr->numRows( $res ) ) {
			return;
		}

		// Read the rows into an array
		$rows = array();
		while ( $row = $dbr->fetchObject( $res ) ) {
      $row->dq_title = Title::newFromID($row->dq_page_id)->getDbkey();     // replace title from dq_page with title from the wiki database to handle special characters correctly 
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
				$prevId = $rows[$limit-1]->dqi_order;
				// Remove undisplayed rows
				$rows = array_slice( $rows, 0, $limit );
			} else {
				// No more rows available before
				$prevId = '';
			}
			// Assume that the ID specified in $from exists, so there must be another page
			$nextId = $fromOrder;

			// Reverse order ready for display
			$rows = array_reverse( $rows );

		} else {
			// Ascending
			if ( $numRows > $limit ) {
				// More rows available after these ones
				// Get the ID from the last row in the result set
				$nextId = $rows[$limit]->dqi_order;
				// Remove undisplayed rows
				$rows = array_slice( $rows, 0, $limit );
			} else {
				// No more rows after
				$nextId = false;
			}
        $prevId = $fromOrder;
		}

    $otherParms = '';  // add parameters such as filters to prev/next links
    if ( $category != '' ) {
      $otherParms .= '&category=' . $category;
    }
    $otherParms .= '&verified=' . $verified;
    if ( $tree != '' ) {
      $otherParms .= '&tree=' . $tree;
    }
    $otherParms .= '&watched=' . $watched; 
	  $prevnext = $this->getPrevNext( $limit, $prevId, $nextId, $otherParms );
		$wgOut->addHTML( $prevnext );

		$wgOut->addHTML( "\n<table id=\"issue_list\">" );
    $rowNum = 0;
		foreach ( $rows as $row ) {
      $rowNum++;
      $nt = Title::makeTitle($row->dq_namespace, $row->dq_title);      
      $pageTitle = '';
      if ($row->dq_namespace == 108) { 
			  $pieces = explode('_', $row->dq_title);
			  if (count($pieces) >= 2) {
			    $pageTitle = 'Person:'.implode(' ',array_slice($pieces, 1, -1)).', '.$pieces[0].' '.$pieces[count($pieces)-1];
			  }
      }
			$link = $this->skin->makeKnownLinkObj( $nt, $pageTitle );
			$wgOut->addHTML( '<tr><td>' . $link . '</td>' );

			// Display issue description and verification info
  		$wgOut->addHTML( '<td>' . $row->dqi_issue_desc . '</td>' );
      if ( $row->dqi_verified_by!='') {
  		  $wgOut->addHTML( '<td>Verified by ' . $row->dqi_verified_by . '</td>' );
      }
      else {
        $wgOut->addHTML( '<td></td>' );
      }
      
      // If user is logged in, add verify button (if applicable) and deferred info/button
      if ( $wgUser->isLoggedIn() ) {
        if ( $row->dqi_category == "Anomaly" ) {
          $template = '';
          foreach ( array_keys(self::$DATA_QUALITY_TEMPLATES) as $partialIssueDesc ) {
            if ( substr($row->dqi_issue_desc, 0, strlen($partialIssueDesc)) == $partialIssueDesc ) {
              $template = urlencode(self::$DATA_QUALITY_TEMPLATES[$partialIssueDesc]);
              break;  
            }
          }
          if ( $template != '' ) {
            $wgOut->addHTML( '<td><input type="button" id="verify' . $rowNum . '" title="Track that you verified this isn\'t an error and added sources as necessary to support the data" value="' . 
                    wfMsgExt( 'verified', array( 'escape') ) . 
                    '" onClick="addVerifiedTemplate(' . $rowNum . ',' . $row->dq_page_id . ',' . $row->dq_namespace . ',\'' . $row->dq_title . '\',\'' . 
                    $template . '\',\'' . urlencode($row->dqi_issue_desc) . '\')" /></td>' );
          }  
        }
        else {
          $wgOut->addHTML( '<td></td>' );
        }
        if ( strpos($row->dq_viewed_by, '|' . $wgUser->getName() . '|') !== false ) {
          $wgOut->addHTML( '<td><span class="attn">Deferred</span></td>');
        }  
        else {
          $wgOut->addHTML( '<td><input type="button" id="defer' . $rowNum . '" title="Do not show me this issue again until I request to see hidden issues." value="' . 
                  wfMsgExt( 'deferissue', array( 'escape') ) . 
                  '" onClick="addDeferredTemplate(' . $rowNum . ',' . $row->dq_page_id . ',' . $row->dq_namespace . ',\'' . $row->dq_title . '\')" /></td>' );
        }      
      }
			$wgOut->addHTML( "</tr>\n" );
		}
		$wgOut->addHTML( "</table>\n" );

		$wgOut->addHTML( $prevnext );
	}

	function makeSelfLink( $text, $query ) {
		return $this->skin->makeKnownLinkObj( $this->selfTitle, $text, $query );
	}

  function getPrevNext( $limit, $prevId, $nextId, $otherParms ) {
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

	function numLink( $limit, $from, $otherParms ) {
		global $wgLang;
		$query = "limit={$limit}{$otherParms}&from={$from}";
		$fmtLimit = $wgLang->formatNum( $limit );
		return $this->makeSelfLink( $fmtLimit, $query );
	}
}

?>
