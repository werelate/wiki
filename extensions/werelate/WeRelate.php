
<?php
if (!defined('MEDIAWIKI')) die();
/**
 * FamilyTree
 */

$wgExtensionFunctions[] = 'wfWeRelateMessages';

# Internationalisation file
require_once( 'WeRelate.i18n.php' );

function wfWeRelateMessages() {
    # Add messages
    global $wgMessageCache, $wgWeRelateMessages;
    //error_log('1'.$wgWeRelateMessages);
    foreach( $wgWeRelateMessages as $key => $value ) {
        $wgMessageCache->addMessages( $wgWeRelateMessages[$key], $key );
    }
}

?>
