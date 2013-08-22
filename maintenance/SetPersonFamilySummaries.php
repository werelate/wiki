<?php
require_once("commandLine.inc");
require_once("$IP/extensions/structuredNamespaces/Person.php");
require_once("$IP/extensions/structuredNamespaces/Family.php");

function printUsage() {
   die("Usage: <extract filename>\n");
}

if ($_SERVER['argc'] != 2) {
   printUsage();
}

// read duplicates
$filename = $_SERVER['argv'][1];
$handle = @fopen($filename, "rb");
if ($handle) {
   $db =& wfGetDB( DB_MASTER );
   $db->begin();
   $cnt = 0;

   while (!feof($handle)) {
      $buffer = chop(fgets($handle));
      if ($buffer) {
         $fields = explode('|',$buffer, 2);
         echo $fields[0].'   :   '.$fields[1];
         $title = Title::newFromText($fields[0]);
         $xml = simplexml_load_string($fields[1]);
         if ($title->getNamespace() == NS_PERSON) {
            $summary = Person::getSummary($xml, $title);
         }
         else if ($title->getNamespace() == NS_FAMILY) {
            $summary = Family::getSummary($xml, $title);
         }
         else {
            $summary = '';
         }

         if ($summary) {
            $db->update( 'watchlist',
               array( /* SET */
                  'wr_summary' => $summary
               ), array( /* WHERE */
                  'wl_namespace' => $title->getNamespace(),
                  'wl_title' => $title->getDBkey(),
                  'wr_summary' => ''
               ), 'updateWatchlistSummary'
            );
         }
         if (++$cnt % 500 == 0) {
            echo '.';
            $db->commit();
            $db->begin();
         }
      }
   }
   $db->commit();
   fclose($handle);
   echo "Watchlists added\n";
}
else {
   echo "File not found: $filename\n";
   exit(1);
}
?>
