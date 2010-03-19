<?php
/**
 *
 * @author G. Giunta
 * @version $Id$
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 * @copyright (C) G. Giunta 2010
 */


class SubTreeMultiplexerType extends eZWorkflowEventType
{
    const WORKFLOW_TYPE_STRING = 'subtreemultiplexer';

    function __construct()
    {
        $this->eZWorkflowEventType( self::WORKFLOW_TYPE_STRING, ezi18n( 'extension/ezworkflowcollection', 'Subtree Multiplexer' ) );
        $this->setTriggerTypes( array( 'content' => array( 'publish' => array ( 'before', 'after' ) ) ) );
    }

    function execute( $process, $event )
    {
        $parameters = $process->attribute( 'parameter_list' );
        $objectId = $parameters['object_id'];
        $object = eZContentObject::fetch( $objectId );
        $subtreeNodeID = $event->attribute( 'target_subtree' );
        $subtreeNode = eZContentObjectTreeNode::fetch( $subtreeNodeID );
        eZDebug::writeDebug( "Event begins execution for object $objectId, subtree $subtreeNodeID", __METHOD__ );
        if ( $object != null && $subtreeNode != null )
        {
            $is_child = false;
            $locations = $object->assignedNodes();
            if ( $locations == null )
            {
                // pre-creation event: obj has no node on its own, but a putative parent
                //eZDebug::writeDebug( 'Obj node is new!', __METHOD__ );
                $locations = eZNodeAssignment::fetchForObject( $objectId, $object->attribute( "current_version" ) );
                foreach( $locations as $key => $location )
                {
                    $locations[$key] = $location->getParentNode();
                }
            }

            foreach( $locations as $node )
            {
                $subtreeNodePath = $node->pathArray();
                //eZDebug::writeDebug( 'Testing if obj node '.$node->NodeID.' is child of : ' . $subtreeNodeID, __METHOD__ );
                if ( in_array( $subtreeNodeID, $subtreeNodePath ) )
                {
                    eZDebug::writeDebug( 'Found that obj node ' . $node->NodeID . ' is child of node ' . $subtreeNodeID, __METHOD__ );
                    $is_child = true;
                    break;
                }
            }

            if ( $is_child )
            {
                $workflowToRun = $event->attribute( 'target_workflow' );
                $user = eZUser::currentUser();
                $userID = $user->id();
                $processParameters = $process->attribute( 'parameter_list' );

                // code copy+pasted from ez multoplexer worflow...

                $childParameters = array_merge( $processParameters,
                                                array( 'workflow_id' => $workflowToRun,
                                                       'user_id' => $userID,
                                                       'parent_process_id' => $process->attribute( 'id' )
                                                       ) );

                $childProcessKey = eZWorkflowProcess::createKey( $childParameters );

                $childProcessArray = eZWorkflowProcess::fetchListByKey( $childProcessKey );
                $childProcess =& $childProcessArray[0];
                if ( $childProcess == null )
                {
                    $childProcess = eZWorkflowProcess::create( $childProcessKey, $childParameters );
                    $childProcess->store();
                }

                $workflow = eZWorkflow::fetch( $childProcess->attribute( "workflow_id" ) );
                $workflowEvent = null;

                if ( $childProcess->attribute( "event_id" ) != 0 )
                    $workflowEvent = eZWorkflowEvent::fetch( $childProcess->attribute( "event_id" ) );

                $childStatus = $childProcess->run( $workflow, $workflowEvent, $eventLog );
                $childProcess->store();

                if ( $childStatus ==  eZWorkflow::STATUS_DEFERRED_TO_CRON )
                {
                    $this->setActivationDate( $childProcess->attribute( 'activation_date' ) );
                    $childProcess->setAttribute( "status", eZWorkflow::STATUS_WAITING_PARENT );
                    $childProcess->store();
                    return eZWorkflowType::STATUS_DEFERRED_TO_CRON_REPEAT;
                }
                else if ( $childStatus ==  eZWorkflow::STATUS_FETCH_TEMPLATE )
                {
                    $process->Template =& $childProcess->Template;
                    return eZWorkflowType::STATUS_FETCH_TEMPLATE_REPEAT;
                }
                else if ( $childStatus ==  eZWorkflow::STATUS_REDIRECT )
                {
                    $process->RedirectUrl =& $childProcess->RedirectUrl;
                    return eZWorkflowType::STATUS_REDIRECT_REPEAT;
                }
                else if ( $childStatus ==  eZWorkflow::STATUS_DONE  )
                {
                    $childProcess->removeThis();
                    return eZWorkflowType::STATUS_ACCEPTED;
                }
                else if ( $childStatus == eZWorkflow::STATUS_CANCELLED || $childStatus == eZWorkflow::STATUS_FAILED )
                {
                    $childProcess->removeThis();
                    return eZWorkflowType::STATUS_REJECTED;
                }
                return $childProcess->attribute( 'event_status' );
            }

            return eZWorkflowType::STATUS_ACCEPTED;

        }
        else
        {
            eZDebug::writeError( "Event triggered for inexisting object ($objectId) or subtree ($subtreeNodeID)", __METHOD__ );
            return eZWorkflowType::STATUS_WORKFLOW_CANCELLED;
        }

    }

// *** storage/retrieval of per-event-class attributes values

    function attributes()
    {
        return array_merge( array( 'workflow_list' ), eZWorkflowEventType::attributes() );
    }

    function hasAttribute( $attr )
    {
        return in_array( $attr, $this->attributes() );
    }

    function attribute( $attr )
    {
        switch( $attr )
        {
            case 'workflow_list':
                $workflows = eZWorkflow::fetchList();
                $workflowList = array();
                for ( $i = 0; $i < count( $workflows ); $i++ )
                {
                    $workflowList[$i]['Name'] = $workflows[$i]->attribute( 'name' );
                    $workflowList[$i]['value'] = $workflows[$i]->attribute( 'id' );
                }
                return $workflowList;
        }
        return eZWorkflowEventType::attribute( $attr );
    }

// *** storage/retrieval of per-event attributes values (these will be added to the event attributes) ***

    function typeFunctionalAttributes()
    {
        return array( 'target_subtree', 'target_workflow' );
    }

    function attributeDecoder( $event, $attribute )
    {
        switch ( $attribute )
        {
             case 'target_subtree':
                return $event->attribute( 'data_int1' );
             case 'target_workflow':
                return $event->attribute( 'data_int2' );
        }
        return null;
    }

    // get the values to be used for the custom attributes from http post and store them
    function fetchHTTPInput( $http, $base, $event )
    {
        $varName = $base . "_event_subtreemultiplexer_target_subtree_" . $event->attribute( "id" );
        if ( $http->hasPostVariable( $varName ) )
        {
            $event->setAttribute( 'data_int1', (int)$http->PostVariable( $varName ) );
        }
        $varName = $base . "_event_subtreemultiplexer_target_workflow_" . $event->attribute( "id" );
        if ( $http->hasPostVariable( $varName ) )
        {
            $event->setAttribute( 'data_int2', (int)$http->PostVariable( $varName ) );
        }
    }

}

eZWorkflowEventType::registerEventType( SubTreeMultiplexerType::WORKFLOW_TYPE_STRING, 'subtreemultiplexertype' );

?>