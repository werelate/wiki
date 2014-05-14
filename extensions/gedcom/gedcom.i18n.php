<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Taylor
 * Date: 3/1/14
 * Time: 8:00 AM
 * To change this template use File | Settings | File Templates.
 */

/**
 * Internationalisation file for Gedcom
 *
 * @package MediaWiki
 * @subpackage Extensions
 */

$wgGedcomMessages = array();

$wgGedcomMessages['en'] = array(
    'GedcomExportReady' => "== GEDCOM Export Ready [$1] == \n\nThe GEDCOM for tree ''$2'' is ready to download.  <span class=\"plainlinks\">[$3 Click here]</span>.",
    'nbytes' => "$1 {{PLURAL:$1|byte|bytes}}",
    'volumefilm#pages' => "volume / film# / pages $1. ",
    'invalidpagetitle' => "Invalid page title; unable to create page",
    'volumefilmpages' => "volume / film# / pages $1. ",
    'referencescities' => "references / cites: $1. ",
    'textavailableunderccbysa' => "Text content at WeRelate.org is available under the Creative Commons Attribution/Share-Alike License: http://creativecommons.org/licenses/by-sa/3.0\n",
    'addspousefamily' => "Add spouse family:",
    'ifnotrefreshing' => "If this window does not refresh automatically in a few seconds, please click on the page in the top part of your browser",
    'loadinggedcom' => "Loading ($1 gedcom)",
    //This came from $wgOut->setPageTitle("Loading ($this->gedcomkey gedcom)");
    //links with $wgOut->setPageTitle(wfMsg('loadinggedcom", $this->gedcomkey)); now
    'nolivingpeople110' => "Living people cannot be added to WeRelate. People born in the last 110 years must have a death date",
    'writedateslikethis' => " Please write dates in D MMM YYYY format so they are unambiguous (ie 5 Jan 1900)",
    'missingfieldsource' => "Required source fields are missing; please press the Back button on your browser to enter the required fields.",
    'missingfieldcountry' => "You need to fill in the country",
    'gedcom' => "gedcom",
    'findmatchingsource' => "Find a matching Source",
    //'true' => "true",
    'savepage' => "Save page",
    'saveyourchanges' => "Save your changes [alt-s]",

);

?>

