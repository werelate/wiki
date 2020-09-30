// SpecialAddPage and SpecialSearch share this script
function showFocusSearchFields(focus) {
	$('#name_row').hide();
	$('#givenname_cell1').hide();
	$('#givenname_cell2').hide();
   $('#place_row').hide();
   $('#source_place_row').hide();
	$('#birth_row').hide();
	$('#death_row').hide();
	$('#father_row').hide();
	$('#mother_row').hide();
	$('#spouse_row').hide();
	$('#husband_row').hide();
	$('#wife_row').hide();
	$('#marriage_row').hide();
	$('#placename_row').hide();
	$('#subject_row').hide();
	$('#source_title_row').hide();
	$('#author_row').hide();
	$('#title_row').hide();
	$('#coverage_row').hide();
   $("#watch option[value='ws']").attr('disabled', 'disabled');
	var focusPath;
	var ns = $('#ns').val();
	switch(ns)
	{
	case 'All':
		$('#name_row').show();
		$('#givenname_cell1').show();
		$('#givenname_cell2').show();
		focusPath = '#input_g';
		$('#place_row').show();
	  	break;    
	case 'Image':
		$('#name_row').show();
		$('#givenname_cell1').show();
		$('#givenname_cell2').show();
		focusPath = '#input_g';
		$('#place_row').show();
		$('#title_row').show();
	  	break;    
	case 'Article':
	case 'User':
   case 'Transcript':
		$('#name_row').show();
		focusPath = '#input_s';
		$('#place_row').show();
		$('#title_row').show();
	  	break;    
	case 'Source':
		$('#name_row').show();
		focusPath = '#author_row input';
      $('#place_row').show();
      $('#source_place_row').show();
		$('#subject_row').show();
		$('#source_title_row').show();
		$('#coverage_row').show();
		$('#author_row').show();
		break;
	case 'MySource':
		$('#name_row').show();
		focusPath = '#input_s';
		$('#place_row').show();
		$('#author_row').show();
		$('#title_row').show();
	  	break;    
	case 'Person':
		$('#name_row').show();
		$('#givenname_cell1').show();
		$('#givenname_cell2').show();
		focusPath = '#input_g';
		$('#birth_row').show();
		$('#death_row').show();
		$('#father_row').show();
		$('#mother_row').show();
		$('#spouse_row').show();
      if (!$('#watch').attr('disabled')) {
         $("#watch option[value='ws']").removeAttr('disabled');
      }
	  	break;
	case 'Family':
		$('#husband_row').show();
		focusPath = '#husband_row input';
		$('#wife_row').show();
		$('#marriage_row').show();
      if (!$('#watch').attr('disabled')) {
         $("#watch option[value='ws']").removeAttr('disabled');
      }
	  	break;
	case 'Place':
		$('#placename_row').show();
		focusPath = '#placename_row input';
	  	break;    
	case 'Repository':
		$('#place_row').show();
		focusPath = '#place_row input';
		$('#title_row').show();
	  	break;    
	default:
		$('#title_row').show();
		focusPath = '#title_row input';
	  	break; 
	}   
   if (ns != 'Person' && ns != 'Family' && $('#watch option:selected').val() == 'ws') {
      $('#watch').val('wu');
   }
   var fp = $(focusPath);
	if (focus && fp.length > 0) fp[0].focus();
}

function showSearchFields() {
	showFocusSearchFields(false);
}

function bindExactClick() {
   $('#ecp').change(function () {
      var val = $('#ecp option:selected').val();
      if (val == 'e' || val == 'c') {
         $("#sort option[value='title']").removeAttr('disabled');
         $("#sort option[value='date']").removeAttr('disabled');
      }
      else { // val == 'p'
         $('#sort').val('score');
         $("#sort option[value='title']").attr('disabled', 'disabled');
         $("#sort option[value='date']").attr('disabled', 'disabled');
      }
   }).trigger('change');
}

// Disable/enable structured search criteria based on "Include talk" checkbox and vice versa (added Sep 2020 by Janet Bjorndahl) 
function bindIncludeTalk() {
   $('#checkbox_talk').change(function() {
      if (document.getElementById("checkbox_talk").checked) {     
         $("#input_g").attr('disabled', 'disabled');
         $("#input_s").attr('disabled', 'disabled');
         $("#input_p").attr('disabled', 'disabled');
         $("#input_bd").attr('disabled', 'disabled');
         $("#input_bp").attr('disabled', 'disabled');
         $("#input_dd").attr('disabled', 'disabled');
         $("#input_dp").attr('disabled', 'disabled');
         $("#input_fg").attr('disabled', 'disabled');
         $("#input_fs").attr('disabled', 'disabled');
         $("#input_mg").attr('disabled', 'disabled');
         $("#input_ms").attr('disabled', 'disabled');
         $("#input_sg").attr('disabled', 'disabled');
         $("#input_ss").attr('disabled', 'disabled');
         $("#input_hg").attr('disabled', 'disabled');
         $("#input_hs").attr('disabled', 'disabled');
         $("#input_wg").attr('disabled', 'disabled');
         $("#input_ws").attr('disabled', 'disabled');
         $("#input_md").attr('disabled', 'disabled');
         $("#input_mp").attr('disabled', 'disabled');
         $("#input_pn").attr('disabled', 'disabled');
         $("#input_li").attr('disabled', 'disabled');
         $("#input_a").attr('disabled', 'disabled');
         $("#br").attr('disabled', 'disabled');
         $("#dr").attr('disabled', 'disabled');
         $("#mr").attr('disabled', 'disabled');
         $("#su").attr('disabled', 'disabled');
         $("#sa").attr('disabled', 'disabled');
         $("#checkbox_sub").attr('disabled', 'disabled');
         $("#checkbox_sup").attr('disabled', 'disabled');
      }
      else {
         $("#input_g").removeAttr('disabled');
         $("#input_s").removeAttr('disabled');
         $("#input_p").removeAttr('disabled');
         $("#input_bd").removeAttr('disabled');
         $("#input_bp").removeAttr('disabled');
         $("#input_dd").removeAttr('disabled');
         $("#input_dp").removeAttr('disabled');
         $("#input_fg").removeAttr('disabled');
         $("#input_fs").removeAttr('disabled');
         $("#input_mg").removeAttr('disabled');
         $("#input_ms").removeAttr('disabled');
         $("#input_sg").removeAttr('disabled');
         $("#input_ss").removeAttr('disabled');
         $("#input_hg").removeAttr('disabled');
         $("#input_hs").removeAttr('disabled');
         $("#input_wg").removeAttr('disabled');
         $("#input_ws").removeAttr('disabled');
         $("#input_md").removeAttr('disabled');
         $("#input_mp").removeAttr('disabled');
         $("#input_pn").removeAttr('disabled');
         $("#input_li").removeAttr('disabled');
         $("#input_a").removeAttr('disabled');
         $("#br").removeAttr('disabled');
         $("#dr").removeAttr('disabled');
         $("#mr").removeAttr('disabled');
         $("#su").removeAttr('disabled');
         $("#sa").removeAttr('disabled');
         $("#checkbox_sub").removeAttr('disabled');
         $("#checkbox_sup").removeAttr('disabled');
      }
   }).trigger('change');
   
   // If any structured criteria entered (with minor exceptions), disable "Include talk" since the search engine won't return Talk pages anyway
   // This code needs to be kept in sync with similar code in SpecialSearch.php
   $('#searchform').change(function() {
      if ( document.getElementById("input_g").value ||          
           document.getElementById("input_s").value ||          
           document.getElementById("input_p").value ||          
           document.getElementById("input_bd").value ||          
           document.getElementById("input_bp").value ||          
           document.getElementById("input_dd").value ||          
           document.getElementById("input_dp").value ||          
           document.getElementById("input_fg").value ||          
           document.getElementById("input_fs").value ||          
           document.getElementById("input_mg").value ||          
           document.getElementById("input_ms").value ||          
           document.getElementById("input_sg").value ||          
           document.getElementById("input_ss").value ||          
           document.getElementById("input_hg").value ||          
           document.getElementById("input_hs").value ||          
           document.getElementById("input_wg").value ||          
           document.getElementById("input_ws").value ||          
           document.getElementById("input_md").value ||          
           document.getElementById("input_mp").value ||          
           document.getElementById("input_pn").value ||          
           document.getElementById("input_li").value ||  
           document.getElementById("input_a").value ||               
           document.getElementById("su").value ||          
           document.getElementById("sa").value ) {
         $("#checkbox_talk").attr('disabled', 'disabled');
      }   
      else {
      // Enable "Include talk" only if no structured criteria entered and not comparing or adding pages
         if ( document.getElementsByName("match").length == 0 &&
              document.getElementsByName("target").length == 0) {
            $("#checkbox_talk").removeAttr('disabled');
         }   
      }
   });
}

function closeWindow() {
   window.close();
}

function getFormValue(fieldName) {
   var input = $('#input_'+fieldName);
   if (input.length > 0) {
      return input.val();
   }
   else {
      return '';
   }
}

function updateTarget(target, nsText, title) {
   var key = getAcStorageKey(nsText);
   try {
      var titles = $.jStorage.get(key,'');
      if (!titles || titles.length == 0) {
         titles = new Array();
      }
      else {
         titles = titles.split('|');
         for (var i = 0; i < titles.length; i++) {
            if (titles[i] == title) {
               titles.splice(i,1);
               break;
            }
         }
      }
      if (titles.length >= 100) titles.pop();
      titles.unshift(title);
      $.jStorage.set(key, titles.join('|'));
   }
   catch (e) {
      // ignore
   }

	if (target == 'gedcom') {
		try {
		   if (parent && parent.review) {
		      var swf=(navigator.appName.indexOf("Microsoft")!=-1) ? parent.review.window["gedcom"] : parent.review.document["gedcom"];
		      if (swf && swf.matchFound) swf.matchFound(title);
		   }
		} catch (e) {
			alert('Unable to communicate with gedcom review program.  Please refresh your browser window.');
		}
	}
	else if (target != 'AddPage' && target != 'AddSpouse' && target != 'AddFather' && target != 'AddMother') {
	   window.opener.document.getElementById(target).value=title;
	}
}

function getTrees() {
   var trees = '';
   $(".treeCheckbox").each(function (i) {
      var cb = $(this);
      if (cb.attr('checked')) {
         trees += '|tree='+cb.attr('name');
      }
   });
   return trees;
}

function generateRsargs(parms) {
   var result = '';
   for (var parm in parms) {
      result += '|'+parm+'='+parms[parm];
   }
   return result;
}

function generateUrlParms(parms) {
   var result = '';
   for (var parm in parms) {
      result += '&'+parm+'='+encodeURIComponent(parms[parm]);
   }
   return result;
}

function selectPage(target, nsText, title) {
   var parms = {};
   title = decodeURIComponent(title);
   var needsUpdate = false;
   if (nsText == 'Person') {
      var pf = getFormValue('pf');
      var sf = getFormValue('sf');
      parms = {pf:pf, sf:sf};
      needsUpdate = pf || sf;
   }
   else if (nsText == 'Family') {
      var ht = getFormValue('ht');
      var wt = getFormValue('wt');
      var ct = getFormValue('ct');
      parms = {ht:ht, wt:wt, ct:ct};
      needsUpdate = ht || wt || ct;
   }
   // update target
   updateTarget(target, nsText, title);
   if (target == 'AddPage' && needsUpdate) {
      // redirect to edit page
      window.location = '/w/index.php?title='+encodeURIComponent(nsText+':'+title)+'&action=edit'+generateUrlParms(parms);
   }
   else if (target == 'AddPage' || target == 'gedcom') {
      // redirect to view
      window.location = '/w/index.php?title='+encodeURIComponent(nsText+':'+title);
   }
   else if (nsText == 'Person' || nsText == 'Family') {
      // update tree membership and add to watchlist; add spouse-family if necessary
      var pleasewait = $('#pleasewait');
      // don't add page twice if user double-clicks
      if (!pleasewait.is(':visible')) {
         pleasewait.css('display','inline');
         $.get('/w/index.php',
               {action: 'ajax', rs: 'wfAddPage', rsargs: 'update=true|ns='+nsText+'|title='+title + generateRsargs(parms) + getTrees()},
               function(data) {
            var results = data.getElementsByTagName('addpage');
            var status = results[0].getAttribute('status');
   //         $('#pleasewait').css('display','none');
            if (status == 0) {
               if (target == 'AddFather') {
                  parms['wg'] = getFormValue('wg');
                  parms['ws'] = getFormValue('ws');
                  promptForPerson(target, title, parms);
               }
               else {
                  // close window
                  setTimeout(closeWindow, 10);
               }
            }
            else {
               var error = results[0].getAttribute('error');
               alert(error);
            }
         });
      }
   }
   else {
      // close window
      setTimeout(closeWindow, 10);
   }
}

function promptForPerson(target, title, parms) {
   var addParms = {};
   var addedPage = 'family';
   // if target is spouse_of_familyX with spouse name -> AddSpouse w/ spouse name, sf=title
   if (target.substr(0, 16) == 'spouse_of_family' &&
       ((parms['gnd']=='M' && (parms['wg'] || parms['ws'])) || (parms['gnd']=='F' && (parms['hg'] || parms['hs'])))) {
      if (parms['gnd'] == 'M') {
         addParms['g'] = parms['wg'];
         addParms['s'] = parms['ws'];
         addParms['gnd'] = 'F';
      }
      else {
         addParms['g'] = parms['hg'];
         addParms['s'] = parms['hs'];
         addParms['gnd'] = 'M';
      }
      addParms['sf'] = title;
      target = 'AddSpouse';
   }
   // if target is child_of_familyX with father name -> AddFather w/ sf=title
   else if (target.substr(0, 15) == 'child_of_family' && (parms['hg'] || parms['hs'])) {
      addParms['sf'] = title;
      addParms['g'] = parms['hg'];
      addParms['s'] = parms['hs'];
      addParms['wg'] = parms['wg'];
      addParms['ws'] = parms['ws'];
      addParms['gnd'] = 'M';
      target = 'AddFather';
   }
   // if target is AddFather or child_of_familyX without father name with mother name -> AddMother w/ sf=sf
   else if ((target == 'AddFather' || (target.substr(0, 15) == 'child_of_family') && !(parms['hg'] || parms['hs'])) &&
            (parms['wg'] || parms['ws'])) {
      addParms['sf'] = (target == 'AddFather' ? parms['sf'] : title);
      addParms['g'] = parms['wg'];
      addParms['s'] = parms['ws'];
      addParms['gnd'] = 'F';
      if (target == 'AddFather') addedPage = 'father\'s';
      target = 'AddMother';
   }
   else {
      target = '';
   }

   // prompt to add a person?
   if (target) {
      var given = (addParms['g'] == 'Unknown' ? '' : addParms['g']);
      var surname = (addParms['s'] == 'Unknown' ? '' : addParms['s']);
      var name = (given && surname ? given+' '+surname : (given || surname));
      var $dialog = $('<div></div>')
         .html('The '+addedPage+' page has been added. Do you want to add a page for '+escapeHtml(name)+'?')
         .dialog({
            title: 'Add a page for '+escapeHtml(name)+'?',
            resizable: false,
   //         height:100,
            modal: true,
            buttons: {
             'Yes': function() {
                $( this ).dialog( "destroy" );
                // redirect to AddPage
//                $('#pleasewait').css('display','inline');
                window.location = '/wiki/Special:AddPage?namespace=Person&target='+encodeURIComponent(target)+generateUrlParms(addParms);
             },
             'No': function() {
                $( this ).dialog( "destroy" );
                promptForPerson(target, title, addParms);
             }
            }
         });
   }
   else {
      // close the window
      setTimeout(closeWindow, 10);
   }
}

function addPage(nsText, target, parms) {
   var pleasewait = $('#pleasewait');
   // don't add page twice if user double-clicks
   if (!pleasewait.is(':visible')) {
      pleasewait.css('display','inline');
      $.get('/w/index.php',
            {action: 'ajax', rs: 'wfAddPage', rsargs: 'update='+(target=='AddPage' || target=='gedcom' ? 'false' : 'true') + '|ns='+nsText+generateRsargs(parms) + getTrees()},
            function(data) {
         var results = data.getElementsByTagName('addpage');
         var status = results[0].getAttribute('status');
         var title = results[0].getAttribute('title');
   //      pleasewait.css('display','none');
         if (status == 0) {
            // update target
            updateTarget(target, nsText, title);
            if (target == 'AddPage' || target == 'gedcom') {
               // redirect to edit page
               window.location = '/w/index.php?title='+encodeURIComponent(nsText+':'+title)+'&action=edit'+generateUrlParms(parms);
            }
            else if (nsText == 'Person' || nsText == 'Family') {
               promptForPerson(target, title, parms);
            }
            else {
               // close window
               setTimeout(closeWindow, 10);
            }
         }
         else if (target == 'AddPage' || target == 'gedcom') {
            // redirect to AddPage
            window.location = '/wiki/Special:AddPage?confirm=true&target='+encodeURIComponent(target)+'&namespace='+encodeURIComponent(nsText)+generateUrlParms(parms);
         }
         else {
            pleasewait.css('display','none');
            var error = results[0].getAttribute('error');
            $('<div></div>')
            .html(escapeHtml(error))
            .dialog({
               title: 'Cannot add page',
               resizable: false,
			      modal: true,
			      buttons: {
				      'Ok': function() {
					      $( this ).dialog( "destroy" );
				      }
			      }
		      });
         }
      });
   }
}

function addPersonPage(target) {
   var givenname = getFormValue('g');
   var surname = getFormValue('s');
   var gender = getFormValue('gnd');
   var birthDate = getFormValue('bd');
   var birthPlace = getFormValue('bp');
   var birthType = getFormValue('bt');
   var deathDate = getFormValue('dd');
   var deathPlace = getFormValue('dp');
   var deathType = getFormValue('dt');
   var parentFamily = getFormValue('pf');
   var spouseFamily = getFormValue('sf');
   var wifeGivenname = getFormValue('wg'); // if we're adding a father we need to remember the mother
   var wifeSurname = getFormValue('ws');
	addPage('Person', target, {g:givenname, s:surname, gnd:gender,
	                           bd:birthDate, bp:birthPlace, bt:birthType,
	                           dd:deathDate, dp:deathPlace, dt:deathType,
                              pf:parentFamily, sf:spouseFamily, wg:wifeGivenname, ws:wifeSurname});
}

function addFamilyPage(target) {
   var gender = getFormValue('gnd');
   var husbandGivenname = getFormValue('hg');
   var husbandSurname = getFormValue('hs');
   var wifeGivenname = getFormValue('wg');
   var wifeSurname = getFormValue('ws');
   var marriageDate = getFormValue('md');
   var marriagePlace = getFormValue('mp');
   var childTitle = getFormValue('ct');
   var husbandTitle = getFormValue('ht');
   var wifeTitle = getFormValue('wt');
	addPage('Family', target, {hg:husbandGivenname, hs:husbandSurname, wg:wifeGivenname, ws:wifeSurname, gnd:gender,
                               md:marriageDate, mp:marriagePlace, ht:husbandTitle, wt:wifeTitle, ct:childTitle});
}

function addSourcePage(target, sourceType, placeIssued, publisher) {
   sourceType = decodeURIComponent(sourceType);
   placeIssued = decodeURIComponent(placeIssued);
   publisher = decodeURIComponent(publisher);
   var title = getFormValue('st');
   var author = getFormValue('a');
   var place = getFormValue('p');
	addPage('Source', target, {sty:sourceType, st:title, a:author, p:place, pi:placeIssued, pu:publisher});
}

function addMySourcePage(target) {
   var title = getFormValue('t');
   var author = getFormValue('a');
   var place = getFormValue('p');
   var surname = getFormValue('s');
   addPage('MySource', target, {t:title, a:author, p:place, s:surname});
}

function addPlacePage(target) {
   var placeName = getFormValue('pn');
   var locatedIn = getFormValue('li');
	addPage('Place', target, {pn:placeName, li:locatedIn});
}

function readcache() {
   $('#addpage_cache').each(function () {
      var re = /[&?]target=([^&#]*)/;
      var target = re.exec(window.location.href);
      target = (target && target[1] ? target[1] : 'AddPage');
      re = /[&?](pf|sf|ht|wt|ct)=([^&#])/;
      // if target is not AddPage or we're attaching this page to a relative
      //if (target != 'AddPage' || re.test(window.location.href)) {
      var nsText = $('#ns').val();
      var key = getAcStorageKey(nsText);
      try {
         var titles = $.jStorage.get(key,'');
         if (titles.length > 0) {
            var cache = $(this);
            // write heading
            var nsLabel = (nsText == 'Family' ? 'Families' : (nsText == 'Person' ? 'People' : nsText+'s'));
            cache.prepend('<h4>'+nsLabel+' you\'ve recently added or selected &nbsp; <span id="pleasewait" style="display:none"><span style="font-size: 80%; padding: 0 .2em; color: #fff; background-color: #888">Please Wait</span></span></h4>');
            titles = titles.split('|');
            for (var i = 0; i < Math.min((nsText == 'Family' || nsText == 'Person' ? 5 : 10),titles.length); i++) {
               var titleInfo = titles[i].split('#');
               // write entry w select button
               var button = $('<input type="submit" value="Select">').click(function(title) {
                  return function() {
                     selectPage(target, nsText, encodeURIComponent(title));
                  }
               }(titleInfo[0]));
               // escape page title
               var entry = $('<div class="cache_entry"/>')
                    .append(button)
                    .append('<span>'+escapeHtml(titleInfo[0])+'</span>');
               cache.append(entry);
            }
            cache.show();
         }
      }
      catch (e) {
         // ignore
      }
      //}
   });
}

$(document).ready(function() {
	showFocusSearchFields(true);
	bindExactClick();
  bindIncludeTalk();
   readcache();
});
