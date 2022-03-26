// Based on a script by Dylan Verheul at http://www.dyve.net/jquery/?autocomplete
// with modifications by Dallan Quass
var acCache = {};
acCache.length = 0;
var acCacheMore = {};
acCacheMore.length = 0;

function findPos(obj) {
	var curleft = 0;
	var curtop = 0;
	if (obj.offsetParent) {
	  curleft = parseInt(obj.offsetLeft)
	  curtop = parseInt(obj.offsetTop)
	  while (obj = obj.offsetParent) {
	    curleft += parseInt(obj.offsetLeft)
	    curtop += parseInt(obj.offsetTop)
	  }
	}
	return {x:curleft,y:curtop};
}

$.autocomplete = function(input, options) {
	// Create jQuery object for input element; turn off browser autocomplete
	var $input = $(input).attr("autocomplete", "off");

	// Apply inputClass if necessary
	if (options.inputClass) $input.addClass(options.inputClass);

	// Create results
	var results = document.createElement("div");
	// if you change the id, change it in move and remove as well
	results.id = input.name+'results';
	// Create jQuery object for results
	var $results = $(results);
	// Set default values for results
	var pos = findPos(input);
	// if you change the css, change it in move as well
	$results.hide().addClass(options.resultsClass).css({
		position: "absolute",
		top: (pos.y + input.offsetHeight) + "px",
		left: pos.x + "px",
		overflow: "auto"
	});
	// Add to body element
	$("body").append(results);

	input.lastSelected = splitMultiLine($input.val(),1);

	var timeout = null;
	var active = -1;
	var requestTimeout = null;
	var formTimeout = null;
	var requestQuery = null;
	var returnFalse = function() { return false; };
	var hasFocus = false;

	$input
	.keydown(function(e) {
      if (!$input.attr('readonly')) {
         switch(e.keyCode) {
            case 38: // up
               if ($results.is(":visible")) {
                  e.preventDefault();
                  moveSelect(-1);
               }
               break;
            case 40: // down
               if ($results.is(":visible")) {
                  e.preventDefault();
                  moveSelect(1);
               }
               break;
            case 33: // pgup
               if ($results.is(":visible")) {
                  e.preventDefault();
                  moveSelect(-options.pgSize);
               }
               break;
            case 34: // pgdn
               if ($results.is(":visible")) {
                  e.preventDefault();
                  moveSelect(options.pgSize);
               }
               break;
            case 9:	 // tab
            case 13: // return
               if (selectCurrent()) {
                  e.preventDefault();
                     // hack due to Safari, which ignores preventDefault
                  if (options.formId && e.keyCode == 13) {
                     $('#'+options.formId).bind('submit',returnFalse)
                     if (formTimeout) clearTimeout(formTimeout);
                     formTimeout = setTimeout(enableForm, 250);
                  }
               }
               break;
            case 27: // esc
               active = -1;
               hideResults(200);
               break;
            default:
               active = -1;
               if (timeout) clearTimeout(timeout);
               // space , / get a short delay; others get a long delay
               var delay = (e.keyCode == 32 || e.keyCode == 188 || e.keyCode == 191) ? options.shortDelay : options.longDelay;
               timeout = setTimeout(onChange, delay);
               break;
         }
      }
	})
	.focus(function() {
	   hasFocus = true;
	})
	.blur(function() {
	   hasFocus = false;
		hideResults(200);
	});

	hideResultsNow();

	function enableForm() {
		if (formTimeout) clearTimeout(formTimeout);
		formTimeout = null;
		$('#'+options.formId).unbind('submit',returnFalse);
	}

   function addStoredMatches(q, data) {
      var key = getAcStorageKey(options.defaultNs);
      try {
         var titles = $.jStorage.get(key, '');
         if (titles.length > 0) {
            titles = titles.split('|');
            for (var i = titles.length-1; i >= 0; i--) {
               var test = titles[i].substr(0, q.length);
               if (options.ignoreCase) test = test.toLowerCase();
               if (test === q && (!options.matchCommaPhrases || titles[i].indexOf('#') > 0)) {
                  var titleInfo = titles[i].split('#');
                  var title = titleInfo[0];
                  var info = titleInfo.length > 1 ? titleInfo[1] : '';
                  var multiLine = titleInfo.length > 1;
                  if (data) {
                     // remove from later in array
                     for (var j = data.length-1; j >= 0; j--) {
                        if (data[j].length > 1) multiLine = true;
                        if (data[j][0] === title && (!options.matchCommaPhrases || data[j][1] === info)) {
                           data.splice(j,1);
                        }
                     }
                  }
                  else {
                     data = new Array();
                  }
                  // add to beginning
                  titleInfo = new Array();
                  titleInfo[0] = title;
                  if (multiLine) titleInfo[1] = info;
                  data.unshift(titleInfo);
               }
            }
         }
      }
      catch (e) {
         // ignore
      }
      return data;
   }

   function storeSelected(title, info) {
      var key = getAcStorageKey(options.defaultNs);
      try {
         var titles = $.jStorage.get(key,'');
         if (titles.length == 0) {
            titles = new Array();
         }
         else {
            titles = titles.split('|');
            for (var i = 0; i < titles.length; i++) {
               var titleInfo = titles[i].split('#');
               if (titleInfo[0] == title) {
                  titles.splice(i,1);
                  break;
               }
            }
         }
         if (titles.length >= 100) titles.pop();
         if (info) title = title+'#'+info;
         titles.unshift(title);
         $.jStorage.set(key, titles.join('|'));
      }
      catch (e) {
         // ignore
      }
   }

	function onChange() {
		var v = splitMultiLine($input.val(),1);
		if (options.matchCommaPhrases) {
		   v = v.replace(/^(\s*,\s*)+/,'');  // remove opening commas
		}
		// request data on 3 or more characters
		// ignore everything if there's a bar
		if (v.indexOf('|') < 0 && v.length >= 3) {
			requestData(v);
		} else {
			$input.removeClass(options.loadingClass);
			$results.hide();
		}
	};

	function moveSelect(step) {

	  var lis = $("li", results);
	  if (!lis) return;

	  active += step;

	  if (active < 0) {
	    active = 0;
	  } else if (active >= lis.size()) {
	    active = lis.size() - 1;
	  }

	  // scroll if necessary
	  if (lis[active]) {
	    if (lis[active].offsetTop < results.scrollTop) {
	      results.scrollTop = lis[active].offsetTop;
	    }
	    else if ((lis[active].offsetTop+lis[active].offsetHeight) > (results.scrollTop+results.offsetHeight)) {
	      results.scrollTop = (lis[active].offsetTop + lis[active].offsetHeight) - results.offsetHeight;
	    }
	  }

	  lis.removeClass("ac_over");
	  $(lis[active]).addClass("ac_over");

	// Weird behaviour in IE
	// if (lis[active] && lis[active].scrollIntoView) {
	//	lis[active].scrollIntoView(false);
	// }
	};

	function selectCurrent() {
		var li = $("li.ac_over", results)[0];
		if (!li) {
			var $li = $("li", results);
			if (options.selectOnly) {
				if ($li.length == 1) li = $li[0];
			} else if (options.selectFirst) {
				li = $li[0];
			}
		}
		if (li) {
			selectItem(li);
			return true;
		} else {
			return false;
		}
	};



	function selectItem(li) {
		if (!li) {
			li = document.createElement("li");
			li.selectValue = "";
		}
      var v = $.trim(li.selectValue ? li.selectValue : li.innerHTML);
      storeSelected(v, li.infoValue);
		input.lastSelected = v;
		$results.html("");
		var v0 = splitMultiLine($input.val(),0);
		$input.val(v0+v);
		// scroll textarea to end
		if (input.type == 'textarea') {
		   input.scrollTop = input.scrollHeight;
		}
		selectText(input, input.value.length, input.value.length);
		hideResults(1);
		if (options.onItemSelect) setTimeout(function() { options.onItemSelect(li) }, 1);
	};

	function hideResults(ms) {
		if (timeout) clearTimeout(timeout);
		timeout = setTimeout(hideResultsNow, ms);
	};

	function hideResultsNow() {
		if (timeout) clearTimeout(timeout);
		timeout = null;
		$input.removeClass(options.loadingClass);
		if ($results.is(":visible")) {
			$results.hide();
		}
		if (options.mustMatch) {
			var v = splitMultiLine($input.val(),1);
			if (v != input.lastSelected) {
				selectItem(null);
			}
		}
	};

	function receiveData(data) {
		$input.removeClass(options.loadingClass);
		if (hasFocus && data && data.length > 0) {
			results.innerHTML = "";
			if ($.browser.msie) {
				// we put a styled iframe behind the list so HTML SELECT elements don't show through
				// cmt out because it causes background not to show
//				$results.append(document.createElement('iframe'));
			}
			results.appendChild(dataToDom(data));
// HACK - fotonotes (fnclientwiki.js) moves later div's, so reposition the results div now
//         if ($('.fn-image').get(0)) {
//            $input.autocompleteMove();
//         }
         $input.autocompleteMove();
			$results.show();
		} else {
			hideResultsNow();
		}
	};

	function getMore(data) {
	   if (!data) return false;
	   var results = data.getElementsByTagName('results');
	   if (results.length > 0) {
	      var status = results[0].getAttribute('status');
	      return (status == 'more');
	   }
	   return false;
	}

	function parseData(data, more) {
	   //TODO someday, check status; if != 'success', do something
	  if (!data) return null;
	  var records = [];
	  var elms = data.getElementsByTagName('result');
     var fields;
	  for (var i = 0; i < elms.length; i++) {
	  	 fields = [];
	    var title = elms[i].getElementsByTagName('title')[0].firstChild.nodeValue;
	    if (options.defaultNs && title.substr(0,options.defaultNs.length+1) == (options.defaultNs + ':')) {
	       title = title.substr(options.defaultNs.length+1);
	    }
	    fields[0] = title;

	    var nodes = elms[i].getElementsByTagName('fullName');
	    if (nodes.length > 0) {
   	    fields[1] = nodes[0].firstChild.nodeValue;
	    }
	    records[i] = fields;
	  }
	  records.sort(sortRecords);
	  if (more) {
	     fields = [];
	     fields[0] = 'more not shown';
	     records[elms.length] = fields;
	  }
	  return records.length > 0 ? records : null;
	};
	
	//TODO: sort ID numbers
	function sortRecords(a,b) {
		 var s1 = a[0]; 
		 var s2 = b[0];
		 if (s1 < s2) {
		    return -1;
		 }
		 else if (s1 > s2) {
		    return 1;
		 }
		 return 0;
	};
	
	function parsePlaceData(data) {
	  if (!data) return null;
	  var records = [];
	  var elms = data.getElementsByTagName('result');
     var fields;
	  for (var i = 0; i < elms.length; i++) {
	  	 fields = [];
	  	 fields[0] = elms[i].getElementsByTagName('name')[0].firstChild.nodeValue;
	  	 fields[1] = elms[i].getElementsByTagName('title')[0].firstChild.nodeValue;
	    records[i] = fields;
	  }
	  return records.length > 0 ? records : null;
	};

	function dataToDom(data, more) {
		var ul = document.createElement("ul");
		var num = data.length;
		for (var i=0; i < num; i++) {
			var row = data[i];
			if (!row) continue;
			var li = document.createElement("li");
			if (options.formatItem) {
				li.innerHTML = options.formatItem(row, i, num);
			}
			else if (row.length > 1 && row[1]) {
				li.innerHTML = "<b>" + row[0] + "</b><div style=\"font-size: 85%;\"> &nbsp; &nbsp; " + row[1] + "</div>";
            li.infoValue = row[1];
			}
			else {
			   li.innerHTML = row[0];
			}
			if (options.matchCommaPhrases && row[0] != row[1]) {
				li.selectValue = row[1]+'|'+row[0];
			}
			else {
	   		li.selectValue = row[0];
			}
			ul.appendChild(li);
			$(li).hover(
				function() {
					   $("li", ul).removeClass("ac_over");
					   $(this).addClass("ac_over"); },
				function() {
					   $(this).removeClass("ac_over"); }
			).click(function(e) { e.preventDefault(); e.stopPropagation(); selectItem(this) });
		}
		return ul;
	};

	// no longer used
	function trimQuery(q) {
	   if (options.matchCommaPhrases) {
		   var cpos = q.indexOf(',');
			if (cpos > 0) {
			   q = q.substr(0,cpos);
			}
			var ppos = q.indexOf('(');
			if (ppos > 0) {
			   q = q.substr(0,ppos);
			}
         return q;
	   }
	   else { // trim ending non-alphanumeric
	      for (var i = q.length-1; i >= 0; i--) {
            if (/[A-Za-z0-9]/.test(q.substr(i,1))) {
               return q.substr(0,i+1);
            }
	      }
	      return '';
	   }
   }

	function requestData(q) {
		q = $.trim(q);
		if (options.ignoreCase) q = q.toLowerCase();
		var data = !options.dontCache ? loadFromCache(q) : null;
		if (data) {
         data = addStoredMatches(q, data);
			receiveData(data);
		}
		else {
			// q = trimQuery(q);
			// don't make the same request too often
			// wait for the earlier request to return
			if (setRequestTimeout(q)) {
				$input.addClass(options.loadingClass);
				$.get(makeUrl(q), function(data) {
					resetRequestTimeout();
					var more = false;
					if (options.matchCommaPhrases) {
						data = parsePlaceData(data);
					}
					else {
						more = getMore(data);
						data = parseData(data, more);
					}
					if (data) {
						if (!options.dontCache) {
							addToCache(q, data, more);
						}
						var currText = splitMultiLine(input.value,1);
						if (options.ignoreCase) {
							currText = currText.toLowerCase();
						}
	         		if (options.matchCommaPhrases) {
	         			currText = currText.replace(/^(\s*,\s*)+/,'');  // remove opening comma
	         		}
	         		if (!options.dontCache) {
	         			data = loadFromCache(currText);
	         		}
					}
     	         data = addStoredMatches(q, data);
					receiveData(data);
				});
			}
		}
	};

	function setRequestTimeout(q) {
		 if (requestTimeout && requestQuery == q) {
		    return false;
		 }
		 resetRequestTimeout();
		 requestTimeout = setTimeout(resetRequestTimeout, options.requestDelay);
		 requestQuery = q;
		 return true;
	}

	function resetRequestTimeout() {
		 if (requestTimeout) {
		    clearTimeout(requestTimeout);
		 }
		 requestTimeout = null;
	}

	function makeUrl(q) {
		var url = options.url + q;
		if (options.defaultNs) {
		   url += "&ns=" + options.defaultNs;
		}
		if (options.userid) {
		   url += "&userid=" + options.userid;
		}
		for (var i in options.extraParams) {
			url += "&" + i + "=" + options.extraParams[i];
		}
		return url;
	};
	
	function getCache(q) {
		return acCache[options.defaultNs+':'+q];
	}
	function setCache(q,data) {
		return acCache[options.defaultNs+':'+q] = data;
	}
	function getCacheMore(q) {
		return acCacheMore[options.defaultNs+':'+q];
	}
	function setCacheMore(q, more) {
		return acCacheMore[options.defaultNs+':'+q] = more;
	}
	
	function loadFromCache(q) {
	  if (!q) return null;
	  if (getCache(q)) {
	  	return getCache(q);
	  }
	  if (options.matchSubset) {
	     for (var i = q.length - 1; i >= 1; i--) {
      		var qs = q.substr(0, i);
   			var c = getCache(qs);
   			if (c) {
   			  if (getCacheMore(qs)) {
   			    return null;
   			  }
   			  var csub = [];
   			  for (var j = 0; j < c.length; j++) {
   			    var x = c[j];
   			    if (matchSubset(x, q)) {
   			      csub[csub.length] = x;
   			    }
   			  }
   			  return csub;
   	      }
	     }
	  }
	  return null;
	};

	function matchSubset(cacheObj, query) {
		var cacheText = cacheObj[0];
		if (options.ignoreCase) {
			cacheText = cacheText.toLowerCase();
		}
		if (options.matchCommaPhrases) {
			var queryPhrases = query.split(',');
			var cachePhrases = cacheText.split(',');
			for (var i = 0; i < queryPhrases.length; i++) {
				var qp = $.trim(queryPhrases[i]);
				if (qp.length > 0) {
					var found = false;
					for (var j = i; j < cachePhrases.length; j++) {
						if (cachePhrases[j].indexOf(qp) >= 0) {
							found = true;
							break;
						}
					}
					if (!found) {
						return false;
					}
				}
			}
			return true;
		}
		else {
			return cacheText.indexOf(query) == 0;
		}
	}

	flushCache = function() {
		acCache = {};
		acCache.length = 0;
		acCacheMore = {};
		acCacheMore = 0;
	}

	setExtraParams = function(p) {
		options.extraParams = p;
	}

	function addToCache(q, data, more) {
		if (!data || !q || options.dontCache) return;
		if (acCache.length > 20) { // max number of cache entries
			flushCache();
		}
		if (!getCache(q)) {
			acCache.length++;
			acCacheMore.length++;
		}
		setCache(q, data);
		setCacheMore(q, more);
	}

	// if last, return last line; else return up to last line
	function splitMultiLine(s,last) {
		s = $.trim(s);
		var pos = s.lastIndexOf("\n");
		if (pos < 0) return (last ? s : '');
		return (last ? s.substr(pos+1) : s.substr(0,pos+1));
	}

	function selectText(inp,start,end) {

		 if (inp.setSelectionRange) { // for Mozilla
		    inp.setSelectionRange(start,end);
		 }
		 else if (inp.createTextRange) { // for IE
		    var textRange = inp.createTextRange();
		    textRange.moveStart("character", start);
		    textRange.moveEnd("character", end - inp.value.length);
		    textRange.select();
		 }
		 else {
		    inp.select();
		 }
	}
}

$.fn.autocomplete = function(options) {
	// Make sure options exists
	options = options || {};
	// Set default values for required options
	options.url = options.url || '/w/ac.php?title=';
	options.inputClass = options.inputClass || "ac_input";
	options.resultsClass = options.resultsClass || "ac_results";
	options.ignoreCase = options.ignoreCase || 0;
	options.matchSubset = options.matchSubset || 1;
	options.dontCache = options.dontCache || false;
	options.mustMatch = options.mustMatch || 0;
	options.extraParams = options.extraParams || {};
	options.loadingClass = options.loadingClass || "ac_loading";
	options.selectFirst = options.selectFirst || false;
	options.selectOnly = options.selectOnly || false;
	options.shortDelay = options.shortDelay || 200;
	options.longDelay = options.longDelay || 1000;
	options.requestDelay = options.requestDelay || 2000;
	options.defaultNs = options.defaultNs || '';
	options.userid = options.userid || '';
	options.matchCommaPhrases = options.matchCommaPhrases || 0;
	options.pgSize = options.pgSize || 8;
   options.formId = options.formId || 'editForm';
   options.redirSymbol = options.redirSymbol || '=&gt; ';
   //options.formatItem

	// HACK if matchCommaPhrases, set defaultNs
	if (options.matchCommaPhrases && !options.defaultNs) {
		options.defaultNs = 'Place';
	}
	
	this.each(function() {
		var input = this;
		new $.autocomplete(input, options);
	});

	// Don't break the chain
	return this;
}

$.fn.autocompleteMove = function() {
	this.each(function() {
		var pos = findPos(this);
		$('#'+this.name+'results').css({
			position: "absolute",
			top: (pos.y + this.offsetHeight) + "px",
			left: pos.x + "px",
			overflow: "auto"
		});
	});
	return this;
}

$.fn.autocompleteRemove = function() {
	this.each(function() {
		$('#'+this.name+'results').remove();
		$(this).unbind('keydown');
	});
	return this;
}

$(document).ready(function() {
	$(".nocemetery_input").autocomplete({ defaultNs:'NoCemetery', dontCache: true, matchCommaPhrases:1, ignoreCase:1});
	$(".place_input").autocomplete({ defaultNs:'Place', dontCache: true, matchCommaPhrases:1, ignoreCase:1});
	$(".person_input").autocomplete({ defaultNs:'Person', userid:userId});
	$(".family_input").autocomplete({ defaultNs:'Family', userid:userId});
	$(".image_input").autocomplete({ defaultNs:'Image', userid:userId});
	$(".main_input").autocomplete({ defaultNs:'', userid:userId});
   $(".mysource_input").autocomplete({ defaultNs:'MySource', userid:userId});
	$(".source_input").autocomplete({ defaultNs:'Source', userid:0});
	$(".repository_input").autocomplete({ defaultNs:'Repository', userid:0});
   $(".transcript_input").autocomplete({ defaultNs:'Transcript', userid:userId});
});
