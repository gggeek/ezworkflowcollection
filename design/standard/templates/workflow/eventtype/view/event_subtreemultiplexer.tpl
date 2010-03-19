{*
 * Template for display of subtree-multiplexer event
 * @author G. Giunta
 * @version $Id$
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 * @copyright (C) G. Giunta 2010
 *}
<div class="element">

<table class="list">
<tr>
    <th>{'Affected subtree'|i18n( 'extension/ezworkflowcollection' )}</th>
</tr>
{def $target=null}
<tr>
    {set $target=fetch('content', 'node', hash( 'node_id', $event.target_subtree ) )}
    <td class="bglight"><a href={$target.path_identification_string|ezurl}>{$target.name|wash}</a> [{$node}]</td>
</tr>
{undef $target}
</table>

{"Workflow to run"|i18n("design/standard/workflow/eventtype/view")}:
<table class="list">
<tr>
{let selectedWorkflow=$event.target_workflow}
{section var=workflow loop=$event.workflow_type.workflow_list}
    {section show=$selectedWorkflow|contains($workflow.value)}
        <td class="bglight">{$workflow.Name|wash}</td>
    {/section}
{/section}
{/let}
</tr>
</table>

</div>
