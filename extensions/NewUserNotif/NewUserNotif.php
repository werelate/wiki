<?php
/**
 * Extension to provide customisable email notification of new user creation
 *
 * @author Rob Church <robchur@gmail.com>
 * @package MediaWiki
 * @subpackage Extensions
 * @copyright © 2006 Rob Church
 * @licence GNU General Public Licence 2.0 or later
 */

if( defined( 'MEDIAWIKI' ) ) {

	require_once( 'UserMailer.php' );

	$wgExtensionFunctions[] = 'efNewUserNotifSetup';
	$wgExtensionCredits['other'][] = array( 'name' => 'New User Email Notification', 'author' => 'Rob Church', 'url' => 'http://www.mediawiki.org/wiki/New_User_Email_Notification' );

	$wgNewUserNotifSender = $wgPasswordSender;
	$wgNewUserNotifTargets[] = 1;
// WERELATE: don't empty this array!
//	$wgNewUserNotifEmailTargets = array();

	function efNewUserNotifSetup() {
		global $wgHooks, $wgMessageCache;
		$wgHooks['AddNewAccount'][] = 'efNewUserNotif';
		$wgMessageCache->addMessage( 'newusernotifsubj', 'New User Notification for $1' );
		$wgMessageCache->addMessage( 'newusernotifbody', "Hello $1,\n\nA new user account, $2, has been created on $3 at $4." );
	}

	function efNewUserNotif( $user = NULL ) {
		global $wgUser;
		# Backwards-compatible fiddling
		if( is_null( $user ) )
			$user =& $wgUser;
		$notifier = new NewUserNotifier();
		$notifier->execute( $user );
	}

	class NewUserNotifier {

		var $sender;
		var $user;

		/**
		 * Constructor
		 * Set up the sender
		 */
		function NewUserNotifier() {
			global $wgNewUserNotifSender;
			$this->sender = $wgNewUserNotifSender;
		}

		/**
		 * Format an email address in a backwards-compatible fashion
		 * 1.6 introduced a wrapper for it, but this isn't helpful for 1.5 installs
		 *
		 * @param $address Email address
		 * @return mixed
		 */
		function mailAddress( $address ) {
			return class_exists( 'MailAddress' ) ? new MailAddress( $address ) : $address;
		}

		/**
		 * Send all email notifications
		 *
		 * @param $user User that was created
		 */
		function execute( &$user ) {
			$this->user =& $user;
			$this->sendExternalMails();
			$this->sendInternalMails();
		}

		/**
		 * Return the subject for notification emails
		 *
		 * @return string
		 */
		function makeSubject() {
			global $wgSitename;
			static $subject = false;
			if( !$subject )
				$subject = wfMsgForContent( 'newusernotifsubj', $wgSitename );
			return $subject;
		}

		/**
		 * Return the text of a notification email
		 *
		 * @param $recipient Name of the recipient
		 * @param $user User that was created
		 */
		function makeMessage( $recipient, &$user ) {
			global $wgSitename;
// WERELATE added talk page url parameter
			return wfMsgForContent( 'newusernotifbody', $recipient, $user->getName(), $wgSitename, $this->makeTimestamp(), $user->getTalkPage()->getFullURL('action=edit') );
		}

		/**
		 * Return an appropriate timestamp
		 *
		 * @param $lang Language object
		 * @return string
		 */
		function makeTimestamp() {
			global $wgContLang;
			return $wgContLang->timeAndDate( wfTimestampNow() );
		}

		/**
		 * Send email to external addresses
		 */
		function sendExternalMails() {
			global $wgNewUserNotifEmailTargets, $wgContLang;
			foreach( $wgNewUserNotifEmailTargets as $target ) {
				$message = $this->makeMessage( $target, $this->user );
				userMailer( $this->mailAddress( $target ), $this->mailAddress( $this->sender ), $this->makeSubject(), $message );
			}
		}

		/**
		 * Send email to users
		 */
		function sendInternalMails() {
			global $wgNewUserNotifTargets;
// WERELATE just send to one of the internal emails from MediaWiki:newuseremailtargets
			$newUserEmailTargets = wfMsg('newuseremailtargets');
			$users = explode("\n", $newUserEmailTargets);
         $sent = false;
         $tries = 0;
         while (count($users) > 0 && !$sent && $tries <= 10) {
            $tries += 1;
            $userSpec = $users[rand(0, count($users)-1)];
//			foreach( $wgNewUserNotifTargets as $userSpec ) {
				if( $user =& $this->makeUser( $userSpec ) ) {
					$message = $this->makeMessage( $user->getName(), $this->user );
					if( $user->isEmailConfirmed() ) {
						$user->sendMail( $this->makeSubject(), $message, $this->sender );
						$sent = true;
					}
				}
			}
		}

		/**
		 * Initialise a user from an identifier or a username
		 *
		 * @param $spec User identifier or name
		 * @return mixed
		 */
		function makeUser( $spec ) {
			$name = is_integer( $spec ) ? User::whoIs( $spec ) : $spec;
			$user = User::newFromName( $name );
			if( is_object( $user ) ) {
				$user->loadFromDatabase();
				if( $user->getId() > 0 )
					return $user;
			}
			return false;
		}

	}

} else {
	echo( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
	die( 1 );
}


?>
