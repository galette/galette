	<h1 id="titre">{_T string="Fields configuration"}</h1>
		<div id="errorbox">
			<h1>{_T string="- WARNING -"}</h1>
			<p>{_T string="This page is under construction.<br/><strong>It does not store any data for now</strong><br/>It just show the future interface possibilities :-)"}</p>
		</div>
	{* TODO: Dynamically generate required tabs entries *}
	<ul id="tabs">
		<li{if $current eq 'membership'} class="current_tab"{/if}><a href="?current=membership">{_T string="Membership"}</a></li>
		<li{if $current eq 'members'} class="current_tab"{/if}><a href="?current=members">{_T string="Members"}</a></li>
	</ul>
	<form action="config_fields.php" method="post">
	<div class="tabbed">
{foreach item=category from=$categories name=categories_list}
		<fieldset class="cssform large">
	{assign var='catname' value=$category->category}
			<legend>{_T string="$catname"}</legend>
			<ul id="sortable_{$smarty.foreach.categories_list.iteration}" class="fields_list connectedSortable">
				<li class="listing">
					<span class="label">{_T string="Field name"}</span>
					<span class="yesno">{_T string="Required"}</span>
					<span class="yesno">{_T string="Visible"}</span>
				</li>

	{assign var='fs' value=$category->id_field_category}
	{foreach key=col item=value from=$fields[$fs] name=fields_list}
				<li class="tbl_line_{if $smarty.foreach.fields_list.iteration % 2 eq 0}even{else}odd{/if}">
					<span class="label">{if isset($labels[$value])}{$labels[$value]}{else}{$value}{/if}</span>
					<span class="yesno">
						<label for="{$value}_required_yes">{_T string="Yes"}</label>
						<input type="radio" name="{$value}_required" id="{$value}_required_yes" value="1"{if isset($requireds[$value])} checked="checked"{/if}/>
						<label for="{$value}_required_no">{_T string="No"}</label>
						<input type="radio" name="{$value}_required" id="{$value}_required_no" value="0"{if !isset($requireds[$value])} checked="checked"{/if}/>
					</span>
					<span class="yesno">
						<label for="{$value}_visible_yes">{_T string="Yes"}</label>
						<input type="radio" name="{$value}_visible" id="{$value}_visible_yes" value="1"{if isset($visibles[$value])} checked="checked"{/if}/>
						<label for="{$value}_visible_no">{_T string="No"}</label>
						<input type="radio" name="{$value}_visible" id="{$value}_visible_no" value="0"{if !isset($visibles[$value])} checked="checked"{/if}/>
					</span>
				</li>
	{/foreach}
			</ul>
		</fieldset>
{/foreach}
		{* <fieldset class="cssform">
			<legend>{_T string="Identity:"}</legend>
			<ul id="sortable1" class="fields_list connectedSortable">
				<li class="listing">
					<span class="label">{_T string="Field name"}</span>
					<span class="yesno">{_T string="Required"}</span>
					<span class="yesno">{_T string="Visible"}</span>
				</li>
{foreach key=col item=value from=$fields name=fields_list}
				<li class="tbl_line_{if $smarty.foreach.fields_list.iteration % 2 eq 0}even{else}odd{/if}">
					<span class="label">{if isset($labels[$value])}{$labels[$value]}{else}{$value}{/if}</span>
					<span class="yesno">
						<label for="{$value}_required_yes">{_T string="Yes"}</label>
						<input type="radio" name="{$value}_required" id="{$value}_required_yes" value="1"{if isset($requireds[$value])} checked="checked"{/if}/>
						<label for="{$value}_required_no">{_T string="No"}</label>
						<input type="radio" name="{$value}_required" id="{$value}_required_no" value="0"{if !isset($requireds[$value])} checked="checked"{/if}/>
					</span>
					<span class="yesno">
						<label for="{$value}_visible_yes">{_T string="Yes"}</label>
						<input type="radio" name="{$value}_visible" id="{$value}_visible_yes" value="1"{if isset($visibles[$value])} checked="checked"{/if}/>
						<label for="{$value}_visible_no">{_T string="No"}</label>
						<input type="radio" name="{$value}_visible" id="{$value}_visible_no" value="0"{if !isset($visibles[$value])} checked="checked"{/if}/>
					</span>
				</li>
{/foreach}
			</ul>
		</fieldset> *}
	</div>
		<div class="button-container">
			<input type="submit" class="submit" value="{_T string="Save"}"/>
		</div>
	</form>
	<script type="text/javascript">
		//<![CDATA[
		//let's round some corners
		$('#tabs li').corner('top');
		$('.tabbed').corner('bottom');

		$(function() {ldelim}
			$('.fields_list').sortable({ldelim}
				items: 'li:not(.listing)',
				connectWith: '.connectedSortable'
			{rdelim}).disableSelection();
		{rdelim});
		//]]>
	</script>
