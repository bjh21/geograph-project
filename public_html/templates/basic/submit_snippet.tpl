{assign var="page_title" value="Snippets"}
{include file="_basic_begin.tpl"}
{dynamic}
<form method="post" action="{$script_name}?gr={$gr|escape:'url'}&amp;upload_id={$upload_id|escape:'url'}&amp;gridimage_id={$gridimage_id}" style="background-color:#f0f0f0;" name="theForm">
<input type="hidden" name="gridimage_id" value="{$gridimage_id}" />
<input type="hidden" name="gr" value="{$gr|escape:'html'}" />

<div id="showcreate" {if !$create} style="display:none"{/if}>
	<fieldset>

		<div class="field">
			{if $errors.title}<div class="formerror"><p class="error">{$errors.title}</p>{/if}

			<label for="title"><b>Short Title</b>:</label>
			<input type="text" id="title" name="title" size="30" maxlength="64"/>

			<div class="fieldnotes" style="font-size:0.7em;color:gray">Short title for the object/location being represented</div>

			{if $errors.title}</div>{/if}
		</div>
		
		<div class="field">

			<label for="comment"><b>Description</b>:</label>

			<textarea name="comment" id="comment" rows="10" cols="60"></textarea>

			<div class="fieldnotes" style="font-size:0.7em;color:gray">Remember this shared description may be used on multiple images - so keep it generic.<br/>
			
			TIP: use <span style="color:blue">[[TQ7506]]</span> or <span style="color:blue">[[5463]]</span> to link 
to a Grid Square or another Image.<br/>For a weblink just enter directly like: <span style="color:blue">http://www.example.com</span><br/><br/>
			</div>
		</div>
		
		<div class="field">
			{if $errors.grid_reference}<div class="formerror"><p class="error">{$errors.grid_reference}</p>{/if}

			<label for="grid_reference"><b>Grid Reference</b>:</label>
			<input type="text" id="grid_reference" name="grid_reference" size="10" maxlength="12"/> {if $gr}<small><a href="javascript:void(document.theForm.grid_reference.value='{$gr|escape:'url'}');">Use {$gr}</a></small>{/if}
			| or <input type="checkbox" name="nogr" value="1" id="nogr"/><label for="nogr">Don't attach a location to this description</label>

			<div class="fieldnotes" style="font-size:0.7em;color:gray">Optional Grid Reference for the feature/location, great if this description describes a specific location. Ideally 6 figure plus.</div>

			{if $errors.grid_reference}</div>{/if}
		</div>
		
		<input type="submit" name="create" value="Create Shared Description"/> &nbsp; <small>[ <a href="javascript:void(hide_tree('create'))">Cancel / Close</a> ]</small>
		
		<div class="fieldnotes" style="font-size:0.7em;color:gray">Idea: Even if you leave the description itself blank, a 'shared description' can still be used as a way to link a series of images into 'Collection'.</div>
		
	</fieldset>
</div>

<div class="interestBox" style="font-size:0.8em{if $create};display:none{/if}" id="hidecreate">
	<div style="float:right;text-align:center;position:relative;background-color:white;padding:3px">
		<input type="button" value="Create New Shared Description" onclick="show_tree('create')" style="background-color:lightgreen"/><br/>
		<a href="/snippets.php?gr={$gr|escape:'html'}&amp;onlymine=on" target="_blank">Edit nearby Shared Descriptions</a>
	</div>
	&middot; Here you can create descriptions that are common to multiple images.<br/>&middot; These shared descriptions can operate in addition <i>or</i> instead of the main description.{if $used}<br/> &middot; Optional: Reference a shared description by its number eg [1] in the main description.{/if}
	
	{if $gridimage_id < 4294967296}
		<br/>&middot; <b>Changes made here apply immediately and don't go though the change request system.</b>
	{/if}
	<br/>&middot; <b>{newwin href="/article/Shared-Descriptions" text="Read more about Shared Descriptions here"}</b>
</div>
{if $used}
<div style="background-color:lightgreen">
<div style="font-size:0.7em;color:green;border-top:2px solid gray;padding:2px">&nbsp;<b>Shared Descriptions attached to this image</b>:</div>
{foreach from=$used item=item name=used}
	
	<div style="margin-left:4px;background-color:{cycle values="#e4e4e4,#f4f4f4"}">
		<div style="float:right;position:relative">
			{if $user->user_id == $item.user_id || $is_mod}
				<a href="/snippets.php?edit[{$item.snippet_id}]=edit&amp;onlymine=on" target="_blank">Edit</a>
			{/if}
			<input type="submit" name="remove[{$item.snippet_id}]" value="Remove" style="background-color:pink"/>
		</div>

		{$smarty.foreach.used.iteration}. 
		<a href="/snippet/{$item.snippet_id}" target="_blank"><b>{$item.title|escape:'html'|default:'untitled description'}</b></a><br/>
		<div style="font-size:0.7em">{$item.comment|escape:'html'|truncate:250:' (more...)'}</div>

		<br style="clear:both"/>
	</div>

{foreachelse}
	<p style="margin:4px;margin-bottom:10px"><i><b>None</b>. <small>Click 'Create New Shared Description'{if $results}, or a 'Use this Description' button below,{/if} to add a description to this image.</small></i></p>
{/foreach}
</div>
{/if}

<div style="font-size:0.7em;color:green;border-top:2px solid gray;padding:2px">&nbsp;<b>Shared Descriptions available</b>: 
{if $tab eq 'recent'}
	( <a href="?gr={$gr|escape:'url'}&amp;upload_id={$upload_id|escape:'url'}&amp;gridimage_id={$gridimage_id}">Search/Local Filter</a> / <b>My Recently used</b> {if $sphinx}/ <a href="?gr={$gr|escape:'url'}&amp;upload_id={$upload_id|escape:'url'}&amp;gridimage_id={$gridimage_id}&amp;tab=suggestions" onclick="return suggestionsClicker(this);">Suggestions</a>{/if} )</div>
{elseif $tab eq 'suggestions'}
	( <a href="?gr={$gr|escape:'url'}&amp;upload_id={$upload_id|escape:'url'}&amp;gridimage_id={$gridimage_id}">Search/Local Filter</a> / <a href="?gr={$gr|escape:'url'}&amp;upload_id={$upload_id|escape:'url'}&amp;gridimage_id={$gridimage_id}&amp;tab=recent">My Recently used</a> / <b>Suggestions</b> )</div>
{else}	
	( <b>Search/Local Filter</b> / <a href="?gr={$gr|escape:'url'}&amp;upload_id={$upload_id|escape:'url'}&amp;gridimage_id={$gridimage_id}&amp;tab=recent">My Recently used</a> {if $sphinx}/ <a href="?gr={$gr|escape:'url'}&amp;upload_id={$upload_id|escape:'url'}&amp;gridimage_id={$gridimage_id}&amp;tab=suggestions" onclick="return suggestionsClicker(this);">Suggestions</a>{/if} )</div>

<div class="interestBox" style="margin:4px;margin-left:24px">
Within radius:{if $centisquare}
<label for="rad01" class="nowrap"><input type="radio" name="radius" id="rad01" value="0.1"{if $radius == 0.1} checked{/if}/> centisquare</label> / 
{/if}
<label for="rad1" class="nowrap"><input type="radio" name="radius" id="rad1" value="1" {if $radius == 1 || !$radius} checked{/if}/> {$grid_reference|default:'gridsquare'}</label> / 
<label for="rad2" class="nowrap"><input type="radio" name="radius" id="rad2" value="2" {if $radius == 2} checked{/if}/> surrounding gridsquares</label> / 
<label for="rad10" class="nowrap"><input type="radio" name="radius" id="rad10" value="10"{if $radius == 10} checked{/if}/> within 10km</label> /
<label for="rad1000" class="nowrap"><input type="radio" name="radius" id="rad1000" value="1000"{if $radius == 1000} checked{/if}/> anywhere <sub>(keyword needed below!)</sub></label>  <br/>
<label for="fq">Search{if $sphinx} keywords{/if}</label>: <input type="text" name="q" id="fq" size="20"{if $q} value="{$q|escape:'html'}"{/if}/>
{if !$sphinx}
	(single keyword only)
{/if}
 <input type="checkbox" name="onlymine" {if $onlymine} checked{/if}/> Only show my descriptions. 
 
 <input type="submit" value="Update"/>
</div>
{/if}

{foreach from=$results item=item}
	
	<div style="margin-left:4px;background-color:{cycle values="#e4e4e4,#f4f4f4"}">
		<div style="float:right;position:relative">
			{if $user->user_id == $item.user_id || $is_mod}
				<a href="/snippets.php?edit[{$item.snippet_id}]=edit&amp;onlymine=on" target="_blank">Edit</a>
			{/if}
			<input type="submit" name="add[{$item.snippet_id}]" value="Use this Description" style="background-color:lightgreen"/>
		</div>

		<a href="/snippet/{$item.snippet_id}" target="_blank"><b>{$item.title|escape:'html'|default:'<span style=color:gray>untitled description</span>'}</b></a> {if $item.grid_reference && $item.grid_reference != $grid_reference} :: {$item.grid_reference} {/if}{if $item.distance}(Distance {$item.distance}km){/if} (<a href="javascript:void(document.theForm.elements['add[{$item.snippet_id}]'].click())" style="color:green">use</a>)<br/>
		<div style="font-size:0.7em">{$item.comment|escape:'html'|truncate:250:' (more...)'}</div>
		<div style="font-size:0.7em;color:gray;margin-left:10px;">

		{if $user->user_id != $item.user_id}
			By <a href="/profile/{$item.user_id}">{$item.realname|escape:'html'}</a>. 
		{/if}

		Used on {$item.images|thousends} images {if $item.images != $item.yours}(of which {$item.yours|thousends} are yours){/if}</div>
		
		<br style="clear:both"/>
	</div>

{foreachelse}
	{if $empty}
	<p style="margin:4px"><i>please specify some keywords{if $grid_reference}, or choose a smaller radius{/if}</i></p><br/>
	{else}
	<p style="margin:4px"><i>No shared descriptions found{if $grid_reference && ($radius == 1 || !$radius)} in {$grid_reference}{/if}{if $q}, matching [{$q|escape:'html'}]{/if}, <a href="#" onclick="show_tree('create');return false">create your own</a>!</i></p><br/>
	{/if}
{/foreach}
{if $query_info}
	<p><i>{$query_info}</i></p>
{/if}
<input type="text" value="" style="display:none"/>
</form>

{/dynamic}

<div class="interestBox" style="background-color:pink; font-size:0.7em; border-top:2px solid gray"><i>For clarification, you are submitting these shared descriptions to Geograph Project directly. Geograph Project then grants any contributor the right to reuse any shared description within their Creative Commons licensed submission. From a practical point of view this allows the contributor the use the description without attribution (as its not Creative Commons licensed).</i></div>

<script type="text/javascript">{literal}

function suggestionsClicker(that) {
	var str;
	var thatForm = window.parent.document.forms['theForm'];

	if (thatForm.elements['title']) {
		str = thatForm.elements['title'].value;
	}
	if (thatForm.elements['comment']) {
		str = str + ' '+ thatForm.elements['comment'].value;
	}
	if (thatForm.elements['imageclass']) {
		str = str + ' '+ thatForm.elements['imageclass'].value;
	}
	
	window.location.href = that.href + "&corpus="+encodeURIComponent(str.replace(/[\r\n]+/,' '));
	return false;
}
		
{/literal}</script>

</body>
</html>