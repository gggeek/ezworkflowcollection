{let item_type=ezpreference( 'admin_list_limit' )
     number_of_items=min( $item_type, 3)|choose( 10, 10, 25, 50 )
     list_count = fetch('content', 'tree_count', hash( 'parent_node_id', 1,
                                           'attribute_filter', array( 'and', array('state', "=", $state.id)),
                                           'main_node_only', true()
                                           ))
     content_list=fetch('content', 'tree', hash( 'parent_node_id', 1,
                                           'limit', $number_of_items,
                                           'offset', $view_parameters.offset,
                                           'attribute_filter', array( 'and', array('state', "=", $state.id)),
                                           'sort_by', array(array('modified', false() ) ),
                                           'main_node_only', true()
                                          ))}

<form name="listaction" action={concat( 'updatestate/list/' )|ezurl} method="post">

<div class="context-block">

{* DESIGN: Header START *}<div class="box-header"><div class="box-tc"><div class="box-ml"><div class="box-mr"><div class="box-tl"><div class="box-tr">
<h1 class="context-title">{"Contents in state"|i18n('extension/ezworkflowcollection/design/admin/updatestate/list')} <i>{$state_name}</i> [{$list_count}]</h1>


{* DESIGN: Mainline *}<div class="header-mainline"></div>

{* DESIGN: Header END *}</div></div></div></div></div></div>

{* DESIGN: Content START *}<div class="box-ml"><div class="box-mr"><div class="box-content">


{* Items per page and view mode selector. *}
<div class="context-toolbar">
<div class="block">
<div class="left">
    <p>
    {switch match=$number_of_items}
        {case match=25}
        <a href={concat('/user/preferences/set/admin_list_limit/1/updatestate/list/', $state.id)|ezurl}>10</a>
        <span class="current">25</span>
        <a href={concat('/user/preferences/set/admin_list_limit/3/updatestate/list/', $state.id)|ezurl}>50</a>
        {/case}

        {case match=50}
        <a href={concat('/user/preferences/set/admin_list_limit/1/updatestate/list/', $state.id)|ezurl}>10</a>
        <a href={concat('/user/preferences/set/admin_list_limit/2/updatestate/list/', $state.id)|ezurl}>25</a>
        <span class="current">50</span>
        {/case}

        {case}
        <span class="current">10</span>
        <a href={concat('/user/preferences/set/admin_list_limit/2/updatestate/list/', $state.id)|ezurl}>25</a>
        <a href={concat('/user/preferences/set/admin_list_limit/3/updatestate/list/', $state.id)|ezurl}>50</a>
        {/case}
    {/switch}
    </p>
</div>

<div class="right"> {"List contents in state"|i18n('extension/ezworkflowcollection/design/admin/updatestate/list')} :
	<select name="State" onChange="submit()">
	{def $ignore_states = cond( ezini_hasvariable("UpdateObjectStatesSettings", "IgnoreObjectStateIDList", "ezworkflowcollection.ini"), ezini("UpdateObjectStatesSettings", "IgnoreObjectStateIDList", "ezworkflowcollection.ini"), true(), array() ) }
	{foreach $state.group.states as $element}
		{if $ignore_states|contains($element.id)|not}
		<option value="{$element.id}"{if $element.id|eq($state.id)} selected="selected"{/if}>{$element.current_translation.name}</option>
		{/if}
	{/foreach}
	</select>
</div>
<div class="break"></div>
</div>
</div>
{section show=$content_list}

<table class="list" cellspacing="0">
<tr>
	<th class="tight"><img src={'toggle-button-16x16.gif'|ezimage} alt="{'Invert selection.'|i18n( 'design/admin/content/draft' )}" onclick="ezjs_toggleCheckboxes( document.listaction, 'SelectIDArray[]' ); return false;" title="{'Invert selection.'|i18n( 'design/admin/content/draft' )}" /></th>
    <th>{'Name'|i18n( 'design/admin/content/draft' )}</th>
    <th>{'Type'|i18n( 'design/admin/content/draft' )}</th>
    <th>{'Section'|i18n( 'design/admin/content/draft' )}</th>
    <th>{'Language'|i18n( 'design/admin/content/draft' )}</th>
    <th>{'Modified'|i18n( 'design/admin/content/draft' )}</th>
    <th class="tight">&nbsp;</th>
</tr>
{def $content_object = 0}
{section var=Content loop=$content_list sequence=array( bglight, bgdark )}
<tr class="{$Content.sequence}">
	{set $content_object = $Content.item.object}
    <td align="left" width="1"><input type="checkbox" name="SelectIDArray[]" value="{$content_object.id}"></td>
    <td>{$content_object.content_class.identifier|class_icon( small, $content_object.content_class.name|wash )}&nbsp;<a href={*concat( '/content/versionview/', $content_object.id, '/', $content_object.current_version, '/', $content_object.initial_language.locale, '/' )|ezurl*}{$Content.item.url_alias|ezurl}>{if $Content.item.name|ne('')}{$Content.item.name|wash}{else}<i>{'Unknown'|i18n( 'design/admin/content/draft' )}</i>{/if}</a></td>
    <td>{$content_object.content_class.name|wash}</td>
    <td>{let section_object=fetch( section, object, hash( section_id, $content_object.section_id ) )}{section show=$section_object}{$section_object.name|wash}{section-else}<i>{'Unknown'|i18n( 'design/admin/content/draft' )}</i>{/section}{/let}</td>
    <td><img src="{$content_object.initial_language.locale|flag_icon}" alt="{$content_object.initial_language.locale|wash}" style="vertical-align: middle;" />&nbsp;{$content_object.initial_language.name|wash}</td>
    <td>{$content_object.modified|l10n( shortdatetime )}</td>
    <td><a href={concat( '/content/edit/', $content_object.id, '/', $content_object.version, '/' )|ezurl} title="{'Edit <%draft_name>.'|i18n( 'design/admin/content/draft',, hash( '%draft_name', $content_object.name ) )|wash}" ><img src={'edit.gif'|ezimage} border="0" alt="{'Edit'|i18n( 'design/admin/content/draft' )}" /></a></td>
</tr>
{/section}
</table>
{section-else}
<div class="block">
<p>{"There is no contents in state"|i18n('extension/ezworkflowcollection/design/admin/updatestate/list')} <i>{$state_name}</i></p>
</div>
{/section}

<div class="context-toolbar">
{include name=navigator
         uri='design:navigator/google.tpl'
         page_uri=concat('/updatestate/list/', $state.id)
         item_count=$list_count
         view_parameters=$view_parameters
         item_limit=$number_of_items}
</div>

{* DESIGN: Content END *}</div></div></div>

<div class="controlbar">
{* DESIGN: Control bar START *}<div class="box-bc"><div class="box-ml"><div class="box-mr"><div class="box-tc"><div class="box-bl"><div class="box-br">
<div class="block" id="update-states-list">

{if $content_list|count()}
	<label>{"Update selected content objects for following state"|i18n('extension/ezworkflowcollection/design/admin/updatestate/list')}:</label>
	<select name="TargetObjectState">
	{foreach $state.group.states as $element}
		<option value="{$element.id}"{if $element.id|eq($state.id)} selected="selected"{/if}>{$element.current_translation.name}</option>
	{/foreach}
	</select>
    <input id="set-state-button" class="button" type="submit" name="UpdateObjectStateButton" value="{"Update"|i18n('extension/ezworkflowcollection/design/admin/updatestate/list')}" />

<script type="text/javascript">
{literal}
(function( $ )
{
    $('#update-states-list select').change(function()
    {
        var btn = $('#set-state-button');
        if ( !btn.attr('disabled') )
        {
            btn.removeClass('button').addClass('defaultbutton');
        }
    });
})( jQuery );
{/literal}
</script>
{else}
	&nbsp;
{/if}

</div>
{* DESIGN: Control bar END *}</div></div></div></div></div></div>
</div>

</div>

</form>

{/let}
{literal}
<script language="JavaScript" type="text/javascript">
<!--
    function confirmDiscard( question )
    {
        // Ask user if he really wants to do it.
        return confirm( question );
    }
-->
</script>
{/literal}

