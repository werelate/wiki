<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */

/**
 *
 */
require_once 'Image.php';
/**
 * Entry point
 */
function wfSpecialUpload() {
	global $wgRequest;
	$form = new UploadForm( $wgRequest );
	$form->execute();
}

/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */
class UploadForm {
	/**#@+
	 * @access private
	 */
	var $mUploadFile, $mUploadDescription, $mLicense ,$mIgnoreWarning, $mUploadError;
	var $mUploadSaveName, $mUploadTempName, $mUploadSize, $mUploadOldVersion;
	var $mUploadCopyStatus, $mUploadSource, $mReUpload, $mAction, $mUpload;
	var $mOname, $mSessionKey, $mStashed, $mDestFile, $mRemoveTempFile;
// WERELATE - added vars
   var $mDate, $mPlace, $mCopyright, $mReUploading, $mPeople, $mFamilies;
   var $mTarget, $mId;
   var $mWhatLinksHere = null;
	/**#@-*/

// WERELATE - copied escapeXml, titleStringHasId, getXml, getWhatLinksHere from StructuredData
//          - added getAttrs, getMetadata, fromRequest, toRequest functions
	private function escapeXml($text) {
		return str_replace(array('&', '<', '>', '"', "'"), array('&amp;', '&lt;', '&gt;', '&quot;', '&apos;'), $text);
	}
	
	private function titleStringHasId($titleString) {
		return preg_match('/\(\d+\)$/', $titleString);
	}
	

	private function standardizeNameCase($name) {
      $result = '';
      $pieces = explode(' ', $name);
      for ($i = 0; $i < count($pieces); $i++) {
         if ($pieces[$i]) {
            if ($result) {
               $result .= ' ';
            }

            if ($i < count($pieces) - 1 && strcasecmp($pieces[$i], 'and') == 0) {
               $result .= 'and';
            }
            else if ($pieces[$i] == mb_strtoupper($pieces[$i]) || $pieces[$i] == mb_strtolower($pieces[$i])) {  // all uppercase or all lowercase
               $result .= mb_convert_case($pieces[$i], MB_CASE_TITLE);
            }
            else {
               $result .= $pieces[$i];
            }
         }
      }
		return $result;
	}
	
	private function getXml($tagName, &$text) {
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
	
	private function getWhatLinksHere() { 
		if (is_null($this->mWhatLinksHere) && $this->mDestFile) {
			$title = Title::newFromText($this->mDestFile, NS_IMAGE);
			if ($title) {
				$this->mWhatLinksHere = array();
				$db =& wfGetDB(DB_MASTER); // make sure this is the most current set of pages that link here
				// get pages linking to images
				$rows = $db->select('imagelinks', 'il_from', array('il_to' => $title->getDBkey()), 'SpecialUpload::getWhatLinksHere');
				while ($row = $db->fetchObject($rows)) {
					$this->mWhatLinksHere[] = $row->il_from;
				}
				$db->freeResult($rows);
			}
		}
		return $this->mWhatLinksHere;
	}
	
	private function getEventAttrs($eventName, $event) {
		$result = '';
		if ((string)$event['date']) {
			$result .= " {$eventName}date=\"".$this->escapeXml((string)$event['date']).'"';
		}
		if ((string)$event['place']) {
			$result .= " {$eventName}place=\"".$this->escapeXml((string)$event['place']).'"';
		}
		return $result;
	}
	
	private function getAttrs($titleString, $ns) {
		$result = '';
		$t = Title::newFromText($titleString, $ns);
		$revision = null;
		$nt = $t;
		while ($nt) { // follow redirects
			$t = $nt;
			$nt = null;
			$revision = Revision::newFromTitle($t);
			if ($revision) {
				$text =& $revision->getText();
				$nt = Title::newFromRedirect($text);
			}
		}
		if ($t) {
			$result .= ' title="'.$this->escapeXml($t->getText()).'"';
		}
		if ($revision) {
			// get attributes from text
			if ($ns == NS_PERSON) {
				$xml = $this->getXml('person', $text);
				if (isset($xml)) {
					$name = $xml->name;
					if (isset($name)) {
						if ((string)$name['given']) {
							$result .= ' given="'.$this->escapeXml((string)$name['given']).'"';
						}
						if ((string)$name['surname']) {
							$result .= ' surname="'.$this->escapeXml((string)$name['surname']).'"';
						}
						if ((string)$name['title_prefix']) {
							$result .= ' title_prefix="'.$this->escapeXml((string)$name['title_prefix']).'"';
						}
						if ((string)$name['title_suffix']) {
							$result .= ' title_suffix="'.$this->escapeXml((string)$name['title_suffix']).'"';
						}
					}
					if (isset($xml->event_fact)) {
			   		foreach ($xml->event_fact as $ef) {
			   			if ($ef['type'] == 'Birth') {
			   				$result .= $this->getEventAttrs('birth', $ef);
			   			}
			   			else if ($ef['type'] == 'Death') {
			   				$result .= $this->getEventAttrs('death', $ef);
			   			}
			   			else if ($ef['type'] == 'Christening' || $ef['type'] == 'Baptism') {
			   				$result .= $this->getEventAttrs('chr', $ef);
			   			}
			   			else if ($ef['type'] == 'Burial') {
			   				$result .= $this->getEventAttrs('burial', $ef);
			   			}
			   		}
					}
				}
			}
		}
		return $result;
	}

   private function getMetadata() {
      $result = "<image_data>\n".
         ($this->mLicense ? '<license>'.$this->escapeXml($this->mLicense)."</license>\n" : '') .
      	($this->mCopyright ? '<copyright_holder>'.$this->escapeXml($this->mCopyright)."</copyright_holder>\n" : '');
      $result .= 
         ($this->mDate ? '<date>'.$this->escapeXml($this->mDate)."</date>\n" : '') .
         ($this->mPlace ? '<place>'.$this->escapeXml($this->mPlace)."</place>\n" : '');
		foreach ($this->mPeople as $person) {
			$attrs = $this->getAttrs($person, NS_PERSON);
			$result .= "<person$attrs/>\n";
		}
		foreach ($this->mFamilies as $family) {
			$attrs = $this->getAttrs($family, NS_FAMILY);
			$result .= "<family$attrs/>\n";
		}
      $result .= "</image_data>\n";
      return $result;
   }

	private function fromRequest($request, $name, $ns) {
		$targetTitle = '';
		if ($this->mTarget) {
			$t = Title::newFromText($this->mTarget);
			if ($t && $t->getNamespace() == $ns) {
				$targetTitle = $t->getText();
			}
		}
	   $result = array();
	   // get titles from request, except target
		for ($i = 0; $request->getVal("{$name}_id$i"); $i++) {
		   $titleString = $request->getVal("$name$i");
		   if ($titleString) {
		      if (!$this->titleStringHasId($titleString)) {
		         $titleString = $this->standardizeNameCase($titleString);
		      }
		   	$t = Title::newFromText($titleString, $ns);
				// don't save target title; we'll update that when the target page is saved
		   	if ($t && $t->getNamespace() == $ns && $t->getText() != $targetTitle && !in_array($t->getText(), $result)) { 
			      $result[] = $t->getText();
		   	}
		   }
		}
		// add any titles from WLH (even target if it already links here)
		// uses mDestFile instead of mUploadSaveName because the latter hasn't been set yet
		// only do this if the request is a "get" with a passed-in wpDestFile
		if( !$request->wasPosted() && $this->mDestFile) {
			$t = Title::newFromText($this->mDestFile, NS_IMAGE);
			$filename = ($t ? $t->getText() : '');
			$pageids = $this->getWhatLinksHere();
			$db =& wfGetDB(DB_MASTER); // make sure this is the most current set of pages that link here
			foreach ($pageids as $pageid) {
				$revision = Revision::loadFromPageId($db, $pageid);
				if ($revision && $revision->getTitle()->getNamespace() == $ns) {
					$text = $revision->getText();
					$xml = $this->getXml($name, $text);
					if (isset($xml)) {
						foreach ($xml->image as $img) {
							if ((string)$img['filename'] == $filename && !in_array($revision->getTitle()->getText(), $result)) {
								$result[] = $revision->getTitle()->getText();
							}
						}
					}
				}
			}
		}
	   return $result;
	}

	private function toForm($titles, $name, $ns, $visible) {
		$targetPos = -1;
		if ($this->mTarget) {
			$t = Title::newFromText($this->mTarget);
			if ($t && $t->getNamespace() == $ns &&	!in_array($t->getText(), $titles)) {
				$targetPos = count($titles);
				$titles[] = $t->getText();
			}
		}
		if ($visible) {
			if (count($titles) == 0) { // ensure go through loop at least once
				$titles[] = '';
			}
			$result = "<table id='image_{$name}_table' cellspacing=0 cellpadding=0>";
		}
		else {
			$result = '';
		}
		$i = 0;
		foreach($titles as $title) {
			$readOnly = ($visible && $targetPos == $i ? " readOnly='true'" : '');
			$result .= ($visible ? "<tr><td>" : '') .
				"<input type=\"hidden\" name=\"{$name}_id$i\" value=\"".($i+1)."\"/>".
				"<input ".($visible ? "class=\"{$name}_input\" type=\"text\" size=40$readOnly" : "type=\"hidden\"")." name=\"$name$i\" value=\"" . htmlspecialchars( $title ) . "\"/>".
				($visible ? "</td></tr>" : '') . "\n";
			$i++;
		}
		if ($visible) {
			$result .= '</table>';
		}
		return $result;
	}


	// WERELATE added
	private function cleanFilename($filename) {
      $unwanted_array = array(
       'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
       'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
       'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
       'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
       'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y',
       'Ğ'=>'G', 'İ'=>'I', 'Ş'=>'S', 'ğ'=>'g', 'ı'=>'i', 'ş'=>'s', 'ü'=>'u',
       'ă'=>'a', 'Ă'=>'A', 'ș'=>'s', 'Ș'=>'S', 'ț'=>'t', 'Ț'=>'T');
      return iconv('UTF-8', 'US-ASCII//TRANSLIT//IGNORE', strtr($filename, $unwanted_array ));
	}

   /**
	 * Constructor : initialise object
	 * Get data POSTed through the form and assign them to the object
	 * @param $request Data posted.
	 */
	function UploadForm( &$request ) {
// WERELATE - clean filename so we don't error when creating thumbnails
		$this->mDestFile          = $this->cleanFilename($request->getText( 'wpDestFile' ));
// WERELATE - add target and id and people and families
		$this->mTarget				  = $request->getText( 'target');
		$this->mId					  = $request->getText( 'id');
		$this->mPeople            = $this->fromRequest($request, 'person', NS_PERSON);
		$this->mFamilies          = $this->fromRequest($request, 'family', NS_FAMILY);

// WERELATE - added mReUploading
		$this->mReUploading       = false;
      if (strlen($this->mDestFile) > 0) {
         $imageTitle = Title::newFromText($this->mDestFile, NS_IMAGE);
         $this->mReUploading = $imageTitle && $imageTitle->exists();
      }
		
		if( !$request->wasPosted() ) {
			# GET requests just give the main form; no data except wpDestfile.
			return;
		}

		$this->mIgnoreWarning     = $request->getCheck( 'wpIgnoreWarning' );
		$this->mReUpload          = $request->getCheck( 'wpReUpload' );
		$this->mUpload            = $request->getCheck( 'wpUpload' );

		$this->mUploadDescription = $request->getText( 'wpUploadDescription' );
		$this->mLicense           = $request->getText( 'wpLicense' );
// WERELATE - added fields
		$this->mDate              = $request->getText( 'wrDate' );
		$this->mPlace             = $request->getText( 'wrPlace' );
		$this->mCopyright         = $request->getText( 'wrCopyright' );
		$this->mUploadCopyStatus  = $request->getText( 'wpUploadCopyStatus' );
		$this->mUploadSource      = $request->getText( 'wpUploadSource' );
		$this->mWatchthis         = $request->getBool( 'wpWatchthis' );

		$this->mAction            = $request->getVal( 'action' );

		$this->mSessionKey        = $request->getInt( 'wpSessionKey' );
		if( !empty( $this->mSessionKey ) &&
			isset( $_SESSION['wsUploadData'][$this->mSessionKey] ) ) {
			/**
			 * Confirming a temporarily stashed upload.
			 * We don't want path names to be forged, so we keep
			 * them in the session on the server and just give
			 * an opaque key to the user agent.
			 */
			$data = $_SESSION['wsUploadData'][$this->mSessionKey];
			$this->mUploadTempName   = $data['mUploadTempName'];
			$this->mUploadSize       = $data['mUploadSize'];
			$this->mOname            = $data['mOname'];
			$this->mUploadError      = 0/*UPLOAD_ERR_OK*/;
			$this->mStashed          = true;
			$this->mRemoveTempFile   = false;
		} else {
			/**
			 *Check for a newly uploaded file.
			 */
			$this->mUploadTempName = $request->getFileTempName( 'wpUploadFile' );
			$this->mUploadSize     = $request->getFileSize( 'wpUploadFile' );
			$this->mOname          = $request->getFileName( 'wpUploadFile' );
			$this->mUploadError    = $request->getUploadError( 'wpUploadFile' );
			$this->mSessionKey     = false;
			$this->mStashed        = false;
			$this->mRemoveTempFile = false; // PHP will handle this
		}
	}

	/**
	 * Start doing stuff
	 * @access public
	 */
	function execute() {
		global $wgUser, $wgOut;
		global $wgEnableUploads, $wgUploadDirectory;

		# Check uploading enabled
		if( !$wgEnableUploads ) {
			$wgOut->showErrorPage( 'uploaddisabled', 'uploaddisabledtext' );
			return;
		}

		# Check permissions
		if( $wgUser->isLoggedIn() ) {
			if( !$wgUser->isAllowed( 'upload' ) ) {
				$wgOut->permissionRequired( 'upload' );
				return;
			}
		} else {
			$wgOut->showErrorPage( 'uploadnologin', 'uploadnologintext' );
			return;
		}

		# Check blocks
		if( $wgUser->isBlocked() ) {
			$wgOut->blockedPage();
			return;
		}

		if( wfReadOnly() ) {
			$wgOut->readOnlyPage();
			return;
		}

		/** Check if the image directory is writeable, this is a common mistake */
		if ( !is_writeable( $wgUploadDirectory ) ) {
			$wgOut->addWikiText( wfMsg( 'upload_directory_read_only', $wgUploadDirectory ) );
			return;
		}

		if( $this->mReUpload ) {
			if ( !$this->unsaveUploadedFile() ) {
				return;
			}
			$this->mainUploadForm();
		} else if ( 'submit' == $this->mAction || $this->mUpload ) {
			$this->processUpload();
		} else {
			$this->mainUploadForm();
		}

		$this->cleanupTempFile();
	}

	/* -------------------------------------------------------------- */

	/**
	 * Really do the upload
	 * Checks are made in SpecialUpload::execute()
	 * @access private
	 */
	function processUpload() {
		global $wgUser, $wgOut;

		/* Check for PHP error if any, requires php 4.2 or newer */
		if ( $this->mUploadError == 1/*UPLOAD_ERR_INI_SIZE*/ ) {
			$this->mainUploadForm( wfMsgHtml( 'largefileserver' ) );
			return;
		}

		/**
		 * If there was no filename or a zero size given, give up quick.
		 */
		if( trim( $this->mOname ) == '' || empty( $this->mUploadSize ) ) {
			$this->mainUploadForm( wfMsgHtml( 'emptyfile' ) );
			return;
		}

		# Chop off any directories in the given filename
		if ( $this->mDestFile ) {
			$basename = wfBaseName( $this->mDestFile );
		} else {
			$basename = wfBaseName( $this->mOname );
		}

		/**
		 * We'll want to blacklist against *any* 'extension', and use
		 * only the final one for the whitelist.
		 */
		list( $partname, $ext ) = $this->splitExtensions( $basename );

		if( count( $ext ) ) {
			$finalExt = $ext[count( $ext ) - 1];
		} else {
			$finalExt = '';
		}
		$fullExt = implode( '.', $ext );

		# If there was more than one "extension", reassemble the base
		# filename to prevent bogus complaints about length
		if( count( $ext ) > 1 ) {
			for( $i = 0; $i < count( $ext ) - 1; $i++ )
				$partname .= '.' . $ext[$i];
		}

		if ( strlen( $partname ) < 3 ) {
			$this->mainUploadForm( wfMsgHtml( 'minlength' ) );
			return;
		}

// WERELATE - added validation tests
      if (!$this->mLicense && !$this->mReUploading) {
         $this->uploadError("You must select a license (press the \"back button\" on your browser to correct this)");
         return;
      }

      /**
		 * Filter out illegal characters, and try to make a legible name
		 * out of it. We'll strip some silently that Title would die on.
		 */
		$filtered = preg_replace ( "/[^".Title::legalChars()."]|:/", '-', $basename );
		$nt = Title::newFromText( $filtered );
		if( is_null( $nt ) ) {
			$this->uploadError( wfMsgWikiHtml( 'illegalfilename', htmlspecialchars( $filtered ) ) );
			return;
		}
		$nt =& Title::makeTitle( NS_IMAGE, $nt->getDBkey() );
		$this->mUploadSaveName = $nt->getDBkey();

		/**
		 * If the image is protected, non-sysop users won't be able
		 * to modify it by uploading a new revision.
		 */
		if( !$nt->userCanEdit() ) {
			return $this->uploadError( wfMsgWikiHtml( 'protectedpage' ) );
		}

		/**
		 * In some cases we may forbid overwriting of existing files.
		 */
		$overwrite = $this->checkOverwrite( $this->mUploadSaveName );
		if( WikiError::isError( $overwrite ) ) {
			return $this->uploadError( $overwrite->toString() );
		}

		/* Don't allow users to override the blacklist (check file extension) */
		global $wgStrictFileExtensions;
		global $wgFileExtensions, $wgFileBlacklist;
		if( $this->checkFileExtensionList( $ext, $wgFileBlacklist ) ||
			($wgStrictFileExtensions &&
				!$this->checkFileExtension( $finalExt, $wgFileExtensions ) ) ) {
			return $this->uploadError( wfMsgHtml( 'badfiletype', htmlspecialchars( $fullExt ) ) );
		}

		/**
		 * Look at the contents of the file; if we can recognize the
		 * type but it's corrupt or data of the wrong type, we should
		 * probably not accept it.
		 */
		if( !$this->mStashed ) {
			$this->checkMacBinary();
			$veri = $this->verify( $this->mUploadTempName, $finalExt );

			if( $veri !== true ) { //it's a wiki error...
				return $this->uploadError( $veri->toString() );
			}
		}

		/**
		 * Provide an opportunity for extensions to add futher checks
		 */
		$error = '';
		if( !wfRunHooks( 'UploadVerification',
				array( $this->mUploadSaveName, $this->mUploadTempName, &$error ) ) ) {
			return $this->uploadError( $error );
		}

		/**
		 * Check for non-fatal conditions
		 */
		if ( ! $this->mIgnoreWarning ) {
			$warning = '';

			global $wgCapitalLinks;
			if( $wgCapitalLinks ) {
				$filtered = ucfirst( $filtered );
			}
			if( $this->mUploadSaveName != $filtered ) {
				$warning .=  '<li>'.wfMsgHtml( 'badfilename', htmlspecialchars( $this->mUploadSaveName ) ).'</li>';
			}

			global $wgCheckFileExtensions;
			if ( $wgCheckFileExtensions ) {
				if ( ! $this->checkFileExtension( $finalExt, $wgFileExtensions ) ) {
					$warning .= '<li>'.wfMsgHtml( 'badfiletype', htmlspecialchars( $fullExt ) ).'</li>';
				}
			}

			global $wgUploadSizeWarning;
			if ( $wgUploadSizeWarning && ( $this->mUploadSize > $wgUploadSizeWarning ) ) {
				# TODO: Format $wgUploadSizeWarning to something that looks better than the raw byte
				# value, perhaps add GB,MB and KB suffixes?
				$warning .= '<li>'.wfMsgHtml( 'largefile', $wgUploadSizeWarning, $this->mUploadSize ).'</li>';
			}
			if ( $this->mUploadSize == 0 ) {
				$warning .= '<li>'.wfMsgHtml( 'emptyfile' ).'</li>';
			}

			if( $nt->getArticleID() ) {
				global $wgUser;
				$sk = $wgUser->getSkin();
				$dlink = $sk->makeKnownLinkObj( $nt );
				$warning .= '<li>'.wfMsgHtml( 'fileexists', $dlink ).'</li>';
// WERELATE: added fileexistsnoreupload warning; assume that if user entered license, then this isn't a case of purposeful re-uploading 
				if ($this->mLicense) {
					$warning .= '<li>'.wfMsgHtml('fileexistsnoreupload').'</li>';
				}
			} else {
				# If the file existed before and was deleted, warn the user of this
				# Don't bother doing so if the image exists now, however
// WERELATE: remove
//				$image = new Image( $nt );
//				if( $image->wasDeleted() ) {
//					$skin = $wgUser->getSkin();
//					$ltitle = Title::makeTitle( NS_SPECIAL, 'Log' );
//					$llink = $skin->makeKnownLinkObj( $ltitle, wfMsgHtml( 'deletionlog' ), 'type=delete&page=' . $nt->getPrefixedUrl() );
//					$warning .= wfOpenElement( 'li' ) . wfMsgWikiHtml( 'filewasdeleted', $llink ) . wfCloseElement( 'li' );
//				}
			}

			if( $warning != '' ) {
				/**
				 * Stash the file in a temporary location; the user can choose
				 * to let it through and we'll complete the upload then.
				 */
				return $this->uploadWarning( $warning );
			}
		}

		/**
		 * Try actually saving the thing...
		 * It will show an error form on failure.
		 */
		$hasBeenMunged = !empty( $this->mSessionKey ) || $this->mRemoveTempFile;
		if( $this->saveUploadedFile( $this->mUploadSaveName,
		                             $this->mUploadTempName,
		                             $hasBeenMunged ) ) {
			/**
			 * Update the upload log and create the description page
			 * if it's a new file.
			 */
			$img = Image::newFromName( $this->mUploadSaveName );
// WERELATE - changed - added getMetadata, null out license (because we capture it in metadata), redirect only if target is empty
			$success = $img->recordUpload( $this->mUploadOldVersion,
			                                $this->mUploadDescription,
			                                '',
			                                $this->mUploadCopyStatus,
			                                $this->mUploadSource,
			                                $this->mWatchthis,
			                                $this->getMetadata(),
			                                !$this->mTarget );
			if ( $success ) {
// WERELATE - if we're uploading for a P/F target, showSuccess, else just redirect
				if ($this->mTarget) {
					$this->showSuccess();
				}
				else {
					$article = new Article( $img->getTitle() );
					$article->doRedirect();
				}
				wfRunHooks( 'UploadComplete', array( &$img ) );
			} else {
				// Image::recordUpload() fails if the image went missing, which is
				// unlikely, hence the lack of a specialised message
				$wgOut->showFileNotFoundError( $this->mUploadSaveName );
			}
		}
	}

	/**
	 * Move the uploaded file from its temporary location to the final
	 * destination. If a previous version of the file exists, move
	 * it into the archive subdirectory.
	 *
	 * @todo If the later save fails, we may have disappeared the original file.
	 *
	 * @param string $saveName
	 * @param string $tempName full path to the temporary file
	 * @param bool $useRename if true, doesn't check that the source file
	 *                        is a PHP-managed upload temporary
	 */
	function saveUploadedFile( $saveName, $tempName, $useRename = false ) {
		global $wgOut;

		$fname= "SpecialUpload::saveUploadedFile";

		$dest = wfImageDir( $saveName );
		$archive = wfImageArchiveDir( $saveName );
		if ( !is_dir( $dest ) ) wfMkdirParents( $dest );
		if ( !is_dir( $archive ) ) wfMkdirParents( $archive );

		$this->mSavedFile = "{$dest}/{$saveName}";

		if( is_file( $this->mSavedFile ) ) {
			$this->mUploadOldVersion = gmdate( 'YmdHis' ) . "!{$saveName}";
			wfSuppressWarnings();
			$success = rename( $this->mSavedFile, "${archive}/{$this->mUploadOldVersion}" );
			wfRestoreWarnings();

			if( ! $success ) {
				$wgOut->showFileRenameError( $this->mSavedFile,
				  "${archive}/{$this->mUploadOldVersion}" );
				return false;
			}
			else wfDebug("$fname: moved file ".$this->mSavedFile." to ${archive}/{$this->mUploadOldVersion}\n");
		}
		else {
			$this->mUploadOldVersion = '';
		}

		wfSuppressWarnings();
		$success = $useRename
			? rename( $tempName, $this->mSavedFile )
			: move_uploaded_file( $tempName, $this->mSavedFile );
		wfRestoreWarnings();

		if( ! $success ) {
			$wgOut->showFileCopyError( $tempName, $this->mSavedFile );
			return false;
		} else {
			wfDebug("$fname: wrote tempfile $tempName to ".$this->mSavedFile."\n");
		}

		chmod( $this->mSavedFile, 0644 );
		return true;
	}

	/**
	 * Stash a file in a temporary directory for later processing
	 * after the user has confirmed it.
	 *
	 * If the user doesn't explicitly cancel or accept, these files
	 * can accumulate in the temp directory.
	 *
	 * @param string $saveName - the destination filename
	 * @param string $tempName - the source temporary file to save
	 * @return string - full path the stashed file, or false on failure
	 * @access private
	 */
	function saveTempUploadedFile( $saveName, $tempName ) {
		global $wgOut;
		$archive = wfImageArchiveDir( $saveName, 'temp' );
		if ( !is_dir ( $archive ) ) wfMkdirParents( $archive );
		$stash = $archive . '/' . gmdate( "YmdHis" ) . '!' . $saveName;

		$success = $this->mRemoveTempFile
			? rename( $tempName, $stash )
			: move_uploaded_file( $tempName, $stash );
		if ( !$success ) {
			$wgOut->showFileCopyError( $tempName, $stash );
			return false;
		}

		return $stash;
	}

	/**
	 * Stash a file in a temporary directory for later processing,
	 * and save the necessary descriptive info into the session.
	 * Returns a key value which will be passed through a form
	 * to pick up the path info on a later invocation.
	 *
	 * @return int
	 * @access private
	 */
	function stashSession() {
		$stash = $this->saveTempUploadedFile(
			$this->mUploadSaveName, $this->mUploadTempName );

		if( !$stash ) {
			# Couldn't save the file.
			return false;
		}

		$key = mt_rand( 0, 0x7fffffff );
		$_SESSION['wsUploadData'][$key] = array(
			'mUploadTempName' => $stash,
			'mUploadSize'     => $this->mUploadSize,
			'mOname'          => $this->mOname );
		return $key;
	}

	/**
	 * Remove a temporarily kept file stashed by saveTempUploadedFile().
	 * @access private
	 * @return success
	 */
	function unsaveUploadedFile() {
		global $wgOut;
		wfSuppressWarnings();
		$success = unlink( $this->mUploadTempName );
		wfRestoreWarnings();
		if ( ! $success ) {
			$wgOut->showFileDeleteError( $this->mUploadTempName );
			return false;
		} else {
			return true;
		}
	}

	/* -------------------------------------------------------------- */

	/**
	 * Show some text and linkage on successful upload.
	 * @access private
	 */
	function showSuccess() {
		global $wgUser, $wgOut, $wgContLang;
// WERELATE: add script if uploading for P/F target; change fileuploaded message to wiki text with title, target parms; don't return to main
		$title = Title::makeTitleSafe( NS_IMAGE, $this->mUploadSaveName );
		$value = htmlspecialchars($title->getText());
		$id = htmlspecialchars($this->mId);
		$wgOut->addScript(<<< END
<script type='text/javascript'>/*<![CDATA[*/
$(document).ready(function() {
   window.opener.document.getElementById("$id").value="$value";
});
/*]]>*/</script>
END
);
		$sk = $wgUser->getSkin();
//		$ilink = $sk->makeMediaLink( $this->mUploadSaveName, Image::imageUrl( $this->mUploadSaveName ) );
		$ilink = $sk->makeImageLinkObj( $title, $this->mUploadSaveName, $this->mUploadSaveName);
		$dname = $wgContLang->getNsText( NS_IMAGE ) . ':'.$this->mUploadSaveName;
		$dlink = $sk->makeKnownLink( $dname, $dname );

		$wgOut->addHTML( '<h2>' . wfMsgHtml( 'successfulupload' ) . "</h2>\n" );
//		$text = wfMsgWikiHtml( 'fileuploaded', $ilink, $dlink, htmlspecialchars($this->mTarget) );
		$text = wfMsg( 'fileuploaded', $ilink, $dlink, htmlspecialchars($this->mTarget) );
		$wgOut->addHTML( $text );
//		$wgOut->returnToMain( false );
	}

	/**
	 * @param string $error as HTML
	 * @access private
	 */
	function uploadError( $error ) {
		global $wgOut;
		$wgOut->addHTML( "<h2>" . wfMsgHtml( 'uploadwarning' ) . "</h2>\n" );
		$wgOut->addHTML( "<span class='error'>{$error}</span>\n" );
	}

	/**
	 * There's something wrong with this file, not enough to reject it
	 * totally but we require manual intervention to save it for real.
	 * Stash it away, then present a form asking to confirm or cancel.
	 *
	 * @param string $warning as HTML
	 * @access private
	 */
	function uploadWarning( $warning ) {
		global $wgOut;
		global $wgUseCopyrightUpload;

		$this->mSessionKey = $this->stashSession();
		if( !$this->mSessionKey ) {
			# Couldn't save file; an error has been displayed so let's go.
			return;
		}

		$wgOut->addHTML( "<h2>" . wfMsgHtml( 'uploadwarning' ) . "</h2>\n" );
		$wgOut->addHTML( "<ul class='warning'>{$warning}</ul><br />\n" );

		$save = wfMsgHtml( 'savefile' );
		$reupload = wfMsgHtml( 'reupload' );
		$iw = wfMsgWikiHtml( 'ignorewarning' );
		$reup = wfMsgWikiHtml( 'reuploaddesc' );
		$titleObj = Title::makeTitle( NS_SPECIAL, 'Upload' );
// WERELATE: added target and id 
		$query = 'action=submit';
		if ($this->mTarget) {
			$query .= '&target=' . urlencode($this->mTarget) . '&id=' . urlencode($this->mId);
		}
		$action = $titleObj->escapeLocalURL( $query );

		if ( $wgUseCopyrightUpload )
		{
			$copyright =  "
	<input type='hidden' name='wpUploadCopyStatus' value=\"" . htmlspecialchars( $this->mUploadCopyStatus ) . "\" />
	<input type='hidden' name='wpUploadSource' value=\"" . htmlspecialchars( $this->mUploadSource ) . "\" />
	";
		} else {
			$copyright = "";
		}

// WERELATE - added back-button instructions; added four fields; added id='wpUpload' to wpUpload button; removed reupload; added $people and $families; added checkboxes
//            metadata fields and tree checkboxes are ignored in a re-upload situation
		$people = $this->toForm($this->mPeople, 'person', NS_PERSON, false);
		$families = $this->toForm($this->mFamilies, 'family', NS_FAMILY, false);
//!!! remove this code dependency before sharing
		require_once("extensions/familytree/FamilyTreeUtil.php");
		global $wgUser, $wgRequest;
		$allTrees = FamilyTreeUtil::getFamilyTrees($wgUser->getName());
		$checkedTreeIds = FamilyTreeUtil::readTreeCheckboxes($allTrees, $wgRequest);
		$treecheckboxeshtml = FamilyTreeUtil::generateHiddenTreeCheckboxes($allTrees, $checkedTreeIds);
      $wgOut->addHTML("<p><b>Press the \"back button\" on your browser to choose a different file, or</b></p>");

      $wgOut->addHTML( "
	<form id='uploadwarning' method='post' enctype='multipart/form-data' action='$action'>
		<input type='hidden' name='wpIgnoreWarning' value='1' />
		<input type='hidden' name='wpSessionKey' value=\"" . htmlspecialchars( $this->mSessionKey ) . "\" />
		<input type='hidden' name='wpUploadDescription' value=\"" . htmlspecialchars( $this->mUploadDescription ) . "\" />
		<input type='hidden' name='wrDate' value=\"" . htmlspecialchars( $this->mDate ) . "\" />
		<input type='hidden' name='wrPlace' value=\"" . htmlspecialchars( $this->mPlace ) . "\" />
		$people
		$families
		$treecheckboxeshtml
		<input type='hidden' name='wrCopyright' value=\"" . htmlspecialchars( $this->mCopyright ) . "\" />
		<input type='hidden' name='wpLicense' value=\"" . htmlspecialchars( $this->mLicense ) . "\" />
		<input type='hidden' name='wpDestFile' value=\"" . htmlspecialchars( $this->mDestFile ) . "\" />
		<input type='hidden' name='wpWatchthis' value=\"" . htmlspecialchars( intval( $this->mWatchthis ) ) . "\" />
	{$copyright}
	<table border='0'>
		<tr>
			<tr>
				<td align='right'>
					<input tabindex='2' type='submit' id='wpUpload' name='wpUpload' value=\"$save\" />
				</td>
				<td align='left'>$iw</td>
			</tr>
		</tr>
	</table></form>\n" );
	}

	/**
	 * Displays the main upload form, optionally with a highlighted
	 * error message up at the top.
	 *
	 * @param string $msg as HTML
	 * @access private
	 */
	function mainUploadForm( $msg='' ) {
		global $wgOut, $wgUser;
		global $wgUseCopyrightUpload;

		$cols = intval($wgUser->getOption( 'cols' ));
		$ew = $wgUser->getOption( 'editwidth' );
		if ( $ew ) $ew = " style=\"width:100%\"";
		else $ew = '';

		if ( '' != $msg ) {
			$sub = wfMsgHtml( 'uploaderror' );
			$wgOut->addHTML( "<h2>{$sub}</h2>\n" .
			  "<span class='error'>{$msg}</span>\n" );
		}
		$wgOut->addHTML( '<div id="uploadtext">' );
		$wgOut->addWikiText( wfMsg( 'uploadtext' ) );
		$wgOut->addHTML( '</div>' );
		$sk = $wgUser->getSkin();


		$sourcefilename = wfMsgHtml( 'sourcefilename' );
		$destfilename = wfMsgHtml( 'destfilename' );
		$summary = wfMsgWikiHtml( 'fileuploadsummary' );

		$licenses = new Licenses();
		$license = wfMsgHtml( 'license' );
		$nolicense = wfMsgHtml( 'nolicense' );
		$licenseshtml = $licenses->getHtml();
// WERELATE - added licenseHelp
		$licenseHelpUrl = $sk->makeInternalOrExternalUrl( wfMsgForContent( 'licensehelppage' ));
		$licenseHelp = '<a target="helpwindow" href="'.$licenseHelpUrl.'">'.htmlspecialchars( wfMsg( 'licensehelp' ) ).'</a>';

// WERELATE - added code to select proper license
      if ($this->mLicense) {
         $protectedLicense = str_replace(array(  '\\',  '$',  '^',  '.',  '[',  ']',  '|',  '(',  ')',  '?',  '*',  '+',  '{',  '}',  '-'),
		                                   array('\\\\','\\$','\\^','\\.','\\[','\\]','\\|','\\(','\\)','\\?','\\*','\\+','\\{','\\}','\\-'), $this->mLicense);
   	   $licenseshtml = preg_replace('$value="('.$protectedLicense.')"$','value="$1" selected="selected"', $licenseshtml);
      }

		$ulb = wfMsgHtml( 'uploadbtn' );


		$titleObj = Title::makeTitle( NS_SPECIAL, 'Upload' );
// WERELATE: added target and id 
		$query = '';
		if ($this->mTarget) {
			$query = 'target=' . urlencode($this->mTarget) . '&id=' . urlencode($this->mId);
		}
		$action = $titleObj->escapeLocalURL($query);

		$encDestFile = htmlspecialchars( $this->mDestFile );
//WERELATE - added watchcreations
		$watchChecked = $wgUser->getOption( 'watchdefault' ) || $wgUser->getOption( 'watchcreations' )
			? 'checked="checked"'
			: '';
// WERELATE - add scripts
      global $wgScriptPath;
		$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/autocomplete.yui.8.js\"></script>");
		$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/image.1.js\"></script>");
// WERELATE: removed tabindexes; added id to table
		$wgOut->addHTML( "
	<form id='upload' method='post' enctype='multipart/form-data' action=\"$action\">
		<table id='image_form_table' border='0'>
		<tr>
			<td align='right'><label for='wpUploadFile'>{$sourcefilename}:</label></td>
			<td align='left'>
				<input type='file' name='wpUploadFile' id='wpUploadFile' " . ($this->mDestFile?"":"onchange='fillDestFilename()' ") . "size='40' />
			</td>
		</tr>
		<tr>
			<td align='right'><label for='wpDestFile'>{$destfilename}:</label></td>
			<td align='left'>
				<input type='text' name='wpDestFile' id='wpDestFile' size='40' value=\"$encDestFile\" />
			</td>
		</tr>");
// WERELATE - added check to omit fields in case of a re-upload, since they're ignored
		$treecheckboxeshtml = '';
		if( !$this->mReUploading ) {
         $wgOut->addHTML( "
		<tr><td>&nbsp;</td><td>&nbsp;</td></tr>
		<tr><td>&nbsp;</td><td align='left'><b>License and copyright</b></td></tr>
		<tr>");

         if ( $licenseshtml != '' ) {
			global $wgStylePath;
			$wgOut->addHTML( "
			<td align='right'><label for='wpLicense'>$license (&nbsp;$licenseHelp&nbsp;):</label></td>
			<td align='left'>
				<script type='text/javascript' src=\"$wgStylePath/common/upload.2.js\"></script>
				<select name='wpLicense' id='wpLicense' 
					onchange='licenseSelectorCheck()'>
					<option value=''>$nolicense</option>
					$licenseshtml
				</select>
			</td>
			</tr>
			<tr>
		");
		}
		
		if ( $wgUseCopyrightUpload ) {
			$filestatus = wfMsgHtml ( 'filestatus' );
			$copystatus =  htmlspecialchars( $this->mUploadCopyStatus );
			$filesource = wfMsgHtml ( 'filesource' );
			$uploadsource = htmlspecialchars( $this->mUploadSource );

			$wgOut->addHTML( "
			        <td align='right' nowrap='nowrap'><label for='wpUploadCopyStatus'>$filestatus:</label></td>
			        <td><input type='text' name='wpUploadCopyStatus' id='wpUploadCopyStatus' value=\"$copystatus\" size='40' /></td>
		        </tr>
			<tr>
		        	<td align='right'><label for='wpUploadCopyStatus'>$filesource:</label></td>
			        <td><input type='text' name='wpUploadSource' id='wpUploadCopyStatus' value=\"$uploadsource\" size='40' /></td>
			</tr>
			<tr>
		");
		}

// WERELATE: added fields
		$personTbl = $this->toForm($this->mPeople, 'person', NS_PERSON, true);
		$familyTbl = $this->toForm($this->mFamilies, 'family', NS_FAMILY, true);
		$wgOut->addHTML("
			<td align='right'><label for='wrCopyright'>Copyright holder:</label></td>
			<td align='left'>
				<input type='text' name='wrCopyright' id='wrCopyright' size='30' value=\"" . htmlspecialchars($this->mCopyright) ."\" />
			</td>
		</tr>
		<tr><td>&nbsp;</td><td>&nbsp;</td></tr>
		<tr><td>&nbsp;</td><td align='left'><b>Time place and people</b></td></tr>
		<tr>
			<td align='right'><label for='wrDate'>Image date:</label></td>
			<td align='left'>
				<input type='text' name='wrDate' id='wrDate' size='15' value=\"" . htmlspecialchars($this->mDate) ."\" />
			</td>
		</tr>
		<tr>
			<td align='right'><label for='wrPlace'>Place:</label></td>
			<td align='left'>
				<input class='place_input' type='text' name='wrPlace' id='wrDate' size='30' value=\"" . htmlspecialchars($this->mPlace) ."\" />
			</td>
		</tr>
		<tr>
			<td align='right' valign='top'>Person page:</td>
			<td align='left'>$personTbl</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td align='left'><a id='person_link' href='javascript:void(0)' onClick='addImagePage(\"person\"); return preventDefaultAction(event);'>Add another person</a></td>
		</tr>
		<tr>
			<td align='right' valign='top'>Family page:</td>
			<td align='left'>$familyTbl</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td align='left'><a id='family_link' href='javascript:void(0)' onClick='addImagePage(\"family\"); return preventDefaultAction(event);'>Add another family</a></td>
		</tr>
		");
		
// WERELATE - move description from above; end reUploading if statement; moved summary label
//            add id for wpUpload; added tree checkboxes
//!!! remove this code dependency before sharing
		require_once("extensions/familytree/FamilyTreeUtil.php");
		$t = null;
		if ($this->mDestFile) {
			$t = Title::newFromText($this->mDestFile, NS_IMAGE);
		}
		$treecheckboxeshtml = FamilyTreeUtil::generateTreeCheckboxes($wgUser, $t, true);
		$wgOut->addHtml( "
			<tr><td>&nbsp;</td><td>&nbsp;</td></tr>
			<tr><td></td><td align='left'><b>{$summary}</b></td></tr>
			<tr><td></td><td align='left'>
				<textarea name='wpUploadDescription' id='wpUploadDescription' rows='6' cols='{$cols}'{$ew}>" . htmlspecialchars( $this->mUploadDescription ) . "</textarea>
			</td>
		</tr> ");
		}
		$wgOut->addHTML( "
		<tr>
		<td></td>
		<td>
			<input type='checkbox' name='wpWatchthis' id='wpWatchthis' $watchChecked value='true' />
			<label for='wpWatchthis'>" . wfMsgHtml( 'watchthis' ) . "</label>
			<input type='checkbox' name='wpIgnoreWarning' id='wpIgnoreWarning' value='true' />
			<label for='wpIgnoreWarning'>" . wfMsgHtml('ignorewarnings' ) . "</label>" .
$treecheckboxeshtml . "
		</td>
	</tr>
	<tr>

	</tr>
	<tr>
		<td></td>
		<td align='left'><input type='submit' id='wpUpload' name='wpUpload' value=\"{$ulb}\" /></td>
	</tr>

	<tr>
		<td></td>
		<td align='left'>
		" );
		$wgOut->addWikiText( wfMsgForContent( 'edittools' ) );
		$wgOut->addHTML( "
		</td>
	</tr>

	</table>
	</form>" );
	}

	/* -------------------------------------------------------------- */

	/**
	 * Split a file into a base name and all dot-delimited 'extensions'
	 * on the end. Some web server configurations will fall back to
	 * earlier pseudo-'extensions' to determine type and execute
	 * scripts, so the blacklist needs to check them all.
	 *
	 * @return array
	 */
	function splitExtensions( $filename ) {
		$bits = explode( '.', $filename );
		$basename = array_shift( $bits );
		return array( $basename, $bits );
	}

	/**
	 * Perform case-insensitive match against a list of file extensions.
	 * Returns true if the extension is in the list.
	 *
	 * @param string $ext
	 * @param array $list
	 * @return bool
	 */
	function checkFileExtension( $ext, $list ) {
		return in_array( strtolower( $ext ), $list );
	}

	/**
	 * Perform case-insensitive match against a list of file extensions.
	 * Returns true if any of the extensions are in the list.
	 *
	 * @param array $ext
	 * @param array $list
	 * @return bool
	 */
	function checkFileExtensionList( $ext, $list ) {
		foreach( $ext as $e ) {
			if( in_array( strtolower( $e ), $list ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Verifies that it's ok to include the uploaded file
	 *
	 * @param string $tmpfile the full path of the temporary file to verify
	 * @param string $extension The filename extension that the file is to be served with
	 * @return mixed true of the file is verified, a WikiError object otherwise.
	 */
	function verify( $tmpfile, $extension ) {
		#magically determine mime type
		$magic=& wfGetMimeMagic();
		$mime= $magic->guessMimeType($tmpfile,false);

		$fname= "SpecialUpload::verify";

		#check mime type, if desired
		global $wgVerifyMimeType;
		if ($wgVerifyMimeType) {

			#check mime type against file extension
			if( !$this->verifyExtension( $mime, $extension ) ) {
				return new WikiErrorMsg( 'uploadcorrupt' );
			}

			#check mime type blacklist
			global $wgMimeTypeBlacklist;
			if( isset($wgMimeTypeBlacklist) && !is_null($wgMimeTypeBlacklist)
				&& $this->checkFileExtension( $mime, $wgMimeTypeBlacklist ) ) {
				return new WikiErrorMsg( 'badfiletype', htmlspecialchars( $mime ) );
			}
		}

		#check for htmlish code and javascript
		if( $this->detectScript ( $tmpfile, $mime, $extension ) ) {
			return new WikiErrorMsg( 'uploadscripted' );
		}

		/**
		* Scan the uploaded file for viruses
		*/
		$virus= $this->detectVirus($tmpfile);
		if ( $virus ) {
			return new WikiErrorMsg( 'uploadvirus', htmlspecialchars($virus) );
		}

		wfDebug( "$fname: all clear; passing.\n" );
		return true;
	}

	/**
	 * Checks if the mime type of the uploaded file matches the file extension.
	 *
	 * @param string $mime the mime type of the uploaded file
	 * @param string $extension The filename extension that the file is to be served with
	 * @return bool
	 */
	function verifyExtension( $mime, $extension ) {
		$fname = 'SpecialUpload::verifyExtension';

		$magic =& wfGetMimeMagic();

		if ( ! $mime || $mime == 'unknown' || $mime == 'unknown/unknown' )
			if ( ! $magic->isRecognizableExtension( $extension ) ) {
				wfDebug( "$fname: passing file with unknown detected mime type; unrecognized extension '$extension', can't verify\n" );
				return true;
			} else {
				wfDebug( "$fname: rejecting file with unknown detected mime type; recognized extension '$extension', so probably invalid file\n" );
				return false;
			}

		$match= $magic->isMatchingExtension($extension,$mime);

		if ($match===NULL) {
			wfDebug( "$fname: no file extension known for mime type $mime, passing file\n" );
			return true;
		} elseif ($match===true) {
			wfDebug( "$fname: mime type $mime matches extension $extension, passing file\n" );

			#TODO: if it's a bitmap, make sure PHP or ImageMagic resp. can handle it!
			return true;

		} else {
			wfDebug( "$fname: mime type $mime mismatches file extension $extension, rejecting file\n" );
			return false;
		}
	}

	/** Heuristig for detecting files that *could* contain JavaScript instructions or
	* things that may look like HTML to a browser and are thus
	* potentially harmful. The present implementation will produce false positives in some situations.
	*
	* @param string $file Pathname to the temporary upload file
	* @param string $mime The mime type of the file
	* @param string $extension The extension of the file
	* @return bool true if the file contains something looking like embedded scripts
	*/
	function detectScript($file, $mime, $extension) {
		global $wgAllowTitlesInSVG;

		#ugly hack: for text files, always look at the entire file.
		#For binarie field, just check the first K.

		if (strpos($mime,'text/')===0) $chunk = file_get_contents( $file );
		else {
			$fp = fopen( $file, 'rb' );
			$chunk = fread( $fp, 1024 );
			fclose( $fp );
		}

		$chunk= strtolower( $chunk );

		if (!$chunk) return false;

		#decode from UTF-16 if needed (could be used for obfuscation).
		if (substr($chunk,0,2)=="\xfe\xff") $enc= "UTF-16BE";
		elseif (substr($chunk,0,2)=="\xff\xfe") $enc= "UTF-16LE";
		else $enc= NULL;

		if ($enc) $chunk= iconv($enc,"ASCII//IGNORE",$chunk);

		$chunk= trim($chunk);

		#FIXME: convert from UTF-16 if necessarry!

		wfDebug("SpecialUpload::detectScript: checking for embedded scripts and HTML stuff\n");

		#check for HTML doctype
		if (eregi("<!DOCTYPE *X?HTML",$chunk)) return true;

		/**
		* Internet Explorer for Windows performs some really stupid file type
		* autodetection which can cause it to interpret valid image files as HTML
		* and potentially execute JavaScript, creating a cross-site scripting
		* attack vectors.
		*
		* Apple's Safari browser also performs some unsafe file type autodetection
		* which can cause legitimate files to be interpreted as HTML if the
		* web server is not correctly configured to send the right content-type
		* (or if you're really uploading plain text and octet streams!)
		*
		* Returns true if IE is likely to mistake the given file for HTML.
		* Also returns true if Safari would mistake the given file for HTML
		* when served with a generic content-type.
		*/

		$tags = array(
			'<body',
			'<head',
			'<html',   #also in safari
			'<img',
			'<pre',
			'<script', #also in safari
			'<table'
			);
		if( ! $wgAllowTitlesInSVG && $extension !== 'svg' && $mime !== 'image/svg' ) {
			$tags[] = '<title';
		}

		foreach( $tags as $tag ) {
			if( false !== strpos( $chunk, $tag ) ) {
				return true;
			}
		}

		/*
		* look for javascript
		*/

		#resolve entity-refs to look at attributes. may be harsh on big files... cache result?
		$chunk = Sanitizer::decodeCharReferences( $chunk );

		#look for script-types
		if (preg_match("!type\s*=\s*['\"]?\s*(\w*/)?(ecma|java)!sim",$chunk)) return true;

		#look for html-style script-urls
		if (preg_match("!(href|src|data)\s*=\s*['\"]?\s*(ecma|java)script:!sim",$chunk)) return true;

		#look for css-style script-urls
		if (preg_match("!url\s*\(\s*['\"]?\s*(ecma|java)script:!sim",$chunk)) return true;

		wfDebug("SpecialUpload::detectScript: no scripts found\n");
		return false;
	}

	/** Generic wrapper function for a virus scanner program.
	* This relies on the $wgAntivirus and $wgAntivirusSetup variables.
	* $wgAntivirusRequired may be used to deny upload if the scan fails.
	*
	* @param string $file Pathname to the temporary upload file
	* @return mixed false if not virus is found, NULL if the scan fails or is disabled,
	*         or a string containing feedback from the virus scanner if a virus was found.
	*         If textual feedback is missing but a virus was found, this function returns true.
	*/
	function detectVirus($file) {
		global $wgAntivirus, $wgAntivirusSetup, $wgAntivirusRequired, $wgOut;

		$fname= "SpecialUpload::detectVirus";

		if (!$wgAntivirus) { #disabled?
			wfDebug("$fname: virus scanner disabled\n");

			return NULL;
		}

		if (!$wgAntivirusSetup[$wgAntivirus]) {
			wfDebug("$fname: unknown virus scanner: $wgAntivirus\n");

			$wgOut->addHTML( "<div class='error'>Bad configuration: unknown virus scanner: <i>$wgAntivirus</i></div>\n" ); #LOCALIZE

			return "unknown antivirus: $wgAntivirus";
		}

		#look up scanner configuration
		$virus_scanner= $wgAntivirusSetup[$wgAntivirus]["command"]; #command pattern
		$virus_scanner_codes= $wgAntivirusSetup[$wgAntivirus]["codemap"]; #exit-code map
		$msg_pattern= $wgAntivirusSetup[$wgAntivirus]["messagepattern"]; #message pattern

		$scanner= $virus_scanner; #copy, so we can resolve the pattern

		if (strpos($scanner,"%f")===false) $scanner.= " ".wfEscapeShellArg($file); #simple pattern: append file to scan
		else $scanner= str_replace("%f",wfEscapeShellArg($file),$scanner); #complex pattern: replace "%f" with file to scan

		wfDebug("$fname: running virus scan: $scanner \n");

		#execute virus scanner
		$code= false;

		#NOTE: there's a 50 line workaround to make stderr redirection work on windows, too.
		#      that does not seem to be worth the pain.
		#      Ask me (Duesentrieb) about it if it's ever needed.
		if (wfIsWindows()) exec("$scanner",$output,$code);
		else exec("$scanner 2>&1",$output,$code);

		$exit_code= $code; #remeber for user feedback

		if ($virus_scanner_codes) { #map exit code to AV_xxx constants.
			if (isset($virus_scanner_codes[$code])) $code= $virus_scanner_codes[$code]; #explicite mapping
			else if (isset($virus_scanner_codes["*"])) $code= $virus_scanner_codes["*"]; #fallback mapping
		}

		if ($code===AV_SCAN_FAILED) { #scan failed (code was mapped to false by $virus_scanner_codes)
			wfDebug("$fname: failed to scan $file (code $exit_code).\n");

			if ($wgAntivirusRequired) return "scan failed (code $exit_code)";
			else return NULL;
		}
		else if ($code===AV_SCAN_ABORTED) { #scan failed because filetype is unknown (probably imune)
			wfDebug("$fname: unsupported file type $file (code $exit_code).\n");
			return NULL;
		}
		else if ($code===AV_NO_VIRUS) {
			wfDebug("$fname: file passed virus scan.\n");
			return false; #no virus found
		}
		else {
			$output= join("\n",$output);
			$output= trim($output);

			if (!$output) $output= true; #if ther's no output, return true
			else if ($msg_pattern) {
				$groups= array();
				if (preg_match($msg_pattern,$output,$groups)) {
					if ($groups[1]) $output= $groups[1];
				}
			}

			wfDebug("$fname: FOUND VIRUS! scanner feedback: $output");
			return $output;
		}
	}

	/**
	 * Check if the temporary file is MacBinary-encoded, as some uploads
	 * from Internet Explorer on Mac OS Classic and Mac OS X will be.
	 * If so, the data fork will be extracted to a second temporary file,
	 * which will then be checked for validity and either kept or discarded.
	 *
	 * @access private
	 */
	function checkMacBinary() {
		$macbin = new MacBinary( $this->mUploadTempName );
		if( $macbin->isValid() ) {
			$dataFile = tempnam( wfTempDir(), "WikiMacBinary" );
			$dataHandle = fopen( $dataFile, 'wb' );

			wfDebug( "SpecialUpload::checkMacBinary: Extracting MacBinary data fork to $dataFile\n" );
			$macbin->extractData( $dataHandle );

			$this->mUploadTempName = $dataFile;
			$this->mUploadSize = $macbin->dataForkLength();

			// We'll have to manually remove the new file if it's not kept.
			$this->mRemoveTempFile = true;
		}
		$macbin->close();
	}

	/**
	 * If we've modified the upload file we need to manually remove it
	 * on exit to clean up.
	 * @access private
	 */
	function cleanupTempFile() {
		if( $this->mRemoveTempFile && file_exists( $this->mUploadTempName ) ) {
			wfDebug( "SpecialUpload::cleanupTempFile: Removing temporary file $this->mUploadTempName\n" );
			unlink( $this->mUploadTempName );
		}
	}

	/**
	 * Check if there's an overwrite conflict and, if so, if restrictions
	 * forbid this user from performing the upload.
	 *
	 * @return mixed true on success, WikiError on failure
	 * @access private
	 */
	function checkOverwrite( $name ) {
		$img = Image::newFromName( $name );
		if( is_null( $img ) ) {
			// Uh... this shouldn't happen ;)
			// But if it does, fall through to previous behavior
			return false;
		}

		$error = '';
		if( $img->exists() ) {
			global $wgUser, $wgOut;
			if( $img->isLocal() ) {
				if( !$wgUser->isAllowed( 'reupload' ) ) {
					$error = 'fileexists-forbidden';
				}
			} else {
				if( !$wgUser->isAllowed( 'reupload' ) ||
				    !$wgUser->isAllowed( 'reupload-shared' ) ) {
					$error = "fileexists-shared-forbidden";
				}
			}
		}

		if( $error ) {
			$errorText = wfMsg( $error, wfEscapeWikiText( $img->getName() ) );
			return new WikiError( $wgOut->parse( $errorText ) );
		}

		// Rockin', go ahead and upload
		return true;
	}

}
?>
