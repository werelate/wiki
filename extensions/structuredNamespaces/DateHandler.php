<?php

// Created Oct 2020 (and modified Nov 2020) by Janet Bjorndahl
abstract class DateHandler {

  private static $GEDCOM_MONTHS = array('jan'=>'Jan','feb'=>'Feb','mar'=>'Mar','apr'=>'Apr','may'=>'May','jun'=>'Jun',
                                        'jul'=>'Jul','aug'=>'Aug','sep'=>'Sep','oct'=>'Oct','nov'=>'Nov','dec'=>'Dec',
                                        'january'=>'Jan','february'=>'Feb','march'=>'Mar','april'=>'Apr','may'=>'May','june'=>'Jun',
                                        'july'=>'Jul','august'=>'Aug','september'=>'Sep','october'=>'Oct','november'=>'Nov','december'=>'Dec',
                                        'febr'=>'Feb','sept'=>'Sep',
                                        // additional for Dutch
                                        'mrt'=>'Mar','mei'=>'May','okt'=>'Oct',
                                        'januari'=>'Jan','februari'=>'Feb','maart'=>'Mar','juni'=>'Jun','juli'=>'Jul','augustus'=>'Aug','oktober'=>'Oct',
                                        // additional for French (accented and unaccented)
                                        'fév'=>'Feb','avr'=>'Apr','mai'=>'May','aoû'=>'Aug','déc'=>'Dec',
                                        'fev'=>'Feb','aou'=>'Aug',
                                        'janvier'=>'Jan','février'=>'Feb','mars'=>'Mar','avril'=>'Apr','juin'=>'Jun',
                                        'juillet'=>'Jul','août'=>'Aug','septembre'=>'Sep','octobre'=>'Oct','novembre'=>'Nov','décembre'=>'Dec',
                                        'fevrier'=>'Feb','aout'=>'Aug','decembre'=>'Dec',
                                        // additional for German (accented and unaccented)
                                        'mär'=>'Mar','mai'=>'May','okt'=>'Oct','dez'=>'Dec',
                                        'januar'=>'Jan','februar'=>'Feb','märz'=>'Mar','juni'=>'Jun','juli'=>'Jul','oktober'=>'Oct','dezember'=>'Dec',
                                        'marz'=>'Mar',
                                        // additional for Spanish
                                        'ene'=>'Jan','abr'=>'Apr','ago'=>'Aug','dic'=>'Dec',
                                        'enero'=>'Jan','febrero'=>'Feb','marzo'=>'Mar','abril'=>'Apr','mayo'=>'May','junio'=>'Jun',
                                        'julio'=>'Jul','agosto'=>'Aug','septiembre'=>'Sep','octubre'=>'Oct','noviembre'=>'Nov','diciembre'=>'Dec'
                                        );
                                        
  private static $MONTHS = array('jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec');                                        

  // Expanded Mar 2021 by Janet Bjorndahl
  private static $GEDCOM_MODIFIERS = array('abt'=>'Abt','cal'=>'Cal','est'=>'Est','bef'=>'Bef','aft'=>'Aft','from'=>'From','to'=>'to','bet'=>'Bet','and'=>'and','int'=>'Int',
                                           'about'=>'Abt','approx'=>'Abt','approximately'=>'Abt','calculated'=>'Cal','calc'=>'Cal','calcd'=>'Cal',
                                           'estd'=>'Est','estimated'=>'Est','c'=>'Est','ca'=>'Est','circa'=>'Est','cir'=>'Est','say'=>'Est',
                                           'before'=>'Bef','bfr'=>'Bef','by'=>'Bef','after'=>'Aft','frm'=>'From','until'=>'to','btw'=>'Bet','between'=>'Bet','&'=>'and','interpreted'=>'Int',
                                           // additional for other languages (as found in the data); more could be added on request
                                           'vers'=>'Abt','omstreeks'=>'Abt','omstr'=>'Abt','omkring'=>'Abt','omk'=>'Abt',
                                           'ansl'=>'Est','anslat'=>'Est','voor'=>'Bef','vóór'=>'Bef','før'=>'Bef','avant'=>'Bef',
                                           'na'=>'Aft','ett'=>'Aft','etter'=>'Aft','van'=>'From','tot'=>'to');
                                           
  private static $ORDINAL_SUFFIXES = array('st', 'nd', 'rd', 'th');            // added Feb 2021 by Janet Bjorndahl                               

  public static function formatDate($date) {
    $formatedDate = $languageDate = '';
    if ( self::editDate($date, $formatedDate, $languageDate) === true ) {
      return $languageDate;
    }
    else {
      return $date;
    }
  }

  public static function formatDateEnglish($date) {
    $formatedDate = $languageDate = '';
    if ( self::editDate($date, $formatedDate, $languageDate) === true ) {
      return $formatedDate;
    }
    else {
      return $date;
    }
  }

  public static function editDate($date, &$formatedDate, &$languageDate, $discreteEvent=false, $reformatDetails=false) {    // reformatDetails added Mar 2021 by Janet Bjorndahl
    $parsedDate = array();
    $formatedDate = '';
    $languageDate = '';
    
    $parsedDate=self::parseDate($date);
    
    // Check for some overall errors and do a few fixes before dealing with each part of the parsed date
    if ( isset($parsedDate['message']) ) {                             // if parsing was unsuccessful, return the message
      return $parsedDate['message'];
    }
    
    if ( isset($parsedDate['modifier'][2]) || isset($parsedDate['year'][2]) || isset($parsedDate['month'][2]) || isset($parsedDate['day'][2]) || isset($parsedDate['suffix'][2]) ) {
      return 'Too many parts';                                         // error if more than 2 modifiers/dates
    }

    // Error if a pair of modifiers or a pair of dates does not use modifiers Bet/and or From/to (enhanced Mar 2021 by Janet Bjorndahl)
    if ( isset($parsedDate['modifier'][1]) || isset($parsedDate['year'][1]) ) {   
      if ( !isset($parsedDate['modifier'][1]) ||  
           ! (($parsedDate['modifier'][1] === 'Bet' && $parsedDate['modifier'][0] === 'and') || ($parsedDate['modifier'][1] === 'From' && $parsedDate['modifier'][0] === 'to')) ) {
        return 'Invalid combination of modifiers';  
      }
      if ( $discreteEvent && $parsedDate['modifier'][1] == 'From' ) {  // if this is a discrete event, change From/to (misused) to Bet/and
        $parsedDate['modifier'][1] = 'Bet';
        $parsedDate['modifier'][0] = 'and';
      }
    }
    else {
      if ( isset($parsedDate['modifier'][0]) ) {
        if ( $parsedDate['modifier'][0] === 'to' ) {                   // "to" can be used on its own - if so, capitalize
          $parsedDate['modifier'][0] = 'To';
        }
        if ( $parsedDate['modifier'][0] === 'Bet' || $parsedDate['modifier'][0] === 'and') { // error if "Bet" or "and" used on their own
          return 'Incorrect usage of bet/and';
        }
      }
    }

    // Edit and format each part of the parsed date
    for ($i=1; $i>=0; $i--) {
      if ( isset($parsedDate['modifier'][$i]) || isset($parsedDate['year'][$i]) || isset($parsedDate['month'][$i]) || isset($parsedDate['day'][$i]) || isset($parsedDate['suffix'][$i]) ) {
        // error if no year, or if day without month
        if ( !isset($parsedDate['year'][$i]) || (isset($parsedDate['day'][$i]) && !isset($parsedDate['month'][$i])) ) {
          $formatedDate = '';
          $languageDate = '';
          return 'Incomplete date';
        }
        // error if day does not match month (leap day not checked)
        if ( isset($parsedDate['day'][$i]) && (($parsedDate['day'][$i] > 29 && $parsedDate['month'][$i] == 'Feb') || 
             ($parsedDate['day'][$i] > 30 && ($parsedDate['month'][$i] == 'Apr' || $parsedDate['month'][$i] == 'Jun' ||
              $parsedDate['month'][$i] == 'Sep' || $parsedDate['month'][$i] == 'Nov'))) ) {
          $formatedDate = '';
          $languageDate = '';
          return 'Invalid day for ' . $parsedDate['month'][$i];
        }
        // error if split year for a month after Mar
        if ( strpos($parsedDate['year'][$i],'/') && isset($parsedDate['month'][$i]) && 
             $parsedDate['month'][$i] !== 'Jan' && $parsedDate['month'][$i] !== 'Feb' && $parsedDate['month'][$i] !== 'Mar' ) {
          $formatedDate = '';
          $languageDate = '';
          return 'Split year valid only for Jan-Mar';
        } 
        $formatedDate .= ($formatedDate ? ' ' : '') . 
                         (isset($parsedDate['modifier'][$i]) ? $parsedDate['modifier'][$i] . ' ' : '') . 
                         (isset($parsedDate['day'][$i]) ? $parsedDate['day'][$i] . ' ' : '') . 
                         (isset($parsedDate['month'][$i]) ? $parsedDate['month'][$i] . ' ' : '') . 
                         (isset($parsedDate['year'][$i]) ? $parsedDate['year'][$i] : '') .
                         (isset($parsedDate['suffix'][$i]) ? ' ' . $parsedDate['suffix'][$i] : '');
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
    }
    
    // Check for invalid date range (added Feb 2021 by Janet Bjorndahl, switched to effyear Mar 2021 by Janet Bjorndahl)
    if ( isset($parsedDate['effyear'][1]) ) {
      if ( isset($parsedDate['month'][0]) ) {
        $secondMonthNum = self::getMonthNumber($parsedDate['month'][0]);
      }
      if ( isset($parsedDate['month'][1]) ) {
        $firstMonthNum = self::getMonthNumber($parsedDate['month'][1]);
      }
      if ( $parsedDate['effyear'][1] > $parsedDate['effyear'][0] ||
           ($parsedDate['effyear'][1] == $parsedDate['effyear'][0] && (!isset($firstMonthNum) || !isset($secondMonthNum) || $firstMonthNum > $secondMonthNum)) ||
           ($parsedDate['effyear'][1] == $parsedDate['effyear'][0] && $firstMonthNum == $secondMonthNum && 
               (!isset($parsedDate['day'][1]) || !isset($parsedDate['day'][0]) || $parsedDate['day'][1] >= $parsedDate['day'][0])) ) {
        $formatedDate = '';
        $languageDate = '';
        return 'Invalid date range';
      }  
    }

    if ( isset($parsedDate['text']) ) {
      $formatedDate .= ($formatedDate == '' ? $parsedDate['text'] : ' ' . $parsedDate['text']);
      $languageDate .= ($languageDate == '' ? $parsedDate['text'] : ' ' . $parsedDate['text']);
    }

    // If reformating details requested and there are any, return that information (added Mar 2021 by Janet Bjorndahl)
    if ( $reformatDetails && isset($parsedDate['reformat']) ) {
      return $parsedDate['reformat'];
    }
    return true;
  }

   /**
    * Return a date as yyyy-mm-dd
    * @param $date in yyyymmdd format, from getDateKey with getNumericKey=false
    * @return string
    */
   public static function getIsoDate($date) {
      if (strlen($date) >= 4) {
         $result = substr($date, 0, 4);
         if (strlen($date) >= 6) {
            $result .= '-'.substr($date, 4, 2);
            if (strlen($date) == 8) {
               $result .= '-'.substr($date, 6, 2);
            }
         }
      }
      else {
         $result = $date;
      }
      return $result;
   }

   /**
    * Return the date as yyyymmdd for sorting or for other purposes requiring this format
    * @param string $date
    * @param boolean getNumericKey:
    *    if true, returns a numeric key that has 00 placeholders for missing month/day data and is adjusted based on bef/aft/bet/from/to modifiers
    *    if false, returns a string that ignores missing month/day data and is NOT adjusted for date modifiers
    * If the date is a date range, the start date is used.
    */
  public static function getDateKey($date, $getNumericKey=false) {
    $pd = array();
    $fields = array();
    $result = '';
    
    $pd=self::parseDate($date);
    
    // Use start date if a date range (only date if not). If no year, nothing to return.
    if ( isset($pd['effyear'][1]) ) {
      $i = 1;
    } 
    else {
      if ( isset($pd['effyear'][0]) ) {
        $i = 0;
      }
      else {
        return $result;
      }
    }
    
    if ( isset($pd['month'][$i]) ) {
      $monthNum = self::getMonthNumber($pd['month'][$i]);
    }
    
    // Note that when returning a string, BC dates are not handled since the context might not be able to handle them. An empty string is returned instead.
    if ( !$getNumericKey ) {
      if ( isset($pd['suffix'][$i]) && $pd['suffix'][$i] === 'BC' ) {
        return $result;
      }
      $result = str_pad($pd['effyear'][$i],4,'0',STR_PAD_LEFT) . 
                (isset($pd['month'][$i]) ? 
                  str_pad($monthNum,2,'0',STR_PAD_LEFT) . (isset($pd['day'][$i]) ? str_pad($pd['day'][$i],2,'0',STR_PAD_LEFT) : '' ) :
                  '' );
      return $result;
    }
    
    // The remainder of the code is for numeric keys.
    
    if ( isset($pd['suffix'][$i]) && $pd['suffix'][$i] === 'BC' ) {
      $intYear = $pd['effyear'][$i] * (-1);
    }
    else {
      $intYear = (int)$pd['effyear'][$i];
    }
    
    // Handle situation where year, month and day are all present.
    if ( isset($pd['month'][$i]) && isset($pd['day'][$i]) ) {
      $jd = gregoriantojd($monthNum, $pd['day'][$i], $intYear);
      if ( isset($pd['modifier'][$i]) && preg_match("/\b(Bef|To)\b/i", $pd['modifier'][$i]) ) {              // For these modifiers, subtract 1 from the day
        $jd -= 1;
      }
      if ( isset($pd['modifier'][$i]) && preg_match("/\b(Aft|Bet|From)\b/i", $pd['modifier'][$i]) ) {        // For these modifiers, add 1 to the day 
        $jd += 1;
      }
      // TO DO - handle reversing days and months if negative year (BC) - figure out how to handle negative years elsewhere - doesn't work now
      preg_match_all('#-?\d+#', jdtogregorian($jd), $fields, PREG_SET_ORDER);
      $result = $fields[2][0] . str_pad($fields[0][0],2,'0',STR_PAD_LEFT) . str_pad ($fields[1][0],2,'0',STR_PAD_LEFT);
      return (int)$result;
    }
    
    // Handle situation where only year and month are present.
    if ( isset($pd['month'][$i]) ) {
      if ( isset($pd['modifier'][$i]) && preg_match("/\b(Bef|To)\b/i", $pd['modifier'][$i]) ) {              // For these modifiers, subtract 1 from the month
        if ( $monthNum == 1 ) {
          $pd['effyear'][$i] -= 1;
          $monthNum = 12;
        }
        else {
          $monthNum -= 1;
        }
      } 
      if ( isset($pd['modifier'][$i]) && preg_match("/\b(Aft|Bet|From)\b/i", $pd['modifier'][$i]) ) {        // For these modifiers, add 1 to the month
        if ( $monthNum == 12 ) {
          $pd['effyear'][$i] += 1;
          $monthNum = 1;
        }
        else {
          $monthNum += 1;
        }
      } 
      $result = $pd['effyear'][$i] . str_pad($monthNum,2,'0',STR_PAD_LEFT) . '00';
      return (int)$result;
    }
    
    // Handle situation where only year is present.
    if ( isset($pd['modifier'][$i]) && preg_match("/\b(Bef|To)\b/i", $pd['modifier'][$i]) ) {              // For these modifiers, subtract 1 from the year
      $pd['effyear'][$i] -= 1;
    } 
    if ( isset($pd['modifier'][$i]) && preg_match("/\b(Aft|Bet|From)\b/i", $pd['modifier'][$i]) ) {        // For these modifiers, add 1 to the year
      $pd['effyear'][$i] += 1;
    } 
    $result = $pd['effyear'][$i] . '0000';
    return (int)$result;
  }
  
  // This function returns the year of a date. If the date has a range and modifiers are to be included, it returns the year range.
  // Otherwise, it returns just the last year of the range. 
	public static function getYear($date, $includeModifiers=false) {
    $parsedDate = array();
    
    $parsedDate=self::parseDate($date);
    if ( isset($parsedDate['year'][0]) ) {
      // If this is a date range and the first year and last year are the same, return the year (once) without modifiers.
      if ( isset($parsedDate['year'][1]) && $parsedDate['year'][0] == $parsedDate['year'][1] ) {
        return $parsedDate['year'][1];
      }
      else {
        if ( isset($parsedDate['modifier'][0]) && !isset($parsedDate['year'][1]) && $parsedDate['modifier'][0] === 'to' ) {
          $parsedDate['modifier'][0] = 'To';                           // "to" can be used on its own - if so, capitalize (added Feb 2021)
        }
        return ($includeModifiers && isset($parsedDate['modifier'][1]) ? $parsedDate['modifier'][1] . " " : "") .
                ($includeModifiers && isset($parsedDate['year'][1]) ? $parsedDate['year'][1] . " " : "") .
                ($includeModifiers && isset($parsedDate['modifier'][0]) ? ($parsedDate['modifier'][0] == 'and' ? "&" : $parsedDate['modifier'][0]) . " " : "") .
                $parsedDate['year'][0];
      }
    }
    return false;
	}

  // This function returns the last year in the date. If it is a split year, it returns the effective year (e.g., 1624 for 1623/24).
  public static function getEffectiveYear($date) {
    $parsedDate = array();
    
    $parsedDate=self::parseDate($date);
    if ( isset($parsedDate['effyear'][0]) ) {
      return $parsedDate['effyear'][0];
    }
    else {
      return false;
    }
  }
    
  // This function parses the input date and returns results in an array, along with a possible error message.
  // Modifier, day, month, year, suffix (e.g., BC) and effective year are returned in sub-arrays. If there are 2 dates (bet/and or from/to), 
  // [0] is the second date and [1] is the first date (because the parsing works from the end to the beginning of the date).
  // The results may also include a single parenthetical text portion. 
  private static function parseDate($originalDate) {
    $parsedDate = array();
    $saveOriginal = false;
    $fields = array();
    $parts = array();

    // If the date ends with text in parentheses (GEDCOM standard), remove the parenthetical portion and return it separately
    if ( ($begin = strpos($originalDate, '(')) !== false && strrpos($originalDate, ')') == strlen($originalDate)-1 ) {
      $parsedDate['text'] = substr($originalDate, $begin);
      if ( $begin > 0 ) {
        $originalDate = substr($originalDate, 0, $begin-1);
      }
      else {
        return $parsedDate;
      }
    }
    
    // Prepare: lower case; remove leading and trailing whitespace, and reduce internal strings of whitespace to one space each
    // Original date (minus any text portion removed above) is retained in case it needs to be returned in the text portion
    $date = mb_strtolower(trim(preg_replace('!\s+!', ' ', $originalDate)));
    
    // Special cases (several added Mar 2021 by Janet Bjorndahl)
    switch ( $date ) {
      case "":
        return $parsedDate;
      case "unknown":                    // unknown (or variation) will result in a blank date (or parenthetical portion if there is one)
        return $parsedDate;
      case "date unknown":
        return $parsedDate;
      case "unk":
        return $parsedDate;
      case "unknow":
        return $parsedDate;
      case "not known":
        return $parsedDate;
      case "unbekannt":
        return $parsedDate;
      case "unbek.":
        return $parsedDate;
      case "onbekend":
        return $parsedDate;
      case "inconnue":
        return $parsedDate;
      case "in infancy":
        $parsedDate['text'] = "(in infancy)" . ( isset($parsedDate['text']) ? " " . $parsedDate['text'] : "" );
        return $parsedDate;
      case "died in infancy":
        $parsedDate['text'] = "(in infancy)" . ( isset($parsedDate['text']) ? " " . $parsedDate['text'] : "" );
        return $parsedDate;
      case "infant":
        $parsedDate['text'] = "(in infancy)" . ( isset($parsedDate['text']) ? " " . $parsedDate['text'] : "" );
        return $parsedDate;
      case "infancy":
        $parsedDate['text'] = "(in infancy)" . ( isset($parsedDate['text']) ? " " . $parsedDate['text'] : "" );
        return $parsedDate;
      case "young":
        $parsedDate['text'] = "(young)" . ( isset($parsedDate['text']) ? " " . $parsedDate['text'] : "" );
        return $parsedDate;
      case "died young":
        $parsedDate['text'] = "(young)" . ( isset($parsedDate['text']) ? " " . $parsedDate['text'] : "" );
        return $parsedDate;
    }

    // Convert valid numeric dates to GEDCOM format (dd mmm yyyy) before continuing with parsing. 
    // This is somewhat inefficient because of having to reparse these dates, but it was the easiest way to add this code, and these dates are not common.
     
    // Replace any (valid) embedded date already in yyyy-mm-dd format with the GEDCOM equivalent.
    // Start with the last embedded date, because any replacement of an earlier one can affect the offset (position of the embedded date within the string).
    if ( preg_match_all('#\d{3,4}[-./]\d{1,2}[-./]\d{1,2}#', $date, $fields, PREG_OFFSET_CAPTURE) > 0 ) {
      for ( $i=count($fields[0])-1; $i>=0 ; $i-- ) {
        preg_match_all('#\d+#', $fields[0][$i][0], $parts);
        if ( @self::$MONTHS[$parts[0][1]-1] ) {
          $embeddedDate = $parts[0][2] . " " . self::$MONTHS[$parts[0][1]-1] . " " . $parts[0][0];
          $date = substr($date,0,$fields[0][$i][1]) . $embeddedDate . substr($date,$fields[0][$i][1]+strlen($fields[0][$i][0]));
          $parsedDate['reformat'] = 'Significant reformat';          // added Mar 2021 by Janet Bjorndahl
        }
      }
    $fields = array();              // reinitialize fields and parts (used again below)
    $parts = array();
    } 

    // Check for one or more embedded dates in mm-dd-yyyy or dd-mm-yyyy format. 
    // If a date is ambiguous, return an error message. Otherwise, if valid, replace with the GEDCOM equivalent.
    // Start with the last embedded date, because any replacement of an earlier one can affect the offset (position of the embedded date within the string).
    // Some refactoring Mar 2021 by Janet Bjorndahl.
    if ( preg_match_all('#\d{1,2}[-./]\d{1,2}[-./]\d{3,4}#', $date, $fields, PREG_OFFSET_CAPTURE) > 0 ) {
      for ( $i=count($fields[0])-1; $i>=0 ; $i-- ) {
        preg_match_all('#\d+#', $fields[0][$i][0], $parts);
        if ( !self::isNumMonth($parts[0][0]) && self::isNumMonth($parts[0][1]) ) {
          $embeddedDate = $parts[0][0] . ' ' . self::$MONTHS[$parts[0][1]-1] . ' ' . $parts[0][2];
          $date = substr($date,0,$fields[0][$i][1]) . $embeddedDate . substr($date,$fields[0][$i][1]+strlen($fields[0][$i][0]));
          $parsedDate['reformat'] = 'Significant reformat';          // added Mar 2021 by Janet Bjorndahl
        }
        else {
          if ( !self::isNumMonth($parts[0][1]) && self::isNumMonth($parts[0][0]) ) {
            $embeddedDate = $parts[0][1] . ' ' . self::$MONTHS[$parts[0][0]-1] . ' ' . $parts[0][2];
            $date = substr($date,0,$fields[0][$i][1]) . $embeddedDate . substr($date,$fields[0][$i][1]+strlen($fields[0][$i][0]));
            $parsedDate['reformat'] = 'Significant reformat';        // added Mar 2021 by Janet Bjorndahl
          }
          else {
            $parsedDate['year'][0] = $parts[0][2];        // Even if the date is ambiguous, the year can be returned for functions that require it
            $parsedDate['effyear'][0] = $parts[0][2];     // Not worried whether this is the first or second date since it is very rare to have a range with numeric dates
            $parsedDate['message'] = 'Ambiguous date';
            return $parsedDate;
          }
        }
      }
    $fields = array();              // reinitialize fields (used again below)
    }
    
    // If the date includes one or more split years expressed with uncertainty ([/d?] or [/dd?]), set a flag to save the original in the parenthetical text portion
    // and remove the ? so that it is not also added separately to the text portion in code below. 
    // The remaining code will ignore the brackets and format the split year correctly.                 Added Mar 2021 by Janet Bjorndahl
    if ( preg_match_all('#\[/\d{1,2}\?\]#', $date, $fields, PREG_OFFSET_CAPTURE) > 0 ) {
      $saveOriginal = true;
      $parsedDate['reformat'] = 'Significant reformat';          
      for ( $i=count($fields[0])-1; $i>=0; $i-- ) {
        $date = substr($date,0,$fields[0][$i][1]) . str_replace("?","",$fields[0][$i][0]) . substr($date,$fields[0][$i][1]+strlen($fields[0][$i][0]));
      }
    $fields = array();              // reinitialize fields (used again below)
    }
    
    // If date includes a dash, replace with "to" or "and" depending on whether the string already has "from/est" or "bet".
    // If it has neither, treat as bet/and (applicable to all types of events).
    if ( strpos($date, '-') ) {
      if ( strpos($date, 'from' ) !== false || strpos($date, 'est' ) !== false ) {
        $date = str_replace('-', ' to ', $date);
      }
      else { 
        $date = str_replace('-', ' and ', $date);
        if ( strpos($date, 'bet' ) === false ) {
          $date = 'bet ' . $date;
        }
      }
      $parsedDate['reformat'] = 'Significant reformat';          // added Mar 2021 by Janet Bjorndahl
    }
        
    // replace b.c. with bc unless it is at the beginning of the string (when the user might have been intending "before circa")
    $dateFirstChar = substr($date, 0, 1);
    $dateRest = substr($date,1);
    if ( strpos($dateRest,"b.c.") !== false ) { 
      $date = $dateFirstChar . str_replace("b.c." , "bc", $dateRest);
      $parsedDate['reformat'] = 'Significant reformat';          // added Mar 2021 by Janet Bjorndahl
    }
    
    // this should match 0-9+ or / or ? or & or alphabetic(including accented)+ (& removed from the regex Mar 2021 by Janet Bjorndahl)
    preg_match_all('/(\d+|[^0-9\s`~!@#%^*()_+\-={}|:\'<>;,"\[\]\.\\\\]+)/', $date, $fields, PREG_SET_ORDER);
    
    // start at the last field so that first numeric encountered is treated as the year
    $findSplitYear = false;
    $possibleSplitYear = false;
    $dateIndex = 0;                                              // index=0 for the last date; index=1 for the first date if there are 2
    for ($i=count($fields)-1; $i>=0; $i--) {
      $field = $fields[$i][1];
      if ( $field === '/' ) {
        if ( !isset($parsedDate['year'][$dateIndex]) && !$possibleSplitYear ) {   // error if second part of the split year not already captured
          $parsedDate['message'] = 'Incomplete split year';
          return $parsedDate;
        }
        // error if split year, month or day already captured for this date (could indicate an either/or date with "/" meaning "or") - added Mar 2021 by Janet Bjorndahl
        if ( (isset($parsedDate['year'][$dateIndex]) && strpos($parsedDate['year'][$dateIndex],'/')!==false) || 
              isset($parsedDate['month'][$dateIndex]) || isset($parsedDate['day'][$dateIndex]) ) {
          $parsedDate['message'] = "Invalid date format";
          return $parsedDate;
        }
        else {
          $findSplitYear = true;  
          if ( $possibleSplitYear ) {                            // if digit(s) after / were 0, save them in the year now (as second part of the split year)
            $parsedDate['year'][$dateIndex] = '0';
            $possibleSplitYear = false;
          }
        }
      }
      else {
        if ( $findSplitYear ) {                                  // if waiting for the first part of a split year, treat this field as the first part
          if ( !is_numeric($field) ) {                           // if field is not numeric, return error (added Feb 2021 by Janet Bjorndahl)
            $parsedDate['message'] = 'Incomplete split year';    // (year and effective year have already been captured - the value after the /)
            return $parsedDate;
          }            
          $splitYearEdit = self::editSplitYear($field, $parsedDate['year'][$dateIndex]);     // $parsedDate['year'][$dateIndex] is updated if no error
          if ( $splitYearEdit !== true ) {
            $parsedDate['message'] = $splitYearEdit;
            $parsedDate['year'][$dateIndex] = $field;            // save first part of split year even if not valid (closest we have to a year for functions that need it)
            $parsedDate['effyear'][$dateIndex] = $field;  
            return $parsedDate;
          }
          $parsedDate['effyear'][$dateIndex] = $field + 1;       // capture the effective year (first part + 1)
          $findSplitYear = false;
        }
        else {
          if ( $field === 'bce' || $field === 'bc' ) {
            $parsedDate['suffix'][$dateIndex] = 'BC';
          }
          else {
            // If the field is numeric, have to determine whether to treat is as the day or the year.
            if ( is_numeric($field) ) {
              $num = $field + 0;                                 // force conversion to number - this strips leading zeros
              if ( $num === 0 && !isset($parsedDate['year'][$dateIndex]) ) {  // modified Feb 2021 by Janet Bjorndahl
                $possibleSplitYear = true;                       // a value of 0 is not valid for anything other than the second part of a split year: keep track of it
              }
              else {
                if ( self::isDay($num) ) {                       // if this field could be a day, it could also be a year. Need some logic to determine how to treat it.
                  // If month and/or year is already captured, treat it as the day.
                  if ( isset($parsedDate['month'][$dateIndex]) || isset($parsedDate['year'][$dateIndex]) ) {             
                    if ( isset($parsedDate['day'][$dateIndex]) ) {               // error if day already has a value
                      $parsedDate['message'] = 'Too many numbers (days/years)';
                      return $parsedDate;
                    }
                    else {
                      $parsedDate['day'][$dateIndex] = "$num";          
                    }
                  }
                  // If neither month nor year is captured, this would normally be treated as the year.
                  // However, in the case of bet/and or from/to, the first date can pick up the year and month from the second date (e.g.,
                  // ("Bet 10 and 15 Oct 1823" becomes "Bet 10 Oct 1823 and 15 Oct 1823") as long as this results in a valid date range.
                  // If this is applicable, treat this field as the day.
                  // UNLESS it is possible that this field is the second part of a split year - if the field before this one is "/", treat this as the year.
                  else {             
                    if ( !($i>0 && $fields[$i-1][1]=='/') &&                           // check for possible split year situation added Mar 2021 by Janet Bjorndahl
                          $dateIndex===1 && !isset($parsedDate['year'][1]) && isset($parsedDate['year'][0]) ) {   
                      if ( isset($parsedDate['day'][0]) && $num < $parsedDate['day'][0] ) { 
                        $parsedDate['year'][1] = $parsedDate['year'][0];                        
                        $parsedDate['effyear'][1] = $parsedDate['effyear'][0];         // added Feb 2021 by Janet Bjorndahl (so it is set even if an error is found later)
                        $parsedDate['month'][1] = $parsedDate['month'][0];
                        $parsedDate['day'][1] = "$num";
                        $parsedDate['reformat'] = 'Significant reformat';              // added Mar 2021 by Janet Bjorndahl
                        $saveOriginal = true;
                      }
                      else {
                        if ( !self::treatAsYearRange($num,$parsedDate['year'][0]) ) {  // if not valid to use this as the day, and not reasonable to use it as the year, error
                          $parsedDate['message'] = 'Missing month';
                          return $parsedDate;
                        }
                      }
                    }
                    // If neither month nor year is captured, and it is not the special case above, treat it as the year (to be updated later if this is a split year)      
                    else {
                      $parsedDate['year'][$dateIndex] = "$num";             
                      $parsedDate['effyear'][$dateIndex] = "$num";     // added Feb 2021 by Janet Bjorndahl (so it is set even if an error is found later)           
                    }
                  }    
                }
                // Numeric field that is not a valid day is the year (or an error)
                else {
                  if ( isset($parsedDate['year'][$dateIndex]) ) {    // error if year already has a value
                    $parsedDate['message'] = 'Invalid day number';
                    return $parsedDate;
                  }
                  else {
                    if ( self::isYear($num) ) {
                      $parsedDate['year'][$dateIndex] = "$num";             
                      $parsedDate['effyear'][$dateIndex] = "$num";     // added Feb 2021 by Janet Bjorndahl (so it is set even if an error is found later)            
                    }
                    else {                                               // error if a number is neither a valid day nor a valid year
                      $parsedDate['message'] = 'Invalid year number';  
                      return $parsedDate;
                    }
                  }
                }
              }
            }
            // The field is not numeric.
            else {
              if ( $m = self::getMonthAbbrev($field) ) {
                if ( isset($parsedDate['month'][$dateIndex]) ) {       // error if month already has a value
                  $parsedDate['message'] = 'Too many months';  
                  return $parsedDate;
                }
                else {
                  $parsedDate['month'][$dateIndex] = $m;
                }
              }
              else {
                if ( $q = self::getModifier($field) ) {
                  $parsedDate['modifier'][$dateIndex++] = $q;
                }
                else { 
                  if ( $field === '?' ) {                              // if there is a question mark, capture it in a text field (too important to drop)
                    $parsedDate['text'] = "(?)" . ( isset($parsedDate['text']) ? " " . $parsedDate['text'] : "" );
                  }
                  else {
                    if ( in_array($field, self::$ORDINAL_SUFFIXES) ) {     // ignore 'st', 'nd', 'rd', 'th' (added Feb 2021 by Janet Bjorndahl)
                    }
                    else {
                      if ( strpos($field,'wft') !== false ) {              // error if unrecognizable field
                        $parsedDate['message'] = 'WFT estimates not accepted';
                      }
                      else {  
                        $parsedDate['message'] = 'Unrecognized text';
                      }
                    return $parsedDate;
                    }
                  }
                }
              }
            }
          }  
        }
      }
    }
    
    // If still waiting for first part of split year, message.  Added Feb 2021 by Janet Bjorndahl
    if ( $findSplitYear ) {
      $parsedDate['message'] = 'Incomplete split year';
    }
    
    // If using bet/and or from/to and the first date [1] is missing the year (but has the month), pick it up from the second date [0]
    // as long as this results in a valid date range.
    // Note that picking up the month from the second date is handled in above code when dealing with the day.
    if ( $dateIndex === 2 ) {                                            // dateIndex would have been incremented after the last modifier read
      if ( !isset($parsedDate['year'][1]) && isset($parsedDate['year'][0]) && isset($parsedDate['month'][1]) && isset($parsedDate['month'][0]) ) {
        if ( self::getMonthNumber($parsedDate['month'][1]) < self::getMonthNumber($parsedDate['month'][0]) ) {   
        $saveOriginal = true;
        $parsedDate['year'][1] = $parsedDate['year'][0];
        $parsedDate['effyear'][1] = $parsedDate['effyear'][0];           // added Feb 2021 by Janet Bjorndahl (refactored where effyear is set)
        $parsedDate['reformat'] = 'Significant reformat';                // added Mar 2021 by Janet Bjorndahl
        }
      }
    }
    // If it was necessary to pick up the year and/or month from the second date, capture the original date in a parenthetical text portion (possibly in addition to an existing one)
    if ( $saveOriginal ) {
      $parsedDate['text'] = "($originalDate)" . ( isset($parsedDate['text']) ? " " . $parsedDate['text'] : "" );
    }

    // If there is a pair of dates with "to" between them but no "From" before the first date, add the "From" (added Mar 2021 by Janet Bjorndahl)
    if ( isset($parsedDate['year'][1]) && !isset($parsedDate['modifier'][1]) && $parsedDate['modifier'][0] == 'to' ) {
      $parsedDate['modifier'][1] = 'From';
      $parsedDate['reformat'] = 'Significant reformat';
    }

  return $parsedDate;
  }

  private static function isDay($d) {
    return ($d >= 1 && $d <= 31);
  }
  
  private static function isNumMonth($m) {
    return ($m >= 1 && $m <= 12);
  }

  private static function isYear($y) {
    return ($y >= 1 && $y <= 5000);
  }
  
  private static function treatAsYearRange($y1, $y2) {
    return ( ($y2-$y1) < 300 );
  }

  // Note: Double-dating applies when the year started March 25 (not necessarily corresponding to when the Julian calendar was in use.)
  // In England, the civil year started March 25 from the 12th century to 1752. From the 7th century to the 12th century, it started Dec 25.
  // We're allowing split year dates starting in 1000 because some other countries started the year in March before England did.
  // Most other countries started using Jan 1 as the beginning of the year around 1600 (Italy started about 1750).
  private static function isDoubleDating($y) {
    return ($y >= 1000 && $y <= 1752);            // year range changed Mar 2021 by Janet Bjorndahl
  }
  
  private static function getMonthAbbrev($m) {
    return @self::$GEDCOM_MONTHS[$m];
  }
  
  private static function getMonthNumber($m) {
    return @array_search(strtolower($m), self::$MONTHS)+1;
  }

  private static function getModifier($q) {
    return @self::$GEDCOM_MODIFIERS[$q];
  }    
   
  private static function editSplitYear($firstPart, &$secondPart) {  // firstPart is an unknown field; secondPart qualifies as a valid year (numeric string without leading zeros)
    $num = $firstPart + 0;                     // force conversion to number
    if ( !self::isDoubleDating($num) ) {
      return 'Split year not valid for this year';
    }
    $newYear = (substr($firstPart, 0, strlen($firstPart)-strlen($secondPart))) . $secondPart;
    if ( $newYear - $num == 1 ||
          (substr($firstPart, 3) === '9' && substr($secondPart,strlen($secondPart)-1) === '0') ) {
      $secondPart = $firstPart . '/' . (substr($firstPart, 2) == '99' ? '00' : str_pad(substr($firstPart, 2)+1,2,'0',STR_PAD_LEFT));
      return true;
    }
    return 'Invalid split year';
  }

}
?>
