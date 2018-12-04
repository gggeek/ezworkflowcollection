<?php
/**
 * When a node is published, this event will check that it has a node as child of
 * all the locations of all its parents - it is useful to have a set of content
 * trees which are kept in sync at different locations (for that case, it might
 * be placed after a 1st-version-only multiplexer event)
 *
 * @author G. Giunta
 * @license Licensed under GNU General Public License v2.0. See file LICENSE
 * @copyright (C) G. Giunta 2012-2018
 */

class copyNodeToAllParentLocationsType extends eZWorkflowEventType
{
    const EVENT_TYPE = 'copynodetoallparentlocations';
    const EVENT_NAME = 'Copy a node to all locations of parent (if parent has many)';
    const EVENT_CLASS = __CLASS__;

    public function __construct()
    {
        parent::eZWorkflowEventType( self::EVENT_TYPE, self::EVENT_NAME );
        $this->setTriggerTypes( array( 'content' => array( 'publish' => array( 'after' ) ) ) );
    }

    public function execute( $process, $event )
    {
        $params = $process->attribute('parameter_list');
        $object_id = $params['object_id'];

        $object = eZContentObject::fetch( $object_id );
        if ( !is_object( $object ) )
        {
            eZDebug::writeError( "Unable to fetch object: '$object_id'", __METHOD__ );
            return eZWorkflowType::STATUS_WORKFLOW_CANCELLED;
        }

        // current parent node(s)
        $parentNodeIds = $object->attribute( 'parent_nodes' );
        $checkedObjs = array();
        foreach( $parentNodeIds as $parentNodeId )
        {
            //eZDebug::writeDebug( "Checking parent node: " . $parentNodeId, __METHOD__ );
            $parentNode = eZContentObjectTreeNode::fetch( $parentNodeId );
            $parentObj = $parentNode->attribute( 'object' );
            if ( !in_array( $parentObj->attribute( 'id' ), $checkedObjs ) )
            {
                //eZDebug::writeDebug( "Checking all nodes of parent obj: " . $parentObj->attribute( 'id' ), __METHOD__ );
                foreach(  $parentObj->attribute( 'assigned_nodes' ) as $node )
                {
                    if ( !in_array( $node->attribute( 'node_id' ), $parentNodeIds ) )
                    {
                        //eZDebug::writeDebug( "Found a node which is not parent of current obj: " . $node->attribute( 'node_id' ), __METHOD__ );
                        // the current obj has no node which is children of the given node of one of its parent objects
                        $operationResult = eZOperationHandler::execute(
                            'content',
                            'addlocation',
                            array(
                                'node_id' => $object->attribute( 'main_node_id' ),
                                'object_id' => $object->attribute( 'id' ),
                                'select_node_id_array' => array( $node->attribute( 'node_id' ) ) ),
                            null,
                            true );

                        if ( $operationResult == null || $operationResult['status'] != true )
                        {
                                eZDebug::writeError( "Unable to add new location to object: " . $object->attribute( 'id' ), __METHOD__ );
                        }
                    }
                    else
                    {
                        //eZDebug::writeDebug( "Found a node which is already parent of current obj: " . $node->attribute( 'node_id' ), __METHOD__ );
                    }
                }
            }
            $checkedObjs[] = $parentObj->attribute( 'id' );
        }

        return eZWorkflowType::STATUS_ACCEPTED;

    }

}

eZWorkflowEventType::registerEventType(
    copyNodeToAllParentLocationsType::EVENT_TYPE,
    copyNodeToAllParentLocationsType::EVENT_CLASS
);
