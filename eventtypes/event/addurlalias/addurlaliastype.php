<?php
/**
 * Workflow event to add a 2nd url-alias to a node based on an attribute
 *
 * @author G. Giunta
 * @license Licensed under GNU General Public License v2.0. See file LICENSE
 * @copyright (C) G. Giunta 2012
 *
 * @todo add support for "<att1> <att2>" syntax just as in object name definition
 * @todo use class/attribute picker in gui
 */

class addUrlAliasType extends eZWorkflowEventType
{
    const WORKFLOW_TYPE_STRING = 'addurlalias';

    /**
     * Constructor: declare the supported triggers
     */
    public function __construct()
    {
        $this->eZWorkflowEventType( self::WORKFLOW_TYPE_STRING, ezpI18n::tr( 'ezworkflows/workflow/event', "Add Url Alias" ) );
        $this->setTriggerTypes( array( 'content' => array( 'publish' => array( 'after' ) ) ) );
    }

    /**
     * Event execution
     *
     * @todo currently the alias is created in the 1st language of the version. Use ezontentlanguage::languagesByMask() instead yto make it multilingual...
     */
    public function execute( $process, $event )
    {
        $params = $process->attribute( 'parameter_list' );
        $object_id = $params['object_id'];

        $object = eZContentObject::fetch( $object_id );
        if ( !is_object( $object ) )
        {
            eZDebug::writeError( "Unable to fetch object: '{$params['object_id']}'", __METHOD__ );
            return eZWorkflowType::STATUS_WORKFLOW_CANCELLED;
        }

        $attrname = $event->attribute( 'source_attribute' );
        $data_map = $object->attribute( 'data_map' );
        if ( !isset( $data_map[$attrname] ) )
        {
            eZDebug::writeError( "Object has no attribute $attrname", __METHOD__ );
            return eZWorkflowType::STATUS_WORKFLOW_CANCELLED;
        }

        // we use the same rules as used in generating object titles
        $url = $data_map[$attrname]->title();

        if ( $url == '' )
        {
            return eZWorkflowType::STATUS_ACCEPTED;
        }

        $version = $object->currentversion();
        $assignedNodes = $object->attribute( 'assigned_nodes' );
        if ( $assignedNodes )
        {
            foreach( $assignedNodes as $assignedNode )
            {
                $results = self::addUrlALias( $assignedNode, $url, $version->attribute( 'initial_language_id' ), $event->attribute( 'external_redirect' ), $event->attribute( 'at_root' ) );
                if ( strpos( $results['infoCode'], 'error' ) === 0 )
                {
                    /// @todo return an error / abend here?
                    eZDebug::writeError( "Error: " . $results['infoCode'], __METHOD__ );
                }
                else
                {
                    eZDebug::writeDebug( $results['infoCode'] . ' Alias: ' . $results['infoData']['new_alias'], __METHOD__ );
                }
                if ( $event->attribute( 'at_root' ) )
                {
                    // if url-alias is to be set at root, create it only once, not once-per-parent-node
                    break;
                }
            }
        }

        return eZWorkflowType::STATUS_ACCEPTED;
    }

    /**
     * Code taken from content/urlalias module
     */
    static function addUrlALias( $node, $aliasText, $languageId, $aliasRedirects = false, $parentIsRoot = false )
    {
        $infoCode = 'no-errors';

        $language = eZContentLanguage::fetch( $languageId );
        if ( !$language )
        {
            $infoCode = "error-invalid-language";
            $infoData['language'] = $languageCode;
        }
        else
        {
            $parentID = 0;
            $linkID   = 0;
            $filter = new eZURLAliasQuery();
            $filter->actions = array( 'eznode:' . $node->attribute( 'node_id' ) );
            $filter->type = 'name';
            $filter->limit = false;
            $existingElements = $filter->fetchAll();
            // TODO: add error handling when $existingElements is empty
            if ( count( $existingElements ) > 0 )
            {
                $parentID = (int)$existingElements[0]->attribute( 'parent' );
                $linkID   = (int)$existingElements[0]->attribute( 'id' );
            }
            if ( $parentIsRoot )
            {
                $parentID = 0; // Start from the top
            }
            $mask = $language->attribute( 'id' );
            $obj = $node->object();
            $alwaysMask = ( $obj->attribute( 'language_mask' ) & 1 );
            $mask |= $alwaysMask;

            $origAliasText = $aliasText;
            $result = eZURLAliasML::storePath( $aliasText, 'eznode:' . $node->attribute( 'node_id' ),
                                               $language, $linkID, $alwaysMask, $parentID,
                                               true, false, false, $aliasRedirects );
            if ( $result['status'] === eZURLAliasML::LINK_ALREADY_TAKEN )
            {
                $lastElements = eZURLAliasML::fetchByPath( $result['path'] );
                if ( count ( $lastElements ) > 0 )
                {
                    $lastElement  = $lastElements[0];
                    $infoCode = "feedback-alias-exists";
                    $infoData['new_alias'] = $aliasText;
                    $infoData['url'] = $lastElement->attribute( 'path' );
                    $infoData['action_url'] = $lastElement->actionURL();
                    //$aliasText = $origAliasText;
                }
            }
            else if ( $result['status'] === true )
            {
                $aliasText = $result['path'];
                if ( strcmp( $aliasText, $origAliasText ) != 0 )
                {
                    $infoCode = "feedback-alias-cleanup";
                    $infoData['orig_alias']  = $origAliasText;
                    $infoData['new_alias'] = $aliasText;
                }
                else
                {
                    $infoData['new_alias'] = $aliasText;
                }
                if ( $infoCode == 'no-errors' )
                {
                    $infoCode = "feedback-alias-created";
                }
                //$aliasText = false;
            }
        }
        return array( 'infoCode' => $infoCode, 'infoData' => $infoData );
    }

    /// customizable attributes

    function typeFunctionalAttributes()
    {
        return array( 'source_attribute',
                      'external_redirect',
                      'at_root' );
    }

    function attributeDecoder( $event, $attr )
    {
        switch ( $attr )
        {
            case 'source_attribute':
                return trim( $event->attribute( 'data_text1' ) );

            case 'external_redirect':
                return (bool) $event->attribute( 'data_int1' );

            case 'at_root':
                return (bool) $event->attribute( 'data_int2' );
        }
        return null;
    }

    function validateHTTPInput( $http, $base, $event, &$validation )
    {
        $http_input_sa = $base . '_event_' . self::WORKFLOW_TYPE_STRING . '_source_attribute_' . $event->attribute( 'id' );
        $http_input_er = $base . '_event_' . self::WORKFLOW_TYPE_STRING . '_external_redirect_' . $event->attribute( 'id' );
        $http_input_ar = $base . '_event_' . self::WORKFLOW_TYPE_STRING . '_at_root_' . $event->attribute( 'id' );

        /// @todo add more validation for _er and _ar: should be bool values...
        if( $http->hasPostVariable( $http_input_sa ) /*&& ( !$http->hasPostVariable( $http_input_state_er ) || $http->hasPostVariable( $http_input_group_after) && $http->hasPostVariable($http_input_state_after)*/ )
        {
            $returnState = eZInputValidator::STATE_ACCEPTED;
        }
        else
        {
            $returnState = eZInputValidator::STATE_INVALID;
            //$reason[ 'text' ] = "Select at least one group, then one state.";
        }

        return $returnState;
    }

    function fetchHTTPInput( $http, $base, $event )
    {
        $http_input_sa = $base . '_event_' . self::WORKFLOW_TYPE_STRING . '_source_attribute_' . $event->attribute( 'id' );
        $http_input_er = $base . '_event_' . self::WORKFLOW_TYPE_STRING . '_external_redirect_' . $event->attribute( 'id' );
        $http_input_ar = $base . '_event_' . self::WORKFLOW_TYPE_STRING . '_at_root_' . $event->attribute( 'id' );

        if ( $http->hasPostVariable( $http_input_sa ) )
        {
            $event->setAttribute( "data_text1", $http->postVariable( $http_input_sa ) );
        }

        if ( $http->hasPostVariable( $http_input_er ) )
        {
            $event->setAttribute( "data_int1", (bool) $http->postVariable( $http_input_er ) );
        }

        if ( $http->hasPostVariable( $http_input_ar ) )
        {
            $event->setAttribute( "data_int2", (bool) $http->postVariable( $http_input_ar ) );
        }
    }
}

eZWorkflowEventType::registerEventType( addUrlAliasType::WORKFLOW_TYPE_STRING, 'addUrlAliasType' );
