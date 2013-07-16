<?php

$ini = new eZINI( 'content.ini' );
$unpublishClasses = $ini->variable( 'UnpublishSettings','ClassList' );

$rootNodeIDList = $ini->variable( 'UnpublishSettings','RootNodeList' );

$currrentDate = time();

// Get ArchiveState and user ID from ini file
$ini = new eZINI( 'ezworkflowcollection.ini' );
$targetState = $ini->variable('UpdateObjectStatesSettings', 'ArchiveObjectState');

$adminUser = eZUser::fetch($ini->variable('UpdateObjectStatesSettings', 'ObjectStateUserID'));
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