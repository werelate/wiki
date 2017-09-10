function showSourceFields() {
	var type = $('#source_type').val();
	switch(type)
	{
	case 'Book':
		$('#authors_row').show();
		$('#subtitle_row').show();
		$('#publisher_row').show();
		$('#date_issued_row').show();
		$('#place_issued_row').show();
		$('#series_name_row').show();
		$('#volumes_row').show();
		$('#pages_row').hide();
		$('#references_row').show();
	  	break;    
	case 'Article':
		$('#authors_row').show();
		$('#subtitle_row').show();
		$('#publisher_row').show();
		$('#date_issued_row').show();
		$('#place_issued_row').hide();
		$('#series_name_row').show();
		$('#volumes_row').show();
		$('#pages_row').show();
		$('#references_row').show();
		break;
	case 'Government / Church records':
		$('#authors_row').show();
		$('#subtitle_row').show();
		$('#publisher_row').show();
		$('#date_issued_row').show();
		$('#place_issued_row').show();
		$('#series_name_row').show();
		$('#volumes_row').show();
		$('#pages_row').hide();
		$('#references_row').show();
		break;
	case 'Newspaper':
		$('#authors_row').hide();
		$('#subtitle_row').hide();
		$('#publisher_row').hide();
		$('#date_issued_row').hide();
		$('#place_issued_row').show();
		$('#series_name_row').hide();
		$('#volumes_row').hide();
		$('#pages_row').hide();
		$('#references_row').hide();
		break;
	case 'Periodical':
		$('#authors_row').show();
		$('#subtitle_row').hide();
		$('#publisher_row').show();
		$('#date_issued_row').hide();
		$('#place_issued_row').show();
		$('#series_name_row').hide();
		$('#volumes_row').hide();
		$('#pages_row').hide();
		$('#references_row').hide();
		break;
	default:
		$('#authors_row').show();
		$('#subtitle_row').show();
		$('#publisher_row').show();
		$('#date_issued_row').show();
		$('#place_issued_row').show();
		$('#series_name_row').show();
		$('#volumes_row').show();
		$('#pages_row').show();
		$('#references_row').show();
	}	
}

function showSourceERO() {
	var cats = $('#subject').val() || [];
	var n = $('#ethnicity_row');
	if ($.inArray('Ethnic/Cultural',cats) != -1) {
		n.show();
	}
	else {
		n.hide();
	}
	n = $('#religion_row');
	if ($.inArray('Church records',cats) != -1) {
		n.show();
	}
	else {
		n.hide();
	}
	n = $('#occupation_row');
	if ($.inArray('Occupation',cats) != -1) {
		n.show();
	}
	else {
		n.hide();
	}
}

function addRepository(availTypes) {
   var tbl=document.getElementById('repository_table');
   var rowNum=tbl.rows.length;
   $(tbl).css('display','block');
   var row=tbl.insertRow(rowNum);
   var inputNum=rowNum-1;
   var cell=row.insertCell(0);
   cell.innerHTML='<input type="hidden" name="repository_id'+inputNum+'" value="'+rowNum+'"/>';
   cell = row.insertCell(1);
   cell.innerHTML='<input tabindex="1" type="text" size=20 class="repository_input" name="repository_title'+inputNum+'" value=""/>';
   $('input', cell).autocomplete({ defaultNs:'Repository', userid:0});
   cell = row.insertCell(2);
   cell.innerHTML='<input tabindex="1" type="text" size=45 name="repository_location'+inputNum+'" value=""/>';
   cell = row.insertCell(3);
	availTypes=availTypes.split(',');
	var sel='<select tabindex="1" name="availability'+inputNum+'"><option value="" selected="selected">Select</option>';
	for (var i=0;i<availTypes.length;i++) {
		sel+='<option value="'+availTypes[i]+'">'+availTypes[i]+'</option>';
	}
	cell.innerHTML=sel+'</select>';
   cell = row.insertCell(4);
   cell.innerHTML='<a title="Remove this repository" href="javascript:void(0);" onClick="removeRepository('+rowNum+'); return preventDefaultAction(event);">remove</a></td>';
}

function removeRepository(rowNum) {
	var tbl=document.getElementById('repository_table');
	var numRows=tbl.rows.length;
	for (var i=rowNum;i<numRows-1;i++) {
		var row=tbl.rows[i];
		var copyRow=tbl.rows[i+1];
		for (var j=1; j<=2; j++) {
			$(row.cells[j]).find('input').val($(copyRow.cells[j]).find('input').val());
		}
		$(row.cells[3]).find('select').val($(copyRow.cells[3]).find('select').val());
	}
  	$('input', tbl.rows[numRows-1].cells[1]).autocompleteRemove();
  	tbl.deleteRow(numRows-1);
	if (tbl.rows.length==1) {
		$(tbl).css('display','none');
	}
}

$(document).ready(function() {
	showSourceFields();
   showSourceERO();
   $('#subject').multiSelect({
      selectAll:false,
      noneSelected: 'Select',
      oneOrMoreSelected: '*'
   }, function(cb) {
      var n = null;
      if (cb.val() == 'Ethnic/Cultural') {
         n = $('#ethnicity_row');
      }
      else if (cb.val() == 'Church records') {
         n = $('#religion_row');
      }
      else if (cb.val() == 'Occupation') {
         n = $('#occupation_row');
      }
      if (n) {
         if (cb.is(':checked')) {
            n.show();
         }
         else {
            n.hide();
         }
      }
   });
});
