var MONTHS = {
   'january':1,
   'february':2,
   'march':3,
   'april':4,
   'may':5,
   'june':6,
   'july':7,
   'august':8,
   'september':9,
   'october':10,
   'november':11,
   'december':12,
   'jan':1,
   'feb':2,
   'mar':3,
   'apr':4,
   'jun':6,
   'jul':7,
   'aug':8,
   'sep':9,
   'oct':10,
   'nov':11,
   'dec':12,
   'febr':2,
   'sept':9
};

function isYear(y) {
   return (y >= 100 && y <= 3000);
}

function getAlphaMonth(mon) {
   return MONTHS[mon.toLowerCase()];
}

function isDay(d) {
   return (d >= 1 && d <= 31);
}

function isNumMonth(m) {
   return (m >= 1 && m <= 12);
}

function isNextYear(year, field) {
   if (field.length > 4) return false;
   var y = year.toString();
   if ((field === '00' && y.substr(2) === '99') || (field === '0' && y.substr(3) === '9')) return true;
   newYear = y.substr(0, 4-field.length)+field;
   return (parseInt(newYear) - parseInt(year) === 1);
}

function getDateFields(date) {
   var fields = [];
   var field = '';
   var isNumericField;
   // split on number-letter transition, or non-alphanumeric
   for (var i=0; i < date.length; i++) {
      var c = date.charAt(i);
      if (c >= '0' && c <= '9') {
         if (field.length > 0 && !isNumericField) {
            fields.push(field);
            field = '';
         }
         field += c;
         isNumericField = true;
      }
      // assume non-7bit-ascii characters are international
      // we'll eventually want to add international months to the MONTHS array
      else if ((c >= 'A' && c <= 'Z') || (c >= 'a' && c <= 'z') || c > '~') {
         if (field.length > 0 && isNumericField) {
            fields.push(field);
            field = '';
         }
         field += c;
         isNumericField = false;
      }
      else if (field.length > 0) {
         fields.push(field);
         field = '';
      }
   }
   if (field.length > 0) {
      fields.push(field);
   }
   return fields;
}

function getDateKey(date) {
   var year = 0;
   var month = 0;
   var day = 0;
   var result = '00000000';
   var fields = getDateFields(date);
   for (var i = 0; i < fields.length; i++) {
      var field = fields[i];
      var numField = parseInt(field);
      var m = getAlphaMonth(field);
      if (isYear(numField)) {
         if (year === 0) year = numField;
      }
      else if (m !== undefined) {
         if (month === 0) month = m;
      }
      else if (i > 0 && isYear(parseInt(fields[i-1])) && isNextYear(year, field)) {
         year++; // 1963/4 or 1963/64
      }
      else if (isDay(numField) &&
               (!isNumMonth(numField) ||
                (i > 0 && getAlphaMonth(fields[i-1]) !== undefined) ||
                (i < fields.length-1 && getAlphaMonth(fields[i+1]) !== undefined))) {
         if (day === 0) day = numField;
      }
      else if (isNumMonth(numField)) {
         if (month === 0) month = numField;
      }
   }
   if (year !== 0) {
      result = year.toString();
      if (month !== 0) {
         if (month < 10) {
            result += '0';
         }
         result += month.toString();
         if (day !== 0) {
            if (day < 10) {
               result += '0';
            }
            result += day.toString();
         }
         else {
            result += '00';
         }
      }
      else {
         result += '0000';
      }
   }
   return parseInt(result);
}

var dates = [
        '19 Mar 1963',
        '19Mar1963',
        '1963/4',
        'Mar 1963',
        '3/1963',
        '19.Mar.1963',
        '3/19/1963'
];
for (var i = 0; i < dates.length; i++) {
   console.log(dates[i]+" => "+getDateKey(dates[i]));
}
