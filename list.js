// Set global variables
		var dataView;
		var grid;

		var activeTrees;
		var data = [];

// Set up SlickGrid display columns
		var columns = [
			{id:"linkedName", name:"Name", field:"linkedName", cssClass:"cell-title", width:150, sortable: true},
			{id:"gender", name:"Gender", field:"gender", width:45},
			{id:"birthDate", name:"Birth Date", field:"birthDate", sortable: true},
			{id:"birthPlace", name:"Birth Place", field:"birthPlace", width:120, sortable: true},
			{id:"deathDate", name:"Death Date", field:"deathDate", sortable: true},
			{id:"deathPlace", name:"Death Place", field:"deathPlace", width: 120, sortable: true},
         {id:"trees", name:"Tree(s)", field:"trees", width:60, sortable: true}
		];

// Set SlickGrid options
		var options = {
			editable: false,
			enableAddRow: false,
			enableCellNavigation: true,
			asyncEditorLoading: false,
			forceFitColumns: true
		};


		var searchString = "";

// Define search filter (currently searches name, birth place, and death place)
		function myFilter(item) {

			var itemTrees = getWords(item.trees);
   		// Check if at least one of the item's trees is in the list of active trees.
         if (!activeTrees) {
            return false;
         }
         var inTrees = false;
         var activeTreesLength = activeTrees.length;
         for (var t = 0; t < activeTreesLength; t++) {
            var activeTree = activeTrees[t];
            if ((activeTree == 'wfNoTree' && !item.trees) || item.trees.indexOf(activeTree) >= 0) {
               inTrees = true;
               break;
            }
         }
			if (!inTrees){
				//alert("No inTrees!");
				return false;
			}
			else {
			var searchWords = getWords(searchString);
			var searchFields = ["name","birthPlace","deathPlace", "birthDate", "deathDate"];
         var searchFieldsLength = searchFields.length;
			if (searchWords){
				// Go through each of the words in the search string
            var searchWordsLength = searchWords.length;
				for (var j = 0; j < searchWordsLength; j++){
					var itemFound = false;
					searchWord = searchWords[j].toUpperCase();
					// Make sure that the word is in at least one of the search fields.
					for (var i = 0; i < searchFieldsLength; i++) {
						if (item[searchFields[i]].toUpperCase().indexOf(searchWord) != -1){
							itemFound = true;
						}
					}
					if (itemFound === false){
						return false;
					}
			}
		}
			return true;
			}
		}

// Given a string of words separated by commas and/or spaces, return an array of the words.
		function getWords(wordString){
			pattern = /[^, ]+/g;
			wordArray = wordString.match(pattern);
			return wordArray;
		}

// This is my function to do a sort by name any time we change the data.

function initialSort(){
	sortcol = "linkedName";
	dataView.sort(comparer, 1);
}

// This is the comparer. Right now, it just sorts based on the name.
	function comparer(a,b) {
		if (sortcol === "linkedName"){
			var x = a["name"], y = b["name"];
	}
		else if (sortcol === "birthDate"){
			var x = a["birthDateKey"], y = b["birthDateKey"];
		}
		else if (sortcol === "deathDate"){
			var x = a["deathDateKey"], y = b["deathDateKey"];
		}
		else {
			var x = a[sortcol], y = b[sortcol];
		}
		return (x == y ? 0 : (x > y ? 1 : -1));
} 

// This function finds the number of rows in the data view, and sticks it into the rowCount div.
	function updateRowCount() {
		var rowCount = dataView.getLength();
		var content;
		if (rowCount === 1){
			content = '<b>' + rowCount.toString() + '</b> person found'
		}
		else {
			content = '<b>' + rowCount.toString() + '</b> people found'
		}
		$("#rowCount").html(content);
	}
		
	// This function takes a user, and returns a list of their active trees.
	function getTreeList(user){
// need the www so we pick up the sign-in cookie
		var jsonURL = '/w/index.php?action=ajax&rs=wfGetTrees&user=' + user + '&callback=?';
		$.getJSON(jsonURL,function(json){
		// Create the initial select box
		$("#treeFilter").append('<label>Tree: </label><select id="treeSelect" name="treeSelect" multiple="multiple"></select>')
		$.each(json, function(key,text){
			// Create an option in the select menu for each tree
			$('#treeSelect').append('<option value="' + text + '">' + text + '</option>')
		});
      $('#treeSelect').append('<option value="wfNoTree">No Tree</option>');
	// Initialize the select menu as a multiselect.
		$("#treeSelect").multiselect({
      noneSelectedText: 'Check trees',
		selectedText: function(numChecked, numTotal, checkedItems) {
         if (numChecked == numTotal) {
            return 'All trees';
         }
         else if (numChecked == 1) {
            return $(checkedItems[0]).attr("title");
         }
         else {
            return numChecked+' selected';
         }
      },
		checkAllText: "Check all trees",
		create: function(event, ui){
			$("#treeSelect").multiselect("checkAll");
			activeTrees = $.map($(this).multiselect("getChecked"), function( input ){
            return input.value;
	});
}

		})
		.bind("multiselectclick multiselectcheckall multiselectuncheckall", function(event, ui){
			activeTrees = $.map($(this).multiselect("getChecked"), function( input ){
            return input.value;
		 });
			dataView.refresh();
});
	// Loads all of the people for a given user.
	loadTree(user);
	});
}

	// This function takes a user name and tree name, loads the data, and initializes the grid. This has to be called before the grid shows up.
	function loadTree(user){
		// Masks the div, and puts up a loading image
		$("#myGrid").mask("Loading...");
// need the www so we pick up the sign-in cookie
		var jsonURL = '/w/index.php?action=ajax&rs=wfGetWatchlist&user=' + user + '&callback=?';
		$.getJSON(jsonURL,function(json){
	var items = [];
	// Go through each item and put it into the data object
  $.each(json, function(key, val) {
	  var d = (data[key] = {});

				d["id"] = "id_" + key;
				d["title"] = val.title;
				// This is used for searching, not display
// surname and given name may be missing; default to "" like other fields
				d["name"] = (val.surname||"") + ", " + (val.given||"");
				d["linkedName"] = '<a href="/wiki/Person:' + val.title + '">' + (val.surname||"") + ', ' + (val.given||"") + '</a>'||"";
				d["gender"] = val.gender||"";
				d["birthDate"] = val.birthDate||"";
				d["birthDateKey"] = getDateKey(val.birthDate||"");
				d["birthPlace"] = val.birthPlace||"";
				d["deathDate"] = val.deathDate||"";
				d["deathDateKey"] = getDateKey(val.deathDate||"");
				d["deathPlace"] = val.deathPlace||"";
                d["trees"] = val.trees||"";
			});



// Everything below here is copied directly from the SlickGrid example

			// initialize the model
			dataView = new Slick.Data.DataView();
			dataView.beginUpdate();
			dataView.setItems(data);
			dataView.setFilter(myFilter);
			dataView.endUpdate();


			// initialize the grid
			grid = new Slick.Grid("#myGrid", dataView, columns, options);


            
// We don't need something like this until we allow people to click on a star to flag a row (like gmail)
//			grid.onClick.subscribe(function(e,args) {
//                if ($(e.target).hasClass("toggle")) {
//                    var item = dataView.getItem(args.row);
//                    if (item) {
//                        if (!item._collapsed)
//                            item._collapsed = true;
//                        else
//                            item._collapsed = false;
//
//                        dataView.updateItem(item.id, item);
//                    }
//                    e.stopImmediatePropagation();
//                }
//            });
            

         grid.onSort.subscribe(function(e, args) {
			sortdir = args.sortAsc ? 1 : -1;
			sortcol = args.sortCol.field; 
			dataView.sort(comparer, args.sortAsc);
		});


			// wire up model events to drive the grid
			dataView.onRowCountChanged.subscribe(function(e,args) {
				grid.updateRowCount();
                grid.render();
                updateRowCount();
			});

			dataView.onRowsChanged.subscribe(function(e,args) {
				grid.invalidateRows(args.rows);
				grid.render();
			});


			var h_runfilters = null;


			// wire up the search textbox to apply the filter to the model
			$("#txtSearch").keyup(function(e) {
                Slick.GlobalEditorLock.cancelCurrentEdit();

				// clear on Esc
				if (e.which == 27)
					this.value = "";

				searchString = this.value;
				dataView.refresh();
			})
		initialSort(); // Ok - this is mine, not from SlickGrid.
		updateRowCount();
		$("#myGrid").unmask();
		})
		};


// Dallan's sortable date code.

var MONTHS = {
	'january': 1,
	'february': 2,
	'march': 3,
	'april': 4,
	'may': 5,
	'june': 6,
	'july': 7,
	'august': 8,
	'september': 9,
	'october': 10,
	'november': 11,
	'december': 12,
	'jan': 1,
	'feb': 2,
	'mar': 3,
	'apr': 4,
	'jun': 6,
	'jul': 7,
	'aug': 8,
	'sep': 9,
	'oct': 10,
	'nov': 11,
	'dec': 12,
	'febr': 2,
	'sept': 9
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
	if (field.length > 4) return
	false;
	var y = year.toString();
	if ((field === '00' && y.substr(2) === '99') || (field === '0' && y.substr(3) === '9')) return true;
	newYear = y.substr(0, 4 - field.length) + field;
	return (parseInt(newYear) - parseInt(year) === 1);
}

function getDateFields(date) {
	var fields = [];
	var field = '';
	var isNumericField; // split on number-letter transition, or non - alphanumeric
	for (var i = 0; i < date.length; i++) {
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
		} else if (field.length > 0) {
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
		} else if (m !== undefined) {
			if (month === 0) month = m;
		} else if (i > 0 && isYear(parseInt(fields[i - 1])) && isNextYear(year, field)) {
			year++; // 1963/4 or 1963/64
			}
			else if (isDay(numField) && (!isNumMonth(numField) || (i > 0 && getAlphaMonth(fields[i - 1]) !== undefined) || (i < fields.length - 1 && getAlphaMonth(fields[i + 1]) !== undefined))) {
				if (day === 0) day = numField;
			} else if (isNumMonth(numField)) {
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
				} else {
					result += '00';
				}
			} else {
				result += '0000';
			}
		}
		return parseInt(result);
	}



// Ready, set, go.
$(function()
		{
// Get user from url: user parameter for testing, or whatever follows Special:List/ for production
// default is empty, which returns logged-in user
			var user = '';
			var results = new RegExp('(Special:ListPages/|\\?user=)([^&#]*)').exec(window.location.href);
			if (results) {
				user = results[2];
			}
			getTreeList(user);
		}
		);
