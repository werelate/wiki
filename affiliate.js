function wr_escape(s) {
	return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\"/g, '&quot;');
}

function wr_formatFootnote(ads, data) {
	var count = '';
	var result = data.getElementsByTagName('result')[0].getElementsByTagName('doc')[0];
	if (result != null) {
		for (var i=0;i<result.childNodes.length;i++) {
			if (result.childNodes[i].getAttribute('name') == 'count') {
				count = result.childNodes[i].firstChild.nodeValue;
				if (count.length > 3) {
					count = count.substr(0,count.length-3)+','+count.substr(count.length-3,3);
				}
				break;
			}
		}
	}
	if (count.length > 0) {
		ads.append(
'<form name="footnote" action="http://www.footnote.com/searchdocuments.php?xid=43&img=25&kbid=1298" method="post" style="border: 1px solid #bbb;width: 120px;  url(http://www.footnote.com/i/bgContent.gif) repeat-x 0 0;">'+
'<p style="margin: 8px 0; text-align: center"><a style="font-size: 190%; font-weight: bold; text-decoration: none; color: #b84702" href="#" onclick="document.footnote.submit();">'+count+'</a></p>'+
'<p style="margin: 4px 0; text-align: center; color: #b84702; font-weight: bold; font-size: 110%">'+wr_surname+"'"+ (wr_surname.substr(wr_surname.length-1,1) == 's' ? '' : 's') +'</p>'+
'<p style="text-align: center; margin: 0 0 4px 0">'+
'found in<br>'+
'<span style="font-weight: bold; font-size: 85%">Revolutionary War Pensions</span><br>'+
'at<br>'+
'<input name="query" type="hidden" value="'+wr_surname+'" id="gsi"/>'+
'<input name="collection" value="10936943" id="collection" type="hidden"/>'+
'<input type="image" src="http://www.footnote.com/i/affimg/120x60_download.gif" value="Search" />'+
'</p>'+
'</form><div>&nbsp;</div>'
		);
	}
	ads.append(
'<form action="http://www.footnote.com/results.php?xid=31&img=31&kbid=1298&links=0" method="post" style="border: 1px solid #bbb;width: 120px; url(http://www.footnote.com/i/bgContent.gif) repeat-x 0 0;">'+
'<div align="center"> <a href="http://www.footnote.com/genealogyrecords.php?img=31&kbid=1298&xid=31">'+
'<img src="http://www.footnote.com/i/affimg/88x31_Logo.gif" vspace="10" width="88" height="31" border="0" alt="Footnote.com" /></a></div>'+
'<ul style="margin: 0 0 0 3px;padding: 0 0 0 1em; font: 12px Verdana,Arial,sans-serif;">'+
'<li>Revolutionary War Records</li>'+
'<li>Civil War Records</li>'+
'<li>Naturalization Records</li>'+
'<li>and More...</li>'+
'</ul>'+
'<p style="margin: 0;font: bold 13px \'Trebuchet MS\', sans-serif; color: #cc4e01; text-align:center;">Search Millions Of Original Documents</p>'+
'<div align="center" style="margin-bottom: 4px;">'+
'<input name="query" type="text" id="gsi" size="10" style="color:#555;" onfocus="if(this.value==\'Enter a name\'){this.value=\'\';};if(this.style==\'color:#555\'){this.style=\'color:#555\';}" onblur="if(this.value==\'\'){this.value=\'Enter a name\';};" value="Enter a name"/>'+
'<input type="submit" value="Search" style="margin: 5px 10px 0 10px;" />'+
'<input name="collection" value="-1" id="collection" type="hidden"/>'+
'<input name="bc" value="All" id="bc" type="hidden"/>'+
'</div>'+
'</form>'
	);
}

function wr_formatAmazon(ads, data) {
	var results = data.getElementsByTagName('result')[0].getElementsByTagName('doc');
	var books = [];
	for (var i=0;i<results.length;i++) {
		var result = {};
		for (var j=0;j<results[i].childNodes.length;j++) {
			var name = results[i].childNodes[j].getAttribute('name');
			var value = results[i].childNodes[j].firstChild.nodeValue;
			result[name] = value;
		}
		result['chosen']=false;
		books[i] = result;
	}
	for (i=0;i<3 && i<books.length;i++) {
		var found = true;
		while (found) {
			j = Math.floor(Math.random()*books.length);
			found = books[j]['chosen'];
		}
		books[j]['chosen']=true;
	}
	var rows = '';
	for (i=0;i<books.length;i++) {
		var book = books[i];
		if (book['chosen']) {
			var image = '';
			var title = '';
			if (book['imageurl']) {
				if (book['imageheight'] > 80) {
					book['imagewidth'] = Math.ceil(book['imagewidth'] * 80 / book['imageheight']);
					book['imageheight'] = 80;
				}
				image = '<a href="'+book['url']+'"><img height="'+book['imageheight']+'" width="'+book['imagewidth']+'" src="'+book['imageurl']+'"></a><br>';
			}
			else {
				image = '<a href="'+book['url']+'"><img height="40" width="60" src="http://ecx.images-amazon.com/images/G/01/x-site/icons/no-img-sm.gif"></a><br>';
			}
			if (book['title']) {
				if (book['title'].length > 80) {
					book['title'] = book['title'].substr(0,77)+'...';
				}
				title = '<a style="text-decoration: none; color: black" href="'+book['url']+'">'+wr_escape(book['title'])+'</a>';
			}
			ads.append('<div align="center" style="border: 1px solid #bbb; width: 120px; margin: 8px 0; text-align: center">'+image+title+'</div>');
		}
	}
	ads.append('<iframe src="http://rcm.amazon.com/e/cm?t=werelaorg-20&o=1&p=20&l=qs1&f=ifr" width="120" height="90" frameborder="0" scrolling="no"></iframe>')
}

function wr_formatAncestry(ads) {
	ads.append('This is an ancestry ad');
}

$(document).ready(function() {
	var affil = Math.ceil(Math.random()*2);
	var params = { surname: wr_surname, place: wr_place, adkey: wr_adkey };
	var ads = $('#ads');
	if (affil == 1 && wr_surname.length > 0) {
		params['affiliate'] = 'footnote';
		$.get('/w/affiliate.php', params, function(data) { wr_formatFootnote(ads, data); });
	}
	else if (affil == 2 || (affil == 1 && wr_surname.length == 0)) {
		params['affiliate'] = 'amazon';
		$.get('/w/affiliate.php', params, function(data) { wr_formatAmazon(ads, data); });
	}
	else if (affil == 3) {
		wr_formatAncestry(ads);
	}
});