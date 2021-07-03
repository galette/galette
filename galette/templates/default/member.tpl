{extends file="$parent_tpl"}

{block name="content"}
{if isset($navigate) and $navigate|@count != 0}
    <nav>
        <a href="{if isset($navigate.prev)}{path_for name="editMember" data=["id" => $navigate.prev]}{else}#{/if}" class="button{if !isset($navigate.prev)} selected{/if}">
            <i class="fas fa-step-backward"></i>
            {_T string="Previous"}
        </a>
        {$navigate.pos}/{$navigate.count}
        <a href="{if isset($navigate.next)}{path_for name="editMember" data=["id" => $navigate.next]}{else}#{/if}" class="button{if !isset($navigate.next)} selected{/if}">
            {_T string="Next"}
            <i class="fas fa-step-forward"></i>
        </a>
    </nav>
{/if}
        <form action="{if $self_adh}{path_for name="storeselfmembers"}{elseif !$member->id}{path_for name="doAddMember"}{else}{path_for name="doEditMember" data=["id" => $member->id]}{/if}" method="post" enctype="multipart/form-data" id="form">
        <div class="bigtable">
            <p>{_T string="NB : The mandatory fields are in"} <span class="required">{_T string="red"}</span></p>
    {if !$self_adh}
            <div>
        {if $member->hasParent() && !$member->isDuplicate()}
                <strong>{_T string="Attached to:"}
                <a href="{path_for name="member" data=["id" => $member->parent->id]}">{$member->parent->sfullname}</a></strong><br/>
            {if $login->isAdmin() or $login->isStaff() or $login->id eq $member->parent->id}
                <label for="detach_parent">{_T string="Detach?"}</label>
                <input type="checkbox" name="detach_parent" id="detach_parent" value="1"/>
            {/if}
        {else if ($login->isAdmin() or $login->isStaff()) and !$member->hasChildren() and isset($members.list)}
            <input type="checkbox" name="attach" id="attach" value="1"{if $member->isDuplicate()} checked="checked"{/if}/>
            <label for="attach"><i class="fas fa-link"></i> {_T string="Attach member"}</label>
            <span id="parent_id_elt" class="sr-only">
                <select name="parent_id" id="parent_id" class="nochosen">
                    <option value="">{_T string="-- select a name --"}</option>
                    {foreach $members.list as $k=>$v}
                        <option value="{$k}"{if $member->isDuplicate() && isset($member->parent) && $member->parent->id eq $k} selected="selected"{/if}>{$v}</option>
                    {/foreach}
                </select>
            </span>
            {if $member->isDuplicate()}
                <input type="hidden" name="duplicate" value="1" />
            {/if}
        {else if $member->hasChildren()}
            <strong>{_T string="Parent of:"}</strong>
            {foreach from=$member->children item=child}
                <a href="{path_for name="member" data=["id" => $child->id]}">{$child->sfullname}</a>{if not $child@last}, {/if}
            {/foreach}
            </tr>
        {/if}
            </div>
    {/if}

    {* Main form entries*}
    {include file="forms_types.tpl"}
    {* Dynamic entries *}
    {include file="edit_dynamic_fields.tpl" object=$member}

                    <p>
            {if !$member->id && !$self_adh }
               <label for="redirect_on_create">{_T string="After member creation:"}</label>
               <select name="redirect_on_create" id="redirect_on_create">
                  <option value="{constant('Galette\Entity\Adherent::AFTER_ADD_DEFAULT')}"{if $preferences->pref_redirect_on_create  == constant('Galette\Entity\Adherent::AFTER_ADD_DEFAULT')} selected="selected"{/if}>{_T string="create a new contribution (default action)"}</option>
                  <option value="{constant('Galette\Entity\Adherent::AFTER_ADD_TRANS')}"{if $preferences->pref_redirect_on_create  == constant('Galette\Entity\Adherent::AFTER_ADD_TRANS')} selected="selected"{/if}>{_T string="create a new transaction"}</option>
                  <option value="{constant('Galette\Entity\Adherent::AFTER_ADD_NEW')}"{if $preferences->pref_redirect_on_create  == constant('Galette\Entity\Adherent::AFTER_ADD_NEW')} selected="selected"{/if}>{_T string="create another new member"}</option>
                  <option value="{constant('Galette\Entity\Adherent::AFTER_ADD_SHOW')}"{if $preferences->pref_redirect_on_create  == constant('Galette\Entity\Adherent::AFTER_ADD_SHOW')} selected="selected"{/if}>{_T string="show member"}</option>
                  <option value="{constant('Galette\Entity\Adherent::AFTER_ADD_LIST')}"{if $preferences->pref_redirect_on_create  == constant('Galette\Entity\Adherent::AFTER_ADD_LIST')} selected="selected"{/if}>{_T string="go to members list"}</option>
                  <option value="{constant('Galette\Entity\Adherent::AFTER_ADD_HOME')}"{if $preferences->pref_redirect_on_create  == constant('Galette\Entity\Adherent::AFTER_ADD_HOME')} selected="selected"{/if}>{_T string="go to main page"}</option>
               </select>
               <br/>
            {/if}

    {if $pref_mail_method neq constant('Galette\Core\GaletteMail::METHOD_DISABLED') and (!$self_adh and ($login->isAdmin() or $login->isStaff()))}
                        <br/><label for="mail_confirm">
        {if $member->id}
                            {_T string="Notify member his account has been modified"}
        {else}
                            {_T string="Notify member his account has been created"}
        {/if}
                        </label>
                        <input type="checkbox" name="mail_confirm" id="mail_confirm" value="1" {if isset($smarty.post.mail_confirm) and $smarty.post.mail_confirm != ""}checked="checked"{/if}/>
                        <br/><span class="exemple">
        {if $member->id}
                            {_T string="Member will be notified by email his account has been modified."}
        {else}
                            {_T string="Member will receive his username and password by email, if he has an address."}
        {/if}
                        </span>
    {/if}
                    </p>
        </div>
        <div class="button-container">
            <button type="submit" name="valid" class="action">
                <i class="fas fa-save fa-fw"></i> {_T string="Save"}
            </button>


            {foreach item=entry from=$hidden_elements}
                {if $entry->field_id neq 'mdp_adh'}
                    {assign var="title" value=null}
                    {assign var="tip" value=null}
                    {assign var="size" value=null}
                    {assign var="propname" value=$entry->propname}
                    {if $entry->field_id eq 'activite_adh'}
                        {assign var="value" value=$member->isActive()}
                    {else}
                        {assign var="value" value=$member->$propname}
                    {/if}
                    {assign var="checked" value=null}
                    {assign var="example" value=null}

                    {if $value neq '' and $entry->field_id neq 'parent_id'}
                        {include
                            file="forms_types/hidden.tpl"
                            name=$entry->field_id
                            id=$entry->field_id
                            value=$value
                        }
                    {/if}
                {/if}
            {/foreach}

            <a href="#" id="back2top">{_T string="Back to top"}</a>
        </div>
        </form>
{/block}

{block name="javascripts"}
        <script type="text/javascript">
            {include file="js_chosen_adh.tpl" js_chosen_id="#parent_id"}
            $(function() {
                $('#is_company').change(function(){
                    $('#company_field').toggleClass('hidden');
                    $('#company_field').backgroundFade(
                        {
                            sColor:'#ffffff',
                            eColor:'#DDDDFF',
                            steps:10
                        },
                        function() {
                            $(this).backgroundFade(
                                {
                                    sColor:'#DDDDFF',
                                    eColor:'#ffffff'
                                }
                            );
                        });
                });

                _collapsibleFieldsets();

                $('#ddn_adh').datepicker({
                    changeMonth: true,
                    changeYear: true,
                    showOn: 'button',
                    maxDate: '-0d',
                    yearRange: '-200:+0',
                    buttonText: '<i class="far fa-calendar-alt"></i> <span class="sr-only">{_T string="Select a date" escape="js"}</span>'
                });
                $('#date_crea_adh').datepicker({
                    changeMonth: true,
                    changeYear: true,
                    showOn: 'button',
                    maxDate: '-0d',
                    yearRange: 'c-10:c+0',
                    buttonText: '<i class="far fa-calendar-alt"></i> <span class="sr-only">{_T string="Select a date" escape="js"}</span>'
                });

{if !$self_adh}
                {* Groups popup *}
                $('#btngroups, #btnmanagedgroups').click(function(){
                    var _managed = false;
                    if ( $(this).attr('id') == 'btnmanagedgroups' ) {
                        _managed = true;
                    }
                    var _groups = [];
                    var _form = (_managed) ? 'managed' : 'user';
                    $('#' + _form + 'groups_form input').each(function(){
                        _group = $(this).val().split('|');
                        _groups[_groups.length] = {
                            id: _group[0],
                            name: _group[1]
                        };
                    });
                    $.ajax({
                        url: '{path_for name="ajax_groups"}',
                        type: "POST",
                        data: {
                            ajax: true,
                            groups: _groups,
                            managed: _managed
                        },
                        {include file="js_loader.tpl"},
                        success: function(res){
                            _groups_dialog(res, _groups, _managed);
                        },
                        error: function() {
                            alert("{_T string="An error occurred displaying groups interface :(" escape="js"}");
                        }
                    });
                    return false;
                });

                var _groups_dialog = function(res, _groups, _managed){
                    var _title = '{_T string="Groups selection" escape="js"}';
                    if ( _managed ) {
                        _title = '{_T string="Managed groups selection" escape="js"}';
                    }
                    var _el = $('<div id="ajax_groups_list" title="' + _title + '"> </div>');
                    _el.appendTo('body').dialog({
                        modal: true,
                        hide: 'fold',
                        width: '80%',
                        height: 500,
                        close: function(event, ui){
                            _el.remove();
                        },
                        create: function (event, ui) {
                            if ($(window ).width() < 767) {
                                $(this).dialog('option', {
                                        'width': '95%',
                                        'draggable': false
                                });
                            }
                        }
                    });
                    _groups_ajax_mapper(res, _groups, _managed);
                }

                var _groups_ajax_mapper = function(res, _groups, _managed){
                    $('#ajax_groups_list').append(res);
                    $('#btnvalid').button().click(function(){
                        //remove actual groups
                        var _form = (_managed) ? 'managed' : 'user';
                        $('#' + _form + 'groups_form').empty();
                        var _groups = new Array();
                        var _groups_str = '<br/><strong>';
                        if ( _managed ) {
                            _groups_str += '{_T string="Manager for:" escape="js"}';
                        } else {
                            _groups_str += '{_T string="Member of:" escape="js"}';
                        }
                        _groups_str += '</strong> ';

                        $('li[id^="group_"]').each(function(){
                            //get group values
                            _gid = this.id.substring(6, this.id.length);
                            _gname = $(this).text();
                            _groups[_groups.length] = this.id.substring(6, this.id.length);
                            var _iname = (_managed) ? 'groups_managed_adh' : 'groups_adh';
                            $('#' + _form + 'groups_form').append(
                                '<input type="hidden" value="' +
                                _gid + '|' + _gname + '|' +
                                '" name="' + _iname + '[]">'
                            );
                            if ( _groups.length > 1 ) {
                                _groups_str += ', ';
                            }
                            _groups_str += _gname;
                        });
                        $('#' + _form + 'groups').html(_groups_str);
                        $('#ajax_groups_list').dialog("close");
                    });
                    //Remap links
                    var _none = $('#none_selected').clone();
                    $('li input[type=checkbox]').click(function(e){
                        e.stopPropagation();
                    });
                    $('li[id^="group_"]').click(function(){
                        $(this).remove();
                        if ( $('#selected_groups ul li').length == 0 ) {
                            $('#selected_groups ul').append(_none);
                        }
                    });
                    $('#listing a').click(function(e){
                        e.preventDefault();
                        var _gid = this.href.match(/.*\/(\d+)$/)[1];
                        var _gname = $(this).text();
                        $('#none_selected').remove()
                        if ( $('#group_' + _gid).length == 0 ) {
                            var _li = '<li id="group_' + _gid + '"><i class="fas fa-user-minus"></i> ' + _gname + '</li>';
                            $('#selected_groups ul').append(_li);
                            $('#group_' + _gid).click(function(){
                                $(this).remove();
                                if ( $('#selected_groups ul li').length == 0 ) {
                                    $('#selected_groups ul').append(_none);
                                }
                            });
                        }
                        return false;
                    });

                }

    {if !$self_adh and !$member->hasChildren()}
                {* Parent selection *}
                $('#parent_id_elt').removeClass('sr-only');
        {if !$member->isDuplicate()}
                $('#parent_id_elt').hide();
        {/if}
                $('#attach').on('click', function() {
                    var _checked = $(this).is(':checked');
                    $('#parent_id_elt').toggle();
                });
    {/if}

    {if !$self_adh}
        {if $parent_fields|@count gt 0}
                $('#detach_parent').on('change', function(){
                    var _checked = $(this).is(':checked');
                    var _changes = '';
            {foreach item=req from=$parent_fields}
                    _changes += '#{$req}';
                {if !$req@last}
                    _changes += ',';
                {/if}
            {/foreach}
                    if (_checked) {
                        $(_changes).attr('required', 'required');
                    } else {
                        $(_changes).removeAttr('required');
                    }
                });

                $('#parent_id').on('change', function(){
                    var _hasParent = $(this).attr('value') != '';
                    var _changes = '';
            {foreach item=req from=$parent_fields}
                    _changes += '#{$req}';
                {if !$req@last}
                    _changes += ',';
                {/if}
            {/foreach}
                    if (_hasParent) {
                        $(_changes).removeAttr('required');
                    } else {
                        $(_changes).attr('required', 'required');
                    }
                });
        {/if}
    {/if}
                {include file="photo_dnd.tpl"}

                $('#ddn_adh').on('blur', function() {
                    var _bdate = $(this).val();
                    if ('{_T string="Y-m-d"}' === 'Y-m-d') {
                        _bdate = new Date(_bdate);
                    } else {
                        //try for dd/mm/yyyy
                        var _dparts = _bdate.split("/");
                        _bdate = new Date(_dparts[2], _dparts[1] - 1, _dparts[0]);
                    }

                    if (! isNaN(_bdate.getTime())) {
                        var _today = new Date();
                        var _age = Math.floor((_today-_bdate) / (365.25 * 24 * 60 * 60 * 1000));
                        $('#member_age').html('{_T string=" (%age years old)"}'.replace(/%age/, _age))
                    } else {
                        $('#member_age').html('');
                    }
                });
{/if}
            });
        </script>
{/block}
