<?php
if (!defined('MEDIAWIKI')) die();
/**
 * FamilyTree
 */

$wgExtensionFunctions[] = 'wfFamilyTree';

# Internationalisation file
require_once( 'FamilyTree.i18n.php' );

function wfFamilyTree() {
	# Add messages
	global $wgMessageCache, $wgFamilyTreeMessages;
	foreach( $wgFamilyTreeMessages as $key => $value ) {
		$wgMessageCache->addMessages( $wgFamilyTreeMessages[$key], $key );
	}
}
?>
