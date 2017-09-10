<?php

if ( defined( 'MEDIAWIKI' ) ) {

class SpamBlacklist {
	var $regexes = false;
	var $previousFilter = false;
	var $files = array();
	var $warningTime = 600;
	var $expiryTime = 900; 
	var $warningChance = 100;
	
	function SpamBlacklist( $settings = array() ) {
		global $IP;
		$this->files = array( "http://meta.wikimedia.org/w/index.php?title=Spam_blacklist&action=raw&sb_ver=1" );

		foreach ( $settings as $name => $value ) {
			$this->$name = $value;
		}
	}

	function getRegexes() {
		global $wgMemc, $wgDBname, $messageMemc;
		$fname = 'SpamBlacklist::getRegex';
		wfProfileIn( $fname );

		if ( $this->regexes !== false ) {
			return $this->regexes;
		}

		wfDebug( "Loading spam regex..." );
		
		if ( !is_array( $this->files ) ) {
			$this->files = array( $this->files );
		}
		if ( count( $this->files ) == 0 ){ 
			# No lists
			wfDebug( "no files specified\n" );
			wfProfileOut( $fname );
			return false;
		}

		# Refresh cache if we are saving the blacklist
		$recache = false;
		foreach ( $this->files as $fileName ) {
			if ( preg_match( '/^DB: (\w*) (.*)$/', $fileName, $matches ) ) {
				if ( $wgDBname == $matches[1] && $this->title && $this->title->getPrefixedDBkey() == $matches[2] ) {
					$recache = true;
					break;
				}
			}
		}
		
		if ( $this->regexes === false || $recache ) {
			if ( !$recache ) {
				$this->regexes = $wgMemc->get( "spam_blacklist_regexes" );
			}
			if ( $this->regexes === false || $this->regexes === null ) {
				# Load lists
				$lines = array();
				wfDebug( "Constructing spam blacklist\n" );
				foreach ( $this->files as $fileName ) {
					if ( preg_match( '/^DB: ([\w-]*) (.*)$/', $fileName, $matches ) ) {
						if ( $wgDBname == $matches[1] && $this->title && $this->title->getPrefixedDBkey() == $matches[2] ) {
							wfDebug( "Fetching default local spam blacklist...\n" );
							$lines = array_merge( $lines, explode( "\n", $this->text ) );
						} else {
							wfDebug( "Fetching local spam blackist from '{$matches[2]}' on '{$matches[1]}'...\n" );
							$lines = array_merge( $lines, $this->getArticleLines( $matches[1], $matches[2] ) );
						}
						wfDebug( "got from DB\n" );
					} elseif ( preg_match( '/^http:\/\//', $fileName ) ) {
						# HTTP request
						# To keep requests to a minimum, we save results into $messageMemc, which is
						# similar to $wgMemc except almost certain to exist. By default, it is stored
						# in the database
						#
						# There are two keys, when the warning key expires, a random thread will refresh
						# the real key. This reduces the chance of multiple requests under high traffic 
						# conditions.
						$key = "spam_blacklist_file:$fileName";
						$warningKey = "$wgDBname:spamfilewarning:$fileName";
						$httpText = $messageMemc->get( $key );
						$warning = $messageMemc->get( $warningKey );

						if ( !is_string( $httpText ) || ( !$warning && !mt_rand( 0, $this->warningChance ) ) ) {
							wfDebug( "Loading spam blacklist from $fileName\n" );
							$httpText = $this->getHTTP( $fileName );
							$messageMemc->set( $warningKey, 1, $this->warningTime );
							$messageMemc->set( $key, $httpText, $this->expiryTime );
						} else {
							wfDebug( "got from HTTP cache\n" );
						}						
						$lines = array_merge( $lines, explode( "\n", $httpText ) );
					} else {
						$lines = array_merge( $lines, file( $fileName ) );
						wfDebug( "got from file\n" );
					}
				}
				
				$this->regexes = $this->buildRegexes( $lines );
				$wgMemc->set( "spam_blacklist_regexes", $this->regexes, $this->expiryTime );
			} else {
				wfDebug( "got from cache\n" );
			}
		} 
		if( $this->regexes !== true && !is_array( $this->regexes ) ) {
			// Corrupt regex
			wfDebug( "Corrupt regex\n" );
			$this->regexes = false;
		}
		wfProfileOut( $fname );
		return $this->regexes;
	}
	
	function getWhitelists() {
		$source = wfMsgForContent( 'spam-whitelist' );
		if( $source && $source != '&lt;spam-whitelist&gt;' ) {
			return $this->buildRegexes( explode( "\n", $source ) );
		}
		// Empty
		return true;
	}
	
	function buildRegexes( $lines ) {
		# Strip comments and whitespace, then remove blanks
		$lines = array_filter( array_map( 'trim', preg_replace( '/#.*$/', '', $lines ) ) );

		# No lines, don't make a regex which will match everything
		if ( count( $lines ) == 0 ) {
			wfDebug( "No lines\n" );
			return true;
		} else {
			# Make regex
			# It's faster using the S modifier even though it will usually only be run once
			//$regex = 'http://+[a-z0-9_\-.]*(' . implode( '|', $lines ) . ')';
			//return '/' . str_replace( '/', '\/', preg_replace('|\\\*/|', '/', $regex) ) . '/Si';
			$regexes = array();
			$regexStart = '/http:\/\/+[a-z0-9_\-.]*(';
			$regexEnd = ')/Si';
			$regexMax = 20000;
			$build = false;
			foreach( $lines as $line ) {
				// FIXME: not very robust size check, but should work. :)
				if( $build === false ) {
					$build = $line;
				} elseif( strlen( $build ) + strlen( $line ) > $regexMax ) {
					$regexes[] = $regexStart .
						str_replace( '/', '\/', preg_replace('|\\\*/|', '/', $build) ) .
						$regexEnd;
					$build = $line;
				} else {
					$build .= '|';
					$build .= $line;
				}
			}
			if( $build !== false ) {
				$regexes[] = $regexStart .
					str_replace( '/', '\/', preg_replace('|\\\*/|', '/', $build) ) .
					$regexEnd;
			}
			return $regexes;
		}
	}
	
	function filter( &$title, $text, $section ) {
		global $wgArticle, $wgVersion, $wgOut, $wgParser, $wgUser;

		$fname = 'wfSpamBlacklistFilter';
		wfProfileIn( $fname );

		# Call the rest of the hook chain first
		if ( $this->previousFilter ) {
			$f = $this->previousFilter;
			if ( $f( $title, $text, $section ) ) {
				wfProfileOut( $fname );
				return true;
			}
		}

		$this->title = $title;
		$this->text = $text;
		$this->section = $section;

		$regexes = $this->getRegexes();
		$whitelists = $this->getWhitelists();
		
		if ( is_array( $regexes ) ) {
			# Run parser to strip SGML comments and such out of the markup
			# This was being used to circumvent the filter (see bug 5185)
			$options = new ParserOptions();
			$text = $wgParser->preSaveTransform( $text, $title, $wgUser, $options );
			$out = $wgParser->parse( $text, $title, $options );
			$links = implode( "\n", array_keys( $out->getExternalLinks() ) );
			
			# Strip whitelisted URLs from the match
			if( is_array( $whitelists ) ) {
				wfDebug( "Excluding whitelisted URLs from " . count( $whitelists ) .
					" regexes: " . implode( ', ', $whitelists ) . "\n" );
				foreach( $whitelists as $regex ) {
					$links = preg_replace( $regex, '', $links );
				}
			}

			# Do the match
			wfDebug( "Checking text against " . count( $regexes ) .
				" regexes: " . implode( ', ', $regexes ) . "\n" );
			$retVal = false;
			foreach( $regexes as $regex ) {
				if ( preg_match( $regex, $links, $matches ) ) {
					wfDebug( "Match!\n" );
					EditPage::spamPage( $matches[0] );
					$retVal = true;
					break;
				}
			}
		} else {
			$retVal = false;
		}

		wfProfileOut( $fname );
		return $retVal;
	}

	function getArticleLines( $db, $article ) {
		global $wgDBname;
		$dbr = wfGetDB( DB_READ );
		$dbr->selectDB( $db );
		$text = false;
		if ( $dbr->tableExists( 'page' ) ) {
			// 1.5 schema
			$dbw =& wfGetDB( DB_READ );
			$dbw->selectDB( $db );
			$revision = Revision::newFromTitle( Title::newFromText( $article ) );
			if ( $revision ) {
				$text = $revision->getText();
			}
			$dbw->selectDB( $wgDBname );
		} else {
			// 1.4 schema
			$cur = $dbr->tableName( 'cur' );
			$title = Title::newFromText( $article );
			$text = $dbr->selectField( 'cur', 'cur_text', array( 'cur_namespace' => $title->getNamespace(),
				'cur_title' => $title->getDBkey() ), 'SpamBlacklist::getArticleLines' );
		}
		$dbr->selectDB( $wgDBname );
		if ( $text !== false ) {
			return explode( "\n", $text );
		} else {
			return array();
		}
	}

	function getHTTP( $url ) {
		// Use wfGetHTTP from MW 1.5 if it is available
		include_once( 'HttpFunctions.php' );
		if ( function_exists( 'wfGetHTTP' ) ) {
			$text = wfGetHTTP( $url );
		} else {
			$url_fopen = ini_set( 'allow_url_fopen', 1 );
			$text = file_get_contents( $url );
			ini_set( 'allow_url_fopen', $url_fopen );
		}
		return $text;
	}
}

	
} # End invocation guard
?>