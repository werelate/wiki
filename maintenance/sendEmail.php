<?php
require_once( 'commandLine.inc' );
require_once('AdminSettings.php');

$db =& wfGetDB( DB_SLAVE );

$rows = $db->select('user', array('user_name'), array('user_id' => 2));
$errno = $db->lastErrno();
if ($errno != 0) {
    die("read users $errno");
}

$count = 0;
$from = 'WeRelate <donotreply@werelate.org>';
$subject = 'Exclusive offer for WeRelate users: 50% off MyHeritage';

while ($row = $db->fetchObject($rows)) {
    $name = $row->user_name;
    $u = User::newFromName($name);
    $dest = $u->getEmail();
    if ($u->mTouched > '20190901000000' && $u->canSendEmail() && $u->canReceiveEmail()) {
        echo $dest.",".$name."\n";
//         $count += 1;
//         $body = wordwrap(getBody($name), 70, "\r\n");
// 		$headers =
// 			"MIME-Version: 1.0\r\n" .
// 			"Content-type: text/html; charset=utf-8\r\n" .
// 			"Content-Transfer-Encoding: 8bit\r\n" .
// 			"X-Mailer: MediaWiki mailer\r\n".
// 			"From: {$from}\r\n".
// 			"Reply-To: ${from}";
// 		$success = mail( $dest, wfQuotedPrintable( $subject ), $body, $headers );
// 		if (!$success) {
//             echo("Error sending mail ".error_get_last()['message']."\n");
// 		}
//         sleep(1);
    }
}
$db->freeResult($rows);
echo "Total $count\n";

function getBody($name) {
return <<<EOM
<p>&nbsp;</p>
<!-- [if gte mso 9]>
	<xml>
		<o:OfficeDocumentSettings>
		<o:AllowPNG/>
		<o:PixelsPerInch>96</o:PixelsPerInch>
		</o:OfficeDocumentSettings>
	</xml>
	<![endif]-->
<p></p>
<!-- [if gte mso 9]>
	<style type="text/css" media="all">
		sup { font-size: 100% !important; }
	</style>
	<![endif]-->
<table class="gwfw" border="0" width="100%" cellspacing="0" cellpadding="0" bgcolor="#f7f7f7">
<tbody>
<tr>
<td class="px-10 mpx-0" style="padding-left: 10px; padding-right: 10px;" align="center" valign="top">
<table class="m-shell" border="0" width="600" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="td" style="width: 600px; min-width: 600px; font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal;"><!-- Header -->
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="pb-20 pt-30 px-20 img-center" style="font-size: 0pt; line-height: 0pt; text-align: center; padding: 30px 20px 20px 20px;"><a style="font-size: 14px;" href=https://www.myheritage.com/partner/werelateaugcomplete2020?utm_source=external&utm_campaign=partner_werelateaugcomplete2020" target="_blank" rel="noopener"><span style="color: #000000;"><img src="https://www.myheritageimages.com/C/storage/blogs/genealogyblog/logo_1594293214.png" alt="" width="184" height="32" border="0" /></span></a></td>
</tr>
</tbody>
</table>
<!-- END Header --> <!-- Main -->
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="px-15 pt-30 pb-40 mpx-0" style="border-radius: 4px; padding: 30px 15px 40px 15px;" bgcolor="#ffffff"><!-- Section 1 / Offer -->
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="pb-40" style="padding-bottom: 40px;">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="bg" style="border-radius: 6px; background-size: 100% auto; background-repeat: no-repeat !important; background-position: left bottom !important;" valign="top" bgcolor="#fff6ef" height="260"><!-- Set the link here -->
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="tiw" style="font-size: 0pt; line-height: 0pt; text-align: left;" width="20" height="260"><span style="font-size: 14px;">&nbsp;</span></td>
<td valign="top">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="py-22" style="padding-top: 22px; padding-bottom: 22px;">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="pb-30" style="padding-bottom: 30px;">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="text-22" style="color: #333333; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 22px; line-height: 32px; text-align: left; letter-spacing: 0.3px; font-weight: normal; min-width: auto !important;"><span style="color: #000000; font-family: Verdana, Arial, Helvetica, sans-serif;"><span style="font-size: 14px; letter-spacing: normal;">Dear $name,</span></span></td>
<td class="img" style="font-size: 0pt; line-height: 0pt; text-align: left;" width="20"><span style="font-size: 14px;">&nbsp;</span></td>
<td class="img-right" style="font-size: 0pt; line-height: 0pt; text-align: right;" valign="top" width="58">
<div class="mt-40" style="margin-top: -40px; position: relative;"><span style="font-size: 14px;"><img src="https://www.myheritageimages.com/C/storage/blogs/genealogyblog/ico_top_1594293015.png" alt="" width="58" height="73" border="0" /></span></div>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
<tr>
<td class="text-18 fw-bold pb-20" style="color: #333333; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 18px; line-height: 30px; text-align: left; letter-spacing: 0.3px; min-width: auto !important; font-weight: bold; padding-bottom: 20px;"><span style="color: #000000; font-family: Verdana, Arial, Helvetica, sans-serif;"><span style="color: #000000; font-family: Verdana, Arial, Helvetica, sans-serif;"><span style="font-size: 14px; font-weight: 400; letter-spacing: normal;">We&rsquo;re excited to share an exclusive offer for&nbsp;</span></span></span><span style="color: #000000; font-family: Verdana, Arial, Helvetica, sans-serif;"><span style="font-size: 14px; font-weight: 400; letter-spacing: normal;">WeRelate users: 50% off the ultimate subscription to MyHeritage, valid through 09/08/2020.</span></span></td>
</tr>
<tr>
<td align="center"><!-- Button -->
<table border="0" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="text-btn" style="mso-padding-alt: 10px 20px; color: #ffffff; background: #f56932; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 15px; line-height: 19px; letter-spacing: 1px; border-radius: 17px; text-align: center; min-width: auto !important;"><!-- Set the fallback link here for older email clients --> <a style="text-decoration: none;" href="https://www.myheritage.com/partner/werelateaugcomplete2020?utm_source=external&utm_campaign=partner_werelateaugcomplete2020" target="_blank" rel="noopener"><span style="color: #000000; font-family: Verdana, Arial, Helvetica, sans-serif;"> <span class="link-white button-inner" style="padding: 7px 30px 6px; display: block; color: #ffffff; text-decoration: none;">Get 50% off the <span class="mob-block">MyHeritage Complete plan</span></span> </span></a></td>
</tr>
</tbody>
</table>
<!-- END Button --></td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>
</td>
<td class="tiw" style="font-size: 0pt; line-height: 0pt; text-align: left;" width="30"><span style="font-size: 14px;">&nbsp;</span></td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>
<!-- END Section 1 / Offer --> <!-- Section 2 / Text Only -->
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="px-15 pr-40 mpx-20 text-18 pb-60 mpb-40" style="padding-left: 15px; padding-right: 40px; color: #333333; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 18px; line-height: 30px; text-align: left; letter-spacing: 0.3px; min-width: auto !important; padding-bottom: 60px;"><span style="color: #000000; font-family: Verdana, Arial, Helvetica, sans-serif;"><span style="font-size: 14px; letter-spacing: normal;">MyHeritage is an industry-leading platform that makes family history research easy and offers you some of the most advanced tools on the market to make fascinating discoveries about your ancestors. They&rsquo;re constantly developing new features and adding historical records to help you break through those brick walls.</span></span></td>
</tr>
</tbody>
</table>
<!-- END Section 2 / Text Only --> <!-- Section 3 / Features  -->
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="px-40 mpx-20" style="padding-left: 40px; padding-right: 40px;">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="text-18 fw-bold a-center pb-35" style="color: #333333; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 18px; line-height: 30px; letter-spacing: 0.3px; min-width: auto !important; font-weight: bold; text-align: center; padding-bottom: 35px;"><span style="color: #000000; font-family: Verdana, Arial, Helvetica, sans-serif;"><span style="font-size: 14px; font-weight: 400; letter-spacing: normal;">The Complete plan gives you full access to all <span class="mob-hide"><br /></span>MyHeritage advanced features, including:</span></span></td>
</tr>
<!-- Row 1 -->
<tr>
<td class="pb-30" style="padding-bottom: 30px;">
<table class="box" style="border-radius: 6px; box-shadow: 0 10px 25px 0 rgba(131, 117, 107, 0.08);" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="py-15 px-15" style="padding: 15px;"><!-- END Content -->
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<th class="col-top" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal; vertical-align: top;" width="113">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="img mob-center" style="font-size: 0pt; line-height: 0pt; text-align: left;"><a class="link-image" style="text-decoration: none;" href="https://www.myheritage.com/partner/werelateaugcomplete2020?utm_source=external&utm_campaign=partner_werelateaugcomplete2020" target="_blank" rel="noopener"><span style="color: #000000;"><img src="https://www.myheritageimages.com/C/storage/blogs/genealogyblog/image_1_1594293056.png" alt="" width="113" height="108" border="0" /></span></a></td>
</tr>
</tbody>
</table>
</th>
<th class="col pb-30" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal; padding-bottom: 30px;" width="25"><span style="font-size: 14px;">&nbsp;</span></th>
<th class="col" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal;">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="text-15 c-grey mob-center" style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 15px; line-height: 28px; text-align: left; min-width: auto !important; color: #595959;"><span style="color: #000000; font-family: Verdana, Arial, Helvetica, sans-serif;"><span style="font-size: 14px;">Unlimited use of the new MyHeritage <span class="mob-hide"><br /></span>Photo Enhancer and MyHeritage In Color<sup class="sup" style="font-size: 0.6em; line-height: 1;">&trade;</sup></span></span></td>
</tr>
</tbody>
</table>
</th>
</tr>
</tbody>
</table>
<!-- END Row Content --></td>
</tr>
</tbody>
</table>
</td>
</tr>
<!-- END Row 1 --> <!-- Row 2 -->
<tr>
<td class="pb-30" style="padding-bottom: 30px;">
<table class="box" style="border-radius: 6px; box-shadow: 0 10px 25px 0 rgba(131, 117, 107, 0.08);" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="py-15" style="padding-top: 15px; padding-bottom: 15px;"><!-- END Content -->
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<th class="column pb-30" style="padding-bottom: 30px;" width="14"><span style="font-weight: 400;">&nbsp;</span></th>
<th class="col-top" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal; vertical-align: top;" width="113">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="img mob-center" style="font-size: 0pt; line-height: 0pt; text-align: left;"><a class="link-image" style="text-decoration: none;" href="https://www.myheritage.com/partner/werelateaugcomplete2020?utm_source=external&utm_campaign=partner_werelateaugcomplete2020" target="_blank" rel="noopener"><span style="color: #000000;"><img src="https://www.myheritageimages.com/C/storage/blogs/genealogyblog/image_2_1594293078.png" alt="" width="113" height="125" border="0" /></span></a></td>
</tr>
</tbody>
</table>
</th>
<th class="col pb-30" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal; padding-bottom: 30px;" width="25"><span style="font-size: 14px;">&nbsp;</span></th>
<th class="col" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal;">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="text-15 c-grey mob-center" style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 15px; line-height: 28px; text-align: left; min-width: auto !important; color: #595959;"><span style="color: #000000; font-family: Verdana, Arial, Helvetica, sans-serif;"><span style="font-size: 14px;">Instant Discoveries<sup class="sup" style="font-size: 0.6em; line-height: 1;">&trade;</sup>, which can add an <span class="mob-hide"><br /></span>entire branch to your family tree with 1 click</span></span></td>
</tr>
</tbody>
</table>
</th>
</tr>
</tbody>
</table>
<!-- END Row Content --></td>
</tr>
</tbody>
</table>
</td>
</tr>
<!-- END Row 2 --> <!-- Row 3 -->
<tr>
<td class="pb-30" style="padding-bottom: 30px;">
<table class="box" style="border-radius: 6px; box-shadow: 0 10px 25px 0 rgba(131, 117, 107, 0.08);" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="py-15" style="padding-top: 15px; padding-bottom: 15px;"><!-- END Content -->
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<th class="column pb-30" style="padding-bottom: 30px;" width="14"><span style="font-weight: 400;">&nbsp;</span></th>
<th class="col-top" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal; vertical-align: top;" width="113">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="img mob-center" style="font-size: 0pt; line-height: 0pt; text-align: left;"><a class="link-image" style="text-decoration: none;" href="https://www.myheritage.com/partner/werelateaugcomplete2020?utm_source=external&utm_campaign=partner_werelateaugcomplete2020" target="_blank" rel="noopener"><span style="color: #000000;"><img src="https://www.myheritageimages.com/C/storage/blogs/genealogyblog/image_3_1594293111.png" alt="" width="113" height="95" border="0" /></span></a></td>
</tr>
</tbody>
</table>
</th>
<th class="col pb-30" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal; padding-bottom: 30px;" width="25"><span style="font-size: 14px;">&nbsp;</span></th>
<th class="col" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal;">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="text-15 c-grey mob-center" style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 15px; line-height: 28px; text-align: left; min-width: auto !important; color: #595959;"><span style="color: #000000; font-family: Verdana, Arial, Helvetica, sans-serif;"><span style="font-size: 14px;">Consistency Checker, which automatically <span class="mob-hide"><br /></span>identifies inaccuracies in your tree</span></span></td>
</tr>
</tbody>
</table>
</th>
</tr>
</tbody>
</table>
<!-- END Row Content --></td>
</tr>
</tbody>
</table>
</td>
</tr>
<!-- END Row 3 --> <!-- Row 4 -->
<tr>
<td class="pb-30" style="padding-bottom: 30px;">
<table class="box" style="border-radius: 6px; box-shadow: 0 10px 25px 0 rgba(131, 117, 107, 0.08);" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="py-15" style="padding-top: 15px; padding-bottom: 15px;"><!-- END Content -->
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<th class="column pb-30" style="padding-bottom: 30px;" width="14"><span style="font-weight: 400;">&nbsp;</span></th>
<th class="col-top" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal; vertical-align: top;" width="113">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="img mob-center" style="font-size: 0pt; line-height: 0pt; text-align: left;"><a class="link-image" style="text-decoration: none;" href="https://www.myheritage.com/partner/werelateaugcomplete2020?utm_source=external&utm_campaign=partner_werelateaugcomplete2020" target="_blank" rel="noopener"><span style="color: #000000;"><img src="https://www.myheritageimages.com/C/storage/blogs/genealogyblog/image_4_1594293135.png" alt="" width="113" height="92" border="0" /></span></a></td>
</tr>
</tbody>
</table>
</th>
<th class="col pb-30" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal; padding-bottom: 30px;" width="25"><span style="font-size: 14px;">&nbsp;</span></th>
<th class="col" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal;">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="text-15 c-grey mob-center" style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 15px; line-height: 28px; text-align: left; min-width: auto !important; color: #595959;"><span style="color: #000000; font-family: Verdana, Arial, Helvetica, sans-serif;"><span style="font-size: 14px;">Automatic Smart Matches&trade; with millions <span class="mob-hide"><br /></span>of family trees</span></span></td>
</tr>
</tbody>
</table>
</th>
</tr>
</tbody>
</table>
<!-- END Row Content --></td>
</tr>
</tbody>
</table>
</td>
</tr>
<!-- END Row 4 --> <!-- Row 5 -->
<tr>
<td class="pb-30" style="padding-bottom: 30px;">
<table class="box" style="border-radius: 6px; box-shadow: 0 10px 25px 0 rgba(131, 117, 107, 0.08);" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="py-15" style="padding-top: 15px; padding-bottom: 15px;"><!-- END Content -->
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<th class="column pb-30" style="padding-bottom: 30px;" width="14"><span style="font-weight: 400;">&nbsp;</span></th>
<th class="col-top" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal; vertical-align: top;" width="113">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="img mob-center" style="font-size: 0pt; line-height: 0pt; text-align: left;"><a class="link-image" style="text-decoration: none;" href="https://www.myheritage.com/partner/werelateaugcomplete2020?utm_source=external&utm_campaign=partner_werelateaugcomplete2020" target="_blank" rel="noopener"><span style="color: #000000;"><img src="https://www.myheritageimages.com/C/storage/blogs/genealogyblog/image_5_1594293147.png" alt="" width="113" height="69" border="0" /></span></a></td>
</tr>
</tbody>
</table>
</th>
<th class="col pb-30" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal; padding-bottom: 30px;" width="25"><span style="font-size: 14px;">&nbsp;</span></th>
<th class="col" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal;">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="text-15 c-grey mob-center" style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 15px; line-height: 28px; text-align: left; min-width: auto !important; color: #595959;"><span style="color: #000000; font-family: Verdana, Arial, Helvetica, sans-serif;"><span style="font-size: 14px;">Automatic Record Matches for your <span class="mob-hide"><br /></span>family tree</span></span></td>
</tr>
</tbody>
</table>
</th>
</tr>
</tbody>
</table>
<!-- END Row Content --></td>
</tr>
</tbody>
</table>
</td>
</tr>
<!-- END Row 5 --> <!-- Row 6 -->
<tr>
<td class="pb-30" style="padding-bottom: 30px;">
<table class="box" style="border-radius: 6px; box-shadow: 0 10px 25px 0 rgba(131, 117, 107, 0.08);" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="py-15" style="padding-top: 15px; padding-bottom: 15px;"><!-- END Content -->
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<th class="column pb-30" style="padding-bottom: 30px;" width="14"><span style="font-weight: 400;">&nbsp;</span></th>
<th class="col-top" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal; vertical-align: top;" width="113">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="img mob-center" style="font-size: 0pt; line-height: 0pt; text-align: left;"><span style="font-size: 14px;">&lt;<a class="link-image" style="text-decoration: none;" href="https://www.myheritage.com/partner/werelateaugcomplete2020?utm_source=external&utm_campaign=partner_werelateaugcomplete2020" target="_blank" rel="noopener"><img src="https://www.myheritageimages.com/C/storage/blogs/genealogyblog/image_6_1594293161.png" alt="" width="113" height="93" border="0" /></a></span></td>
</tr>
</tbody>
</table>
</th>
<th class="col pb-30" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal; padding-bottom: 30px;" width="25"><span style="font-size: 14px;">&nbsp;</span></th>
<th class="col" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal;">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="text-15 c-grey mob-center" style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 15px; line-height: 28px; text-align: left; min-width: auto !important; color: #595959;"><span style="color: #000000; font-family: Verdana, Arial, Helvetica, sans-serif;"><span style="font-size: 14px;">12.4 billion international historical records <span class="mob-hide"><br /></span>are available for you</span></span></td>
</tr>
</tbody>
</table>
</th>
</tr>
</tbody>
</table>
<!-- END Row Content --></td>
</tr>
</tbody>
</table>
</td>
</tr>
<!-- END Row 6 --> <!-- Row 7 -->
<tr>
<td class="pb-30" style="padding-bottom: 30px;">
<table class="box" style="border-radius: 6px; box-shadow: 0 10px 25px 0 rgba(131, 117, 107, 0.08);" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="py-15" style="padding-top: 15px; padding-bottom: 15px;"><!-- END Content -->
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<th class="column pb-30" style="padding-bottom: 30px;" width="14"><span style="font-weight: 400;">&nbsp;</span></th>
<th class="col-top" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal; vertical-align: top;" width="113">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="img mob-center" style="font-size: 0pt; line-height: 0pt; text-align: left;"><a class="link-image" style="text-decoration: none;" href="https://www.myheritage.com/partner/werelateaugcomplete2020?utm_source=external&utm_campaign=partner_werelateaugcomplete2020" target="_blank" rel="noopener"><span style="color: #000000;"><img src="https://www.myheritageimages.com/C/storage/blogs/genealogyblog/image_7_1594293175.png" alt="" width="113" height="111" border="0" /></span></a></td>
</tr>
</tbody>
</table>
</th>
<th class="col pb-30" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal; padding-bottom: 30px;" width="25"><span style="font-size: 14px;">&nbsp;</span></th>
<th class="col" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal;">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="text-15 c-grey mob-center" style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 15px; line-height: 28px; text-align: left; min-width: auto !important; color: #595959;"><span style="color: #000000; font-family: Verdana, Arial, Helvetica, sans-serif;"><span style="font-size: 14px;">Unlimited family tree size</span></span></td>
</tr>
</tbody>
</table>
</th>
</tr>
</tbody>
</table>
<!-- END Row Content --></td>
</tr>
</tbody>
</table>
</td>
</tr>
<!-- END Row 7 --> <!-- Row 8 -->
<tr>
<td class="pb-30" style="padding-bottom: 30px;">
<table class="box" style="border-radius: 6px; box-shadow: 0 10px 25px 0 rgba(131, 117, 107, 0.08);" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="py-15" style="padding-top: 15px; padding-bottom: 15px;"><!-- END Content -->
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<th class="column pb-30" style="padding-bottom: 30px;" width="14"><span style="font-weight: 400;">&nbsp;</span></th>
<th class="col-top" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal; vertical-align: top;" width="113">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="img mob-center" style="font-size: 0pt; line-height: 0pt; text-align: left;"><a class="link-image" style="text-decoration: none;" href="https://www.myheritage.com/partner/werelateaugcomplete2020?utm_source=external&utm_campaign=partner_werelateaugcomplete2020" target="_blank" rel="noopener"><span style="color: #000000;"><img src="https://www.myheritageimages.com/C/storage/blogs/genealogyblog/image_8_1594293187.png" alt="" width="113" height="54" border="0" /></span></a></td>
</tr>
</tbody>
</table>
</th>
<th class="col pb-30" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal; padding-bottom: 30px;" width="25"><span style="font-size: 14px;">&nbsp;</span></th>
<th class="col" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal;">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="text-15 c-grey mob-center" style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 15px; line-height: 28px; text-align: left; min-width: auto !important; color: #595959;"><span style="color: #000000; font-family: Verdana, Arial, Helvetica, sans-serif;"><span style="font-size: 14px;">Advanced DNA features</span></span></td>
</tr>
</tbody>
</table>
</th>
</tr>
</tbody>
</table>
<!-- END Row Content --></td>
</tr>
</tbody>
</table>
</td>
</tr>
<!-- END Row 8 --> <!-- Row 9 -->
<tr>
<td class="pb-30" style="padding-bottom: 30px;">
<table class="box" style="border-radius: 6px; box-shadow: 0 10px 25px 0 rgba(131, 117, 107, 0.08);" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="py-15" style="padding-top: 15px; padding-bottom: 15px;"><!-- END Content -->
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<th class="column pb-30" style="padding-bottom: 30px;" width="14"><span style="font-weight: 400;">&nbsp;</span></th>
<th class="col-top" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal; vertical-align: top;" width="113">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="img mob-center" style="font-size: 0pt; line-height: 0pt; text-align: left;"><a class="link-image" style="text-decoration: none;" href="https://www.myheritage.com/partner/werelateaugcomplete2020?utm_source=external&utm_campaign=partner_werelateaugcomplete2020" target="_blank" rel="noopener"><span style="color: #000000;"><img src="https://www.myheritageimages.com/C/storage/blogs/genealogyblog/image_9_1594293199.png" alt="" width="113" height="108" border="0" /></span></a></td>
</tr>
</tbody>
</table>
</th>
<th class="col pb-30" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal; padding-bottom: 30px;" width="25"><span style="font-size: 14px;">&nbsp;</span></th>
<th class="col" style="font-size: 0pt; line-height: 0pt; padding: 0; margin: 0; font-weight: normal;">
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="text-15 c-grey mob-center" style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 15px; line-height: 28px; text-align: left; min-width: auto !important; color: #595959;"><span style="color: #000000; font-family: Verdana, Arial, Helvetica, sans-serif;"><span style="font-size: 14px;">Priority customer support via phone and <span class="mob-hide"><br /></span>email 24/7</span></span></td>
</tr>
</tbody>
</table>
</th>
</tr>
</tbody>
</table>
<!-- END Row Content --></td>
</tr>
</tbody>
</table>
</td>
</tr>
<!-- END Row 9 --></tbody>
</table>
</td>
</tr>
</tbody>
</table>
<!-- END Section 3 / Features --> <!-- Section 4 / Text Only Secondary -->
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="px-15 mpx-20 text-18 pb-30" style="padding-left: 15px; padding-right: 15px; color: #333333; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 18px; line-height: 30px; text-align: left; letter-spacing: 0.3px; min-width: auto !important; padding-bottom: 30px;"><span style="color: #000000; font-family: Verdana, Arial, Helvetica, sans-serif;"><span style="font-size: 14px; letter-spacing: normal;">With the MyHeritage Complete plan, you&rsquo;ll enjoy all of the tools and technologies that MyHeritage has to offer. They recently released the MyHeritage Photo Enhancer, an incredible feature that takes faces in any photo and brings them into sharp focus. In the past few months they&rsquo;ve also added millions of historical records, including several collections that are exclusive to MyHeritage.</span></span></td>
</tr>
</tbody>
</table>
<!-- END Section 4 / Text Only Secondary --> <!-- Section 5 / Image (Three Icons) -->
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="img-center pb-36" style="font-size: 0pt; line-height: 0pt; text-align: center; padding-bottom: 36px;"><span style="font-size: 14px;"><img src="https://www.myheritageimages.com/C/storage/blogs/genealogyblog/three_icons_1594293227.png" alt="" width="201" height="53" border="0" /></span></td>
</tr>
</tbody>
</table>
<!-- END Section 5 / Image (Three Icons) --> <!-- Section 6 / Text Only Tertiary -->
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="px-15 mpx-20 text-18 pb-30" style="padding-left: 15px; padding-right: 15px; color: #333333; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 18px; line-height: 30px; text-align: left; letter-spacing: 0.3px; min-width: auto !important; padding-bottom: 30px;"><span style="color: #000000; font-family: Verdana, Arial, Helvetica, sans-serif;"><span style="color: #000000; font-family: Verdana, Arial, Helvetica, sans-serif;"><span style="font-size: 14px; letter-spacing: normal;">Hurry up! For a limited time, every&nbsp;</span></span></span><span style="color: #000000; font-family: Verdana, Arial, Helvetica, sans-serif;"><span style="font-size: 14px; letter-spacing: normal;">WeRelate user can get a one-year Complete subscription for <strong>ONLY $149</strong>. Grab this deal before it&rsquo;s gone!</span></span></td>
</tr>
</tbody>
</table>
<!-- END Section 6 / Text Only Tertiary --> <!-- Section 7 / CTA -->
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="px-15 mpx-20 pb-30" style="padding-left: 15px; padding-right: 15px; padding-bottom: 30px;" align="center"><!-- Button -->
<table border="0" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="text-btn" style="mso-padding-alt: 10px 20px; color: #ffffff; background: #f56932; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 15px; line-height: 19px; letter-spacing: 1px; border-radius: 17px; text-align: center; min-width: auto !important;"><a class="link-white" style="padding: 7px 30px 6px; display: block; color: #ffffff; text-decoration: none;" href="https://www.myheritage.com/partner/werelateaugcomplete2020?utm_source=external&utm_campaign=partner_werelateaugcomplete2020" target="_blank" rel="noopener"><span class="link-white" style="color: #ffffff; text-decoration: none;"><span style="color: #000000; font-family: Verdana, Arial, Helvetica, sans-serif;">Save 50% off now</span></span></a></td>
</tr>
</tbody>
</table>
<!-- END Button --></td>
</tr>
</tbody>
</table>
<!-- END Section 7 / CTA --> <!-- Section 8 / Text Only Quaternary -->
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="px-15 mpx-20 text-18 pb-30" style="padding-left: 15px; padding-right: 15px; color: #333333; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 18px; line-height: 30px; text-align: left; letter-spacing: 0.3px; min-width: auto !important; padding-bottom: 30px;"><span style="color: #000000; font-family: Verdana, Arial, Helvetica, sans-serif;"><span style="font-size: 14px; letter-spacing: normal;">*Offer valid for NEW MyHeritage subscribers only, valid through 08/09/2020. <br /><br /><br /> Best regards, <br />WeRelate</span></span></td>
</tr>
</tbody>
</table>
<!-- END Section 8 / Text Only Quaternary --></td>
</tr>
</tbody>
</table>
<!-- END Main --> <!-- Footer -->
<table border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td class="pb-20 pt-30 px-20 img-center" style="font-size: 0pt; line-height: 0pt; text-align: center; padding: 30px 20px 20px 20px;"><!-- put your custom footer content here --></td>
</tr>
</tbody>
</table>
<!-- END Footer --></td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>
EOM;
}
?>
