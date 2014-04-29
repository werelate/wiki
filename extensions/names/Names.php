<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Taylor
 * Date: 3/1/14
 * Time: 8:19 AM
 * To change this template use File | Settings | File Templates.
 */

if (!defined('MEDIAWIKI')) die();
/**
 * FamilyTree
 */

$wgExtensionFunctions[] = 'wfNames';

# Internationalisation file
require_once( 'Names.i18n.php' );

function wfNames() {
    # Add messages
    global $wgMessageCache, $wgNamesMessages;
    foreach( $wgNamesMessages as $key => $value ) {
        $wgMessageCache->addMessages( $wgNamesMessages[$key], $key );
    }
}

?>