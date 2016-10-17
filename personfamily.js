function replaceIdInTable(tbl,col,re,newId,start,inc) {
	var numRows=tbl.rows.length;
	for(var i=start;i<numRows;i+=inc) {
		var inp=tbl.rows[i].cells[col].getElementsByTagName('input')[0];
		var value=inp.value.replace(re,newId);
		if (newId.length==0) {
			value=value.replace(/\s*,\s*$/,'');
		}
		inp.value=value;
	}
}

function replaceIdInText(oldId,newId) {
	var recite=new RegExp("\\{\\{cite\\s*\\|\\s*"+oldId+"\\s*(\\|[^\\}]*)?\\}\\}",'mig');
   var relink=new RegExp("\\[\\[#"+oldId+"\\s*(\\|[^\\]]*)?\\]\\]",'mig');
   var reref=new RegExp("<ref\\s*name=\"?"+oldId+"\"?\\s*/>",'mig');
	var textbox=document.getElementById('wpTextbox1');
	var value=textbox.value
           .replace(recite,newId ? '{{cite|'+newId+'$1}}' : '')
           .replace(relink,newId ? '[[#'+newId+'$1]]' : '')
           .replace(reref,newId ? '<ref name="'+newId+'"/>' : '');
	textbox.value=value;
}

function replaceId(oldId,newId,nameCol,efCol,srcCol) {
	var p="\\b"+oldId+"\\b";
	if (newId.length==0) {
		p+="(\\s*,\\s*)?";
	}
	var re=new RegExp(p,'ig');
	var tbl=document.getElementById('name_input');
	if (nameCol>=0 && tbl) {
		replaceIdInTable(tbl,nameCol,re,newId,1,1);
	}
	tbl=document.getElementById('event_fact_input');
	if (efCol>=0 && tbl) {
		replaceIdInTable(tbl,efCol,re,newId,2,2);
	}
	tbl=document.getElementById('source_input');
	if (srcCol>=0 && tbl) {
		replaceIdInTable(tbl,srcCol,re,newId,3,5);
	}
	replaceIdInText(oldId,newId);
}

function addName(nameTypes) {
	nameTypes=nameTypes.split(',');
	var tbl=document.getElementById('name_input');
	$('span', tbl.rows[1].cells[0]).css('display','inline');
	var rowNum=tbl.rows.length;
	var row=tbl.insertRow(rowNum);
	var nameNum=rowNum-1;
	var cell=row.insertCell(0);
	var sel='<select class="n_select" tabindex="1" name="alt_name'+nameNum+'"><option value="Unknown" selected="selected">Type of name</option>';
	for (var i=0;i<nameTypes.length;i++) {
		sel+='<option value="'+nameTypes[i]+'">'+nameTypes[i]+'</option>';
	}
	cell.innerHTML=sel+'</select>';
   cell=row.insertCell(1); cell.innerHTML='<input class="n_presuf" tabindex="1" type="text" name="title_prefix'+nameNum+'"/>';
	cell=row.insertCell(2); cell.innerHTML='<input class="n_given" tabindex="1" type="text" name="given'+nameNum+'"/>';
	cell=row.insertCell(3); cell.innerHTML='<input class="n_surname" tabindex="1" type="text" name="surname'+nameNum+'"/>';
	cell=row.insertCell(4); cell.innerHTML='<input class="n_presuf" tabindex="1" type="text" name="title_suffix'+nameNum+'"/>';
	cell=row.insertCell(5); cell.innerHTML='<a title="Add a source for this name" href="#sourcesSection" onClick="addRef(\'name_input\','+rowNum+',6,newSource());">+</a>';
   cell.className = "n_plus";
	cell=row.insertCell(6); cell.innerHTML='<input class="n_ref" tabindex="1" type="text" name="name_sources'+nameNum+'"/>';
	cell=row.insertCell(7); cell.innerHTML='<a title="Add a note for this name" href="#notesSection" onClick="addRef(\'name_input\','+rowNum+',8,newNote());">+</a>';
   cell.className = "n_plus";
	cell=row.insertCell(8); cell.innerHTML='<input class="n_ref" tabindex="1" type="text" name="name_notes'+nameNum+'"/>';
	cell=row.insertCell(9); cell.innerHTML='<a title="Remove this name" href="javascript:void(0);" onClick="removeName('+rowNum+'); return preventDefaultAction(event);">remove</a>';
}

function removeName(rowNum) {
	var tbl=document.getElementById('name_input');
	$('a', tbl.rows[rowNum]).unbind('click');
	tbl.deleteRow(rowNum);
	var numRows=tbl.rows.length;
	for (var i=rowNum;i<numRows;i++) {
		var row=tbl.rows[i];
		var j=i-1;
		row.cells[0].getElementsByTagName('select')[0].name='alt_name'+j;
      row.cells[1].getElementsByTagName('input')[0].name='title_prefix'+j;
		row.cells[2].getElementsByTagName('input')[0].name='given'+j;
		row.cells[3].getElementsByTagName('input')[0].name='surname'+j;
		row.cells[4].getElementsByTagName('input')[0].name='title_suffix'+j;
		$('a', tbl.rows[i].cells[5]).removeAttr("onClick").unbind('click').click(function(y){ return function(event) { addRef('name_input',y,6,newSource()); }}(i));
		row.cells[6].getElementsByTagName('input')[0].name='name_sources'+j;
		$('a', tbl.rows[i].cells[7]).removeAttr("onClick").unbind('click').click(function(y){ return function(event) { addRef('name_input',y,8,newNote()); }}(i));
		row.cells[8].getElementsByTagName('input')[0].name='name_notes'+j;
		$('a', tbl.rows[i].cells[9]).removeAttr("onClick").unbind('click').click(function (y){ return function(event) { removeName(y); return preventDefaultAction(event); }}(i));
//		$('a', tbl.rows[i].cells[9]).removeAttr("onClick").unbind('click').click(new Function('event', 'removeName(' + i + '); return preventDefaultAction(event)'));
	}
	if (numRows==2) {
		$('span', tbl.rows[1].cells[0]).css('display','none');
	}
}

function addRef(tbl,row,col,ref) {
	var input=document.getElementById(tbl).rows[row].cells[col].getElementsByTagName('input')[0];
	if (input.value.length>0) { ref = ', ' + ref; }
	input.value+=ref;
}

function addRefToSrc(tbl,row,ix,ref) {
	var input=document.getElementById(tbl).rows[row].cells[1].getElementsByTagName('input')[ix];
	if (input.value.length>0) { ref = ', ' + ref; }
	input.value+=ref;
}

function moveAutoCompletes(tbl,col,inc) {
	for (var i=1; i<tbl.rows.length; i+=inc) {
		$('input', tbl.rows[i].cells[col]).autocompleteMove();
	}
}

function addEventFact(eventTypes) {
	eventTypes=eventTypes.split(',');
	var tbl=document.getElementById('event_fact_input');
	var rowNum=tbl.rows.length;
   var efNum=(rowNum-1)/2;
	var row=tbl.insertRow(rowNum);
	var cell=row.insertCell(0);
	var sel='<select class="ef_select" tabindex="1" name="event_fact'+efNum+'"><option value="Unknown">Type of event</option>';
	for (var i=0;i<eventTypes.length;i++) {
		var eventType = eventTypes[i].replace(/~/g,"'");
		var attrs;
		var value;
		if (eventType.substring(0,1)=='=') {
			eventType = eventType.substring(1,eventType.length);
			attrs = ' disabled="disabled" style="color:GrayText"';
			value = ''
		}
		else {
			attrs = '';
			if (eventType.substring(0,1)==':') {
				value = eventType.substring(1,eventType.length);
				eventType = "\xa0\xa0"+value
			}
			else {
				value = eventType
			}
		}
		sel += '<option value="'+value+'"'+attrs+'>'+eventType+'</option>'
	}
	cell.innerHTML=sel+'</select>';
	cell.getElementsByTagName('select')[0].focus();
	cell=row.insertCell(1); cell.innerHTML='<input class="ef_date" tabindex="1" type="text" name="date'+efNum+'"/>';
	cell=row.insertCell(2); cell.innerHTML='<input class="ef_place" tabindex="1" type="text" name="place'+efNum+'"/>';
	$('input', cell).autocomplete({ defaultNs:'Place', dontCache: true, matchCommaPhrases:1, ignoreCase:1});
	cell=row.insertCell(3); cell.colSpan=2; cell.innerHTML='<input class="ef_desc" tabindex="1" type="text" name="desc'+efNum+'"/>';
   cell=row.insertCell(4); cell.innerHTML='<a title="Remove this event/fact" href="javascript:void(0);" onClick="removeEventFact('+(efNum+1)+'); return preventDefaultAction(event);">remove</a>';
   row=tbl.insertRow(rowNum+1);
   cell=row.insertCell(0); cell.colSpan=2; cell.innerHTML='';
	cell=row.insertCell(1); cell.className='ef_ref';
   cell.innerHTML='<a title="Add a source for this event/fact" href="#sourcesSection" onClick="addRef(\'event_fact_input\','+(rowNum+1)+',1,newSource());">+</a>'
                 +'<input tabindex="1" type="text" name="sources'+efNum+'"/>';
	cell=row.insertCell(2); cell.className='ef_ref';
   cell.innerHTML='<a title="Add an image for this event/fact" href="#imagesSection" onClick="addRef(\'event_fact_input\','+(rowNum+1)+',2,newImage());">+</a>'
                 +'<input tabindex="1" type="text" name="images'+efNum+'"/>';
	cell=row.insertCell(3); cell.className='ef_ref';
   cell.innerHTML='<a title="Add a note for this event/fact" href="#notesSection" onClick="addRef(\'event_fact_input\','+(rowNum+1)+',3,newNote());">+</a>'
                 +'<input tabindex="1" type="text" name="notes'+efNum+'"/>';
	// when we insert the first alt event, the standard event cells move over
	if (tbl.rows[rowNum-1].cells[0].getElementsByTagName('select').length == 0) {
		moveAutoCompletes(tbl,2,2);
	}
}

function removeEventFact(efNum) {
   var rowNum=(efNum-1)*2+1;
	var tbl=document.getElementById('event_fact_input');
	var numRows=tbl.rows.length;
	var row;
	for (var i=rowNum;i<numRows-2;i+=2) {
		row=tbl.rows[i];
		var copyRow=tbl.rows[i+2];
      var j;
		$(row.cells[0]).find('select').val($(copyRow.cells[0]).find('select').val());
		for (j=1; j<=3; j++) {
			$(row.cells[j]).find('input').val($(copyRow.cells[j]).find('input').val());
		}
      row=tbl.rows[i+1];
      copyRow=tbl.rows[i+3];
      for (j=1; j<=3; j++) {
         $(row.cells[j]).find('input').val($(copyRow.cells[j]).find('input').val());
      }
	}
   $('input', tbl.rows[numRows-2].cells[2]).autocompleteRemove();
    for (i=1; i <= 2; i++) {
         tbl.deleteRow(numRows-i);
    }
	// when we remove the last alt event, the standard event cells move over
	if (tbl.rows[tbl.rows.length-1].cells[0].getElementsByTagName('select').length == 0) {
		moveAutoCompletes(tbl,2,2);
	}
}

function addSource() {
	newSource();
}

function newSource() {
	var tbl=document.getElementById('source_input');
	var rowNum=tbl.rows.length;
	var srcNum=rowNum/5;
	var srcId='S'+(srcNum+1);
	var row=tbl.insertRow(rowNum);
	var cell=row.insertCell(0); cell.align='right'; cell.style.paddingTop="13px"; cell.innerHTML='<b>Citation ID</b>';
	cell=row.insertCell(1); cell.style.paddingTop="13px"; 
	cell.innerHTML=srcId+'<input type="hidden" name="source_id'+srcNum+'" value="'+srcId+'"/>&nbsp;&nbsp;&nbsp;<a title="Remove this source" href="javascript:void(0);" onClick="removeSource('+(srcNum+1)+'); return preventDefaultAction(event);">remove</a>';
	row=tbl.insertRow(rowNum+1);
	cell=row.insertCell(0); cell.align='right'; cell.innerHTML='Source';
	cell=row.insertCell(1);
	cell.innerHTML='<span class="s_source">'
        +'<select id="source_namespace'+srcNum+'" class="s_select" tabindex="1" name="source_namespace'+srcNum+'" onChange="changeSourceNamespace('+srcNum+',\''+srcId+'\')">'
        +'<option value="" value="0" selected="selected">Citation only</option><option value="104">Source</option><option value="112">MySource</option></select>'
        +'</span>'
        +'<span class="s_label">Title</span>'
        +'<input id="'+srcId+'input" class="s_title" tabindex="1" type="text" name="source_title'+srcNum+'" value=""/>'
        +'&nbsp;<span class="s_findall" style="font-size: 90%"><a id="'+srcId+'choose" style="visibility:hidden" href="javascript:void(0);" onClick="choose(0,\''+srcId+'input\'); return preventDefaultAction(event);">find/add&nbsp;&raquo;</a></span>';
    row=tbl.insertRow(rowNum+2);
	cell=row.insertCell(0); cell.align="right"; cell.innerHTML='Record&nbsp;name';
	cell=row.insertCell(1);
	cell.innerHTML='<input class="s_recordname" tabindex="1" type="text" name="record_name'+srcNum+'" value=""/>'
	    +'<span class="s_label">Images&nbsp;<a title="Add an image to this citation" href="#imagesSection" onClick="addRefToSrc(\'source_input\','+(rowNum+2)+',1,newImage());">+</a></span>'
	    +'<input class="s_ref s_ref-images" tabindex="1" type="text" name="source_images'+srcNum+'" value=""/>'
	    +'<span class="s_label">Notes&nbsp;<a title="Add a note to this citation" href="#notesSection" onClick="addRefToSrc(\'source_input\','+(rowNum+2)+',2,newNote());">+</a></span>'
	    +'<input class="s_ref s_ref-notes" tabindex="1" type="text" name="source_notes'+srcNum+'" value=""/>';
	row=tbl.insertRow(rowNum+3);
	cell=row.insertCell(0); cell.align="right"; cell.innerHTML='Volume / Pages';
	cell=row.insertCell(1); cell.innerHTML='<input class="s_page" tabindex="1" type="text" name="source_page'+srcNum+'" value=""/>'
	    +'<span class="s_widelabel">Date</span>'
	    +'<input class="s_date" tabindex="1" type="text" name="source_date'+srcNum+'" value=""/>';
	row=tbl.insertRow(rowNum+4);
	cell=row.insertCell(0); cell.align='right'; cell.innerHTML='Text /<br/>Transcription<br/>location';
	cell=row.insertCell(1); cell.colSpan=10; cell.innerHTML='<textarea class="s_text" tabindex="1" name="source_text'+srcNum+'" rows="3"></textarea>';
	return srcId;
}

function removeSource(srcNum) {
	replaceId('S'+srcNum,'',6,1,-1);
	var rowNum=(srcNum-1)*5;
	var tbl=document.getElementById('source_input');
	var numRows=tbl.rows.length;
	for (var i=rowNum;i<numRows-5;i+=5) {
		srcNum=i/5;
		var srcId = 'S'+(srcNum+1);
		replaceId('S'+(srcNum+2),srcId,6,1,-1);
		var row=tbl.rows[i+1];
		var copyRow=tbl.rows[i+6];
		$(row.cells[1]).find('select').val($(copyRow.cells[1]).find('select').val());
		$(row.cells[1]).find('input').val($(copyRow.cells[1]).find('input').val());
        row=tbl.rows[i+2];
        copyRow=tbl.rows[i+7];
		$(row.cells[1]).find('input.s_recordname').val($(copyRow.cells[1]).find('input.s_recordname').val());
		$(row.cells[1]).find('input.s_ref-images').val($(copyRow.cells[1]).find('input.s_ref-images').val());
		$(row.cells[1]).find('input.s_ref-notes').val($(copyRow.cells[1]).find('input.s_ref-notes').val());
		row=tbl.rows[i+3];
		copyRow=tbl.rows[i+8];
		$(row.cells[1]).find('input.s_page').val($(copyRow.cells[1]).find('input.s_page').val());
		$(row.cells[1]).find('input.s_date').val($(copyRow.cells[1]).find('input.s_date').val());
		$(tbl.rows[i+4].cells[1]).find('textarea').val($(tbl.rows[i+9].cells[1]).find('textarea').val());
		changeSourceNamespace(srcNum, srcId);
	}
  	$('input', tbl.rows[numRows-4].cells[1]).autocompleteRemove();
	for (i=1; i <= 5; i++) {
  		tbl.deleteRow(numRows-i);
	}
}

function addImage() {
	newImage();
}

function newImage() {
	var tbl=document.getElementById('image_input');
	var rowNum=tbl.rows.length;
	$(tbl).css('display','block');
	var row=tbl.insertRow(rowNum);
	var imgNum=rowNum-1;
	var imgId='I'+rowNum;
	var cell=row.insertCell(0); cell.align='center'; cell.innerHTML=imgId+'<input type="hidden" name="image_id'+imgNum+'" value="'+imgId+'"/>';
	cell=row.insertCell(1); cell.align='center'; cell.innerHTML='<input tabindex="1" type="checkbox" name="image_primary'+imgNum+'"/>';
	cell=row.insertCell(2); cell.innerHTML='<input id="'+imgId+'input" tabindex="1" type="text" size=40 name="image_filename'+imgNum+'"/>';
	$('input', cell).autocomplete({ defaultNs:'Image', userid:userId});
	cell.getElementsByTagName('input')[0].focus();
	cell=row.insertCell(3); cell.innerHTML='<span style="font-size: 90%"><a href="javascript:void(0);" onClick="choose(6,\''+imgId+'input\'); return preventDefaultAction(event);">find&nbsp;&raquo;</a>&nbsp;<br><a href="javascript:void(0);" onClick="uploadImage(\''+imgId+'input\'); return preventDefaultAction(event);">add&nbsp;&raquo;</a>&nbsp;</span>';
	cell=row.insertCell(4); cell.innerHTML='<input tabindex="1" type="text" size=30 name="image_caption'+imgNum+'"/>';
	cell=row.insertCell(5); cell.innerHTML='<a title="Remove this image" href="javascript:void(0);" onClick="removeImage('+rowNum+'); return preventDefaultAction(event);">remove</a>';
	return imgId;
}

function removeImage(rowNum) {
	replaceId('I'+rowNum,'',-1,2,7);
	var tbl=document.getElementById('image_input');
	var numRows=tbl.rows.length;
	for (var i=rowNum;i<numRows-1;i++) {
		var row=tbl.rows[i];
		var copyRow=tbl.rows[i+1];
		replaceId('I'+(i+1),'I'+i,-1,2,7);
		$(row.cells[1]).find('input')[0].checked = $(copyRow.cells[1]).find('input')[0].checked;
		for (var j=2; j<=4; j++) {
			$(row.cells[j]).find('input').val($(copyRow.cells[j]).find('input').val());
		}
	}
  	$('input', tbl.rows[numRows-1].cells[1]).autocompleteRemove();
  	tbl.deleteRow(numRows-1);
	if (tbl.rows.length==1) {
		$(tbl).css('display','none');
	}
}

function addNote() {
	newNote();
}

function newNote() {
	var tbl=document.getElementById('note_input');
	var rowNum=tbl.rows.length;
	$(tbl).css('display','block');
	var row=tbl.insertRow(rowNum);
	var noteNum=rowNum-1;
	var noteId='N'+rowNum;
	var cell=row.insertCell(0); cell.align='center'; cell.innerHTML=noteId+'<input type="hidden" name="note_id'+noteNum+'" value="'+noteId+'"/>';
	cell=row.insertCell(1); cell.innerHTML='<textarea tabindex="1" name="note_text'+noteNum+'" rows="3" cols="85"></textarea>';
	cell.getElementsByTagName('textarea')[0].focus();
	cell=row.insertCell(2); cell.innerHTML='<a title="Remove this Note" href="javascript:void(0);" onClick="removeNote('+rowNum+'); return preventDefaultAction(event);">remove</a>';
	return noteId;
}

function removeNote(rowNum) {
	replaceId('N'+rowNum,'',8,3,9);
	var tbl=document.getElementById('note_input');
	var numRows=tbl.rows.length;
	for (var i=rowNum;i<numRows-1;i++) {
		var row=tbl.rows[i];
		var copyRow=tbl.rows[i+1];
		replaceId('N'+(i+1),'N'+i,8,3,9);
		$(row.cells[1]).find('textarea').val($(copyRow.cells[1]).find('textarea').val());
	}
  	tbl.deleteRow(numRows-1);
	if (tbl.rows.length==1) {
		$(tbl).css('display','none');
	}
}

function fixupPersonFamilyRows(name, tbl, start) {
   var numRows = tbl.rows.length;
   if (!start) start = 0;
   for (var i = start; i < numRows; i++) {
      var row=tbl.rows[i];
      $('input', row.cells[0]).val(i+1).attr('name',name+'_id'+i);
      $('input', row.cells[1]).attr('name',name+i).attr('id',name+i);
      $('a', row.cells[2]).removeAttr('onclick').unbind('click').click((function(i) {
         return function(event) {
            removePersonFamily(name,i);
            return preventDefaultAction(event);
         }
      }(i)));
   }
}

function removePersonFamily(name, rowNum) {
	var tbl=document.getElementById(name+'_table');
   tbl.deleteRow(rowNum);
   fixupPersonFamilyRows(name, tbl, rowNum);
   if (tbl.rows.length == 0 && (name == 'husband' || name == 'wife' || name == 'child_of_family')) {
      $('#'+name+'_addlink').show();
   }
}

function choose_internal(ns,id,gender,given,surname) {
	var parms = '';
	if (ns == 108) {
      parms = '&gnd='+encodeURIComponent(gender)+'&g='+encodeURIComponent(given)+'&s='+encodeURIComponent(surname);
	}
   else if (ns == 110 && id.indexOf('spouse_of_family') === 0) {
      given = $.trim($('#given0').attr('value'));
      if (!given || given == 'Unknown') given = '';
//      var pos = given.indexOf(' ');
//      if (pos > 0) {
//         given = given.substr(0, pos);
//      }
		surname = $.trim($('#surname0').attr('value'));
		if (!surname || surname == 'Unknown') surname = '';
		gender = $('#gender').attr('value');
      if ((!given && !surname) || !(gender=='M' || gender=='F')) {
         alert('You must enter this person\'s name and gender first');
         return false;
      }
      parms += '&gnd='+encodeURIComponent(gender);
		parms += (gender=='M' ? '&hg=' : '&wg=')+encodeURIComponent(given);
		parms += (gender=='M' ? '&hs=' : '&ws=')+encodeURIComponent(surname);
   }
   else if (ns == 104 || ns == 118) { // source or transcript
      parms = '&st='+encodeURIComponent($('#'+id).val());
   }
   else if (ns == 112) { // mysource
      parms = '&t='+encodeURIComponent($('#'+id).val());
   }
	var page;
	if (ns == 6) {
		page='Special:Search';
		parms += '&ns=Image';
	}
	else {
		page = 'Special:AddPage';
		parms += '&namespace='+encodeURIComponent(ns)
	}
	var width = 875;
	if (width > screen.width) {
		width = screen.width;
	}
	var choose=window.open('/wiki/'+page+'?target='+id+parms,'','height=600,width='+width+',scrollbars=yes,resizable=yes,toolbar=yes,menubar=no,location=no,directories=no');
	return true;
}

function addPage(name, gender, given, surname) {
   var ns='';
   var nsNum;
   var className='';
   if (name=='spouse_of_family' || name=='child_of_family') {
      className=='family_input';
      ns='Family';
      nsNum=110;
   }
   else {
      className=='person_input';
      ns='Person';
      nsNum=108;
   }
   var tbl=document.getElementById(name+'_table');
   var rowNum=tbl.rows.length;
   var row=tbl.insertRow(rowNum);
   var inputNum=rowNum;
   var cell=row.insertCell(0); cell.innerHTML='<input type="hidden" name="'+name+'_id'+inputNum+'" value="'+(inputNum+1)+'"/>';
   cell=row.insertCell(1); cell.innerHTML='<input id="'+name+inputNum+'" class="'+className+'" tabindex="1" type="text" size=40 name="'+name+inputNum+'" value=""/>';
   cell=row.insertCell(2); cell.innerHTML='<a href="javascript:void(0);" onClick="removePersonFamily(\''+name+'\','+inputNum+'); return preventDefaultAction(event);">remove</a>';
   $('#'+name+inputNum).autocomplete({ defaultNs:ns, userid:userId});
   var ok = choose_internal(nsNum, name+inputNum, gender, given, surname);
   if (!ok) {
      tbl.deleteRow(rowNum);
   }
   else if (name == 'husband' || name == 'wife' || name == 'child_of_family') {
      $('#'+name+'_addlink').hide();
   }
}

function choose(ns, id) {
	var choose=choose_internal(ns,id, '', '', '');
}

function uploadImage(id) {
	var title = document.URL.replace(/.*?\?title=(.*?)&.*/i, "$1");
	var upload=window.open('/wiki/Special:Upload?target='+title+'&id='+id,'','height=600,width=700,scrollbars=yes,resizable=yes,toolbar=yes,menubar=no,location=no,directories=no');
}

function changeSourceNamespace(srcNum,id) {
	var ns = $('#source_namespace'+srcNum).val();
	if (ns == 104 || ns == 112) {
		var nsText = (ns == 104 ? 'Source' : 'MySource');
		$('#'+id+'input').autocompleteRemove().autocomplete({ defaultNs:nsText, userid:userId});
		$('#'+id+'choose').attr('href','javascript:void(0);').removeAttr("onClick").unbind('click').click(function(event) { choose(ns,id+'input'); return preventDefaultAction(event); }).css('visibility','visible');
	}
	else {
		$('#'+id+'input').autocompleteRemove();
		$('#'+id+'choose').css('visibility','hidden');
	}
}

$(document).ready(function() {
    // Initialise the table
    $("#child_of_family_table, #spouse_of_family_table, #husband_table, #wife_table, #child_table").tableDnD({onDrop: function(table, row) {
       var id = $(table).attr('id');
       var name = id.substr(0, id.length-6);
       fixupPersonFamilyRows(name, table);
    }});
//   $('#event_fact_input .ef_ref input').ezpz_hint();
//   $('#name_input .n_ref').ezpz_hint();
});