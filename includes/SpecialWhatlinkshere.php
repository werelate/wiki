<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */

/**
 * Entry point
 * @param string $par An article name ??
 */
function wfSpecialWhatlinkshere($par = NULL) {
	global $wgRequest;
	$page = new WhatLinksHerePage( $wgRequest, $par );
	$page->execute();
}

class WhatLinksHerePage {
	var $request, $par;
	var $limit, $from, $dir, $target;
	var $selfTitle, $skin;

	function WhatLinksHerePage( &$request, $par = null ) {
		global $wgUser;
		$this->request =& $request;
		$this->skin =& $wgUser->getSkin();
		$this->par = $par;
	}

	function execute() {
		global $wgOut, $wgRequest;

		$this->limit = min( $this->request->getInt( 'limit', 500 ), 5000 );
		if ( $this->limit <= 0 ) {
			$this->limit = 500;
		}
		$this->from = $this->request->getText( 'from' );
		$this->dir = $this->request->getText( 'dir', 'next' );
		if ( $this->dir != 'prev' ) {
			$this->dir = 'next';
		}

		$targetString = isset($this->par) ? $this->par : $this->request->getVal( 'target' );

		if (is_null($targetString)) {
			$wgOut->showErrorPage( 'notargettitle', 'notargettext' );
			return;
		}

		$this->target = Title::newFromURL( $targetString );
		if( !$this->target ) {
			$wgOut->showErrorPage( 'notargettitle', 'notargettext' );
			return;
		}
		$this->selfTitle = Title::makeTitleSafe( NS_SPECIAL,
			'Whatlinkshere/' . $this->target->getPrefixedDBkey() );
		$wgOut->setPagetitle( $this->target->getPrefixedText() );
		$wgOut->setSubtitle( wfMsg( 'linklistsub' ) );

		$isredir = ' (' . wfMsg( 'isredirect' ) . ")\n";

		$wgOut->addHTML('&lt; '.$this->skin->makeLinkObj($this->target, '', 'redirect=no' )."<br />\n");
   
    // Filter on namespace added Sep 2020 by Janet Bjorndahl
    $this->ns = $wgRequest->getVal('namespace');
    $nsForm = '<br><form method="get" action="/wiki/' . $this->selfTitle->getPrefixedURL() . '">';
    $nsForm .= '<label for="namespace">Namespace: </label>';
    $nsForm .= HTMLnamespaceselector( $this->ns, '' );
    $nsForm .= '<input type="hidden" name="limit" value="' . $this->limit . '">';
	  $nsForm .= '<input type="submit" name="submit" value="' . wfMsgExt( 'allpagessubmit', array( 'escape') ) . '" />';
  	$nsForm .= '</form>';
  	$wgOut->addHTML( $nsForm );

		$this->showIndirectLinks( 0, $this->target, $this->limit, $this->from, $this->dir, $this->ns );
	}

	function padNs($ns) {
		if ($ns < 10) {
			return '00'.$ns;
		}
		if ($ns < 100) {
			return '0'.$ns;
		}
		return $ns;
	}

	/**
	 * @param int       $level      Recursion level
	 * @param Title     $target     Target title
	 * @param int       $limit      Number of entries to display
	 * @param Title     $from       Display from this article ID
	 * @param string    $dir        'next' or 'prev', whether $fromTitle is the start or end of the list
	 * @private
	 */
	function showIndirectLinks( $level, $target, $limit, $from = '', $dir = 'next', $ns = '' ) {
		global $wgOut;
		$fname = 'WhatLinksHerePage::showIndirectLinks';

		$dbr =& wfGetDB( DB_READ );

		extract( $dbr->tableNames( 'pagelinks', 'templatelinks', 'page' ) );

		// Some extra validation
		if ( !$from && $dir == 'prev' ) {
			// Before start? No make sense
			$dir = 'next';
		}

		// Make the query
		$plConds = array(
			'page_id=pl_from',
			'pl_namespace' => $target->getNamespace(),
			'pl_title' => $target->getDBkey(),
		);

		$tlConds = array(
			'page_id=tl_from',
			'tl_namespace' => $target->getNamespace(),
			'tl_title' => $target->getDBkey(),
		);

		$options = array();
		$pageTitle = "case page_namespace when 108 then concat(substring_index(substring_index(page_title, '_', 2), '_', -1), '_', substring_index(page_title, '_', 1), '_', substring_index(page_title, '_', -1)) else page_title end";
   
    if ( $ns == '' ) {      // filter on namespace added Sep 2020 by Janet Bjorndahl 
      $nsCond = false;
    }  
    else {
      $nsCond = "page_namespace = $ns";
    }

    if ( $from && strpos($from, ':')) {
			$flds = split(":", $from, 2);
			$fromNamespace = $dbr->addQuotes($flds[0]);
			$fromTitle = $dbr->addQuotes($flds[1]);
			if ($flds[0] == '108') {
			  $pieces = explode('_', $flds[1]);
			  if (count($pieces) >= 2) {
			    $fromTitle = $dbr->addQuotes($pieces[1].'_'.$pieces[0].'_'.$pieces[count($pieces)-1]);
			  }
			}
			if ( 'prev' == $dir ) {
				$offsetCond = "(page_namespace < $fromNamespace OR (page_namespace = $fromNamespace AND $pageTitle < $fromTitle))";
				$options['ORDER BY'] = "page_namespace DESC, $pageTitle DESC";
			} else {
				$offsetCond = "(page_namespace > $fromNamespace OR (page_namespace = $fromNamespace AND $pageTitle >= $fromTitle))";
				$options['ORDER BY'] = "page_namespace, $pageTitle";
			}
		} else {
			$offsetCond = false;
			$options['ORDER BY'] = "page_namespace, $pageTitle";
		}
   
		// Read an extra row as an at-end check
		$queryLimit = $limit + 1;
		$options['LIMIT'] = $queryLimit;
    if ( $nsCond ) {       // filter on namespace added Sep 2020 by Janet Bjorndahl
      $plConds[] = $nsCond;
    }
		if ( $offsetCond ) {
			$tlConds[] = $offsetCond;
			$plConds[] = $offsetCond;
		}
		$fields = array( 'page_id', 'page_namespace', 'page_title', 'page_is_redirect' );

		$plRes = $dbr->select( array( 'pagelinks', 'page' ), $fields,
			$plConds, $fname, $options );
		$tlRes = $dbr->select( array( 'templatelinks', 'page' ), $fields,
			$tlConds, $fname, $options );

		if ( !$dbr->numRows( $plRes ) && !$dbr->numRows( $tlRes ) ) {
			if ( 0 == $level ) {
        if ( $ns == '' ) {
				  $wgOut->addWikiText( wfMsg( 'nolinkshere' ) );
        }
        else {
				  $wgOut->addWikiText( wfMsg( 'nonamespacelinkshere' ) );   // If user selected a namespace, the message is different (added Sep 2020 by Janet Bjorndahl)
        }
			}
			return;
		}

		// Read the rows into an array and remove duplicates
		// templatelinks comes second so that the templatelinks row overwrites the
		// pagelinks row, so we get (inclusion) rather than nothing
		$rows = array();
		while ( $row = $dbr->fetchObject( $plRes ) ) {
			$row->is_template = 0;
			$pageTitle = $row->page_title;
			if ($row->page_namespace == 108) {
			  $pieces = explode('_', $pageTitle);
			  if (count($pieces) >= 2) {
			    $pageTitle = implode('_',array_slice($pieces, 1, -1)).'_'.$pieces[0].'_'.$pieces[count($pieces)-1];
			  }
			}
			$rows[$this->padNs($row->page_namespace).':'.$pageTitle] = $row;
		}
		$dbr->freeResult( $plRes );
		while ( $row = $dbr->fetchObject( $tlRes ) ) {
			$row->is_template = 1;
			$pageTitle = $row->page_title;
			if ($row->page_namespace == 108) {
			  $pieces = explode('_', $pageTitle);
			  if (count($pieces) >= 2) {
			    $pageTitle = implode('_',array_slice($pieces, 1, -1)).'_'.$pieces[0].'_'.$pieces[count($pieces)-1];
			  }
			}
			$rows[$this->padNs($row->page_namespace).':'.$pageTitle] = $row;
		}
		$dbr->freeResult( $tlRes );

		// Sort by key and then change the keys to 0-based indices
		ksort( $rows );
		$rows = array_values( $rows );

		$numRows = count( $rows );

		// Work out the start and end IDs, for prev/next links
		if ( $dir == 'prev' ) {
			$rows = array_reverse($rows);
			// Descending order
			if ( $numRows > $limit ) {
				// More rows available before these ones
				// Get the ID from the top row displayed
				$prevId = $rows[$limit-1]->page_namespace.':'.$rows[$limit-1]->page_title;
				// Remove undisplayed rows
				$rows = array_slice( $rows, 0, $limit );
			} else {
				// No more rows available before
				$prevId = '';
			}
			// Assume that the ID specified in $from exists, so there must be another page
			$nextId = $from;

			// Reverse order ready for display
			$rows = array_reverse( $rows );
		} else {
			// Ascending
			if ( $numRows > $limit ) {
				// More rows available after these ones
				// Get the ID from the last row in the result set
				$nextId = $rows[$limit]->page_namespace.':'.$rows[$limit]->page_title;
				// Remove undisplayed rows
				$rows = array_slice( $rows, 0, $limit );
			} else {
				// No more rows after
				$nextId = false;
			}
			$prevId = $from;
		}

		if ( 0 == $level ) {
			$wgOut->addWikiText( wfMsg( 'linkshere' ) );
		}
		$isredir = wfMsg( 'isredirect' );
		$istemplate = wfMsg( 'istemplate' );

		if( $level == 0 ) {
      $otherParms = '';  // add parameters such as filtered namespace to prev/next links (added Sep 2020 by Janet Bjorndahl)
      if ( $ns != '' ) {
        $otherParms .= '&namespace=' . $ns;
      }
			$prevnext = $this->getPrevNext( $limit, $prevId, $nextId, $otherParms );
			$wgOut->addHTML( $prevnext );
		}

		$wgOut->addHTML( '<ul>' );
		foreach ( $rows as $row ) {
			$nt = Title::makeTitle( $row->page_namespace, $row->page_title );

			if ( $row->page_is_redirect ) {
				$extra = 'redirect=no';
			} else {
				$extra = '';
			}

            $pageTitle = '';
            if ($row->page_namespace == 108) {
			  $pieces = explode('_', $row->page_title);
			  if (count($pieces) >= 2) {
			    $pageTitle = 'Person:'.implode(' ',array_slice($pieces, 1, -1)).', '.$pieces[0].' '.$pieces[count($pieces)-1];
			  }
            }
			$link = $this->skin->makeKnownLinkObj( $nt, $pageTitle, $extra );
			$wgOut->addHTML( '<li>'.$link );

			// Display properties (redirect or template)
			$props = array();
			if ( $row->page_is_redirect ) {
				$props[] = $isredir;
			}
			if ( $row->is_template ) {
				$props[] = $istemplate;
			}
			if ( count( $props ) ) {
				// FIXME? Cultural assumption, hard-coded punctuation
				$wgOut->addHTML( ' (' . implode( ', ', $props ) . ') ' );
			}

			if ( $row->page_is_redirect ) {
				if ( $level < 2 ) {
					$this->showIndirectLinks( $level + 1, $nt, 500 );
				}
			}
			$wgOut->addHTML( "</li>\n" );
		}
		$wgOut->addHTML( "</ul>\n" );

		if( $level == 0 ) {
			$wgOut->addHTML( $prevnext );
		}
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
