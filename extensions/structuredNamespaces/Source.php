<?php
/**
 * @package MediaWiki
 * @subpackage StructuredNamespaces
 */
require_once("$IP/extensions/structuredNamespaces/StructuredData.php");

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfSourceExtensionSetup";

/**
 * Callback function for $wgExtensionFunctions; sets up extension
 */
function wfSourceExtensionSetup() {
   global $wgHooks;
   global $wgParser;

   # Register hooks for edit UI, request handling, and save features
   $wgHooks['ArticleEditShow'][] = 'renderSourceEditFields';
   $wgHooks['ImportEditFormDataComplete'][] = 'importSourceEditData';
   $wgHooks['EditFilter'][] = 'validateSource';
   $wgHooks['ArticleSave'][] = 'updateSourceWLH';
	$wgHooks['TitleMoveComplete'][] = 'propagateSourceMove';

   # register the extension with the WikiText parser
   $wgParser->setHook('source', 'renderSourceData');
}

/**
 * Callback function for converting source to HTML output
 */
function renderSourceData( $input, $argv, $parser) {
   $source = new Source($parser->getTitle()->getText());
   return $source->renderData($input, $parser);
}

/**
 * Callback function for rendering edit fields
 * @return bool must return true or other hooks don't get called
 */
function renderSourceEditFields( &$editPage ) {
   $ns = $editPage->mTitle->getNamespace();
   if ($ns == NS_SOURCE) {
      $source = new Source($editPage->mTitle->getText());
      $source->renderEditFields($editPage);
   }
   return true;
}

/**
 * Callback function for importing data from edit fields
 * @return bool must return true or other hooks don't get called
 */
function importSourceEditData( &$editPage, &$request ) {
   $ns = $editPage->mTitle->getNamespace();
   if ($ns == NS_SOURCE) {
      $source = new Source($editPage->mTitle->getText());
      $source->importEditData($editPage, $request);
   }
   return true;
}

/**
 * Callback function to validate data
 * @return bool must return true or other hooks don't get called
 */
function validateSource($editPage, $textBox1, $section, &$hookError) {
   $ns = $editPage->mTitle->getNamespace();
   if ($ns == NS_SOURCE) {
      $source = new Source($editPage->mTitle->getText());
      $source->validate($textBox1, $section, $hookError);
   }
   return true;
}

/**
 * Callback function to update what-links-here
 * @return bool must return true or other hooks don't get called
 */
function updateSourceWLH(&$article, &$user, &$text, &$summary, $minor, $dummy1, $dummy2, &$flags) {
   $ns = $article->getTitle()->getNamespace();
   if ($ns == NS_SOURCE) {
      // update people and families that link to this source, because source citation could have changed
      $u = new HTMLCacheUpdate( $article->getTitle(), 'pagelinks' );
      $u->doUpdate();
	}
	return true;
}

/**
 * Callback function to copy page links and propagate data
 * @return bool must return true or other hooks don't get called
 */
function propagateSourceMove(&$title, &$newTitle, &$user, $pageId, $redirPageId) {
	$ns = $title->getNamespace();
	if ($ns == NS_SOURCE) {
      StructuredData::copyPageLinks($title, $newTitle);
		$source = new Source($title->getText());
		$source->propagateMove($newTitle);
	}
	return true;
}

/**
 * Handles sources
 */
class Source extends StructuredData {
	private $correctedPlaceTitles;
	
	public static $SOURCE_TYPE_OPTIONS = array(
		'Article' => 'Article',
		'Book' => 'Book',
		'Government / Church records' => 'Government / Church records',
		'Newspaper' => 'Newspaper',
		'Periodical' => 'Periodical',
      'Website' => 'Website',
		'Miscellaneous / Unknown' => 'Miscellaneous',
		'Should be a MySource' => 'MySource'
	);

	public static $ADD_SOURCE_TYPE_OPTIONS = array(
		'Article' => 'Article',
		'Book' => 'Book',
		'Government / Church records' => 'Government / Church records',
		'Newspaper' => 'Newspaper',
		'Periodical' => 'Periodical',
      'Website' => 'Website',
		'Miscellaneous / Unknown' => 'Miscellaneous'
	);

	public static $SOURCE_AVAILABILITY_OPTIONS = array(
		'Free website',
		'Paid website',
		'Family history center',
      'Archive/Library',
		'Other'
	);

	public static $SOURCE_SUBJECT_OPTIONS = array(
         'Biography' => 'Biography',
         'Cemetery records' => 'Cemetery records',
         'Census records' => 'Census records',
         'Church records' => 'Church records',
         'Deed/Land records' => 'Deed/Land records',
         'Directory records' => 'Directory records',
         'Ethnic/Cultural' => 'Ethnic/Cultural',
         'Family bible' => 'Family bible',
         'Family tree / history' => 'Family tree',
         'Finding aid' => 'Finding aid',
         'History' => 'History',
         'Institutional records' => 'Institutional records',
         'Legal/Court records' => 'Legal/Court records',
         'Manuscripts/Documents' => 'Manuscripts/Documents',
         'Maps/Gazetteers' => 'Maps/Gazetteers',
         'Migration records' => 'Migration records',
         'Military records' => 'Military records',
         'Newspaper (article)' => 'Newspaper article',
         'Obituaries' => 'Obituaries',
         'Occupation' => 'Occupation',
         'Passenger/Immigration records' => 'Passenger/Immigration records',
         'Periodical (article)' => 'Periodical article',
         'Photograph collection' => 'Photograph collection',
         'Tax records' => 'Tax records',
         'Vital records' => 'Vital records',
         'Voter records' => 'Voter records',
         'Will/Probate records' => 'Will/Probate records'
	);
	
	public static $ETHNICITY_OPTIONS = array(
             'African' => 'African',
             'African American' => 'African American',
             'Armenian' => 'Armenian',
             'Australian' => 'Australian',
             'Canadian' => 'Canadian',
             'Chinese' => 'Chinese',
             'Costa Rican' => 'Costa Rican',
             'Cuban' => 'Cuban',
             'Czech' => 'Czech',
             'Danish' => 'Danish',
             'Dutch' => 'Dutch',
             'Ecuadorian' => 'Ecuadorian',
             'El Salvadoran' => 'El Salvadoran',
             'Egyptian' => 'Egyptian',
             'English' => 'English',
             'Estonian' => 'Estonian',
             'Filipino' => 'Filipino',
             'Finnish' => 'Finnish',
             'Flemish' => 'Flemish',
             'French' => 'French',
             'French Canadian' => 'French Canadian',
             'German' => 'German',
             'Greek' => 'Greek',
             'Guatemalan' => 'Guatemalan',
             'Haitian' => 'Haitian',
             'Hawaiian' => 'Hawaiian',
             'Hispanic' => 'Hispanic',
             'Honduran' => 'Honduran',
             'Icelander' => 'Icelander',
             'Indian' => 'Indian',
             'Iranian' => 'Iranian',
             'Iraqi' => 'Iraqi',
             'Irish' => 'Irish',
             'Italian' => 'Italian',
             'Jamaican' => 'Jamaican',
             'Japanese' => 'Japanese',
             'Jewish' => 'Jewish',
             'Korean' => 'Korean',
             'Latvian' => 'Latvian',
             'Lithuanian' => 'Lithuanian',
             'Mexican' => 'Mexican',
             'Native American (American Indian)' => 'Native American',
             'Newfoundlander' => 'Newfoundlander',
             'New Zealander' => 'New Zealander',
             'Nicaraguan' => 'Nicaraguan',
             'Norwegian' => 'Norwegian',
             'Palestinian' => 'Palestinian',
             'Panamanian' => 'Panamanian',
             'Peruvian' => 'Peruvian',
             'Polish' => 'Polish',
             'Portuguese' => 'Portuguese',
             'Puerto Rican' => 'Puerto Rican',
             'Romanian' => 'Romanian',
             'Russian' => 'Russian',
             'Scandinavian' => 'Scandinavian',
             'Scottish' => 'Scottish',
             'Serbian' => 'Serbian',
             'Slovakian' => 'Slovakian',
             'South African' => 'South African',
             'Spanish' => 'Spanish',
             'Spanish American' => 'Spanish American',
             'Swedish' => 'Swedish',
             'Swiss' => 'Swiss',
             'Syrian' => 'Syrian',
             'Ukrainian' => 'Ukrainian',
             'Uruguayan' => 'Uruguayan',
             'Venezuelan' => 'Venezuelan',
             'Welsh' => 'Welsh',
             'Yugoslavian' => 'Yugoslavian'
	);
	
	public static $RELIGION_OPTIONS = array(
             'Adventist' => 'Adventist',
             'African Methodist Episcopal Church' => 'African Methodist Episcopal Church',
             'Albanian Orthodox' => 'Albanian Orthodox',
             'Amish Mennonite' => 'Amish Mennonite',
             'Anabaptist' => 'Anabaptist',
             'Anglican/Church of England' => 'Anglican/Church of England',
             'Apostolic' => 'Apostolic',
             'Armenian Apostolic' => 'Armenian Apostolic',
             'Armenian Orthodox' => 'Armenian Orthodox',
             'Assembly of God' => 'Assembly of God',
             'Atheism' => 'Atheism',
             'Baptist' => 'Baptist',
             'Brethren' => 'Brethren',
             'Buddhism' => 'Buddhism',
             'Bulgarian Orthodox' => 'Bulgarian Orthodox',
             'Calvinist' => 'Calvinist',
             'Catholic (Roman Catholic)' => 'Catholic',
             'Christian Scientist' => 'Christian Scientist',
             'Church of Christ' => 'Church of Christ',
             'Church of God' => 'Church of God',
             'Church of Jesus Christ of Latter-day Saints' => 'Church of Jesus Christ of Latter-day Saints',
             'Church of the Nazarene' => 'Church of the Nazarene',
             'Colored Methodist Episcopal Church' => 'Colored Methodist Episcopal Church',
             'Congregationalist' => 'Congregationalist',
             'Coptic' => 'Coptic',
             'Deist' => 'Deist',
             'Disciples of Christ' => 'Disciples of Christ',
             'Dutch Reformed' => 'Dutch Reformed',
             'Eastern Orthodox' => 'Eastern Orthodox',
             'Episcopalian' => 'Episcopalian',
             'Evangelical' => 'Evangelical',
             'Evangelical Baptist' => 'Evangelical Baptist',
             'Evangelical Lutheran' => 'Evangelical Lutheran',
             'Evangelical Mennonite' => 'Evangelical Mennonite',
             'Free Wesleyan Church' => 'Free Wesleyan Church',
             'Fundalmentalist Christian' => 'Fundalmentalist Christian',
             'German Baptist' => 'German Baptist',
             'German Reformed' => 'German Reformed',
             'Gnostics' => 'Gnostics',
             'Greek Orthodox' => 'Greek Orthodox',
             'Gypsies (Roma)' => 'Gypsies',
             'Hinduism' => 'Hinduism',
             'Islam' => 'Islam',
             'Jehovah\'s Witnesses' => 'Jehovah\'s Witnesses',
             'Judaism' => 'Judaism',
             'Judaism Orthodox' => 'Judaism Orthodox',
             'Judaism Reform' => 'Judaism Reform',
             'Lutheran' => 'Lutheran',
             'Macedonian Orthodox' => 'Macedonian Orthodox',
             'Mennonite' => 'Mennonite',
             'Mennonite Brethren' => 'Mennonite Brethren',
             'Methodist' => 'Methodist',
             'Missionary Church of God' => 'Missionary Church of God',
             'Moravian' => 'Moravian',
             'Muslim' => 'Muslim',
             'Nazarene' => 'Nazarene',
             'Paganism' => 'Paganism',
             'Pentecostal' => 'Pentecostal',
             'Presbyterian' => 'Presbyterian',
             'Primitive Baptist' => 'Primitive Baptist',
             'Quaker (Society of Friends)' => 'Quaker',
             'Romanian Orthodox' => 'Romanian Orthodox',
             'Russian Orthodox' => 'Russian Orthodox',
             'Scientology' => 'Scientology',
             'Serbian Orthodox' => 'Serbian Orthodox',
             'Seventh-Day Adventist' => 'Seventh-Day Adventist',
             'Shaker' => 'Shaker',
             'Shamanism' => 'Shamanism',
             'Shintoism' => 'Shintoism',
             'Southern Baptist Convention' => 'Southern Baptist Convention',
             'Swedenborgian' => 'Swedenborgian',
             'Taoist' => 'Taoist',
             'Ukranian Orthodox' => 'Ukranian Orthodox',
             'Unitarian Universalist' => 'Unitarian Universalist',
             'United Brethren' => 'United Brethren',
             'United Church of Christ' => 'United Church of Christ',
             'Zionist Christian' => 'Zionist Christian'
	);

	public static $OCCUPATION_OPTIONS = array(
             'Accountant' => 'Accountant',
             'Agent' => 'Agent',
             'Ambassador' => 'Ambassador',
             'Apprentice' => 'Apprentice',
             'Artist' => 'Artist',
             'Astronomer' => 'Astronomer',
             'Athlete' => 'Athlete',
             'Baker' => 'Baker',
             'Banker' => 'Banker',
             'Barber' => 'Barber',
             'Bartender' => 'Bartender',
             'Beekeeper' => 'Beekeeper',
             'Bellman' => 'Bellman',
             'Blacksmith' => 'Blacksmith',
             'Boatman' => 'Boatman',
             'Boilermaker' => 'Boilermaker',
             'Brewer' => 'Brewer',
             'Bricklayer (Stone Mason, Stonecutter)' => 'Bricklayer',
             'Businessman' => 'Businessman',
             'Cabbie' => 'Cabbie',
             'Carpenter' => 'Carpenter',
             'Carriage Driver' => 'Carriage Driver',
             'Cartographer (Map Maker)' => 'Cartographer',
             'Cartwright' => 'Cartwright',
             'Cattle Hunter' => 'Cattle Hunter',
             'Cleaner' => 'Cleaner',
             'Clerk' => 'Clerk',
             'Clothier' => 'Clothier',
             'Cook' => 'Cook',
             'Cooper (Cask Maker, Barrel Maker)' => 'Cooper',
             'Coroner' => 'Coroner',
             'Councilman' => 'Councilman',
             'Cowboy' => 'Cowboy',
             'Craftsman' => 'Craftsman',
             'Criminal' => 'Criminal',
             'Dairyman' => 'Dairyman',
             'Dentist' => 'Dentist',
             'Doctor (Physician)' => 'Doctor',
             'Elected Official' => 'Elected Official',
             'Electrician' => 'Electrician',
             'Engineer' => 'Engineer',
             'Envoy' => 'Envoy',
             'Factory Worker' => 'Factory Worker',
             'Farmer (Farm hand, Gardener, Yeoman)' => 'Farmer',
             'Ferryman' => 'Ferryman',
             'Fireman' => 'Fireman',
             'Fisherman' => 'Fisherman',
             'Furrier' => 'Furrier',
             'Genealogist' => 'Genealogist',
             'Governor' => 'Governor',
             'Grocer' => 'Grocer',
             'Groom' => 'Groom',
             'Guard (Watchman)' => 'Guard',
             'Historian' => 'Historian',
             'Homemaker (Keeping House)' => 'Homemaker',
             'Hunter' => 'Hunter',
             'Iceman' => 'Iceman',
             'Innkeeper' => 'Innkeeper',
             'Inspector' => 'Inspector',
             'Jailor' => 'Jailor',
             'Janitor' => 'Janitor',
             'Jeweler' => 'Jeweler',
             'Judge' => 'Judge',
             'Laborer (Handyman, Factory Worker)' => 'Laborer',
             'Laundress' => 'Laundress',
             'Lawyer (Attorney)' => 'Lawyer',
             'Librarian' => 'Librarian',
             'Livestock Minder' => 'Livestock Minder',
             'Locksmith' => 'Locksmith',
             'Lumberjack (Lumberman)' => 'Lumberjack',
             'Magician' => 'Magician',
             'Magistrate' => 'Magistrate',
             'Maid' => 'Maid',
             'Manager' => 'Manager',
             'Mayor' => 'Mayor',
             'Mechanic' => 'Mechanic',
             'Merchant' => 'Merchant',
             'Messenger' => 'Messenger',
             'Midwife' => 'Midwife',
             'Miller' => 'Miller',
             'Miner' => 'Miner',
             'Minister (Priest, Rabbi, Religious)' => 'Minister',
             'Mortician (Undertaker)' => 'Mortician',
             'Musician' => 'Musician',
             'Notary' => 'Notary',
             'Nurse' => 'Nurse',
             'Official (elected)' => 'Official',
             'Overseer (Driver)' => 'Overseer',
             'Painter' => 'Painter',
             'Peddler' => 'Peddler',
             'Pharmacist (Druggist, Apothecary)' => 'Pharmacist',
             'Pilot' => 'Pilot',
             'Plumber' => 'Plumber',
             'Policeman (Constable, Sheriff)' => 'Policeman',
             'Politician' => 'Politician',
             'Porter (for homes, hotels, railroads)' => 'Porter',
             'Postal Worker (Mailman, Postman)' => 'Postal Worker',
             'Programmer' => 'Programmer',
             'Quilter' => 'Quilter',
             'Railroader (Conductor, RR Fireman)' => 'Railroader',
             'Rancher' => 'Rancher',
             'Realtor' => 'Realtor',
             'Record Keeper' => 'Record Keeper',
             'Reporter' => 'Reporter',
             'Researcher' => 'Researcher',
             'Retailer' => 'Retailer',
             'Sailor' => 'Sailor',
             'Salesman' => 'Salesman',
             'Scientist' => 'Scientist',
             'Seamstress (Dressmaker)' => 'Seamstress',
             'Secretary' => 'Secretary',
             'Servant' => 'Servant',
             'Sharecropper' => 'Sharecropper',
             'Shepherd' => 'Shepherd',
             'Shoemaker (Cobbler)' => 'Shoemaker',
             'Singer' => 'Singer',
             'Soldier' => 'Soldier',
             'Spinner' => 'Spinner',
             'Spy' => 'Spy',
             'Store Owner' => 'Store Owner',
             'Student' => 'Student',
             'Superindentent' => 'Superindentent',
             'Surveyor' => 'Surveyor',
             'Tailor' => 'Tailor',
             'Tanner' => 'Tanner',
             'Teacher (Educator, Professor)' => 'Teacher',
             'Technician' => 'Technician',
             'Telegraph Operator' => 'Telegraph Operator',
             'Trapper' => 'Trapper',
             'Trunk Minder' => 'Trunk Minder',
             'Vendor (Huckster)' => 'Vendor',
             'Veterinarian' => 'Veterinarian',
             'Wagoner' => 'Wagoner',
             'Waiter' => 'Waiter',
             'Warden (Jail Keeper)' => 'Warden',
             'Weaver' => 'Weaver',
             'Writer (Novelist, Poet)' => 'Writer'
	);

	  /**
     * Construct a new source object
     */
   public function __construct($titleString) {
      parent::__construct('source', $titleString, NS_SOURCE);
   }

//   protected function formatAltName($value, $dummy) {
//      return $value;
//   }

	protected function formatPlain($value, $dummy) {
		return (string)$value;
	}
	
   protected function formatUrl($url) {
   	if ($url) {
	      if (strlen($url) > 50) {
	         $urlShow = substr($url, 0, 48) . '..';
	     	}
	     	else {
	     		$urlShow = $url;
	     	}
	      return "[$url $urlShow]";
   	}
   	return '';
   }
   
   protected function formatRepository($value, $dummy) {
   	$title = (string)$value['title'];
   	$sourceLocation = (string)$value['source_location'];
   	$availability = (string)$value['availability'];
   	if (StructuredData::isValidUrl($sourceLocation)) {
   		$sourceLocation = $this->formatUrl($sourceLocation);
   	}
   	$result = '';
   	if ($title) {
   		$result = "<dt>[[Repository:$title|$title]]";
   		if ($sourceLocation) {
   			$result .= "<dd>$sourceLocation";
   		}
   	}
   	else if ($sourceLocation) {
   		$result = "<dt>$sourceLocation";
   	}
		if ($availability) {
			$result .= "<dd>$availability";
		}
   	return $result;
   }

   protected function showField($label, $node, &$hideTop) {
   	$value = (string)$node;
   	if ($value) {
   		$result = $this->addValueToTableDL($label, $value, $hideTop);
   		$hideTop = true;
   		return $result;
   	}
   	return "";
   }
   
   protected function showFields($label, $node, $formatFunction, $formatParam, &$hideTop) {
   	if (isset($node)) {
      	$result = $this->addValuesToTableDL($label, $node, $formatFunction, $formatParam, $hideTop);
   		$hideTop = true;
   		return $result;
   	}
   	return "";
   }
   
   protected function addCategory($catNode) {
   	$category = (string)$catNode;
   	if ($category) {
   		return "[[Category:$category]]";
   	}
   	return '';
   }

   protected function formatAsLink($value, $nameSpace) {
      $valueString = (string)$value;
      if ($valueString) {
     		$fields = explode('|', $valueString);
     		if (count($fields) == 1) {
     			$fields[1] = $fields[0];
     		}
         return "[[$nameSpace:{$fields[0]}|{$fields[1]}]]";
      }
      return '';
   }

   private function getTitleAuthorValue($value, $titleAuthor) {
      $value = htmlspecialchars($value);
      if ($titleAuthor) {
         return "<span class=\"wr-infobox-title\">$value</span>";
      }
      return $value;
   }

   protected function getLV($label, $value, $titleAuthor=false, $value2=null, $linkNamespace=null, $delimiter='<br>') {
      $values = '';
      if (is_string($value)) {
         $values .= $this->getTitleAuthorValue($value, $titleAuthor);
      }
      else {
         foreach ($value as $v) {
            if ($values) $values .= $delimiter;
            $v = str_replace("\n", $delimiter, (string)$v);
            if ($linkNamespace) {
               $v = $this->formatAsLink($v, $linkNamespace);
            }
            $values .= $this->getTitleAuthorValue($v, $titleAuthor);
            $titleAuthor = false;
         }
      }
      if (isset($value2) && (string)$value2) {
         $values .= ' - '.(string)$value2;
      }
      if (!$values) return '';
      return <<<END
<tr>
   <td class="wr-infobox-label">$label</td>
   <td>$values</td>
</tr>
END;
   }

   protected function sortXml($values) {
      $a = array();
      foreach ($values as $v) {
         $a[] = (string)$v;
      }
      sort($a);
      return $a;
   }

   protected function getSourceTitle() {
      if (isset($this->xml->source_title)) {
         return (string)$this->xml->source_title;
      }
      return $this->titleString;
   }

   protected function getTitleAuthor() {
      $result = '';
      $result .= $this->getLV("Source", $this->getSourceTitle(), true);
      $result .= $this->getLV("", $this->xml->subtitle, true);
      $result .= $this->getLV("Author", $this->xml->author, true);
      if (!$result) return '';
      return <<<END
<table>
$result
</table>
END;
   }

   protected function getCoverage() {
      $result = '';
      $type = (string)$this->xml->source_type;
      $highlight = false;
      if ($type == 'Government / Church records' || $type == 'Newspaper') {
         $highlight = true;
         foreach ($this->xml->author as $a) {
            $highlight = false;
            break;
         }
      }
      $result .= $this->getLV("Place", $this->xml->place, $highlight, null, "Place");
      $result .= $this->getLV("Year range", $this->xml->from_year, false,
                              ((string)$this->xml->from_year && !(string)$this->xml->to_year ? " " : $this->xml->to_year));
      $result .= $this->getLV("Surname", $this->sortXml($this->xml->surname), false, null, null, ', ');
      $result .= $this->getLV("Subject", $this->xml->subject, false, null, null, ', ');
      $result .= $this->getLV("Ethnicity / Culture", $this->xml->ethnicity);
      $result .= $this->getLV("Religion", $this->xml->religion);
      $result .= $this->getLV("Occupation", $this->xml->occupation);
      if (!$result) return '';
      return <<<END
<div class="wr-infobox-heading">Coverage</div>
<table>
$result
</table>
END;
   }

   protected function getPublicationInfo() {
      $result = '';
      $result .= $this->getLV("Type", $this->xml->source_type);
      $result .= $this->getLV("Publisher", $this->xml->publisher);
      $result .= $this->getLV("Date issued", $this->xml->date_issued);
      $result .= $this->getLV("Place issued", $this->xml->place_issued);
      $result .= $this->getLV("Periodical / Series name", $this->xml->series_name);
      $result .= $this->getLV("Number of Volumes", $this->xml->volumes);
      $result .= $this->getLV("Volume / Film# / Pages", $this->xml->pages);
      $result .= $this->getLV("References / Cites", $this->xml->references);
      if (!$result) return '';
      return <<<END
<div class="wr-infobox-heading">Publication information</div>
<table>
$result
</table>
END;
   }

   public function getCitationText($makeLink=false, $altTitle=null) {
      $result = '';
      $type = '';
      if (isset($this->xml->source_type)) {
         $type = (string)$this->xml->source_type;
      }
      if (isset($this->xml->author)) {
         $authors = array();
         foreach ($this->xml->author as $author) {
            $authorString = (string)$author;
            if (count($authors) > 0) {
               $pos = mb_strpos($authorString, ',');
               if ($pos !== false) {
                  $authorString = trim(mb_substr($authorString, $pos+1)).' '.trim(mb_substr($authorString, 0, $pos));
               }
            }
            $authors[] = trim($authorString);
         }
         if (count($authors) == 1) {
            $result .= $authors[0];
         }
         else if (count($authors) == 2) {
            $result .= StructuredData::chomp($authors[0],',').', and '.$authors[1];
         }
         else if (count($authors) > 2) {
            for ($i = 0; $i < count($authors); $i++) {
               if ($i == count($authors)-1) {
                  $result = StructuredData::chomp($result,';').'; and ';
               }
               else if ($i > 0) {
                  $result = StructuredData::chomp($result,';').'; ';
               }
               $result .= $authors[$i];
            }
         }
      }
      else if ($type == 'Government / Church records' || $type == 'Newspaper') {
         foreach ($this->xml->place as $place) {
            $p = (string)$place;
            $pos = mb_strpos($p, '|');
            if ($pos !== false) {
               $p = mb_substr($p, $pos+1);
            }
            $result .= $p;
            break;
         }
      }
      if (isset($this->xml->source_title)) {
         $sourceTitle = StructuredData::chomp((string)$this->xml->source_title,'.');
      }
      else {
         $sourceTitle = StructuredData::chomp($this->titleString,'.');
      }
      if ($result) $result = StructuredData::chomp($result,'.').'. ';
      $result .= "<i>$sourceTitle</i>";
      if (isset($this->xml->subtitle)) {
         $result .= ': <i>'.StructuredData::chomp((string)$this->xml->subtitle,'.').'</i>';
      }
      if ($makeLink) {
         $result = preg_replace("/\[\[(.+?\|(.+?)|([^\|]+?))\]\]/", "$2$3", $result);
         $result = "[[Source:{$this->titleString}|$result]]";
      }
      if ($type == 'Article' && isset($this->xml->series_name)) {
         $result = StructuredData::chomp($result,'.').'. '.(string)$this->xml->series_name;
      }
//      if (isset($this->xml->references)) {
//         $result = StructuredData::chomp($result,'.').'. References '.(string)$this->xml->references;
//      }
//      if (isset($this->xml->volumes)) {
//         $result = StructuredData::chomp($result,'.').'. '.(string)$this->xml->volumes.' Volumes';
//      }
      if (isset($this->xml->place_issued) || isset($this->xml->publisher) || isset($this->xml->date_issued)) {
         $pubInfo = '';
         if (isset($this->xml->place_issued)) {
            $pubInfo .= (string)$this->xml->place_issued;
         }
         if (isset($this->xml->publisher)) {
            if ($pubInfo) $pubInfo = StructuredData::chomp($pubInfo,':').': ';
            $pubInfo .= (string)$this->xml->publisher;
         }
         if (isset($this->xml->date_issued)) {
            if ($pubInfo) $pubInfo = StructuredData::chomp($pubInfo,',').', ';
            $pubInfo .= (string)$this->xml->date_issued;
         }
         $result = StructuredData::chomp($result,'.').". ($pubInfo)";
      }
//      if (isset($this->xml->pages)) {
//         $result = StructuredData::chomp($result,',').', '.(string)$this->xml->pages;
//      }
      return StructuredData::chomp($result,'.');
   }

   protected function getCitation() {
      $result = $this->getCitationText();
      if (!$result) return '';
      return <<<END
<div class="wr-infobox-heading">Citation</div>
<table><tr><td>$result.</td></tr></table>
END;
   }

   protected function getRepos() {
      $result = '';
      foreach ($this->xml->repository as $repo) {
         $title = (string)$repo['title'];
         if ($title) {
            $title = "[[Repository:$title|$title]]";
         }
         $sourceLocation = (string)$repo['source_location'];
         if (StructuredData::isValidUrl($sourceLocation)) {
            if (stripos($sourceLocation, 'familysearch.org/eng/library/fhlcatalog') !== false) {
               $sourceLocation = strtolower($sourceLocation);
            }
            $sourceLocation = $this->formatUrl($sourceLocation);
         }
         $avail = (string)$repo['availability'];
         $result .= "<tr><td>$title</td><td>$sourceLocation</td><td>$avail</td></tr>";
      }
      if (!$result) return '';
      return <<<END
<div class="wr-infobox-heading">Repositories</div>
<table>
$result
</table>
END;
   }

   private function cleanSearchString($s) {
      $s = preg_replace('/[`~!@#%^&*()_+\-={}|:\'<>?;,\/"\[\]\.\\\\]/', ' ', $s);
      $s = preg_replace('/\\s+/', ' ', $s);
      return urlencode($s);
   }

   /**
	 * Create wiki text from xml property
	 */
   protected function toWikiText($parser) {
      $result = '';
      if (isset($this->xml)) {
         // add infobox
         $titleAuthor = $this->getTitleAuthor();
         $coverage = $this->getCoverage();
         $publicationInfo = $this->getPublicationInfo();
         $citation = $this->getCitation();
         $repos = $this->getRepos();
         $searchString = $this->cleanSearchString($this->xml->author.' '.$this->getSourceTitle());
         $searchGoogle = "<div class=\"sourcesource\">[http://www.google.com/search?btnG=Search+Books&tbm=bks&tbo=1&q=$searchString search google books]</div>";
         $searchWorldcat = "<div class=\"sourcesource\">[http://www.worldcat.org/search?q=$searchString search worldcat]</div>";

         $infobox = <<<END
<div class="wr-infobox wr-infobox-source">
$searchGoogle
$searchWorldcat
$titleAuthor
$coverage
$publicationInfo
$citation
$repos
</div>
END;
			$result = $infobox.StructuredData::addCategories($this->xml->surname, $this->xml->place);
      }
      return $result;
   }
   
   private function addRepositoryInput($i, $title, $sourceLocation, $availability, $availabilityStyle) {
   	// temporary
   	if ((!$title || !$availability) && stripos($sourceLocation, 'www.familysearch.org/Eng/Library/fhlcatalog') !== false) {
   		$availabilityStyle = '';
   		if (!$title) $title = 'Family History Center';
   		if (!$availability) $availability = 'Family history center';
   	}
   	
	   return "<tr><td><input type=\"hidden\" name=\"repository_id$i\" value=\"". ($i+1) ."\"/></td>"
	      ."<td><input tabindex=\"1\" type=\"text\" size=20 class=\"repository_input\" name=\"repository_title$i\" value=\"$title\"/></td>"
	      ."<td><input tabindex=\"1\" type=\"text\" size=45 name=\"repository_location$i\" value=\"$sourceLocation\"/></td>"
			."<td>".StructuredData::addSelectToHtml(1, "availability$i", self::$SOURCE_AVAILABILITY_OPTIONS, $availability, $availabilityStyle)."</td>"
	      ."<td><a title='Remove this repository' href=\"javascript:void(0)\" onClick=\"removeRepository(".($i+1)."); return preventDefaultAction(event);\">remove</a></td>"
	      ."</tr>\n";
   }
   
   public static function getPageText($sourceType, $title, $author, $place, $placeIssued, $publisher) {
   	$result = "<source>\n";
   	if ($sourceType) {
   		$result .= '<source_type>'.StructuredData::escapeXml($sourceType)."</source_type>\n";
   	}
   	if ($author) {
   		$result .= '<author>'.StructuredData::escapeXml($author)."</author>\n";
   	}
   	if ($title) {
   		$result .= '<source_title>'.StructuredData::escapeXml($title)."</source_title>\n";
   	}
   	if ($place) {
   		$result .= '<place>'.StructuredData::escapeXml($place)."</place>\n";
   	}
   	if ($publisher) {
   		$result .= '<publisher>'.StructuredData::escapeXml($publisher)."</publisher>\n";
   	}
   	if ($placeIssued) {
   		$result .= '<place_issued>'.StructuredData::escapeXml($placeIssued)."</place_issued>\n";
   	}
   	$result .= "</source>\n";
   	return $result;
   }

    /**
     * Create edit fields from xml property
     */
   protected function toEditFields(&$textbox1) {
		global $wgOut, $wgScriptPath, $wgRequest;

      $result = '';
      $target = $wgRequest->getVal('target');

		// add javascript functions
		$wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/autocomplete.yui.8.js\"></script>");
//      if ($target && $target != 'AddPage') {
//         $result .= "<p><font color=red>Add any additional information you have about the source".
//                     ($target == 'gedcom' ? ' and save the page' : ', save the page, then close this window').
//                    ".</font></p>";
//      }
      $wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/jquery.multiSelect.yui.1.js\"></script>");
      $wgOut->addScript("<script type=\"text/javascript\" src=\"$wgScriptPath/source.18.js\"></script>");

		$tm = new TipManager();

		$sourceType = '';
		$authors = '';
		$sourceTitle = '';
		$subtitle = '';
		$publisher = '';
		$dateIssued = '';
		$placeIssued = '';
		$seriesName = '';
		$pages = '';
		$references = '';
		$surnames = '';
		$places = '';
		$subjects = array();
		$ethnicity = '';
		$religion = '';
		$occupation = '';
		$fromYear = '';
		$toYear = '';
		
		$invalidStyle = ' style="background-color:#fdd;"';
      $fromYearStyle = '';
      $toYearStyle= '';

		$exists = isset($this->xml);
		if (!$exists) {
      	// construct <source> text from request
			$text = Source::getPageText($wgRequest->getVal('sty'), $wgRequest->getVal('st'), $wgRequest->getVal('a'),
												 $wgRequest->getVal('p'), $wgRequest->getVal('pi'), $wgRequest->getVal('pu'));
			$this->xml = StructuredData::getXml('source', $text);
		}
		if (isset($this->xml)) {
      	$sourceType = htmlspecialchars((string)$this->xml->source_type);
         foreach ($this->xml->author as $author) {
            $authors .= htmlspecialchars((string)$author) . "\n";
         }
         $sourceTitle = htmlspecialchars((string)$this->xml->source_title);
         if (!$sourceTitle) {
         	$sourceTitle = htmlspecialchars($this->titleString);
         }
         $subtitle = htmlspecialchars((string)$this->xml->subtitle);
         $publisher = htmlspecialchars((string)$this->xml->publisher);
         if (!$publisher) {
         	$publisher = htmlspecialchars((string)$this->xml->publication_info);
         }
         $dateIssued = htmlspecialchars((string)$this->xml->date_issued);
         $placeIssued = htmlspecialchars((string)$this->xml->place_issued);
         $seriesName = htmlspecialchars((string)$this->xml->series_name);
         $volumes = htmlspecialchars((string)$this->xml->volumes);
         $pages = htmlspecialchars((string)$this->xml->pages);
         $references = htmlspecialchars((string)$this->xml->references);
         foreach ($this->xml->surname as $surname) {
            $surnames .= htmlspecialchars((string)$surname) . "\n";
         }
         foreach ($this->xml->place as $place) {
            $places.= htmlspecialchars((string)$place) . "\n";
         }
         foreach ($this->xml->subject as $subject) {
            $subjects[] = htmlspecialchars((string)$subject);
         }
         if (count($subjects) == 0 && (string)$this->xml->source_category) {
            $subjects[] = htmlspecialchars((string)$this->xml->source_category);
         }
         $ethnicity = htmlspecialchars((string)$this->xml->ethnicity);
         $religion = htmlspecialchars((string)$this->xml->religion);
         $occupation = htmlspecialchars((string)$this->xml->occupation);
         $fromYear = htmlspecialchars((string)$this->xml->from_year);
         $toYear = htmlspecialchars((string)$this->xml->to_year);

         $url = htmlspecialchars((string)$this->xml->url);                  // old
         $callNumber = htmlspecialchars((string)$this->xml->call_number);   // old

         $repoName = htmlspecialchars((string)$this->xml->repository_name); // old
         $repoAddr = htmlspecialchars((string)$this->xml->repository_addr); // old
      }

      $missingAvailability = false;
      if (isset($this->xml)) {
			foreach ($this->xml->repository as $repository) {
				// the source_location condition is temporary
	   		if (!(string)$repository['availability'] && stripos((string)$repository['source_location'], 'www.familysearch.org/Eng/Library/fhlcatalog') === false) {
					$missingAvailability = true;
				}
			}
			if ($url && stripos($url, 'www.familysearch.org/Eng/Library/fhlcatalog') === false) {
				$missingAvailability = true;
			}
		}
		if ($missingAvailability) {
			$result .= "<p><font color=red>Please select an Availability</font></p>";
		}
      
      if (!StructuredData::isValidYear($fromYear) || !StructuredData::isValidYear($toYear)) {
         if (!StructuredData::isValidYear($fromYear)) {
            $fromYearStyle = $invalidStyle;
         }
         if (!StructuredData::isValidYear($toYear)) {
            $toYearStyle = $invalidStyle;
         }
         $result .= "<p><font color=red>The year range is not valid</font></p>";
      }
      
		// display edit fields
      $result .= "<h2>Source information</h2><table>"
			. '<tr><td align=right>Type:</td><td align=left>'
			.   StructuredData::addSelectToHtml(1, 'source_type', self::$SOURCE_TYPE_OPTIONS, $sourceType, 'onChange="showSourceFields()"')
			.   $tm->addMsgTip('SourceType').'</td></tr>'
			. '<tr id="authors_row"><td align=right>Authors:<br/><font size=\"-1\"><i>one per line<br/>surname, given</i></font></td>'
			.   "<td align=left><textarea tabindex=\"1\" name=\"authors\" rows=\"3\" cols=\"60\">$authors</textarea></td></tr>"
         . "<tr id=\"source_title_row\"><td align=right>Title:</td><td align=left><input tabindex=\"1\" name=\"source_title\" value=\"$sourceTitle\" size=\"60\"/></td></tr>"
         . "<tr id=\"subtitle_row\"><td align=right>Subtitle:</td><td align=left><input tabindex=\"1\" name=\"subtitle\" value=\"$subtitle\" size=\"60\"/></td></tr>"
         . "<tr id=\"publisher_row\"><td align=right>Publisher:</td><td align=left><input tabindex=\"1\" name=\"publisher\" value=\"$publisher\" size=\"60\"/></td></tr>"
         . "<tr id=\"date_issued_row\"><td align=right>Date issued:</td><td align=left><input tabindex=\"1\" name=\"date_issued\" value=\"$dateIssued\" size=\"20\"/></td></tr>"
         . "<tr id=\"place_issued_row\"><td align=right>Place issued:</td><td align=left><input tabindex=\"1\" name=\"place_issued\" value=\"$placeIssued\" size=\"60\"/></td></tr>"
         . "<tr id=\"series_name_row\"><td align=right>Periodical/Series name:</td><td align=left><input tabindex=\"1\" class=\"source_input\" name=\"series_name\" value=\"$seriesName\" size=\"60\"/></td></tr>"
         . "<tr id=\"volumes_row\"><td align=right>Number of volumes:</td><td align=left><input tabindex=\"1\" name=\"volumes\" value=\"$volumes\" size=\"10\"/></td></tr>"
         . "<tr id=\"pages_row\"><td align=right>Volume/Film#/Pages:</td><td align=left><input tabindex=\"1\" name=\"pages\" value=\"$pages\" size=\"20\"/></td></tr>"
        	. "<tr id=\"references_row\"><td align=right>References/Cites:</td><td align=left><input tabindex=\"1\" name=\"references\" value=\"$references\" size=\"60\"/></td></tr>"
        	. "</table>";
      $result .= '<h2>Coverage</h2><table>'
      	. "<tr><td align=right>Surnames covered:<br/><i>one per line</i></td><td align=left><textarea tabindex=\"1\" name=\"surnames\" rows=\"3\" cols=\"60\">$surnames</textarea></td></tr>"
	      . "<tr><td align=right>Places covered:<br/><i>one per line</i></td><td align=left><textarea class=\"place_input\" tabindex=\"1\" name=\"places\" rows=\"3\" cols=\"60\">$places</textarea></td></tr>"
	      . "<tr><td align=right>Year range:</td><td align=left><input tabindex=\"1\" name=\"fromYear\" value=\"$fromYear\" size=\"5\"$fromYearStyle/>"
         .   "&nbsp;&nbsp;-&nbsp;<input tabindex=\"1\" name=\"toYear\" value=\"$toYear\" size=\"5\"$toYearStyle/></td></tr>"
         . "<tr><td align=right>Subject:</td><td align=left>"
        	.   StructuredData::addSelectToHtml(1, 'subject', self::$SOURCE_SUBJECT_OPTIONS, $subjects, 'multiple="multiple"')."</td></tr>"
         . "<tr id=\"ethnicity_row\"><td align=right>Ethnicity/Culture:</td><td align=left>"
        	.   StructuredData::addSelectToHtml(1, 'ethnicity', self::$ETHNICITY_OPTIONS, $ethnicity)."</td></tr>"
         . "<tr id=\"religion_row\"><td align=right>Religion:</td><td align=left>"
        	.   StructuredData::addSelectToHtml(1, 'religion', self::$RELIGION_OPTIONS, $religion)."</td></tr>"
         . "<tr id=\"occupation_row\"><td align=right>Occupation:</td><td align=left>"
        	.   StructuredData::addSelectToHtml(1, 'occupation', self::$OCCUPATION_OPTIONS, $occupation)."</td></tr>"
			. "</table>";
		$rows = '';
		$i = 0;
		if (isset($this->xml)) {
   		foreach ($this->xml->repository as $repository) {
			   $rows .= $this->addRepositoryInput($i, htmlspecialchars((string)$repository['title']), htmlspecialchars((string)$repository['source_location']), 
			   												htmlspecialchars((string)$repository['availability']), (string)$repository['availability'] ? '' : $invalidStyle);
   			$i++;
   		}
   		if ($url) {
   			$rows .= $this->addRepositoryInput($i, '', $url, '', $invalidStyle);
   			$i++;
   		}
   		if ($callNumber || $repoName) {
   			$rows .= $this->addRepositoryInput($i, $repoName, $callNumber, '', $invalidStyle);
   			$i++;
   		}
		}
		if ($i == 0) {
  			$rows .= $this->addRepositoryInput($i, '', '', '', '');
  			$i++;
		}
		$result .= '<h2>Repositories</h2>';
      $result .= '<table id="repository_table" border="0" width="500px" style="display:block">';
      $result .= '<tr><th></th><th>Repository name</th><th>Location (call#, URL) of source within repository</th><th>Availability</th></tr>';
		$result .= "$rows</table><a href=\"javascript:void(0)\" onClick=\"addRepository('".implode(',',self::$SOURCE_AVAILABILITY_OPTIONS)."'); return preventDefaultAction(event);\">Add Repository</a>";
		$result .= $tm->getTipTexts();
      $result .= "<br><br>Text:<br>";
      return $result;
   }

   protected function formatAuthor($value) {
		$escapedValue =& StructuredData::escapeXml($value);
      return "<author>$escapedValue</author>";
   }

   protected function formatSurname($value) {
      $value = StructuredData::standardizeNameCase(trim($value), false);
		$escapedValue =& StructuredData::escapeXml($value);
      return "<surname>$escapedValue</surname>";
   }

   protected function formatSubject($value) {
		$escapedValue =& StructuredData::escapeXml($value);
      return "<subject>$escapedValue</subject>";
   }

   protected function formatPlace($value) {
		$correctedPlace = @$this->correctedPlaceTitles[$value];
		if ($correctedPlace) {
			$value = strcasecmp($value,$correctedPlace) == 0 ? $correctedPlace : $correctedPlace . '|' . $value;
		}
		$escapedValue =& StructuredData::escapeXml($value);
      return "<place>$escapedValue</place>";
   }

   /**
     * Return xml elements from data in request
     * @param unknown $request
     */
   protected function fromEditFields($request) {
   	$this->correctedPlaceTitles = PlaceSearcher::correctPlaceTitlesMultiLine($request->getVal('places', ''));
      $subjects = $request->getArray('subject', array());

		$sourceType = $request->getVal('source_type', '');
      $result = 
           $this->addSingleLineFieldToXml($sourceType, 'source_type')
      	. (!$sourceType || in_array($sourceType, array('Book', 'Article', 'Government / Church records', 'Periodical', 'Manuscript collection', 'Website', 'Miscellaneous', 'MySource'))
				? $this->addMultiLineFieldToXml($request->getVal('authors', ''), 'formatAuthor') : '')
      	. $this->addSingleLineFieldToXml($request->getVal('source_title', ''), 'source_title')
      	. (!$sourceType || in_array($sourceType, array('Book', 'Article', 'Government / Church records', 'Manuscript collection', 'Website', 'Miscellaneous', 'MySource'))
      		? $this->addSingleLineFieldToXml($request->getVal('subtitle', ''), 'subtitle') : '')
      	. (!$sourceType || in_array($sourceType, array('Book', 'Article', 'Government / Church records', 'Periodical', 'Manuscript collection', 'Miscellaneous', 'MySource'))
      		? $this->addSingleLineFieldToXml($request->getVal('publisher', ''), 'publisher') : '')
      	. (!$sourceType || in_array($sourceType, array('Book', 'Article', 'Government / Church records', 'Manuscript collection', 'Miscellaneous', 'MySource'))
				? $this->addSingleLineFieldToXml($request->getVal('date_issued', ''), 'date_issued') : '')
      	. (!$sourceType || in_array($sourceType, array('Book', 'Government / Church records', 'Newspaper', 'Periodical', 'Manuscript collection', 'Miscellaneous', 'MySource'))
      		? $this->addSingleLineFieldToXml($request->getVal('place_issued', ''), 'place_issued') : '')
      	. (!$sourceType || in_array($sourceType, array('Book', 'Article', 'Government / Church records', 'Miscellaneous', 'MySource'))
      		? $this->addSingleLineFieldToXml($request->getVal('series_name', ''), 'series_name') : '')
      	. (!$sourceType || in_array($sourceType, array('Book', 'Article', 'Government / Church records', 'Miscellaneous', 'MySource'))
      		? $this->addSingleLineFieldToXml($request->getVal('volumes', ''), 'volumes') : '')
      	. (!$sourceType || in_array($sourceType, array('Article', 'Miscellaneous', 'MySource'))
      		? $this->addSingleLineFieldToXml($request->getVal('pages', ''), 'pages') : '')
      	. (!$sourceType || in_array($sourceType, array('Book', 'Article', 'Government / Church records', 'Miscellaneous', 'MySource'))
      		? $this->addSingleLineFieldToXml($request->getVal('references', ''), 'references') : '')
      	. $this->addMultiLineFieldToXml($request->getVal('surnames', ''), 'formatSurname')
			. $this->addMultiLineFieldToXml($request->getVal('places', ''), 'formatPlace')
			. $this->addSingleLineFieldToXml($request->getVal('fromYear', ''), 'from_year')
      	. $this->addSingleLineFieldToXml($request->getVal('toYear', ''), 'to_year')
			. $this->addMultiLineFieldToXml($subjects, 'formatSubject')
      	. (in_array('Ethnic/Cultural',$subjects) ? $this->addSingleLineFieldToXml($request->getVal('ethnicity', ''), 'ethnicity') : '')
      	. (in_array('Church records',$subjects) ? $this->addSingleLineFieldToXml($request->getVal('religion', ''), 'religion') : '')
      	. (in_array('Occupation',$subjects) ? $this->addSingleLineFieldToXml($request->getVal('occupation', ''), 'occupation') : '');
		for ($i = 0; $request->getVal("repository_id$i"); $i++) {
		   $title = trim($request->getVal("repository_title$i"));
		   $location = trim($request->getVal("repository_location$i"));
		   $availability = trim($request->getVal("availability$i"));
		   if ($title || $location) {
   		   $result .= $this->addMultiAttrFieldToXml(array('title' => $title, 'source_location' => $location, 'availability' => $availability), 'repository');
		   }
		}
      return $result;
   }

   /**
     * Return true if xml property is valid
     */
   protected function validateData(&$textbox1) {
      if (!StructuredData::isRedirect($textbox1)) {
			foreach ($this->xml->repository as $repository) {
				if (!(string)$repository['availability']) {
					return false;
				}
			}
         return (StructuredData::isValidYear((string)$this->xml->from_year) &&
         StructuredData::isValidYear((string)$this->xml->to_year));
      }
      return true;
   }
   
	/**
     * Propagate move, delete, or undelete to other articles if necessary
     *
     * @param String $newTitleString null in case of delete; same as this title string in case of undelete
     * @param int $newNs 0 in case of delete; tame as this title namespace in case of undelete
     * @param String $text text of article
     * @param bool $textChanged set to true if we change the text
     * @return bool true if success
     */
	protected function propagateMoveDeleteUndelete($newTitleString, $newNs, &$text, &$textChanged) {
	   if ($newNs == NS_REPOSITORY) {
	   	$repoText = "<repository>\n";
	   	if (isset($this->xml)) {
	         foreach ($this->xml->place as $place) {
	         	$repoText .= $this->addSingleLineFieldToXml($place, 'place');
	         	break;
	         }
	         $url = '';
	   		foreach ($this->xml->repository as $repository) {
	   			$url = (string)$repository['source_location'];
	   			if (StructuredData::isValidUrl($url)) {
	   				break;
	   			}
	   			else {
	   				$url = '';
	   			}
	   		}
	   		if (!$url) {
	            $url = (string)$this->xml->url;
	   		}
	   		if ($url) {
	   			$repoText .= $this->addSingleLineFieldToXml($url, 'url');
	   		}
	   	}
	   	$repoText .= "</repository>";
	   	$text = $repoText . preg_replace('$<source>.*?</source>$s', '', $text);
	   	$textChanged = true;
	   }
	   
	   return true;
	}
}
?>
