<?php

// Created Apr 2023 (extracted from SpecialDataQuality.php) by Janet Bjorndahl
abstract class DQHandler {
     
   public static $DATA_QUALITY_TEMPLATES = array(
     'Wife younger than' => 'UnusuallyYoungWife',
     'Husband younger than' => 'UnusuallyYoungHusband',
     'Wife older than' => 'UnusuallyOldWife',
     'Husband older than' => 'UnusuallyOldHusband',
     "Born before parents' marriage" => 'BirthBeforeParentsMarriage',
     'Born over' => 'BirthLongAfterParentsMarriage',
     'Born before mother was' => 'UnusuallyYoungMother',
     'Born before father was' => 'UnusuallyYoungFather',
     'Born after mother was' => 'UnusuallyOldMother',
     'Born after father was' => 'UnusuallyOldFather',
     'Christened/baptized after mother died' => 'BaptismAfterMothersDeath',
     'Christened/baptized more than 1 year after father died' => 'BaptismWellAfterFathersDeath'
   );

   // Message to display when in edit mode instead of the issue description that displays in other contexts. Only needed for a few issue types.  
   private static $DATA_QUALITY_EDIT_MESSAGE = array(
      "Invalid date(s)" => "Invalid date(s). Dates should be in \"<i>D MMM YYYY</i>\" format (ie 5 Jan 1900) with optional modifiers (eg, bef, aft).",
      "Considered living" => "This person was born/christened less than 110 years ago and does not have a death/burial date.  Living people cannot be entered into WeRelate.org.",
      "Potentially living" => "This person may have been born/christened less than 110 years ago and does not have a death/burial date.  Living people cannot be entered into WeRelate.org."
   );

  /**
   * Determine whether an issue (found by a batch job) is still outstanding (not fixed)
   */
  function determineIssueStatus($title, $tagName, $category, $issueDesc) {

    // See if the issue exists on the page itself
 	  $structuredContent = self::getStructuredContent(self::getpageContent($title), $tagName);
    $issues = self::getIssues($tagName, $structuredContent, $title->getText(), "none");
    if ( $issues["status"][0] == "success" ) { 
      $outstanding = self::issueExists($issues, $category, $issueDesc);
    }
    else {
      $outstanding = true;                    // If issue retrieval failed, assume the issue is still outstanding
    }
    
    // If the issue is not found on the page itself and this is a Person page, check issues in relation to the parents' page.
    // Note that there may be multiple sets of parents - if so, check all because the issue could have been created for any of them.
    if ( $tagName = "person" ) {
      $remainingContent = $structuredContent;
      while ( !$outstanding && strpos($remainingContent, '<child_of_family') !== false ) {
        $parentInfo = self::getParentInfo($remainingContent);
        if ( $parentInfo['content'] != "" ) {
          // Get issues specific to this child. This is done for performance reasons and also because getting issues for all children in the family
          // requires selecting only appropriate issues on the return. This is problematic because special characters (from other languages)
          // are not always returned coded the same way they are sent.    
          $issues = self::getIssues("family", $parentInfo['content'], $parentInfo['titlestring'], $title->getText());          
          if ( $issues["status"][0] == "success" ) { 
            $outstanding = self::issueExists($issues, $category, $issueDesc);
          }
          else {
            $outstanding = true;               // If issue retrieval failed, assume the issue is still outstanding
          }
        }
        $remainingContent = substr($remainingContent, strpos($remainingContent, '<child_of_family')+16);
      }
    }
    return $outstanding;
  }
  
  /**
   * Get the list of Data Quality issues (not verified) for a person or family, including issues in relation to other family members
   */
  function getUnverifiedIssues($pageContent, $title, $tagName, $scope="none") {
    $issues = array();
    
    // Get issues on the page itself
 	  $structuredContent = self::getStructuredContent($pageContent, $tagName);
    $namedIssues = self::getIssues($tagName, $structuredContent, $title->getText(), $scope);
    
    // Once retrieved, copy to a new table using a numeric index rather than named keys. Easier to manage in the remaining code (especially when removing verified issues).
    // While copying, drop the last entry, which is the status of retrieval.
    for ( $i=0; $i<sizeof($namedIssues)-1; $i++ ) {
      $issues[$i] = $namedIssues['issue ' . ($i+1)];
    }
//error_log($title->getText() . ": num issues1=" . sizeof($issues));    
    
    // If this is a Person page, get issues in relation to the parents' Family page(s).
    if ( $tagName == "person" ) {
      $remainingContent = $structuredContent;
      while ( strpos($remainingContent, '<child_of_family') !== false ) {
        $parentInfo = self::getParentInfo($remainingContent);
        if ( $parentInfo['content'] != "" ) {
          // Get issues specific to this child. This is done due to inconsistent handling of special characters between PHP and Java (see note in determineIssueStatus). 
          $namedIssues = self::getIssues("family", $parentInfo['content'], $parentInfo['titlestring'], $title->getText());          
          
          // Append new issues to existing issues, dropping the last entry (status of retrieval)
          $numIssues = sizeof($issues);
          for ($i=0; $i<sizeof($namedIssues)-1; $i++) {
            $issues[$numIssues+$i] = $namedIssues['issue ' . ($i+1)];
          }
        }
        $remainingContent = substr($remainingContent, strpos($remainingContent, '<child_of_family')+16);
      }
    }
//error_log($title->getText() . ": num issues2=" . sizeof($issues));    
    
    // Remove verified anomalies
    // Note that for a Family page, this will only remove the anomalies whose templates are on the Family or Family Talk page.
    self::removeVerifiedIssues($title, $issues, $tagName);
    
    // If this is a Family page, remove verified anomalies for each of the children on the page, based on templates on their Person and/or Person Talk pages.
    // Work through the array from the end to the beginning so that no entries are skipped when entries are removed. 
    // The routine is called once for each child, and the index is adjusted after each call, in case more than one entry was removed from the array.
    // Note that due to the difference between Java and PHP in handling certain special characters, this will not work for pages whose
    // title includes those special characters. Therefore, their anomalies will be displayed even if they have been verified.
    if ( $tagName == "family" ) {
      $childTitleString = "";
      for ( $i=sizeof($issues)-1; $i>=0; $i-- ) {
        if ( $issues[$i][3] != $childTitleString ) {
          $childTitleString = $issues[$i][3];
          self::removeVerifiedIssues(Title::newFromText($childTitleString, NS_PERSON), $issues, "person");
          if ( $i > sizeof($issues)-1 ) {  
            $i = sizeof($issues);          // note: the "for" loop will immediately reduce $1 by 1
          }
        }
      }
    }
//error_log($title->getText() . ": num issues3=" . sizeof($issues));  
//error_log  ($title->getText() . ": issue1 =" . $issues[0][1] . "; issue2 =" . $issues[1][1]);
    return $issues;
  }

  // Remove verified anomalies from the array of Data Quality issues, checking for templates on both the Person or Family page and the corresponding Talk page
  function removeVerifiedIssues($title, &$issues, $tagName) {
    if ( sizeof($issues) > 0 ) {
      
      // Firstly, based on templates on this Person or Family page (as stored in the database)
      self::removeVerIssues($title, $issues, $tagName);
      
      // Secondly, based on templates on the corresponding Talk page. Don't bother if all issues were already removed.
      if (sizeof($issues) > 0 ) {
        $talk = $title->getTalkPage();      
        self::removeVerIssues($talk, $issues, $tagName);
      }
    }
  }  

  // Remove verified anomalies from the array of Data Quality issues, based on templates on the indicated page
  function removeVerIssues($title, &$issues, $tagName) {
  
    // Get the text of this page. If none, no templates to find, so return.
 	  $revision = StructuredData::getRevision($title, true);
 	  if ( $revision ) {
      $text =& $revision->getText();
      if ( !$text || ($text == "") ) {
        return;
      }
    }
    else {
      return;
    }
    // For each anomaly (that matches the title for which anomalies are being removed), look for the corresponding template in the text of this page. 
    // If it exists, remove the issue from the issues array.
    // Check the array from the end to the beginning so that no entries are skipped when entries are removed.
    for ( $i=sizeof($issues)-1; $i>=0; $i-- ) {
      if ( $issues[$i][0] == 'Anomaly' && strtolower($issues[$i][2]) == $tagName && $issues[$i][3] == $title->getText()) {
        foreach ( array_keys(DQHandler::$DATA_QUALITY_TEMPLATES) as $partialIssueDesc ) {
          if ( substr($issues[$i][1], 0, strlen($partialIssueDesc)) == $partialIssueDesc ) {
            $template = self::$DATA_QUALITY_TEMPLATES[$partialIssueDesc];
            $pattern = "/{{" . $template . ".*}}/";
            if ( preg_match($pattern, $text)==1 ) {
              array_splice($issues,$i,1);
            }
            break;  
          }
        }
      }
    }
  }  
  
  /**
   * Create the WikiText to display issues on a Person or Family page as "Questionable Information"
   */
  function addQuestionableInfoIssues($issues, $tagname) {
  
    // Remove issues other than errors and anomalies (e.g., missing data), so they don't cause the "questionable information" heading to display.
    // Check the array from the end to the beginning so that no entries are skipped when entries are removed.
    for ( $i=sizeof($issues)-1; $i>=0; $i-- ) {
      if ( $issues[$i][0] != 'Error' && $issues[$i][0] != 'Anomaly' ) {
        array_splice($issues,$i,1);
      }
    }
    
    $result = "";
    
    // Display errors first, then anomalies (as warnings)
    $classes = "wr-infobox wr-infobox-dataquality" . ($tagname=="person" ? " wr-infobox-dataquality-person" : "");
    if ( sizeof($issues) > 0 ) {
      $result = "\n<div class=\"$classes\"><span class=large-attn>Questionable information</span> identified by WeRelate automation\n<table>";
      for ( $i=0; $i<sizeof($issues); $i++ ) {
        if ( $issues[$i][0] == "Error" ) {
          $result .= "<tr><td>To fix:</td><td>" . 
              (($tagname == "family" && $issues[$i][2] == "Person") ? "[[Person:" . $issues[$i][3] . "|" . $issues[$i][3] . "]]" : "") . "</td><td>" .
              $issues[$i][1] . "</td></tr>";
        }
      }
      for ( $i=0; $i<sizeof($issues); $i++ ) {
        if ( $issues[$i][0] == "Anomaly" ) {
          $result .= "<tr><td>To check:</td><td>" . 
              (($tagname == "family" && $issues[$i][2] == "Person") ? "[[Person:" . $issues[$i][3] . "|" . $issues[$i][3] . "]]" : "") . "</td><td>" .
              $issues[$i][1] . "</td></tr>";
        }
      }
      $result .= "</table></div>";
    }
  return $result; 
  }
  
  /**
   * Create the WikiText to display warning and error messages when editing a Person or Family page
   */
  function addEditIssues($issues) {
    $result = "";
    
    for ( $i=0; $i<sizeof($issues); $i++ ) {
      // By default, use the issue description in the warning or error message. 
      $msg = $issues[$i][1];
      
      // But use a different message as appropriate.
      foreach ( array_keys(self::$DATA_QUALITY_EDIT_MESSAGE) as $partialIssueDesc ) {
        if ( substr($issues[$i][1], 0, strlen($partialIssueDesc)) == $partialIssueDesc ) {
          $msg = self::$DATA_QUALITY_EDIT_MESSAGE[$partialIssueDesc];
          break;  
        }
      }
      if ( $issues[$i][4] == "yes" ) {
        $result .= "<p><font color=red>" . ($issues[$i][0] == "Living" ? "" : "Please fix this issue: ") . $msg . "</font></p>";
      }
      else {
  	    $result .= "<p><font color=red>Warning: $msg</font></p>";
      }
    }
  return $result; 
  }
  
  /**
   * Determine whether the page has issues that must be fixed before saving
   */
  function hasSevereIssues($issues) {
    for ( $i=0; $i<sizeof($issues); $i++ ) {
      if ( $issues[$i][4] == "yes" ) {
    	  return true;
      } 
    }
  return false; 
  }
   
  /** 
   * Get Data Quality issues from the structured content of a Person or Family page 
   */
  function getIssues($tagName, $structuredContent, $titleString, $childTitleString) {
    global $wrSearchHost, $wrSearchPort, $wrSearchPath;
    
    $issues['status'][0] = 'failed';    // return a status in case Java (search) server is unavailable; set default to failed
    if ( $structuredContent !== "" ) {
      // Call Java code to find issues for this page
      if ( $tagName == 'person' ) {
        $query = "http://$wrSearchHost:$wrSearchPort$wrSearchPath/dqfind?data=" . urlencode($structuredContent) . 
                 '&ns=Person&ptitle=' . urlencode($titleString) . '&wt=php';
      }
      else {
        $query = "http://$wrSearchHost:$wrSearchPort$wrSearchPath/dqfind?data=" . urlencode($structuredContent) . 
                 '&ns=Family&ftitle=' . urlencode($titleString) . '&ctitle=' . urlencode($childTitleString) . '&wt=php';
      }
      if ( file_get_contents($query) ) {
        eval('$issues = ' . file_get_contents($query) . ';');
        array_splice($issues, 0, 1);          // remove first element, which is the response header (status and time)
        $issues['status'][0] = 'success';     // add an element to indicate that issues (if any) were successfully retrieved
      }
    }
    return $issues;
  }

  /**
   * Determine if a specific issue is in a list of issues
   */
  function issueExists( $issues, $category, $issueDesc ) {
    if ( sizeof($issues) > 1 ) {        // Note that the last entry is the status of whether or not issues could be retrieved
      for ( $i=0; $i<sizeof($issues)-1; $i++ ) {
        if ( $issues["issue " . ($i+1)][0] == $category && $issues["issue " . ($i+1)][1] == $issueDesc ) {
          return true;
          break;
        }
      }
    }
    return false;
  }

  /** 
   * Get the current content of a page, based on the page title
   */
  function getPageContent( $title ) {
   	$revision = StructuredData::getRevision($title, true);
   	if ( $revision ) {
      return $revision->getText();
    }
    else {
      return "";
    }
  }
  
  /**
   * Extract the minimal structured content from page contents to pass to Java code on the Java server
   */
  function getStructuredContent( $content, $tagName ) {
   	$start = strpos($content, "<$tagName>");
    // We expect only one tag instance; ignore any extras
    if ( $start !== false ) {
      $end = strpos($content, "</$tagName>", $start);
      if ( $end !== false ) {
        // Strip out extraneous tags and attributes to keep the XML from exceeding the call string size limit (about 6400 bytes)
	      return self::stripExtraneous(trim(substr($content, $start, $end + 3 + strlen($tagName) - $start)));
      }
    }
    return "";
  }
  
  // Strip out source citations, notes, place names and event descriptions to keep the XML relatively small
  function stripExtraneous( $structuredContent ) {
    $srcTag = 'source_citation';
    $noteTag = 'note';
    $placeAtt = 'place';
    $descAtt = 'desc';
    
    $strippedContent = $structuredContent;
    
    // Strip out source citations. They can end with either "/>" or "</source_citation>".
    while ( strpos($strippedContent, "<$srcTag") !== false ) {
      $start = strpos($strippedContent, "<$srcTag");
      // Find end of this source citation - the first occurrence of either possibility
      $end1 = strpos($strippedContent, "/>", $start);
      $end2 = strpos($strippedContent, "</$srcTag>", $start);
      if ( $end1 !== false ) {
        if ( $end2 !== false && $end2 < $end1 ) {
          $end = $end2;
          $endSkip = strlen("</$srcTag>");
        }
        else {
          $end = $end1;
          $endSkip = strlen("/>");
        }
      }
      else {
        if ( $end2 !== false ) {
          $end = $end2;
          $endSkip = strlen("</$srcTag>");
        }
        else {
          break;  // ill-formed XML - the source citation doesn't have a proper end
        }
      }
      $strippedContent = substr($strippedContent, 0, $start) . substr($strippedContent, $end + $endSkip);
    }
           
    // Strip out notes. Because they always have text, they have to end with "</note>"
    while ( strpos($strippedContent, "<$noteTag") !== false ) {
      $start = strpos($strippedContent, "<$noteTag");
      $end = strpos($strippedContent, "</$noteTag>", $start);
      $endSkip = strlen("</note>");
      if ( $end !== false ) {
        $strippedContent = substr($strippedContent, 0, $start) . substr($strippedContent, $end + $endSkip);
      }
      else {
        break;  // ill-formed XML - the note doesn't have a proper end
      }
    }
    
    // By this point, sources and notes are gone, so the only places left should be in event tags and in spouse and child tags of family pages.

    //Strip out place names. Note that the place attribute is prefixed with "birth", "chr", "death" or "burial" within spouse and child tags of family pages. For events, there is no prefix. 
    while ( strpos($strippedContent, "$placeAtt=\"") !== false ) {
      $start = strpos($strippedContent, "$placeAtt=\"");
      $end = strpos($strippedContent, '"', $start+strlen($placeAtt)+2);
      if ( $end === false) {
        break;  // ill-formed XML - the place attribute doesn't have an ending quotation mark
      }
      if ( substr($strippedContent, $start-5, 5) == "birth" || substr($strippedContent, $start-5, 5) == "death" ) {
        $start -= 5;
      } 
      if ( substr($strippedContent, $start-3, 3) == "chr" ) {
        $start -= 3;
      } 
      if ( substr($strippedContent, $start-6, 6) == "burial" ) {
        $start -= 6;
      } 
      $strippedContent = substr($strippedContent, 0, $start) . substr($strippedContent, $end+1);
    }        

    //Strip out descriptions except for those including templates (which might be needed).
    $start = 0;
    // Look for desc attribute tag in the portion of strippedContent that is beyond what has already been examined (which might include a kept desc attribute).
    while ( strpos(substr($strippedContent, $start), "$descAtt=\"") !== false ) {
      // Calculate start relative to the full strippedContent.
      $start = $start + strpos(substr($strippedContent, $start), "$descAtt=\"");
      $end = strpos($strippedContent, '"', $start+strlen($descAtt)+2);
      // If the desc attribute doesn't include a template, remove it from strippedContent; otherwise, skip over the desc attribute tag for the next loop.
      if ( strpos(substr($strippedContent, $start, $end-$start), "{{") === false ) {
        $strippedContent = substr($strippedContent, 0, $start) . substr($strippedContent, $end+1);
      }
      else {
        $start += strlen($descAtt)+2;
      }
    }        
    
    return $strippedContent;
  }
  
  /**
   * Get the structured content of the Family page of the parents identified on a Person page
   */
  function getParentInfo( $content ) {
    $parentInfo = array();
    $parentInfo['content'] = "";
    $parentInfo['titlestring'] = "";
    
    $start = strpos($content, '<child_of_family');
    if ( $start !== false ) {
      $start = strpos($content, 'title="', $start);
      if ( $start !== false ) {
        $start = $start + 7;
        $end = strpos($content, '"', $start);
        if ( $end !== false ) {
          $parentInfo['titlestring'] = StructuredData::unescapeXml(trim(substr($content, $start, $end - $start)));  // unescape the parent title in order to get content 
          $title = Title::makeTitle(110, $parentInfo['titlestring']);
          $parentInfo['content'] = self::getStructuredContent(self::getPageContent($title), 'family');  // do NOT unescape the parent content since doing so can break the XML
        }
      }  
    }
    return $parentInfo;
  }
}

?>
