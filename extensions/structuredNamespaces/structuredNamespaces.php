<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Taylor
 * Date: 3/1/14
 * Time: 5:47 PM
 * To change this template use File | Settings | File Templates.
 */

$wgExtensionFunctions[] = 'wfStructuredNamespaces';

# Internationalisation file
require_once('StructuredNamespaces.i18n.php');

function wfStructuredNamespaces() {
    # Add messages
    global $wgMessageCache, $wgStructuredNamespacesMessages;
    foreach( $wgStructuredNamespacesMessages as $key => $value ) {
        $wgMessageCache->addMessages( $wgStructuredNamespacesMessages[$key], $key );
    }
}

?>