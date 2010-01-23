{assign var="page_title" value="Hectad Map"}
{include file="_std_begin.tpl"}
<h2>Hectad Coverage Map{if $u} for {$profile->realname|escape:'html'}{/if}</h2>

<form method="get" action="{$script_name}">
<div class="interestBox">Colour squares by: 
{if $which eq 1}
	<b>Geograph Coverage</b>
{else}
	<a href="?w=1{if $profile}&amp;u={$profile->user_id}{/if}">Geograph Coverage</a>
{/if} |
{if $which eq 2}
	<b>Coverage Percentage</b>
{else}
	<a href="?w=2{if $profile}&amp;u={$profile->user_id}{/if}">Coverage Percentage</a>
{/if} |
{if $which eq 3}
	<b>Land Squares</b>
{else}
	<a href="?w=3">Land Squares</a>
{/if}
<input type="hidden" name="w" value="{$which}"/>
{dynamic}
    {if $user->registered}
	- <select name="u">
		{if $u && $u != $user->user_id}
			<option value="{$u}">Just for {$profile->realname|escape:'html'}</option>
		{/if}
		<option value="{$user->user_id}">Just for {$user->realname|escape:'html'}</option>
		<option value="" {if !$u} selected{/if}>For Everyone</option>
	</select>
	<input type="submit" value="Go">
    {else}
	{if $u}
	- <select name="u">
		<option value="{$u}" selected>Just for {$profile->realname|escape:'html'}</option>
		<option value="">For Everyone</option>
	</select>
	<input type="submit" value="Go">
	{/if}
    {/if}
    {/dynamic}
</div>
</form>    

<table style="background-color:white;font-family:courier;font-size:0.7em" border=1 cellspacing=0 cellpadding=1 bordercolor="#f7f7f7"> <tbody>

	<tr>
	{section name=x loop=$w start=$x1 step=1}
		<th width="90">&nbsp;&nbsp;&nbsp;&nbsp;</th>
	{/section}
	</tr>

{section name=y loop=$h start=$y2 step=-1}
	{assign var="y" value=$smarty.section.y.index}

	<tr>
	{section name=x loop=$w start=$x1 step=1}
		{assign var="x" value=$smarty.section.x.index}
		
		{if $grid.$y.$x}{assign var="mapcell" value=$grid.$y.$x}
			<td bgcolor="#{$mapcell.$column|colerize}" title="{$mapcell.geosquares}/{$mapcell.landsquares}={$mapcell.percentage}%">
			{if $mapcell.geosquares}<b>{$mapcell.hectad}</b>{else}{$mapcell.hectad}{/if}
			</td>
		{else}
			<td>&nbsp;</td>
		{/if}
	{/section}
	
	</tr>
{/section}
</tbody>
</table>

<p><i>Hover over square to see statistics</i></p>

{include file="_std_end.tpl"}

