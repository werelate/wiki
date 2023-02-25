<?php

class PropagationManager {
   private static $propagatedActions;
   private static $blacklistTitles;
   private static $whitelistTitles;
   private static $isPropagationEnabled = true;
   
   public static function reset() {
   	PropagationManager::$propagatedActions = null;
   	PropagationManager::$blacklistTitles = null;
   	PropagationManager::$whitelistTitles = null;
   	PropagationManager::$isPropagationEnabled = true;
   }
   
   public static function isPropagationEnabled() {
   	return PropagationManager::$isPropagationEnabled;
   }
   
   public static function enablePropagation($enabled) {
   	PropagationManager::$isPropagationEnabled = $enabled;
   }
   
   private static function getActionKey($pageTitle, $action, $objectPageTitle) {
   	if (!is_array(PropagationManager::$propagatedActions)) PropagationManager::$propagatedActions = array();
   	return $pageTitle->getPrefixedText().'|'.$action.'|'.$objectPageTitle->getPrefixedText();
   }
   
   // action is propagatable if it is not found in the propagatedActions array
   public static function isPropagatableAction($pageTitle, $action, $objectPageTitle) {
    if (!$pageTitle || !$objectPageTitle) {
 	    error_log("Null title");
        return false;
    }
   	$key = PropagationManager::getActionKey($pageTitle, $action, $objectPageTitle);
   	$result = @!PropagationManager::$propagatedActions[$key];
   	return $result;
   }
   
   public static function addPropagatedAction($pageTitle, $action, $objectPageTitle) {
    if (!$pageTitle || !$objectPageTitle) {
 	    error_log("Null title");
        return;
    }
   	$key = PropagationManager::getActionKey($pageTitle, $action, $objectPageTitle);
   	if (@PropagationManager::$propagatedActions[$key]) {
   		error_log("Already propagated: $key");
   	}
   	PropagationManager::$propagatedActions[$key] = 1;
   }
   
   private static function getPageKey($pageTitle) {
   	return ($pageTitle ? $pageTitle->getPrefixedText() : '');
   }
   
   public static function isPropagatablePage($pageTitle) {
   	$key = PropagationManager::getPageKey($pageTitle);
   	return ($key &&
   			  (!is_array(PropagationManager::$whitelistTitles) || @PropagationManager::$whitelistTitles[$key]) &&
   			  (!is_array(PropagationManager::$blacklistTitles) || !@PropagationManager::$blacklistTitles[$key]));
   }
   
   public static function addBlacklistPage($pageTitle) {
   	$key = PropagationManager::getPageKey($pageTitle);
   	if ($key) {
	   	if (!is_array(PropagationManager::$blacklistTitles)) PropagationManager::$blacklistTitles = array();
	   	PropagationManager::$blacklistTitles[$key] = 1;
   	}
   	else {
   		error_log("addBlacklistPage bad title");
   	}
   }
   
   public static function setWhitelist() {
   	if (!is_array(PropagationManager::$whitelistTitles)) PropagationManager::$whitelistTitles = array();
   }

   public static function addWhitelistPage($pageTitle) {
   	$key = PropagationManager::getPageKey($pageTitle);
   	if ($key) {
	   	if (!is_array(PropagationManager::$whitelistTitles)) PropagationManager::$whitelistTitles = array();
	   	PropagationManager::$whitelistTitles[$key] = 1;
   	}
   	else {
   		error_log("addWhitelistPage bad title");
   	}
   }
}
?>
