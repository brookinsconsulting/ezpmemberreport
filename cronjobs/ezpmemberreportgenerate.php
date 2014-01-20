<?php
/**
 * File containing the ezpmemberreportgenerate.php cronjob.
 *
 * @copyright Copyright (C) 1999 - 2014 Brookins Consulting. All rights reserved.
 * @copyright Copyright (C) 2013 - 2014 Think Creative. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or later)
 * @version 0.0.1
 * @package ezpmemberreport
 */

$ini = eZINI::instance( 'ezpmemberreport.ini' );
$reportStoragePath = $ini->variable( 'SiteSettings', 'ReportStoragePath' );
$siteHostname = $ini->variable( 'SiteSettings', 'SiteHostname' );
$nodeID = $ini->variable( 'SiteSettings', 'NodeID' );

// General cronjob part options
$phpBin = '/usr/bin/php -d memory_limit=-1 ';
$generatorWorkerScript = 'extension/ezpmemberreport/bin/php/ezpmemberreport.php';
$options = '--storage-dir=' . $reportStoragePath . ' --nodeid=' . $nodeID . ' --hostname=' . $siteHostname;
$result = false;

passthru( "$phpBin ./$generatorWorkerScript $options;", $result );

print_r( $result ); echo "\n";

?>