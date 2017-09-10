<?php
/**
 * @package MediaWiki
 * @subpackage StructuredNamespaces
 */
require_once('includes/memcached-client.php');
require_once('extensions/other/AutoCompleter.php');
require_once('AdminSettings.php');

function wfProfileIn($dummy) {} // ignore
function wfProfileOut($dummy) {} // ignore

$userid = @$_GET['userid'];
$nsDefault = @$_GET['ns'];
$title = @$_GET['title'];
header('Content-type: text/xml');

// connect to memcached
// don't change these unless you change them in ObjectCache
$mc = new MWmemcached(array('persistant' => true, 'compress_threshold' => 1500));
$mc->set_servers($wgMemCachedServers);

// connect to database
$conn = @mysql_connect($wgDBhost,$wgDBadminuser,$wgDBadminpassword) OR die(AutoCompleter::formatError(mysql_error()));
@mysql_select_db('wikidb', $conn) OR die(AutoCompleter::formatError(mysql_error()));

// print the results
print AutoCompleter::getResults($mc, $conn, $title, $userid, $nsDefault);

@mysql_close($conn);
?>
