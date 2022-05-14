/* Enable or disable one field (toggleId) based on the value of another field (checkId). */
function toggleEnabled(checkId, checkValue, toggleId) {
  if (document.getElementById(checkId).value==checkValue) {
    document.getElementById(toggleId).disabled=false;
  }  
  else {
    document.getElementById(toggleId).disabled=true;
  }
}

/* Add a template to a Talk page to indicate that an anomaly has been verified. The Talk page will be created if it doesn't already exist. */
function addVerifiedTemplate(rowNum, pageId, ns, title, template, desc) {
	$.getJSON('/w/index.php?action=ajax&rs=wfAddVerifiedTemplate&pid=' + pageId + '&ns=' + ns + '&title=' + title + '&template=' + template + '&desc=' + desc + '&callback=?', function(success) {
     if (success) {
       $('#' + 'verify' + rowNum).replaceWith('<span class="attn">&nbsp;Marked verified</span>');
     }
     else {
       $('#' + 'verify' + rowNum).replaceWith('<span class="attn">&nbsp;Failed</span>');
     }
  })
}

/* Add a template to a Talk page to indicate that the user doesn't want to see the Person/Family page on the DQ report for now. The Talk page will be created if it doesn't already exist. */
function addDeferredTemplate(rowNum, pageId, ns, title) {
  var comments = encodeURIComponent(prompt("Do you wish to leave comments on the Talk page?", "none")); 
	$.getJSON('/w/index.php?action=ajax&rs=wfAddDeferredTemplate&pid=' + pageId + '&ns=' + ns + '&title=' + title + '&comments=' + comments + '&callback=?', function(success) {
     if (success) {
       $('#' + 'defer' + rowNum).replaceWith('<span class="attn">&nbsp;Deferred</span>');
     }
     else {
       $('#' + 'defer' + rowNum).replaceWith('<span class="attn">&nbsp;Failed</span>');
     }
  })
}
