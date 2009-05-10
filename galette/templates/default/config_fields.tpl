	<h1 id="titre">{_T string="Fields configuration"}</h1>
	<div id="errorbox">
		<h1>{_T string="- WARNING -"}</h1>
		<p>{_T string="This page is under construction.<br/><strong>It does not store any data for now</strong><br/>It just show the future interface 	possibilities :-)"}</p>
	</div>
	<p id="collapse" class="ui-state-default ui-corner-all">
		<span class="ui-icon ui-icon-circle-arrow-s"/>
		{_T string="Collapse all"}
	</p>
	{* TODO: Dynamically generate required tabs entries *}
	<ul id="tabs">
		<li{if $current eq 'membership'} class="current_tab"{/if}><a href="?current=membership">{_T string="Membership"}</a></li>
		<li{if $current eq 'members'} class="current_tab"{/if}><a href="?current=members">{_T string="Members"}</a></li>
	</ul>
	<form action="config_fields.php" method="post">
	<div class="tabbed" id="{$current}_tab">
		<a href="#" title="{_T string="Add a new category"}" id="add_category">{_T string="Add new category"}</a>
{foreach item=category from=$categories name=categories_list}
		<fieldset class="cssform large" id="cat_{$smarty.foreach.categories_list.iteration}">
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

		var _initSortable = function(){ldelim}
			$('.fields_list').sortable({ldelim}
				items: 'li:not(.listing)',
				connectWith: '.connectedSortable'
			{rdelim}).disableSelection();

			$('#members_tab').sortable({ldelim}
				items: 'fieldset'
			{rdelim});
		{rdelim}

		var _bindCollapse = function() {ldelim}
			$('#collapse').click(function(){ldelim}
				$this = $(this);
				var _expandTxt = '{_T string="Expand all"}';
				var _collapseTxt = '{_T string="Collapse all"}';

				var _span = $this.children('span');
				var _isExpand = false;

				var _child = $this.children('.ui-icon');

				if( _child.is('.ui-icon-circle-arrow-e') ) {ldelim}
					$this.html(_collapseTxt);
				{rdelim} else {ldelim}
					_isExpand = true;
					$this.html(_expandTxt);
				{rdelim}
				$this.prepend(_span);

				_child.toggleClass('ui-icon-circle-arrow-e').toggleClass('ui-icon-circle-arrow-s');

				$('legend a').each(function(){ldelim}
					var _visible = $(this).parent('legend').parent('fieldset').children('ul').is(':visible');
					if( _isExpand && _visible ) {ldelim}
						$(this).click();
					{rdelim} else if( !_isExpand && !_visible){ldelim}
						$(this).click();
					{rdelim}
				{rdelim});
			{rdelim});
		{rdelim}

		$(function() {ldelim}
			_collapsibleFieldsets();

			_bindCollapse();

			_initSortable();

			$('#add_category').click(function() {ldelim}
				var _fieldsets = $('fieldset[id^=cat_]');
				var _cat_iter = _fieldsets.length + 1;

				var _fs = $(_fieldsets[0]).clone();
				_fs.attr('id', 'cat_' + _cat_iter).children('ul').attr('id', 'sortable_' + _cat_iter);
				_fs.find('li:not(.listing)').remove();

				var _legend = _fs.children('legend');
				var _a = _legend.children('a');

				_legend.html('<input type="text" name="toto" id="toto" value="New category #' + _cat_iter + '"/>');
				_legend.prepend(_a);
				_a.spinDown();

				$('#{$current}_tab').append(_fs);
				_initSortable();
				_bindCollapse();

				$(this).attr('href', '#cat_' + _cat_iter);
				//Getting
				var _url = document.location.toString();
				if (_url.match('#')) {ldelim} // the URL contains an anchor
					var _url = _url.split('#')[0];
				{rdelim}
				_url += '#cat_' + _cat_iter;

				document.location = _url;
				_legend.children(':input').focus();
				return false;
			{rdelim});
		{rdelim});
		//]]>
	</script>
