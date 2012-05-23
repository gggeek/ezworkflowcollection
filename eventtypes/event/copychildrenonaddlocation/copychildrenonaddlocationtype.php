<?php
/**
 * When a location is added to a node, this event will copy any existing children
 * to the new location as well.
 *
 * @todo add a setting which allows/prevents recursion on children
 *
 * @author G. Giunta
 * @license Licensed under GNU General Public License v2.0. See file LICENSE
 * @copyright (C) G. Giunta 2012
 */

class copyChildrenOnAddLocationType extends eZWorkflowEventType
{
    const EVENT_TYPE = 'copychildrenonaddlocation';
    const EVENT_NAME = 'Copy all existing children to new location (when adding a location)';
    const EVENT_CLASS = __CLASS__;

    public function __construct()
    {
        parent::eZWorkflowEventType( self::EVENT_TYPE, self::EVENT_NAME );
        $this->setTriggerTypes( array( 'content' => array( 'addlocation' => array( 'after' ) ) ) );
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

        // new node(s) parent(s)
        $assignedParentNodeIDs = $params['select_node_id_array'];
        if ( empty( $assignedParentNodeIDs ) )
        {
            return eZWorkflowType::STATUS_ACCEPTED;
        }

        // current node(s)
        $currentNodes = $object->assignedNodes();
        // Not sure this can happen on a post-publish event, but it does not hurt to check
        if ( $currentNodes == null )
        {
            return eZWorkflowType::STATUS_ACCEPTED;
        }

        // calculate array of newly created nodes
        $assignedNodeIDs = array();
        foreach( $currentNodes as $node )
        {
            // if this is one of the new nodes, no need to check its children
            if ( in_array( $node->attribute( 'parent_node_id' ), $assignedParentNodeIDs ) )
            {
                //eZDebug::writeDebug( "Adding location: " . $node->attribute( 'node_id' ), __METHOD__ );
                $assignedNodeIDs[] = $node->attribute( 'node_id' );
            }
        }

        $checkedObjs = array();
        // loop an all existing nodes of object
        foreach( $currentNodes as $node )
        {
            // if this is one of the new nodes, no need to check its children
            if ( in_array( $node->attribute( 'parent_node_id' ), $assignedParentNodeIDs ) )
            {
                continue;
            }

            //eZDebug::writeDebug( "Checking children of existing location: " . $node->attribute( 'node_id' ), __METHOD__ );

            // find all children of object at this preexisting location
            foreach( $node->attribute( 'children' ) as $childNode )
            {
                // check if child is already also a child of the new locations
                // (avoid checking the same object many times)
                $childObj = $childNode->attribute( 'object' );
                //eZDebug::writeDebug( "Found child obj: " . $childObj->attribute( 'id' ), __METHOD__ );
                if ( !in_array( $childObj->attribute( 'id' ), $checkedObjs ) )
                {
                    $childParentNodeIds = $childObj->attribute( 'parent_nodes' );
                    foreach( $assignedNodeIDs as $newNodeId )
                    {
                        //eZDebug::writeDebug( "Checking it against new node: " . $newNodeId, __METHOD__ );
                        if ( !in_array( $newNodeId, $childParentNodeIds ) )
                        {
                            //eZDebug::writeDebug( "Obj is not child of new node, adding it", __METHOD__ );
                            // this child is not a child of new parent $newNode: add it there
                            $operationResult = eZOperationHandler::execute(
                                'content',
                                'addlocation',
                                array(
                                    'node_id' => $childObj->attribute( 'main_node_id' ),
                                    'object_id' => $childObj->attribute( 'id' ),
                                    'select_node_id_array' => array( $newNodeId ) ),
                                null,
                                true );
                            //eZDebug::writeDebug( var_export( $operationResult, true ), __METHOD__ );
                            if ( $operationResult == null || $operationResult['status'] != true )
                            {
                                eZDebug::writeError( "Unable to add new location to object: " . $childObj->attribute( 'id' ), __METHOD__ );
                            }

                            // doing the same without recursion
                            //eZContentOperationCollection::addAssignment( $childObj->attribute( 'main_node_id' ), $childObj->attribute( 'id' ),array( $newNodeId ) );
                        }
                        else
                        {
                            //eZDebug::writeDebug( "Obj is already child of new node", __METHOD__ );
                        }
                    }
                }

                $checkedObjs[] = $childObj->attribute( 'id' );
            }
        }

        return eZWorkflowType::STATUS_ACCEPTED;

    }

}

eZWorkflowEventType::registerEventType(
    copyChildrenOnAddLocationType::EVENT_TYPE,
    copyChildrenOnAddLocationType::EVENT_CLASS
);

