<?php

if (!defined('MEDIAWIKI')) die();
/**
 * WeRelate
 */

$wgExtensionFunctions[] = 'wfWeRelate';

# Internationalisation file
require_once( 'WeRelate.i18n.php' );

function wfWeRelate() {
    # Add messages
    global $wgMessageCache, $wgWeRelateMessages;
    foreach( $wgWeRelateMessages as $key => $value ) {
        $wgMessageCache->addMessages( $wgWeRelateMessages[$key], $key );
    }
}
?>
