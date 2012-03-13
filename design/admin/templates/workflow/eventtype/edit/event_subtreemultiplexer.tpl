{*
 * Template for editing of subtree-multiplexer event
 * @author G. Giunta
 * @version $Id$
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 * @copyright (C) G. Giunta 2010
 *
 * @todo use node selector to pick up target subtree node
 * @todo remove current workflow from target workflow list
 *}
<div class="block">

{* Subtree *}
<div class="element">
    <label>{'Affected subtree'|i18n( 'extension/ezworkflowcollection' )}</label>
    <input type="text" name="WorkflowEvent_event_subtreemultiplexer_target_subtree_{$event.id}" size="10" value="{$event.target_subtree}"/>
</div>

{* Workflow to run *}
<div class="element">
    <label>{'Workflow to run'|i18n( 'design/admin/workflow/eventtype/edit' )}:</label>
    {section show=$event.workflow_type.workflow_list}
    <select name="WorkflowEvent_event_subtreemultiplexer_target_workflow_{$event.id}">
    {section var=Workflows loop=$event.workflow_type.workflow_list}
    <option value="{$Workflows.item.value}"{section show=eq( $Workflows.item.value, $event.selected_workflow )} selected="selected"{/section}>{$Workflows.item.Name|wash}</option>
    {/section}
    </select>
    {section-else}
    {'You have to create a workflow before using this event.'|i18n( 'design/admin/workflow/eventtype/edit' )}
    {/section}
</div>

</div>