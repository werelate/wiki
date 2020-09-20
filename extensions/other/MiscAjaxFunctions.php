<?php

if( !defined( 'MEDIAWIKI' ) )
        die( 1 );

require_once('GlobalFunctions.php');
require_once('AjaxFunctions.php');

# Register with AjaxDispatcher as a function
$wgAjaxExportList[] = "wfStoreSource";
$wgAjaxExportList[] = "wfRetrieveSource";

/* wfStoreSource and wfRetrieveSource added Sep 2020 by Janet Bjorndahl,
 * to persist and then retrieve source citation data across multiple windows/tabs
 */

// Source data is received and stored as JSON, so that any new/changed fields can be handled in the Javascript
function wfStoreSource() {
  global $wgRequest;

  $_SESSION['copiedSource'] = trim($wgRequest->getVal('source'),'"');
}

function wfRetrieveSource() {
  global $wgRequest;

  if ( isset($_SESSION['copiedSource']) ) {
    $json = $_SESSION['copiedSource'];
    $callback = $wgRequest->getVal('callback');
    if ($callback) {
      $json = $callback.'('.$json.');';
    }
  }
  else {
    $json = '';
  }    
  return $json;
}

?>