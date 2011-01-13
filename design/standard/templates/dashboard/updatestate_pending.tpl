{def $state_id = ezini('UpdateObjectStatesSettings', 'PendingObjectState', 'ezworkflowcollection.ini') }

<h2><a href={concat('updatestate/list/',  $state_id)|ezurl}>{'Contents waiting for publishing validation'|i18n( 'extension/ezworkflowcollection/dashboard' )}</a></h2>

{if fetch('content', 'tree_count', hash( 'parent_node_id', 1,
                                           'attribute_filter', array( 'and', array('state', "=", $state_id )),
                                           'main_node_only', true()
                                           )) }

<table class="list" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <th>{'Name'|i18n( 'design/admin/dashboard/drafts' )}</th>
        <th>{'Type'|i18n( 'design/admin/dashboard/drafts' )}</th>
        <th>{'Version'|i18n( 'design/admin/dashboard/drafts' )}</th>
        <th>{'Modified'|i18n( 'design/admin/dashboard/drafts' )}</th>
        <th class="tight"></th>
    </tr>
    {foreach fetch('content', 'tree', hash( 'parent_node_id', 1,
                                           'limit', $block.number_of_items,
                                           'attribute_filter', array( 'and', array('state', "=", $state_id)),
                                           'sort_by', array(array('modified', false() ) ),
                                           'main_node_only', true()
                                          ) ) as $content sequence array( 'bglight', 'bgdark' ) as $style}

        <tr class="{$style}">
            <td>
                <a href={$content.url_alias|ezurl} title="{$content.name|wash()}">
                    {$content.name|wash()}
                </a>
            </td>
            <td>
                {$content.object.class_name|wash()}
            </td>
            <td>
                {$content.object.current_version}
            </td>
            <td>
                {$content.object.modified|l10n('shortdatetime')}
            </td>
            <td>
                <a href={concat( '/content/edit/', $content.object.id, '/', $content.object.version, '/' )|ezurl} title="{'Edit <%draft_name>.'|i18n( 'design/admin/dashboard/drafts',, hash( '%draft_name', $content.name ) )|wash()}">
                    <img src={'edit.gif'|ezimage} border="0" alt="{'Edit'|i18n( 'design/admin/dashboard/drafts' )}" />
                </a>
            </td>
        </tr>
    {/foreach}
</table>

{else}

{'Currently you do not have any drafts available.'|i18n( 'design/admin/dashboard/drafts' )}

{/if}