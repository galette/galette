		<form action="configurer_fiches.php" method="post" enctype="multipart/form-data" class="tabbed">
{if $error_detected|@count != 0}
		<div id="errorbox">
			<h1>{_T string="- ERROR -"}</h1>
			<ul>
{foreach from=$error_detected item=error}
				<li>{$error}</li>
{/foreach}
			</ul>
		</div>
{/if}
		<div id="configfiches_tabs">
		<ul>
{foreach from=$all_forms key=key item=form}
			<li{if $form_name eq $key} class="ui-tabs-selected"{/if}><a href="?form={$key}">{$form}</a></li>
{/foreach}
		</ul>
        <div id="ui-tabs-1">
            {include file="configurer_fiche_content.tpl"}
        </div>
        </div>
		</form>
        <script type="text/javascript">
            $('#configfiches_tabs > ul > li > a').each(function(){ldelim}
                $(this).attr('href', $(this).attr('href')  + '&ajax=true');
            {rdelim});

            $('#configfiches_tabs').tabs({ldelim}
                load: function(event, ui) {ldelim}
                    $('#configfiches_tabs input:submit, #configfiches_tabs .button, #configfiches_tabs input:reset' ).button();
                {rdelim},
                ajaxOptions: {ldelim}
                    error: function( xhr, status, index, anchor ) {ldelim}
                        alert('{_T string="An error occured :("|escape:"js"}');
                    {rdelim}
                {rdelim}
            {rdelim});
        </script>