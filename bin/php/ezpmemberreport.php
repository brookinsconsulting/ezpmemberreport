#!/usr/bin/env php
<?php
/**
 * File containing the ezpmemberreport.php bin script
 *
 * @copyright Copyright (C) 1999 - 2014 Brookins Consulting. All rights reserved.
 * @copyright Copyright (C) 2013 - 2014 Think Creative. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or later)
 * @version 0.0.5
 * @package ezpmemberreport
 */

require 'autoload.php';

/** Script startup and initialization **/

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => ( "eZ Publish Member CSV Report Script\n" .
                                                        "\n" .
                                                        "ezpmemberreport.php --storage-dir=var/memberReport --nodeid=5 --hostname=www.example.com" ),
                                     'use-session' => false,
                                     'use-modules' => true,
                                     'use-extensions' => true,
                                     'user' => true ) );

$script->startup();

$options = $script->getOptions( "[storage-dir:][hostname:][nodeid:]",
                                "[node]",
                                array( 'storage-dir' => 'Directory to place exported files. Example: --storage-dir=var/memberReport',
                                       'hostname' => 'Website hostname to match url searches for. Example: --hostname=www.example.com',
                                       'nodeid' => 'Content tree NodeID containing image nodes. Example: --nodeid=5' ),
                                false,
                                array( 'user' => true ) );
$script->initialize();

/** Script default values **/

$openedFPs = array();

$orphanedCsvReportFileName = 'ezpMemberReport';

$csvHeader = array( 'MainNodeID', 'Name', 'Locations', 'Company', 'Address', 'Address 2', 'City', 'Province', 'Postal Code', 'Phone 1','Phone 2','Phone 3','Url','Email', 'Booking URL', 'Winter Rate', 'Summer Rate', 'Rates', 'Description', 'Hours', 'Main Image', 'Image 1', 'Image 2', 'Logo', 'External Image Url', 'Meta Title', 'Member ID', 'User Account', 'Current Active Package', 'Languages', 'Uploadable File', 'Uploadable File Name', 'Rating', 'Star Rating', 'Summer', 'Winter', 'YouTube Video URL', 'Toll Free', 'Green Key', 'Accomodation Member - Dining Information', 'Accomodation Member - Room Information', 'Accomodation Member - Directions Information', 'Latitude', 'Longitude', 'Accomodation Member - JackRabbit ID', 'Fax', 'Payment Types Accepted', 'Meta Ac ID' );

$siteNodeUrlPrefix = "http://";

/** Test for required script arguments **/

if ( $options['nodeid'] )
{
    $nodeID = $options['nodeid'];
}
else
{
    $cli->error( 'NodeID is required. Specify a content treee node for the report' );
    $script->shutdown( 1 );
}

if ( !is_numeric( $nodeID ) )
{
    $cli->error( 'Please specify a numeric node ID' );
    $script->shutdown( 2 );
}

if ( $options['storage-dir'] )
{
    $storageDir = $options['storage-dir'];
}
else
{
    $storageDir = '';
}

if ( $options['hostname'] )
{
    $siteNodeUrlHostname = $options['hostname'];
}
else
{
    $cli->error( 'Hostname is required. Specify a website hostname for the site report url matching' );
    $script->shutdown( 2 );
}

/** Fetch starting node from content tree **/

$node = eZContentObjectTreeNode::fetch( $nodeID );

if ( !$node )
{
    $cli->error( "No node with ID: $nodeID" );
    $script->shutdown( 3 );
}

/** Fetch nodes under starting node in content tree **/

$subTree = $node->subTree( array( 'ClassFilterType' => 'include',
                                  'ClassFilterArray' => array( 'member' ) ) );
$subTreeCount = $node->subTreeCount( array( 'ClassFilterType' => 'include',
                                            'ClassFilterArray' => array( 'member' ) ) );

/** Alert user of report generation process starting **/

$cli->output( "Searching through content subtree from node $nodeID to find user objects to include in the report ...\n" );

/** Setup script iteration details **/

$script->setIterationData( '.', '.' );
$script->resetIteration( $subTreeCount );

/** Open report file for writting **/

if ( !isset( $openedFPs[$orphanedCsvReportFileName] ) )
{
    $tempFP = @fopen( $storageDir . '/' . $orphanedCsvReportFileName . '.csv', "w" );

    if ( $tempFP )
    {
        $openedFPs[$orphanedCsvReportFileName] = $tempFP;
    }
    else
    {
        $cli->error( "Can not open output file for $storageDir/$orphanedCsvReportFileName file" );
        $script->shutdown( 4 );
    }
}
else
{
   if ( !$openedFPs[$orphanedCsvReportFileName] )
   {
        $cli->error( "Can not open output file for $storageDir/$orphanedCsvReportFileName file" );
        $script->shutdown( 4 );
   }
}

/** Define report file pointer **/

$fp = $openedFPs[$orphanedCsvReportFileName];

/** Write report csv header **/

if ( !fputcsv( $fp, $csvHeader, ';' ) )
{
    $cli->error( "Can not write to report file" );
    $script->shutdown( 6 );
}

/** Iterate over nodes **/

while ( list( $key, $childNode ) = each( $subTree ) )
{
    $objectData = array();
    $status = true;
    $nodeLocations = '';

    /** Fetch object details **/

    $object = $childNode->attribute( 'object' );

    $classIdentifier = $object->attribute( 'class_identifier' );

    $childNodeID = $childNode->attribute('node_id');

    $nodeFullName = $childNode->attribute('name');

    $nodeUrl = $childNode->attribute('url');

    $nodeFullUrl = $siteNodeUrlPrefix . $siteNodeUrlHostname . '/' . $childNode->attribute('url');

    $actualSiteNodeUrl = $siteNodeUrlPrefix . $siteNodeUrlHostname . '/' . $childNode->attribute('url');

    foreach( $childNode->object()->attribute('assigned_nodes') as $node )
    {
        $nodeLocations .= $siteNodeUrlPrefix . $siteNodeUrlHostname . '/' . $node->attribute('url') . "\n";
    }

    /** Build report for objects of class image **/

    if( $classIdentifier == 'member' )
    {
        $objectData[] = $childNodeID;

        $objectData[] = $nodeFullName;

        $objectData[] = $nodeLocations;

        /** Iterate over node content object attributes **/

        foreach ( $object->attribute( 'contentobject_attributes' ) as $attribute )
        {
               $attributeStringContent = $attribute->toString();

               switch ( $datatypeString = $attribute->attribute( 'data_type_string' ) )
               {
                   case 'ezimage':
                   {
                       $imagePathParts = explode( '/', $attributeStringContent );
                       $imageFile = array_pop( $imagePathParts );
                       $attributeStringContent = @explode( '|', $imageFile);
                       $objectData[] = $attributeStringContent[0];
                   } break;

                   case 'ezbinaryfile':
                   {
                       $imagePathParts = explode( '/', $attributeStringContent );
                       $imageFile = array_pop( $imagePathParts );
                       $attributeStringContent = @explode( '|', $imageFile);
                       if( isset( $attributeStringContent[1] ) )
                       {
                            // print_r( $attributeStringContent[1] );
                            $objectData[] = $attributeStringContent[1];
                       }
                       else
                       {
                            $objectData[] = $attributeStringContent[0];
                       }
                   } break;

                   case 'ezmedia':
                   {
                       $imagePathParts = explode( '/', $attributeStringContent );
                       $imageFile = array_pop( $imagePathParts );
                       $attributeStringContent = @explode( '|', $imageFile);
                       $objectData[] = $attributeStringContent[0];
                   } break;

                   case 'ezxmltext':
                   {
                       $attributeStringContent = @$attribute->content()->attribute('output')->attribute('output_text');
                       $objectData[] = trim( preg_replace( "/\r\n|\r|\n/", ' ', trim( $attributeStringContent ) ) );
                   } break;

                   case 'eztext':
                   {
                       $attributeStringContent = $attribute->toString();
                       $objectData[] = trim( preg_replace( '/\s\s+/', ' ', $attributeStringContent ) );
                   } break;

                   case 'ezuser':
                   {
                       $attributeStringContent = $attribute->content();
                       $attributeStringContent = @explode( '|', $attributeStringContent );
                       $objectData[] = $attributeStringContent[0];
                   } break;

                   case 'ezurl':
                   {
                       $attributeStringContent = $attribute->content();
                       $attributeStringContent = @explode( '|', $attributeStringContent );
                       $objectData[] = $attributeStringContent[0];
                   } break;

                   case 'ezstring':
                   {
                       $objectData[] = $attributeStringContent;
                   } break;

                   case 'ezobjectrelation':
                   {
                        if( $attribute->toString() )
                        {
                            $relationNode = eZContentObject::fetch( $attribute->toString() );
                            if( $relationNode )
                            {
                                $relationNodeName = $relationNode->attribute('name');
                                $objectData[] = $relationNodeName;
                            }
                            else
                            {
                                $objectData[] = '';
                            }
                        }
                        else
                        {
                            $objectData[] = '';
                        }
                   } break;

                   case 'ezobjectrelationlist':
                   {
                        $relationList = $attribute->content();
                        $relationNodesName = '';
                        foreach( $relationList['relation_list'] as $relation )
                        {
                            $relationNode = eZContentObjectTreeNode::fetch( $relation['node_id'] );
                            $relationNodesName .= $relationNode->attribute('name') . "\n";
                        }
                       $objectData[] = $relationNodesName;
                   } break;

                   default:
                       $objectData[] = $attributeStringContent;
               }
        }

        /** Test if report file is opened **/
        if ( !$fp )
        {
            $cli->error( "Can not open output file" );
            $script->shutdown( 5 );
        }

        /** Write report datat to file **/
        if ( !fputcsv( $fp, $objectData, ';' ) )
        {
            $cli->error( "Can not write to file" );
            $script->shutdown( 6 );
        }
    }

    $script->iterate( $cli, $status );
}

/** Close report file **/
while ( $fp = each( $openedFPs ) )
{
    fclose( $fp['value'] );
}

/** Shutdown script **/
$script->shutdown();

?>