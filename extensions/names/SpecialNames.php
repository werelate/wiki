<?php
/**
 * @package MediaWiki
 */

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialNamesSetup";

function wfSpecialNamesSetup() {
	global $wgMessageCache, $wgSpecialPages;
	
	$wgMessageCache->addMessages( array( "names" => "Names" ) );
	$wgSpecialPages['Names'] = array('SpecialPage','Names');
}

/**
 * Called to display the Special:Names page
 *
 * @param unknown_type $par
 * @param unknown_type $specialPage
 */
function wfSpecialNames() {
	global $wgOut, $wgScriptPath;
	
	$namesForm = new NamesForm();

	// read query parameters into variables
	$namesForm->readQueryParms();

	$wgOut->setPageTitle('Variant names');
	$wgOut->addScript("<link href=\"$wgScriptPath/skins/common/jquery.customInput.css\" rel=\"stylesheet\" type=\"text/css\"/>");
	$wgOut->addScript("<script src=\"$wgScriptPath/jquery.customInput.js\"></script>");
	$type = $namesForm->type;

	$wgOut->addScript(<<<END
<script type="text/javascript">
$(function(){
	$('#name_submit').click(function() {
		var name = $('#name_input').val().toLowerCase().replace(/[^a-z]/g, '');
		var input = $('<input type="checkbox" class="confirm" id="cnf3_'+name+'" name="3_'+name+'" value="cnf" checked="checked"/><label for="cnf3_'+name+'">&nbsp;</label><a href="/wiki/Special:Names?name='+name+'&type=$type">'+name+'</a><br/>');
		$('#confirmed_names').prepend(input);
		input.customInput();
		$('#name_input').val('');
		return false;
	});
});
</script>
END
);

   // get name and type
   $name = $namesForm->getName();
   $type = $namesForm->getType();

   if ($namesForm->isUpdate() && $name && $type) {
      $message = $namesForm->issueUpdate();
      if (!$message) {
         $soundex = $namesForm->getSoundex();
         $wgOut->redirect('/wiki/Special:Names?name=' . urlencode($name) . '&type=' . urlencode($type) . '&updated=true' .
                          ($soundex ? ('&soundex=' . urlencode($soundex)) : ''));
         return;
      }
   }
   else {
      $message = $namesForm->getMessage();
   }

   if ($name) {
      $results = $namesForm->issueQuery();
   }
   else {
      $results = '';
   }
   $formHtml = $namesForm->getFormHtml();
   $wgOut->addHTML(<<< END
$message
<p>$formHtml</p>
$results
END
   );
}

/**
 * View and update similar names
*/
class NamesForm {
   private $name;
   public $type;
   private $soundex;
   private $isUpdate;
   private $updated;

   public static $TYPE_OPTIONS = array(
      'Given name' => 'g',
      'Surname' => 's'
   );

   public function readQueryParms() {
      global $wgRequest;

		$this->name = strtolower($wgRequest->getVal('name'));
		$this->type = strtolower($wgRequest->getVal('type'));
      $this->soundex = $wgRequest->getVal('soundex');
      $this->updated = $wgRequest->getVal('updated');
      $this->isUpdate = $wgRequest->wasPosted() && $wgRequest->getVal('update');
   }

   public function getName() {
      return $this->name;
   }

   public function getType() {
      return $this->type;
   }

   public function isUpdate() {
      return $this->isUpdate;
   }

   public function getSoundex() {
      return $this->soundex;
   }

   public function getMessage() {
      $message = '';
      if ($this->updated) {
         $message .= wfMsgWikiHtml('namesupdated', htmlspecialchars($this->name));
      }
      if ($this->soundex) {
         $message .= wfMsgWikiHtml('namessoundex', htmlspecialchars($this->soundex));
      }
      return $message;
   }

   public function getFormHtml() {
      $name = htmlspecialchars($this->name);
      $nameTypeSelect = StructuredData::addSelectToHtml(0, "type", self::$TYPE_OPTIONS, $this->type, '', false);
      $namesend = preg_replace('#</?p>#', '', wfMsgWikiHtml('namesend'));
      $go = wfMsg('go');
      $result = <<< END
<form id="names_form" action="/wiki/Special:Names" method="get">
<table id="namesform" class="namesform"><tr>
<td align=right>$nameTypeSelect</td>
<td><input id="input_name" class="input_name" type="text" name="name" maxlength=100 value="$name" onfocus="select()"/></td>
<td><input type="submit" value="$go"/></td>
<td style="padding-left:15px">$namesend</td>
</tr></table></form>
END;
      return $result;
   }

   private function getQueryResponse() {
      global $wrSearchHost, $wrSearchPort, $wrSearchPath;

      $query = "http://$wrSearchHost:$wrSearchPort$wrSearchPath/nameread?name=" . urlencode(trim($this->name)) .
                  '&type=' . ($this->type == 'g' ? 'givenname' : 'surname') . '&wt=php';
      return file_get_contents($query);
   }

   private function getHttpErrorMessage() {
      list($version, $status_code, $msg) = explode(' ', $http_response_header[0], 3);
      if ($status_code != '400') {
         $msg = 'There was an error processing your search, or the server is down; please try a different search or try again later.';
      }
      $pos = strpos($msg, ':');
      if ($pos > 0) {
         $msg = substr($msg, $pos+1);
      }
      $msg = htmlspecialchars($msg);
      return "<p><font color=\"red\">$msg</font></p>";
   }

   private function makeLink($skin, $name) {
      return $skin->makeKnownLinkObj( Title::makeTitle( NS_SPECIAL, 'Names' ), htmlspecialchars($name),
                                          'name='.urlencode($name).'&type='.urlencode($this->type));
   }

   public function issueQuery() {
      global $wgUser;

      // issue query and get response
      $responseString = $this->getQueryResponse();
      if (!$responseString) {
         return $this->getHttpErrorMessage();
      }

      // extract results
      eval('$response = ' . $responseString .  ';');
      $namePiece = $response['name'];
      if ($namePiece != $this->name) {
         $normalizeMessage = "<p>We've &quot;normalized&quot; your name; for example, -dtr endings are normalized to -son</p>";
         $this->name = $namePiece;
      }
      else {
         $normalizeMessage = '';
      }
      $candidateVariants = $response['candidateVariants'];
      $computerVariants = $response['computerVariants'];
      $confirmedVariants = $response['confirmedVariants'];
      $soundexExamples = @$response['soundexExamples'];
      $basename = @$response['basename'];
      $prefixedNames = @$response['prefixedNames'];

		$skin = $wgUser->getSkin();

		$nonVariantInputs = array();
		foreach ($candidateVariants as $candidateVariant) {
         $link = $this->makeLink($skin, $candidateVariant);
         $candidateVariant = htmlspecialchars($candidateVariant);
         $nonVariantInputs[] = <<<END
<input type="checkbox" class="confirm" id="cnf1_$candidateVariant" name="1_$candidateVariant" value="cnf"/><label for="cnf1_$candidateVariant">&nbsp;</label>$link
END;
		}
		$nonVariantInputs = join('<br/>', $nonVariantInputs);

		$computerVariantInputs = array();
		foreach ($computerVariants as $computerVariant) {
			$link = $this->makeLink($skin, $computerVariant);
			$computerVariant = htmlspecialchars($computerVariant);
			$computerVariantInputs[] = <<<END
<input type="checkbox" class="delete" id="del2_$computerVariant" name="2_$computerVariant" value="del"/><label for="del2_$computerVariant">&nbsp;</label><input type="checkbox" class="confirm" id="cnf2_$computerVariant" name="2_$computerVariant" value="cnf"/><label for="cnf2_$computerVariant">&nbsp;</label>$link
END;
		}
		$computerVariantInputs = join('<br/>', $computerVariantInputs);

		$confirmedVariantInputs = array();
		foreach ($confirmedVariants as $confirmedVariant) {
         $link = $this->makeLink($skin, $confirmedVariant);
         $confirmedVariant = htmlspecialchars($confirmedVariant);
         if ($wgUser->isAllowed('patrol')) {
            $confirmedVariantInputs[] = <<<END
<input type="checkbox" class="delete" id="del3_$confirmedVariant" name="3_$confirmedVariant" value="del"/><label for="del3_$confirmedVariant">&nbsp;</label>$link
END;
         }
         else {
            $confirmedVariantInputs[] = <<<END
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$link
END;
         }
		}
		$confirmedVariantInputs = join('<br/>', $confirmedVariantInputs);

      // construct basename
      if ($basename) {
         $link = $this->makeLink($skin, $basename);
          $unprefixedname = wfMsg('unprefixedname');
          $unprefixedinsearches = wfMsg('unprefixedinsearches');
          $basename = <<< END
<h3>$unprefixedname</h3>
<p><em>$unprefixedinsearches</em></p>
$link
END;
      }
      // construct prefixedNames
      $namesArray = array();
      if (is_array($prefixedNames)) {
         sort($prefixedNames);
         foreach ($prefixedNames as $prefixedName) {
            $namesArray[] = $this->makeLink($skin, $prefixedName);
         }
      }
      $prefixedNames = join("<br/>", $namesArray);
      if ($prefixedNames) {
          $prefixedNameTitle = wfMsg('prefixednames');
          $prefixedNameText = wfMsg('prefixedinsearches');
          $prefixedNames = <<< END
<h3>$prefixedNameTitle</h3>
<p><em>$prefixedNameText</em></p>
$prefixedNames
END;
      }
      // construct soundex examples
      $namesArray = array();
      if (is_array($soundexExamples)) {
         sort($soundexExamples);
         foreach ($soundexExamples as $soundexExample) {
            $namesArray[] = htmlspecialchars($soundexExample);
         }
      }
      $soundexExamples = join("<br/>", $namesArray);

      // disable update if user not logged in
      $updateDisabled = '';
      $msg = '';
      if (!$wgUser->isLoggedIn()) {
         $updateDisabled='disabled="disabled"';
         $msg = wfMsg('mustsigninupdate');
      }

		$comments = $this->generateCommentLog($this->type, $this->name, $skin);

      // populate template
      $name = htmlspecialchars($this->name);
      $type = htmlspecialchars($this->type);
      $nonVariants = wfMsg('nonvariants');
      $nonVariantsNotInSearch = wfMsg('nonvariantsnotinsearch');
      $computerRecommVariants = wfMsg('computerrecommvariants');
      $computerRecommVariantsInSearch = wfMsg('computerrecommvariantsinsearch');
      $confirmRecommVariants = wfMsg('confirmrecommvariants');
      $confirmRecommVariantsInSearch = wfMsg('confirmrecommvariantsinsearch');
      $leaveamessage = wfMsg('leaveamessage');
      $soundexvariants = wfMsg('soundexvariants');
      $soundexvariantssameinesearch = wfMsg('soundexvariantssameinsearches');
      $add = wfMsg('add');
      $save = wfMsg('save');
      $result = <<< END
$normalizeMessage
<form id="namesupdate_form" action="/wiki/Special:Names" method="post">
<input type="hidden" name="name" value="$name"/>
<input type="hidden" name="type" value="$type"/>
<input type="hidden" name="update" value="true"/>
<table id="namesupdateform" class="namesupdateform" cellspacing="15">
<tr>
<td width="25%" valign="top">
<h3>$nonVariants</h3>
<p style="font-size: 10px">$nonVariantsNotInSearch</p>
$nonVariantInputs
</td>
<td width="25%" valign="top">
<h3>$computerRecommVariants</h3>
<p style="font-size: 10px">$computerRecommVariantsInSearch</p>
$computerVariantInputs
</td>
<td width="25%" valign="top">
<h3>$confirmRecommVariants</h3>
<p style="font-size: 10px">$confirmRecommVariantsInSearch<a href="/wiki/WeRelate_talk:Variant_names_project">$leaveamessage</a>.</p>
<input type="text" id="name_input" size="10"/> <input type="submit" id="name_submit" value="$add"/>
<div id="confirmed_names">$confirmedVariantInputs</div>
</td>
<td width="25%" valign="top">
$basename
$prefixedNames
<h3>$soundexvariants</h3>
<p style="font-size: 10px">$soundexvariantssameinesearch</p>
$soundexExamples
</td>
</tr>
</table>
<p>Comment (optional):<br/><input type="text" name="comment" size="50" value=""/></p>
<p><input type="submit" name="update" value="$save" $updateDisabled/>$msg</p>
</form>
<div>
$comments
</div>
END;
      return $result;
   }

   private function getPostResponse($adds, $deletes, $comment) {
      global $wrSearchHost, $wrSearchPort, $wrSearchPath, $wgUser;

      $url = "http://$wrSearchHost:$wrSearchPort$wrSearchPath/nameupdate";
      $data = http_build_query(array('name' => $this->name, 'type' => $this->type, 'userName' => $wgUser->getName(),
                                   'comment' => $comment, 'isAdmin' => ($wgUser->isAllowed('patrol') ? 'true' : 'false'),
                                   'adds' => join(' ', $adds), 'deletes' => join(' ', $deletes)));
      $context = stream_context_create(array(
          'http' => array(
              'method' => 'POST',
              'header' => 'Content-Type: application/x-www-form-urlencoded',
              'content' => $data
          )
      ));
      return file_get_contents($url, false, $context);
   }

   public function issueUpdate() {
      global $wgUser, $wgRequest;

      if (!$wgUser->isLoggedIn()) {
         return '';
      }

      // gather checked boxes
      $values = $wgRequest->getValues();
      $adds = array();
      $deletes = array();
      foreach ($values as $key => $value) {
         if (strpos($key, '1_') === 0 || strpos($key, '2_') === 0 || strpos($key, '3_') === 0) {
            $name = substr($key, 2);
            if ($value === 'cnf') {
               $adds[] = $name;
            }
            else if ($value === 'del') {
               $deletes[] = $name;
            }
         }
      }

      // submit as post and get response
      if (count($adds) > 0 || count($deletes) > 0) {
         $responseString = $this->getPostResponse($adds, $deletes, $wgRequest->getVal('comment'));
         if (!$responseString) {
            return $this->getHttpErrorMessage();
         }
         eval('$response = ' . $responseString . ';');

         // set soundex
         $this->soundex = join(' ',$response['soundex']);
      }
      else {
         $this->soundex = '';
      }
      return '';
   }

	private function generateCommentLog($type, $name, $skin) {
		$dbr =& wfGetDB( DB_SLAVE );
		$type = $dbr->addQuotes($type == 'g' ? 'givenname' : 'surname');
		$name = $dbr->addQuotes($name);
		$sql = <<<END
SELECT log_timestamp, log_user_text, log_adds, log_deletes, log_comment
from names_log
where log_type=$type and log_name=$name
order by log_id desc
END;
		$logs = array();
		$rows = $dbr->query($sql);
		while ($row = $dbr->fetchObject($rows)) {
			$logs[] = $this->formatLogEntry($skin, $row);
		}
		$dbr->freeResult($rows);
		if (count($logs) > 0) {
			return wfMsgHtml('changehistory').'<dl class="wr-nameslog">'.join('', $logs).'</dl>';
		}
		return '';
	}

	private function formatLogEntry( $skin, $result ) {
		global $wgLang;

		$statusDate = $wgLang->timeAndDate( $result->log_timestamp, true );
		$userid = User::idFromName($result->log_user_text);
		$ulink = $skin->userLink( $userid, $result->log_user_text ) . $skin->userToolLinks( $userid, $result->log_user_text );
      $comment = $result->log_comment ? ' <em>('.htmlspecialchars($result->log_comment).')</em>' : '';
      $adds = '';
      $deletes = '';
      if ($result->log_adds) {
         $adds = '<dd>+ <span class="wr-nameslog-adds">'.htmlspecialchars($result->log_adds).'</span></dd>';
      }
      if ($result->log_deletes) {
         $deletes = '<dd>- <span class="wr-nameslog-deletes">'.htmlspecialchars($result->log_deletes).'</span></dd>';
      }
		return "<dt>$statusDate {$ulink} $comment</dt>$deletes$adds";
	}
}
