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
  var comments = encodeURIComponent(prompt("Enter any comments you wish to leave on the Talk page", "none")); 
  // If user selected "cancel" (comments = 'null'), don't proceed with the template
  if ( comments !== 'null' ) {
	  $.getJSON('/w/index.php?action=ajax&rs=wfAddVerifiedTemplate&pid=' + pageId + '&ns=' + ns + '&title=' + title + '&template=' + template + '&desc=' + desc +
          '&comments=' + comments + '&callback=?', function(success) {
      if (success) {
        $('#' + 'verify' + rowNum).replaceWith('<span class="attn">&nbsp;Marked verified</span>');
      }  
      else {
        $('#' + 'verify' + rowNum).replaceWith('<span class="attn">&nbsp;Failed</span>');
      }
    })
  }  
}

/* Add a template to a Talk page to indicate that the user has defered resolution of issues on a Person/Family page for now. The Talk page will be created if it doesn't already exist. */
function addDeferredTemplate(rowNum, pageId, ns, title, desc) {
  var comments = encodeURIComponent(prompt("Enter any comments you wish to leave on the Talk page", "none")); 
  // If user selected "cancel" (comments = 'null'), don't proceed with the template
  if ( comments !== 'null' ) {
	  $.getJSON('/w/index.php?action=ajax&rs=wfAddDeferredTemplate&pid=' + pageId + '&ns=' + ns + '&title=' + title + '&desc=' + desc + '&comments=' + comments + '&callback=?', function(success) {
      if (success) {
        $('#' + 'defer' + rowNum).replaceWith('<span class="attn">&nbsp;Deferred</span>');
      }
      else {
        $('#' + 'defer' + rowNum).replaceWith('<span class="attn">&nbsp;Failed</span>');
      }
    })   
  }
}
