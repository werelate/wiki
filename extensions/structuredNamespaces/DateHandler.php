<?php

// Created Oct 2020, refactored to call Java Apr 2024 by Janet Bjorndahl
abstract class DateHandler {

  // Variables set when parsing and/or editing the date
  private static $yearRange, $yearOnly, $effectiveYear, $effectiveStartYear, $dateSortKey, $dateStringKey, $isoDate;

  // Some functions require the date to be edited, while others only require it to be parsed.

   /**
    * Return the formated date in the user's preferred language, or the original date if editing failed.
    * @param string $date
    * @param string $eventType (optional)
    */
  public static function formatDate($date, $eventType) {
    $formatedDate = $languageDate = '';
    if ( self::editDate($date, $formatedDate, $languageDate, $eventType) === true ) {
      return $languageDate;
    }
    else {
      return $date;
    }
  }

   /**
    * Return the formated date in English, or the original date if editing failed.
    * @param string $date
    * @param string $eventType (optional)
    */
  public static function formatDateEnglish($date, $eventType) {
    $formatedDate = $languageDate = '';
    if ( self::editDate($date, $formatedDate, $languageDate, $eventType) === true ) {
      return $formatedDate;
    }
    else {
      return $date;
    }
  }
  
   /** 
    * Return the year of a date. If the year is a split year (double-dating), return it as such.
    * @param string $date
    * @param string $eventType (optional)
    * @param boolean $includeModifiers:
    *    if true, returns the year or year range, with modifiers
    *    if false, returns the end year in the date, without modifiers
    */
	public static function getYear($date, $eventType, $includeModifiers=false) {
    $year = null;
    if ( $includeModifiers ) {
      $formatedDate = $languageDate = '';
      self::editDate($date, $formatedDate, $languageDate, $eventType);
      $year = self::$yearRange;
    }
    else {
      self::parseDate($date);
      $year = self::$yearOnly; 
    }
    if ( isset($year) ) {
      return $year;
    }
    else {
      return false;
    }
  }

   /*
    * Return the end year of the date. If it is a split year, return the effective year (e.g., 1624 for 1623/24).
    * @param string $date
    */
  public static function getEffectiveYear($date) {
    self::parseDate($date);
    if ( isset(self::$effectiveYear) ) {
      return self::$effectiveYear;
    }
    else {
      return false;
    }
  }

   /*
    * Return the start year of the date. If it is a split year, return the effective year (e.g., 1624 for 1623/24).
    * @param string $date
    */
  public static function getEffectiveStartYear($date) {
    self::parseDate($date);
    if ( isset(self::$effectiveStartYear) ) {
      return self::$effectiveStartYear;
    }
    else {
      return false;
    }
  }
  
   /**
    * Return the date (start date if a date range) as yyyymmdd for sorting or for other purposes requiring this format.
    * @param string $date
    * @param boolean getNumericKey:
    *    if true, returns a numeric key that has 00 placeholders for missing month/day data and is adjusted based on bef/aft/bet/from/to modifiers
    *    if false, returns a string that ignores missing month/day data and is NOT adjusted for date modifiers
    */
  public static function getDateKey($date, $getNumericKey=false) {
    self::parseDate($date);
    if ( $getNumericKey ) {
      return self::$dateSortKey;
    }
    else {
      return self::$dateStringKey;
    }
  }

   /**
    * Return the date (start date if a date range) as yyyy-mm-dd
    * @param string $date
    * @return string
    */
   public static function getIsoDate($date) {
    self::parseDate($date);
    return self::$isoDate;
   }

   /**
    * Parse and edit the date (calling Java code) and set values that depend on the edited date.
    * Construct the date in the user's preferred language (not done in Java).
    * @param string $date
    * @param string $formatedDate is returned
    * @param string $languageDate is returned
    * @param string $eventType (optional)
    * @param boolean $reformatDetails
    *    if true and the date passed edit rules but significant reformating was required, return a message to indicate significant reformating
    *    if false, return the error message from the edit if it failed, or success status if the edit passed
    */
  public static function editDate($date, &$formatedDate, &$languageDate, $eventType, $reformatDetails=false) {    // reformatDetails added Mar 2021 by Janet Bjorndahl
    global $wrSearchHost, $wrSearchPort, $wrSearchPath;

    $parsedDate = array();
    $formatedDate = '';
    $languageDate = '';

    $query = "http://$wrSearchHost:$wrSearchPort$wrSearchPath/eventdate?edit=yes&date=" . urlencode($date) . "&type=" . urlencode($eventType) . "&wt=php";
    if ( file_get_contents($query) ) {
      eval('$response = ' . file_get_contents($query) . ';');
      $formatedDate = $response['formatedDate'];
      self::$yearRange = $response['yearRange'];
      $parsedDate['year'] = $response['parsedDate'][0];
      $parsedDate['month'] = $response['parsedDate'][1];
      $parsedDate['day'] = $response['parsedDate'][2];
      $parsedDate['modifier'] = $response['parsedDate'][3];
      $parsedDate['suffix'] = $response['parsedDate'][4];
      $parsedDate['text'] = $response['parsedDate'][5];

      // Construct the date in the user's preferred language
      // This leverages translations available in the wiki and thus is done here rather than in Java
      for ($i=1; $i>=0; $i--) {
        if ( isset($parsedDate['modifier'][$i]) ) {
          // Do this until modifiers in another language are implemented (if ever). Otherwise, Bet/and shows up as Bet and the translation of "and".
          $languageModifier = $parsedDate['modifier'][$i];  
//        $languageModifier = wfMsg(strtolower($parsedDate['modifier'][$i]));
//        if ( substr($languageModifier,0,4) == '&lt;' || substr($languageModifier,0,1) == '<' ) {  // modifier not found in user's preferred language - use English
//          $languageModifier = $parsedDate['modifier'][$i];
//        }                      
        }

        if ( isset($parsedDate['month'][$i]) ) {
          $languageMonth = wfMsg(strtolower($parsedDate['month'][$i]));
          if ( substr($languageMonth,0,4) == '&lt;' || substr($languageMonth,0,1) == '<' ) {  // month not found in user's preferred language - use English
            $languageMonth = $parsedDate['month'][$i];
          }
        }
        $languageDate .= ($languageDate ? ' ' : '') . 
                         (isset($parsedDate['modifier'][$i]) ? $languageModifier . ' ' : '') . 
                         (isset($parsedDate['day'][$i]) ? $parsedDate['day'][$i] . ' ' : '') . 
                         (isset($parsedDate['month'][$i]) ? $languageMonth . ' ' : '') . 
                         (isset($parsedDate['year'][$i]) ? $parsedDate['year'][$i] : '') .
                         (isset($parsedDate['suffix'][$i]) ? ' ' . $parsedDate['suffix'][$i] : '');
      }
      if ( isset($parsedDate['text']) ) {
        $languageDate .= ($languageDate == '' ? $parsedDate['text'] : ' ' . $parsedDate['text']);
      }
      
      // If there is an error message or the calling function asked for an indication of significant reformating and that happened, return the message.
      // Otherwise, indicate that the date passed the edit checks.
      if ($response['errorMessage'] != null) {
        return $response['errorMessage'];
      }
      if ($reformatDetails && $response['significantReformat'] == true) {
        return 'Significant reformat';
      }
      return true;
    }  
    // Ignore editing (and assume success) if there was no response.
    return true;
  }

   /**
    * Parse the date (calling Java code) and set values that depend only on parsing (not editing).
    * @param string $date
    */  
  // This function calls Java to parse the date and get values that depend on the parsed date (but don't require editing the date).
  private static function parseDate($date) {
    global $wrSearchHost, $wrSearchPort, $wrSearchPath;
    
    $query = "http://$wrSearchHost:$wrSearchPort$wrSearchPath/eventdate?edit=no&date=" . urlencode($date) . "&wt=php";
    if ( file_get_contents($query) ) {
      eval('$response = ' . file_get_contents($query) . ';');
      self::$yearOnly = $response['yearOnly'];
      self::$effectiveYear = $response['effectiveYear'];
      self::$effectiveStartYear = $response['effectiveStartYear'];
      self::$dateSortKey = $response['dateSortKey'];
      self::$dateStringKey = $response['dateStringKey'];
      self::$isoDate = $response['isoDate'];
      return true;
    }
    else { 
      return false;
    }
  }

}
?>
