<?php
/**
 * Workflow that will expire remote caches for all nodes of an object (eg. reverse proxies).
 * Uses the eZHTTPCacheManager class and config from eZ Flow
 *
 * @author G. Giunta
 * @license Licensed under GNU General Public License v2.0. See file LICENSE
 * @copyright (C) G. Giunta 2010-2012
 *
 * @todo we could loop on all attributes on an object and skip execution if we find an ezpage,
 *       as it does the same on its own...
 */
include_once( 'kernel/common/i18n.php' );

class expireremotecacheflowType extends eZWorkflowEventType
{
    const WORKFLOW_TYPE_STRING = 'expireremotecacheflow';

    // register workflow event as available for post-publish only
    function __construct()
    {
        $this->eZWorkflowEventType( expireremotecacheflowType::WORKFLOW_TYPE_STRING, ezpI18n::tr( 'extension/ezworkflowcollection', 'Expire remote caches (ezflow based)' ) );
        $this->setTriggerTypes( array( 'content' => array( 'publish' => array ( 'after' ) ) ) );
    }

    function execute( $process, $event )
    {
        // get object being published
        $parameters = $process->attribute( 'parameter_list' );
        $objectId = $parameters['object_id'];
        eZDebug::writeDebug( 'Expire remote cache event begins execution for object ' . $objectId );

        $ini = eZINI::instance( 'ezworkflowcollection.ini' );
        $object = eZContentObject::fetch( $objectId );
        if ( $object != null )
        {
            if ( $ini->variable( 'ExpireRemoteCacheFlowSettings', 'ExpireOnlyObjectNodes' ) == 'enabled' )
            {
                // basic version
                // get list of nodes this object is published with
                $assigned_nodes = $object->attribute( 'assigned_nodes' );
            }
            else
            {
                // smart-cache enabled version
                // get list of nodes whose view-cache is expired
                $assigned_nodes = array();
                eZContentCacheManager::nodeListForObject( $object, true, eZContentCacheManager::CLEAR_DEFAULT, $assigned_nodes, $handledObjectList );
                foreach( $assigned_nodes as $i => $nodeID )
                {
                    $assigned_nodes[$i] = eZContentObjectTreeNode::fetch( $nodeID );
                }
            }

            $domains = $ini->variable( 'ExpireRemoteCacheFlowSettings', 'ExpireDomains' );
            foreach( $assigned_nodes as $assigned_node )
    		{
                // for every node, call eZHTTPCacheManager to clean the remote cache
    		    $url = $assigned_node->urlAlias();
    		    if ( is_array( $domains ) && ( count( $domains ) > 1 || ( count( $domains ) > 0 && $domains[0] != '' ) ) )
    		    {
    		        eZURI::transformURI( $url );
    		        foreach( $domains as $domain )
    		        {
    		            eZHTTPCacheManager::execute( $domain . $url );
    		        }
    		    }
    		    else
    		    {
    		        eZURI::transformURI( $url, false, 'full' );
    		        eZHTTPCacheManager::execute( $url );
    		    }
    		}
        }
        else
        {
            eZDebug::writeError( 'Expire remote cache event triggered for inexisting object: ' . $objectId );
            return eZWorkflowType::STATUS_WORKFLOW_CANCELLED;
        }
        return eZWorkflowType::STATUS_ACCEPTED;
    }

/*
    // extra attributes (used in defining events, but with global values, ie. defined per type, not per event)
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
*/

}

eZWorkflowEventType::registerEventType( expireremotecacheflowType::WORKFLOW_TYPE_STRING, 'expireremotecacheflowtype' );

?>