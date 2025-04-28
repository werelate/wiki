<?php
/**
 * @package MediaWiki
   @subpackage other
 */
require_once('includes/memcached-client.php');

class TimeSinceLastRequest {
	const EXP_TIME = 28800; // keep in sync with cache.ccf

	private static function prepareKey($ip, $requestType) {
		return "ip:$ip:$requestType"; 
	}

	private static function lookupCache($cache, $ip, $requestType) {
		$key = self::prepareKey($ip, $requestType);
		$result = $cache->get($key);
		if (!isset($result)) {
			$result = false;
		}
		return $result;
	}

	private static function cacheResults($cache, $ip, $requestType, $timestamp) {
  	$key = self::prepareKey($ip, $requestType);
   	$cache->set($key, $timestamp, self::EXP_TIME);
		}
   
  private static function ipRange($ip) {
    return substr($ip, 0, strrpos($ip, '.'));
  }

/**
 * Returns the number of seconds since the previous request of the same type from the same IP address
 * @param $requestType String: the type of request
 * @param $range Boolean: whether to consider a range of IP addresses (the first 3 parts) rather than the 4-part IP address
 */
  public static function getSecondsBetween($requestType, $range=true) {
    global $wgMemc; 

    $ip = wfgetIP();
    if ($range) {
      $ip = self::ipRange($ip);
    } 
    $timestamp = wfTimestampNow();
    $prevTimestamp = self::lookupCache($wgMemc, $ip, $requestType);
    self::cacheResults($wgMemc, $ip, $requestType, $timestamp); 
    
    // If check was for the 4-part IP address, cache for IP address range (first 3 parts) in case needed
    if (!$range) {
      self::cacheResults($wgMemc, self::ipRange($ip), $requestType, $timestamp);
    }         
    return $timestamp - $prevTimestamp;
  }

}
?>