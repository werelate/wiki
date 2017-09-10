<?php
require_once("$IP/includes/Hooks.php");
require_once("$IP/includes/QueryPage.php");

/**
 * FamilyTreeQueryPage extends QueryPage.
 * It is used to collect user name and tree name.
 */
class FamilyTreeQueryPage extends QueryPage {

	public static $NAMESPACE_OPTIONS = array(
		'All' => '',
		'Article' => '0',
		'Portal' => NS_PORTAL,
		'Person' => NS_PERSON,
		'Family' => NS_FAMILY,
		'Image' => NS_IMAGE,
		'MySource' => NS_MYSOURCE,
      'Source' => NS_SOURCE,
      'Transcript' => NS_TRANSCRIPT,
		'Repository' => NS_REPOSITORY,
		'Place' => NS_PLACE,
		'User' => NS_USER,
		'Category' => NS_CATEGORY,
		'Surname' => NS_SURNAME,
		'Givenname' => NS_GIVEN_NAME,
		'Help' => NS_HELP,
		'WeRelate' => NS_PROJECT,
		'Template' => NS_TEMPLATE,
		'MediaWiki' => NS_MEDIAWIKI
	);

	protected $userName;
	protected $treeName;
	protected $allTrees;
	protected $namespace;
	
	// don't cache anything
	function isExpensive() {
		return false;
	}
	function isSyndicated() {
		return false;
	}
	
	// override to not include namespace drop-down
	function selectNamespace() {
		return true;
	}
	
	function getRequestVars($par) {
		global $wgRequest, $wgUser;
		
		$this->userName = $wgRequest->getVal('user');
		$this->treeName = $wgRequest->getVal('tree');
		$this->namespace = $wgRequest->getIntOrNull( 'namespace' );
		if ($this->namespace === 0) $this->namespace = '0';
		
		if (!$this->userName) {
			$this->treeName = '';
			if ($par) {
				$this->userName = $par;
			}
			else if ($wgUser->isLoggedIn()) {
				$this->userName = $wgUser->getName();
			}
		}

		$this->allTrees = array();
		if ($this->userName) {
		   $dbr =& wfGetDB( DB_SLAVE );
	      $sql = 'SELECT ft_name FROM familytree WHERE ft_user = '.$dbr->addQuotes($this->userName);
	      $rows = $dbr->query($sql);
  		   while ($row = $dbr->fetchObject($rows)) {
  		   	$this->allTrees[] = $row->ft_name;
  		   }
  		   $dbr->freeResult($rows);
		}
		if (count($this->allTrees) == 0) {
			$this->treeName = '';
		}
		else if (count($this->allTrees) == 1) {
			$this->treeName = $this->allTrees[0];
		}
	}

	function getPageHeader() {
		global $wgScript;
		
		$t = Title::makeTitle( NS_SPECIAL, $this->getName() );
		$submitbutton = '<input type="submit" value="' . wfMsgHtml( 'allpagessubmit' ) . "\" />\n";
		$out = "<div class='namespacesettings'><form method='get' action='{$wgScript}'>\n";
		$out .= '<input type="hidden" name="title" value="'.$t->getPrefixedText().'" />';
		$out .= '<label for="user">User name:</label> <input type="text" name="user" size="15" value="'.htmlspecialchars($this->userName).'" />';
		$out .= '&nbsp; <label for="tree">Tree name:</label>'. StructuredData::addSelectToHtml(0, 'tree', $this->allTrees, $this->treeName, '', true);
		if ($this->selectNamespace()) {
		   $namespaceselect = StructuredData::addSelectToHtml(0, 'namespace', self::$NAMESPACE_OPTIONS, $this->namespace, '', false);
			$out .= "&nbsp; <label for='namespace'> " . wfMsgHtml('namespace') . "</label> {$namespaceselect} {$submitbutton}";
		}
		$out .= '</form></div><br>';
		return $out;
	}
	
	function linkParameters() {
		$parms = array('user' => $this->userName, 'tree' => $this->treeName);
		if ($this->selectNamespace() && $this->namespace) {
			$parms['namespace'] = $this->namespace;
		}
		return $parms;
	}
	
	function doQuery($par, $offset, $limit) {
		global $wgOut;
		
		// get user name and tree name and maybe the namespace
		$this->getRequestVars($par);

		if ($this->userName && $this->treeName) {
			// call doQuery in QueryPage
			parent::doQuery($offset, $limit);
		}
		else {
			// just display a form to get user name and tree name
			$wgOut->addHTML( $this->getPageHeader() );
		}
	}
}
?>
