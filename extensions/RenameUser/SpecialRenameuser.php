<?php
if (!defined('MEDIAWIKI')) die();
/**
 * A Special Page extension to rename users, runnable by users with renameuser
 * righs
 *
 * @addtogroup Extensions
 *
 * @author Ævar Arnfjörð Bjarmason <avarab@gmail.com>
 * @copyright Copyright © 2005, Ævar Arnfjörð Bjarmason
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$wgAvailableRights[] = 'renameuser';
$wgGroupPermissions['bureaucrat']['renameuser'] = true;

$wgExtensionFunctions[] = 'wfSpecialRenameuser';
$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Renameuser',
	'author' => 'Ævar Arnfjörð Bjarmason',
	'url' => 'http://meta.wikimedia.org/wiki/Renameuser',
	'description' => 'Rename a user (need \'\'renameuser\'\' right)',
);

# Internationalisation file
require_once( 'SpecialRenameuser.i18n.php' );

/**
 * The maximum number of edits a user can have and still be allowed renaming,
 * set it to 0 to disable the limit.
 */
//define( 'RENAMEUSER_CONTRIBLIMIT', 6800 );
define( 'RENAMEUSER_CONTRIBLIMIT', 200000 );

# Add a new log type
global $wgLogTypes, $wgLogNames, $wgLogHeaders, $wgLogActions;
$wgLogTypes[]                          = 'renameuser';
$wgLogNames['renameuser']              = 'renameuserlogpage';
$wgLogHeaders['renameuser']            = 'renameuserlogpagetext';
$wgLogActions['renameuser/renameuser'] = 'renameuserlogentry';

/**
 * If this is set to true, then the archive table (deleted revisions) will
 * not be updated. Defaults to the value of $wgMiserMode, since if that's on,
 * then it's probably desirable to have this switched on too.
 */
$wgRenameUserQuick = $wgMiserMode;

# Register the special page
if ( !function_exists( 'extAddSpecialPage' ) ) {
	require( dirname(__FILE__) . '/../ExtensionFunctions.php' );
}
extAddSpecialPage( dirname(__FILE__) . '/SpecialRenameuser_body.php', 'Renameuser', 'Renameuser' );

function wfSpecialRenameuser() {
	# Add messages
	global $wgMessageCache, $wgRenameuserMessages;
	foreach( $wgRenameuserMessages as $key => $value ) {
		$wgMessageCache->addMessages( $wgRenameuserMessages[$key], $key );
	}
}
?>
