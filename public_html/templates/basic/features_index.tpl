{assign var="page_title" value="Datasets"}
{include file="_std_begin.tpl"}

<h2>Feature Datasets</h2>

<p>Here are a list of datasets curated by Geograph Members. For each dataset we try to determine how many of them are photographed.


{if $list}
	<h3>Current Datasets</h3>
	<table style="border:1px solid black; background:#000066;color:white" cellpadding=4>
	{foreach from=$list key=index item=type}
		<tr>
			<td><b><a href="/features/view.php?id={$type.feature_type_id}" 
				style="color:yellow">{$type.title|escape:'html'|regex_replace:'/(Images|Photos|Photographs) of/':'</b>$1 of<b>'|regex_replace:'/(in )?(Great Britain|GB|Ireland|British|Irish|UK)/':'</b>$1<i>$2</i><b>'}</b></td>
			<td align=right>{$type.rows|thousends} features</td>
			<td align=right>{$type.percent}% photographed</td>
		</tr>
		{if $type.extract}
			<tr>
	                        <td colspan=4 style="border-bottom:2px solid gray">{$type.extract|escape:'html'}</td>
			</tr>
		{/if}
	{/foreach}
	</table>
{/if}

{dynamic}
{if $user->registered}
	<a href="/features/edit.php?id=new">Add/Create new dataset</a>
{/if}
{/dynamic}

{include file="_std_end.tpl"}

