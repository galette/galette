		<form action="{if $login->isLogged()}ajouter_adherent.php{else}self_adherent.php{/if}" method="post" enctype="multipart/form-data" id="form">
		<div class="bigtable">
{if $self_adh and $head_redirect}
            <div id="infobox">
                <h1>{_T string="Account registered!"}</h1>
                <p>
    {if $pref_mail_method == constant('GaletteMail::METHOD_DISABLED') or $member->email eq ""}
                    {_T string="Your subscription has been registered."}
     {else}
                    {_T string="Your subscription has been registered, you will receive a recapitulative email soon (remember to check your spam box ;) )."}
     {/if}
                    <br/>{_T string="You'll be redirected to the login page in a few seconds"}
                </p>
            </div>
{else}
    {if (!$self_adh and ($login->isAdmin() or $login->isStaff())) and !$disabled.send_mail}
					<p>
						<label for="mail_confirm">
        {if $member->id}
                            {_T string="Notify member his account has been modified"}
        {else}
                            {_T string="Notify member his account has been created"}
        {/if}
                        </label>
						<input type="checkbox" name="mail_confirm" id="mail_confirm" value="1" {if $smarty.post.mail_confirm != ""}checked="checked"{/if}/>
						<br/><span class="exemple">
        {if $member->id}
							{_T string="Member will be notified by mail his account has been modified."}
        {else}
							{_T string="Member will receive his username and password by email, if he has an address."}
        {/if}
						</span>
					</p>
    {/if}
			<fieldset class="cssform">
				<legend class="ui-state-active ui-corner-top">{_T string="Identity:"}</legend>
				<div>
    {if !$self_adh}
					<p>
						<span class="bline">{_T string="Picture:"}</span>
						<img id="photo_adh" src="{$galette_base_path}picture.php?id_adh={$member->id}&amp;rand={$time}" class="picture" width="{$member->picture->getOptimalWidth()}" height="{$member->picture->getOptimalHeight()}" alt="{_T string="Picture"}"/><br/>
        {if $member->hasPicture() eq 1 }
						<span class="labelalign"><label for="del_photo">{_T string="Delete image"}</label></span><input type="checkbox" name="del_photo" id="del_photo" value="1"/><br/>
        {/if}
						<input class="labelalign" type="file" name="photo"/>
					</p>
    {/if}
					<p>
						<span class="bline">{_T string="Title:"}</span>
						{if $disabled.titre_adh != ''}
							{html_radios name="titre_adh" options=$radio_titres checked=$member->politeness separator="&nbsp;" disabled="disabled"}
						{else}
							{html_radios name="titre_adh" options=$radio_titres checked=$member->politeness separator="&nbsp;"}
						{/if}
					</p>
					<p>
						<label for="nom_adh" class="bline">{_T string="Name:"}</label>
						<input type="text" name="nom_adh" id="nom_adh" value="{$member->name|escape}" maxlength="20" {$disabled.nom_adh}{if $required.nom_adh eq 1} required{/if}/>
					</p>
					<p>
						<label for="prenom_adh" class="bline">{_T string="First name:"}</label>
						<input type="text" name="prenom_adh" id="prenom_adh" value="{$member->surname}" maxlength="20" {$disabled.prenom_adh}{if $required.prenom_adh eq 1} required{/if}/>
					</p>
                    <p>
						<label for="is_company" class="bline">{_T string="Is company?"}</label>
						<input type="checkbox" name="is_company" id="is_company" value="1"{if $member->isCompany()} checked="checked"{/if}/>
                    </p>
					<p id="company_field"{if !$member->isCompany()} class="hidden"{/if}>
						<label for="societe_adh" class="bline">{_T string="Company:"}</label>
						<input type="text" name="societe_adh" id="societe_adh" value="{$member->company_name}" maxlength="20" {$disabled.societe_adh}{if $required.societe_adh eq 1} required{/if}/>
					</p>
					<p>
						<label for="pseudo_adh" class="bline">{_T string="Nickname:"}</label>
						<input type="text" name="pseudo_adh" id="pseudo_adh" value="{$member->nickname|htmlspecialchars}" maxlength="20" {$disabled.pseudo_adh}{if $required.pseudo_adh eq 1} required{/if}/>
					</p>
					<p>
						<label for="ddn_adh" class="bline">{_T string="Birth date:"}</label>
						<input type="text" name="ddn_adh" id="ddn_adh" value="{$member->birthdate}" maxlength="10" {$disabled.ddn_adh}{if $required.ddn_adh eq 1} required{/if}/> <span class="exemple">{_T string="(dd/mm/yyyy format)"}</span>
					</p>
                    <p>
                        <label for="lieu_naissance" class="bline">{_T string="Birthplace:"}</label>
                        <input type="text" name="lieu_naissance" id="lieu_naissance" value="{$member->birth_place}" {$disabled.lieu_naissance}{if $required.lieu_naissance eq 1} required{/if}/>
                    </p>
					<p>
						<label for="prof_adh" class="bline">{_T string="Profession:"}</label>
						<input type="text" name="prof_adh" id="prof_adh" value="{$member->job|htmlspecialchars}" maxlength="150" {$disabled.prof_adh}{if $required.prof_adh eq 1} required{/if}/>
					</p>
					<p>
						<label for="pref_lang" class="bline">{_T string="Language:"}</label>
						<select name="pref_lang" id="pref_lang" {$disabled.pref_lang}{if $required.pref_lang eq 1} required{/if}>
							{foreach item=langue from=$languages}
								<option value="{$langue->getID()}"{if $member->language eq $langue->getID()} selected="selected"{/if} style="background:url({$langue->getFlag()}) no-repeat;padding-left:30px;">{$langue->getName()|ucfirst}</option>
							{/foreach}
						</select>
					</p>
				</div>
			</fieldset>

			<fieldset class="cssform">
				<legend class="ui-state-active ui-corner-top">{_T string="Contact information:"}</legend>
				<div>
					<p>
						<label for="adresse_adh" class="bline">{_T string="Address:"}</label>
						<input type="text" class="large" name="adresse_adh" id="adresse_adh" value="{$member->adress|htmlspecialchars}" maxlength="150" {$disabled.adresse_adh}{if $required.adresse_adh eq 1} required{/if}/><br/>
                        {* FIXME: A-t-on réellement besoin de deux lignes pour une adresse ? *}
						<label for="adresse2_adh" class="bline libelle">{_T string="Address:"} {_T string=" (continuation)"}</label>
						<input type="text" class="large" name="adresse2_adh" id="adresse2_adh" value="{$member->adress_continuation|htmlspecialchars}" maxlength="150" {$disabled.adresse2_adh}{if $required.adresse2_adh eq 1} required{/if}/>
					</p>
					<p>
						<label for="cp_adh" class="bline">{_T string="Zip Code:"}</label>
						<input type="text" name="cp_adh" id="cp_adh" value="{$member->zipcode}" maxlength="10" {$disabled.cp_adh}{if $required.cp_adh eq 1} required{/if}/>
					</p>
					<p>
						<label for="ville_adh" class="bline">{_T string="City:"}</label>
						<input type="text" name="ville_adh" id="ville_adh" value="{$member->town|htmlspecialchars}" maxlength="50" {$disabled.ville_adh}{if $required.ville_adh eq 1} required{/if}/>
					</p>
					<p>
						<label for="pays_adh" class="bline">{_T string="Country:"}</label>
						<input type="text" name="pays_adh" id="pays_adh" value="{$member->country|htmlspecialchars}" maxlength="50" {$disabled.pays_adh}{if $required.pays_adh eq 1} required{/if}/>
					</p>
					<p>
						<label for="tel_adh" class="bline">{_T string="Phone:"}</label>
						<input type="text" name="tel_adh" id="tel_adh" value="{$member->phone}" maxlength="20" {$disabled.tel_adh}{if $required.tel_adh eq 1} required{/if}/>
					</p>
					<p>
						<label for="gsm_adh" class="bline">{_T string="Mobile phone:"}</label>
						<input type="text" name="gsm_adh" id="gsm_adh" value="{$member->gsm}" maxlength="20" {$disabled.gsm_adh}{if $required.gsm_adh eq 1} required{/if}/>
					</p>
					<p>
						<label for="email_adh" class="bline">{_T string="E-Mail:"}</label>
						<input type="text" name="email_adh" id="email_adh" value="{$member->email}" maxlength="150" size="30" {$disabled.email_adh}{if $required.email_adh eq 1} required{/if}/>
					</p>
					<p>
						<label for="url_adh" class="bline">{_T string="Website:"}</label>
						<input type="text" name="url_adh" id="url_adh" value="{$member->website}" maxlength="200" size="30" {$disabled.url_adh}{if $required.url_adh eq 1} required{/if}/>
					</p>
					<p>
						<label for="icq_adh" class="bline">{_T string="ICQ:"}</label>
						<input type="text" name="icq_adh" id="icq_adh" value="{$member->icq}" maxlength="20" {$disabled.icq_adh}{if $required.icq_adh eq 1}required{/if}/>
					</p>
					<p>
						<label for="jabber_adh" class="bline">{_T string="Jabber:"}</label>
						<input type="text" name="jabber_adh" id="jabber_adh" value="{$member->jabber}" maxlength="150" size="30" {$disabled.jabber_adh}{if $required.jabber_adh eq 1} required{/if}/>
					</p>
					<p>
						<label for="msn_adh" class="bline">{_T string="MSN:"}</label>
						<input type="text" name="msn_adh" id="msn_adh" value="{$member->msn}" maxlength="150" size="30" {$disabled.msn_adh}{if $required.msn_adh eq 1} required{/if}/>
					</p>
					<p>
						<label for="gpgid" class="bline">{_T string="Id GNUpg (GPG):"}</label>
						<input type="text" name="gpgid" id="gpgid" value="{$member->gpgid}" maxlength="8" size="8" {$disabled.gpgid}{if $required.gpgid eq 1} required{/if}/>
					</p>
					<p>
						<label for="fingerprint" class="bline">{_T string="fingerprint:"}</label>
						<input type="text" name="fingerprint" id="fingerprint" value="{$member->fingerprint}" maxlength="40" size="40" {$disabled.fingerprint}{if $required.fingerprint eq 1}required{/if}/>
					</p>
				</div>
			</fieldset>

			<fieldset class="cssform">
				<legend class="ui-state-active ui-corner-top">{_T string="Galette-related data:"}</legend>
				<div>
					<p>
						<label for="bool_display_info" class="bline">{_T string="Be visible in the<br /> members list :"}</label>
						<input type="checkbox" name="bool_display_info" id="bool_display_info" value="1" {if $member->appearsInMembersList() eq 1}checked="checked"{/if} {$disabled.bool_display_info}{if $required.bool_display_info eq 1} required{/if}/>
					</p>
    {if !$self_adh}
        {if $login->isAdmin() or $login->isStaff()}
					<p>
						<label for="activite_adh" class="bline">{_T string="Account:"}</label>
						<select name="activite_adh" id="activite_adh" {$disabled.activite_adh}{if $required.activite_adh eq 1} required{/if}>
							<option value="1" {if $member->isActive() eq 1}selected="selected"{/if}>{_T string="Active"}</option>
							<option value="0" {if $member->isActive() eq 0}selected="selected"{/if}>{_T string="Inactive"}</option>
						</select>
					</p>
					<p>
						<label for="id_statut" class="bline">{_T string="Status:"}</label>
						<select name="id_statut" id="id_statut" {$disabled.id_statut}{if $required.id_statut eq 1} required{/if}>
							{html_options options=$statuts selected=$member->status}
						</select>
					</p>
            {if $login->isAdmin()}
					<p>
						<label for="bool_admin_adh" class="bline">{_T string="Galette Admin:"}</label>
						<input type="checkbox" name="bool_admin_adh" id="bool_admin_adh" value="1" {if $member->isAdmin()}checked="checked"{/if} {$disabled.bool_admin_adh}{if $required.bool_admin_adh eq 1} required{/if}/>
					</p>
            {/if}
					<p>
						<label for="bool_exempt_adh" class="bline">{_T string="Freed of dues:"}</label>
						<input type="checkbox" name="bool_exempt_adh" id="bool_exempt_adh" value="1" {if $member->isDueFree() eq 1}checked="checked"{/if} {$disabled.bool_exempt_adh}{if $required.bool_exempt_adh eq 1} required{/if}/>
					</p>
        {/if}
    {/if}
					<p>
						<label for="login_adh" class="bline">{_T string="Username:"}</label>
						<input type="text" name="login_adh" id="login_adh" value="{$member->login}" maxlength="20" {$disabled.login_adh}{if $required.login_adh eq 1} required{/if}/>
						<span class="exemple">{_T string="(at least 4 characters)"}</span>
					</p>
    {if !$self_adh}
					<p>
						<label for="mdp_adh" class="bline">{_T string="Password:"}</label>
						<input type="password" name="mdp_adh" id="mdp_adh" value="" maxlength="20" autocomplete="off" {$disabled.mdp_adh}{if $required.mdp_adh eq 1} required{/if}/>
						<span class="exemple">{_T string="(at least 4 characters)"}</span>
					</p>
					<p>
						<input class="labelalign" type="password" name="mdp_adh2" value="" maxlength="20" {$disabled.mdp_adh}{if $required.mdp_adh eq 1} required{/if}/>
						<span class="exemple">{_T string="(Confirmation)"}</span>
					</p>
    {else}
					<p>
						<label for="mdp_adh" class="bline libelle">{_T string="Password:"}</label>
						<input type="hidden" name="mdp_crypt" value="{$spam_pass}" />
						<img src="{$spam_img}" alt="{_T string="Passworg image"}" />
						<input type="text" name="mdp_adh" id="mdp_adh" value="" maxlength="20" {$disabled.mdp_adh}{if $required.mdp_adh eq 1} required{/if}/>
						<span class="exemple">{_T string="Please repeat in the field the password shown in the image."}</span>
					</p>
    {/if}

    {if !$self_adh and ($login->isAdmin() or $login->isStaff())}
					<p>
						<label for="date_crea_adh" class="bline">{_T string="Creation date:"}</label>
						<input type="text" name="date_crea_adh" id="date_crea_adh" value="{$member->creation_date}" maxlength="10" {$disabled.date_crea_adh}{if $required.date_crea_adh eq 1} required{/if}/>
						<span class="exemple">{_T string="(dd/mm/yyyy format)"}</span>
					</p>
					<p>
						<label for="info_adh" class="bline">{_T string="Other informations (admin):"}</label>
						<textarea name="info_adh" id="info_adh" cols="50" rows="6" {$disabled.info_adh}{if $required.info_adh eq 1} required{/if}>{$member->others_infos_admin|htmlspecialchars}</textarea><br/>
						<span class="exemple labelalign">{_T string="This comment is only displayed for admins and staff members."}</span>
					</p>
    {/if}
					<p>
						<label for="info_public_adh" class="bline">{_T string="Other informations:"}</label> 
						<textarea name="info_public_adh" id="info_public_adh" cols="61" rows="6" {$disabled.info_public_adh}{if $required.info_public_adh eq 1} required{/if}>{$member->others_infos|htmlspecialchars}</textarea>
    {if $login->isAdmin() or $login->isStaff()}
						<br/><span class="exemple labelalign">{_T string="This comment is reserved to the member."}</span>
    {/if}
					</p>
    {if $groups|@count != 0}
                    <p>
                        <span class="bline">{_T string="Groups:"}</span>
        {if $member->isAGroupManager()}
                        <a class="button" id="btngroups">{_T string="Manage user's groups"}</a>
        {/if}
                        <span id="usergroups_form">
    {foreach from=$groups item=group}
        {if $member->isGroupMember($group->getName())}
                            <input type="hidden" name="groups_adh[]" value="{$group->getId()}|{$group->getName()}|{$member->isGroupManager($group->getName())}"/>
        {/if}
    {/foreach}
                        </span>
                        <span id="usergroups">
    {foreach from=$groups item=group name=groupsiterate}
        {if $member->isGroupMember($group->getName())}
            {if not $smarty.foreach.groupsiterate.first}, {/if}
            {if $member->isGroupManager($group->getName())}
                {_T string="Manager for '%groupname'" pattern="/%groupname/" replace=$group->getName()}
            {else}
                {_T string="Member of '%groupname'" pattern="/%groupname/" replace=$group->getName()}
            {/if}
        {/if}
    {/foreach}
                        </span>
                    </p>
    {/if}
				</div>
			</fieldset>

    {include file="display_dynamic_fields.tpl" is_form=true}
		</div>
		<div class="button-container">
			<input type="submit" name="valid" id="btnsave" value="{_T string="Save"}"/>
			<input type="hidden" name="id_adh" value="{$member->id}"/>
		</div>
		<p>{_T string="NB : The mandatory fields are in"} <span class="required">{_T string="red"}</span></p>
		</form> 
		<script type="text/javascript">
            $(function() {ldelim}
                $('#is_company').change(function(){ldelim}
                    //console.log(this.checked);
                    $('#company_field').toggleClass('hidden');
                {rdelim});

                _collapsibleFieldsets();

                $('#ddn_adh').datepicker({ldelim}
                    changeMonth: true,
                    changeYear: true,
                    showOn: 'button',
                    buttonImage: '{$template_subdir}images/calendar.png',
                    buttonImageOnly: true,
                    maxDate: '-0d',
                    yearRange: 'c-100'
                {rdelim});
                $('#date_crea_adh').datepicker({ldelim}
                    changeMonth: true,
                    changeYear: true,
                    showOn: 'button',
                    buttonImage: '{$template_subdir}images/calendar.png',
                    buttonImageOnly: true,
                    maxDate: '-0d',
                    yearRange: 'c-10'
                {rdelim});

                {* Groups popup *}
                $('#btngroups').click(function(){ldelim}
                    var _groups = [];
                    $('#usergroups_form input').each(function(){ldelim}
                        _group = $(this).val().split('|');
                        _groups[_groups.length] = {ldelim}
                            id: _group[0],
                            name: _group[1],
                            manager: _group[2]
                        {rdelim};
                    {rdelim});
                    $.ajax({ldelim}
                        url: 'ajax_groups.php',
                        type: "POST",
                        data: {ldelim}ajax: true, groups: _groups{rdelim},
                        {include file="js_loader.tpl"},
                        success: function(res){ldelim}
                            _groups_dialog(res, _groups);
                        {rdelim},
                        error: function() {ldelim}
                            alert("{_T string="An error occured displaying groups interface :(" escape="js"}");
                        {rdelim}
                    {rdelim});
                    return false;
                {rdelim});

                var _groups_dialog = function(res, _groups){ldelim}
                    var _el = $('<div id="groups_list" title="{_T string="Groups selection" escape="js"}"> </div>');
                    _el.appendTo('body').dialog({ldelim}
                        modal: true,
                        hide: 'fold',
                        width: '80%',
                        height: 500,
                        close: function(event, ui){ldelim}
                            _el.remove();
                        {rdelim}
                    {rdelim});
                    _groups_ajax_mapper(res, _groups);
                {rdelim}

                var _groups_ajax_mapper = function(res, _groups){ldelim}
                    $('#groups_list').append(res);
                    $('#btnvalid').button().click(function(){ldelim}
                        //remove actual groups
                        $('#usergroups_form').empty();
                        var _groups = new Array();
                        var _groups_str = '';
                        $('li[id^="group_"]').each(function(){ldelim}
                            //get group values
                            _gid = this.id.substring(6, this.id.length);
                            _gname = $(this).text();
                            _gmanager = $(this).find('input[type=checkbox]:checked').length;
                            _groups[_groups.length] = this.id.substring(6, this.id.length);
                            $('#usergroups_form').append(
                                '<input type="hidden" value="' +
                                _gid + '|' + _gname + '|' + _gmanager +
                                '" name="groups_adh[]">'
                            );
                            if ( _groups_str != '' ) {ldelim}
                                _groups_str += ', ';
                            {rdelim}
                            if ( _gmanager == 0 ) {ldelim}
                                _groups_str += '{_T string="Member of '%groupname'" escape="js"}'.replace(/%groupname/, _gname);
                            {rdelim} else {ldelim}
                                _groups_str += '{_T string="Manager for '%groupname'" escape="js"}'.replace(/%groupname/, _gname);
                            {rdelim}
                        {rdelim});
                        $('#usergroups').html(_groups_str);
                        $('#groups_list').dialog("close");
                    {rdelim});
                    //Remap links
                    var _none = $('#none_selected').clone();
                    $('li input[type=checkbox]').click(function(e){ldelim}
                        e.stopPropagation();
                    {rdelim});
                    $('li[id^="group_"]').click(function(){ldelim}
                        $(this).remove();
                        if ( $('#selected_groups ul li').length == 0 ) {ldelim}
                            $('#selected_groups ul').append(_none);
                        {rdelim}
                    {rdelim});
                    $('#listing a').click(function(){ldelim}
                        var _gid = this.href.substring(this.href.indexOf('?')+10);
                        var _gname = $(this).text();
                        $('#none_selected').remove()
                        if ( $('#group_' + _gid).length == 0 ) {ldelim}
                            var _li = '<li id="group_' + _gid + '"><input type="checkbox" name="managers[]" id="manager_' + _gid + '"/><label for="manager_' + _gid + '">' + _gname + '</label></li>';
                            $('#selected_groups ul').append(_li);
                            $('#group_' + _gid).click(function(){ldelim}
                                $(this).remove();
                                if ( $('#selected_groups ul li').length == 0 ) {ldelim}
                                    $('#selected_groups ul').append(_none);
                                {rdelim}
                            {rdelim});
                            $('#manager_' + _gid).click(function(e){ldelim}
                                e.stopPropagation();
                            {rdelim});
                        {rdelim}
                        return false;
                    {rdelim});

                {rdelim}

                {include file="photo_dnd.tpl"}
            {rdelim});
		</script>
{/if}