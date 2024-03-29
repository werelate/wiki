<?php


#-------------------------------------------------------------------
# Default messages
#-------------------------------------------------------------------
# Allowed characters in keys are: A-Z, a-z, 0-9, underscore (_) and
# hyphen (-). If you need more characters, you may be able to change
# the regex in MagicWord::initRegex

global $wgAllMessagesEn;
$wgAllMessagesEn = array(
/*
The sidebar for MonoBook is generated from this message, lines that do not
begin with * or ** are discarded, furthermore lines that do begin with ** and
do not contain | are also discarded, but don't depend on this behaviour for
future releases. Also note that since each list value is wrapped in a unique
XHTML id it should only appear once and include characters that are legal
XHTML id names.

Note to translators: Do not include this message in the language files you
submit for inclusion in MediaWiki, it should always be inherited from the
parent class in order maintain consistency across languages.
*/
'sidebar' => '
* navigation
** mainpage|mainpage
** portal-url|portal
** currentevents-url|currentevents
** recentchanges-url|recentchanges
** randompage-url|randompage
** helppage|help
** sitesupport-url|sitesupport',

# User preference toggles
'tog-underline' => 'Underline links:',
'tog-highlightbroken' => 'Format broken links <a href="" class="new">like this</a> (alternative: like this<a href="" class="internal">?</a>).',
'tog-justify'	=> 'Justify paragraphs',
'tog-hideminor' => 'Hide minor edits in recent changes',
'tog-extendwatchlist' => 'Expand watchlist to show all applicable changes',
'tog-usenewrc' => 'Enhanced recent changes (JavaScript)',
'tog-numberheadings' => 'Auto-number headings',
'tog-showtoolbar'		=> 'Show edit toolbar (JavaScript)',
'tog-editondblclick' => 'Edit pages on double click (JavaScript)',
'tog-editsection'		=> 'Enable section editing via [edit] links',
'tog-editsectiononrightclick'	=> 'Enable section editing by right clicking<br /> on section titles (JavaScript)',
'tog-showtoc'			=> 'Show table of contents (for pages with more than 3 headings)',
'tog-rememberpassword' => 'Remember across sessions',
'tog-editwidth' => 'Edit box has full width',
'tog-watchcreations' => 'Add pages I create to my watchlist',
'tog-watchdefault' => 'Add pages I edit to my watchlist',
'tog-minordefault' => 'Mark all edits minor by default',
'tog-previewontop' => 'Show preview before edit box',
'tog-previewonfirst' => 'Show preview on first edit',
'tog-nocache' => 'Disable page caching',
'tog-enotifwatchlistpages' 	=> 'E-mail me when a page I\'m watching is changed',
'tog-enotifusertalkpages' 	=> 'E-mail me when my user talk page is changed',
'tog-enotifminoredits' 		=> 'E-mail me also for minor edits of pages',
'tog-enotifrevealaddr' 		=> 'Reveal my e-mail address in notification mails',
'tog-shownumberswatching' 	=> 'Show the number of watching users',
'tog-fancysig' => 'Raw signatures (without automatic link)',
'tog-externaleditor' => 'Use external editor by default',
'tog-externaldiff' => 'Use external diff by default',
'tog-showjumplinks' => 'Enable "jump to" accessibility links',
'tog-uselivepreview' => 'Use live preview (JavaScript) (Experimental)',
'tog-autopatrol' => 'Mark edits I make as patrolled',
'tog-forceeditsummary' => 'Prompt me when entering a blank edit summary',
'tog-watchlisthideown' => 'Hide my edits from the watchlist',
'tog-watchlisthidebots' => 'Hide bot edits from the watchlist',

'underline-always' => 'Always',
'underline-never' => 'Never',
'underline-default' => 'Browser default',

'skinpreview' => '(Preview)',

# dates
'sunday' => 'Sunday',
'monday' => 'Monday',
'tuesday' => 'Tuesday',
'wednesday' => 'Wednesday',
'thursday' => 'Thursday',
'friday' => 'Friday',
'saturday' => 'Saturday',
'january' => 'January',
'february' => 'February',
'march' => 'March',
'april' => 'April',
'may_long' => 'May',
'june' => 'June',
'july' => 'July',
'august' => 'August',
'september' => 'September',
'october' => 'October',
'november' => 'November',
'december' => 'December',
'jan' => 'Jan',
'feb' => 'Feb',
'mar' => 'Mar',
'apr' => 'Apr',
'may' => 'May',
'jun' => 'Jun',
'jul' => 'Jul',
'aug' => 'Aug',
'sep' => 'Sep',
'oct' => 'Oct',
'nov' => 'Nov',
'dec' => 'Dec',
# Bits of text used by many pages:
#
'categories' => '{{PLURAL:$1|Category|Categories}}',
'category' => 'category',
'category_header' => 'Articles in category "$1"',
'subcategories' => 'Subcategories',


'linktrail'		=> '/^([a-z]+)(.*)$/sD',
'linkprefix'		=> '/^(.*?)([a-zA-Z\x80-\xff]+)$/sD',
'mainpage'		=> 'Main Page',
'mainpagetext'	=> "<big>'''MediaWiki has been successfully installed.'''</big>",
'mainpagedocfooter' => "Consult the [http://meta.wikimedia.org/wiki/Help:Contents User's Guide] for information on using the wiki software.

== Getting started ==

* [http://www.mediawiki.org/wiki/Help:Configuration_settings Configuration settings list]
* [http://www.mediawiki.org/wiki/Help:FAQ MediaWiki FAQ]
* [http://mail.wikimedia.org/mailman/listinfo/mediawiki-announce MediaWiki release mailing list]",

'portal'		=> 'Community portal',
'portal-url'		=> 'Project:Community Portal',
'about'			=> 'About',
'aboutsite'		=> 'About {{SITENAME}}',
'aboutpage'		=> 'Project:About',
'article'		=> 'Content page',
'help'			=> 'Help',
'helppage'		=> 'Help:Contents',
'bugreports'	=> 'Bug reports',
'bugreportspage' => 'Project:Bug_reports',
'sitesupport'   => 'Donations',
'sitesupport-url' => 'Project:Site support',
'faq'			=> 'FAQ',
'faqpage'		=> 'Project:FAQ',
'edithelp'		=> 'Editing help',
'newwindow'		=> '(opens in new window)',
'edithelppage'	=> 'Help:Editing',
'cancel'		=> 'Cancel',
'qbfind'		=> 'Find',
'qbbrowse'		=> 'Browse',
'qbedit'		=> 'Edit',
'qbpageoptions' => 'This page',
'qbpageinfo'	=> 'Context',
'qbmyoptions'	=> 'My pages',
'qbspecialpages'	=> 'Special pages',
'moredotdotdot'	=> 'More...',
'mypage'		=> 'My page',
'mytalk'		=> 'My talk',
'anontalk'		=> 'Talk for this IP',
'navigation' => 'Navigation',

# Metadata in edit box
'metadata_help' => 'Metadata (see [[{{ns:project}}:Metadata]] for an explanation):',

'currentevents' => 'Current events',
'currentevents-url' => 'Current events',

'disclaimers' => 'Disclaimers',
'disclaimerpage' => 'Project:General_disclaimer',
'privacy' => 'Privacy policy',
'privacypage' => 'Project:Privacy_policy',
'errorpagetitle' => 'Error',
'returnto'		=> 'Return to $1.',
'tagline'      	=> 'From {{SITENAME}}',
'help'			=> 'Help',
'search'		=> 'Search',
'go'		=> 'Go',
'history'		=> 'Page history',
'history_short' => 'History',
'updatedmarker' => 'updated since my last visit',
'info_short'	=> 'Information',
'printableversion' => 'Printable version',
'permalink'     => 'Permanent link',
'print' => 'Print',
'edit' => 'Edit',
'editthispage'	=> 'Edit this page',
'delete' => 'Delete',
'deletethispage' => 'Delete this page',
'undelete_short' => 'Undelete {{PLURAL:$1|one edit|$1 edits}}',
'protect' => 'Protect',
'protectthispage' => 'Protect this page',
'unprotect' => 'unprotect',
'unprotectthispage' => 'Unprotect this page',
'newpage' => 'New page',
'talkpage'		=> 'Discuss this page',
'specialpage' => 'Special Page',
'personaltools' => 'Personal tools',
'postcomment'   => 'Post a comment',
'addsection'   => '+',
'articlepage'	=> 'View content page',
'subjectpage'	=> 'View subject', # For compatibility
'talk' => 'Discussion',
'views' => 'Views',
'toolbox' => 'Toolbox',
'userpage' => 'View user page',
'projectpage' => 'View project page',
'imagepage' => 	'View image page',
'viewtalkpage' => 'View discussion',
'otherlanguages' => 'In other languages',
'redirectedfrom' => '(Redirected from $1)',
'autoredircomment' => 'Redirecting to [[$1]]',
'redirectpagesub' => 'Redirect page',
'lastmodified'	=> 'This page was last modified $1.',
'viewcount'		=> 'This page has been accessed {{plural:$1|one time|$1 times}}.',
'copyright'	=> 'Content is available under $1.',
'protectedpage' => 'Protected page',
'administrators' => '{{ns:project}}:Administrators',
'jumpto' => 'Jump to:',
'jumptonavigation' => 'navigation',
'jumptosearch' => 'search',

'sysoptitle'	=> 'Sysop access required',
'sysoptext'		=> 'The action you have requested can only be
performed by users with "sysop" capability.
See $1.',
'developertitle' => 'Developer access required',
'developertext'	=> 'The action you have requested can only be
performed by users with "developer" capability.
See $1.',

'badaccess'     => 'Permission error',
'badaccesstext' => 'The action you have requested is limited
to users with the "$2" permission assigned.
See $1.',

'versionrequired' => 'Version $1 of MediaWiki required',
'versionrequiredtext' => 'Version $1 of MediaWiki is required to use this page. See [[Special:Version]]',

'widthheight'		=> '$1×$2',
'ok'			=> 'OK',
'sitetitle'		=> '{{SITENAME}}',
'pagetitle'		=> '$1 - {{SITENAME}}',
'sitesubtitle'	=> '',
'retrievedfrom' => 'Retrieved from "$1"',
'youhavenewmessages' => 'You have $1 ($2).',
'newmessageslink' => 'new messages',
'newmessagesdifflink' => 'diff to penultimate revision',
'editsection'=>'edit',
'editold'=>'edit',
'editsectionhint' => 'Edit section: $1',
'toc' => 'Contents',
'showtoc' => 'show',
'hidetoc' => 'hide',
'thisisdeleted' => 'View or restore $1?',
'viewdeleted' => 'View $1?',
'restorelink' => '{{PLURAL:$1|one deleted edit|$1 deleted edits}}',
'feedlinks' => 'Feed:',
'feed-invalid' => 'Invalid subscription feed type.',
'sitenotice'	=> '-', # the equivalent to wgSiteNotice
'anonnotice' => '-',

# Short words for each namespace, by default used in the 'article' tab in monobook
'nstab-main' => 'Article',
'nstab-user' => 'User page',
'nstab-media' => 'Media page',
'nstab-special' => 'Special',
'nstab-project' => 'Project page',
'nstab-image' => 'File',
'nstab-mediawiki' => 'Message',
'nstab-template' => 'Template',
'nstab-help' => 'Help',
'nstab-category' => 'Category',

# Main script and global functions
#
'nosuchaction'	=> 'No such action',
'nosuchactiontext' => 'The action specified by the URL is not
recognized by the wiki',
'nosuchspecialpage' => 'No such special page',
'nospecialpagetext' => 'You have requested an invalid special page, a list of valid special pages may be found at [[{{ns:special}}:Specialpages]].',

# General errors
#
'error'			=> 'Error',
'databaseerror' => 'Database error',
'dberrortext'	=> 'A database query syntax error has occurred.
This may indicate a bug in the software.
The last attempted database query was:
<blockquote><tt>$1</tt></blockquote>
from within function "<tt>$2</tt>".
MySQL returned error "<tt>$3: $4</tt>".',
'dberrortextcl' => 'A database query syntax error has occurred.
The last attempted database query was:
"$1"
from within function "$2".
MySQL returned error "$3: $4"',
'noconnect'		=> 'Sorry! The wiki is experiencing some technical difficulties, and cannot contact the database server. <br />
$1',
'nodb'			=> 'Could not select database $1',
'cachederror'	=> 'The following is a cached copy of the requested page, and may not be up to date.',
'laggedslavemode'   => 'Warning: Page may not contain recent updates.',
'readonly'			=> 'Database locked',
'enterlockreason'	=> 'Enter a reason for the lock, including an estimate
of when the lock will be released',
'readonlytext'		=> 'The database is currently locked to new entries and other modifications, probably for routine database maintenance, after which it will be back to normal.

The administrator who locked it offered this explanation: $1',
'missingarticle' => 'The database did not find the text of a page that it should have found, named "$1".

This is usually caused by following an outdated diff or history link to a
page that has been deleted.

If this is not the case, you may have found a bug in the software.
Please report this to an administrator, making note of the URL.',
'readonly_lag' => 'The database has been automatically locked while the slave database servers catch up to the master',
'internalerror' => 'Internal error',
'filecopyerror' => 'Could not copy file "$1" to "$2".',
'filerenameerror' => 'Could not rename file "$1" to "$2".',
'filedeleteerror' => 'Could not delete file "$1".',
'filenotfound'	=> 'Could not find file "$1".',
'unexpected'	=> 'Unexpected value: "$1"="$2".',
'formerror'		=> 'Error: could not submit form',
'badarticleerror' => 'This action cannot be performed on this page.',
'cannotdelete'	=> 'Could not delete the page or file specified. (It may have already been deleted by someone else.)',
'badtitle'		=> 'Bad title',
'badtitletext' => 'The requested page title was invalid, empty, or an incorrectly linked inter-language or inter-wiki title. It may contain one more characters which cannot be used in titles.',
'perfdisabled' => 'Sorry! This feature has been temporarily disabled because it slows the database down to the point that no one can use the wiki.',
'perfdisabledsub' => 'Here is a saved copy from $1:', # obsolete?
'perfcached' => 'The following data is cached and may not be up to date.',
'perfcachedts' => 'The following data is cached, and was last updated $1.',
'wrong_wfQuery_params' => 'Incorrect parameters to wfQuery()<br />
Function: $1<br />
Query: $2',
'viewsource' => 'View source',
'viewsourcefor' => 'for $1',
'protectedtext' => 'This page has been locked to prevent editing.

You can view and copy the source of this page:',
'protectedinterface' => 'This page provides interface text for the software, and is locked to prevent abuse.',
'editinginterface' => "'''Warning:''' You are editing a page which is used to provide interface text for the software. Changes to this page will affect the appearance of the user interface for other users.",
'sqlhidden' => '(SQL query hidden)',

# Login and logout pages
#
'logouttitle'	=> 'User logout',
'logouttext'	=> '<strong>You are now logged out.</strong><br />
You can continue to use {{SITENAME}} anonymously, or you can log in
again as the same or as a different user. Note that some pages may
continue to be displayed as if you were still logged in, until you clear
your browser cache.',

'welcomecreation' => "== Welcome, $1! ==

Your account has been created. Don't forget to change your {{SITENAME}} preferences.",

'loginpagetitle' => 'User login',
'yourname'		=> 'Username',
'yourpassword'	=> 'Password',
'yourpasswordagain' => 'Retype password',
'remembermypassword' => 'Remember me',
'yourdomainname'       => 'Your domain',
'externaldberror'      => 'There was either an external authentication database error or you are not allowed to update your external account.',
'loginproblem'	=> '<b>There has been a problem with your login.</b><br />Try again!',
'alreadyloggedin' => "<strong>User $1, you are already logged in!</strong><br />",

'login'			=> 'Log in',
'loginprompt'	=> 'You must have cookies enabled to log in to {{SITENAME}}.',
'userlogin'		=> 'Log in / create account',
'logout'		=> 'Log out',
'userlogout'	=> 'Log out',
'notloggedin'	=> 'Not logged in',
'nologin'	=> 'Don\'t have a login? $1.',
'nologinlink'	=> 'Create an account',
'createaccount'	=> 'Create account',
'gotaccount'	=> 'Already have an account? $1.',
'gotaccountlink'	=> 'Log in',
'createaccountmail'	=> 'by e-mail',
'badretype'		=> 'The passwords you entered do not match.',
'userexists'	=> 'Username entered already in use. Please choose a different name.',
'youremail'		=> 'E-mail *',
'username'		=> 'Username:',
'uid'			=> 'User ID:',
'yourrealname'		=> 'Real name *',
'yourlanguage'	=> 'Language:',
'yourvariant'  => 'Variant',
'yournick'		=> 'Nickname:',
'badsig'		=> 'Invalid raw signature; check HTML tags.',
'email'			=> 'E-mail',
'prefs-help-email-enotif' => 'This address is also used to send you e-mail notifications if you enabled the options.',
'prefs-help-realname' 	=> '* Real name (optional): if you choose to provide it this will be used for giving you attribution for your work.',
'loginerror'	=> 'Login error',
'prefs-help-email'      => '* E-mail (optional): Enables others to contact you through your user or user_talk page without needing to reveal your identity.',
'nocookiesnew'	=> 'The user account was created, but you are not logged in. {{SITENAME}} uses cookies to log in users. You have cookies disabled. Please enable them, then log in with your new username and password.',
'nocookieslogin'	=> '{{SITENAME}} uses cookies to log in users. You have cookies disabled. Please enable them and try again.',
'noname'		=> 'You have not specified a valid user name.',
'loginsuccesstitle' => 'Login successful',
'loginsuccess'	=> "'''You are now logged in to {{SITENAME}} as \"$1\".'''",
'nosuchuser'	=> 'There is no user by the name "$1". Check your spelling, or create a new account.',
'nosuchusershort'	=> 'There is no user by the name "$1". Check your spelling.',
'nouserspecified'	=> 'You have to specify a username.',
'wrongpassword'		=> 'Incorrect password entered. Please try again.',
'wrongpasswordempty'		=> 'Password entered was blank. Please try again.',
'mailmypassword' 	=> 'E-mail password',
'passwordremindertitle' => 'Password reminder from {{SITENAME}}',
'passwordremindertext' => 'Someone (probably you, from IP address $1)
requested that we send you a new password for {{SITENAME}} ($4).
The password for user "$2" is now "$3".
You should log in and change your password now.

If someone else made this request or if you have remembered your password and
you no longer wish to change it, you may ignore this message and continue using
your old password.',
'noemail' => 'There is no e-mail address recorded for user "$1".',
'passwordsent'	=> 'A new password has been sent to the e-mail address
registered for "$1".
Please log in again after you receive it.',
'eauthentsent' =>  'A confirmation e-mail has been sent to the nominated e-mail address.
Before any other mail is sent to the account, you will have to follow the instructions in the e-mail,
to confirm that the account is actually yours.',
'loginend'		            => '',
'signupend'		            => '{{int:loginend}}',
'mailerror'                 => 'Error sending mail: $1',
'acct_creation_throttle_hit' => 'Sorry, you have already created $1 accounts. You can\'t make any more.',
'emailauthenticated'        => 'Your e-mail address was authenticated on $1.',
'emailnotauthenticated'     => 'Your e-mail address is <strong>not yet authenticated</strong>. No e-mail
will be sent for any of the following features.',
'noemailprefs'              => 'Specify an e-mail address for these features to work.',
'emailconfirmlink' => 'Confirm your e-mail address',
'invalidemailaddress'	=> 'The e-mail address cannot be accepted as it appears to have an invalid
format. Please enter a well-formatted address or empty that field.',
'accountcreated' => 'Account created',
'accountcreatedtext' => 'The user account for $1 has been created.',

# Edit page toolbar
'bold_sample'=>'Bold text',
'bold_tip'=>'Bold text',
'italic_sample'=>'Italic text',
'italic_tip'=>'Italic text',
'link_sample'=>'Link title',
'link_tip'=>'Internal link',
'extlink_sample'=>'http://www.example.com link title',
'extlink_tip'=>'External link (remember http:// prefix)',
'headline_sample'=>'Headline text',
'headline_tip'=>'Level 2 headline',
'math_sample'=>'Insert formula here',
'math_tip'=>'Mathematical formula (LaTeX)',
'nowiki_sample'=>'Insert non-formatted text here',
'nowiki_tip'=>'Ignore wiki formatting',
'image_sample'=>'Example.jpg',
'image_tip'=>'Embedded image',
'media_sample'=>'Example.ogg',
'media_tip'=>'Media file link',
'sig_tip'=>'Your signature with timestamp',
'hr_tip'=>'Horizontal line (use sparingly)',

# Edit pages
#
'summary'		=> 'Summary',
'subject'		=> 'Subject/headline',
'minoredit'		=> 'This is a minor edit',
'watchthis'		=> 'Watch this page',
'savearticle'	=> 'Save page',
'preview'		=> 'Preview',
'showpreview'	=> 'Show preview',
'showlivepreview'	=> 'Live preview',
'showdiff'	=> 'Show changes',
'anoneditwarning' => "'''Warning:''' You are not logged in. Your IP address will be recorded in this page's edit history.",
'missingsummary' => "'''Reminder:''' You have not provided an edit summary. If you click Save again, your edit will be saved without one.",
'missingcommenttext' => 'Please enter a comment below.',
'blockedtitle'	=> 'User is blocked',
'blockedtext'	=> 'Your user name or IP address has been blocked by $1.
The reason given is this:<br />\'\'$2\'\'<br />You may contact $1 or one of the other
[[{{ns:project}}:Administrators|administrators]] to discuss the block.

Note that you may not use the "e-mail this user" feature unless you have a valid e-mail address registered in your [[Special:Preferences|user preferences]].

Your IP address is $3. Please include this address in any queries you make.',
'blockedoriginalsource' => "The source of '''$1''' is shown below:",
'blockededitsource' => "The text of '''your edits''' to '''$1''' is shown below:",
'whitelistedittitle' => 'Login required to edit',
'whitelistedittext' => 'You have to $1 to edit pages.',
'whitelistreadtitle' => 'Login required to read',
'whitelistreadtext' => 'You have to [[Special:Userlogin|login]] to read pages.',
'whitelistacctitle' => 'You are not allowed to create an account',
'whitelistacctext' => 'To be allowed to create accounts in this Wiki you have to [[Special:Userlogin|log]] in and have the appropriate permissions.',
'confirmedittitle' => 'E-mail confirmation required to edit',
'confirmedittext' => 'You must confirm your e-mail address before editing pages. Please set and validate your e-mail address through your [[Special:Preferences|user preferences]].',
'loginreqtitle'	=> 'Login Required',
'loginreqlink' => 'log in',
'loginreqpagetext'	=> 'You must $1 to view other pages.',
'accmailtitle' => 'Password sent.',
'accmailtext' => 'The password for "$1" has been sent to $2.',
'newarticle'	=> '(New)',
'newarticletext' =>
"You've followed a link to a page that doesn't exist yet.
To create the page, start typing in the box below
(see the [[{{ns:help}}:Contents|help page]] for more info).
If you are here by mistake, just click your browser's '''back''' button.",
'newarticletextanon' => '{{int:newarticletext}}',
'talkpagetext' => '<!-- MediaWiki:talkpagetext -->',
'anontalkpagetext' => "----''This is the discussion page for an anonymous user who has not created an account yet or who does not use it. We therefore have to use the numerical IP address to identify him/her. Such an IP address can be shared by several users. If you are an anonymous user and feel that irrelevant comments have been directed at you, please [[Special:Userlogin|create an account or log in]] to avoid future confusion with other anonymous users.''",
'noarticletext' => 'There is currently no text in this page, you can [[{{ns:special}}:Search/{{PAGENAME}}|search for this page title]] in other pages or [{{fullurl:{{FULLPAGENAME}}|action=edit}} edit this page].',
'noarticletextanon' => '{{int:noarticletext}}',
'clearyourcache' => "'''Note:''' After saving, you may have to bypass your browser's cache to see the changes. '''Mozilla / Firefox / Safari:''' hold down ''Shift'' while clicking ''Reload'', or press ''Ctrl-Shift-R'' (''Cmd-Shift-R'' on Apple Mac); '''IE:''' hold ''Ctrl'' while clicking ''Refresh'', or press ''Ctrl-F5''; '''Konqueror:''': simply click the ''Reload'' button, or press ''F5''; '''Opera''' users may need to completely clear their cache in ''Tools→Preferences''.",
'usercssjsyoucanpreview' => '<strong>Tip:</strong> Use the \'Show preview\' button to test your new CSS/JS before saving.',
'usercsspreview' => '\'\'\'Remember that you are only previewing your user CSS, it has not yet been saved!\'\'\'',
'userjspreview' => '\'\'\'Remember that you are only testing/previewing your user JavaScript, it has not yet been saved!\'\'\'',
'userinvalidcssjstitle' => "'''Warning:''' There is no skin \"$1\". Remember that custom .css and .js pages use a lowercase title, e.g. User:Foo/monobook.css as opposed to User:Foo/Monobook.css.",
'updated' => '(Updated)',
'note' => '<strong>Note:</strong>',
'previewnote' => '<strong>This is only a preview; changes have not yet been saved!</strong>',
'session_fail_preview' => '<strong>Sorry! We could not process your edit due to a loss of session data.
Please try again. If it still doesn\'t work, try logging out and logging back in.</strong>',
'previewconflict' => 'This preview reflects the text in the upper text editing area as it will appear if you choose to save.',
'session_fail_preview_html' => '<strong>Sorry! We could not process your edit due to a loss of session data.</strong>

\'\'Because this wiki has raw HTML enabled, the preview is hidden as a precaution against JavaScript attacks.\'\'

<strong>If this is a legitimate edit attempt, please try again. If it still doesn\'t work, try logging out and logging back in.</strong>',
'importing' => 'Importing $1',
'editing' => 'Editing $1',
'editingsection' => 'Editing $1 (section)',
'editingcomment' => 'Editing $1 (comment)',
'editconflict' => 'Edit conflict: $1',
'explainconflict' => 'Someone else has changed this page since you started editing it.
The upper text area contains the page text as it currently exists.
Your changes are shown in the lower text area.
You will have to merge your changes into the existing text.
<b>Only</b> the text in the upper text area will be saved when you
press "Save page".<br />',
'yourtext'		=> 'Your text',
'storedversion' => 'Stored version',
'nonunicodebrowser' => "<strong>WARNING: Your browser is not unicode compliant. A workaround is in place to allow you to safely edit articles: non-ASCII characters will appear in the edit box as hexadecimal codes.</strong>",
'editingold'	=> "<strong>WARNING: You are editing an out-of-date
revision of this page.
If you save it, any changes made since this revision will be lost.</strong>",
'yourdiff'		=> 'Differences',
'copyrightwarning' => 'Please note that all contributions to {{SITENAME}} are considered to be released under the $2 (see $1 for details). If you don\'t want your writing to be edited mercilessly and redistributed at will, then don\'t submit it here.<br />
You are also promising us that you wrote this yourself, or copied it from a public domain or similar free resource.
<strong>DO NOT SUBMIT COPYRIGHTED WORK WITHOUT PERMISSION!</strong>',
'copyrightwarning2' => 'Please note that all contributions to {{SITENAME}} may be edited, altered, or removed by other contributors. If you don\'t want your writing to be edited mercilessly, then don\'t submit it here.<br />
You are also promising us that you wrote this yourself, or copied it from a
public domain or similar free resource (see $1 for details).
<strong>DO NOT SUBMIT COPYRIGHTED WORK WITHOUT PERMISSION!</strong>',
'longpagewarning' => "<strong>WARNING: This page is $1 kilobytes long; some
browsers may have problems editing pages approaching or longer than 32kb.
Please consider breaking the page into smaller sections.</strong>",
'longpageerror' => "<strong>ERROR: The text you have submitted is $1 kilobytes 
long, which is longer than the maximum of $2 kilobytes. It cannot be saved.</strong>",
'readonlywarning' => '<strong>WARNING: The database has been locked for maintenance,
so you will not be able to save your edits right now. You may wish to cut-n-paste
the text into a text file and save it for later.</strong>',
'protectedpagewarning' => "<strong>WARNING:  This page has been locked so that only users with sysop privileges can edit it.</strong>",
'semiprotectedpagewarning' => "'''Note:''' This page has been locked so that only registered users can edit it.",
'templatesused'	=> 'Templates used on this page:',
'edittools' => '<!-- Text here will be shown below edit and upload forms. -->',
'nocreatetitle' => 'Page creation limited',
'nocreatetext' => 'This site has restricted the ability to create new pages.
You can go back and edit an existing page, or [[Special:Userlogin|log in or create an account]].',

# History pages
#
'revhistory'	=> 'Revision history',
'viewpagelogs' => 'View logs for this page',
'nohistory'		=> 'There is no edit history for this page.',
'revnotfound'	=> 'Revision not found',
'revnotfoundtext' => "The old revision of the page you asked for could not be found.
Please check the URL you used to access this page.",
'loadhist'		=> 'Loading page history',
'currentrev'	=> 'Current revision',
'revisionasof'          => 'Revision as of $1',
'old-revision-navigation' => 'Revision as of $1; $5<br />($6) $3 | $2 | $4 ($7)',
'previousrevision'	=> '←Older revision',
'nextrevision'		=> 'Newer revision→',
'currentrevisionlink'   => 'Current revision',
'cur'			=> 'cur',
'next'			=> 'next',
'last'			=> 'last',
'orig'			=> 'orig',
'histlegend'	=> 'Diff selection: mark the radio boxes of the versions to compare and hit enter or the button at the bottom.<br />
Legend: (cur) = difference with current version,
(last) = difference with preceding version, M = minor edit.',
'history_copyright'    => '-',
'deletedrev' => '[deleted]',
'histfirst' => 'Earliest',
'histlast' => 'Latest',
'rev-deleted-comment' => '(comment removed)',
'rev-deleted-user' => '(username removed)',
'rev-deleted-text-permission' => '<div class="mw-warning plainlinks">
This page revision has been removed from the public archives.
There may be details in the [{{fullurl:Special:Log/delete|page={{PAGENAMEE}}}} deletion log].
</div>',
'rev-deleted-text-view' => '<div class="mw-warning plainlinks">
This page revision has been removed from the public archives.
As an administrator on this site you can view it;
there may be details in the [{{fullurl:Special:Log/delete|page={{PAGENAMEE}}}} deletion log].
</div>',
#'rev-delundel' => 'del/undel',
'rev-delundel' => 'show/hide',

'history-feed-title' => 'Revision history',
'history-feed-description'	=> 'Revision history for this page on the wiki',
'history-feed-item-nocomment' => '$1 at $2', # user at time
'history-feed-empty' => 'The requested page doesn\'t exist.
It may have been deleted from the wiki, or renamed.
Try [[Special:Search|searching on the wiki]] for relevant new pages.',

# Revision deletion
#
'revisiondelete' => 'Delete/undelete revisions',
'revdelete-selected' => 'Selected revision of [[:$1]]:',
'revdelete-text' => "Deleted revisions will still appear in the page history,
but their text contents will be inaccessible to the public.

Other admins on this wiki will still be able to access the hidden content and can
undelete it again through this same interface, unless an additional restriction
is placed by the site operators.",
'revdelete-legend' => 'Set revision restrictions:',
'revdelete-hide-text' => 'Hide revision text',
'revdelete-hide-comment' => 'Hide edit comment',
'revdelete-hide-user' => 'Hide editor\'s username/IP',
'revdelete-hide-restricted' => 'Apply these restrictions to sysops as well as others',
'revdelete-log' => 'Log comment:',
'revdelete-submit' => 'Apply to selected revision',
'revdelete-logentry' => 'changed revision visibility for [[$1]]',

# Diffs
#
'difference'	=> '(Difference between revisions)',
'loadingrev'	=> 'loading revision for diff',
'lineno'		=> "Line $1:",
'editcurrent'	=> 'Edit the current version of this page',
'selectnewerversionfordiff' => 'Select a newer version for comparison',
'selectolderversionfordiff' => 'Select an older version for comparison',
'compareselectedversions' => 'Compare selected versions',

# Search results
#
'searchresults' => 'Search results',
'searchresulttext' => "For more information about searching {{SITENAME}}, see [[{{ns:project}}:Searching|Searching {{SITENAME}}]].",
'searchsubtitle' => "You searched for '''[[:$1]]'''",
'searchsubtitleinvalid' => "You searched for '''$1'''",
'badquery'		=> 'Badly formed search query',
'badquerytext'	=> 'We could not process your query.
This is probably because you have attempted to search for a
word fewer than three letters long, which is not yet supported.
It could also be that you have mistyped the expression, for
example "fish and and scales".
Please try another query.',
'matchtotals'	=> "The query \"$1\" matched $2 page titles
and the text of $3 pages.",
'noexactmatch' => "'''There is no page titled \"$1\".''' You can [[:$1|create this page]].",
'titlematches'	=> 'Article title matches',
'notitlematches' => 'No page title matches',
'textmatches'	=> 'Page text matches',
'notextmatches'	=> 'No page text matches',
'prevn'			=> "previous $1",
'nextn'			=> "next $1",
'viewprevnext'	=> "View ($1) ($2) ($3).",
'showingresults' => "Showing below up to <b>$1</b> results starting with #<b>$2</b>.",
'showingresultsnum' => "Showing below <b>$3</b> results starting with #<b>$2</b>.",
'nonefound'		=> "'''Note''': Unsuccessful searches are
often caused by searching for common words like \"have\" and \"from\",
which are not indexed, or by specifying more than one search term (only pages
containing all of the search terms will appear in the result).",
'powersearch' => 'Search',
'powersearchtext' => "Search in namespaces:<br />$1<br />$2 List redirects<br />Search for $3 $9",
'searchdisabled' => '{{SITENAME}} search is disabled. You can search via Google in the meantime. Note that their indexes of {{SITENAME}} content may be out of date.',

'googlesearch' => '
<form method="get" action="http://www.google.com/search" id="googlesearch">
    <input type="hidden" name="domains" value="{{SERVER}}" />
    <input type="hidden" name="num" value="50" />
    <input type="hidden" name="ie" value="$2" />
    <input type="hidden" name="oe" value="$2" />

    <input type="text" name="q" size="31" maxlength="255" value="$1" />
    <input type="submit" name="btnG" value="$3" />
  <div>
    <input type="radio" name="sitesearch" id="gwiki" value="{{SERVER}}" checked="checked" /><label for="gwiki">{{SITENAME}}</label>
    <input type="radio" name="sitesearch" id="gWWW" value="" /><label for="gWWW">WWW</label>
  </div>
</form>',
'blanknamespace' => '(Main)',

# Preferences page
#
'preferences'	=> 'Preferences',
'prefsnologin' => 'Not logged in',
'prefsnologintext'	=> "You must be [[Special:Userlogin|logged in]] to set user preferences.",
'prefsreset'	=> 'Preferences have been reset from storage.',
'qbsettings'	=> 'Quickbar',
'changepassword' => 'Change password',
'skin'			=> 'Skin',
'math'			=> 'Math',
'dateformat'		=> 'Date format',
'datedefault'		=> 'No preference',
'datetime'		=> 'Date and time',
'math_failure'		=> 'Failed to parse',
'math_unknown_error'	=> 'unknown error',
'math_unknown_function'	=> 'unknown function',
'math_lexing_error'	=> 'lexing error',
'math_syntax_error'	=> 'syntax error',
'math_image_error'	=> 'PNG conversion failed; check for correct installation of latex, dvips, gs, and convert',
'math_bad_tmpdir'	=> 'Can\'t write to or create math temp directory',
'math_bad_output'	=> 'Can\'t write to or create math output directory',
'math_notexvc'	=> 'Missing texvc executable; please see math/README to configure.',
'prefs-personal' => 'User profile',
'prefs-rc' => 'Recent changes',
'prefs-watchlist' => 'Watchlist',
'prefs-watchlist-days' => 'Number of days to show in watchlist:',
'prefs-watchlist-edits' => 'Number of edits to show in expanded watchlist:',
'prefs-misc' => 'Misc',
'saveprefs'		=> 'Save',
'resetprefs'	=> 'Reset',
'oldpassword'	=> 'Old password:',
'newpassword'	=> 'New password:',
'retypenew'		=> 'Retype new password:',
'textboxsize'	=> 'Editing',
'rows'			=> 'Rows:',
'columns'		=> 'Columns:',
'searchresultshead' => 'Search',
'resultsperpage' => 'Hits per page:',
'contextlines'	=> 'Lines per hit:',
'contextchars'	=> 'Context per line:',
'stubthreshold' => 'Threshold for stub display:',
'recentchangescount' => 'Titles in recent changes:',
'savedprefs'	=> 'Your preferences have been saved.',
'timezonelegend' => 'Time zone',
'timezonetext'	=> 'The number of hours your local time differs from server time (UTC).',
'localtime'	=> 'Local time',
'timezoneoffset' => 'Offset¹',
'servertime'	=> 'Server time',
'guesstimezone' => 'Fill in from browser',
'allowemail'		=> 'Enable e-mail from other users',
'defaultns'		=> 'Search in these namespaces by default:',
'default'		=> 'default',
'files'			=> 'Files',

# User rights
'userrights-lookup-user' => 'Manage user groups',
'userrights-user-editname' => 'Enter a username:',
'editusergroup' => 'Edit User Groups',

'userrights-editusergroup' => 'Edit user groups',
'saveusergroups' => 'Save User Groups',
'userrights-groupsmember' => 'Member of:',
'userrights-groupsavailable' => 'Available groups:',
'userrights-groupshelp' => 'Select groups you want the user to be removed from or added to.
Unselected groups will not be changed. You can deselect a group with CTRL + Left Click',
'userrights-logcomment' => 'Changed group membership from $1 to $2',

# Groups
'group'                   => 'Group:',
'group-bot'               => 'Bots',
'group-sysop'             => 'Sysops',
'group-bureaucrat'        => 'Bureaucrats',
'group-steward'           => 'Stewards',
'group-all'               => '(all)',

'group-bot-member'        => 'Bot',
'group-sysop-member'      => 'Sysop',
'group-bureaucrat-member' => 'Bureaucrat',
'group-steward-member'    => 'Steward',

'grouppage-bot' => '{{ns:project}}:Bots',
'grouppage-sysop' => '{{ns:project}}:Administrators',
'grouppage-bureaucrat' => '{{ns:project}}:Bureaucrats',

# Recent changes
#
'changes' => 'changes',
'recentchanges' => 'Recent changes',
'recentchanges-url' => 'Special:Recentchanges',
'recentchangestext' => 'Track the most recent changes to the wiki on this page.',
'rcnote'		=> "Below are the last <strong>$1</strong> changes in the last <strong>$2</strong> days, as of $3.",
'rcnotefrom'	=> "Below are the changes since <b>$2</b> (up to <b>$1</b> shown).",
'rclistfrom'	=> "Show new changes starting from $1",
'rcshowhideminor' => '$1 minor edits',
'rcshowhidebots' => '$1 bots',
'rcshowhideliu' => '$1 logged-in users',
'rcshowhideanons' => '$1 anonymous users',
'rcshowhidepatr' => '$1 patrolled edits',
'rcshowhidemine' => '$1 my edits',
'rclinks'		=> "Show last $1 changes in last $2 days<br />$3",
'diff'			=> 'diff',
'hist'			=> 'hist',
'hide'			=> 'Hide',
'show'			=> 'Show',
'minoreditletter' => 'm',
'newpageletter' => 'N',
'boteditletter' => 'b',
'sectionlink' => '→',
'number_of_watching_users_RCview' 	=> '[$1]',
'number_of_watching_users_pageview' 	=> '[$1 watching user/s]',
'rc_categories'	=> 'Limit to categories (separate with "|")',
'rc_categories_any'	=> 'Any',

# Upload
#
'upload'		=> 'Upload file',
'uploadbtn'		=> 'Upload file',
'reupload'		=> 'Re-upload',
'reuploaddesc'	=> 'Return to the upload form.',
'uploadnologin' => 'Not logged in',
'uploadnologintext'	=> "You must be [[Special:Userlogin|logged in]]
to upload files.",
'upload_directory_read_only' => 'The upload directory ($1) is not writable by the webserver.',
'uploaderror'	=> 'Upload error',
'uploadtext'	=> "Use the form below to upload files, to view or search previously uploaded images go to the [[Special:Imagelist|list of uploaded files]], uploads and deletions are also logged in the [[Special:Log/upload|upload log]].

To include the image in a page, use a link in the form
'''<nowiki>[[{{ns:image}}:File.jpg]]</nowiki>''',
'''<nowiki>[[{{ns:image}}:File.png|alt text]]</nowiki>''' or
'''<nowiki>[[{{ns:media}}:File.ogg]]</nowiki>''' for directly linking to the file.",
'uploadlog'		=> 'upload log',
'uploadlogpage' => 'Upload log',
'uploadlogpagetext' => 'Below is a list of the most recent file uploads.',
'filename'		=> 'Filename',
'filedesc'		=> 'Summary',
'fileuploadsummary' => 'Summary:',
'filestatus' => 'Copyright status',
'filesource' => 'Source',
'copyrightpage' => "Project:Copyrights",
'copyrightpagename' => "{{SITENAME}} copyright",
'uploadedfiles'	=> 'Uploaded files',
'ignorewarning'        => 'Ignore warning and save file anyway.',
'ignorewarnings'	=> 'Ignore any warnings',
'minlength'		=> 'File names must be at least three letters.',
'illegalfilename'	=> 'The filename "$1" contains characters that are not allowed in page titles. Please rename the file and try uploading it again.',
'badfilename'	=> 'File name has been changed to "$1".',
'badfiletype'	=> "\".$1\" is not a recommended image file format.",
'largefile'		=> 'It is recommended that files do not exceed $1 bytes in size; this file is $2 bytes',
'largefileserver' => 'This file is bigger than the server is configured to allow.',
'emptyfile'		=> 'The file you uploaded seems to be empty. This might be due to a typo in the file name. Please check whether you really want to upload this file.',
'fileexists'		=> 'A file with this name exists already, please check $1 if you are not sure if you want to change it.',
'fileexists-forbidden' => 'A file with this name exists already; please go back and upload this file under a new name. [[Image:$1|thumb|center|$1]]',
'fileexists-shared-forbidden' => 'A file with this name exists already in the shared file repository; please go back and upload this file under a new name. [[Image:$1|thumb|center|$1]]',
'successfulupload' => 'Successful upload',
'fileuploaded'	=> "File $1 uploaded successfully.
Please follow this link: $2 to the description page and fill
in information about the file, such as where it came from, when it was
created and by whom, and anything else you may know about it. If this is an image, you can insert it like this: <tt><nowiki>[[Image:$1|thumb|Description]]</nowiki></tt>",
'uploadwarning' => 'Upload warning',
'savefile'		=> 'Save file',
'uploadedimage' => "uploaded \"[[$1]]\"",
'uploaddisabled' => 'Uploads disabled',
'uploaddisabledtext' => 'File uploads are disabled on this wiki.',
'uploadscripted' => 'This file contains HTML or script code that may be erroneously be interpreted by a web browser.',
'uploadcorrupt' => 'The file is corrupt or has an incorrect extension. Please check the file and upload again.',
'uploadvirus' => 'The file contains a virus! Details: $1',
'sourcefilename' => 'Source filename',
'destfilename' => 'Destination filename',
'filewasdeleted' => 'A file of this name has been previously uploaded and subsequently deleted. You should check the $1 before proceeding to upload it again.',

'license' => 'Licensing',
'nolicense' => 'None selected',
'licenses' => '-', # Don't duplicate this in translations

# Image list
#
'imagelist'		=> 'File list',
'imagelisttext' => "Below is a list of '''$1''' {{plural:$1|file|files}} sorted $2.",
'imagelistforuser' => "This shows only images uploaded by $1.",
'getimagelist'	=> 'fetching file list',
'ilsubmit'		=> 'Search',
'showlast'		=> 'Show last $1 files sorted $2.',
'byname'		=> 'by name',
'bydate'		=> 'by date',
'bysize'		=> 'by size',
'imgdelete'		=> 'del',
'imgdesc'		=> 'desc',
'imglegend'		=> 'Legend: (desc) = show/edit file description.',
'imghistory'	=> 'File history',
'revertimg'		=> 'rev',
'deleteimg'		=> 'del',
'deleteimgcompletely'		=> 'Delete all revisions of this file',
'imghistlegend' => 'Legend: (cur) = this is the current file, (del) = delete
this old version, (rev) = revert to this old version.
<br /><i>Click on date to see the file uploaded on that date</i>.',
'imagelinks'	=> 'Links',
'linkstoimage'	=> 'The following pages link to this file:',
'nolinkstoimage' => 'There are no pages that link to this file.',
'sharedupload' => 'This file is a shared upload and may be used by other projects.',
'shareduploadwiki' => 'Please see the $1 for further information.',
'shareduploadwiki-linktext' => 'file description page',
'shareddescriptionfollows' => '-',
'noimage'       => 'No file by this name exists, you can $1.',
'noimage-linktext'       => 'upload it',
'uploadnewversion-linktext' => 'Upload a new version of this file',

# Mime search
#
'mimesearch' => 'MIME search',
'mimetype' => 'MIME type:',
'download' => 'download',

# Unwatchedpages
#
'unwatchedpages' => 'Unwatched pages',

# List redirects
'listredirects' => 'List redirects',

# Unused templates
'unusedtemplates' => 'Unused templates',
'unusedtemplatestext' => 'This page lists all pages in the template namespace which are not included in another page. Remember to check for other links to the templates before deleting them.',
'unusedtemplateswlh' => 'other links',

# Random redirect
'randomredirect' => 'Random redirect',

# Statistics
#
'statistics'	=> 'Statistics',
'sitestats'		=> '{{SITENAME}} statistics',
'userstats'		=> 'User statistics',
'sitestatstext' => "There are '''$1''' total pages in the database.
This includes \"talk\" pages, pages about {{SITENAME}}, minimal \"stub\"
pages, redirects, and others that probably don't qualify as content pages.
Excluding those, there are '''$2''' pages that are probably legitimate
content pages. 

'''$8''' files have been uploaded.

There have been a total of '''$3''' page views, and '''$4''' page edits
since the wiki was setup.
That comes to '''$5''' average edits per page, and '''$6''' views per edit.

The [http://meta.wikimedia.org/wiki/Help:Job_queue job queue] length is '''$7'''.",
'userstatstext' => "There are '''$1''' registered users, of which
'''$2''' (or '''$4%''') are administrators (see $3).",

'disambiguations'	=> 'Disambiguation pages',
'disambiguationspage'	=> 'Template:disambig',
'disambiguationstext'	=> "The following pages link to a <i>disambiguation page</i>. They should link to the appropriate topic instead.<br />A page is treated as disambiguation if it is linked from $1.<br />Links from other namespaces are <i>not</i> listed here.",

'doubleredirects'	=> 'Double redirects',
'doubleredirectstext'	=> "Each row contains links to the first and second redirect, as well as the first line of the second redirect text, usually giving the \"real\" target page, which the first redirect should point to.",

'brokenredirects'	=> 'Broken redirects',
'brokenredirectstext'	=> 'The following redirects link to non-existent pages:',


# Miscellaneous special pages
#
'nbytes'		=> '$1 {{PLURAL:$1|byte|bytes}}',
'ncategories'		=> '$1 {{PLURAL:$1|category|categories}}',
'nlinks'		=> '$1 {{PLURAL:$1|link|links}}',
'nmembers'		=> '$1 {{PLURAL:$1|member|members}}',
'nrevisions'		=> '$1 {{PLURAL:$1|revision|revisions}}',
'nviews'		=> '$1 {{PLURAL:$1|view|views}}',

'lonelypages'	=> 'Orphaned pages',
'uncategorizedpages'	=> 'Uncategorized pages',
'uncategorizedcategories'	=> 'Uncategorized categories',
'uncategorizedimages' => 'Uncategorized images',
'unusedcategories' => 'Unused categories',
'unusedimages'	=> 'Unused files',
'popularpages'	=> 'Popular pages',
'wantedcategories' => 'Wanted categories',
'wantedpages'	=> 'Wanted pages',
'mostlinked'	=> 'Most linked to pages',
'mostlinkedcategories' => 'Most linked to categories',
'mostcategories' => 'Articles with the most categories',
'mostimages'	=> 'Most linked to images',
'mostrevisions' => 'Articles with the most revisions',
'allpages'		=> 'All pages',
'prefixindex'   => 'Prefix index',
'randompage'	=> 'Random page',
'randompage-url'=> 'Special:Random',
'shortpages'	=> 'Short pages',
'longpages'		=> 'Long pages',
'deadendpages'  => 'Dead-end pages',
'listusers'		=> 'User list',
'specialpages'	=> 'Special pages',
'spheading'		=> 'Special pages for all users',
'restrictedpheading'	=> 'Restricted special pages',
'recentchangeslinked' => 'Related changes',
'rclsub'		=> "(to pages linked from \"$1\")",
'newpages'		=> 'New pages',
'ancientpages'		=> 'Oldest pages',
'intl'		=> 'Interlanguage links',
'move' => 'Move',
'movethispage'	=> 'Move this page',
'unusedimagestext' => '<p>Please note that other web sites may link to an image with
a direct URL, and so may still be listed here despite being
in active use.</p>',
'unusedcategoriestext' => 'The following category pages exist although no other article or category make use of them.',

'booksources'	=> 'Book sources',
'categoriespagetext' => 'The following categories exist in the wiki.',
'data'	=> 'Data',
'userrights' => 'User rights management',
'groups' => 'User groups',

'booksourcetext' => "Below is a list of links to other sites that
sell new and used books, and may also have further information
about books you are looking for.",
'isbn'	=> 'ISBN',
'rfcurl' =>  'http://www.ietf.org/rfc/rfc$1.txt',
'pubmedurl' =>  'http://www.ncbi.nlm.nih.gov/entrez/query.fcgi?cmd=Retrieve&db=pubmed&dopt=Abstract&list_uids=$1',
'alphaindexline' => "$1 to $2",
'version'		=> 'Version',
'log'		=> 'Logs',
'alllogstext'	=> 'Combined display of upload, deletion, protection, blocking, and sysop logs.
You can narrow down the view by selecting a log type, the user name, or the affected page.',
'logempty' => 'No matching items in log.',


# Special:Allpages
'nextpage'          => 'Next page ($1)',
'allpagesfrom'		=> 'Display pages starting at:',
'allarticles'		=> 'All articles',
'allnonarticles'	=> 'All non-articles',
'allinnamespace'	=> 'All pages ($1 namespace)',
'allnotinnamespace'	=> 'All pages (not in $1 namespace)',
'allpagesprev'		=> 'Previous',
'allpagesnext'		=> 'Next',
'allpagessubmit'	=> 'Go',
'allpagesprefix'	=> 'Display pages with prefix:',
'allpagesbadtitle'	=> 'The given page title was invalid or had an inter-language or inter-wiki prefix. It may contain one more characters which cannot be used in titles.',

# E this user
#
'mailnologin'	=> 'No send address',
'mailnologintext' => "You must be [[Special:Userlogin|logged in]]
and have a valid e-mail address in your [[Special:Preferences|preferences]]
to send e-mail to other users.",
'emailuser'		=> 'E-mail this user',
'emailpage'		=> 'E-mail user',
'emailpagetext'	=> 'If this user has entered a valid e-mail address in
his or her user preferences, the form below will send a single message.
The e-mail address you entered in your user preferences will appear
as the "From" address of the mail, so the recipient will be able
to reply.',
'usermailererror' => 'Mail object returned error:',
'defemailsubject'  => "{{SITENAME}} e-mail",
'noemailtitle'	=> 'No e-mail address',
'noemailtext'	=> 'This user has not specified a valid e-mail address,
or has chosen not to receive e-mail from other users.',
'emailfrom'		=> 'From',
'emailto'		=> 'To',
'emailsubject'	=> 'Subject',
'emailmessage'	=> 'Message',
'emailsend'		=> 'Send',
'emailsent'		=> 'E-mail sent',
'emailsenttext' => 'Your e-mail message has been sent.',

# Watchlist
'watchlist'			=> 'My watchlist',
'watchlistfor' => "(for '''$1''')",
'nowatchlist'		=> 'You have no items on your watchlist.',
'watchlistanontext' => 'Please $1 to view or edit items on your watchlist.',
'watchlistcount' 	=> "'''You have $1 items on your watchlist, including talk pages.'''",
'clearwatchlist' 	=> 'Clear watchlist',
'watchlistcleartext' => 'Are you sure you wish to remove them?',
'watchlistclearbutton' => 'Clear watchlist',
'watchlistcleardone' => 'Your watchlist has been cleared. $1 items were removed.',
'watchnologin'		=> 'Not logged in',
'watchnologintext'	=> 'You must be [[Special:Userlogin|logged in]] to modify your watchlist.',
'addedwatch'		=> 'Added to watchlist',
'addedwatchtext'	=> "The page \"[[:$1]]\" has been added to your [[Special:Watchlist|watchlist]].
Future changes to this page and its associated Talk page will be listed there,
and the page will appear '''bolded''' in the [[Special:Recentchanges|list of recent changes]] to
make it easier to pick out.

If you want to remove the page from your watchlist later, click \"Unwatch\" in the sidebar.",
'removedwatch'		=> 'Removed from watchlist',
'removedwatchtext' 	=> "The page \"[[:$1]]\" has been removed from your watchlist.",
'watch' => 'Watch',
'watchthispage'		=> 'Watch this page',
'unwatch' => 'Unwatch',
'unwatchthispage' 	=> 'Stop watching',
'notanarticle'		=> 'Not a content page',
'watchnochange' 	=> 'None of your watched items was edited in the time period displayed.',
'watchdetails'		=> '* $1 pages watched not counting talk pages
* [[Special:Watchlist/edit|Show and edit complete watchlist]]
* [[Special:Watchlist/clear|Remove all pages]]',
'wlheader-enotif' 		=> "* E-mail notification is enabled.",
'wlheader-showupdated'   => "* Pages which have been changed since you last visited them are shown in '''bold'''",
'watchmethod-recent'=> 'checking recent edits for watched pages',
'watchmethod-list'	=> 'checking watched pages for recent edits',
'removechecked' 	=> 'Remove checked items from watchlist',
'watchlistcontains' => "Your watchlist contains $1 pages.",
'watcheditlist'		=> 'Here\'s an alphabetical list of your
watched content pages. Check the boxes of pages you want to remove from your watchlist and click the \'remove checked\' button
at the bottom of the screen (deleting a content page also deletes the accompanying talk page and vice versa).',
'removingchecked' 	=> 'Removing requested items from watchlist...',
'couldntremove' 	=> "Couldn't remove item '$1'...",
'iteminvalidname' 	=> "Problem with item '$1', invalid name...",
'wlnote' 		=> 'Below are the last $1 changes in the last <b>$2</b> hours.',
'wlshowlast' 		=> 'Show last $1 hours $2 days $3',
'wlsaved'		=> 'This is a saved version of your watchlist.',
'wlhideshowown'   	=> '$1 my edits',
'wlhideshowbots'   	=> '$1 bot edits',
'wldone'			=> 'Done.',

'enotif_mailer' 		=> '{{SITENAME}} Notification Mailer',
'enotif_reset'			=> 'Mark all pages visited',
'enotif_newpagetext'=> 'This is a new page.',
'changed'			=> 'changed',
'created'			=> 'created',
'enotif_subject' 	=> '{{SITENAME}} page $PAGETITLE has been $CHANGEDORCREATED by $PAGEEDITOR',
'enotif_lastvisited' => 'See $1 for all changes since your last visit.',
'enotif_body' => 'Dear $WATCHINGUSERNAME,

the {{SITENAME}} page $PAGETITLE has been $CHANGEDORCREATED on $PAGEEDITDATE by $PAGEEDITOR, see $PAGETITLE_URL for the current version.

$NEWPAGE

Editor\'s summary: $PAGESUMMARY $PAGEMINOREDIT

Contact the editor:
mail: $PAGEEDITOR_EMAIL
wiki: $PAGEEDITOR_WIKI

There will be no other notifications in case of further changes unless you visit this page. You could also reset the notification flags for all your watched pages on your watchlist.

             Your friendly {{SITENAME}} notification system

--
To change your watchlist settings, visit
{{fullurl:{{ns:special}}:Watchlist/edit}}

Feedback and further assistance:
{{fullurl:{{ns:help}}:Contents}}',

# Delete/protect/revert
#
'deletepage'	=> 'Delete page',
'confirm'		=> 'Confirm',
'excontent' => "content was: '$1'",
'excontentauthor' => "content was: '$1' (and the only contributor was '$2')",
'exbeforeblank' => "content before blanking was: '$1'",
'exblank' => 'page was empty',
'confirmdelete' => 'Confirm delete',
'deletesub'		=> "(Deleting \"$1\")",
'historywarning' => 'Warning: The page you are about to delete has a history:',
'confirmdeletetext' => "You are about to permanently delete a page
or image along with all of its history from the database.
Please confirm that you intend to do this, that you understand the
consequences, and that you are doing this in accordance with
[[{{ns:project}}:Policy]].",
'actioncomplete' => 'Action complete',
'deletedtext'	=> "\"$1\" has been deleted.
See $2 for a record of recent deletions.",
'deletedarticle' => "deleted \"[[$1]]\"",
'dellogpage'	=> 'Deletion log',
'dellogpagetext' => 'Below is a list of the most recent deletions.',
'deletionlog'	=> 'deletion log',
'reverted'		=> 'Reverted to earlier revision',
'deletecomment'	=> 'Reason for deletion',
'imagereverted' => 'Revert to earlier version was successful.',
'rollback'		=> 'Roll back edits',
'rollback_short' => 'Rollback',
'rollbacklink'	=> 'rollback',
'rollbackfailed' => 'Rollback failed',
'cantrollback'	=> 'Cannot revert edit; last contributor is only author of this page.',
'alreadyrolled'	=> "Cannot rollback last edit of [[$1]]
by [[User:$2|$2]] ([[User talk:$2|Talk]]); someone else has edited or rolled back the page already.

Last edit was by [[User:$3|$3]] ([[User talk:$3|Talk]]).",
#   only shown if there is an edit comment
'editcomment' => "The edit comment was: \"<i>$1</i>\".",
'revertpage'	=> "Reverted edits by [[Special:Contributions/$2|$2]] ([[User_talk:$2|Talk]]); changed back to last version by [[User:$1|$1]]",
'sessionfailure' => 'There seems to be a problem with your login session;
this action has been canceled as a precaution against session hijacking.
Please hit "back" and reload the page you came from, then try again.',
'protectlogpage' => 'Protection log',
'protectlogtext' => "Below is a list of page locks and unlocks.",
'protectedarticle' => 'protected "[[$1]]"',
'unprotectedarticle' => 'unprotected "[[$1]]"',
'protectsub' => '(Protecting "$1")',
'confirmprotecttext' => 'Do you really want to protect this page?',
'confirmprotect' => 'Confirm protection',
'protectmoveonly' => 'Protect from moves only',
'protectcomment' => 'Reason for protecting',
'unprotectsub' =>"(Unprotecting \"$1\")",
'confirmunprotecttext' => 'Do you really want to unprotect this page?',
'confirmunprotect' => 'Confirm unprotection',
'unprotectcomment' => 'Reason for unprotecting',
'protect-unchain' => 'Unlock move permissions',
'protect-text' => 'You may view and change the protection level here for the page <strong>$1</strong>.',
'protect-viewtext' => 'Your account does not have permission to change
page protection levels. Here are the current settings for the page <strong>$1</strong>:',
'protect-default' => '(default)',
'protect-level-autoconfirmed' => 'Block unregistered users',
'protect-level-sysop' => 'Sysops only',

# restrictions (nouns)
'restriction-edit' => 'Edit',
'restriction-move' => 'Move',


# Undelete
'undelete' => 'View deleted pages',
'undeletepage' => 'View and restore deleted pages',
'viewdeletedpage' => 'View deleted pages',
'undeletepagetext' => 'The following pages have been deleted but are still in the archive and
can be restored. The archive may be periodically cleaned out.',
'undeleteextrahelp' => "To restore the entire page, leave all checkboxes deselected and
click '''''Restore'''''. To perform a selective restoration, check the boxes corresponding to the
revisions to be restored, and click '''''Restore'''''. Clicking '''''Reset''''' will clear the
comment field and all checkboxes.",
'undeletearticle' => 'Restore deleted page',
'undeleterevisions' => "$1 revisions archived",
'undeletehistory' => 'If you restore the page, all revisions will be restored to the history.
If a new page with the same name has been created since the deletion, the restored
revisions will appear in the prior history, and the current revision of the live page
will not be automatically replaced.',
'undeletehistorynoadmin' => 'This article has been deleted. The reason for deletion is
shown in the summary below, along with details of the users who had edited this page
before deletion. The actual text of these deleted revisions is only available to administrators.',
'undeleterevision' => "Deleted revision as of $1",
'undeletebtn' => 'Restore',
'undeletereset' => 'Reset',
'undeletecomment' => 'Comment:',
'undeletedarticle' => "restored \"[[$1]]\"",
'undeletedrevisions' => "$1 revisions restored",
'undeletedrevisions-files' => "$1 revisions and $2 file(s) restored",
'undeletedfiles' => "$1 file(s) restored",
'cannotundelete' => 'Undelete failed; someone else may have undeleted the page first.',
'undeletedpage' => "<big>'''$1 has been restored'''</big>

Consult the [[Special:Log/delete|deletion log]] for a record of recent deletions and restorations.",

# Namespace form on various pages
'namespace' => 'Namespace:',
'invert' => 'Invert selection',

# Contributions
#
'contributions' => 'User contributions',
'mycontris'     => 'My contributions',
'contribsub'    => "For $1",
'nocontribs'    => 'No changes were found matching these criteria.',
'ucnote'        => "Below are this user's last <b>$1</b> changes in the last <b>$2</b> days.",
'uclinks'       => "View the last $1 changes; view the last $2 days.",
'uctop'         => ' (top)' ,
'newbies'       => 'newbies',

'sp-newimages-showfrom' => 'Show new images starting from $1',

'sp-contributions-newest' => 'Newest',
'sp-contributions-oldest' => 'Oldest',
'sp-contributions-newer'  => 'Newer $1',
'sp-contributions-older'  => 'Older $1',
'sp-contributions-newbies-sub' => 'For newbies',


# What links here
#
'whatlinkshere'	=> 'What links here',
'notargettitle' => 'No target',
'notargettext'	=> 'You have not specified a target page or user
to perform this function on.',
'linklistsub'	=> '(List of links)',
'linkshere'		=> 'The following pages link to here:',
'nolinkshere'	=> 'No pages link to here.',
'isredirect'	=> 'redirect page',
'istemplate'	=> 'inclusion',

# Block/unblock IP
#
'blockip'		=> 'Block user',
'blockiptext'	=> "Use the form below to block write access
from a specific IP address or username.
This should be done only only to prevent vandalism, and in
accordance with [[{{ns:project}}:Policy|policy]].
Fill in a specific reason below (for example, citing particular
pages that were vandalized).",
'ipaddress'		=> 'IP Address',
'ipadressorusername' => 'IP Address or username',
'ipbexpiry'		=> 'Expiry',
'ipbreason'		=> 'Reason',
'ipbsubmit'		=> 'Block this user',
'ipbother'		=> 'Other time',
'ipboptions'		=> '2 hours:2 hours,1 day:1 day,3 days:3 days,1 week:1 week,2 weeks:2 weeks,1 month:1 month,3 months:3 months,6 months:6 months,1 year:1 year,infinite:infinite',
'ipbotheroption'	=> 'other',
'badipaddress'	=> 'Invalid IP address',
'blockipsuccesssub' => 'Block succeeded',
'blockipsuccesstext' => '[[{{ns:Special}}:Contributions/$1|$1]] has been blocked.
<br />See [[{{ns:Special}}:Ipblocklist|IP block list]] to review blocks.',
'unblockip'		=> 'Unblock user',
'unblockiptext'	=> 'Use the form below to restore write access
to a previously blocked IP address or username.',
'ipusubmit'		=> 'Unblock this address',
'unblocked' => '[[User:$1|$1]] has been unblocked',
'ipblocklist'	=> 'List of blocked IP addresses and usernames',
'blocklistline'	=> "$1, $2 blocked $3 ($4)",
'infiniteblock' => 'infinite',
'expiringblock' => 'expires $1',
'ipblocklistempty'	=> 'The blocklist is empty.',
'blocklink'		=> 'block',
'unblocklink'	=> 'unblock',
'contribslink'	=> 'contribs',
'autoblocker'	=> 'Autoblocked because your IP address has been recently used by "[[User:$1|$1]]". The reason given for $1\'s block is: "\'\'\'$2\'\'\'"',
'blocklogpage'	=> 'Block log',
'blocklogentry'	=> 'blocked "[[$1]]" with an expiry time of $2',
'blocklogtext'	=> 'This is a log of user blocking and unblocking actions. Automatically
blocked IP addresses are not listed. See the [[Special:Ipblocklist|IP block list]] for
the list of currently operational bans and blocks.',
'unblocklogentry'	=> 'unblocked $1',
'range_block_disabled'	=> 'The sysop ability to create range blocks is disabled.',
'ipb_expiry_invalid'	=> 'Expiry time invalid.',
'ip_range_invalid'	=> 'Invalid IP range.',
'proxyblocker'	=> 'Proxy blocker',
'proxyblockreason'	=> 'Your IP address has been blocked because it is an open proxy. Please contact your Internet service provider or tech support and inform them of this serious security problem.',
'proxyblocksuccess'	=> 'Done.',
'sorbs'         => 'SORBS DNSBL',
'sorbsreason'   => 'Your IP address is listed as an open proxy in the [http://www.sorbs.net SORBS] DNSBL.',
'sorbs_create_account_reason' => 'Your IP address is listed as an open proxy in the [http://www.sorbs.net SORBS] DNSBL. You cannot create an account',


# Developer tools
#
'lockdb'		=> 'Lock database',
'unlockdb'		=> 'Unlock database',
'lockdbtext'	=> 'Locking the database will suspend the ability of all
users to edit pages, change their preferences, edit their watchlists, and
other things requiring changes in the database.
Please confirm that this is what you intend to do, and that you will
unlock the database when your maintenance is done.',
'unlockdbtext'	=> 'Unlocking the database will restore the ability of all
users to edit pages, change their preferences, edit their watchlists, and
other things requiring changes in the database.
Please confirm that this is what you intend to do.',
'lockconfirm'	=> 'Yes, I really want to lock the database.',
'unlockconfirm'	=> 'Yes, I really want to unlock the database.',
'lockbtn'		=> 'Lock database',
'unlockbtn'		=> 'Unlock database',
'locknoconfirm' => 'You did not check the confirmation box.',
'lockdbsuccesssub' => 'Database lock succeeded',
'unlockdbsuccesssub' => 'Database lock removed',
'lockdbsuccesstext' => 'The database has been locked.
<br />Remember to remove the lock after your maintenance is complete.',
'unlockdbsuccesstext' => 'The database has been unlocked.',

# Make sysop
'makesysoptitle'	=> 'Make a user into a sysop',
'makesysoptext'		=> 'This form is used by bureaucrats to turn ordinary users into administrators.
Type the name of the user in the box and press the button to make the user an administrator',
'makesysopname'		=> 'Name of the user:',
'makesysopsubmit'	=> 'Make this user into a sysop',
'makesysopok'		=> "<b>User \"$1\" is now a sysop</b>",
'makesysopfail'		=> "<b>User \"$1\" could not be made into a sysop. (Did you enter the name correctly?)</b>",
'setbureaucratflag' => 'Set bureaucrat flag',
'setstewardflag'    => 'Set steward flag',
'rightslog'		=> 'User rights log',
'rightslogtext'		=> 'This is a log of changes to user rights.',
'rightslogentry'	=> 'changed group membership for $1 from $2 to $3',
'rights'			=> 'Rights:',
'set_user_rights'	=> 'Set user rights',
'user_rights_set'	=> "<b>User rights for \"$1\" updated</b>",
'set_rights_fail'	=> "<b>User rights for \"$1\" could not be set. (Did you enter the name correctly?)</b>",
'makesysop'         => 'Make a user into a sysop',
'already_sysop'     => 'This user is already an administrator',
'already_bureaucrat' => 'This user is already a bureaucrat',
'already_steward'   => 'This user is already a steward',
'rightsnone' 		=> '(none)',

# Move page
#
'movepage'		=> 'Move page',
'movepagetext'	=> 'Using the form below will rename a page, moving all
of its history to the new name.
The old title will become a redirect page to the new title.
Links to the old page title will not be changed; be sure to
check for double or broken redirects.
You are responsible for making sure that links continue to
point where they are supposed to go.

Note that the page will \'\'\'not\'\'\' be moved if there is already
a page at the new title, unless it is empty or a redirect and has no
past edit history. This means that you can rename a page back to where
it was just renamed from if you make a mistake, and you cannot overwrite
an existing page.

<b>WARNING!</b>
This can be a drastic and unexpected change for a popular page;
please be sure you understand the consequences of this before
proceeding.',
'movepagetalktext' => 'The associated talk page will be automatically moved along with it \'\'\'unless:\'\'\'
*A non-empty talk page already exists under the new name, or
*You uncheck the box below.

In those cases, you will have to move or merge the page manually if desired.',
'movearticle'	=> 'Move page',
'movenologin'	=> 'Not logged in',
'movenologintext' => "You must be a registered user and [[Special:Userlogin|logged in]]
to move a page.",
'newtitle'		=> 'To new title',
'movepagebtn'	=> 'Move page',
'pagemovedsub'	=> 'Move succeeded',
'pagemovedtext' => "Page \"[[$1]]\" moved to \"[[$2]]\".",
'articleexists' => 'A page of that name already exists, or the
name you have chosen is not valid.
Please choose another name.',
'talkexists'	=> "'''The page itself was moved successfully, but the talk page could not be moved because one already exists at the new title. Please merge them manually.'''",
'movedto'		=> 'moved to',
'movetalk'		=> 'Move associated talk page',
'talkpagemoved' => 'The corresponding talk page was also moved.',
'talkpagenotmoved' => 'The corresponding talk page was <strong>not</strong> moved.',
'1movedto2'		=> '[[$1]] moved to [[$2]]',
'1movedto2_redir' => '[[$1]] moved to [[$2]] over redirect',
'movelogpage' => 'Move log',
'movelogpagetext' => 'Below is a list of page moved.',
'movereason'	=> 'Reason',
'revertmove'	=> 'revert',
'delete_and_move' => 'Delete and move',
'delete_and_move_text'	=>
'==Deletion required==

The destination article "[[$1]]" already exists. Do you want to delete it to make way for the move?',
'delete_and_move_confirm' => 'Yes, delete the page',
'delete_and_move_reason' => 'Deleted to make way for move',
'selfmove' => "Source and destination titles are the same; can't move a page over itself.",
'immobile_namespace' => "Destination title is of a special type; cannot move pages into that namespace.",

# Export

'export'		=> 'Export pages',
'exporttext'	=> 'You can export the text and editing history of a particular page or
set of pages wrapped in some XML. This can be imported into another wiki using MediaWiki
via the Special:Import page.

To export pages, enter the titles in the text box below, one title per line, and
select whether you want the current version as well as all old versions, with the page
history lines, or just the current version with the info about the last edit.

In the latter case you can also use a link, e.g. [[{{ns:Special}}:Export/{{int:mainpage}}]] for the page {{int:mainpage}}.',
'exportcuronly'	=> 'Include only the current revision, not the full history',
'exportnohistory' => "----
'''Note:''' Exporting the full history of pages through this form has been disabled due to performance reasons.",
'export-submit' => 'Export',

# Namespace 8 related

'allmessages'	=> 'System messages',
'allmessagesname' => 'Name',
'allmessagesdefault' => 'Default text',
'allmessagescurrent' => 'Current text',
'allmessagestext'	=> 'This is a list of system messages available in the MediaWiki namespace.',
'allmessagesnotsupportedUI' => 'Your current interface language <b>$1</b> is not supported by Special:Allmessages at this site.',
'allmessagesnotsupportedDB' => '\'\'\'Special:Allmessages\'\'\' cannot be used because \'\'\'$wgUseDatabaseMessages\'\'\' is switched off.',
'allmessagesfilter' => 'Message name filter:',
'allmessagesmodified' => 'Show only modified',


# Thumbnails

'thumbnail-more'	=> 'Enlarge',
'missingimage'		=> '<b>Missing image</b><br /><i>$1</i>',
'filemissing'		=> 'File missing',
'thumbnail_error'   => 'Error creating thumbnail: $1',

# Special:Import
'import'	=> 'Import pages',
'importinterwiki' => 'Transwiki import',
'import-interwiki-text' => 'Select a wiki and page title to import.
Revision dates and editors\' names will be preserved.
All transwiki import actions are logged at the [[Special:Log/import|import log]].',
'import-interwiki-history' => 'Copy all history versions for this page',
'import-interwiki-submit' => 'Import',
'import-interwiki-namespace' => 'Transfer pages into namespace:',
'importtext'	=> 'Please export the file from the source wiki using the Special:Export utility, save it to your disk and upload it here.',
'importstart'	=> "Importing pages...",
'import-revision-count' => '$1 revision(s)',
'importnopages'	=> "No pages to import.",
'importfailed'	=> "Import failed: $1",
'importunknownsource'	=> "Unknown import source type",
'importcantopen'	=> "Couldn't open import file",
'importbadinterwiki'	=> "Bad interwiki link",
'importnotext'	=> 'Empty or no text',
'importsuccess'	=> 'Import succeeded!',
'importhistoryconflict' => 'Conflicting history revision exists (may have imported this page before)',
'importnosources' => 'No transwiki import sources have been defined and direct history uploads are disabled.',
'importnofile' => 'No import file was uploaded.',
'importuploaderror' => 'Upload of import file failed; perhaps the file is bigger than the allowed upload size.',

# import log
'importlogpage' => 'Import log',
'importlogpagetext' => 'Administrative imports of pages with edit history from other wikis.',
'import-logentry-upload' => 'imported $1 by file upload',
'import-logentry-upload-detail' => '$1 revision(s)',
'import-logentry-interwiki' => 'transwikied $1',
'import-logentry-interwiki-detail' => '$1 revision(s) from $2',


# Keyboard access keys for power users
'accesskey-search' => 'f',
'accesskey-minoredit' => 'i',
'accesskey-save' => 's',
'accesskey-preview' => 'p',
'accesskey-diff' => 'v',
'accesskey-compareselectedversions' => 'v',
'accesskey-watch' => 'w',

# tooltip help for some actions, most are in Monobook.js
'tooltip-search' => 'Search {{SITENAME}} [alt-f]',
'tooltip-minoredit' => 'Mark this as a minor edit [alt-i]',
'tooltip-save' => 'Save your changes [alt-s]',
'tooltip-preview' => 'Preview your changes, please use this before saving! [alt-p]',
'tooltip-diff' => 'Show which changes you made to the text. [alt-v]',
'tooltip-compareselectedversions' => 'See the differences between the two selected versions of this page. [alt-v]',
'tooltip-watch' => 'Add this page to your watchlist [alt-w]',

# stylesheets
'Common.css' => '/** CSS placed here will be applied to all skins */',
'Monobook.css' => '/* CSS placed here will affect users of the Monobook skin */',

# Metadata
'nodublincore' => 'Dublin Core RDF metadata disabled for this server.',
'nocreativecommons' => 'Creative Commons RDF metadata disabled for this server.',
'notacceptable' => 'The wiki server can\'t provide data in a format your client can read.',

# Attribution

'anonymous' => 'Anonymous user(s) of {{SITENAME}}',
'siteuser' => '{{SITENAME}} user $1',
'lastmodifiedby' => 'This page was last modified $1 by $2.',
'and' => 'and',
'othercontribs' => 'Based on work by $1.',
'others' => 'others',
'siteusers' => '{{SITENAME}} user(s) $1',
'creditspage' => 'Page credits',
'nocredits' => 'There is no credits info available for this page.',

# Spam protection

'spamprotectiontitle' => 'Spam protection filter',
'spamprotectiontext' => 'The page you wanted to save was blocked by the spam filter. This is probably caused by a link to an external site.',
'spamprotectionmatch' => 'The following text is what triggered our spam filter: $1',
'subcategorycount' => "There {{PLURAL:$1|is one subcategory|are $1 subcategories}} to this category.",
'categoryarticlecount' => "There {{PLURAL:$1|is one article|are $1 articles}} in this category.",
'listingcontinuesabbrev' => " cont.",
'spambot_username' => 'MediaWiki spam cleanup',
'spam_reverting' => 'Reverting to last version not containing links to $1',
'spam_blanking' => 'All revisions contained links to $1, blanking',

# Info page
'infosubtitle' => 'Information for page',
'numedits' => 'Number of edits (article): $1',
'numtalkedits' => 'Number of edits (discussion page): $1',
'numwatchers' => 'Number of watchers: $1',
'numauthors' => 'Number of distinct authors (article): $1',
'numtalkauthors' => 'Number of distinct authors (discussion page): $1',

# Math options
'mw_math_png' => 'Always render PNG',
'mw_math_simple' => 'HTML if very simple or else PNG',
'mw_math_html' => 'HTML if possible or else PNG',
'mw_math_source' => 'Leave it as TeX (for text browsers)',
'mw_math_modern' => 'Recommended for modern browsers',
'mw_math_mathml' => 'MathML if possible (experimental)',

# Patrolling
'markaspatrolleddiff'   => "Mark as patrolled",
'markaspatrolledlink'   => "[$1]",
'markaspatrolledtext'   => "Mark this article as patrolled",
'markedaspatrolled'     => "Marked as patrolled",
'markedaspatrolledtext' => "The selected revision has been marked as patrolled.",
'rcpatroldisabled'      => "Recent Changes Patrol disabled",
'rcpatroldisabledtext'  => "The Recent Changes Patrol feature is currently disabled.",
'markedaspatrollederror'  => "Cannot mark as patrolled",
'markedaspatrollederrortext' => "You need to specify a revision to mark as patrolled.",

# Monobook.js: tooltips and access keys for monobook
'Monobook.js' => '/* tooltips and access keys */
var ta = new Object();
ta[\'pt-userpage\'] = new Array(\'.\',\'My user page\');
ta[\'pt-anonuserpage\'] = new Array(\'.\',\'The user page for the ip you\\\'re editing as\');
ta[\'pt-mytalk\'] = new Array(\'n\',\'My talk page\');
ta[\'pt-anontalk\'] = new Array(\'n\',\'Discussion about edits from this ip address\');
ta[\'pt-preferences\'] = new Array(\'\',\'My preferences\');
ta[\'pt-watchlist\'] = new Array(\'l\',\'The list of pages you\\\'re monitoring for changes.\');
ta[\'pt-mycontris\'] = new Array(\'y\',\'List of my contributions\');
ta[\'pt-login\'] = new Array(\'o\',\'You are encouraged to log in, it is not mandatory however.\');
ta[\'pt-anonlogin\'] = new Array(\'o\',\'You are encouraged to log in, it is not mandatory however.\');
ta[\'pt-logout\'] = new Array(\'o\',\'Log out\');
ta[\'ca-talk\'] = new Array(\'t\',\'Discussion about the content page\');
ta[\'ca-edit\'] = new Array(\'e\',\'You can edit this page. Please use the preview button before saving.\');
ta[\'ca-addsection\'] = new Array(\'+\',\'Add a comment to this discussion.\');
ta[\'ca-viewsource\'] = new Array(\'e\',\'This page is protected. You can view its source.\');
ta[\'ca-history\'] = new Array(\'h\',\'Past versions of this page.\');
ta[\'ca-protect\'] = new Array(\'=\',\'Protect this page\');
ta[\'ca-delete\'] = new Array(\'d\',\'Delete this page\');
ta[\'ca-undelete\'] = new Array(\'d\',\'Restore the edits done to this page before it was deleted\');
ta[\'ca-move\'] = new Array(\'m\',\'Move this page\');
ta[\'ca-watch\'] = new Array(\'w\',\'Add this page to your watchlist\');
ta[\'ca-unwatch\'] = new Array(\'w\',\'Remove this page from your watchlist\');
ta[\'search\'] = new Array(\'f\',\'Search this wiki\');
ta[\'p-logo\'] = new Array(\'\',\'Main Page\');
ta[\'n-mainpage\'] = new Array(\'z\',\'Visit the Main Page\');
ta[\'n-portal\'] = new Array(\'\',\'About the project, what you can do, where to find things\');
ta[\'n-currentevents\'] = new Array(\'\',\'Find background information on current events\');
ta[\'n-recentchanges\'] = new Array(\'r\',\'The list of recent changes in the wiki.\');
ta[\'n-randompage\'] = new Array(\'x\',\'Load a random page\');
ta[\'n-help\'] = new Array(\'\',\'The place to find out.\');
ta[\'n-sitesupport\'] = new Array(\'\',\'Support us\');
ta[\'t-whatlinkshere\'] = new Array(\'j\',\'List of all wiki pages that link here\');
ta[\'t-recentchangeslinked\'] = new Array(\'k\',\'Recent changes in pages linked from this page\');
ta[\'feed-rss\'] = new Array(\'\',\'RSS feed for this page\');
ta[\'feed-atom\'] = new Array(\'\',\'Atom feed for this page\');
ta[\'t-contributions\'] = new Array(\'\',\'View the list of contributions of this user\');
ta[\'t-emailuser\'] = new Array(\'\',\'Send a mail to this user\');
ta[\'t-upload\'] = new Array(\'u\',\'Upload images or media files\');
ta[\'t-specialpages\'] = new Array(\'q\',\'List of all special pages\');
ta[\'ca-nstab-main\'] = new Array(\'c\',\'View the content page\');
ta[\'ca-nstab-user\'] = new Array(\'c\',\'View the user page\');
ta[\'ca-nstab-media\'] = new Array(\'c\',\'View the media page\');
ta[\'ca-nstab-special\'] = new Array(\'\',\'This is a special page, you can\\\'t edit the page itself.\');
ta[\'ca-nstab-project\'] = new Array(\'a\',\'View the project page\');
ta[\'ca-nstab-image\'] = new Array(\'c\',\'View the image page\');
ta[\'ca-nstab-mediawiki\'] = new Array(\'c\',\'View the system message\');
ta[\'ca-nstab-template\'] = new Array(\'c\',\'View the template\');
ta[\'ca-nstab-help\'] = new Array(\'c\',\'View the help page\');
ta[\'ca-nstab-category\'] = new Array(\'c\',\'View the category page\');',

# image deletion
'deletedrevision' => 'Deleted old revision $1.',

# browsing diffs
'previousdiff' => '← Previous diff',
'nextdiff' => 'Next diff →',

'imagemaxsize' => 'Limit images on image description pages to:',
'thumbsize'	=> 'Thumbnail size:',
'showbigimage' => 'Download high resolution version ($1x$2, $3 KB)',

'newimages' => 'Gallery of new files',
'showhidebots' => '($1 bots)',
'noimages'  => 'Nothing to see.',

# short names for language variants used for language conversion links.
# to disable showing a particular link, set it to 'disable', e.g.
# 'variantname-zh-sg' => 'disable',
'variantname-zh-cn' => 'cn',
'variantname-zh-tw' => 'tw',
'variantname-zh-hk' => 'hk',
'variantname-zh-sg' => 'sg',
'variantname-zh' => 'zh',
# variants for Serbian language
'variantname-sr-ec' => 'sr-ec',
'variantname-sr-el' => 'sr-el',
'variantname-sr-jc' => 'sr-jc',
'variantname-sr-jl' => 'sr-jl',
'variantname-sr' => 'sr',

# labels for User: and Title: on Special:Log pages
'specialloguserlabel' => 'User:',
'speciallogtitlelabel' => 'Title:',

'passwordtooshort' => 'Your password is too short. It must have at least $1 characters.',

# Media Warning
'mediawarning' => '\'\'\'Warning\'\'\': This file may contain malicious code, by executing it your system may be compromised.<hr />',

'fileinfo' => '$1KB, MIME type: <code>$2</code>',

# Metadata
'metadata' => 'Metadata',
'metadata-help' => 'This file contains additional information, probably added from the digital camera or scanner used to create or digitize it. If the file has been modified from its original state, some details may not fully reflect the modified image.',
'metadata-expand' => 'Show extended details',
'metadata-collapse' => 'Hide extended details',
'metadata-fields' => 'EXIF metadata fields listed in this message will
be included on image page display when the metadata table
is collapsed. Others will be hidden by default.
* make
* model
* datetimeoriginal
* exposuretime
* fnumber
* focallength',

# Exif tags
'exif-imagewidth' =>'Width',
'exif-imagelength' =>'Height',
'exif-bitspersample' =>'Bits per component',
'exif-compression' =>'Compression scheme',
'exif-photometricinterpretation' =>'Pixel composition',
'exif-orientation' =>'Orientation',
'exif-samplesperpixel' =>'Number of components',
'exif-planarconfiguration' =>'Data arrangement',
'exif-ycbcrsubsampling' =>'Subsampling ratio of Y to C',
'exif-ycbcrpositioning' =>'Y and C positioning',
'exif-xresolution' =>'Horizontal resolution',
'exif-yresolution' =>'Vertical resolution',
'exif-resolutionunit' =>'Unit of X and Y resolution',
'exif-stripoffsets' =>'Image data location',
'exif-rowsperstrip' =>'Number of rows per strip',
'exif-stripbytecounts' =>'Bytes per compressed strip',
'exif-jpeginterchangeformat' =>'Offset to JPEG SOI',
'exif-jpeginterchangeformatlength' =>'Bytes of JPEG data',
'exif-transferfunction' =>'Transfer function',
'exif-whitepoint' =>'White point chromaticity',
'exif-primarychromaticities' =>'Chromaticities of primarities',
'exif-ycbcrcoefficients' =>'Color space transformation matrix coefficients',
'exif-referenceblackwhite' =>'Pair of black and white reference values',
'exif-datetime' =>'File change date and time',
'exif-imagedescription' =>'Image title',
'exif-make' =>'Camera manufacturer',
'exif-model' =>'Camera model',
'exif-software' =>'Software used',
'exif-artist' =>'Author',
'exif-copyright' =>'Copyright holder',
'exif-exifversion' =>'Exif version',
'exif-flashpixversion' =>'Supported Flashpix version',
'exif-colorspace' =>'Color space',
'exif-componentsconfiguration' =>'Meaning of each component',
'exif-compressedbitsperpixel' =>'Image compression mode',
'exif-pixelydimension' =>'Valid image width',
'exif-pixelxdimension' =>'Valid image height',
'exif-makernote' =>'Manufacturer notes',
'exif-usercomment' =>'User comments',
'exif-relatedsoundfile' =>'Related audio file',
'exif-datetimeoriginal' =>'Date and time of data generation',
'exif-datetimedigitized' =>'Date and time of digitizing',
'exif-subsectime' =>'DateTime subseconds',
'exif-subsectimeoriginal' =>'DateTimeOriginal subseconds',
'exif-subsectimedigitized' =>'DateTimeDigitized subseconds',
'exif-exposuretime' =>'Exposure time',
'exif-exposuretime-format' => '$1 sec ($2)',
'exif-fnumber' =>'F Number',
'exif-fnumber-format' =>'f/$1',
'exif-exposureprogram' =>'Exposure Program',
'exif-spectralsensitivity' =>'Spectral sensitivity',
'exif-isospeedratings' =>'ISO speed rating',
'exif-oecf' =>'Optoelectronic conversion factor',
'exif-shutterspeedvalue' =>'Shutter speed',
'exif-aperturevalue' =>'Aperture',
'exif-brightnessvalue' =>'Brightness',
'exif-exposurebiasvalue' =>'Exposure bias',
'exif-maxaperturevalue' =>'Maximum land aperture',
'exif-subjectdistance' =>'Subject distance',
'exif-meteringmode' =>'Metering mode',
'exif-lightsource' =>'Light source',
'exif-flash' =>'Flash',
'exif-focallength' =>'Lens focal length',
'exif-focallength-format' =>'$1 mm',
'exif-subjectarea' =>'Subject area',
'exif-flashenergy' =>'Flash energy',
'exif-spatialfrequencyresponse' =>'Spatial frequency response',
'exif-focalplanexresolution' =>'Focal plane X resolution',
'exif-focalplaneyresolution' =>'Focal plane Y resolution',
'exif-focalplaneresolutionunit' =>'Focal plane resolution unit',
'exif-subjectlocation' =>'Subject location',
'exif-exposureindex' =>'Exposure index',
'exif-sensingmethod' =>'Sensing method',
'exif-filesource' =>'File source',
'exif-scenetype' =>'Scene type',
'exif-cfapattern' =>'CFA pattern',
'exif-customrendered' =>'Custom image processing',
'exif-exposuremode' =>'Exposure mode',
'exif-whitebalance' =>'White Balance',
'exif-digitalzoomratio' =>'Digital zoom ratio',
'exif-focallengthin35mmfilm' =>'Focal length in 35 mm film',
'exif-scenecapturetype' =>'Scene capture type',
'exif-gaincontrol' =>'Scene control',
'exif-contrast' =>'Contrast',
'exif-saturation' =>'Saturation',
'exif-sharpness' =>'Sharpness',
'exif-devicesettingdescription' =>'Device settings description',
'exif-subjectdistancerange' =>'Subject distance range',
'exif-imageuniqueid' =>'Unique image ID',
'exif-gpsversionid' =>'GPS tag version',
'exif-gpslatituderef' =>'North or South Latitude',
'exif-gpslatitude' =>'Latitude',
'exif-gpslongituderef' =>'East or West Longitude',
'exif-gpslongitude' =>'Longitude',
'exif-gpsaltituderef' =>'Altitude reference',
'exif-gpsaltitude' =>'Altitude',
'exif-gpstimestamp' =>'GPS time (atomic clock)',
'exif-gpssatellites' =>'Satellites used for measurement',
'exif-gpsstatus' =>'Receiver status',
'exif-gpsmeasuremode' =>'Measurement mode',
'exif-gpsdop' =>'Measurement precision',
'exif-gpsspeedref' =>'Speed unit',
'exif-gpsspeed' =>'Speed of GPS receiver',
'exif-gpstrackref' =>'Reference for direction of movement',
'exif-gpstrack' =>'Direction of movement',
'exif-gpsimgdirectionref' =>'Reference for direction of image',
'exif-gpsimgdirection' =>'Direction of image',
'exif-gpsmapdatum' =>'Geodetic survey data used',
'exif-gpsdestlatituderef' =>'Reference for latitude of destination',
'exif-gpsdestlatitude' =>'Latitude destination',
'exif-gpsdestlongituderef' =>'Reference for longitude of destination',
'exif-gpsdestlongitude' =>'Longitude of destination',
'exif-gpsdestbearingref' =>'Reference for bearing of destination',
'exif-gpsdestbearing' =>'Bearing of destination',
'exif-gpsdestdistanceref' =>'Reference for distance to destination',
'exif-gpsdestdistance' =>'Distance to destination',
'exif-gpsprocessingmethod' =>'Name of GPS processing method',
'exif-gpsareainformation' =>'Name of GPS area',
'exif-gpsdatestamp' =>'GPS date',
'exif-gpsdifferential' =>'GPS differential correction',

# Make & model, can be wikified in order to link to the camera and model name

'exif-make-value' => '$1',
'exif-model-value' =>'$1',
'exif-software-value' => '$1',

# Exif attributes

'exif-compression-1' => 'Uncompressed',
'exif-compression-6' => 'JPEG',

'exif-photometricinterpretation-2' => 'RGB',
'exif-photometricinterpretation-6' => 'YCbCr',

'exif-orientation-1' => 'Normal', // 0th row: top; 0th column: left
'exif-orientation-2' => 'Flipped horizontally', // 0th row: top; 0th column: right
'exif-orientation-3' => 'Rotated 180°', // 0th row: bottom; 0th column: right
'exif-orientation-4' => 'Flipped vertically', // 0th row: bottom; 0th column: left
'exif-orientation-5' => 'Rotated 90° CCW and flipped vertically', // 0th row: left; 0th column: top
'exif-orientation-6' => 'Rotated 90° CW', // 0th row: right; 0th column: top
'exif-orientation-7' => 'Rotated 90° CW and flipped vertically', // 0th row: right; 0th column: bottom
'exif-orientation-8' => 'Rotated 90° CCW', // 0th row: left; 0th column: bottom

'exif-planarconfiguration-1' => 'chunky format',
'exif-planarconfiguration-2' => 'planar format',

'exif-xyresolution-i' => '$1 dpi',
'exif-xyresolution-c' => '$1 dpc',

'exif-colorspace-1' => 'sRGB',
'exif-colorspace-ffff.h' => 'FFFF.H',

'exif-componentsconfiguration-0' => 'does not exist',
'exif-componentsconfiguration-1' => 'Y',
'exif-componentsconfiguration-2' => 'Cb',
'exif-componentsconfiguration-3' => 'Cr',
'exif-componentsconfiguration-4' => 'R',
'exif-componentsconfiguration-5' => 'G',
'exif-componentsconfiguration-6' => 'B',

'exif-exposureprogram-0' => 'Not defined',
'exif-exposureprogram-1' => 'Manual',
'exif-exposureprogram-2' => 'Normal program',
'exif-exposureprogram-3' => 'Aperture priority',
'exif-exposureprogram-4' => 'Shutter priority',
'exif-exposureprogram-5' => 'Creative program (biased toward depth of field)',
'exif-exposureprogram-6' => 'Action program (biased toward fast shutter speed)',
'exif-exposureprogram-7' => 'Portrait mode (for closeup photos with the background out of focus)',
'exif-exposureprogram-8' => 'Landscape mode (for landscape photos with the background in focus)',

'exif-subjectdistance-value' => '$1 metres',

'exif-meteringmode-0' => 'Unknown',
'exif-meteringmode-1' => 'Average',
'exif-meteringmode-2' => 'CenterWeightedAverage',
'exif-meteringmode-3' => 'Spot',
'exif-meteringmode-4' => 'MultiSpot',
'exif-meteringmode-5' => 'Pattern',
'exif-meteringmode-6' => 'Partial',
'exif-meteringmode-255' => 'Other',

'exif-lightsource-0' => 'Unknown',
'exif-lightsource-1' => 'Daylight',
'exif-lightsource-2' => 'Fluorescent',
'exif-lightsource-3' => 'Tungsten (incandescent light)',
'exif-lightsource-4' => 'Flash',
'exif-lightsource-9' => 'Fine weather',
'exif-lightsource-10' => 'Cloudy weather',
'exif-lightsource-11' => 'Shade',
'exif-lightsource-12' => 'Daylight fluorescent (D 5700 – 7100K)',
'exif-lightsource-13' => 'Day white fluorescent (N 4600 – 5400K)',
'exif-lightsource-14' => 'Cool white fluorescent (W 3900 – 4500K)',
'exif-lightsource-15' => 'White fluorescent (WW 3200 – 3700K)',
'exif-lightsource-17' => 'Standard light A',
'exif-lightsource-18' => 'Standard light B',
'exif-lightsource-19' => 'Standard light C',
'exif-lightsource-20' => 'D55',
'exif-lightsource-21' => 'D65',
'exif-lightsource-22' => 'D75',
'exif-lightsource-23' => 'D50',
'exif-lightsource-24' => 'ISO studio tungsten',
'exif-lightsource-255' => 'Other light source',

'exif-focalplaneresolutionunit-2' => 'inches',

'exif-sensingmethod-1' => 'Undefined',
'exif-sensingmethod-2' => 'One-chip color area sensor',
'exif-sensingmethod-3' => 'Two-chip color area sensor',
'exif-sensingmethod-4' => 'Three-chip color area sensor',
'exif-sensingmethod-5' => 'Color sequential area sensor',
'exif-sensingmethod-7' => 'Trilinear sensor',
'exif-sensingmethod-8' => 'Color sequential linear sensor',

'exif-filesource-3' => 'DSC',

'exif-scenetype-1' => 'A directly photographed image',

'exif-customrendered-0' => 'Normal process',
'exif-customrendered-1' => 'Custom process',

'exif-exposuremode-0' => 'Auto exposure',
'exif-exposuremode-1' => 'Manual exposure',
'exif-exposuremode-2' => 'Auto bracket',

'exif-whitebalance-0' => 'Auto white balance',
'exif-whitebalance-1' => 'Manual white balance',

'exif-scenecapturetype-0' => 'Standard',
'exif-scenecapturetype-1' => 'Landscape',
'exif-scenecapturetype-2' => 'Portrait',
'exif-scenecapturetype-3' => 'Night scene',

'exif-gaincontrol-0' => 'None',
'exif-gaincontrol-1' => 'Low gain up',
'exif-gaincontrol-2' => 'High gain up',
'exif-gaincontrol-3' => 'Low gain down',
'exif-gaincontrol-4' => 'High gain down',

'exif-contrast-0' => 'Normal',
'exif-contrast-1' => 'Soft',
'exif-contrast-2' => 'Hard',

'exif-saturation-0' => 'Normal',
'exif-saturation-1' => 'Low saturation',
'exif-saturation-2' => 'High saturation',

'exif-sharpness-0' => 'Normal',
'exif-sharpness-1' => 'Soft',
'exif-sharpness-2' => 'Hard',

'exif-subjectdistancerange-0' => 'Unknown',
'exif-subjectdistancerange-1' => 'Macro',
'exif-subjectdistancerange-2' => 'Close view',
'exif-subjectdistancerange-3' => 'Distant view',

// Pseudotags used for GPSLatitudeRef and GPSDestLatitudeRef
'exif-gpslatitude-n' => 'North latitude',
'exif-gpslatitude-s' => 'South latitude',

// Pseudotags used for GPSLongitudeRef and GPSDestLongitudeRef
'exif-gpslongitude-e' => 'East longitude',
'exif-gpslongitude-w' => 'West longitude',

'exif-gpsstatus-a' => 'Measurement in progress',
'exif-gpsstatus-v' => 'Measurement interoperability',

'exif-gpsmeasuremode-2' => '2-dimensional measurement',
'exif-gpsmeasuremode-3' => '3-dimensional measurement',

// Pseudotags used for GPSSpeedRef and GPSDestDistanceRef
'exif-gpsspeed-k' => 'Kilometres per hour',
'exif-gpsspeed-m' => 'Miles per hour',
'exif-gpsspeed-n' => 'Knots',

// Pseudotags used for GPSTrackRef, GPSImgDirectionRef and GPSDestBearingRef
'exif-gpsdirection-t' => 'True direction',
'exif-gpsdirection-m' => 'Magnetic direction',

# external editor support
'edit-externally' => 'Edit this file using an external application',
'edit-externally-help' => 'See the [http://meta.wikimedia.org/wiki/Help:External_editors setup instructions] for more information.',

# 'all' in various places, this might be different for inflected languages
'recentchangesall' => 'all',
'imagelistall' => 'all',
'watchlistall1' => 'all',
'watchlistall2' => 'all',
'namespacesall' => 'all',

# E-mail address confirmation
'confirmemail' => 'Confirm E-mail address',
'confirmemail_text' => "This wiki requires that you validate your e-mail address
before using e-mail features. Activate the button below to send a confirmation
mail to your address. The mail will include a link containing a code; load the
link in your browser to confirm that your e-mail address is valid.",
'confirmemail_send' => 'Mail a confirmation code',
'confirmemail_sent' => 'Confirmation e-mail sent.',
'confirmemail_sendfailed' => 'Could not send confirmation mail. Check address for invalid characters.',
'confirmemail_invalid' => 'Invalid confirmation code. The code may have expired.',
'confirmemail_needlogin' => 'You need to $1 to confirm your email address.',
'confirmemail_success' => 'Your e-mail address has been confirmed. You may now log in and enjoy the wiki.',
'confirmemail_loggedin' => 'Your e-mail address has now been confirmed.',
'confirmemail_error' => 'Something went wrong saving your confirmation.',

'confirmemail_subject' => '{{SITENAME}} e-mail address confirmation',
'confirmemail_body' => "Someone, probably you from IP address $1, has registered an
account \"$2\" with this e-mail address on {{SITENAME}}.

To confirm that this account really does belong to you and activate
e-mail features on {{SITENAME}}, open this link in your browser:

$3

If this is *not* you, don't follow the link. This confirmation code
will expire at $4.",

# Inputbox extension, may be useful in other contexts as well
'tryexact' => 'Try exact match',
'searchfulltext' => 'Search full text',
'createarticle' => 'Create article',

# Scary transclusion
'scarytranscludedisabled' => '[Interwiki transcluding is disabled]',
'scarytranscludefailed' => '[Template fetch failed for $1; sorry]',
'scarytranscludetoolong' => '[URL is too long; sorry]',

# Trackbacks
'trackbackbox' => '<div id="mw_trackbacks">
Trackbacks for this article:<br />
$1
</div>',
'trackback' => '; $4$5 : [$2 $1]',
'trackbackexcerpt' => '; $4$5 : [$2 $1]: <nowiki>$3</nowiki>',
'trackbackremove' => ' ([$1 Delete])',
'trackbacklink' => 'Trackback',
'trackbackdeleteok' => 'The trackback was successfully deleted.',


# delete conflict

'deletedwhileediting' => 'Warning: This page has been deleted after you started editing!',
'confirmrecreate' => 'User [[User:$1|$1]] ([[User talk:$1|talk]]) deleted this page after you started editing with reason:
: \'\'$2\'\'
Please confirm that really want to recreate this page.',
'recreate' => 'Recreate',
'tooltip-recreate' => 'Recreate the page despite it has been deleted',

'unit-pixel' => 'px',

# HTML dump
'redirectingto' => 'Redirecting to [[$1]]...',

# action=purge
'confirm_purge' => "Clear the cache of this page?\n\n$1",
'confirm_purge_button' => 'OK',

'youhavenewmessagesmulti' => "You have new messages on $1",
'newtalkseperator' => ',_',
'searchcontaining' => "Search for articles containing ''$1''.",
'searchnamed' => "Search for articles named ''$1''.",
'articletitles' => "Articles starting with ''$1''",
'hideresults' => 'Hide results',

# DISPLAYTITLE
'displaytitle' => '(Link to this page as [[$1]])',

# Separator for categories in page lists
# Please don't localise this
'catseparator' => '|',

'loginlanguagelabel' => 'Language: $1',

# Don't duplicate this in translations; defaults should remain consistent
'loginlanguagelinks' => "* Deutsch|de
* English|en
* Esperanto|eo
* Français|fr
* Español|es
* Italiano|it
* Nederlands|nl",

# WERELATE
'add' => 'Add',
'all' => 'All',
'admin' => 'Admin',
'articles' => 'Articles',
'browseall' => 'Browse All',
'comparepages' => 'Compare pages',
'contents' => 'Contents',
'families' => 'Families',
'family' => 'Family',
'gedcomreview' => 'GEDCOM review',
'home' => 'Home',
'image' => 'Image',
'images' => 'Images',
'launchfte' => 'Launch FTE',
'logs' => 'Logs',
'myrelate' => 'My Relate',
'mysource' => 'MySource',
'mytrees' => 'My Trees',    // added Dec 2020 by Janet Bjorndahl
'nominate' => 'Nominate',
'otherpage' => 'Other Page',
'people' => 'People',
'person' => 'Person',
'place' => 'Place',
'places' => 'Places',
'portals' => 'Portals',
'repository' => 'Repository',
'reviewneeded' => 'Review Needed',
'speedydelete' => 'Speedy Delete',
'source' => 'Source',
'sources' => 'Sources',
'standardizetitle' => 'Standardize title',
'suggestions' => 'Suggestions',
'support' => 'Support',
'transcript' => 'Transcript',
'treeupdate' => 'Trees',
'treeupdatebutton' => 'Update',
'userprofile' => 'User Profile',
'userwildcard' => 'User name cannot include a search wildcard (*,?)',    // added Nov 2021 by Janet Bjorndahl
'watercooler' => 'Watercooler',

# Data quality issues    // added Apr 2022 by Janet Bjorndahl
'DQissues' => 'Data Quality Issues',
'unusualsituations' => '(incomplete data, errors and unusual situations that should be reviewed)',
'verified' => 'Verified',
'verifiedagain' => 'Verified (again)',
'deferissue' => 'Defer',
'DQstatistics' => 'Data Quality Statistics',    // added Aug 2022 by Janet Bjorndahl
'trackingDQprogress' => '(a way to measure the health of the data and track progress on addressing issues)',

);

?>
