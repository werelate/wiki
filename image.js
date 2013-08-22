function addImagePage(type) {
	var tbl=document.getElementById('image_'+type+'_table');
	var id=tbl.rows.length;
	var row=tbl.insertRow(tbl.rows.length);
	var cell=row.insertCell(0); 
	cell.innerHTML='<input type="hidden" name="'+type+'_id'+id+'" value="'+(id+1)+'"/><input class="'+type+'_input" type="text" size=40 name="'+type+id+'" value=""/>';
	var ns=type.substr(0,1).toUpperCase()+type.substr(1).toLowerCase();
	var inp=cell.getElementsByTagName('input')[1];
	$(inp).autocomplete({ defaultNs:ns, userid:userId});
	inp.focus();
}

$(document).ready(function() {
	$('#wpDestFile').blur( function () { 
		var src = $('#wpUploadFile').val();
		var dest = $(this).val().replace(/[<\[{>\]}|#?+]+/g, "");
		var pos = src.lastIndexOf('.');
		if (pos > 0) {
			var ext = src.substring(pos);
			if (dest.length < ext.length || dest.substr(dest.length - ext.length).toLowerCase() != ext.toLowerCase()) {
				dest += ext;
			}
		}
		$(this).val(dest);
	});
});