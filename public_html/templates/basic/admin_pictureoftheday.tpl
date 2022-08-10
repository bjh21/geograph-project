{assign var="page_title" value="Geograph Admin"}
{include file="_std_begin.tpl"}


<h2>Picture of the day</h2>

<h3>Nominate New Image</h3>

<div class="interestBox" style="width:230px;float:right">
	You can leave the 'When' blank to add the image to a pool of images used
	when a particular day hasn't been assigned, or specify the date
	with any <a href="https://www.php.net/manual/en/function.strtotime.php">strtotime</a> format, e.g.
	<ul>
	<li>2007-05-29 </li>
	<li>24 Sep</li>
	<li>tomorrow</li>
	<li>this friday</li>
	</ul>
</div>

<p>Use the form below to nominate an image for <i>Picture of the Day</i>, displayed predominantly on the homepage. If you do not specify a day it will be added to a pool, which will use the images as needed. The pool should be kept well stocked.</p>


<form method="post" action="pictureoftheday.php">

<p><small><b>NOTE</b>: the image will be cropped to landscape format - so for portrait format photos check that they still work when cropped to the central area.</small></p>

<div>
<label for="addimage">Image number</label>
<input type="text" name="addimage" size="8" id="addimage" value="{$addimage}"/>
<input type="button" value="Preview" onclick="window.open('/?potd='+this.form.addimage.value);">
</div>

<div>
<label for="when">When?</label>
<input type="text" name="when" size="16" id="when" value="{$when}"/> (optional)<br/>

<input type="submit" name="add" value="Add"/>
</div>

{if $error}
<div style="border:1px solid red;background:#ffeeee;padding:5px;margin-top:5px;">{$error}</div>
{/if}
{if $confirm}
<div style="border:1px solid green;background:#eeffee;padding:5px;margin-top:5px;">{$confirm}</div>
{/if}



</form>


<h3>Upcoming Pool</h3>

<p>You can <a href="/search.php?i=2136521">Preview</a>(don't disclose this url) the images currently in the kitty. 
You can also <a href="/search.php?i=5761957&amp;temp_displayclass=vote">review the newly-added images</a> 
	<b>or <a href="/search.php?i=5761957&amp;temp_displayclass=blackvote">as GeoRiver</a></b> (again don't disclose this), please visit this page regularly and rate images. 
The highest rated photos will be added to the allocation list below. </p>


<h3>Upcoming Allocations</h3>

<p>Note, the pool images are randomly being shuffled, the list below really just shows that there are pool images to show, not the exact allocation of day. (on that day the pool will shuffle differenly!) 

<table class="report">
{foreach from=$coming_up key=date item=info}
<tr>
<td>{$date}</td>

{if $info.gridimage_id}
	<td><a href="/photo/{$info.gridimage_id}" class="imagepopup">photo {$info.gridimage_id}</a> 
		{if $info.pool}
		 (random from pool)
		{/if}
	</td>
{else}
<td><i>no image</i></td>
{/if}
</tr>

{/foreach}
</table>

<p><i>Note: the exact date a pool image will be used is subject to change. If the pool runs dry lower rated images may be used.</i></p>     

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.5.1/jquery.min.js" type="text/javascript"></script>

{literal}
<style>
	#dispThumbs {
		position:absolute;
		color:white;
		z-index:1000;
	} 
	#dispThumbs iframe {
		width:550px;
		height:280px;
	}
</style>
<script>
	$(function() {
		var xOffset = 20;
		var yOffset = 20;

		$('a.imagepopup').hover(
			function(e) {
				var m = this.href.match(/\/(\d+)$/);
				$("body").append("<div id='dispThumbs'><iframe src='/frame.php?id="+m[1]+"'></iframe></div>");
			},
			function() {
				$("#dispThumbs").remove();
			}
		).mousemove(function(e){
			$("#dispThumbs")
				.css("top",(e.pageY + xOffset) + "px")
				.css("left",(e.pageX + yOffset) + "px");
		});
	});
</script>



{/literal}



{include file="_std_end.tpl"}

