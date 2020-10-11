<?php

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

  private static $GEDCOM_MODIFIERS = array('abt'=>'Abt','cal'=>'Cal','est'=>'Est','bef'=>'Bef','aft'=>'Aft','from'=>'From','to'=>'to','bet'=>'Bet','and'=>'and','int'=>'Int',
                                           'about'=>'Abt','calculated'=>'Cal','calc'=>'Cal','calcd'=>'Cal','estd'=>'Est','estimated'=>'Est','c'=>'Est','ca'=>'Est','circa'=>'Est',
                                           'before'=>'Bef','after'=>'Aft','frm'=>'From','btw'=>'Bet','between'=>'Bet','interpreted'=>'Int',
                                           'say'=>'Est');
                                           // will add other languages at user request, if they provide the full words and abbreviations

  public static function editDate($date, &$formatedDate, &$languageDate, $discreteEvent=false) {
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

    if ( isset($parsedDate['modifier'][1]) ) {                         // error if a pair of modifiers is not Bet/and or From/to
      if ( ! (($parsedDate['modifier'][1] === 'Bet' && $parsedDate['modifier'][0] === 'and') || ($parsedDate['modifier'][1] === 'From' && $parsedDate['modifier'][0] === 'to')) ) {
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
             $parsedDate['month'][$i] !== 'Jan' && $parsedDate['month'][$i] !== 'Feb' & $parsedDate['month'][$i] !== 'Mar' ) {
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
    if ( isset($parsedDate['text']) ) {
      $formatedDate .= ($formatedDate == '' ? $parsedDate['text'] : ' ' . $parsedDate['text']);
      $languageDate .= ($languageDate == '' ? $parsedDate['text'] : ' ' . $parsedDate['text']);
    }
    return true;
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
  // [0] is the second date and [1] is the first date. The results may also include a single parenthetical text portion. 
  private static function parseDate($originalDate) {
    $parsedDate = array();
    $saveOriginal = false;
    $fields = array();

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
    
    // lower case; remove leading and trailing whitespace, and reduce internal strings of whitespace to one space each
    // original date (minus any text portion removed above) is retained in case it needs to be returned in the text portion
    $date = mb_strtolower(trim(preg_replace('!\s+!', ' ', $originalDate)));
    
    // special cases
    switch ( $date ) {
      case "":
        return $parsedDate;
      case "unknown":                    // unknown will result in a blank date (or parenthetical portion if there is one)
        return $parsedDate;
      case "in infancy":
        $parsedDate['text'] = "(in infancy)" . ( isset($parsedDate['text']) ? " " . $parsedDate['text'] : "" );
        return $parsedDate;;
      case "infant":
        $parsedDate['text'] = "(in infancy)" . ( isset($parsedDate['text']) ? " " . $parsedDate['text'] : "" );
        return $parsedDate;;
      case "young":
        $parsedDate['text'] = "(young)" . ( isset($parsedDate['text']) ? " " . $parsedDate['text'] : "" );
        return $parsedDate;;
      case "died young":
        $parsedDate['text'] = "(young)" . ( isset($parsedDate['text']) ? " " . $parsedDate['text'] : "" );
        return $parsedDate;;
    }
    
    // Check to see if it is an ambiguous date (dd/mm/yyyy or mm/dd/yyyy) and if so, try to resolve 
    if ( preg_match('#\d{1,2}[-./ ]+\d{1,2}[-./ ]+\d{2,4}#', $date) ) {
      preg_match_all('#\d+#', $date, $fields, PREG_SET_ORDER);
      if ( $fields[0][0] > 12 ) {
        $date = $fields[0][0] . ' ' . self::$MONTHS[$fields[1][0]-1] . ' ' . $fields[2][0];
      } 
      else {
        if ( $fields[1][0] > 12 ) {
          $date = $fields[1][0] . ' ' . self::$MONTHS[$fields[0][0]-1] . ' ' . $fields[2][0];
        }
        else {
          $parsedDate['year'][0] = $fields[2][0];
          $parsedDate['effyear'][0] = $fields[2][0];
          $parsedDate['message'] = 'Ambiguous date';
          return $parsedDate;
        }
      }
      $fields = array();              // reinitialize fields (used again below)
    }
    
    // If date includes a dash, replace with "to" or "and" depending on whether the string already has "from" or "bet".
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
    }
        
    // replace b.c. with bc unless it is at the beginning of the string (when the user might have been intending "before circa")
    $dateFirstChar = substr($date, 0, 1);
    $dateRest = substr($date,1);
    $date = $dateFirstChar . str_replace("b.c." , "bc", $dateRest);
    
    // this should match 0-9+ or / or ? or alphabetic(including accented)+
    preg_match_all('/(\d+|[^0-9\s`~!@#%^&*()_+\-={}|:\'<>;,"\[\]\.\\\\]+)/', $date, $fields, PREG_SET_ORDER);
    
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
              if ( $num === 0 ) {                                // a value of 0 is not valid for anything other than the second part of a split year
                $possibleSplitYear = true;                       // keep track of it
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
                  else {             
                    if ( $dateIndex===1 && !isset($parsedDate['year'][1]) && isset($parsedDate['year'][0]) ) {   
                      if ( isset($parsedDate['day'][0]) && $num < $parsedDate['day'][0] ) { 
                        $parsedDate['year'][1] = $parsedDate['year'][0];
                        $parsedDate['month'][1] = $parsedDate['month'][0];
                        $parsedDate['day'][1] = "$num";
                        $saveOriginal = true;
                      }
                      else {
                        if ( !self::treatAsYearRange($num,$parsedDate['year'][0]) ) {  // if not valid to use this as the day, and not reasonable to use it as the year, error
                          $parsedDate['message'] = 'Missing month';
                          return $parsedDate;
                        }
                      }
                    }
                    // If neither month nor year is captured, and it is not the special case above, treat it as the year      
                    else {
                      $parsedDate['year'][$dateIndex] = "$num";             
                    }
                  }    
                }
                // Numeric field that is not a valid day is the year (or an error)
                else {
                  if ( self::isYear($num) ) {
                    if ( isset($parsedDate['year'][$dateIndex]) ) {    // error if year already has a value
                      $parsedDate['message'] = 'Invalid day number';
                      return $parsedDate;
                    }
                    else {
                      $parsedDate['year'][$dateIndex] = "$num";             
                    }
                  }
                  else {                                               // error if a number is neither a valid day nor a valid year
                    $parsedDate['message'] = 'Invalid year number';  
                    return $parsedDate;
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
    
    // If using bet/and or from/to and the first date [1] is missing the year (but has the month), pick it up from the second date [0]
    // as long as this results in a valid date range.
    // Note that picking up the month from the second date is handled in above code when dealing with the day.
    if ( $dateIndex === 2 ) {                                            // dateIndex would have been incremented after the last modifier read
      if ( !isset($parsedDate['year'][1]) && isset($parsedDate['year'][0]) && isset($parsedDate['month'][1]) && isset($parsedDate['month'][0]) ) {
        if ( self::getMonthNumber($parsedDate['month'][1]) < self::getMonthNumber($parsedDate['month'][0]) ) {   
        $saveOriginal = true;
        $parsedDate['year'][1] = $parsedDate['year'][0];
        }
      }
    }
    // If it was necessary to pick up the year and/or month from the second date, capture the original date in a parenthetical text portion (possibly in addition to an existing one)
    if ( $saveOriginal ) {
      $parsedDate['text'] = "($originalDate)" . ( isset($parsedDate['text']) ? " " . $parsedDate['text'] : "" );
    }
    
    // Set effective years not already set by split-year processing
    for ($i=0; $i<2; $i++) {
      if ( isset($parsedDate['year'][$i]) && !isset($parsedDate['effyear'][$i]) ) {
        $parsedDate['effyear'][$i] = $parsedDate['year'][$i];
      }
    }
    
  return $parsedDate;
  }

  private static function isDay($d) {
    return ($d >= 1 && $d <= 31);
  }

  private static function isYear($y) {
    return ($y >= 1 && $y <= 5000);
  }
  
  private static function treatAsYearRange($y1, $y2) {
    return ( ($y2-$y1) < 300 );
  }

  private static function isDoubleDating($y) {
    return ($y >= 1582 && $y <= 1923);
  }
  
  private static function getMonthAbbrev($m) {
    return @self::$GEDCOM_MONTHS[$m];
  }
  
  private static function getMonthNumber($m) {
    return @array_search(strtolower($m), self::$MONTHS);
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
