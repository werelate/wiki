/* Enable or disable one field (toggleId) based on the value of another field (checkId). */
function toggleEnabled(checkId, checkValue, toggleId) {
  if (document.getElementById(checkId).value==checkValue) {
    document.getElementById(toggleId).disabled=false;
  }  
  else {
    document.getElementById(toggleId).disabled=true;
  }
}
