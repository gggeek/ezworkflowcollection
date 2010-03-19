<?php
/**
 * Workflow that will multi-publish content under some other user-specified nodes
 *
 * @author G. Giunta
 * @version $Id$
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 * @copyright (C) G. Giunta 2010
 *
 * @todo verify it this workflow event can work as pre-publish too
 * @todo use a static nclass var for storing data in-between function calls instead of $globals
 */

class multiPublishType extends eZWorkflowEventType
{
    const WORKFLOW_TYPE_STRING = 'multipublish';

    // register workflow event as available for post-publish only
    function __construct()
    {
        $this->eZWorkflowEventType( multiPublishType::WORKFLOW_TYPE_STRING, ezi18n( 'extension/ezworkflowcollection', 'Multipublish' ) );
        $this->setTriggerTypes( array( 'content' => array( 'publish' => array ( 'after' ) ) ) );
    }

    function execute( $process, $event )
    {
        // get object being published
        $parameters = $process->attribute( 'parameter_list' );
        $objectId = $parameters['object_id'];
        eZDebug::writeDebug( 'MultiPublish event begins execution for object ' . $objectId );

        $object = eZContentObject::fetch( $objectId );
        if ( $object != null )
        {
            // check if this workflow event has a filter on class attributes
            $waitUntilDateObject = $this->workflowEventContent( $event );
            $waitUntilDateEntryList = $waitUntilDateObject->attribute( 'classattribute_id_list' );
            if ( count( $waitUntilDateEntryList ) )
            {
                // if it has, verify if it matches the current object
                $ok = false;
                $version = $object->version( $parameters['version'] );
                $objectAttributes = $version->attribute( 'contentobject_attributes' );
                foreach ( $objectAttributes as $objectAttribute )
                {
                    $contentClassAttributeID = $objectAttribute->attribute( 'contentclassattribute_id' );
                    if ( in_array( $objectAttribute->attribute( 'contentclassattribute_id' ), $waitUntilDateEntryList ) )
                    {
                        if ( $objectAttribute->attribute( 'data_type_string' ) == 'ezboolean' )
                        {
                            $value = $objectAttribute->attribute( 'content' );
                            if ( $value )
                            {
                                $ok = true;
                                break;
                            }
                        }
                        else
                        {
                            eZDebug::writeDebug( 'MultiPublish event set up for an attribute of non-boolean datatype: ' . $objectAttribute->attribute( 'data_type_string' ) );
                        }
                    }
                }
                // no matching attribute: return without processing
                if ( !$ok )
                {
                    return eZWorkflowType::STATUS_ACCEPTED;
                }
            }

            // get list of nodes this object is already child of
            $assigned_nodes = $object->attribute( 'assigned_nodes' );
    		foreach( $assigned_nodes as $assigned_node )
    		{
    			$parent = $assigned_node->attribute( 'parent' );
    			$parents[] = $parent->attribute( 'node_id' );
    		}

            // get list of target nodes for this event
            // NB: even if adding a location fails, we go on with the workflow, and merely log the error
            $targetnodes = $event->attribute( 'target_nodes' );
            foreach( $targetnodes as $targetnode )
            {

                if ( in_array( $targetnode, $parents ) )
                {
                    eZDebug::writeDebug( 'MultiPublish event skipping location ' . $targetnode . ' object already there' );
                }
                else
                {
                    // check that node exists
                    $target = eZContentObjectTreeNode::fetch( $targetnode );
                    if ( $target != null )
                    {
                        eZDebug::writeDebug( 'MultiPublish event adding location ' . $targetnode );
            			// assign object to node
        			    $nodeAssignment = $object->addLocation( $targetnode );
            			if( !$nodeAssignment )
            			{
            				 eZDebug::writeError( 'MultiPublish event failed to add object ' . $objectId . ' to node ' . $targetnode );
            			}
                    }
                    else
                    {
                        eZDebug::writeError( 'MultiPublish event triggered for inexisting target node: ' . $targetnode . ' for object ' . $objectId );
                    }
                }
            }
        }
        else
        {
            eZDebug::writeError( 'MultiPublish event triggered for inexisting object: ' . $objectId );
            return eZWorkflowType::STATUS_WORKFLOW_CANCELLED;
        }
        return eZWorkflowType::STATUS_ACCEPTED;
    }

    // extra attributes (used in defining events, but with global values, ie. defined per typ, not per event)
    function attributes()
    {
        return array_merge( array( 'contentclass_list' ),
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
            case 'contentclass_list' :
                return eZContentClass::fetchList( eZContentClass::VERSION_STATUS_DEFINED, true );
                break;
            default:
                return eZWorkflowEventType::attribute( $attr );
        }
    }

    // management of the class / class attributes list: code stolen from ezwaituntildate
    function customWorkflowEventHTTPAction( $http, $action, $workflowEvent )
    {
        $id = $workflowEvent->attribute( 'id' );
        switch ( $action )
        {
            case "new_classelement" :
                $waitUntilDate = $workflowEvent->content( );

                $classIDList = $http->postVariable( 'WorkflowEvent' . '_event_multipublish_' . 'class_' . $id  );
                $classAttributeIDList = $http->postVariable( 'WorkflowEvent' . '_event_multipublish_' . 'classattribute_' . $id  );

                $waitUntilDate->addEntry(  $classAttributeIDList[0], $classIDList[0] );
                $workflowEvent->setContent( $waitUntilDate );
                break;
            case "remove_selected" :
                $version = $workflowEvent->attribute( 'version' );
                $postvarname = 'WorkflowEvent' . '_data_multipublish_remove_' . $id;
                $arrayRemove = $http->postVariable( $postvarname );
                $waitUntilDate = $workflowEvent->content( );

                foreach( $arrayRemove as $entryID )
                {
                    $waitUntilDate->removeEntry( $id, $entryID, $version );
                }
                break;
            case "load_class_attribute_list" :
                $postvarname = 'WorkflowEvent' . '_event_multipublish_class_' . $id;
                if ( $http->hasPostVariable( $postvarname ) )
                {
                    $classIDList = $http->postVariable( $postvarname );
                    $GLOBALS['multiPublishTypeSelectedClass'][$id] = $classIDList[0];
                }
                else
                {
                    eZDebug::writeError( 'MultiPublish event definition - no class selected' );
                }
                break;
            default :
                eZDebug::writeError( 'MultiPublish event definition - unknown custom HTTP action: ' . $action );
        }

    }

    // storage/retrieval of per-event attributes values (these will be added to the event attributes)
    function typeFunctionalAttributes()
    {
        return array( 'target_nodes', 'contentclass', 'contentclassattribute_list' );
    }

    function attributeDecoder( $event, $attribute )
    {
        switch ( $attribute )
        {
            case 'target_nodes':
                if ( $event->attribute( 'data_text1' ) == '' )
                {
                    return array();
                }
                return explode( ',', $event->attribute( 'data_text1' ) );
            case 'contentclassattribute_list' :
                $id = $event->attribute( 'id' );
                if ( isset ( $GLOBALS['multiPublishTypeSelectedClass'] ) && isset ( $GLOBALS['multiPublishTypeSelectedClass'][$id] ) )
                {
                    $classID = $GLOBALS['multiPublishTypeSelectedClass'][$id];
                }
                else
                {
                    // if nothing was preselected, we will use the first one:
                    // @todo in the common case, the contentclass_list fetch will be called twice
                    $classList = $this->attribute( 'contentclass_list' );
                    if ( isset( $classList[0] ) )
                        $classID = $classList[0]->attribute( 'id' );
                    else
                        $classID = false;
                }
                if ( $classID )
                {
                   $attrlist = eZContentClassAttribute::fetchListByClassID( $classID );
                   foreach( $attrlist as  $key => $attr )
                   {
                       if ( $attr->attribute( 'data_type_string' ) != 'ezboolean' )
                       {
                           unset( $attrlist[$key] );
                       }
                   }
                   return $attrlist;
                }
                return array();
            case 'contentclass' :
                $id = $event->attribute( 'id' );
                if ( isset ( $GLOBALS['multiPublishTypeSelectedClass'] ) && isset ( $GLOBALS['multiPublishTypeSelectedClass'][$id] ) )
                {
                    return $GLOBALS['multiPublishTypeSelectedClass'][$id];
                }
                return '';
        }
        return null;
    }

    // get the values to be used for the custom attributes from http post and store them
    function fetchHTTPInput( $http, $base, $event )
    {
        $varName = $base . "_event_multipublish_target_nodes_" . $event->attribute( "id" );
        if ( $http->hasPostVariable( $varName ) )
        {
            $targetNodes = $http->postVariable( $varName );
            // clean up the stuff
            if ( $targetNodes != '' )
            {
                $targetNodes = implode( ',', array_unique( array_map( 'intval', explode( ',', $targetNodes) ) ) );
            }
            $event->setAttribute( "data_text1", $targetNodes );
        }
        else
        {
            /// @todo log some warning ???
        }
    }

    function workflowEventContent( $event )
    {
        $id = $event->attribute( "id" );
        $version = $event->attribute( "version" );
        return new eZWaitUntilDate( $id, $version );
    }

    function storeEventData( $event, $version )
    {
        $event->content()->setVersion( $version );
    }

    function storeDefinedEventData( $event )
    {
        $id = $event->attribute( 'id' );
        $version = 1;
        $waitUntilDateVersion1 = new eZWaitUntilDate( $id, $version );
        $waitUntilDateVersion1->setVersion( 0 ); //strange name but we are creating version 0 here
        eZWaitUntilDate::removeWaitUntilDateEntries( $id, 1 );
    }

}

eZWorkflowEventType::registerEventType( multiPublishType::WORKFLOW_TYPE_STRING, 'multipublishtype' );

?>