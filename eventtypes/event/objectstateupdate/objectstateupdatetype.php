<?php
/**
 * Workflow that will update the object state of an object upon publication (from X to Y)
 *
 * @author O. Portier
 * @license Licensed under GNU General Public License v2.0. See file LICENSE
 * @copyright (C) O. Portier 2010-2012
 *
 * @todo we could add some option and limitations to check initial object state and update it from the new state
 * 		 configured from the administration interface, with the workflow event editing
 */
include_once( 'kernel/common/i18n.php' );

class objectStateUpdateType extends eZWorkflowEventType
{
    const WORKFLOW_TYPE_STRING = 'objectstateupdate';

    // register workflow event as available for post-publish only
    function __construct()
    {
        $this->eZWorkflowEventType( self::WORKFLOW_TYPE_STRING, ezpI18n::tr( 'extension/ezworkflowobjectstate', 'Object state update' ) );
        $this->setTriggerTypes( array( 'content' => array( 'publish' => array ( 'after' ) ) ) );
    }

    function attributes()
    {
        return array_merge( array( 'state_before',
                                   'state_after',
                                   'stat_groups' ),
                            eZWorkflowEventType::attributes() );

    }

    function hasAttribute( $attr )
    {
        return in_array( $attr, $this->attributes() );
    }

    function attribute( $attr )
    {
        return eZWorkflowEventType::attribute( $attr );
    }

    function attributeDecoder( $event, $attr )
    {
        switch ( $attr )
        {
            case 'state_before':
                $returnValue = trim( $event->attribute( 'data_int1' ) );
                if($returnValue > 0)
               	{
	                $returnValue = eZContentObjectState::fetchById( $returnValue );
                }
                break;

            case 'state_after':
                $returnValue = trim( $event->attribute( 'data_int2' ) );
                if($returnValue > 0)
               	{
	                $returnValue = eZContentObjectState::fetchById( $returnValue );
                }
                break;

            case 'state_groups':
				$returnValue = eZContentObjectStateGroup::fetchByOffset();
                break;

            default:
                $returnValue = null;
        }
        return $returnValue;
    }

    function typeFunctionalAttributes()
    {
        return array( 'state_before',
                      'state_after',
                      'state_groups' );
    }

    function execute( $process, $event )
    {
        // get object being published
        $parameters = $process->attribute( 'parameter_list' );
        $objectID = $parameters['object_id'];
        eZDebug::writeDebug( 'Update object state for object: ' . $objectID );

        $object = eZContentObject::fetch( $objectID );
        $state_before = $event->attribute( 'state_before' );
        $state_after = $event->attribute( 'state_after' );

        if ( $object == null )
        {
            eZDebug::writeError( 'Update object state failed for inexisting object: ' . $objectID, __METHOD__ );
            return eZWorkflowType::STATUS_WORKFLOW_CANCELLED;
        }
        if ( $state_before == null || $state_after == null )
        {
            eZDebug::writeError( 'Update object state failed: badly configured states', __METHOD__ );
            return eZWorkflowType::STATUS_WORKFLOW_CANCELLED;
        }

        $currentStateIDArray = $object->attribute( 'state_id_array' );
        if ( in_array( $state_before->attribute('id'), $currentStateIDArray ) )
        {
            $canAssignStateIDList = $object->attribute( 'allowed_assign_state_id_list' );
            if ( !in_array( $state_after->attribute('id'), $canAssignStateIDList ) )
            {
                eZDebug::writeWarning( "Not enough rights to assign state to object $objectID: " . $state_after->attribute( 'id' ) , __METHOD__ );
            }
            else
            {
                eZDebug::writeDebug( 'Changing object state from '. $state_before->attribute('name'). ' to '. $state_after->attribute( 'name' ), __METHOD__ );
                if ( eZOperationHandler::operationIsAvailable( 'content_updateobjectstate' ) )
			    {
			        $operationResult = eZOperationHandler::execute( 'content', 'updateobjectstate',
			                                                        array( 'object_id'     => $objectID,
			                                                               'state_id_list' => array( $state_after->attribute( 'id' ) ) ) );
			    }
			    else
			    {
			        eZContentOperationCollection::updateObjectState( $objectID, array( $state_after->attribute( 'id' ) ) );
			    }
            }
        }
        return eZWorkflowType::STATUS_ACCEPTED;
    }

    function validateHTTPInput( $http, $base, $workflowEvent, &$validation )
    {
        $http_input_group_before = $base.'_event_'.self::WORKFLOW_TYPE_STRING.'_group_before_'.$workflowEvent->attribute(id);
        $http_input_state_before = $base.'_event_'.self::WORKFLOW_TYPE_STRING.'_state_before_'.$workflowEvent->attribute(id);

        $http_input_group_after = $base.'_event_'.self::WORKFLOW_TYPE_STRING.'_group_after_'.$workflowEvent->attribute(id);
        $http_input_state_after = $base.'_event_'.self::WORKFLOW_TYPE_STRING.'_state_after_'.$workflowEvent->attribute(id);


        if( $http->hasPostVariable($http_input_group_before) && $http->hasPostVariable($http_input_state_before) && $http->hasPostVariable($http_input_group_after) && $http->hasPostVariable($http_input_state_after) )
        {
	        $returnState = eZInputValidator::STATE_ACCEPTED;
        }
        else
        {
	    	$returnState = eZInputValidator::STATE_INVALID;
	        $reason[ 'text' ] = "Select at least one group, then one state.";
        }

    	return $returnState;
    }

    function fetchHTTPInput( $http, $base, $event )
    {
    	$http_input_state_before = $base.'_event_'.self::WORKFLOW_TYPE_STRING.'_state_before_'.$event->attribute(id);
    	$http_input_state_after = $base.'_event_'.self::WORKFLOW_TYPE_STRING.'_state_after_'.$event->attribute(id);

		if ( $http->hasPostVariable( $http_input_state_before ) )
		{
			$event->setAttribute( "data_int1", $http->postVariable( $http_input_state_before ) );
		}

		if ( $http->hasPostVariable( $http_input_state_after ) )
		{
			$event->setAttribute( "data_int2", $http->postVariable( $http_input_state_after ) );
		}
    }
}

eZWorkflowEventType::registerEventType( objectStateUpdateType::WORKFLOW_TYPE_STRING, 'objectstateupdatetype' );

?>