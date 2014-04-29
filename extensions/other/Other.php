<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Taylor
 * Date: 3/1/14
 * Time: 8:40 AM
 * To change this template use File | Settings | File Templates.
 */

$wgExtensionFunctions[] = 'wfOther';

# Internationalisation file
require_once( 'Other.i18n.php' );

function wfOther() {
    # Add messages
    global $wgMessageCache, $wgOtherMessages;
    foreach( $wgOtherMessages as $key => $value ) {
        $wgMessageCache->addMessages( $wgOtherMessages[$key], $key );
    }
}

?>