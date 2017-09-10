<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */
# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSpecialShowVideoSetup";

function wfSpecialShowVideoSetup() {
	global $wgMessageCache, $wgSpecialPages;
	$wgMessageCache->addMessages( array( "showvideo" => "ShowVideo" ) );
	$wgSpecialPages['ShowVideo'] = array('SpecialPage','ShowVideo');
}

/**
 * constructor
 */
function wfSpecialShowVideo() {
	global $wgOut, $wgRequest;

	$thumb = $wgRequest->getVal('thumb');
	$video = $wgRequest->getVal('video');
	$width = $wgRequest->getVal('width');
	$height = $wgRequest->getVal('height');
	$videotitle = $wgRequest->getVal('videotitle');
	$wgOut->setPageTitle($videotitle);
	$wgOut->setArticleRelated(false);
	$wgOut->setRobotpolicy('noindex,nofollow');

	$wgOut->addHtml(<<< END
<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="$width" height="$height">
<param name="movie" value="http://content.screencast.com/bootstrap.swf"></param>
<param name="quality" value="high"></param>
<param name="bgcolor" value="#FFFFFF"></param>
<param name="flashVars" value="thumb=http://content.screencast.com/media/$thumb&content=http://content.screencast.com/media/$video&width=$width&height=$height"></param>
<param name="allowFullScreen" value="true"></param>
<param name="scale" value="showall"></param>
<param name="allowScriptAccess" value="always"></param>
<embed src="http://content.screencast.com/bootstrap.swf" quality="high" bgcolor="#FFFFFF" width="$width" height="$height" type="application/x-shockwave-flash" allowScriptAccess="always" flashVars="thumb=http://content.screencast.com/media/$thumb&content=http://content.screencast.com/media/$video&width=$width&height=$height" allowFullScreen="true" scale="showall"></embed>
</object>
END
	);
}
?>
