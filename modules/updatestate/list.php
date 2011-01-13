<?php

include_once('kernel/common/template.php');

$Module = $Params['Module'];
$http = eZHTTPTool::instance();

$Offset = $Params['Offset'];
$viewParameters = array( 'offset' => $Offset );

if ( $Params['State'] !=0 )
{
    $state = $Params['State'];
}
elseif( $http->hasPostVariable( 'State' ) )
{
    $state = $http->postVariable( 'State' );
}
else
{
    $ini = new eZINI( 'ezworkflowcollection.ini' );
    $state = $ini->variable( 'UpdateObjectStatesSettings', 'DefaultObjectState' );
}

if( $http->hasVariable( 'SelectIDArray' ) && $http->hasVariable( 'UpdateObjectStateButton' ) && $http->hasVariable( 'TargetObjectState' ) )
{
    $targetState = null;
    if( $http->variable( 'TargetObjectState' ) > 0 )
    {
        $targetState = $http->variable( 'TargetObjectState' );
        $targetState = eZContentObjectState::fetchById( $targetState );
    }

    if( $targetState != null )
    {
        foreach( $http->variable( 'SelectIDArray' ) as $objectID )
        {
            $object = eZContentObject::fetch( $objectID );
            if ( $object != null )
            {
                if ( eZOperationHandler::operationIsAvailable( 'content_updateobjectstate' ) )
                {
                    $operationResult = eZOperationHandler::execute( 'content', 'updateobjectstate',
                                                                    array( 'object_id'     => $objectID,
                                                                           'state_id_list' => array( $targetState->attribute( 'id' ) ) ) );
                }
                else
                {
                    eZContentOperationCollection::updateObjectState( $objectID, array( $targetState->attribute( 'id' ) ) );
                }
            }
        }
    }
}

$state = eZContentObjectState::fetchById( $state );

if( !is_object( $state ) )
{
    return $Module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
}
else
{
    $stateName = $state->attribute( 'current_translation' );
    $stateName = $stateName->attribute( 'name' );
}

$tpl = templateInit();
$tpl->setVariable( 'state', $state );
$tpl->setVariable( 'state_name', $stateName );
$tpl->setVariable( 'view_parameters', $viewParameters );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:updatestate/list.tpl' );

$Result['path'] = array( array( 'text' => "Contents in state : ".$stateName,
                                'url' => false ) );
?>