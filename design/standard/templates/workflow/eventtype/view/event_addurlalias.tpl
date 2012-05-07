{*
 * Template for display of addurlalias event
 * @author G. Giunta
 *}
<div class="element">

<table class="list">
<tr><td>{'Source attribute'|i18n( 'extension/ezworkflowcollection' )}: {$event.source_attribute|wash()}</td></tr>
<tr><td>{'External redirect'|i18n( 'extension/ezworkflowcollection' )}: {if $event.external_redirect}Yes{else}No{/if}</td></tr>
<tr><td>{'Place at root'|i18n( 'extension/ezworkflowcollection' )}:  {if $event.at_root}Yes{else}No{/if}</td></tr>
</table>

</div>
