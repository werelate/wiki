// MediaWiki JavaScript support functions

var clientPC = navigator.userAgent.toLowerCase(); // Get client info
var is_gecko = ((clientPC.indexOf('gecko')!=-1) && (clientPC.indexOf('spoofer')==-1)
                && (clientPC.indexOf('khtml') == -1) && (clientPC.indexOf('netscape/7.0')==-1));
var webkit_match = clientPC.match(/applewebkit\/(\d+)/);
if (webkit_match) {
	var is_safari = clientPC.indexOf('applewebkit') != -1 &&
		clientPC.indexOf('spoofer') == -1;
	var is_safari_win = is_safari && clientPC.indexOf('windows') != -1;
	var webkit_version = parseInt(webkit_match[1]);
}
var is_khtml = (navigator.vendor == 'KDE' || ( document.childNodes && !document.all && !navigator.taintEnabled ));
if (clientPC.indexOf('opera') != -1) {
	var is_opera = true;
	var is_opera_preseven = (window.opera && !document.childNodes);
	var is_opera_seven = (window.opera && document.childNodes);
}
var is_ff2 = /firefox\/[2-9]|minefield\/3/.test( clientPC );

// add any onload functions in this hook (please don't hard-code any events in the xhtml source)

var doneOnloadHook;

if (!window.onloadFuncts)
	var onloadFuncts = [];

function addOnloadHook(hookFunct) {
	// Allows add-on scripts to add onload functions
	onloadFuncts[onloadFuncts.length] = hookFunct;
}

function runOnloadHook() {
	// don't run anything below this for non-dom browsers
	if (doneOnloadHook || !(document.getElementById && document.getElementsByTagName))
		return;

	histrowinit();
	unhidetzbutton();
	tabbedprefs();
	akeytt();
	scrollEditBox();
	setupCheckboxShiftClick();

	// Run any added-on functions
	for (var i = 0; i < onloadFuncts.length; i++)
		onloadFuncts[i]();

	doneOnloadHook = true;
}

function hookEvent(hookName, hookFunct) {
	if (window.addEventListener)
		addEventListener(hookName, hookFunct, false);
	else if (window.attachEvent)
		attachEvent("on" + hookName, hookFunct);
}

//WERELATE - call in document.ready below
//hookEvent("load", runOnloadHook);

// WERELATE - remove special stylesheet links and untrapping from framesets

// for enhanced RecentChanges
function toggleVisibility(_levelId, _otherId, _linkId) {
	var thisLevel = document.getElementById(_levelId);
	var otherLevel = document.getElementById(_otherId);
	var linkLevel = document.getElementById(_linkId);
	if (thisLevel.style.display == 'none') {
		thisLevel.style.display = 'block';
		otherLevel.style.display = 'none';
		linkLevel.style.display = 'inline';
	} else {
		thisLevel.style.display = 'none';
		otherLevel.style.display = 'inline';
		linkLevel.style.display = 'none';
	}
}

// page history stuff
// attach event handlers to the input elements on history page
function histrowinit() {
	var hf = document.getElementById('pagehistory');
	if (!hf)
		return;
	var lis = hf.getElementsByTagName('li');
	for (var i = 0; i < lis.length; i++) {
		var inputs = historyRadios(lis[i]);
		if (inputs[0] && inputs[1]) {
			inputs[0].onclick = diffcheck;
			inputs[1].onclick = diffcheck;
		}
	}
	diffcheck();
}

function historyRadios(parent) {
	var inputs = parent.getElementsByTagName('input');
	var radios = [];
	for (var i = 0; i < inputs.length; i++) {
		if (inputs[i].name == "diff" || inputs[i].name == "oldid")
			radios[radios.length] = inputs[i];
	}
	return radios;
}

// check selection and tweak visibility/class onclick
function diffcheck() {
	var dli = false; // the li where the diff radio is checked
	var oli = false; // the li where the oldid radio is checked
	var hf = document.getElementById('pagehistory');
	if (!hf)
		return true;
	var lis = hf.getElementsByTagName('li');
	for (i=0;i<lis.length;i++) {
		var inputs = historyRadios(lis[i]);
		if (inputs[1] && inputs[0]) {
			if (inputs[1].checked || inputs[0].checked) { // this row has a checked radio button
				if (inputs[1].checked && inputs[0].checked && inputs[0].value == inputs[1].value)
					return false;
				if (oli) { // it's the second checked radio
					if (inputs[1].checked) {
						oli.className = "selected";
						return false;
					}
				} else if (inputs[0].checked) {
					return false;
				}
				if (inputs[0].checked)
					dli = lis[i];
				if (!oli)
					inputs[0].style.visibility = 'hidden';
				if (dli)
					inputs[1].style.visibility = 'hidden';
				lis[i].className = "selected";
				oli = lis[i];
			}  else { // no radio is checked in this row
				if (!oli)
					inputs[0].style.visibility = 'hidden';
				else
					inputs[0].style.visibility = 'visible';
				if (dli)
					inputs[1].style.visibility = 'hidden';
				else
					inputs[1].style.visibility = 'visible';
				lis[i].className = "";
			}
		}
	}
	return true;
}

// generate toc from prefs form, fold sections
// XXX: needs testing on IE/Mac and safari
// more comments to follow
function tabbedprefs() {
	var prefform = document.getElementById('preferences');
	if (!prefform || !document.createElement)
		return;
	if (prefform.nodeName.toLowerCase() == 'a')
		return; // Occasional IE problem
	prefform.className = prefform.className + 'jsprefs';
	var sections = new Array();
	var children = prefform.childNodes;
	var seci = 0;
	for (var i = 0; i < children.length; i++) {
		if (children[i].nodeName.toLowerCase() == 'fieldset') {
			children[i].id = 'prefsection-' + seci;
			children[i].className = 'prefsection';
			if (is_opera || is_khtml)
				children[i].className = 'prefsection operaprefsection';
			var legends = children[i].getElementsByTagName('legend');
			sections[seci] = new Object();
			legends[0].className = 'mainLegend';
			if (legends[0] && legends[0].firstChild.nodeValue)
				sections[seci].text = legends[0].firstChild.nodeValue;
			else
				sections[seci].text = '# ' + seci;
			sections[seci].secid = children[i].id;
			seci++;
			if (sections.length != 1)
				children[i].style.display = 'none';
			else
				var selectedid = children[i].id;
		}
	}
	var toc = document.createElement('ul');
	toc.id = 'preftoc';
	toc.selectedid = selectedid;
	for (i = 0; i < sections.length; i++) {
		var li = document.createElement('li');
		if (i == 0)
			li.className = 'selected';
		var a = document.createElement('a');
		a.href = '#' + sections[i].secid;
		a.onmousedown = a.onclick = uncoversection;
		a.appendChild(document.createTextNode(sections[i].text));
		a.secid = sections[i].secid;
		li.appendChild(a);
		toc.appendChild(li);
	}
	prefform.parentNode.insertBefore(toc, prefform.parentNode.childNodes[0]);
	document.getElementById('prefsubmit').id = 'prefcontrol';
}

function uncoversection() {
	var oldsecid = this.parentNode.parentNode.selectedid;
	var newsec = document.getElementById(this.secid);
	if (oldsecid != this.secid) {
		var ul = document.getElementById('preftoc');
		document.getElementById(oldsecid).style.display = 'none';
		newsec.style.display = 'block';
		ul.selectedid = this.secid;
		var lis = ul.getElementsByTagName('li');
		for (var i = 0; i< lis.length; i++) {
			lis[i].className = '';
		}
		this.parentNode.className = 'selected';
	}
	return false;
}

// Timezone stuff
// tz in format [+-]HHMM
function checkTimezone(tz, msg) {
	var localclock = new Date();
	// returns negative offset from GMT in minutes
	var tzRaw = localclock.getTimezoneOffset();
	var tzHour = Math.floor( Math.abs(tzRaw) / 60);
	var tzMin = Math.abs(tzRaw) % 60;
	var tzString = ((tzRaw >= 0) ? "-" : "+") + ((tzHour < 10) ? "0" : "") + tzHour + ((tzMin < 10) ? "0" : "") + tzMin;
	if (tz != tzString) {
		var junk = msg.split('$1');
		document.write(junk[0] + "UTC" + tzString + junk[1]);
	}
}

function unhidetzbutton() {
	var tzb = document.getElementById('guesstimezonebutton');
	if (tzb)
		tzb.style.display = 'inline';
}

// in [-]HH:MM format...
// won't yet work with non-even tzs
function fetchTimezone() {
	// FIXME: work around Safari bug
	var localclock = new Date();
	// returns negative offset from GMT in minutes
	var tzRaw = localclock.getTimezoneOffset();
	var tzHour = Math.floor( Math.abs(tzRaw) / 60);
	var tzMin = Math.abs(tzRaw) % 60;
	var tzString = ((tzRaw >= 0) ? "-" : "") + ((tzHour < 10) ? "0" : "") + tzHour +
		":" + ((tzMin < 10) ? "0" : "") + tzMin;
	return tzString;
}

function guessTimezone(box) {
	document.getElementsByName("wpHourDiff")[0].value = fetchTimezone();
}

function showTocToggle() {
	if (document.createTextNode) {
		// Uses DOM calls to avoid document.write + XHTML issues

		var linkHolder = document.getElementById('toctitle')
		if (!linkHolder)
			return;

		var outerSpan = document.createElement('span');
		outerSpan.className = 'toctoggle';

		var toggleLink = document.createElement('a');
		toggleLink.id = 'togglelink';
		toggleLink.className = 'internal';
		toggleLink.href = 'javascript:toggleToc()';
		toggleLink.appendChild(document.createTextNode(tocHideText));

		outerSpan.appendChild(document.createTextNode('['));
		outerSpan.appendChild(toggleLink);
		outerSpan.appendChild(document.createTextNode(']'));

		linkHolder.appendChild(document.createTextNode(' '));
		linkHolder.appendChild(outerSpan);

		var cookiePos = document.cookie.indexOf("hidetoc=");
		if (cookiePos > -1 && document.cookie.charAt(cookiePos + 8) == 1)
			toggleToc();
	}
}

function changeText(el, newText) {
	// Safari work around
	if (el.innerText)
		el.innerText = newText;
	else if (el.firstChild && el.firstChild.nodeValue)
		el.firstChild.nodeValue = newText;
}

function toggleToc() {
	var toc = document.getElementById('toc').getElementsByTagName('ul')[0];
	var toggleLink = document.getElementById('togglelink')

	if (toc && toggleLink && toc.style.display == 'none') {
		changeText(toggleLink, tocHideText);
		toc.style.display = 'block';
		document.cookie = "hidetoc=0";
	} else {
		changeText(toggleLink, tocShowText);
		toc.style.display = 'none';
		document.cookie = "hidetoc=1";
	}
}

var mwEditButtons = [];
var mwCustomEditButtons = []; // eg to add in MediaWiki:Common.js

// this function generates the actual toolbar buttons with localized text
// we use it to avoid creating the toolbar where javascript is not enabled
function addButton(imageFile, speedTip, tagOpen, tagClose, sampleText) {
	// Don't generate buttons for browsers which don't fully
	// support it.
	mwEditButtons[mwEditButtons.length] =
		{"imageFile": imageFile,
		 "speedTip": speedTip,
		 "tagOpen": tagOpen,
		 "tagClose": tagClose,
		 "sampleText": sampleText};
}

// this function generates the actual toolbar buttons with localized text
// we use it to avoid creating the toolbar where javascript is not enabled
function mwInsertEditButton(parent, item) {
	var image = document.createElement("img");
	image.width = 23;
	image.height = 22;
	image.src = item.imageFile;
	image.border = 0;
	image.alt = item.speedTip;
	image.title = item.speedTip;
	image.style.cursor = "pointer";
	image.onclick = function() {
		insertTags(item.tagOpen, item.tagClose, item.sampleText);
		return false;
	}

	parent.appendChild(image);
	return true;
}

function mwSetupToolbar() {
	var toolbar = document.getElementById('toolbar');
	if (!toolbar) return false;

	var textbox = document.getElementById('wpTextbox1');
	if (!textbox) return false;

	// Don't generate buttons for browsers which don't fully
	// support it.
	if (!document.selection && textbox.selectionStart == null)
		return false;

	for (var i in mwEditButtons) {
		mwInsertEditButton(toolbar, mwEditButtons[i]);
	}
	for (var i in mwCustomEditButtons) {
		mwInsertEditButton(toolbar, mwCustomEditButtons[i]);
	}
	return true;
}

function escapeQuotes(text) {
	var re = new RegExp("'","g");
	text = text.replace(re,"\\'");
	re = new RegExp("\\n","g");
	text = text.replace(re,"\\n");
	return escapeQuotesHTML(text);
}

function escapeQuotesHTML(text) {
	var re = new RegExp('&',"g");
	text = text.replace(re,"&amp;");
	var re = new RegExp('"',"g");
	text = text.replace(re,"&quot;");
	var re = new RegExp('<',"g");
	text = text.replace(re,"&lt;");
	var re = new RegExp('>',"g");
	text = text.replace(re,"&gt;");
	return text;
}

// apply tagOpen/tagClose to selection in textarea,
// use sampleText instead of selection if there is none
// copied and adapted from phpBB
function insertTags(tagOpen, tagClose, sampleText) {
	if (document.editform)
		var txtarea = document.editform.wpTextbox1;
	else {
		// some alternate form? take the first one we can find
		var areas = document.getElementsByTagName('textarea');
		var txtarea = areas[0];
	}

	// IE
	if (document.selection  && !is_gecko) {
		var theSelection = document.selection.createRange().text;
		if (!theSelection)
			theSelection=sampleText;
		txtarea.focus();
		if (theSelection.charAt(theSelection.length - 1) == " ") { // exclude ending space char, if any
			theSelection = theSelection.substring(0, theSelection.length - 1);
			document.selection.createRange().text = tagOpen + theSelection + tagClose + " ";
		} else {
			document.selection.createRange().text = tagOpen + theSelection + tagClose;
		}

	// Mozilla
	} else if(txtarea.selectionStart || txtarea.selectionStart == '0') {
		var replaced = false;
		var startPos = txtarea.selectionStart;
		var endPos = txtarea.selectionEnd;
		if (endPos-startPos)
			replaced = true;
		var scrollTop = txtarea.scrollTop;
		var myText = (txtarea.value).substring(startPos, endPos);
		if (!myText)
			myText=sampleText;
		if (myText.charAt(myText.length - 1) == " ") { // exclude ending space char, if any
			subst = tagOpen + myText.substring(0, (myText.length - 1)) + tagClose + " ";
		} else {
			subst = tagOpen + myText + tagClose;
		}
		txtarea.value = txtarea.value.substring(0, startPos) + subst +
			txtarea.value.substring(endPos, txtarea.value.length);
		txtarea.focus();
		//set new selection
		if (replaced) {
			var cPos = startPos+(tagOpen.length+myText.length+tagClose.length);
			txtarea.selectionStart = cPos;
			txtarea.selectionEnd = cPos;
		} else {
			txtarea.selectionStart = startPos+tagOpen.length;
			txtarea.selectionEnd = startPos+tagOpen.length+myText.length;
		}
		txtarea.scrollTop = scrollTop;

	// All other browsers get no toolbar.
	// There was previously support for a crippled "help"
	// bar, but that caused more problems than it solved.
	}
	// reposition cursor if possible
	if (txtarea.createTextRange)
		txtarea.caretPos = document.selection.createRange().duplicate();
}

var tooltipAccessKeyRegexp = /\[(ctrl-)?(alt-)?(shift-)?(esc-)?(.)\]$/;

function updateTooltipAccessKeys( tooltipAccessKeyPrefix, nodeList ) {
	for ( var i = 0; i < nodeList.length; i++ ) {
		var element = nodeList[i];
		var tip = element.getAttribute("title");
		if ( tip && tooltipAccessKeyRegexp.exec(tip) ) {
			tip = tip.replace(tooltipAccessKeyRegexp,
					  "["+tooltipAccessKeyPrefix+"$5]");
			element.setAttribute("title", tip );
		}
	}
}

function akeytt() {
	var tooltipAccessKeyPrefix = 'alt-';
	if (is_opera) {
		tooltipAccessKeyPrefix = 'shift-esc-';
	} else if (!is_safari_win && is_safari && webkit_version > 526) {
		tooltipAccessKeyPrefix = 'ctrl-alt-';
	} else if (!is_safari_win && (is_safari
			|| clientPC.indexOf('mac') != -1
			|| clientPC.indexOf('konqueror') != -1 )) {
		tooltipAccessKeyPrefix = 'ctrl-';
	} else if (is_ff2) {
		tooltipAccessKeyPrefix = 'alt-shift-';
	}
	
	// skins without a "column-one" element don't seem to have links with accesskeys either
	var columnOne = document.getElementById("column-one");
	if ( columnOne )
		updateTooltipAccessKeys( tooltipAccessKeyPrefix, columnOne.getElementsByTagName("a") );
	// these are rare enough that no such optimization is needed
	updateTooltipAccessKeys( tooltipAccessKeyPrefix, document.getElementsByTagName("input") );
	updateTooltipAccessKeys( tooltipAccessKeyPrefix, document.getElementsByTagName("label") );
}

function setupRightClickEdit() {
	if (document.getElementsByTagName) {
		var divs = document.getElementsByTagName('div');
		for (var i = 0; i < divs.length; i++) {
			var el = divs[i];
			if(el.className == 'editsection') {
				addRightClickEditHandler(el);
			}
		}
	}
}

function addRightClickEditHandler(el) {
	for (var i = 0; i < el.childNodes.length; i++) {
		var link = el.childNodes[i];
		if (link.nodeType == 1 && link.nodeName.toLowerCase() == 'a') {
			var editHref = link.getAttribute('href');

			// find the following a
			var next = el.nextSibling;
			while (next.nodeType != 1)
				next = next.nextSibling;

			// find the following header
			next = next.nextSibling;
			while (next.nodeType != 1)
				next = next.nextSibling;

			if (next && next.nodeType == 1 &&
				next.nodeName.match(/^[Hh][1-6]$/)) {
				next.oncontextmenu = function() {
					document.location = editHref;
					return false;
				}
			}
		}
	}
}

function setupCheckboxShiftClick() {
	if (document.getElementsByTagName) {
		var uls = document.getElementsByTagName('ul');
		var len = uls.length;
		for (var i = 0; i < len; ++i) {
			addCheckboxClickHandlers(uls[i]);
		}
	}
}

function addCheckboxClickHandlers(ul, start, finish) {
	if (ul.checkboxHandlersTimer) {
		clearInterval(ul.checkboxHandlersTimer);
	}
	if ( !ul.childNodes ) {
		return;
	}
	var len = ul.childNodes.length;
	if (len < 2) {
		return;
	}
	start = start || 0;
	finish = finish || start + 250;
	if ( finish > len ) { finish = len; }
	ul.checkboxes = ul.checkboxes || [];
	ul.lastCheckbox = ul.lastCheckbox || null;
	for (var i = start; i<finish; ++i) {
		var child = ul.childNodes[i];
		if ( child && child.childNodes && child.childNodes[0] ) {
			var cb = child.childNodes[0];
			if ( !cb.nodeName || cb.nodeName.toLowerCase() != 'input' ||
			     !cb.type || cb.type.toLowerCase() != 'checkbox' ) {
				return;
			}
			cb.index = ul.checkboxes.push(cb) - 1;
			cb.container = ul;
			cb.onmouseup = checkboxMouseupHandler;
		}
	}
	if (finish < len) {
	  var f=function(){ addCheckboxClickHandlers(ul, finish, finish+250); };
	  ul.checkboxHandlersTimer=setInterval(f, 200);
	}
}

function checkboxMouseupHandler(e) {
	if (typeof e == 'undefined') {
		e = window.event;
	}
	if ( !e.shiftKey || this.container.lastCheckbox === null ) {
		this.container.lastCheckbox = this.index;
		return true;
	}
	var endState = !this.checked;
	if ( is_opera ) { // opera has already toggled the checkbox by this point
		endState = !endState;
	}
	var start, finish;
	if ( this.index < this.container.lastCheckbox ) {
		start = this.index + 1;
		finish = this.container.lastCheckbox;
	} else {
		start = this.container.lastCheckbox;
		finish = this.index - 1;
	}
	for (var i = start; i <= finish; ++i ) {
		this.container.checkboxes[i].checked = endState;
	}
	this.container.lastCheckbox = this.index;
	return true;
}

function fillDestFilename() {
	if (!document.getElementById)
		return;
	var path = document.getElementById('wpUploadFile').value;
	// Find trailing part
	var slash = path.lastIndexOf('/');
	var backslash = path.lastIndexOf('\\');
	var fname;
	if (slash == -1 && backslash == -1) {
		fname = path;
	} else if (slash > backslash) {
		fname = path.substring(slash+1, 10000);
	} else {
		fname = path.substring(backslash+1, 10000);
	}

	// Capitalise first letter and replace spaces by underscores
	fname = fname.charAt(0).toUpperCase().concat(fname.substring(1,10000)).replace(/ /g, '_');

	// Output result
	var destFile = document.getElementById('wpDestFile');
	if (destFile)
		destFile.value = fname;
}


function considerChangingExpiryFocus() {
	if (!document.getElementById)
		return;
	var drop = document.getElementById('wpBlockExpiry');
	if (!drop)
		return;
	var field = document.getElementById('wpBlockOther');
	if (!field)
		return;
	var opt = drop.value;
	if (opt == 'other')
		field.style.display = '';
	else
		field.style.display = 'none';
}

function scrollEditBox() {
	var editBoxEl = document.getElementById("wpTextbox1");
	var scrollTopEl = document.getElementById("wpScrolltop");
	var editFormEl = document.getElementById("editform");

	if (editBoxEl && scrollTopEl) {
		if (scrollTopEl.value) editBoxEl.scrollTop = scrollTopEl.value;
		editFormEl.onsubmit = function() {
			document.getElementById("wpScrolltop").value = document.getElementById("wpTextbox1").scrollTop;
		}
	}
}

//WERELATE - call in document.ready below
//hookEvent("load", scrollEditBox);

function allmessagesfilter() {
	text = document.getElementById('allmessagesinput').value;
	k = document.getElementById('allmessagestable');
	if (!k) { return;}

	var items = k.getElementsByTagName('span');

	if ( text.length > allmessages_prev.length ) {
		for (var i = items.length-1, j = 0; i >= 0; i--) {
			j = allmessagesforeach(items, i, j);
		}
	} else {
		for (var i = 0, j = 0; i < items.length; i++) {
			j = allmessagesforeach(items, i, j);
		}
	}
	allmessages_prev = text;
}

function allmessagesforeach(items, i, j) {
	var hItem = items[i].getAttribute('id');
	if (hItem.substring(0,17) == 'sp-allmessages-i-') {
		if (items[i].firstChild && items[i].firstChild.nodeName == '#text' && items[i].firstChild.nodeValue.indexOf(text) != -1) {
			var itemA = document.getElementById( hItem.replace('i', 'r1') );
			var itemB = document.getElementById( hItem.replace('i', 'r2') );
			if ( itemA.style.display != '' ) {
				var s = "allmessageshider(\"" + hItem.replace('i', 'r1') + "\", \"" + hItem.replace('i', 'r2') + "\", '')";
				var k = window.setTimeout(s,j++*5);
			}
		} else {
			var itemA = document.getElementById( hItem.replace('i', 'r1') );
			var itemB = document.getElementById( hItem.replace('i', 'r2') );
			if ( itemA.style.display != 'none' ) {
				var s = "allmessageshider(\"" + hItem.replace('i', 'r1') + "\", \"" + hItem.replace('i', 'r2') + "\", 'none')";
				var k = window.setTimeout(s,j++*5);
			}
		}
	}
	return j;
}


function allmessageshider(idA, idB, cstyle) {
	var itemA = document.getElementById( idA );
	var itemB = document.getElementById( idB );
	if (itemA) { itemA.style.display = cstyle; }
	if (itemB) { itemB.style.display = cstyle; }
}

function allmessagesmodified() {
	allmessages_modified = !allmessages_modified;
	k = document.getElementById('allmessagestable');
	if (!k) { return;}
	var items = k.getElementsByTagName('tr');
	for (var i = 0, j = 0; i< items.length; i++) {
		if (!allmessages_modified ) {
			if ( items[i].style.display != '' ) {
				var s = "allmessageshider(\"" + items[i].getAttribute('id') + "\", null, '')";
				var k = window.setTimeout(s,j++*5);
			}
		} else if (items[i].getAttribute('class') == 'def' && allmessages_modified) {
			if ( items[i].style.display != 'none' ) {
				var s = "allmessageshider(\"" + items[i].getAttribute('id') + "\", null, 'none')";
				var k = window.setTimeout(s,j++*5);
			}
		}
	}
}

function allmessagesshow() {
	var k = document.getElementById('allmessagesfilter');
	if (k) { k.style.display = ''; }

	allmessages_prev = '';
	allmessages_modified = false;
}

function fixCompareAction(data, id) {
	if (typeof(data) != 'undefined' && data.indexOf('|') >= 0) {
		$('#actions-'+id+' a').attr('href', $('#actions-'+id+' a').attr('href')+data);
	}
	else {
		$('#actions-'+id).remove();
	}
}
function fixCompareActions() {
	fixCompareAction(window['personParents'], 'compare-parents');
	fixCompareAction(window['personSpouses'], 'compare-spouses');
	fixCompareAction(window['familyHusbands'], 'compare-husbands');
	fixCompareAction(window['familyWives'], 'compare-wives');
}

/*
 * Table sorting script based on one (c) 1997-2006 Stuart Langridge and Joost
 * de Valk:
 * http://www.joostdevalk.nl/code/sortable-table/
 * http://www.kryogenix.org/code/browser/sorttable/
 *
 * @todo don't break on colspans/rowspans (bug 8028)
 * @todo language-specific digit grouping/decimals (bug 8063)
 * @todo support all accepted date formats (bug 8226)
 */

window.ts_image_path = '/w/skins/common/images/';
window.ts_image_up = 'sort_up.gif';
window.ts_image_down = 'sort_down.gif';
window.ts_image_none = 'sort_none.gif';
window.ts_europeandate = false; // The non-American-inclined can change to "true"
window.ts_alternate_row_colors = false;
window.ts_number_transform_table = null;
window.ts_number_regex = null;

/*
	Written by Jonathan Snook, http://www.snook.ca/jonathan
	Add-ons by Robert Nyman, http://www.robertnyman.com
	Author says "The credit comment is all it takes, no license. Go crazy with it!:-)"
	From http://www.robertnyman.com/2005/11/07/the-ultimate-getelementsbyclassname/
*/
window.getElementsByClassName = function( oElm, strTagName, oClassNames ) {
	var arrReturnElements = new Array();
	if ( typeof( oElm.getElementsByClassName ) == 'function' ) {
		/* Use a native implementation where possible FF3, Saf3.2, Opera 9.5 */
		var arrNativeReturn = oElm.getElementsByClassName( oClassNames );
		if ( strTagName == '*' ) {
			return arrNativeReturn;
		}
		for ( var h = 0; h < arrNativeReturn.length; h++ ) {
			if( arrNativeReturn[h].tagName.toLowerCase() == strTagName.toLowerCase() ) {
				arrReturnElements[arrReturnElements.length] = arrNativeReturn[h];
			}
		}
		return arrReturnElements;
	}
	var arrElements = ( strTagName == '*' && oElm.all ) ? oElm.all : oElm.getElementsByTagName( strTagName );
	var arrRegExpClassNames = new Array();
	if( typeof oClassNames == 'object' ) {
		for( var i = 0; i < oClassNames.length; i++ ) {
			arrRegExpClassNames[arrRegExpClassNames.length] =
				new RegExp("(^|\\s)" + oClassNames[i].replace(/\-/g, "\\-") + "(\\s|$)");
		}
	} else {
		arrRegExpClassNames[arrRegExpClassNames.length] =
			new RegExp("(^|\\s)" + oClassNames.replace(/\-/g, "\\-") + "(\\s|$)");
	}
	var oElement;
	var bMatchesAll;
	for( var j = 0; j < arrElements.length; j++ ) {
		oElement = arrElements[j];
		bMatchesAll = true;
		for( var k = 0; k < arrRegExpClassNames.length; k++ ) {
			if( !arrRegExpClassNames[k].test( oElement.className ) ) {
				bMatchesAll = false;
				break;
			}
		}
		if( bMatchesAll ) {
			arrReturnElements[arrReturnElements.length] = oElement;
		}
	}
	return ( arrReturnElements );
};

window.sortables_init = function() {
	var idnum = 0;
	// Find all tables with class sortable and make them sortable
	var tables = getElementsByClassName( document, 'table', 'sortable' );
	for ( var ti = 0; ti < tables.length ; ti++ ) {
		if ( !tables[ti].id ) {
			tables[ti].setAttribute( 'id', 'sortable_table_id_' + idnum );
			++idnum;
		}
		ts_makeSortable( tables[ti] );
	}
};

window.ts_makeSortable = function( table ) {
	var firstRow;
	if ( table.rows && table.rows.length > 0 ) {
		if ( table.tHead && table.tHead.rows.length > 0 ) {
			firstRow = table.tHead.rows[table.tHead.rows.length-1];
		} else {
			firstRow = table.rows[0];
		}
	}
	if ( !firstRow ) {
		return;
	}

	// We have a first row: assume it's the header, and make its contents clickable links
	for ( var i = 0; i < firstRow.cells.length; i++ ) {
		var cell = firstRow.cells[i];
		if ( (' ' + cell.className + ' ').indexOf(' unsortable ') == -1 ) {
			$(cell).append ( '<a href="#" class="sortheader" '
				+ 'onclick="ts_resortTable(this);return false;">'
				+ '<span class="sortarrow">'
				+ '<img src="'
				+ ts_image_path
				+ ts_image_none
				+ '" alt="&darr;"/></span></a>');
		}
	}
	if ( ts_alternate_row_colors ) {
		ts_alternate( table );
	}
};

window.getInnerText = function( el ) {
	if ( typeof el == 'string' ) {
		return el;
	}
	if ( typeof el == 'undefined' ) {
		return el;
	}
	// Custom sort value through 'data-sort-value' attribute
	// (no need to prepend hidden text to change sort value)
	if ( el.nodeType && el.getAttribute( 'data-sort-value' ) !== null ) {
		// Make sure it's a valid DOM element (.nodeType) and that the attribute is set (!null)
		return el.getAttribute( 'data-sort-value' );
	}
	if ( el.textContent ) {
		return el.textContent; // not needed but it is faster
	}
	if ( el.innerText ) {
		return el.innerText; // IE doesn't have textContent
	}
	var str = '';

	var cs = el.childNodes;
	var l = cs.length;
	for ( var i = 0; i < l; i++ ) {
		switch ( cs[i].nodeType ) {
			case 1: // ELEMENT_NODE
				str += ts_getInnerText( cs[i] );
				break;
			case 3:	// TEXT_NODE
				str += cs[i].nodeValue;
				break;
		}
	}
	return str;
};

window.ts_getInnerText = function( el ) {
	return getInnerText( el );
};

window.ts_resortTable = function( lnk ) {
	// get the span
	var span = lnk.getElementsByTagName('span')[0];

	var td = lnk.parentNode;
	var tr = td.parentNode;
	var column = td.cellIndex;

	var table = tr.parentNode;
	while ( table && !( table.tagName && table.tagName.toLowerCase() == 'table' ) ) {
		table = table.parentNode;
	}
	if ( !table ) {
		return;
	}

	if ( table.rows.length <= 1 ) {
		return;
	}

	// Generate the number transform table if it's not done already
	if ( ts_number_transform_table === null ) {
		ts_initTransformTable();
	}

	// Work out a type for the column
	// Skip the first row if that's where the headings are
	var rowStart = ( table.tHead && table.tHead.rows.length > 0 ? 0 : 1 );
	var bodyRows = 0;
	if (rowStart == 0 && table.tBodies) {
		for (var i=0; i < table.tBodies.length; i++ ) {
			bodyRows += table.tBodies[i].rows.length;
		}
		if (bodyRows < table.rows.length)
			rowStart = 1;
	}

	var itm = '';
	for ( var i = rowStart; i < table.rows.length; i++ ) {
		if ( table.rows[i].cells.length > column ) {
			itm = ts_getInnerText(table.rows[i].cells[column]);
			itm = itm.replace(/^[\s\xa0]+/, '').replace(/[\s\xa0]+$/, '');
			if ( itm != '' ) {
				break;
			}
		}
	}

	// TODO: bug 8226, localised date formats
	var sortfn = ts_sort_generic;
	var preprocessor = ts_toLowerCase;
   if ( /^\d?\d[\/. -][a-zA-Z]{3,9}[\/. -]\d\d\d\d$/.test( itm ) ) {
      preprocessor = ts_dateToSortKey;
   }
   else if ( /^[a-zA-Z]{3,9}[\/. -]\d\d\d\d$/.test( itm ) ) {
      preprocessor = ts_dateToSortKey;
   } else if ( /^\d\d\d\d$/.test( itm ) ) {
      preprocessor = ts_dateToSortKey;
		// (minus sign)([pound dollar euro yen currency]|cents)
	} else if ( /(^([-\u2212] *)?[\u00a3$\u20ac\u00a4\u00a5]|\u00a2$)/.test( itm ) ) {
		preprocessor = ts_currencyToSortKey;
	} else if ( ts_number_regex.test( itm ) ) {
		preprocessor = ts_parseFloat;
	}

	var reverse = ( span.getAttribute( 'sortdir' ) == 'down' );

	var newRows = new Array();
	var staticRows = new Array();
	for ( var j = rowStart; j < table.rows.length; j++ ) {
		var row = table.rows[j];
		if( (' ' + row.className + ' ').indexOf(' unsortable ') < 0 ) {
			var keyText = ts_getInnerText( row.cells[column] );
			if( keyText === undefined ) {
				keyText = '';
			}
			var oldIndex = ( reverse ? -j : j );
			var preprocessed = preprocessor( keyText.replace(/^[\s\xa0]+/, '').replace(/[\s\xa0]+$/, '') );

			newRows[newRows.length] = new Array( row, preprocessed, oldIndex );
		} else {
			staticRows[staticRows.length] = new Array( row, false, j-rowStart );
		}
	}

	newRows.sort( sortfn );

	var arrowHTML;
	if ( reverse ) {
		arrowHTML = '<img src="' + ts_image_path + ts_image_down + '" alt="&darr;"/>';
		newRows.reverse();
		span.setAttribute( 'sortdir', 'up' );
	} else {
		arrowHTML = '<img src="' + ts_image_path + ts_image_up + '" alt="&uarr;"/>';
		span.setAttribute( 'sortdir', 'down' );
	}

	for ( var i = 0; i < staticRows.length; i++ ) {
		var row = staticRows[i];
		newRows.splice( row[2], 0, row );
	}

	// We appendChild rows that already exist to the tbody, so it moves them rather than creating new ones
	// don't do sortbottom rows
	for ( var i = 0; i < newRows.length; i++ ) {
		if ( ( ' ' + newRows[i][0].className + ' ').indexOf(' sortbottom ') == -1 ) {
			table.tBodies[0].appendChild( newRows[i][0] );
		}
	}
	// do sortbottom rows only
	for ( var i = 0; i < newRows.length; i++ ) {
		if ( ( ' ' + newRows[i][0].className + ' ').indexOf(' sortbottom ') != -1 ) {
			table.tBodies[0].appendChild( newRows[i][0] );
		}
	}

	// Delete any other arrows there may be showing
	var spans = getElementsByClassName( tr, 'span', 'sortarrow' );
	for ( var i = 0; i < spans.length; i++ ) {
		spans[i].innerHTML = '<img src="' + ts_image_path + ts_image_none + '" alt="&darr;"/>';
	}
	span.innerHTML = arrowHTML;

	if ( ts_alternate_row_colors ) {
		ts_alternate( table );
	}
};

window.ts_initTransformTable = function() {
	if ( typeof wgSeparatorTransformTable == 'undefined'
			|| ( wgSeparatorTransformTable[0] == '' && wgDigitTransformTable[2] == '' ) )
	{
		var digitClass = "[0-9,.]";
		ts_number_transform_table = false;
	} else {
		ts_number_transform_table = {};
		// Unpack the transform table
		// Separators
		var ascii = wgSeparatorTransformTable[0].split("\t");
		var localised = wgSeparatorTransformTable[1].split("\t");
		for ( var i = 0; i < ascii.length; i++ ) {
			ts_number_transform_table[localised[i]] = ascii[i];
		}
		// Digits
		ascii = wgDigitTransformTable[0].split("\t");
		localised = wgDigitTransformTable[1].split("\t");
		for ( var i = 0; i < ascii.length; i++ ) {
			ts_number_transform_table[localised[i]] = ascii[i];
		}

		// Construct regex for number identification
		var digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', ',', '\\.'];
		var maxDigitLength = 1;
		for ( var digit in ts_number_transform_table ) {
			// Escape regex metacharacters
			digits.push(
				digit.replace( /[\\\\$\*\+\?\.\(\)\|\{\}\[\]\-]/,
					function( s ) { return '\\' + s; } )
			);
			if ( digit.length > maxDigitLength ) {
				maxDigitLength = digit.length;
			}
		}
		if ( maxDigitLength > 1 ) {
			var digitClass = '[' + digits.join('') + ']';
		} else {
			var digitClass = '(' + digits.join('|') + ')';
		}
	}

	// We allow a trailing percent sign, which we just strip.  This works fine
	// if percents and regular numbers aren't being mixed.
	ts_number_regex = new RegExp(
		"^(" +
			"[-+\u2212]?[0-9][0-9,]*(\\.[0-9,]*)?(E[-+\u2212]?[0-9][0-9,]*)?" + // Fortran-style scientific
			"|" +
			"[-+\u2212]?" + digitClass + "+%?" + // Generic localised
		")$", "i"
	);
};

window.ts_toLowerCase = function( s ) {
	return s.toLowerCase();
};

window.ts_dateToSortKey = function( date ) {
   date = $.trim(date);
   // if date starts with a letter, add 00 for day
   if (date.length > 0 && date.substr(0,1).toLowerCase() >= 'a' && date.substr(0,1).toLowerCase() <= 'z') {
      date = '00 '+date;
   }
   else if (date.length > 1 && date.substr(1,1) == ' ') {
      date = '0'+date;
   }
	if ( date.length >= 11 ) {
		switch ( date.substr( 3, 3 ).toLowerCase() ) {
			case 'jan':
				var month = '01';
				break;
			case 'feb':
				var month = '02';
				break;
			case 'mar':
				var month = '03';
				break;
			case 'apr':
				var month = '04';
				break;
			case 'may':
				var month = '05';
				break;
			case 'jun':
				var month = '06';
				break;
			case 'jul':
				var month = '07';
				break;
			case 'aug':
				var month = '08';
				break;
			case 'sep':
				var month = '09';
				break;
			case 'oct':
				var month = '10';
				break;
			case 'nov':
				var month = '11';
				break;
			case 'dec':
				var month = '12';
				break;
			default:
            var month = '00';
		}
		return date.substr( date.length - 4, 4 ) + month + date.substr( 0, 2 );
	}
   else if (date.length == 4 ) {
      return date+'0000';
   }
	return '00000000';
};

window.ts_parseFloat = function( s ) {
	if ( !s ) {
		return 0;
	}
	if ( ts_number_transform_table != false ) {
		var newNum = '', c;

		for ( var p = 0; p < s.length; p++ ) {
			c = s.charAt( p );
			if ( c in ts_number_transform_table ) {
				newNum += ts_number_transform_table[c];
			} else {
				newNum += c;
			}
		}
		s = newNum;
	}
	var num = parseFloat( s.replace(/[, ]/g, '').replace("\u2212", '-') );
	return ( isNaN( num ) ? -Infinity : num );
};

window.ts_currencyToSortKey = function( s ) {
	return ts_parseFloat(s.replace(/[^-\u22120-9.,]/g,''));
};

window.ts_sort_generic = function( a, b ) {
	return a[1] < b[1] ? -1 : a[1] > b[1] ? 1 : a[2] - b[2];
};

window.ts_alternate = function( table ) {
	// Take object table and get all it's tbodies.
	var tableBodies = table.getElementsByTagName( 'tbody' );
	// Loop through these tbodies
	for ( var i = 0; i < tableBodies.length; i++ ) {
		// Take the tbody, and get all it's rows
		var tableRows = tableBodies[i].getElementsByTagName( 'tr' );
		// Loop through these rows
		// Start at 1 because we want to leave the heading row untouched
		for ( var j = 0; j < tableRows.length; j++ ) {
			// Check if j is even, and apply classes for both possible results
			var oldClasses = tableRows[j].className.split(' ');
			var newClassName = '';
			for ( var k = 0; k < oldClasses.length; k++ ) {
				if ( oldClasses[k] != '' && oldClasses[k] != 'even' && oldClasses[k] != 'odd' ) {
					newClassName += oldClasses[k] + ' ';
				}
			}
			tableRows[j].className = newClassName + ( j % 2 == 0 ? 'even' : 'odd' );
		}
	}
};

/*
 * End of table sorting code
 */

function preventDefaultAction(e) {
	if (e.preventDefault) {
		e.preventDefault();
	} else {
		e.returnValue = false;
	}
	return false;
}

//WERELATE - call in document.ready below
//hookEvent("load", allmessagesshow);
//hookEvent("load", mwSetupToolbar);

function getAcStorageKey(nsText) {
   return 'ac:'+nsText+':';
}

function escapeHtml(unsafe) {
  return unsafe
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
}

$.fn.defaultTreeCheck = function(treeName) {
	this.each(function() {
	   if (treeName.length == 0 || this.name == treeName) {
      	$(this).attr("checked", "checked");
			$('#wpWatchthis').attr("checked", "checked");
	   }
	});
	return this;
}

function showWatchers() {
   function slideDownFadeIn(j, f) {
      j.css('visibility', 'hidden');
      j.slideDown('fast', function() {
         j.fadeOut(0);
         j.css('visibility', 'visible');
         j.fadeIn('fast', f);
      });
   }

   function fadeOutSlideUp(j, f) {
      j.fadeOut('fast', function() {
         j.css('display', 'block');
         j.css('visibility', 'hidden');
         j.slideUp('fast', f);
      });
   }

   var loaded = false;
   $('#wr-watchers .wr-watchers-showall a').click(function () {
      var jthis = $(this);
      var showall = jthis.closest('ul');
      if (!loaded) {
         jthis.html('[loading]');
         $('<ul/>').insertBefore(showall).load(jthis.attr('href'), function() {
            loaded = true;
            jthis.html('[show fewer]');
         });
      }
      else {
         var all = showall.prev();
         if (all.is(':visible')) {
            fadeOutSlideUp(all, function() {
               jthis.html('[show all]');
            });
         }
         else {
            slideDownFadeIn(all, function() {
               jthis.html('[show fewer]');
            });
         }
      }
      return false;
   });
}

function addOpenClose() {
   $('.wr-articleview h2,.wr-articleview div.h2like').each(function() {
      if ($(this).parents('#toc').size() == 0) {
         var x = $('<span class="wr-openclose">▼</span>').click(function() {
            var jthis = $(this);
            if (jthis.data('closed')) {
               jthis.parent().nextUntil('h2,div.h2like,.printfooter,.wr-openclose-end').slideDown('fast');
               jthis.data('closed', false).html('▼');
            }
            else {
               jthis.parent().nextUntil('h2,div.h2like,.printfooter,.wr-openclose-end').slideUp('fast');
               jthis.data('closed', true).html('►');
            }
         });
         $(this).prepend(x);
      }
   });
}

function familytreelink_init() {
   $('#wr_familytreelink').click(function() {
      var iframe = $('#wr_familytree_iframe');
      // if iframe exists
      if (iframe.length) {
//         if (iframe.is(':hidden')) {
//            iframe.slideDown(700);
//         }
//         else {
            iframe.slideUp(300, function() {
               iframe.remove();
            });
//         }
      }
      else {
         var titleRE = /\/wiki\/([^?#]+)/;
         var title = titleRE.exec(window.location.href);
         if (!title || title.length < 2) {
            titleRE = /[?&]title=([^&#]+)/;
            title = titleRE.exec(window.location.href);
         }
         if (title && title.length == 2) {
            $('#wr_familytreelink').after(
               '<iframe style="display:none" id="wr_familytree_iframe" width="100%" height="100%" frameBorder="0" src="/w/familytree.html?id='+
               title[1]+'"/>');
//                    '<div id="wr_familytree_iframe"><iframe width="100%" height="100%" src="/w/familytree.html?id='+
//                    title+'"/></div>');
            $('#wr_familytree_iframe').slideDown(700);
         }
      }
   });
}

$(document).ready(function() {
	runOnloadHook();
	scrollEditBox();
	allmessagesshow();
	mwSetupToolbar();
	fixCompareActions();
   addOpenClose();
   $('#wr-menulist').clickMenu();
   $('#wr-menulist').show();
   $('#wr-personallist').clickMenu();
   $('#wr-personallist').show();
	$('#wr-actionslist').clickMenu();
	$('#wr-actionslist').show();
   $('#wr-searchbox input').ezpz_hint({hintName:'a'});
//   $('#wr-searchbox').inputHintOverlay();
   familytreelink_init();
   var treeName = '';
   var p = parent;
	if (window.opener) {
	   p = window.opener.parent;
	}
	try {
	   if (p && p.fte) {
	      var swf=(navigator.appName.indexOf("Microsoft")!=-1) ? p.fte.window["FTE"] : p.fte.document["FTE"];
	      if (swf) {
	         if (swf.contentLoaded) {
	            swf.contentLoaded(document.title, document.URL, pageRevid); //, treeNames);
	         }
	         if (swf.contentLoaded2) {
	            swf.contentLoaded2(document.title, document.URL, pageRevid, treeNames, userName);
	         }
	         if (swf.getTreeName) {
	            treeName = swf.getTreeName();
	         }
	      }
	   }
	}
	catch (e) {
	}
   if (treeName.length > 0) {
   	var re = new RegExp("[^a-zA-Z0-9]","g");
	   treeName = 'tree_'+treeName.replace(re,"_");
      $('.treeCheckbox').defaultTreeCheck(treeName);
   }
   else {
      $('.defaultTreeCheckbox').defaultTreeCheck('');
   }
	$('.treeCheck').click(function() {
		if ($(this).is(':checked')) {
			$('#wpWatchthis').attr("checked", "checked");
		}
	});
   $('.popup').click(function() {
      window.open($(this).attr('href'),'','height=600,width=800,scrollbars=yes,resizable=yes,toolbar=yes,menubar=no,location=no,directories=no');
      return false;
   });
   $('.wr-imagehover').each(function() {
      var jthis=$(this);
      var attrs = jthis.attr('title').split('|');
      jthis.attr('title','');
      $('a', jthis).attr('rel',attrs[0]).attr('title',attrs.length >= 3 ? attrs[2] : '')
              .cluetip({dropShadow:false,cluetipClass:'image',width:attrs[1],showTitle:false,clickThrough:true});
   });
   $('.jTip').cluetip({local:true,dropShadow:false,cluetipClass:'jtip',activation:'click',sticky:true,closePosition:'title'});
   showWatchers();
   sortables_init();
   if (addthis) {
     addthis.init();
   }
});
