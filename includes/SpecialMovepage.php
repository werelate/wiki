<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */

/**
 * Constructor
 */
function wfSpecialMovepage( $par = null ) {
	global $wgUser, $wgOut, $wgRequest, $action, $wgOnlySysopMayMove;

	# check rights. We don't want newbies to move pages to prevents possible attack
	if ( !$wgUser->isAllowed( 'move' ) or $wgUser->isBlocked() or ($wgOnlySysopMayMove and $wgUser->isNewbie())) {
		$wgOut->showErrorPage( "movenologin", "movenologintext" );
		return;
	}
	# We don't move protected pages
	if ( wfReadOnly() ) {
		$wgOut->readOnlyPage();
		return;
	}

	$f = new MovePageForm( $par );

	if ( 'success' == $action ) {
		$f->showSuccess();
	} else if ( 'submit' == $action && $wgRequest->wasPosted()
		&& $wgUser->matchEditToken( $wgRequest->getVal( 'wpEditToken' ) ) ) {
		$f->doSubmit();
	} else {
		$f->showForm( '' );
	}
}

/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */
class MovePageForm {
	var $oldTitle, $newTitle, $reason; # Text input
	var $moveTalk, $deleteAndMove;

	function MovePageForm( $par ) {
		global $wgRequest;
		$target = isset($par) ? $par : $wgRequest->getVal( 'target' );
		$this->oldTitle = $wgRequest->getText( 'wpOldTitle', $target );
		$this->newTitle = $wgRequest->getText( 'wpNewTitle' );
		$this->reason = $wgRequest->getText( 'wpReason' );
		$this->moveTalk = $wgRequest->getBool( 'wpMovetalk', true );
		$this->deleteAndMove = $wgRequest->getBool( 'wpDeleteAndMove' ) && $wgRequest->getBool( 'wpConfirm' );
	}

	function showForm( $err ) {
		global $wgOut, $wgUser;

		$wgOut->setPagetitle( wfMsg( 'movepage' ) );

		$ot = Title::newFromURL( $this->oldTitle );
		if( is_null( $ot ) ) {
			$wgOut->showErrorPage( 'notargettitle', 'notargettext' );
			return;
		}
		$oldTitle = $ot->getPrefixedText();

		$encOldTitle = htmlspecialchars( $oldTitle );
		if( $this->newTitle == '' ) {
			# Show the current title as a default
			# when the form is first opened.
// WERELATE - remove (d+) from end of person/family title
			$encNewTitle = ($ot->getNamespace() == NS_PERSON || $ot->getNamespace() == NS_FAMILY) ? preg_replace('/\s+\(\d+\)$/', '', $encOldTitle) : $encOldTitle;
		} else {
			if( $err == '' ) {
				$nt = Title::newFromURL( $this->newTitle );
				if( $nt ) {
					# If a title was supplied, probably from the move log revert
					# link, check for validity. We can then show some diagnostic
					# information and save a click.
					$newerr = $ot->isValidMoveOperation( $nt );
					if( is_string( $newerr ) ) {
						$err = $newerr;
					}
				}
			}
			$encNewTitle = htmlspecialchars( $this->newTitle );
		}
		$encReason = htmlspecialchars( $this->reason );

// WERELATE: added title parm
      $tempTitle = $this->newTitle ? Title::newFromURL($this->newTitle) : null;
		if ( $err == 'articleexists' && $wgUser->isAllowed( 'delete', $tempTitle ) ) {
			$wgOut->addWikiText( wfMsg( 'delete_and_move_text', $encNewTitle ) );
			$movepagebtn = wfMsgHtml( 'delete_and_move' );
			$confirmText = wfMsgHtml( 'delete_and_move_confirm' );
			$submitVar = 'wpDeleteAndMove';
			$confirm = "
				<tr>
					<td align='right'>
						<input type='checkbox' name='wpConfirm' id='wpConfirm' value=\"true\" />
					</td>
					<td align='left'><label for='wpConfirm'>{$confirmText}</label></td>
				</tr>";
         $cancel = "&nbsp; <a href=\"{$ot->getLocalURL()}\">Cancel</a>";
			$err = '';
		} else {
			$wgOut->addWikiText( wfMsg( 'movepagetext' ) );
			$movepagebtn = wfMsgHtml( 'movepagebtn' );
			$submitVar = 'wpMove';
			$confirm = false;
         $cancel = '';
		}

		$oldTalk = $ot->getTalkPage();
		$considerTalk = ( !$ot->isTalkPage() && $oldTalk->exists() );

		if ( $considerTalk ) {
			$wgOut->addWikiText( wfMsg( 'movepagetalktext' ) );
		}

		$movearticle = wfMsgHtml( 'movearticle' );
		$newtitle = wfMsgHtml( 'newtitle' );
		$movetalk = wfMsgHtml( 'movetalk' );
		$movereason = wfMsgHtml( 'movereason' );

		$titleObj = Title::makeTitle( NS_SPECIAL, 'Movepage' );
		$action = $titleObj->escapeLocalURL( 'action=submit' );
		$token = htmlspecialchars( $wgUser->editToken() );

		if ( $err != '' ) {
			$wgOut->setSubtitle( wfMsg( 'formerror' ) );
			$wgOut->addWikiText( '<p class="error">' . wfMsg($err) . "</p>\n" );
		}

		$moveTalkChecked = $this->moveTalk ? ' checked="checked"' : '';

		$wgOut->addHTML( "
<form id=\"movepage\" method=\"post\" action=\"{$action}\">
	<table border='0'>
		<tr>
			<td align='right'>{$movearticle}:</td>
			<td align='left'><strong>{$oldTitle}</strong></td>
		</tr>
		<tr>
			<td align='right'><label for='wpNewTitle'>{$newtitle}:</label></td>
			<td align='left'>
				<input type='text' size='40' name='wpNewTitle' id='wpNewTitle' value=\"{$encNewTitle}\" />
				<input type='hidden' name=\"wpOldTitle\" value=\"{$encOldTitle}\" />
			</td>
		</tr>" );
    // WERELATE - added button to standardize the title (required splitting the original addHTML statement)    added Dec 2021 by Janet Bjorndahl
    // If suggested new title not passed in for a person or family page, and the page meets certain criteria, 
    // include a button to redisplay the form with the standardized title as the suggested new title.
    if ( $this->newTitle == '' ) {
      if ( $ot->getNamespace() == NS_PERSON || $ot->getNamespace() == NS_FAMILY ) {
        $correctTitle = StructuredData::standardizeTitle($ot);
        if ( $correctTitle!='' ) {
          $standardizetitle = wfMsgHtml( 'standardizetitle' );
          $url = $titleObj->getLocalURL('target='.wfUrlencode($encOldTitle).
                                        '&wpNewTitle='.wfUrlencode($correctTitle).
                                        '&wpReason='.wfUrlencode('make page title agree with name'));
          $wgOut->addHTML ("
            <td>&nbsp;</td>
            <td align='left'>
              <a href=$url>
		            <button type='button'>{$standardizetitle}</button>
              </a>
            </td>
          </tr>");
        }
      }    
    }         
    $wgOut->addHTML("
		<tr>
			<td align='right' valign='top'><br /><label for='wpReason'>{$movereason}:</label></td>
			<td align='left' valign='top'><br />
				<textarea cols='60' rows='2' name='wpReason' id='wpReason'>{$encReason}</textarea>
			</td>
		</tr>" );

		if ( $considerTalk ) {
			$wgOut->addHTML( "
		<tr>
			<td align='right'>
				<input type='checkbox' id=\"wpMovetalk\" name=\"wpMovetalk\"{$moveTalkChecked} value=\"1\" />
			</td>
			<td><label for=\"wpMovetalk\">{$movetalk}</label></td>
		</tr>" );
		}
		// WERELATE - added id='wpMove' and cancel
		$wgOut->addHTML( "
		{$confirm}
		<tr>
			<td>&nbsp;</td>
			<td align='left'>
				<input type='submit' id='wpMove' name=\"{$submitVar}\" value=\"{$movepagebtn}\" />
   			$cancel
			</td>
		</tr>
	</table>
	<input type='hidden' name='wpEditToken' value=\"{$token}\" />
</form>\n" );

	$this->showLogFragment( $ot, $wgOut );

	}

	function doSubmit() {
		global $wgOut, $wgUser, $wgRequest;
		$fname = "MovePageForm::doSubmit";

		if ( $wgUser->pingLimiter( 'move' ) ) {
			$wgOut->rateLimited();
			return;
		}

		# Variables beginning with 'o' for old article 'n' for new article

// WERELATE - default ns on new title to ns on old title
		$ot = Title::newFromText( $this->oldTitle );
		$nt = Title::newFromText( $this->newTitle, $ot->getNamespace() );

// WERELATE - added: PERSON and FAMILY pages must have a unique id
		if ($nt && ($nt->getNamespace() == NS_PERSON || $nt->getNamespace() == NS_FAMILY) && !StructuredData::titleStringHasId($nt->getText())) {
			$nt = StructuredData::appendUniqueId($nt);
		}

		# Delete to make way if requested
// WERELATE: added title parm and $nt condition
		if ($nt && $wgUser->isAllowed( 'delete', $nt ) && $this->deleteAndMove ) {
			$article = new Article( $nt );
			// This may output an error message and exit
			$article->doDelete( wfMsgForContent( 'delete_and_move_reason' ) );
		}

		# don't allow moving to pages with # in
		if ( !$nt || $nt->getFragment() != '' ) {
			$this->showForm( 'badtitletext' );
			return;
		}

		$error = $ot->moveTo( $nt, true, $this->reason );
		if ( $error !== true ) {
			$this->showForm( $error );
			return;
		}

		wfRunHooks( 'SpecialMovepageAfterMove', array( &$this , &$ot , &$nt ) )	;

		# Move the talk page if relevant, if it exists, and if we've been told to
		$ott = $ot->getTalkPage();
		if( $ott->exists() ) {
			if( $wgRequest->getVal( 'wpMovetalk' ) == 1 && !$ot->isTalkPage() && !$nt->isTalkPage() ) {
				$ntt = $nt->getTalkPage();

				# Attempt the move
				$error = $ott->moveTo( $ntt, true, $this->reason );
				if ( $error === true ) {
					$talkmoved = 1;
					wfRunHooks( 'SpecialMovepageAfterMove', array( &$this , &$ott , &$ntt ) )	;
				} else {
					$talkmoved = $error;
				}
			} else {
				# Stay silent on the subject of talk.
				$talkmoved = '';
			}
		} else {
			$talkmoved = 'notalkpage';
		}

		# Give back result to user.
		$titleObj = Title::makeTitle( NS_SPECIAL, 'Movepage' );
		$success = $titleObj->getFullURL(
		  'action=success&oldtitle=' . wfUrlencode( $ot->getPrefixedText() ) .
		  '&newtitle=' . wfUrlencode( $nt->getPrefixedText() ) .
		  '&talkmoved='.$talkmoved );

		$wgOut->redirect( $success );
	}

	function showSuccess() {
		global $wgOut, $wgRequest, $wgRawHtml;

		$wgOut->setPagetitle( wfMsg( 'movepage' ) );
		$wgOut->setSubtitle( wfMsg( 'pagemovedsub' ) );

		$oldText = $wgRequest->getVal('oldtitle');
		$newText = $wgRequest->getVal('newtitle');
		$talkmoved = $wgRequest->getVal('talkmoved');

		$text = wfMsg( 'pagemovedtext', $oldText, $newText );

		$allowHTML = $wgRawHtml;
		$wgRawHtml = false;
		$wgOut->addWikiText( $text );
		$wgRawHtml = $allowHTML;

		if ( $talkmoved == 1 ) {
			$wgOut->addWikiText( wfMsg( 'talkpagemoved' ) );
		} elseif( 'articleexists' == $talkmoved ) {
			$wgOut->addWikiText( wfMsg( 'talkexists' ) );
		} else {
			$oldTitle = Title::newFromText( $oldText );
			if ( !$oldTitle->isTalkPage() && $talkmoved != 'notalkpage' ) {
				$wgOut->addWikiText( wfMsg( 'talkpagenotmoved', wfMsg( $talkmoved ) ) );
			}
		}
	}

	function showLogFragment( $title, &$out ) {
		$out->addHtml( wfElement( 'h2', NULL, LogPage::logName( 'move' ) ) );
		$request = new FauxRequest( array( 'page' => $title->getPrefixedText(), 'type' => 'move' ) );
		$viewer = new LogViewer( new LogReader( $request ) );
		$viewer->showList( $out );
	}

}
?>
