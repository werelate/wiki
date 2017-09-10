<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */
class TipManager {
   private $tips;

   public function __construct() {
		$this->clearTipTexts();
   }

   /**
    * Add a jTip
    *
    * @param unknown_type $tipId must be unique
    * @param unknown_type $tipTitle title of the tip
    * @param unknown_type $tipText text of the tip
    * @param unknown_type $tipPageTitle title of the page to link to in URL form
    * @param unknown_type $linkText
    * @param unknown_type $width not used
    * @return unknown
    */
	public function addTip($tipId, $tipTitle, $tipText, $tipPagePrefixedURL, $linkText, $width=250) {
		global $wgScript;

     	$tipId = str_replace(array('"',"'",'<','>','&'), '', $tipId);
	   $tipTextId = $tipId.'TipText';
      $this->tips[$tipTextId] = $tipText;
      $tipTitle = StructuredData::escapeXml($tipTitle);
		return "<a class=\"jTip\" title=\"$tipTitle\" rel=\"#{$tipTextId}\" href=\"#\">$linkText</a>";
	}

	public function addMsgTip($field, $width=250) {
	   $fieldTip = $field.'Tip';
	   $tipPageTitle = "MediaWiki:$fieldTip";
	   return $this->addTip($field, wfMsg($field), wfMsg($fieldTip), $tipPageTitle, '?', $width);
	}

	public function clearTipTexts() {
		$this->tips = array();
	}

	public function getTipTexts() {
		$result = '';
		foreach ($this->tips as $tipTextId => $tipText) {
			$result .= "<div id=\"$tipTextId\">$tipText</div>";
		}
		return $result;
	}
}
?>
