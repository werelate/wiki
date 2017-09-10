<?php
require_once("$IP/includes/UserMailer.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialEmailSetup";

function wfSpecialEmailSetup() {
	global $wgMessageCache, $wgSpecialPages;
	$wgMessageCache->addMessages( array( "email" => "Email" ) );
	$wgSpecialPages['Email'] = array('SpecialPage','Email');
}

function wfSpecialEmail() {
	global $wgRequest;
	
	$f = new EmailForm( $wgRequest );
	$f->execute();
}

/**
 * @todo document
 * @package MediaWiki
 * @subpackage SpecialPage
 */
class EmailForm {
	var $action;
	var $target;
	var $text, $subject;
	var $editToken;
	var $wasPosted;

	function __construct( $request ) {
		$this->action = $request->getVal('action');
		$this->target = $request->getVal('wpRecipient');
		$this->subject = $request->getVal('wpSubject');
		$this->text = $request->getVal('wpText');
		$this->editToken = $request->getVal('wpEditToken');
		$this->returnTo = $request->getVal('returnto');
		$this->wasPosted = $request->wasPosted();
	}
	
	function execute() {
		global $wgUser, $wgOut, $wgEnableEmail, $wgEnableUserEmail;
	
		if( !( $wgEnableEmail && $wgEnableUserEmail ) ) {
			$wgOut->showErrorPage( "nosuchspecialpage", "nospecialpagetext" );
			return;
		}
	
		if( !$wgUser->canSendEmail() ) {
			wfDebug( "User can't send.\n" );
			$wgOut->showErrorPage( "mailnologin", "mailnologintext" );
			return;
		}

		if ( "success" == $this->action ) {
			$this->showSuccess();
		} else if ( "submit" == $this->action && $this->wasPosted && $wgUser->matchEditToken( $this->editToken ) ) {
			$this->doSubmit();
		} else {
			$this->showForm();
		}
	}

	function showForm($msg = null) {
		global $wgOut, $wgUser;

		$wgOut->setPagetitle( wfMsg( "emailpage" ) );
		if ($msg) {
			$msg = htmlspecialchars($msg);
			$wgOut->addHTML( "<div class='error'>$msg</div>\n");
		}
		$wgOut->addWikiText( wfMsg( "shareemailpagetext" ) );

		// default subject and text if they're empty
		if ( !$this->subject ) {
			$this->subject = wfMsg( "defshareemailsubject" );
		}
		
		if ( !$this->text ) {
			$t = Title::newFromText($this->returnTo);
			if ($t) {
				$link = $t->getFullURL();
				$this->text = wfMsg("defshareemailtext", $t->getFullURL());
			}
		}

		$emf = wfMsg( "emailfrom" );
		$sender = $wgUser->getName();
		$emt = wfMsg( "emailto" );
		$encRcpt = htmlspecialchars($this->target);
		$emr = wfMsg( "emailsubject" );
		$emm = wfMsg( "emailmessage" );
		$ems = wfMsg( "emailsend" );
		$encSubject = htmlspecialchars( $this->subject );

		$titleObj = Title::makeTitle( NS_SPECIAL, "Email" );
		$action = $titleObj->escapeLocalURL( "action=submit" );
		$encReturnTo = htmlspecialchars($this->returnTo);
		$token = $wgUser->editToken();

		$wgOut->addHTML( "
<form id=\"emailuser\" method=\"post\" action=\"{$action}\">
<table border='0' id='mailheader'><tr>
<td align='right'>{$emf}:</td>
<td align='left'><strong>" . htmlspecialchars( $sender ) . "</strong></td>
</tr><tr>
<td align='right'>{$emt}:</td>
<td align='left'><input type='text' size='60' maxlength='200' name=\"wpRecipient\" value=\"{$encRcpt}\" /></td>
</tr><tr>
<td align='right'>{$emr}:</td>
<td align='left'>
<input type='text' size='60' maxlength='200' name=\"wpSubject\" value=\"{$encSubject}\" />
</td>
</tr>
</table>
<span id='wpTextLabel'><label for=\"wpText\">{$emm}:</label><br /></span>
<textarea name=\"wpText\" rows='20' cols='80' wrap='virtual' style=\"width: 100%;\">" . htmlspecialchars( $this->text ) .
"</textarea>
<input type='hidden' name='returnto' value=\"{$encReturnTo}\" />
<input type='submit' name=\"wpSend\" value=\"{$ems}\" />
<input type='hidden' name='wpEditToken' value=\"$token\" />
</form>\n" );

	}

	function doSubmit() {
		global $wgOut, $wgUser;

		$to = array();
		$addrs = preg_split('/[,;]/', $this->target);
		$err = false;
		for ($i = 0; $i < count($addrs); $i++) {
			$addr = trim($addrs[$i]);
			$u = null;
			if ($addr) {
				$u = User::newFromName($addr);
			}
			if (is_null($u) || $u->getID() == 0) {
				if (strpos($addr, '@') > 0) {
					$to[] = $addr;
				}
			}
			else if (!$u->canReceiveEmail()) {
				$err = true;
				$wgOut->addHTML('<div class="error">' . wfMsg("noemailtext", $addr) . '</div>');
			}
			else {
				$to[] = $u->getEmail();
			}
		}
		if ($err || count($to) == 0) {
			// have we already displayed the probable reason?
			$this->showForm($err ? '' : "Missing or invalid recipient email address / user name" );
			return;
		}

		$dest = new MailAddress(implode(", ", $to)); // hack to pass in multiple addresses to userMailer
		$from = new MailAddress( $wgUser );
		$subject = $this->subject;
		$text = $this->text;

		if( wfRunHooks( 'Email', array( &$dest, &$from, &$subject, &$text ) ) ) {
			$mailResult = userMailer( $dest, $from, $subject, $text );
			$destString = $dest->toString();
			wfDebug("SpecialEmail email sent to: $destString\n");
			if( WikiError::isError( $mailResult ) ) {
				$err = true;
				$wgOut->addHTML( wfMsg( "usermailererror" ) . $mailResult);
			} else {
				$titleObj = Title::makeTitle( NS_SPECIAL, "Email" );
				$encTarget = wfUrlencode( $this->returnTo );
				$wgOut->redirect( $titleObj->getFullURL( "returnto={$encTarget}&action=success" ) );
				wfRunHooks( 'EmailComplete', array( $dest, $from, $subject, $text ) );
			}
		}
	}

	function showSuccess() {
		global $wgOut;

		$wgOut->setPagetitle( wfMsg( "emailsent" ) );
		$wgOut->addHTML( wfMsg( "emailsenttext" ) );

		$wgOut->returnToMain( false, $this->returnTo );
	}
}
?>
