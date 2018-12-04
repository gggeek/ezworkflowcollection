<?php

$ini = new eZINI( 'content.ini' );
$unpublishClasses = $ini->variable( 'UnpublishSettings','ClassList' );

$rootNodeIDList = $ini->variable( 'UnpublishSettings','RootNodeList' );

$currrentDate = time();

// Get ArchiveState and user ID from ini file
$ini = new eZINI( 'ezworkflowcollection.ini' );
$targetState = $ini->variable('UpdateObjectStatesSettings', 'PublishedObjectState');

$adminUser = eZUser::fetch($ini->variable('UpdateObjectStatesSettings', 'PublishedObjectState'));
eZUser::setCurrentlyLoggedInUser( $adminUser );

$currentUser = eZUser::currentUser();

$cli->output( 'Login as admin : '.$currentUser->attribute('login'));
$cli->output( 'Published : Starting.' );
$cli->output( 'Supported Classes : '. implode( ', ', $unpublishClasses)  );


foreach( $rootNodeIDList as $nodeID )
{
    $rootNode = eZContentObjectTreeNode::fetch( $nodeID );

    $articleNodeArray = $rootNode->subTree( array( 'ClassFilterType' => 'include',
                                                    'ClassFilterArray' => $unpublishClasses ) );

    foreach ( $articleNodeArray as $articleNode )
    {
        $article = $articleNode->attribute( 'object' );
        // Si le contenu est à l'état Mise en ligne programmée 'publish_chain/waituntildate'
        if( in_array( $ini->variable('UpdateObjectStatesSettings', 'PendingObjectState'), $article->attribute('state_id_array') ) )
        {
            $dataMap = $article->attribute( 'data_map' );

            $dateAttribute = $dataMap['publish_date'];

            if ( $dateAttribute === null )
                continue;

            $date = $dateAttribute->content();
            $articleRetractDate = $date->attribute( 'timestamp' );

            if ( $articleRetractDate > 0 && $articleRetractDate < $currrentDate )
            {
                $ok = "NOT-OK";
                // Switch Object state to Published
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
}
