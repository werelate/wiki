<?php
/**
 *
 * @package MediaWiki
 * @subpackage SpecialPage
 */

# Register with MediaWiki as an extension
$wgExtensionFunctions[] = "wfDQStatsSetup";

function wfDQStatsSetup() {
	global $wgMessageCache, $wgSpecialPages;
	$wgMessageCache->addMessages( array( "dqstats" => "DQStats" ) );
	$wgSpecialPages['DQStats'] = array('SpecialPage','DQStats');
}

/**
 * Entry point
 * @param string $par Can be a user name - if so, default to watched pages
 */
function wfSpecialDQStats( $par ) {
	global $wgRequest;
	$page = new DQStats( $wgRequest );
	$page->execute( $par );
}

class DQStats {
	var $request;
	var $selfTitle, $skin;
 
  public static $MONTHS = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');                                        

	function DQStats( &$request ) {
		global $wgUser;
		$this->request =& $request;
		$this->skin =& $wgUser->getSkin();
	}

	function execute( $par ) {
		global $wgOut, $wgRequest, $wgLang, $wgUser;
		$fname = 'DQStatsPage::execute';

		$this->selfTitle = Title::makeTitleSafe( NS_SPECIAL, 'DQStats' );
		$wgOut->setPagetitle( wfMsg( 'DQstatistics' ) );
		$wgOut->setSubtitle( wfMsg( 'trackingDQprogress' ) );
   
    // Display message that data is cached, along with a link to the Data Quality Issues page 
		$dbr =& wfGetDB( DB_SLAVE );

		# Fetch the timestamp of this update
		$tRes = $dbr->select( 'querycache_info', array( 'qci_timestamp' ), array( 'qci_type' => 'AnalyzeDataQuality' ), $fname );
		$tRow = $dbr->fetchObject( $tRes );
    $dbr->freeResult( $tRes );
				
		if( $tRow ) {
		  $updated = $wgLang->timeAndDate( $tRow->qci_timestamp, true, true );
			$cacheNotice = wfMsg( 'perfcachedts', $updated );
			$wgOut->addMeta( 'Data-Cache-Time', $tRow->qci_timestamp );
			$wgOut->addScript( '<script language="JavaScript">var dataCacheTime = \'' . $tRow->qci_timestamp . '\';</script>' );
		} else {
			$cacheNotice = wfMsg( 'perfcached' );
		}
		$wgOut->addWikiText( $cacheNotice . " <span class=\"firstHeading\">See [[Special:DataQuality|Data Quality Issues]] for a list of the issues.</span>");

		$this->showStats( );
	}
 
  // Convert date from yyyy-mm-dd format to d mmm yyyy format 
  function toDisplayDate($date) {
    return (substr($date,8,1)=='0' ? substr($date,9,1) : substr($date,8,2)) . ' ' . self::$MONTHS[substr($date,5,2)-1] . ' ' . substr($date,0,4);
  }

	function showStats( ) {
		global $wgOut, $wgUser;
		$fname = 'DQStatsPage::showStats';
		$dbr =& wfGetDB( DB_SLAVE );
   
    // Determine earliest reporting date for each section and query selection criteria accordingly
    $sql = 'SELECT MAX(dqs_date) as dqs_date FROM dq_stats;';
    $res = $dbr->query( $sql, $fname );
    if ( $row = $dbr->fetchObject( $res ) ) {
      if ( substr($row->dqs_date,8,2) > "10" ) {
        $earliest_day = substr($row->dqs_date,0,8) . substr($row->dqs_date,8,1) - 1 . substr($row->dqs_date,9,1);  // report last 10 days (within current month)
      }
      else {
        $earliest_day = substr($row->dqs_date,0,8) . "00";
      }
      $earliest_month = substr($row->dqs_date,0,4) - 1 . substr($row->dqs_date,4,3);  // report last 12 months
      $earliest_year =  substr($row->dqs_date,0,4) - 5;                               // report last 5 years
    }

		$dailyCond = 'SUBSTR(a.dqs_date,1,7) = (SELECT MAX(SUBSTR(dqs_date,1,7)) FROM dq_stats) AND a.dqs_date > "' . $earliest_day . '"';
		$monthlyCond = 'a.dqs_date IN (SELECT MAX(dqs_date) FROM dq_stats GROUP BY SUBSTR(dqs_date,1,7)) AND SUBSTR(a.dqs_date,1,7) > "' . $earliest_month . '"';
    $annualCond = 'a.dqs_date IN (SELECT MAX(dqs_date) from dq_stats GROUP BY SUBSTR(dqs_date,1,4)) 
                  AND SUBSTR(a.dqs_date,1,7) < "' . $earliest_month . '" AND SUBSTR(a.dqs_date,1,4) >= "' . $earliest_year . '"'; 
   
    // Pages impacted
    $wgOut->addHTML('<h2>Number and Percentage of Pages Impacted by Errors and/or Anomalies</h2>');
    $wgOut->addHTML('This section shows the number of pages that have at least one error and/or anomaly, compared to the total number of pages in WeRelate.');
    $wgOut->addHTML('<br>Note: Incomplete information (e.g., missing gender) is NOT included in these counts.');
    $select = 'SELECT a.dqs_date, b.dqs_issue_desc, a.dqs_count as pages_impacted, b.dqs_count as total_pages
                 FROM dq_stats a INNER JOIN dq_stats b ON a.dqs_job_id = b.dqs_job_id
                   AND a.dqs_category = "Impact" AND b.dqs_category = "Growth" 
                   AND SUBSTR(a.dqs_issue_desc,1,6) = SUBSTR(b.dqs_issue_desc,1,6)
                 WHERE ';
    $select = 'SELECT a.dqs_date, a.dqs_count as person_pages_impacted, b.dqs_count as total_person_pages, c.dqs_count as family_pages_impacted, d.dqs_count as total_family_pages
                 FROM dq_stats a INNER JOIN dq_stats b INNER JOIN dq_stats c INNER JOIN dq_stats d 
                   ON a.dqs_job_id = b.dqs_job_id AND a.dqs_job_id = c.dqs_job_id AND a.dqs_job_id = d.dqs_job_id
                   AND a.dqs_category = "Impact" AND a.dqs_issue_desc like "Person%" 
                   AND b.dqs_category = "Growth" AND b.dqs_issue_desc like "Person%"
                   AND c.dqs_category = "Impact" AND c.dqs_issue_desc like "Family%" 
                   AND d.dqs_category = "Growth" AND d.dqs_issue_desc like "Family%"
                 WHERE ';
    $order = ' ORDER by 1 DESC ';
    
    // Current month (daily)
 		$res = $dbr->query( $select . $dailyCond . $order, $fname );
		if ( $dbr->numRows( $res ) ) {
      $wgOut->addHTML('<h3>Current Month (last 10 days)</h3>');
  		$wgOut->addHTML( "\n<table border=1 id=\"current_impact_stats\">" );
      $wgOut->addHTML( "<tr><td>As of</td><td>Person pages impacted</td><td>Total person pages</td><td>Percentage</td>
                  <td>Family pages impacted</td><td>Total family pages</td><td>Percentage</td></tr>" );
      while ( $row = $dbr->fetchObject( $res ) ) {
			  $wgOut->addHTML( '<tr><td>' . self::toDisplayDate($row->dqs_date) . '</td><td>' . $row->person_pages_impacted . '</td><td>' . $row->total_person_pages . '</td><td>' . 
            round(100 * $row->person_pages_impacted / $row->total_person_pages, 2) . '</td><td>' . $row->family_pages_impacted . '</td><td>' . $row->total_family_pages . '</td><td>' . 
            round(100 * $row->family_pages_impacted / $row->total_family_pages, 2) . '</td></tr>');
		  }
		  $wgOut->addHTML( "</table>\n" );
		}
    
    // Monthly then annual history (includes current month)
 		$res = $dbr->query( $select . $monthlyCond . ' UNION ' . $select . $annualCond . $order, $fname );
		if ( $dbr->numRows( $res ) ) {
      $wgOut->addHTML('<h3>Monthly/Annual History</h3>');
  		$wgOut->addHTML( "\n<table border =1 id=\"previous_impact_stats\">" );
      $wgOut->addHTML( "<tr><td>As of</td><td>Person pages impacted</td><td>Total person pages</td><td>Percentage</td>
                  <td>Family pages impacted</td><td>Total family pages</td><td>Percentage</td></tr>" );
      while ( $row = $dbr->fetchObject( $res ) ) {
			  $wgOut->addHTML( '<tr><td>' . self::toDisplayDate($row->dqs_date) . '</td><td>' . $row->person_pages_impacted . '</td><td>' . $row->total_person_pages . '</td><td>' . 
            round(100 * $row->person_pages_impacted / $row->total_person_pages, 2) . '</td><td>' . $row->family_pages_impacted . '</td><td>' . $row->total_family_pages . '</td><td>' . 
            round(100 * $row->family_pages_impacted / $row->total_family_pages, 2) . '</td></tr>');
		  }
		  $wgOut->addHTML( "</table>\n" );
		}
   
    // Counts of issues by category
    $wgOut->addHTML('<h2>Number of Issues by Category</h2>');
    $wgOut->addHTML('This section shows the total number of issues by category. If a page has more than one issue, each is counted separately.');
    $wgOut->addHTML('<br>Note: "Incomplete" includes more than just missing gender (see section "Number of Issues by Issue Description"), but for now, the Data Quality Issues list includes only missing gender in this category.');
    $wgOut->addHTML('<br>Note: "Total issues" is the sum of errors, anomalies and all the types of incomplete information listed in section "Number of Issues by Issue Description".');
    $select = 'SELECT a.dqs_date, a.error_count, b.anomaly_count, c.incomplete_count FROM 
              (SELECT dqs_job_id, dqs_date, SUM(dqs_count) AS error_count FROM dq_stats WHERE dqs_category = "Error" GROUP BY dqs_job_id, dqs_date) a
              INNER JOIN (SELECT dqs_job_id, SUM(dqs_count) AS anomaly_count FROM dq_stats WHERE dqs_category = "Anomaly" GROUP BY dqs_job_id) b ON a.dqs_job_id = b.dqs_job_id
              INNER JOIN (SELECT dqs_job_id, SUM(dqs_count) AS incomplete_count FROM dq_stats WHERE dqs_category = "Incomplete" GROUP BY dqs_job_id) c ON a.dqs_job_id = c.dqs_job_id
              WHERE '; 
    $order = ' ORDER BY 1 DESC ';                 
    
    // Current month (daily)
 		$res = $dbr->query( $select . $dailyCond . $order, $fname );
		if ( $dbr->numRows( $res ) ) {
      $wgOut->addHTML('<h3>Current Month</h3>');
  		$wgOut->addHTML( "\n<table border =1 id=\"current_cat_stats\">" );
      $wgOut->addHTML( "<tr><td>As of</td><td>Errors</td><td>Anomalies</td><td>Total Errors & Anomalies</td><td>Incomplete</td><td>Total Issues</td></tr>" );
      while ( $row = $dbr->fetchObject( $res ) ) {
			  $wgOut->addHTML( '<tr><td>' . self::toDisplayDate($row->dqs_date) . '</td><td>' . $row->error_count . '</td><td>' . 
            $row->anomaly_count . '</td><td>' . ($row->error_count + $row->anomaly_count) . '</td><td>' . $row->incomplete_count . '</td><td>' . 
            ($row->error_count + $row->anomaly_count + $row->incomplete_count) . '</td></tr>');
		  }
		  $wgOut->addHTML( "</table>\n" );
		}
    
    // Monthly then annual history (includes current month)
 		$res = $dbr->query( $select . $monthlyCond . ' UNION ' . $select . $annualCond . $order, $fname );
		if ( $dbr->numRows( $res ) ) {
      $wgOut->addHTML('<h3>Monthly/Annual History</h3>');
  		$wgOut->addHTML( "\n<table border =1 id=\"previous_cat_stats\">" );
      $wgOut->addHTML( "<tr><td>As of</td><td>Errors</td><td>Anomalies</td><td>Total Errors & Anomalies</td><td>Incomplete</td><td>Total Issues</td></tr>" );
      while ( $row = $dbr->fetchObject( $res ) ) {
			  $wgOut->addHTML( '<tr><td>' . self::toDisplayDate($row->dqs_date) . '</td><td>' . $row->error_count . '</td><td>' . 
            $row->anomaly_count . '</td><td>' . ($row->error_count + $row->anomaly_count) . '</td><td>' . $row->incomplete_count . '</td><td>' . 
            ($row->error_count + $row->anomaly_count + $row->incomplete_count) . '</td></tr>');
		  }
		  $wgOut->addHTML( "</table>\n" );
		}
   
    // Counts of issues by issue description
    $wgOut->addHTML('<h2>Number of Issues by Issue Description</h2>');
    $wgOut->addHTML('This section shows the number of issues by issue type. The order is issue type and then date for easy tracking of trends by issue type.');
    $select = 'SELECT dqs_date, dqs_category, dqs_issue_desc, SUM(dqs_count) AS count
                 FROM dq_stats a '; 
    if ( $wgUser->isLoggedIn() && in_array( 'sysop', $wgUser->getGroups()) ) {
      $issueScope = 'WHERE dqs_category IN ("Anomaly", "Error", "Incomplete", "Living") AND ';      // For now (Aug 2022), display counts of considered or potentially living people only to admins
      $wgOut->addHTML('<br>Note that the category "Living" is displayed only to WeRelate admins, until the backlog of possibly living persons is cleaned up. If you would like to help, please contact DataAnalyst.');
    }      
    else {
      $issueScope = 'WHERE dqs_category IN ("Anomaly", "Error", "Incomplete") AND ';
    }
    $group = ' GROUP BY dqs_category, dqs_issue_desc, dqs_date, dqs_job_id ';  // group by dqs_job_id needed in case stats produced more than once in a day
    $order = ' ORDER BY dqs_category, dqs_issue_desc, dqs_date DESC ';
    
    // Daily for this month
 		$res = $dbr->query( $select . $issueScope . $dailyCond . $group . $order, $fname );
		if ( $dbr->numRows( $res ) ) {
      $wgOut->addHTML('<h3>Current Month</h3>');
  		$wgOut->addHTML( "\n<table border =1 id=\"current_issue_stats\">" );
      $wgOut->addHTML( "<tr><td>As of</td><td>Category</td><td>Issue Description</td><td>Number</td></tr>" );
      while ( $row = $dbr->fetchObject( $res ) ) {
			  $wgOut->addHTML( '<tr><td>' . self::toDisplayDate($row->dqs_date) . '</td><td>' . $row->dqs_category . '</td><td>' . 
            $row->dqs_issue_desc . '</td><td>' . $row->count . '</td></tr>');
		  }
		  $wgOut->addHTML( "</table>\n" );
		}
    
    // Monthly for this and previous months
 		$res = $dbr->query( $select . $issueScope . $monthlyCond . $group . ' UNION ' . $select . $issueScope . $annualCond . $group . $order, $fname );
		if ( $dbr->numRows( $res ) ) {
      $wgOut->addHTML('<h3>Monthly/Annual History</h3>');
  		$wgOut->addHTML( "\n<table border =1 id=\"previous_issue_stats\">" );
      $wgOut->addHTML( "<tr><td>As of</td><td>Category</td><td>Issue Description</td><td>Number</td></tr>" );
      while ( $row = $dbr->fetchObject( $res ) ) {
			  $wgOut->addHTML( '<tr><td>' . self::toDisplayDate($row->dqs_date) . '</td><td>' . $row->dqs_category . '</td><td>' . 
            $row->dqs_issue_desc . '</td><td>' . $row->count . '</td></tr>');
		  }
		  $wgOut->addHTML( "</table>\n" );
		}
	}
}

?>
