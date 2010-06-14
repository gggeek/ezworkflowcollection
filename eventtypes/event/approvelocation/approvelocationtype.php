<?php

class approveLocationType extends eZApproveType
{

	const WORKFLOW_TYPE_STRING = "approvelocation";

   function approveLocationType()
    {
        $this->eZWorkflowEventType( self::WORKFLOW_TYPE_STRING, ezi18n( 'ezworkflows/workflow/event', "ApproveLocation" ) );
        $this->setTriggerTypes( array( 'content' => array( 'addlocation' => array( 'before' ) ) ) );
    }

    function attributeDecoder( $event, $attr )
    {
        switch ( $attr )
        {
            case 'selected_sections':
            {
                $attributeValue = trim( $event->attribute( 'data_text1' ) );
                $returnValue = empty( $attributeValue ) ? array( -1 ) : explode( ',', $attributeValue );
            }break;

            case 'approve_users':
            {
                $attributeValue = trim( $event->attribute( 'data_text3' ) );
                $returnValue = empty( $attributeValue ) ? array() : explode( ',', $attributeValue );
            }break;

            case 'approve_groups':
            {
                $attributeValue = trim( $event->attribute( 'data_text4' ) );
                $returnValue = empty( $attributeValue ) ? array() : explode( ',', $attributeValue );
            }break;

            case 'selected_usergroups':
            {
                $attributeValue = trim( $event->attribute( 'data_text2' ) );
                $returnValue = empty( $attributeValue ) ? array() : explode( ',', $attributeValue );
            }break;

            case 'language_list':
            {
                $returnValue = array();
                $attributeValue = $event->attribute( 'data_int2' );
                if ( $attributeValue != 0 )
                {
                    $languages = eZContentLanguage::languagesByMask( $attributeValue );
                    foreach ( $languages as $language )
                    {
                        $returnValue[$language->attribute( 'id' )] = $language->attribute( 'name' );
                    }
                }
            }break;

            case 'version_option':
            {
                $returnValue = self::VERSION_OPTION_ALL & $event->attribute( 'data_int3' );
            }break;

            default:
                $returnValue = null;
        }
        return $returnValue;
    }

    function typeFunctionalAttributes( )
    {
        return array( 'selected_sections',
                      'approve_users',
                      'approve_groups',
                      'selected_usergroups',
                      'language_list',
                      'version_option' );
    }

    function attributes()
    {
        return array_merge( array( 'sections',
                                   'languages',
                                   'users',
                                   'usergroups' ),
                            eZWorkflowEventType::attributes() );

    }

function hasAttribute( $attr )
    {
        return in_array( $attr, $this->attributes() );
    }

    function attribute( $attr )
    {
        switch( $attr )
        {
            case 'sections':
            {
                $sections = eZSection::fetchList( false );
                foreach ( $sections as $key => $section )
                {
                    $sections[$key]['Name'] = $section['name'];
                    $sections[$key]['value'] = $section['id'];
                }
                return $sections;
            }break;
            case 'languages':
            {
                return eZContentLanguage::fetchList();
            }break;
        }
        return eZWorkflowEventType::attribute( $attr );
    }

	function execute( $process, $event )
    {
     	eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $process, 'approveLocationType::execute' );
        eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $event, 'approveLocationType::execute' );
        $parameters = $process->attribute( 'parameter_list' );
        $userID = $parameters['user_id'];
        $objectID = $parameters['object_id'];
        $object = eZContentObject::fetch( $objectID );
        if ( !$object )
        {
            eZDebugSetting::writeError( 'ezworkflowcollection-workflow-approve-location', "No object with ID $objectID", 'approveLocationType::execute' );
            return eZWorkflowType::STATUS_WORKFLOW_CANCELLED;
        }

        $version = $object->currentversion( );
        if ( !$version )
        {
            eZDebugSetting::writeError( 'ezworkflowcollection-workflow-approve-location', "No version for object with ID $objectID", 'approveLocationType::execute' );
            return eZWorkflowType::STATUS_WORKFLOW_CANCELLED;
        }
		else
		{
			$versionID = $version->attribute('version');
		}

        // version option checking
        $version_option = $event->attribute( 'version_option' );
        if ( ( $version_option == self::VERSION_OPTION_FIRST_ONLY and $versionID > 1 ) or
             ( $version_option == self::VERSION_OPTION_EXCEPT_FIRST and $versionID == 1 ) )
        {
            return eZWorkflowType::STATUS_ACCEPTED;
        }

        // Target nodes
		$targetNodeIDs = $parameters['select_node_id_array'];
		if( !is_array($targetNodeIDs) or
			count($targetNodeIDs) == 0 )
		{
            return eZWorkflowType::STATUS_WORKFLOW_CANCELLED;
		}

        /*
          Check userID or get user_id from process object
         */
        if ( $userID == 0 )
        {
            $user = eZUser::currentUser();
            $process->setAttribute( 'user_id', $user->id() );
        }
        else
        {
            $user = eZUser::instance( $userID );
        }
        $userGroups = array_merge( $user->attribute( 'groups' ), array( $user->attribute( 'contentobject_id' ) ) );
        $workflowSections = explode( ',', $event->attribute( 'data_text1' ) );
        $workflowGroups =   $event->attribute( 'data_text2' ) == '' ? array() : explode( ',', $event->attribute( 'data_text2' ) );
        $editors =          $event->attribute( 'data_text3' ) == '' ? array() : explode( ',', $event->attribute( 'data_text3' ) );
        $approveGroups =    $event->attribute( 'data_text4' ) == '' ? array() : explode( ',', $event->attribute( 'data_text4' ) );
        $languageMask = $event->attribute( 'data_int2' );

        eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $user, 'approveLocationType::execute::user' );
        eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $userGroups, 'approveLocationType::execute::userGroups' );
        eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $editors, 'approveLocationType::execute::editor' );
        eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $approveGroups, 'approveLocationType::execute::approveGroups' );
        eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $workflowSections, 'approveLocationType::execute::workflowSections' );
        eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $workflowGroups, 'approveLocationType::execute::workflowGroups' );
        eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $languageMask, 'approveLocationType::execute::languageMask' );
        eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $object->attribute( 'section_id'), 'approveLocationType::execute::section_id' );

        $section = $object->attribute( 'section_id' );
        $correctSection = false;

        if ( !in_array( $section, $workflowSections ) && !in_array( -1, $workflowSections ) )
        {
            $assignedNodes = $object->attribute( 'assigned_nodes' );
            if ( $assignedNodes )
            {
                foreach( $assignedNodes as $assignedNode )
                {
                    $parent = $assignedNode->attribute( 'parent' );
                    $parentObject = $parent->object();
                    $section = $parentObject->attribute( 'section_id');

                    if ( in_array( $section, $workflowSections ) )
                    {
                        $correctSection = true;
                        break;
                    }
                }
            }
        }
        else
            $correctSection = true;

        $inExcludeGroups = count( array_intersect( $userGroups, $workflowGroups ) ) != 0;

        $userIsEditor = ( in_array( $user->id(), $editors ) ||
                          count( array_intersect( $userGroups, $approveGroups ) ) != 0 );

        // All languages match by default
        $hasLanguageMatch = true;
        if ( $languageMask != 0 )
        {
            // Examine if the published version contains one of the languages we
            // match for.
            // If the language ID is part of the mask the result is non-zero.
            $languageID = (int)$version->attribute( 'initial_language_id' );
            $hasLanguageMatch = (bool)( $languageMask & $languageID );
        }

        if ( $hasLanguageMatch and
             !$userIsEditor and
             !$inExcludeGroups and
             $correctSection )
        {

            /* Get user IDs from approve user groups */
            $userClassIDArray = eZUser::contentClassIDs();
            $approveUserIDArray = array();
            foreach ( $approveGroups as $approveUserGroupID )
            {
                if (  $approveUserGroupID != false )
                {
                    $approveUserGroup = eZContentObject::fetch( $approveUserGroupID );
                    if ( isset( $approveUserGroup ) )
                    {
                        foreach ( $approveUserGroup->attribute( 'assigned_nodes' ) as $assignedNode )
                        {
                            $userNodeArray = $assignedNode->subTree( array( 'ClassFilterType' => 'include',
                                                                            'ClassFilterArray' => $userClassIDArray,
                                                                            'Limitation' => array() ) );
                            foreach ( $userNodeArray as $userNode )
                            {
                                $approveUserIDArray[] = $userNode->attribute( 'contentobject_id' );
                            }
                        }
                    }
                }
            }
            $approveUserIDArray = array_merge( $approveUserIDArray, $editors );
            $approveUserIDArray = array_unique( $approveUserIDArray );

            $db = eZDb::instance();
            $taskResult = $db->arrayQuery( 'select workflow_process_id, collaboration_id from ezxapprovelocation_items where workflow_process_id = ' . $process->attribute( 'id' ) . ' and target_node_ids = ' . $targetNodeIDs[0] );
            eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $process->attribute( 'event_state'), 'approve $process->attribute( \'event_state\')' );

            if( count( $taskResult ) > 0 && $taskResult[0]['collaboration_id'] !== false )
			{
				$collaborationID = $taskResult[0]['collaboration_id'];

				$status = eZWorkflowType::STATUS_DEFERRED_TO_CRON_REPEAT;

				if( $process->attribute( 'event_state') == self::COLLABORATION_NOT_CREATED )
	            {
	                approveLocationCollaborationHandler::activateApproval( $collaborationID );
	                $this->setInformation( "We are going to create again approval" );
	                $process->setAttribute( 'event_state', self::COLLABORATION_CREATED );
	                $process->store();
	                eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $this, 'approve re-execute' );
	                $status = eZWorkflowType::STATUS_DEFERRED_TO_CRON_REPEAT;
	            }
	            else //approveLocationType::COLLABORATION_CREATED
	            {
	                $this->setInformation( "we are checking approval now" );
	                eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $event, 'check approval' );

	                $status = $this->checkApproveCollaboration(  $process, $event,  $collaborationID );
	            }

				return $status;
			}
	    	else
	    	{
	            eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $targetNodeIDs, 'NodeIDs to approve' );
	            $this->createApproveCollaboration( $process, $event, $userID, $object->attribute( 'id' ), $versionID, $approveUserIDArray );
                $this->setInformation( "We are going to create approval" );
                $process->setAttribute( 'event_state', self::COLLABORATION_CREATED );
                $process->store();
                eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $this, 'approve execute' );
                return eZWorkflowType::STATUS_DEFERRED_TO_CRON_REPEAT;
	        }
    	}
        else
        {
            eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $workflowSections , "we are not going to create approval " . $object->attribute( 'section_id') );
            eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $userGroups, "we are not going to create approval" );
            eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $workflowGroups,  "we are not going to create approval" );
            eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $user->id(), "we are not going to create approval "  );
            eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $targetNodeIDs, 'NodeIDs approved' );

            return eZWorkflowType::STATUS_ACCEPTED;
        }
    }


function validateUserIDList( $userIDList, &$reason )
    {
        $returnState = eZInputValidator::STATE_ACCEPTED;
        foreach ( $userIDList as $userID )
        {
            if ( !is_numeric( $userID ) or
                 !eZUser::isUserObject( eZContentObject::fetch( $userID ) ) )
            {
                $returnState = eZInputValidator::STATE_INVALID;
                $reason[ 'list' ][] = $userID;
            }
        }
        $reason[ 'text' ] = "Some of passed user IDs are not valid, must be IDs of existing users only.";
        return $returnState;
    }

    function validateGroupIDList( $userGroupIDList, &$reason )
    {
        $returnState = eZInputValidator::STATE_ACCEPTED;
        $groupClassNames = eZUser::fetchUserGroupClassNames();
        if ( count( $groupClassNames ) > 0 )
        {
            foreach( $userGroupIDList as $userGroupID )
            {
                if ( !is_numeric( $userGroupID ) or
                     !is_object( $userGroup = eZContentObject::fetch( $userGroupID ) ) or
                     !in_array( $userGroup->attribute( 'class_identifier' ), $groupClassNames ) )
                {
                    $returnState = eZInputValidator::STATE_INVALID;
                    $reason[ 'list' ][] = $userGroupID;
                }
            }
            $reason[ 'text' ] = "Some of passed user-group IDs are not valid, must be IDs of existing user groups only.";
        }
        else
        {
            $returnState = eZInputValidator::STATE_INVALID;
            $reason[ 'text' ] = "There is no one user-group classes among the user accounts, please choose standalone users.";
        }
        return $returnState;
    }

    function validateHTTPInput( $http, $base, $workflowEvent, &$validation )
    {
        $returnState = eZInputValidator::STATE_ACCEPTED;
        $reason = array();

        if ( !$http->hasSessionVariable( 'BrowseParameters' ) )
        {
            // check approve-users
            $approversIDs = array_unique( $this->attributeDecoder( $workflowEvent, 'approve_users' ) );
            if ( is_array( $approversIDs ) and
                 count( $approversIDs ) > 0 )
            {
                $returnState = self::validateUserIDList( $approversIDs, $reason );
            }
            else
                $returnState = false;

            if ( $returnState != eZInputValidator::STATE_INVALID )
            {
                // check approve-groups
                $userGroupIDList = array_unique( $this->attributeDecoder( $workflowEvent, 'approve_groups' ) );
                if ( is_array( $userGroupIDList ) and
                     count( $userGroupIDList ) > 0 )
                {
                    $returnState = self::validateGroupIDList( $userGroupIDList, $reason );
                }
                else if ( $returnState === false )
                {
                    // if no one user or user-group was passed as approvers
                    $returnState = eZInputValidator::STATE_INVALID;
                    $reason[ 'text' ] = "There must be passed at least one valid user or user group who approves content for the event.";
                }

                // check excluded-users
                /*
                if ( $returnState != eZInputValidator::STATE_INVALID )
                {
                    // TODO:
                    // ....
                }
                */

                // check excluded-groups
                if ( $returnState != eZInputValidator::STATE_INVALID )
                {
                    $userGroupIDList = array_unique( $this->attributeDecoder( $workflowEvent, 'selected_usergroups' ) );
                    if ( is_array( $userGroupIDList ) and
                         count( $userGroupIDList ) > 0 )
                    {
                        $returnState = eZApproveType::validateGroupIDList( $userGroupIDList, $reason );
                    }
                }
            }
        }
        else
        {
            $browseParameters = $http->sessionVariable( 'BrowseParameters' );
            if ( isset( $browseParameters['custom_action_data'] ) )
            {
                $customData = $browseParameters['custom_action_data'];
                if ( isset( $customData['event_id'] ) and
                     $customData['event_id'] == $workflowEvent->attribute( 'id' ) )
                {
                    if ( !$http->hasPostVariable( 'BrowseCancelButton' ) and
                         $http->hasPostVariable( 'SelectedObjectIDArray' ) )
                    {
                        $objectIDArray = $http->postVariable( 'SelectedObjectIDArray' );
                        if ( is_array( $objectIDArray ) and
                             count( $objectIDArray ) > 0 )
                        {
                            switch( $customData['browse_action'] )
                            {
                            case "AddApproveUsers":
                                {
                                    $returnState = eZApproveType::validateUserIDList( $objectIDArray, $reason );
                                } break;
                            case 'AddApproveGroups':
                            case 'AddExcludeUser':
                                {
                                    $returnState = eZApproveType::validateGroupIDList( $objectIDArray, $reason );
                                } break;
                            case 'AddExcludedGroups':
                                {
                                    // TODO:
                                    // .....
                                } break;
                            }
                        }
                    }
                }
            }
        }

        if ( $returnState == eZInputValidator::STATE_INVALID )
        {
            $validation[ 'processed' ] = true;
            $validation[ 'events' ][] = array( 'id' => $workflowEvent->attribute( 'id' ),
                                               'placement' => $workflowEvent->attribute( 'placement' ),
                                               'workflow_type' => &$this,
                                               'reason' => $reason );
        }
        return $returnState;
    }


    function fetchHTTPInput( $http, $base, $event )
    {
        $sectionsVar = $base . "_event_approvelocation_section_" . $event->attribute( "id" );
        if ( $http->hasPostVariable( $sectionsVar ) )
        {
            $sectionsArray = $http->postVariable( $sectionsVar );
            if ( in_array( '-1', $sectionsArray ) )
            {
                $sectionsArray = array( -1 );
            }
            $sectionsString = implode( ',', $sectionsArray );
            $event->setAttribute( "data_text1", $sectionsString );
        }

        $languageVar = $base . "_event_approvelocation_languages_" . $event->attribute( "id" );
        if ( $http->hasPostVariable( $languageVar ) )
        {
            $languageArray = $http->postVariable( $languageVar );
            if ( in_array( '-1', $languageArray ) )
            {
                $languageArray = array();
            }
            $languageMask = 0;
            foreach ( $languageArray as $languageID )
            {
                $languageMask |= $languageID;
            }
            $event->setAttribute( "data_int2", $languageMask );
        }

        $versionOptionVar = $base . "_event_approvelocation_version_option_" . $event->attribute( "id" );
        if ( $http->hasPostVariable( $versionOptionVar ) )
        {
            $versionOptionArray = $http->postVariable( $versionOptionVar );
            $versionOption = 0;
            if ( is_array( $versionOptionArray ) )
            {
                foreach ( $versionOptionArray as $vv )
                {
                    $versionOption = $versionOption | $vv;
                }
            }
            $versionOption = $versionOption & eZApproveType::VERSION_OPTION_ALL;
            $event->setAttribute( 'data_int3', $versionOption );
        }

        if ( $http->hasSessionVariable( 'BrowseParameters' ) )
        {
            $browseParameters = $http->sessionVariable( 'BrowseParameters' );
            if ( isset( $browseParameters['custom_action_data'] ) )
            {
                $customData = $browseParameters['custom_action_data'];
                if ( isset( $customData['event_id'] ) &&
                     $customData['event_id'] == $event->attribute( 'id' ) )
                {
                    if ( !$http->hasPostVariable( 'BrowseCancelButton' ) and
                         $http->hasPostVariable( 'SelectedObjectIDArray' ) )
                    {
                        $objectIDArray = $http->postVariable( 'SelectedObjectIDArray' );
                        if ( is_array( $objectIDArray ) and
                             count( $objectIDArray ) > 0 )
                        {

                            switch( $customData['browse_action'] )
                            {
                            case 'AddApproveUsers':
                                {
                                    foreach( $objectIDArray as $key => $userID )
                                    {
                                        if ( !eZUser::isUserObject( eZContentObject::fetch( $userID ) ) )
                                        {
                                            unset( $objectIDArray[$key] );
                                        }
                                    }
                                    $event->setAttribute( 'data_text3', implode( ',',
                                                                                 array_unique( array_merge( $this->attributeDecoder( $event, 'approve_users' ),
                                                                                                            $objectIDArray ) ) ) );
                                } break;

                            case 'AddApproveGroups':
                                {
                                    $event->setAttribute( 'data_text4', implode( ',',
                                                                                 array_unique( array_merge( $this->attributeDecoder( $event, 'approve_groups' ),
                                                                                                            $objectIDArray ) ) ) );
                                } break;

                            case 'AddExcludeUser':
                                {
                                    $event->setAttribute( 'data_text2', implode( ',',
                                                                                 array_unique( array_merge( $this->attributeDecoder( $event, 'selected_usergroups' ),
                                                                                                            $objectIDArray ) ) ) );
                                } break;

                            case 'AddExcludedGroups':
                                {
                                    // TODO:
                                    // .....
                                } break;
                            }
                        }
                        $http->removeSessionVariable( 'BrowseParameters' );
                    }
                }
            }
        }
    }


    function createApproveCollaboration( $process, $event, $userID, $contentobjectID, $contentobjectVersion, $editors )
    {
	 	$parameters = $process->attribute( 'parameter_list' );
    	$targetNodeIDs = $parameters['select_node_id_array'];
        if ( $editors === null )
            return false;
        $authorID = $userID;

        $collaborationItem = approveLocationCollaborationHandler::createApproval( $contentobjectID, $contentobjectVersion,
                                                                            $authorID, $editors );
        $db = eZDb::instance();
        $db->query( 'INSERT INTO ezxapprovelocation_items( workflow_process_id, collaboration_id, target_node_ids )
                       VALUES(' . $process->attribute( 'id' ) . ',' . $collaborationItem->attribute( 'id' ) . ', \'' . json_encode($targetNodeIDs) . '\'  ) ' );
    }


    function checkApproveCollaboration( $process, $event, $collaborationID = 0 )
    {
    	var_dump('check again approval');
        $db = eZDb::instance();
        if( $collaborationID > 0 )
        {
        	$taskResult = $db->arrayQuery( 'select workflow_process_id, collaboration_id, target_node_ids from ezxapprovelocation_items where collaboration_id = ' . $collaborationID  );
        }
        else
        {
        	$taskResult = $db->arrayQuery( 'select workflow_process_id, collaboration_id, target_node_ids from ezxapprovelocation_items where workflow_process_id = ' . $process->attribute( 'id' )  );
        }

        $collaborationID = $taskResult[0]['collaboration_id'];
        $collaborationItem = eZCollaborationItem::fetch( $collaborationID );
        $approvalStatus = approveLocationCollaborationHandler::checkApproval( $collaborationID );
        $targetNodeIDs = json_decode( $taskResult[0]['target_node_ids'] );

        if ( $approvalStatus == approveLocationCollaborationHandler::STATUS_WAITING )
        {
            eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $event, 'approval still waiting' );
            return eZWorkflowType::STATUS_DEFERRED_TO_CRON_REPEAT;
        }
        elseif ( $approvalStatus == approveLocationCollaborationHandler::STATUS_ACCEPTED )
        {
            eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $event, 'approval was accepted' );
            eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $targetNodeIDs, 'add new location(s)' );

            return eZWorkflowType::STATUS_ACCEPTED;

        }
        elseif ( $approvalStatus == approveLocationCollaborationHandler::STATUS_DENIED or
                 $approvalStatus == approveLocationCollaborationHandler::STATUS_DEFERRED )
        {
            eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $event, 'approval was denied' );
            $status = eZWorkflowType::STATUS_WORKFLOW_CANCELLED;
        }
        else
        {
            eZDebugSetting::writeDebug( 'ezworkflowcollection-workflow-approve-location', $event, "approval unknown status '$approvalStatus'" );
            $status = eZWorkflowType::STATUS_WORKFLOW_CANCELLED;
        }

        if ( $approvalStatus != approveLocationCollaborationHandler::STATUS_DEFERRED )
        {
        	$db->query( 'DELETE FROM ezxapprovelocation_items WHERE workflow_process_id = ' . $process->attribute( 'id' )  );
        }

        return $status;
    }
}

eZWorkflowEventType::registerEventType( approveLocationType::WORKFLOW_TYPE_STRING, "approveLocationType" );

?>