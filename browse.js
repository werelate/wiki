function browseGo(title) {
	if (title.length == 0) {
		title = document.getElementById('titleinput').value;
	}
	browseRequest(title, 0);
}

function browseChangeNs(title) {
	var nsElm = document.getElementById('namespace');
	var ns = nsElm.options[nsElm.selectedIndex].value;
	$('#wr-searchbrowse a').attr('href', $('#wr-searchbrowse a').attr('href').replace(/namespace=\d+/, 'namespace='+ns));
	browseGo(title);
}

function browsePrev() {
	var title = document.getElementById('results').rows[1].cells[0].getElementsByTagName('a')[0].innerHTML;
	browseRequest(title, -1);
}

function browseNext() {
	var title = document.getElementById('results').rows[10].cells[0].getElementsByTagName('a')[0].innerHTML;
	browseRequest(title, 1);
}

function browseEntityEncode(s) {
    return s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

function browseRequest(title, dir) {
	var nsElm = document.getElementById('namespace');
	var ns = nsElm.options[nsElm.selectedIndex].value;
	var scopeElms = document.forms['browse'].elements['scope']
	var scope = '';
	var i;
 	for (i = 0; i < scopeElms.length; i++) {
 		if (scopeElms[i].checked) {
 			scope = scopeElms[i].value;
 		}
 	}
	$('#pleasewait').css('display','inline');
	// !!! do I need to escape(title)?
	$.get('/w/index.php', {action: 'ajax', rs: 'wfBrowse', rsargs: 'scope=' + scope + '|ns=' + ns + '|dir=' + dir + '|title=' + title}, function(data) {
	   var tbl = document.getElementById('results');
	   var results = data.getElementsByTagName('browse');
	   var status = results[0].getAttribute('status');
	   var ns = results[0].getAttribute('ns');
	   var nsText = results[0].getAttribute('nsText');
	   var title = results[0].getAttribute('title');
	   var dir = results[0].getAttribute('dir');
	   var prev = results[0].getAttribute('prev');
	   var next = results[0].getAttribute('next');
	   var elms = data.getElementsByTagName('result');
		for (i = 0; i < 10; i++) {
			var cell=tbl.rows[i+1].cells[0];
			if (i < elms.length) {
				var t = elms[i].childNodes[0].nodeValue;
				cell.innerHTML = '<a href="/wiki/' + encodeURIComponent(nsText+':'+t).replace(/%2F/ig,'/') + '">' + browseEntityEncode(t) + '</a>';
			}
			else {
				cell.innerHTML = '';
			}
		}
	   if (dir == 0) {
			var nsElm = document.getElementById('namespace');
		   var len = nsElm.options.length;
		   for (i = 0; i < len; i++) {
		   	if (nsElm.options[i].value == ns) {
		   		nsElm.selectedIndex = i;
		   		break;
		   	}
		   }
		   var t = document.getElementById('titleinput');
		   if (t.value.length > 0) {
				t.value = title;
		   }
	   }
	   var msg = document.getElementById('message');
	   if (status == 0) {
	   	msg.innerHTML = 'Choose a page from the list';
	   }
	   else if (status == -2) {
	   	msg.innerHTML = 'You must sign in to browse your watchlist';
	   }
	   else {
	   	msg.innerHTML = 'Internal error (please try again)';
	   }
		$('#prevlink').css('display',prev ? 'inline' : 'none');
		$('#prevlink2').css('display',prev ? 'inline' : 'none');
		$('#nextlink').css('display',next ? 'inline' : 'none');
		$('#nextlink2').css('display',next ? 'inline' : 'none');
		$('#pleasewait').css('display','none');
	});
}

$(document).ready(function() {
	document.getElementById('titleinput').focus();
});
