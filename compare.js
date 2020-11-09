function compareClick(i) {
	if ($('#gedcomdata').length > 0 && countMatches() > 2) {
		var ns = $('#ns').val();
		alert('Only one '+ns+' can be selected.  You can merge the other families after the GEDCOM is imported.');
		$('#m_'+i).attr('checked', false);
		return;
	}
	var nc = $('#maxchildren').val();
	if ($('#m_'+i).attr('checked')) {
		$('#mh_'+i).removeAttr('disabled');
		$('#mw_'+i).removeAttr('disabled');
		for (var c = 0; c < nc; c++) {
			$('#mcr_'+i+'_'+c).removeAttr('disabled');
		}
	}
	else {
		$('#mh_'+i).attr('disabled', true);
		$('#mw_'+i).attr('disabled', true);
		for (var c = 0; c < nc; c++) {
			$('#mcr_'+i+'_'+c).attr('disabled', true);
		}
	}
}

function countMatches() {
	var cnt = $('#gedcomdata').length;
	var maxPages = $('#maxpages').val();
	for (var i=0; i < maxPages; i++) {
		if ($('#m_'+i+':checked').val()) cnt++;
	}
	return cnt;
}

function getGedcomId(title) {
	if (title) {
		var start=title.lastIndexOf('('); 
		var end=title.lastIndexOf(' gedcom)');
		if (start >= 0 && end > start) return title.substr(start+1, end-start-1);
	}
	return '';
}

function getGedcomMatch(maxPages, prefix) {
	var id=getGedcomId($('#'+prefix+'_0').val());
	var match='';
	if (id) {
		for (var i=1; i < maxPages; i++) {
			var title = $('#'+prefix+'_'+i+':enabled:checked').val();
			if (title) {
				if (match) return 'multiple';
				match=title+'|'+id;
			}
		}
	}
	return match;
}

function getGedcomChildMatch(maxPages, maxChildren, childNum) {
	var id=getGedcomId($('#mc_0_'+childNum).val());
	var row=$('#mcr_0_'+childNum).val();
	var match='';
	if (id && row > 0) {
		for (i=1; i < maxPages; i++) {
			for (j=0; j < maxChildren; j++) {
				if ($('#mcr_'+i+'_'+j+':enabled').val() == row) {
					var title = $('#mc_'+i+'_'+j).val();
					if (match) return 'multiple';
					match = title+'|'+id;
				}
			}
		}
	}
	return match;
}

function doGedcomMatchOneAll(all) {
	var maxPages = $('#maxpages').val();
	var ns = $('#ns').val();
	var match = getGedcomMatch(maxPages, 'm');
	if (match == '') {
		alert('Please check the "Match" box under the matching '+ns);
		return;
	}
	else if (match == 'multiple') {
		alert('Only one '+ns+' can be selected.  You can merge the other families after the GEDCOM is imported.');
		return;
	}
	var matches=new Array();
	var cnt = 0;
	var pos = match.indexOf('|');
	var title=match.substr(0, pos);
	matches[cnt++] = match;
	if (ns == 'Family') {
		match = getGedcomMatch(maxPages, 'mh')
		if (match && match != 'multiple') {
			matches[cnt++] = match;
		}
		match = getGedcomMatch(maxPages, 'mw')
		if (match && match != 'multiple') {
			matches[cnt++] = match;
		}
		var maxChildren = $('#maxchildren').val();
		for (var i=0; i < maxChildren; i++) {
			match = getGedcomChildMatch(maxPages, maxChildren, i);
			if (match == 'multiple') {
				alert('Only one child can be merged with each GEDCOM child.  You can merge the other children after the GEDCOM is imported.');
				return;
			}
			else if (match) {
				matches[cnt++] = match;
			}
		}
	}
	try {
	   if (parent && parent.review) {
	      var swf=!parent.review.window && !parent.review.document ? parent.review : (navigator.appName.indexOf("Microsoft")!=-1) ? parent.review.window["gedcom"] : parent.review.document["gedcom"];
	      if (swf && swf.matchesFound) swf.matchesFound(matches.join("\n"), false, all);
//	      window.location='/wiki/'+ns+':'+title;
	   }
	} catch (e) {
		alert('Unable to communicate with gedcom review program.  Please refresh your browser window.');
	}
}

function doGedcomMatch() {
	doGedcomMatchOneAll(false);
}
function doGedcomMatchAll() {
	if (confirm('Matching all relatives could result in many people being matched.\nAre you sure?')) {
		doGedcomMatchOneAll(true);
	}
}

function setFormAction(action) {
	document.compare.formAction.value = action;
	document.compare.submit();
}

function doPrepareToMerge() {
	var ns = $('#ns').val();
	if (countMatches() < 2) {
		alert('Please check the "Match" box under the matching '+ns);
		return;
	}
	setFormAction('Merge');
	$('#compare').submit();
}

function doGedcomPrepareToMerge() {
	doPrepareToMerge();	
}

function doNotMatch() {
	var ns = $('#ns').val();
	if (countMatches() < 2) {
		alert('Please check the "Match" box under the non-matching '+ns);
		return;
	}
	setFormAction('NotMatch');
	$('#compare').submit();
}

function doGedcomNotMatch() {
	try {
	   if (parent && parent.review) {
	      var swf=!parent.review.window && !parent.review.document ? parent.review : (navigator.appName.indexOf("Microsoft")!=-1) ? parent.review.window["gedcom"] : parent.review.document["gedcom"];
	      if (swf && swf.matchesFound) swf.matchesFound('', false, false);
	   }
	} catch (e) {
		alert('Unable to communicate with gedcom review program.  Please refresh your browser window.');
	}
}
