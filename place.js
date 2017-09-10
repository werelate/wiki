$.fn.autocomplete_ui = $.fn.autocomplete;

$(function() {
   function split( val ) {
      return val.split( /,\s*/ );
   }
   function extractLast( term ) {
      return split( term ).pop();
   }
   function filterStartsWith(placeTypes, s, minLength) {
      var results = [];
      s = $.trim(s).toLowerCase();
      if (s.length >= minLength) {
         for (var i=0; i < placeTypes.length; i++) {
            var placeType = placeTypes[i];
            if (s.length <= placeType.length && s === placeType.substring(0, s.length).toLowerCase()) {
               results.push(placeType);
            }
         }
      }
      return results;
   }
   var minLength=1;
   $( "#input_type" ).autocomplete_ui({
         minLength: minLength,
         delay: 0,
         source: function( request, response ) {
            // delegate back to autocomplete, but extract the last term
            response( filterStartsWith( //$.ui.autocomplete.filter(
               wrPlaceTypes, extractLast(request.term), minLength));
         },
         focus: function() {
            // prevent value inserted on focus
            return false;
         },
         select: function( event, ui ) {
            var terms = split( this.value );
            // remove the current input
            terms.pop();
            // add the selected item
            terms.push( ui.item.value );
            // add placeholder to get the comma-and-space at the end
            //terms.push( "" );
            this.value = terms.join( ", " );
            return false;
         }
      });
});
