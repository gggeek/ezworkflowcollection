{*
 * Template for editing of multipublication event
 * @author G. Giunta
 * @version $Id$
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 * @copyright (C) G. Giunta 2010
 *
 * @todo use node selector to pick up target nodes
 *}

<div class="block">
    <label>{'Target nodes'|i18n( 'extension/ezworkflowcollection' )}:</label>
    <div class="labelbreak"></div>
    <input name="WorkflowEvent_event_multipublish_target_nodes_{$event.id}" value="{$event.target_nodes|implode(',')|wash()}" />
</div>

{'Filter on class attributes:'|i18n( 'extension/ezworkflowcollection' )} {'(only booleans supported)'|i18n( 'extension/ezworkflowcollection' )}
{* Class *}
<div class="block">
    <label>{"Class"|i18n("design/admin/workflow/eventtype/edit")}</label>
    <select name="WorkflowEvent_event_multipublish_class_{$event.id}[]">
    {foreach $event.workflow_type.contentclass_list as $class}
        <option value="{$class.id}" {if eq( $event.contentclass, $class.id )}selected="selected"{/if}>{$class.name|wash}</option>
    {/foreach}
    </select>
    <input class="button" type="submit" name="CustomActionButton[{$event.id}_load_class_attribute_list]" value="{'Update attributes'|i18n('design/admin/workflow/eventtype/edit')}" />
</div>

{* Attributes *}
<div class="block">
    {def $possibleClassAttributes=$event.workflow_type.contentclassattribute_list}
    <label>{"Attribute"|i18n("design/admin/workflow/eventtype/edit")}:</label>
    <select name="WorkflowEvent_event_multipublish_classattribute_{$event.id}[]">
    {foreach $event.contentclassattribute_list as $classAttribute}
        <option value="{$classAttribute.id}">{$classAttribute.name|wash}</option>
    {/foreach}
    </select>
    <input class="button" type="submit" name="CustomActionButton[{$event.id}_new_classelement]" value="{'Select attribute'|i18n('design/admin/workflow/eventtype/edit')}"{if eq($event.contentclassattribute_list|count(), 0)} disabled="disabled"{/if} />
</div>

<div class="break"></div>

{* Class/attribute list *}
<div class="block">
    <label>{'Class/attribute combinations [%count]'|i18n( 'design/admin/workflow/eventtype/edit',, hash( '%count', $event.content.entry_list|count ) )}:</label>
    {if $event.content.entry_list}
    <table class="list" cellspacing="0">
    <tr>
    <th class="tight">&nbsp;</th>
    <th>{'Class'|i18n( 'design/admin/workflow/eventtype/edit' )}</th>
    <th>{'Attribute'|i18n( 'design/admin/workflow/eventtype/edit' )}</th>
    </tr>
    {foreach $event.content.entry_list as $entry sequence array( 'bglight', 'bgdark' ) as $style}
    <tr class="{$style}">
    <td><input type="checkbox" name="WorkflowEvent_data_multipublish_remove_{$event.id}[]" value="{$entry.id}" /></td>
    <td>{$entry.class_name}</td>
    <td>{$entry.classattribute_name}</td>
    </tr>
    {/foreach}
    </table>
    {else}
    <p>{'There are no combinations'|i18n( 'design/admin/workflow/eventtype/edit' )}</p>
    {/if}

    <div class="controlbar">
    <input class="button" type="submit" name="CustomActionButton[{$event.id}_remove_selected]" value="{'Remove selected'|i18n( 'design/admin/workflow/eventtype/edit' )}"{if $event.content.entry_list|not} disabled="disabled"{/if} />
    </div>
</div>

