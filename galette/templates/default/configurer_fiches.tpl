		<form action="configurer_fiches.php" method="post" enctype="multipart/form-data" class="tabbed">
			<div id="addfield" class="cssform">
                <p>
                    <label for="field_name" class="bline">{_T string="Field name"}</label>
                    <input size="40" type="text" name="field_name" id="field_name"/>
                </p>
                <p>
                    <label for="field_perm" class="bline">{_T string="Visibility"}</label>
                    <select name="field_perm" id="field_perm">
                        {html_options options=$perm_names selected="0"}
                    </select>
                </p>
                <p>
                    <label for="field_type" class="bline">{_T string="Type"}</label>
                    <select name="field_type" id="field_type">
                        {html_options options=$field_type_names selected="0"}
                    </select>
                </p>
                <p>
                    <label for="field_required" class="bline">{_T string="Required"}</label>
                    <select name="field_required" id="field_required">
                        <option value="0">{_T string="No"}</option>
                        <option value="1">{_T string="Yes"}</option>
                    </select>
                </p>
                <p>
                    <label for="field_pos" class="bline">{_T string="Position"}</label>
                    <select name="field_pos" id="field_pos">
                        {html_options options=$field_positions selected="0"}
                    </select>
                </p>
                <div class="center">
                    <input type="submit" name="valid" id="btnadd" value="{_T string="Add"}"/>
                    <input type="hidden" name="form" id="formname" value="{$form_name}"/>
                </div>
			</div>

		<div id="configfiches_tabs">
		<ul>
{foreach from=$all_forms key=key item=form name=formseach}
    {if $form_name eq $key}
        {assign var='activetab' value=$smarty.foreach.formseach.iteration}
    {/if}
			<li{if $form_name eq $key} class="ui-tabs-selected"{/if}><a href="?form={$key}">{$form}</a></li>
{/foreach}
		</ul>
        <div id="ui-tabs-{$activetab}">
            {include file="configurer_fiche_content.tpl"}
        </div>
        </div>
		</form>
        <script type="text/javascript">
            $('#configfiches_tabs').append('<a class="button notext" id="btnadd_small">{_T string="Add"}</a>');
            var _dialogform = $('<form id="dialogform" action="configurer_fiches.php" method="post" title="{_T string="Add new dynamic field"}"">');
            _dialogform.append($('#addfield'));
			_dialogform.dialog({ldelim}
				autoOpen: false,
				modal: true,
				hide: 'fold',
				width: '40%'
			{rdelim}).dialog('close');

			$('#btnadd_small').click(function(){ldelim}
				$('#dialogform').dialog('open');
				return false;
			{rdelim});

            $('#configfiches_tabs > ul > li > a').each(function(){ldelim}
                $(this).attr('href', $(this).attr('href')  + '&ajax=true');
            {rdelim});

            $('#configfiches_tabs').tabs({ldelim}
                load: function(event, ui) {ldelim}
                    $('#configfiches_tabs input:submit, #configfiches_tabs .button, #configfiches_tabs input:reset' ).button();
                {rdelim},
                ajaxOptions: {ldelim}
                    {* Cannot include js_loader.tpl here because we need to use beforeSend specificaly... *}
                    beforeSend: function(xhr, settings) {ldelim}
                        var _reg = /\?form=(.*)&ajax=true/g;
                        $('#formname').val(_reg.exec(settings.url)[1]);
                        if ( settings.url.match(/\?form={$form_name}.*/) ) {ldelim}
                            return false; //avoid reloading first tab onload
                        {rdelim}
                        var _img = $('<figure id="loading"><p><img src="{$template_subdir}images/loading.png" alt="{_T string="Loading..."}"/><br/>{_T string="Currently loading..."}</p></figure>');
                        $('body').append(_img);
                    {rdelim},
                    complete: function() {ldelim}
                        $('#loading').remove();
                    {rdelim},
                    error: function( xhr, status, index, anchor ) {ldelim}
                        alert('{_T string="An error occured :("|escape:"js"}');
                    {rdelim}
                {rdelim}
            {rdelim});
        </script>