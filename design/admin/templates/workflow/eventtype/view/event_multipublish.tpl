{*
 * Template for display of multipublication event
 * @author G. Giunta
 * @version $Id$
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 * @copyright (C) G. Giunta 2010
 *}
<div class="element">

<table class="list">
<tr>
    <th>{'Target nodes'|i18n( 'extension/ezworkflowcollection' )}</th>
</tr>
{def $target=null}
{foreach $event.target_nodes as $node sequence array('bglight', 'bgdark') as $style}
<tr>
    {set $target=fetch('content', 'node', hash( 'node_id', $node ) )}
    <td class="{$style}"><a href={$target.path_identification_string|ezurl}>{$target.name|wash}</a> [{$node}]</td>
</tr>
{/foreach}
{undef $target}
</table>

{'Filter on class attributes:'|i18n( 'extension/ezworkflowcollection' )}
<table class="list">
<tr>
    <th>{'Class name'|i18n( 'extension/ezworkflowcollection' )}</th>
    <th>{'ClassAttribute name'|i18n( 'extension/ezworkflowcollection' )}</th>
</tr>
{foreach  $event.content.entry_list as $entry sequence array('bglight', 'bgdark') as $style}
<tr>
    <td class="{$style}">{$entry.class_name}</td>
    <td class="{$style}">{$entry.classattribute_name}</td>
</tr>
{/foreach}
</table>

</div>
