<?php
require_once("commandLine.inc");

function printUsage() {
    die("Usage: <duplicates filename> <nomerges filename>\n");
}

if ($_SERVER['argc'] != 3) {
	printUsage();
}

// read nomerges
$nomerges = array();
$filename = $_SERVER['argv'][2];
$handle = @fopen($filename, "rb");
if ($handle) {
	while (!feof($handle)) {
   	$buffer = chop(fgets($handle));
   	$nomerges[str_replace( ' ', '_', $buffer)] = 1;
	}
	fclose($handle);
}
else {
	echo "File not found: $filename\n";
	exit(1);
}

// read duplicates
$filename = $_SERVER['argv'][1];
$handle = @fopen($filename, "rb");
if ($handle) {
	$db =& wfGetDB( DB_MASTER );
	echo "Removing old duplicates\n";
	$db->delete('duplicates', '*', 'UpdateDuplicates');
	$db->begin();
	$cnt = 0;
  	while (!feof($handle)) {
   	$buffer = chop(fgets($handle));
   	$fields = explode('|',$buffer);
   	if (count($fields) > 1) {
   		$ns = $fields[0];
   		if ($ns == 'Person') {
   			$nsId = 108;
   		}
   		else {
   			$nsId = 110;
   		}
   		$titles = array();
   		for ($i = 1; $i < count($fields); $i++) {
   			$title = str_replace( ' ', '_', $fields[$i]);
   			$titles[] = $title;
   		}
   		for ($i = 0; $i < count($titles); $i++) {
   			$temp = array();
   			for ($j = 0; $j < count($titles); $j++) {
   				if ($j != $i && !@$nomerges["$ns:{$titles[$i]}|$ns:{$titles[$j]}"]) {
   					$temp[] = $titles[$j];
   				}
   			}
   			$matchingTitles = '';
   			if (count($temp) <= 10) {
   				$matchingTitles = join('|',$temp);
   				if (mb_strlen($matchingTitles) > 1024) {
   					$matchingTitles = ''; // too long
   				}
   			}
   			if ($matchingTitles) {
   				$db->insert('duplicates', array('dp_namespace' => $nsId, 'dp_title' => $titles[$i], 'dp_match_titles' => $matchingTitles), 'UpdateDuplicates', array('ignore'));
   			}
   		}
   	}
   	if (++$cnt % 500 == 0) {
   		$db->commit();
   		$db->begin();
   	}
  	}
  	$db->commit();
  	fclose($handle);
	echo "New duplicates added\n";
}
else {
	echo "File not found: $filename\n";
	exit(1);
}
?>
