{*
 * Template for editing of addurlalias event
 * @author G. Giunta
 *}
<div class="block">

<div class="element">
    <label>{'Attribute used for as source'|i18n( 'extension/ezworkflowcollection' )}:</label>
    <input name="WorkflowEvent_event_addurlalias_source_attribute_{$event.id}" value="{$event.source_attribute|wash()}"/>
</div>

<div class="element">
    <label>{'Use an external redirect (http 301)'|i18n( 'extension/ezworkflowcollection' )}:</label>
    <input type="checkbox" name="WorkflowEvent_event_addurlalias_external_redirect_{$event.id}" value="1" {if $event.external_redirect}checked="checked"{/if}"/>
</div>

<div class="element">
    <label>{'Place alias at root instead of at node level'|i18n( 'extension/ezworkflowcollection' )}:</label>
    <input type="checkbox" name="WorkflowEvent_event_addurlalias_at_root_{$event.id}" value="1"  {if $event.at_root}checked="checked"{/if}"/>
</div>

</div>
