<?php

class AjaxUtil {
	public static function getArgs($args) {
	//	global $wgContLang;
	
		$result = array();
		$args = explode('|', $args); // doesn't appear necessary: $wgContLang->recodeInput(js_unescape($args)));
		foreach ($args as $arg) {
		   $pieces = explode('=', $arg, 2);
		   if (count($pieces) == 2) {
		   	if (@$result[$pieces[0]]) {
		   		$result[$pieces[0]] .= '|' . $pieces[1];
		   	}
		   	else {
	         	$result[$pieces[0]] = $pieces[1];
		   	}
		   }
		}
		return $result;
	}
}

?>