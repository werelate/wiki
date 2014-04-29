<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Taylor
 * Date: 3/1/14
 * Time: 7:58 AM
 * To change this template use File | Settings | File Templates.
 */

if (!defined('MEDIAWIKI')) die();
/**
 * FamilyTree
 */

$wgExtensionFunctions[] = 'wfGedcom';

# Internationalisation file
require_once( 'Gedcom.i18n.php' );

function wfGedcom() {
    # Add messages
    global $wgMessageCache, $wgGedcomMessages;
    foreach( $wgGedcomMessages as $key => $value ) {
        $wgMessageCache->addMessages( $wgGedcomMessages[$key], $key );
    }
}

?>