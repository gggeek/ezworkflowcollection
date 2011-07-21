<?php
//
// Definition of approveLocationCollaborationHandler class
//
// Created on: <12-Mar-2010 14:31:11 op>
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: eZ Publish
// SOFTWARE RELEASE: 4.3.0
// COPYRIGHT NOTICE: Copyright (C) 1999-2010 eZ Systems AS
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
// ## END COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
//

/*! \file
*/

/*!
  \class approveLocationCollaborationHandler approvelocationcollaborationhandler.php
  \brief Handles approval communication using the collaboration system

  The handler uses the fields data_int1, data_int2 and data_int3 to store
  information on the contentobject and the approval status.

  - data_int1 - The content object ID
  - data_int2 - The content object version
  - data_int3 - The status of the approval, see defines.

*/

require_once( 'kernel/common/i18n.php' );

class approveLocationCollaborationHandler extends eZCollaborationItemHandler
{
    /// Approval message type
    const MESSAGE_TYPE_APPROVE = 1;

    /// Default status, no approval decision has been made
    const STATUS_WAITING = 0;

    /// The location was approved and will be published.
    const STATUS_ACCEPTED = 1;

    /// The location was denied and will be archived.
    const STATUS_DENIED = 2;

    /// The location was deferred and will deleted.
    const STATUS_DEFERRED = 3;

    /*!
     Initializes the handler
    */
    function approveLocationCollaborationHandler()
    {
        $this->eZCollaborationItemHandler( approveLocationType::WORKFLOW_TYPE_STRING,
                                           ezpI18n::tr( 'ezworkflows/classes', 'Location approval' ),
                                           array( 'use-messages' => true,
                                                  'notification-types' => true,
                                                  'notification-collection-handling' => eZCollaborationItemHandler::NOTIFICATION_COLLECTION_PER_PARTICIPATION_ROLE ) );
    }

    function title( $collaborationItem )
    {
        return ezpI18n::tr( 'ezworkflows/classes', 'Location approval' );
    }

    function content( $collaborationItem )
    {
 		$db = eZDb::instance();
		$taskResult = $db->arrayQuery( 'select target_node_ids from ezxapprovelocation_items where collaboration_id = ' . $collaborationItem->attribute( 'id' )  );
        return array( "content_object_id" => $collaborationItem->attribute( "data_int1" ),
                      "content_object_version" => $collaborationItem->attribute( "data_int2" ),
                      "approval_status" => $collaborationItem->attribute( "data_int3" ),
                      "target_node_ids" => json_decode( $taskResult[0]['target_node_ids'], true ) );
    }

    function notificationParticipantTemplate( $participantRole )
    {
        if ( $participantRole == eZCollaborationItemParticipantLink::ROLE_APPROVER )
        {
            return 'approvelocation.tpl';
        }
        else if ( $participantRole == eZCollaborationItemParticipantLink::ROLE_AUTHOR )
        {
            return 'author.tpl';
        }
        else
            return false;
    }

    /*!
     \return the content object version object for the collaboration item \a $collaborationItem
    */
    static function contentObjectVersion( $collaborationItem )
    {
        $contentObjectID = $collaborationItem->contentAttribute( 'content_object_id' );
        $contentObjectVersion = $collaborationItem->contentAttribute( 'content_object_version' );
        return eZContentObjectVersion::fetchVersion( $contentObjectVersion, $contentObjectID );
    }

    /*!
     \return the content object for the collaboration item \a $collaborationItem
    */
    static function contentObject( $collaborationItem )
    {
        $contentObjectID = $collaborationItem->contentAttribute( 'content_object_id' );
        return eZContentObject::fetch( $contentObjectID );
    }


    /*!
     Updates the last_read for the participant link.
    */
    function readItem( $collaborationItem, $viewMode = false )
    {
        $collaborationItem->setLastRead();
    }

    /*!
     \return the number of messages for the approve item.
    */
    function messageCount( $collaborationItem )
    {
        return eZCollaborationItemMessageLink::fetchItemCount( array( 'item_id' => $collaborationItem->attribute( 'id' ) ) );
    }

    /*!
     \return the number of unread messages for the approve item.
    */
    function unreadMessageCount( $collaborationItem )
    {
        $lastRead = 0;
        $status = $collaborationItem->attribute( 'user_status' );
        if ( $status )
            $lastRead = $status->attribute( 'last_read' );
        return eZCollaborationItemMessageLink::fetchItemCount( array( 'item_id' => $collaborationItem->attribute( 'id' ),
                                                                      'conditions' => array( 'modified' => array( '>', $lastRead ) ) ) );
    }

    /*!
     \static
     \return the status of the approval collaboration item \a $approvalID.
    */
    static function checkApproval( $approvalID )
    {
        $collaborationItem = eZCollaborationItem::fetch( $approvalID );
        if ( $collaborationItem !== null )
        {
            return $collaborationItem->attribute( 'data_int3' );
        }
        return false;
    }

    /*!
     \static
     \return makes sure the approval item is activated for all participants \a $approvalID.
    */
    static function activateApproval( $approvalID )
    {
        $collaborationItem = eZCollaborationItem::fetch( $approvalID );
        if ( $collaborationItem !== null )
        {
            $collaborationItem->setAttribute( 'data_int3', self::STATUS_WAITING );
            $collaborationItem->setAttribute( 'status', eZCollaborationItem::STATUS_ACTIVE );
            $timestamp = time();
            $collaborationItem->setAttribute( 'modified', $timestamp );
            $collaborationItem->store();
            $participantList = eZCollaborationItemParticipantLink::fetchParticipantList( array( 'item_id' => $approvalID ) );
            foreach( $participantList as $participantLink )
            {
                $collaborationItem->setIsActive( true, $participantLink->attribute( 'participant_id' ) );
            }
            return true;
        }
        return false;
    }

    /*!
     Creates a new approval collaboration item which will approve the content object \a $contentObjectID
     with version \a $contentObjectVersion.
     The item will be added to the author \a $authorID and the approver array \a $approverIDArray.
     \return the collaboration item.
    */
    static function createApproval( $contentObjectID, $contentObjectVersion, $authorID, $approverIDArray )
    {
        $collaborationItem = eZCollaborationItem::create( approveLocationType::WORKFLOW_TYPE_STRING, $authorID );
        $collaborationItem->setAttribute( 'data_int1', $contentObjectID );
        $collaborationItem->setAttribute( 'data_int2', $contentObjectVersion );
        $collaborationItem->setAttribute( 'data_int3', false );
        $collaborationItem->store();
        $collaborationID = $collaborationItem->attribute( 'id' );

        $participantList = array( array( 'id' => array( $authorID ),
                                         'role' => eZCollaborationItemParticipantLink::ROLE_AUTHOR ),
                                  array( 'id' => $approverIDArray,
                                         'role' => eZCollaborationItemParticipantLink::ROLE_APPROVER ) );
        foreach ( $participantList as $participantItem )
        {
            foreach( $participantItem['id'] as $participantID )
            {
                $participantRole = $participantItem['role'];
                $link = eZCollaborationItemParticipantLink::create( $collaborationID, $participantID,
                                                                    $participantRole, eZCollaborationItemParticipantLink::TYPE_USER );
                $link->store();

                $profile = eZCollaborationProfile::instance( $participantID );
                $groupID = $profile->attribute( 'main_group' );
                eZCollaborationItemGroupLink::addItem( $groupID, $collaborationID, $participantID );
            }
        }

        // Create the notification
        $collaborationItem->createNotificationEvent();
        return $collaborationItem;
    }

    /*!
     Adds a new comment, approves the item or denies the item.
    */
    function handleCustomAction( $module, $collaborationItem )
    {
        $redirectView = 'item';
        $redirectParameters = array( 'full', $collaborationItem->attribute( 'id' ) );
        $addComment = false;

        if ( $this->isCustomAction( 'Comment' ) )
        {
            $addComment = true;
        }
        else if ( $this->isCustomAction( 'Accept' ) or
                  $this->isCustomAction( 'Deny' ) or
                  $this->isCustomAction( 'Defer' ) )
        {
            // check user's rights to approve
            $user = eZUser::currentUser();
            $userID = $user->attribute( 'contentobject_id' );
            $participantList = eZCollaborationItemParticipantLink::fetchParticipantList( array( 'item_id' => $collaborationItem->attribute( 'id' ) ) );

            $approveAllowed = false;
            foreach( $participantList as $participant )
            {
                if ( $participant->ParticipantID == $userID &&
                     $participant->ParticipantRole == eZCollaborationItemParticipantLink::ROLE_APPROVER )
                {
                    $approveAllowed = true;
                    break;
                }
            }
            if ( !$approveAllowed )
            {
                return $module->redirectToView( $redirectView, $redirectParameters );
            }

            $contentObjectVersion = $this->contentObjectVersion( $collaborationItem );
            $status = self::STATUS_DENIED;
            if ( $this->isCustomAction( 'Accept' ) )
                $status = self::STATUS_ACCEPTED;
            else if ( $this->isCustomAction( 'Defer' ) or
                      $this->isCustomAction( 'Deny' ) )
                $status = self::STATUS_DENIED;
            $collaborationItem->setAttribute( 'data_int3', $status );
            $collaborationItem->setAttribute( 'status', eZCollaborationItem::STATUS_INACTIVE );
            $timestamp = time();
            $collaborationItem->setAttribute( 'modified', $timestamp );
            $collaborationItem->setIsActive( false );
            $redirectView = 'view';
            $redirectParameters = array( 'summary' );
            $addComment = true;
        }
        if ( $addComment )
        {
            $messageText = $this->customInput( 'ApproveLocationComment' );
            if ( trim( $messageText ) != '' )
            {
                $message = eZCollaborationSimpleMessage::create( approveLocationType::WORKFLOW_TYPE_STRING.'_comment', $messageText );
                $message->store();
                eZCollaborationItemMessageLink::addMessage( $collaborationItem, $message, self::MESSAGE_TYPE_APPROVE );
            }
        }
        $collaborationItem->sync();
        return $module->redirectToView( $redirectView, $redirectParameters );
    }

}

?>
