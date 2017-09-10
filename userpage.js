function addResearchInput() {
   var tbl=document.getElementById('researching_table');
   var rowNum=tbl.rows.length;
   $(tbl).css('display','block');
   var row=tbl.insertRow(rowNum);
   var inputNum=rowNum-1;
   var cell=row.insertCell(0);
   cell.innerHTML='<input type="hidden" name="researching_id'+inputNum+'" value="'+rowNum+'"/><input tabindex="1" type="text" size=20 name="researching_surname'+inputNum+'" value=""/>';
   cell = row.insertCell(1);
   cell.innerHTML='<input class="place_input" tabindex="1" type="text" size=30 name="researching_place'+inputNum+'" value=""/>';
   $('input', cell).autocomplete({ defaultNs:'Place', dontCache: true, matchCommaPhrases:1, ignoreCase:1});
}
