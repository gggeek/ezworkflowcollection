<?php
//
// Created on: <09-12-2010 17:08:52 op>
//
// SOFTWARE NAME: eZ Publish
// SOFTWARE RELEASE: 4.2.0
// BUILD VERSION: 24182
// AUTHOR : Olivier PORTIER eZ SYSTEMS WE
// COPYRIGHT NOTICE: Copyright (C) 1999-2009 eZ Systems AS
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the GNU General
//   Public License as published by the Free Software Foundation.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.
//
//

/*! \file
*/

// Check for extension
require_once( 'kernel/common/ezincludefunctions.php' );
eZExtension::activateExtensions();
// Extension check end

$ini = new eZINI( 'content.ini' );
$unpublishClasses = $ini->variable( 'UnpublishSettings','ClassList' );

$rootNodeIDList = $ini->variable( 'UnpublishSettings','RootNodeList' );

$currrentDate = time();

// Get ArchiveState and user ID from ini file
$ini = new eZINI( 'ezworkflowcollection.ini' );
$targetState = $ini->variable('UpdateObjectStatesSettings', 'ArchiveObjectState');

$adminUser = eZUser::fetch($ini->variable('UpdateObjectStatesSettings', 'ArchiveObjectState'));
eZUser::setCurrentlyLoggedInUser( $adminUser );

$currentUser = eZUser::currentUser();

$cli->output( 'Login as admin : '.$currentUser->attribute('login'));
$cli->output( 'Archiver : Starting.' );
$cli->output( 'Supported Classes : '. implode( ', ', $unpublishClasses)  );


foreach( $rootNodeIDList as $nodeID )
{
    $rootNode = eZContentObjectTreeNode::fetch( $nodeID );

    $articleNodeArray = $rootNode->subTree( array( 'ClassFilterType' => 'include',
                                                    'ClassFilterArray' => $unpublishClasses ) );

    foreach ( $articleNodeArray as $articleNode )
    {
        $article = $articleNode->attribute( 'object' );
        $dataMap = $article->attribute( 'data_map' );

        $dateAttribute = $dataMap['unpublish_date'];

        if ( $dateAttribute === null )
            continue;

        $date = $dateAttribute->content();
        $articleRetractDate = $date->attribute( 'timestamp' );

        if ( $articleRetractDate > 0 && $articleRetractDate < $currrentDate )
        {
            $ok = "NOT-OK";
        	// Switch Object state to Archived
        	if ( eZOperationHandler::operationIsAvailable( 'content_updateobjectstate' ) )
			{
				$operationResult = eZOperationHandler::execute( 'content', 'updateobjectstate',
			                                                        array( 'object_id'     => $articleNode->attribute( 'contentobject_id' ),
			                                                               'state_id_list' => array( $targetState ) ) );
				$ok = "OK";
			}
			else
			{
        		eZContentOperationCollection::updateObjectState( $articleNode->attribute( 'contentobject_id' ), array( $targetState ) );
				$ok = "OK";
			}

			$cli->output(  'Archived : '.$ok.' - '.$articleNode->attribute( 'name' ) );
        }
    }
}

?>