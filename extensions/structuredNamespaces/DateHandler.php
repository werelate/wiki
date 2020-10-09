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

  private static $GEDCOM_QUALIFIERS = array('abt'=>'Abt','cal'=>'Cal','est'=>'Est','bef'=>'Bef','aft'=>'Aft','from'=>'From','to'=>'to','bet'=>'Bet','and'=>'and','int'=>'Int',
                                            'about'=>'Abt','calculated'=>'Cal','calc'=>'Cal','calcd'=>'Cal','estd'=>'Est','estimated'=>'Est','c'=>'Est','ca'=>'Est','circa'=>'Est',
                                            'before'=>'Bef','after'=>'Aft','frm'=>'From','btw'=>'Bet','between'=>'Bet','interpreted'=>'Int',
                                            'say'=>'Est');
                                            // will add other languages at user request, if they provide the full words and abbreviations

  public static function editDate($originalDate, &$formatedDate, &$languageDate, $discreteEvent=false) {
    $text = '';
    $saveOriginal = false;
    $fields = array();
    $day = array();
    $month = array();
    $year = array();
    $suffix = array();
    $qualifier = array();
    $formatedDate = '';
    $languageDate = '';

    // If the date ends with text in parentheses, remove the parenthetical portion and add it back later (GEDCOM-supported text field)
    if ( ($begin = strpos($originalDate, '(')) !== false && strrpos($originalDate, ')') == strlen($originalDate)-1 ) {
      if ( $begin > 0 ) {
        $text = substr($originalDate, $begin);
        $originalDate = substr($originalDate, 0, $begin-1);
      }
      else {
        $formatedDate = $originalDate;
        return true;
      }
    }
    
    // lower case; remove leading and trailing whitespace, and reduce internal strings of whitespace to one space each
    $date = mb_strtolower(trim(preg_replace('!\s+!', ' ', $originalDate)));
    
    // special cases
    // TO DO (if requested): handle translation - need to update Messages files
    switch ( $date ) {
      case "":
        return true;
      case "unknown":
        $formatedDate = '';
        return true;
      case "in infancy":
        $formatedDate = '(in infancy)';
        $languageDate = '(in infancy)';
        return true;
      case "infant":
        $formatedDate = '(in infancy)';
        $languageDate = '(in infancy)';
        return true;
      case "young":
        $formatedDate = '(young)';
        $languageDate = '(young)';
        return true;
      case "died young":
        $formatedDate = '(young)';
        $languageDate = '(young)';
        return true;
    }
    
    // Check to see if it is an ambiguous date (dd/mm/yyyy or mm/dd/yyyy) and if so, try to resolve 
    if ( preg_match('#\d{1,2}[-./ ]+\d{1,2}[-./ ]+\d{2,4}#', $date) ) {
      preg_match_all('#\d+#', $date, $fields, PREG_SET_ORDER);
      if ( $fields[0][0] > 12 ) {
        $date = $fields[0][0] . ' ' . DateHandler::$MONTHS[$fields[1][0]-1] . ' ' . $fields[2][0];
      } 
      else {
        if ( $fields[1][0] > 12 ) {
          $date = $fields[1][0] . ' ' . DateHandler::$MONTHS[$fields[0][0]-1] . ' ' . $fields[2][0];
        }
        else return 'Ambiguous date';
      }
      $fields = array();
    }
    
    // If date includes a dash, replace with "from/to" (or "bet/and" if the string includes "bet")
    // If the date is for a discrete event, "from/to" will be replaced by "bet/and" below
    if ( strpos($date, '-') ) {
      if ( strpos($date, 'bet' ) !== false ) {
        $date = str_replace('-', ' and ', $date);
      }
      else {
        $date = str_replace('-', ' to ', $date);
        if ( strpos($date, 'from') === false ) {
          $date = 'from ' . $date;
        }
      }
    }
        
    // replace b.c. with bc unless it is at the beginning of the string (when the user might have been intending "before circa")
    $dateFirstChar = substr($date, 0, 1);
    $dateRest = substr($date,1);
    $date = $dateFirstChar . str_replace("b.c." , "bc", $dateRest);
    
    // this should match 0-9+ or / or alphabetic(including accented)+
    preg_match_all('/(\d+|[^0-9\s`~!@#%^&*()_+\-={}|:\'<>?;,"\[\]\.\\\\]+)/', $date, $fields, PREG_SET_ORDER);
    
    // start at the last field so that first numeric encountered is treated as the year
    $findSplitYear = false;
    $possibleSplitYear = false;
    $dateIndex = 0;                                              // index=0 for the last date; index=1 for the first date if there are 2
    for ($i=count($fields)-1; $i>=0; $i--) {
      $field = $fields[$i][1];
      if ( $field === '/' ) {
        if ( !isset($year[$dateIndex]) && !$possibleSplitYear ) {   // error if second part of the split year not already captured
          return 'Incomplete split year';
        }
        else {
          $findSplitYear = true;  
          if ( $possibleSplitYear ) {                            // if digit(s) after / were 0, save them in the year now (as second part of the split year)
            $year[$dateIndex] = '0';
            $possibleSplitYear = false;
          }
        }
      }
      else {
        if ( $findSplitYear ) {                                  // if waiting for the first part of a split year, treat this field as the first part
          $splitYearEdit = DateHandler::editSplitYear($field, $year[$dateIndex]);     // $year[$dateIndex] is updated if no error
          if ( $splitYearEdit !== true ) {
            return $splitYearEdit;
          }
          $findSplitYear = false;
        }
        else {
          if ( $field === 'bce' || $field === 'bc' ) {
            $suffix[$dateIndex] = 'BC';
          }
          else {
            // If the field is numeric, have to determine whether to treat is as the day or the year.
            if ( is_numeric($field) ) {
              $num = $field + 0;                                 // force conversion to number - this strips leading zeros
              if ( $num === 0 ) {                                // a value of 0 is not valid for anything other than the second part of a split year
                $possibleSplitYear = true;                       // keep track of it
              }
              else {
                if ( DateHandler::isDay($num) ) {                  // if this field could be a day, it could also be a year. Need some logic to determine how to treat it.
                  // If month and/or year is already captured, treat it as the day.
                  if ( isset($month[$dateIndex]) || isset($year[$dateIndex]) ) {             
                    if ( isset($day[$dateIndex]) ) {               // error if day already has a value
                      return 'Too many numbers (days/years)';
                    }
                    else {
                      $day[$dateIndex] = "$num";          
                    }
                  }
                  // If neither month nor year is captured, this would normally be treated as the year.
                  // However, in the case of bet/and or from/to, the first date can pick up the year and month from the second date (e.g.,
                  // ("Bet 10 and 15 Oct 1823" becomes "Bet 10 Oct 1823 and 15 Oct 1823").
                  // If this is applicable and results in a valid date range, treat this field as the day.
                  else {             
                    if ( $dateIndex===1 && !isset($year[1]) && isset($year[0]) ) {   
                      if ( isset($day[0]) && $num < $day[0] ) {   // only pick up month and year from second date if the date range would be valid
                        $year[1] = $year[0];
                        $month[1] = $month[0];
                        $day[1] = "$num";
                        $saveOriginal = true;
                      }
                      else {
                        if ( !DateHandler::treatAsYearRange($num,$year[0]) ) {  // if not valid to use this as the day, and not reasonable to use it as the year, error
                          return 'Missing month';
                        }
                      }
                    }
                    // If neither month nor year is captured, and it is not the special case above, treat it as the year      
                    else {
                      $year[$dateIndex] = "$num";             
                    }
                  }    
                }
                // Numeric field that is not a valid day is the year (or an error)
                else {
                  if ( DateHandler::isYear($num) ) {
                    if ( isset($year[$dateIndex]) ) {              // error if year already has a value
                      return 'Invalid day number';
                    }
                    else {
                      $year[$dateIndex] = "$num";             
                    }
                  }
                  else {
                    return 'Invalid year number';                  // error if a number is neither a valid day nor a valid year
                  }
                }
              }
            }
            // The field is not numeric.
            else {
              if ( $m = DateHandler::getMonthAbbrev($field) ) {
                if ( isset($month[$dateIndex]) ) {               // error if month already has a value
                  return 'Too many months';
                }
                else {
                  $month[$dateIndex] = $m;
                }
              }
              else {
                if ( $q = DateHandler::getQualifier($field) ) {
                  $qualifier[$dateIndex++] = $q;
                }
                else {                                           // error if unrecognizable field     
                  if ( strpos($field,'wft') !== false ) {
                    return 'WFT estimates not accepted';
                  }
                  else {
                    return 'Unrecognized text';
                  }
                }
              }
            }
          }  
        }
      }
    }
    if ( isset($qualifier[1]) ) {                                 // error if a pair of qualifiers is not Bet/and or From/to
      if ( ! (($qualifier[1] === 'Bet' && $qualifier[0] === 'and') || ($qualifier[1] === 'From' && $qualifier[0] === 'to')) ) {
        return 'Invalid combination of qualifiers';
      }
      if ( $discreteEvent && $qualifier[1] == 'From' ) {          // if this is a discrete event, change From/to (misused) to Bet/and
        $qualifier[1] = 'Bet';
        $qualifier[0] = 'and';
      }
    }
    else {
      if ( isset($qualifier[0]) ) {
        if ( $qualifier[0] === 'to' ) {                           // "to" can be used on its own - if so, capitalize
          $qualifier[0] = 'To';
        }
        if ( $qualifier[0] === 'Bet' || $qualifier[0] === 'and') { // error if "Bet" or "and" used on their own
          return 'Incorrect usage of bet/and';
        }
      }
    }
    // If a qualifier was captured, $dateIndex was incremented - need to decrement it before the next step.
    if ( isset($qualifier[$dateIndex-1]) ) {
      $dateIndex--;
    }
    if ( $dateIndex > 1 ) {
      return 'Too many qualifiers';                                 // error if more than 2 qualifiers found
    }
    
    // If using bet/and or from/to and the first date [1] is missing the year (but has the month), pick it up from the second date [0].
    // Note that picking up the month from the second date is handled in above code when dealing with the day.
    if ( $dateIndex === 1 ) {
      if ( !isset($year[1]) && isset($year[0]) && isset($month[1]) && isset($month[0]) ) {
        if ( DateHandler::getMonthNumber($month[1]) < DateHandler::getMonthNumber($month[0]) ) {   // only pick up year from second date if the date range would be valid
        $saveOriginal = true;
        $year[1] = $year[0];
        }
      }
    }
    // If it was necessary to pick up the year and/or month from the second date, capture the original date in a parenthetical text portion (possibly in addition to an existing one)
    if ( $saveOriginal ) {
      $text = "($originalDate)" . ( $text=="" ? "" : " $text" );
    }

    for ($j=$dateIndex; $j>=0; $j--) {
      // error if no year, or if day without month
      if ( !isset($year[$j]) || (isset($day[$j]) && !isset($month[$j])) ) {
        $formatedDate = '';
        return 'Incomplete date';
      }
      // error if day does not match month (leap day not checked)
      if ( isset($day[$j]) && (($day[$j] > 29 && $month[$j] == 'Feb') || 
           ($day[$j] > 30 && ($month[$j] == 'Apr' || $month[$j] == 'Jun' || $month[$j] == 'Sep' || $month[$j] == 'Nov'))) ) {
        $formatedDate = '';
        return 'Invalid day for ' . $month[$j];
      }
      // error if split year for a month after Mar
      if ( strpos($year[$j],'/') && isset($month[$j]) && $month[$j] !== 'Jan' && $month[$j] !== 'Feb' & $month[$j] !== 'Mar' ) {
        return 'Split year valid only for Jan-Mar';
      } 
      $formatedDate .= ($formatedDate ? ' ' : '') . (isset($qualifier[$j]) ? $qualifier[$j] . ' ' : '') . 
                        (isset($day[$j]) ? $day[$j] . ' ' : '') . (isset($month[$j]) ? $month[$j] . ' ' : '') . (isset($year[$j]) ? $year[$j] : '') .
                        (isset($suffix[$j]) ? ' ' . $suffix[$j] : '');
      if ( isset($qualifier[$j]) ) {
        $languageQualifier = $qualifier[$j];  // do this until qualifiers in another language are implemented (if ever). Otherwise, Bet/and shows up as Bet and the translation of "and".
//        $languageQualifier = wfMsg(strtolower($qualifier[$j]));
//        if ( substr($languageQualifier,0,4) == '&lt;' || substr($languageQualifier,0,1) == '<' ) {  // qualifier not found in user's preferred language - use English
//          $languageQualifier = $qualifier[$j];
//        }                      
      }
      if ( isset($month[$j]) ) {
        $languageMonth = wfMsg(strtolower($month[$j]));
        if ( substr($languageMonth,0,4) == '&lt;' || substr($languageMonth,0,1) == '<' ) {  // month not found in user's preferred language - use English
          $languageMonth = $month[$j];
        }
      }
      $languageDate .= ($languageDate ? ' ' : '') . (isset($qualifier[$j]) ? $languageQualifier . ' ' : '') . 
                        (isset($day[$j]) ? $day[$j] . ' ' : '') . (isset($month[$j]) ? $languageMonth . ' ' : '') . (isset($year[$j]) ? $year[$j] : '') .
                        (isset($suffix[$j]) ? ' ' . $suffix[$j] : '');
    }
    if ( $text != '' ) {
      $formatedDate .= ($formatedDate == '' ? $text : ' ' . $text);
      $languageDate .= ($languageDate == '' ? $text : ' ' . $text);
    }
    return true;
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
    return @DateHandler::$GEDCOM_MONTHS[$m];
  }
  
  private static function getMonthNumber($m) {
    return @array_search(strtolower($m), DateHandler::$MONTHS);
  }

  private static function getQualifier($q) {
    return @DateHandler::$GEDCOM_QUALIFIERS[$q];
  }    
   
  private static function editSplitYear($firstPart, &$secondPart) {  // firstPart is an unknown field; secondPart qualifies as a valid year (numeric string without leading zeros)
    $num = $firstPart + 0;                     // force conversion to number
    if ( !DateHandler::isDoubleDating($num) ) {
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
