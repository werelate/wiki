function addClick(m,p,r) {
	var id = '_'+m+'_'+p+'_'+r;
	if ($('#add'+id).attr('checked')) {
		$('#value'+id).removeClass('merge_unchecked').addClass('merge_checked');
	}
	else {
		$('#value'+id).removeClass('merge_checked').addClass('merge_unchecked');
//		if (p > 0) {
//			id = '_'+m+'_0_'+r;
//			if (!$('#add'+id).attr('checked')) {
//				$('#add'+id).attr('checked',true);
//				$('#value'+id).removeClass('merge_unchecked').addClass('merge_checked');
//			}
//		}
	}
}

function setFormAction(action) {
	document.merge.formAction.value = action;
	document.merge.submit();
}

function doCancel() {
	setFormAction('Cancel');
}

function doMerge() {
	$("#mergeButton").attr("disabled","disabled");
	setFormAction('Merge');
}

function getGedcomId(title) {
	if (title) {
		var start=title.lastIndexOf('('); 
		var end=title.lastIndexOf(' gedcom)');
		if (start >= 0 && end > start) return title.substr(start+1, end-start-1);	
	}
	return '';
}

function doCancelGedcom() {
	try {
	   if (parent && parent.review) {
	      var swf=!parent.review.window && !parent.review.document ? parent.review : (navigator.appName.indexOf("Microsoft")!=-1) ? parent.review.window["gedcom"] : parent.review.document["gedcom"];
	      if (swf && swf.matchesFound) swf.matchesFound('', false, false);
			doCancel();
	   }
	} catch (e) {
		alert('Unable to communicate with gedcom review program.  Please refresh your browser window.');
	}
}

function doMergeGedcom() {
	if ($(".gedcom_source input:checked").length == 0 && $(".gedcom_target input:not(:checked)").length == 0) {
		alert('Please check the boxes next to the information you want to add.  If you don\'t have new information to add, you can move to the next family.');
		return;
	}
	$("#mergeButton").attr("disabled","disabled");
	var ns=$("#merge input[name='ns']").val();
	var merges=$("#merge input[name='merges']").val();
	var matches=new Array();
	var cnt = 0;
	for (i=0; i < merges; i++) {
		var pages=$("#merge input[name='merges_"+i+"']").val();
		var target=$("#merge input[name='target_"+i+"']:checked").val();
		if (!target) target = '0';
		var targetTitle=$("#merge input[name='merges_"+i+"_"+target+"']").val();
		for (j=0; j < pages; j++) {
			if (j != target) {
				var id=getGedcomId($("#merge input[name='merges_"+i+"_"+j+"']").val());
				if (id) {
					matches[cnt++] = targetTitle+'|'+id;
				}
			}
		}
	}
	try {
	   if (parent && parent.review) {
	      var swf=!parent.review.window && !parent.review.document ? parent.review : (navigator.appName.indexOf("Microsoft")!=-1) ? parent.review.window["gedcom"] : parent.review.document["gedcom"];
	      if (swf && swf.matchesFound) swf.matchesFound(matches.join("\n"), true, false);
			doMerge();
	   }
	} catch (e) {
		alert('Unable to communicate with gedcom review program.  Please refresh your browser window.');
	}
}