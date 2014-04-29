<?php
/**
 * @package MediaWiki
 */

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialReviewMergeSetup";

function wfSpecialReviewMergeSetup() {
	global $wgMessageCache, $wgSpecialPages;
	
	$wgMessageCache->addMessages( array( "reviewmerge" => "Review merge" ) );
	$wgSpecialPages['ReviewMerge'] = array('SpecialPage','ReviewMerge');
}

/**
 * Called to display the Special:ReviewMerge page
 *
 * @param unknown_type $par
 * @param unknown_type $specialPage
 */
function wfSpecialReviewMerge( $par=NULL, $specialPage ) {
	global $wgOut, $wgScriptPath, $wgUser, $wrSidebarHtml;

	$reviewForm = new ReviewForm();

	// read query parameters into variables
   $unmerge = '';
	if (!$reviewForm->readQueryParms($par)) {
		$wgOut->setPageTitle('Review merge');
		$results = '<p>'. wfMsg('clickreviewtoreview').'</p>';
	}
	else if ($reviewForm->isMarkPatrolled()) {
		$wgOut->setPagetitle( wfMsg( 'markedaspatrolled' ) );
		$results = $reviewForm->markPatrolled();
	}
	else if ($reviewForm->isUnmerge()) {
	   if (!$wgUser->isLoggedIn()) {
			if( !$wgCommandLineMode && !isset( $_COOKIE[session_name()] )  ) {
				User::SetupSession();
			}
			$title = Title::makeTitle(NS_SPECIAL, 'ReviewMerge/'.$par);
			$requestData = array();
			if ($title) {
				$requestData['returnto'] = $title->getPrefixedUrl();
			}
			$request = new FauxRequest($requestData);
			require_once('includes/SpecialUserlogin.php');
			$form = new LoginForm($request);
			$form->mainLoginForm("You need to sign in to unmerge pages<br/><br/>", '');
			return;
		}
		if( $wgUser->isBlocked() ) {
			$wgOut->blockedPage();
			return;
		}
		else if( wfReadOnly() ) {
			$wgOut->readOnlyPage();
			return;
		}
		$wgOut->setPagetitle('Unmerge');
		$results = $reviewForm->unmerge();
	}
	else {
		$wgOut->setPageTitle('Review merge');
		$wrSidebarHtml = $reviewForm->getReviewSideText();
		$results = $reviewForm->getReviewResults();
      $unmerge = $reviewForm->getUnmergeInfo();
	}
	
	$wgOut->addHTML($unmerge . $results);
}

 /**
  * Review form used in Special:ReviewMerge
  */
class ReviewForm {
	private static $PERSON_COMPARE_LABELS = array('Title', 'Given', 'Surname', 'Prefix', 'Suffix', 'Gender', 'Birthdate', 'Birthplace', 'Christeningdate', 'Christeningplace', 'Deathdate', 'Deathplace', 'Burialdate', 'Burialplace',
																 'OtherEvents', 'Sources', 'Images', 'Notes', 'Contents',
																 'fatherTitle', 'fatherGiven', 'fatherSurname', 'fatherPrefix', 'fatherSuffix', 'fatherBirthdate', 'fatherBirthplace', 'fatherChristeningdate', 'fatherChristeningplace', 'fatherDeathdate', 'fatherDeathplace', 'fatherBurialdate', 'fatherBurialplace',
																 'motherTitle', 'motherGiven', 'motherSurname', 'motherPrefix', 'motherSuffix', 'motherBirthdate', 'motherBirthplace', 'motherChristeningdate', 'motherChristeningplace', 'motherDeathdate', 'motherDeathplace', 'motherBurialdate', 'motherBurialplace',
																 'spouseTitle', 'spouseGiven', 'spouseSurname', 'spousePrefix', 'spouseSuffix', 'spouseBirthdate', 'spouseBirthplace', 'spouseChristeningdate', 'spouseChristeningplace', 'spouseDeathdate', 'spouseDeathplace', 'spouseBurialdate', 'spouseBurialplace');
	private static $FAMILY_COMPARE_LABELS = array('familyTitle', 'Marriagedate', 'Marriageplace',
																 'OtherEvents', 'Sources', 'Images', 'Notes', 'Contents');
	private static $FAMILY_MEMBER_COMPARE_LABELS = array('Title', 'Given', 'Surname', 'Prefix', 'Suffix', 'Gender', 'Birthdate', 'Birthplace', 'Christeningdate', 'Christeningplace', 'Deathdate', 'Deathplace', 'Burialdate', 'Burialplace',
																 'OtherEvents', 'ParentFamilyTitle', 'SpouseFamilyTitle', 'Sources', 'Images', 'Notes', 'Contents');
	private static $OPTIONAL_LABELS = array('Prefix', 'Suffix', 'Christeningdate', 'Christeningplace', 'Burialdate', 'Burialplace',
														 'OtherEvents', 'ParentFamilyTitle', 'SpouseFamilyTitle', 'Sources', 'Images', 'Notes', 'Contents',
														 'fatherPrefix', 'fatherSuffix', 'fatherChristeningdate', 'fatherChristeningplace', 'fatherBurialdate', 'fatherBurialplace',
														 'motherPrefix', 'motherSuffix', 'motherChristeningdate', 'motherChristeningplace', 'motherBurialdate', 'motherBurialplace',
														 'spousePrefix', 'spouseSuffix', 'spouseChristeningdate', 'spouseChristeningplace', 'spouseBurialdate', 'spouseBurialplace');
	private $rcid;
	private $action;
	private $comment;
	private $mergeId;
	private $timestamp;
	private $userid;
	private $unmergeUserid;
	private $unmergeTimestamp;
	private $mergeTitle;
	private $merges;
	
   public function readQueryParms($par) {
      global $wgRequest;
		
      $this->rcid = $wgRequest->getVal('rcid');
      $this->action = $wgRequest->getVal('action');
      $this->comment = $wgRequest->getVal('comment');
      $this->mergeId = intval($par);
      if (!$this->mergeId) return false;
   	$db =& wfGetDB(DB_SLAVE);
   	$row = $db->selectRow('mergelog', array('ml_timestamp', 'ml_user', 'ml_unmerge_user', 'ml_unmerge_timestamp', 'ml_namespace', 'ml_title', 'ml_pages'),
   								array('ml_id' => $this->mergeId));
      if (!$row) return false;
      $this->timestamp = $row->ml_timestamp;
      $this->userid = $row->ml_user;
      $this->unmergeUserid = $row->ml_unmerge_user;
      $this->unmergeTimestamp = $row->ml_unmerge_timestamp;
      $this->mergeTitle = Title::makeTitle($row->ml_namespace, $row->ml_title);
      $this->merges = explode("\n", $row->ml_pages);
      return true;
   }
   
   public function isMarkPatrolled() {
   	global $wgUseRCPatrol, $wgUser;
   	
   	return $this->action == 'markpatrolled' && $this->rcid && $wgUseRCPatrol && $wgUser->isAllowed('patrol');
	}
	
	public function isUnmerge() {
		return $this->action == 'unmerge' && !$this->unmergeTimestamp;
	}
	
	public function markPatrolled() {
		global $wgUser;

		$sk = $wgUser->getSkin();
		$output = '';
		if( wfRunHooks( 'MarkPatrolled', array( &$this->rcid, &$wgUser, false ) ) ) {
			RecentChange::markPatrolled( $this->rcid );
			wfRunHooks( 'MarkPatrolledComplete', array( &$this->rcid, &$wgUser, false ) );
			$output .= wfMsg('selectedmarkedpatrolled', ($this->unmergeTimestamp ? 'unmerge' : 'merge') );
		}
		$rcTitle = Title::makeTitle( NS_SPECIAL, 'Recentchanges' );
		$output .= '<p>Return to '.$sk->makeKnownLinkObj($rcTitle).'.</p>';		
		return $output;
	}
	
	private function revert($page, $isFamilyPage) {
		$revision = Revision::newFromId($page['revid']);
		$text = $revision->getText();
		if ($isFamilyPage) {
			// get current person attrs
			// TODO - this should really be done in propagateEditData for family
    		$text = preg_replace_callback('$<(husband|wife|child)[^>]*? title="(.*?)"[^>]*>\n$', 
            // single quotes are essential here,
            // or alternative escape all $ as \$
				create_function('$matches', 
	    			'$t = Title::newFromText($matches[2], NS_PERSON); $t = StructuredData::getRedirectToTitle($t, true); return Family::formatFamilyMemberElement($matches[1], $t->getText());'), 
				$text);
		}
		$article = new Article($page['title'], 0);
		$mergeComment = 'Undo [[Special:ReviewMerge/'.$this->mergeId.'|previous merge]]'.($this->comment ? ' - '.$this->comment : '');
		$article->doEdit($text, $mergeComment, EDIT_UPDATE | EDIT_FORCE_BOT);
	}
	
	public function unmerge() {
		global $wgUser;

		$nonFamilyPages = array(); // contains revid, next_revid, title
		$familyPages = array();    // ditto
		$manualPages = array();    // ditto
		$unchangedPages = array(); // ditto
		
      $dbw =& wfGetDB(DB_MASTER);

		// break into different arrays
		$seenTitles = array();
      foreach ($this->merges as $merge) {
      	$fields = explode('|',$merge);
      	$role = $fields[0];
      	$revidSets = explode('#', $fields[1]);
      	foreach ($revidSets as $revidSet) {
      		if ($revidSet) {
	      		$revids = explode('/', $revidSet);
		      	foreach ($revids as $revid) {
		      		if ($revid) {
				      	// get two following revisions
			      		$rows = $dbw->query('SELECT r2.rev_page, r2.rev_id, r2.rev_comment FROM revision AS r1, revision AS r2'.
			      									' WHERE r1.rev_id = '.$revid.' AND r1.rev_page = r2.rev_page AND r2.rev_id > '.$revid.
			      									' ORDER BY r2.rev_id LIMIT 2');
							$cnt = 0;      									
							$cmt = '';
							$nextRevid = '';
							$pageId = '';
							while ($row = $dbw->fetchObject($rows)) {
								$cnt++;
								if ($cnt == 1) {
									$pageId = $row->rev_page;
									$nextRevid = $row->rev_id;
									$cmt = $row->rev_comment;
								}
							}
							$dbw->freeResult($rows);
			
							if ($cnt == 0) {
								$revision = Revision::newFromId($revid);
								if ($revision) {
									$title = $revision->getTitle();
								}
								else { // page must have been deleted
									$title = null;
								}
							}
							else {
								$title = Title::newFromId($pageId);
							}
			
							// TODO if title doesn't exist, then unmerged pages could have red links; oh well
							if ($title && !@$seenTitles[$title->getPrefixedText()]) {
								$seenTitles[$title->getPrefixedText()] = 1;
								$entry = array('revid' => $revid, 'next_revid' => $nextRevid, 'title' => $title);
								if ($cnt == 0 || strpos($cmt, '[[Special:ReviewMerge/'.$this->mergeId.'|') === false) {  
									$unchangedPages[] = $entry; // page was not edited in the merge
								}
								else if ($cnt > 1) {
									$manualPages[] = $entry;
								}
								else if ($role == 'Family') {
						  			PropagationManager::addBlacklistPage($title);
									$familyPages[] = $entry;
								}
								else {
					   			PropagationManager::addBlacklistPage($title);
									$nonFamilyPages[] = $entry;
								}
							}
			      	}
		      	}
      		}
      	}
      }

      // update nonFamilyPages
      foreach ($nonFamilyPages as $page) {
      	$this->revert($page, false);
		}
		
      // update familyPages
      foreach ($familyPages as $page) {
      	$this->revert($page, true);
      }
      
      // update mergelog
		$dbw->update('mergelog', array('ml_unmerge_user' => $wgUser->getID(), 'ml_unmerge_timestamp' => $dbw->timestamp( wfTimestampNow() )), 
						array('ml_id' => $this->mergeId), 'ReviewForm::unmerge');

		// add log and RC
		$mergeComment = 'Unmerge [['.$this->mergeTitle->getPrefixedText().']]'.($this->comment ? ' - '.$this->comment : '');
		$log = new LogPage( 'merge', false );
		$t = Title::makeTitle(NS_SPECIAL, "ReviewMerge/{$this->mergeId}");
		$log->addEntry('unmerge', $t, $mergeComment);
		RecentChange::notifyLog(wfTimestampNow(), $t, $wgUser, $mergeComment, '', 'merge', 'unmerge', 
										$t->getPrefixedText(), $mergeComment, '', 0, 0);
		
      // list pages
      $output = '';
   	$skin =& $wgUser->getSkin();
   	
      if (count($manualPages) == 0) {
      	$output .= "<h2>Unmerge successful</h2>\n";
		}
		else {
			$output .= "<h2>Unmerge partially completed</h2><p><b><font color=\"red\">The following page(s) must still be unmerged</font></b></p><ul>";
			foreach ($manualPages as $page) {
				$output .= "<li>".htmlspecialchars($page['title']->getPrefixedText()).' <b>'.
											$skin->makeKnownLinkObj($page['title'], wfMsg('changestoundo'), 'diff='.$page['next_revid'].'&oldid='.$page['revid']).'</b> &nbsp;=>&nbsp; <b>'.
											$skin->makeKnownLinkObj($page['title'], wfMsg('editlowercase'), 'action=edit')."</b></li>\n";
				error_log("Unmerge may be needed: {$page['title']->getPrefixedText()} id={$this->mergeId}");
			}
            $changestoundotext = wfMsg('changestoundotext');
			$output .= <<< END
</ul>
$changestoundotext
<p>&nbsp;</p>
END;
		}
		if (count($familyPages) + count($nonFamilyPages) + count($unchangedPages) > 0) {
			$output .= "<p><b>The following pages have been successfully unmerged:</b></p><ul>\n";
			foreach ($familyPages as $page) {
				$output .= "<li>".$skin->makeKnownLinkObj($page['title'])."</li>\n";
			}
			foreach ($nonFamilyPages as $page) {
				$output .= "<li>".$skin->makeKnownLinkObj($page['title'])."</li>\n";
			}
//			$output .= '</ul>';
//		}
//		if (count($unchangedPages) > 0) {
//			$output .= "<p><b>The following pages were not merged and so did not need to be unmerged:</b></p><ul>\n";
			foreach ($unchangedPages as $page) {
				$output .= "<li>".$skin->makeKnownLinkObj($page['title'])."</li>\n";
			}
			$output .= "</ul>";
		}
		
		return $output;
	}
	
	public function getReviewSideText() {
		global $wgLang, $wgUseRCPatrol, $wgUser;

		$sk = $wgUser->getSkin();

		$userName = htmlspecialchars(User::whoIs($this->userid));
		$userLinks = $sk->userLink( $this->userid, $userName );
		$userToolLinks = $sk->userToolLinks( $this->userid, $userName );
		$datetime = $wgLang->timeanddate($this->timestamp, true, true );
		$markPatrolled = '';
		if ( $wgUseRCPatrol && $this->rcid && $wgUser->isAllowed( 'patrol' )) {
			$t = Title::makeTitle(NS_SPECIAL, 'ReviewMerge/'.$this->mergeId);
			$markPatrolled = '<p>[' . $sk->makeKnownLinkObj($t, wfMsg('markaspatrolleddiff'), "action=markpatrolled&rcid={$this->rcid}" ) . ']</p>';
		}

		return <<<END
<p>Merge performed by: $userLinks $userToolLinks</p>
<p>$datetime</p>
$markPatrolled
END;
	}
	
  	// Include relative data for Person, but not for Family
  	// titleString is empty if page has been deleted
   private function getPreMergePageData($revid, $ns, $titleString, $includeRelatives, &$data, $includeParentFamily = false, $includeSpouseFamily = false) {
   	$dummy = null;
   	if ($ns == NS_PERSON) {
   		CompareForm::initPersonData('', $data);
   		if ($titleString) {
   			CompareForm::readPersonData('', $titleString, $data, $dummy, $includeRelatives, $includeParentFamily, $includeSpouseFamily, true, $revid, $this->timestamp);
   		}
   	}
   	else {
   		CompareForm::initFamilyData($data);
   		if ($titleString) {
   			CompareForm::readFamilyData($titleString, $data, $dummy, $includeRelatives, true, $revid, $this->timestamp);
   		}
   	}
	}
	
	private function getPostMergePageData($revid, $ns, $titleString, $includeRelatives, &$data, $includeParentFamily = false, $includeSpouseFamily = false) {
      $dbr =& wfGetDB(DB_SLAVE);
		$rows = $dbr->query('SELECT r2.rev_id, r2.rev_comment FROM revision AS r1, revision AS r2'.
									' WHERE r1.rev_id = '.$revid.' AND r1.rev_page = r2.rev_page AND r2.rev_id > '.$revid.
									' ORDER BY r2.rev_id LIMIT 1');
		$nextRevid = 0;
		while ($row = $dbr->fetchObject($rows)) {
			if (strpos($row->rev_comment, "[[Special:ReviewMerge/{$this->mergeId}|") !== false) {
				$nextRevid = $row->rev_id;
			}
		}
		$dbr->freeResult($rows);
							
		if ($nextRevid) {
			$this->getPreMergePageData($nextRevid, $ns, $titleString, $includeRelatives, $data, $includeParentFamily, $includeSpouseFamily);
			$data['mergeresult'] = true;
		}
	}
	
	private function getCompareDataForRole($role, $includeRelatives, &$data, &$roles, &$mergeRows, $includeParentFamily = false, $includeSpouseFamily = false) {
      foreach ($this->merges as $merge) {
      	$fields = explode('|',$merge,2);
      	if ($fields[0] == $role) {
      		$ns = ($role == 'Family' ? NS_FAMILY : NS_PERSON);
      		// add a new row
      		$baseRow = $mergeRows;
      		$data[$mergeRows] = array();
      		$roles[$mergeRows] = $role;
      		$mergeRows++;
      		// for each page involved in this row
	      	$revidSets = explode('#', $fields[1]);
	      	$mergeTarget = '';
      		for ($p = 0; $p < count($revidSets); $p++) {
      			if ($revidSets[$p]) {
	     				$data[$baseRow][$p+1] = array();
      				$revids = explode('/', $revidSets[$p]);
      				$used = false;
      				for ($i = 0; $i < count($revids); $i++) {
      					$revid = $revids[$i];
      					if ($revid && $used) {
      						$row = $mergeRows;
      						$data[$mergeRows] = array();
      						$data[$mergeRows][$p+1] = array();
      						$roles[$mergeRows] = $role;
      						$mergeRows++;
      					}
      					else {
      						$row = $baseRow;
      						$used = true;
      					}
     						$titleLabel = ($role == 'Family' ? 'familyTitle' : 'Title');
      					if ($revid) {
						   	$revision = Revision::newFromId($revid);
		   					$titleString = ($revision ? $revision->getTitle()->getText() : '');
		      				$this->getPreMergePageData($revid, $ns, $titleString, $includeRelatives, $data[$row][$p+1], $includeParentFamily, $includeSpouseFamily);
		      				if (!$titleString) {
									$data[$row][$p+1]['deleted'] = true;
									$titleString = 'page has been deleted';
		      				}
		      				else if ($p == 0 && $i == 0) {
						      	$data[$baseRow][0] = array();
		      					$this->getPostMergePageData($revid, $ns, $titleString, $includeRelatives, $data[$baseRow][0], $includeParentFamily, $includeSpouseFamily);
		      					$data[$baseRow][0]['mergeTarget'] = '';
		      				}
      					}
	     					$data[$row][$p+1]['mergeTarget'] = $mergeTarget;
	      				if (!$mergeTarget) {
	      					$mergeTarget = $titleString;
	      				}
      				}
      			}
      		}
      	}
      }
	}
   
   private function getCompareData(&$data, &$roles) {
   	// data[i] == row for each set of pages to merge
   	// data[i][j] = column for each family/person j in this row
   	// data[i][j][label][] = array of data for label - from CompareForm
      $mergeRows = 0;
		$this->getCompareDataForRole('Person', true, $data, $roles, $mergeRows);
		$this->getCompareDataForRole('Family', false, $data, $roles, $mergeRows);
		$this->getCompareDataForRole('husband', false, $data, $roles, $mergeRows, true, false);
		$this->getCompareDataForRole('wife', false, $data, $roles, $mergeRows, true, false);
		$this->getCompareDataForRole('child', false, $data, $roles, $mergeRows, false, true);
   }

   public function getUnmergeInfo() {
      global $wgLang, $wgUser;

      $sk = $wgUser->getSkin();

      if ($this->unmergeTimestamp) {
         $unmergeUserName = htmlspecialchars(User::whoIs($this->unmergeUserid));
         $unmergeUserLinks = $sk->userLink( $this->unmergeUserid, $unmergeUserName );
         $unmergeUserToolLinks = $sk->userToolLinks( $this->unmergeUserid, $unmergeUserName );
         $UnmergeDatetime = $wgLang->timeanddate($this->unmergeTimestamp, true, true );
         $unmerge = "<p><font color=\"red\">Merge has been undone</font> by: $unmergeUserLinks $unmergeUserToolLinks</p><p>$UnmergeDatetime</p>";
      }
      else {
          $leftrightshowsmerge = wfMsg('leftrightshowsmerge');
         $unmerge = <<<END
$leftrightshowsmerge
END;
      }
      return $unmerge;
   }
   
	/**
	 * Return HTML for displaying search results
	 * @return string HTML
	 */
	public function getReviewResults() {
		$data = array();
		$roles = array();
		$this->getCompareData($data, $roles);
		$cols = count($data[0]);
		$tblCols = $cols+1;
		$childNum = 0;
		$output = <<< END
<form name="unmerge" action="/wiki/Special:ReviewMerge/{$this->mergeId}" method="get">
<input type="hidden" name="action" value="unmerge"/>
<table border="0" cellspacing="0" cellpadding="4">
END;
   	for ($i = 0; $i < count($data); $i++) {
			$output .= CompareForm::insertEmptyRow($tblCols);
			if ($roles[0] == 'Person') {
				$labels = self::$PERSON_COMPARE_LABELS;
			}
			else if ($i == 0) {
				$labels = self::$FAMILY_COMPARE_LABELS;
			}
			else {
				$labels = self::$FAMILY_MEMBER_COMPARE_LABELS;
			}
   		foreach ($labels as $label) {
				$found = !in_array($label, self::$OPTIONAL_LABELS);
				if (!$found) {
					for ($j = 0; $j < $cols; $j++) {
						if (is_array(@$data[$i][$j][$label]) && count($data[$i][$j][$label]) > 0) {
							$found = true;
							break;
						}
					}
				}
				if ($found) {
	   			$baseStdValues =& CompareForm::standardizeValues($label, @$data[$i][1][$label]);
					if ($label == 'Title') {
						$role = $roles[$i];
						if ($role == 'child') {
							$roleLabel = $role.$label;
							$childNum++;
						}
						else if ($role == 'Person' || $role == 'Family') {
							$roleLabel = $label;
						}
						else {
							$roleLabel = $role.$label;
						}
					}
					else {
						$roleLabel = $label;
					}
					if ($label == 'familyTitle') {
						$revidLabel = 'Revid';
					}
					else if (strpos($label, 'Title') !== false) {
						$revidLabel = str_replace('Title', 'Revid', $label);
					}
					else {
						$revidLabel = '';
					}
					$labelClass = CompareForm::getLabelClass($roleLabel);
					$output .= "<tr><td class=\"$labelClass\">" . CompareForm::formatLabel($roleLabel, $childNum) ."</td>";
		   		for ($j = $cols-1; $j >= 0; $j--) {
		   			if ($j == 1) {
		   				$stdValues = $baseStdValues;
		   			}
		   			else {
		   				$stdValues =& CompareForm::standardizeValues($label, @$data[$i][$j][$label]);
		   			}
						list($score, $class) = CompareForm::getCompareScoreClass($j == 0 || $j == 1, $label, $baseStdValues, $stdValues);
						$output .= "<td class=\"$class\">";
						$valueFound = false;
						if (is_array(@$data[$i][$j][$label])) {
							for ($v = 0; $v < count($data[$i][$j][$label]); $v++) {
								$value = $data[$i][$j][$label][$v];
								if (strpos($label, 'Title') !== false) {
									if (($label == 'familyTitle' || $label == 'Title') && @$data[$i][$j]['mergeresult']) {
										$formattedValue = 'Merge<br>result';
									}
									else if (GedcomUtil::getGedcomMergeLogKey(@$data[$i][$j]['Revid'][$v])) {
										$formattedValue = '<b>GEDCOM page</b>';
									}
									else {
										$formattedValue = CompareForm::formatValue($label, $value, 
																					(StructuredData::endsWith($label, 'FamilyTitle') ? '' : 'oldid=' . $data[$i][$j][$revidLabel][$v])); 
									}
								}
								else {
									$formattedValue = CompareForm::formatValue($label, $value);
								}
								if ($v) $output .= '<hr>';
								$output .= $formattedValue;
								$valueFound = true;
							}
						}
						if (!$valueFound) $output .= '&nbsp;';
						if ($class == CompareForm::$COMPARE_PAGE_CLASS) {
							$titlesCnt = count(@$data[$i][$j][$label]);
							if ($titlesCnt > 0) {
								if (!$data[$i][$j]['Exists']) { // person/family not found
									$output .= '<br>'.wfMsg('notfound');
								}
								else if ($data[$i][$j]['Redirect']) { // person/family has been merged
									$output .= '<br>'.wfMsg('alreadymerged');
								}
								else if ($data[$i][$j]['mergeTarget']) {
									$output .= '<br>'. wfMsg('mergetargettext', ($data[$i][$j][$label][0] == $data[$i][$j]['mergeTarget'] ? 'Same as ' : 'Merged with '), htmlspecialchars($data[$i][$j]['mergeTarget']) ) ;
								}
								else if ($j == 1 && @$data[$i][0]['mergeresult']) {
									$output .= '<br>(merge target)';
								}
							}
							else if (@$data[$i][$j]['deleted']) {
								$output .= wfMsg('pagebeendeleted');
							}
						}
						$output .= '</td>';
					}
					$output .= '</tr>';
				}
   		}
   	}
   	if ($this->unmergeTimestamp) {
   		$unmergeButton = wfMsg('mergebeenundone');
   	}
   	else {
        $unmergebuttontext = wfMsg('unmerge');
        $commentbuttontext = wfMsg('comment');
   		$unmergeButton = 'Unmerge reason: <input type="text" name="'.$commentbuttontext.'" size=36/><br><input type="submit" value="'.$unmergebuttontext.'"/>';
   	}
		$output .= <<< END
<tr><td align=right colspan="$tblCols">$unmergeButton</td></tr>
</table>
</form>
END;
		
		return $output;
	}
}
?>
