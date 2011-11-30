		<form action="ajouter_groupe.php" method="post" enctype="multipart/form-data" id="form">
{* FIXME: a bad hack... Title will go to page.tpl in the future as well as error/warnings (see public_page.tpl) *}
{if $error_detected|@count != 0 and $login->isLogged()}
		<div id="errorbox">
			<h1>{_T string="- ERROR -"}</h1>
			<ul>
{foreach from=$error_detected item=error}
				<li>{$error}</li>
{/foreach}
			</ul>
		</div>
{/if}
{if $warning_detected|@count != 0}
		<div id="warningbox">
			<h1>{_T string="- WARNING -"}</h1>
			<ul>
				{foreach from=$warning_detected item=warning}
					<li>{$warning}</li>
				{/foreach}
			</ul>
		</div>
{/if}
		<div class="bigtable">
			<fieldset class="cssform">
				<legend class="ui-state-active ui-corner-top">{_T string="Groups:"}</legend>
				<div>
					<p>
						<label for="group_name" class="bline">{_T string="Name:"}</label>
						<input type="text" name="group_name" id="group_name" value="{$group->getName()}" maxlength="20" required/>
					</p>
					<p>
                        {assign var="owner" value=$group->getOwner()}
						<label for="group_owner" class="bline">{_T string="Owner:"}</label>
						<input type="text" name="group_owner" id="group_owner" value="{$owner->id}" maxlength="20" required/> <span id="owner_name">{if $owner->id != ''} - {$owner->sname}{/if}</span>
                        <a class="button" id="btnusers" href="gestion_adherents.php?nbshow=0&showChecked=true">{_T string="Change owner"}</a>
					</p>
				</div>
			</fieldset>

		</div>
		<div class="button-container">
			<input type="submit" name="valid" id="btnsave" value="{_T string="Save"}"/>
			<input type="hidden" name="id_group" value="{$group->getId()}"/>
		</div>
		<p>{_T string="NB : The mandatory fields are in"} <span class="required">{_T string="red"}</span></p>
		</form>
<script type="text/javascript">
    $(function() {ldelim}
        {* Members popup *}
        $('#btnusers').click(function(){ldelim}
            $.ajax({ldelim}
                url: 'ajax_members.php',
                type: "POST",
                data: {ldelim}ajax: true, multiple: false{rdelim},
                {include file="js_loader.tpl"},
                success: function(res){ldelim}
                    _members_dialog(res);
                {rdelim},
                error: function() {ldelim}
                    alert("{_T string="An error occured displaying members interface :(" escape="js"}");
                {rdelim}
            });
            return false;
        {rdelim});

        var _members_dialog = function(res){ldelim}
            var _el = $('<div id="members_list" title="{_T string="Group owner selection" escape="js"}"> </div>');
            _el.appendTo('body').dialog({ldelim}
                modal: true,
                hide: 'fold',
                width: '80%',
                height: 500,
                close: function(event, ui){ldelim}
                    _el.remove();
                {rdelim}
            {rdelim});
            _members_ajax_mapper(res);

        {rdelim}

        var _members_ajax_mapper = function(res){ldelim}
            $('#members_list').append(res);
            //Remap links
            var _none = $('#none_selected').clone();
            $('#listing a').click(function(){ldelim}
                var _mid = this.href.substring(this.href.indexOf('?')+8);
                var _mname = $(this).text();

                $('#group_owner').val(_mid);
                $('#owner_name').html(' - ' + _mname);
                $('#members_list').dialog("close");

                return false;
            {rdelim});

        {rdelim}
    {rdelim});
</script>