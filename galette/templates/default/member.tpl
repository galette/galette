{extends file="$parent_tpl"}

{block name="content"}
{if isset($navigate) and $navigate|@count != 0}
    <nav>
        <a id="prev" href="{if isset($navigate.prev)}{path_for name="editmember" data=["action" => {_T string="edit" domain="routes"}, "id" => $navigate.prev]}{else}#{/if}" class="button{if !isset($navigate.prev)} selected{/if}">{_T string="Previous"}</a>
        {$navigate.pos}/{$navigate.count}
        <a id="next" href="{if isset($navigate.next)}{path_for name="editmember" data=["action" => {_T string="edit" domain="routes"}, "id" => $navigate.next]}{else}#{/if}" class="button{if !isset($navigate.next)} selected{/if}">{_T string="Next"}</a>
    </nav>
{/if}
        <form action="{if $self_adh}{path_for name="storemembers" data=["self" => {_T string="subscribe" domain="routes"}]}{else}{path_for name="storemembers"}{/if}" method="post" enctype="multipart/form-data" id="form">
        <div class="bigtable">
{if $self_adh and $head_redirect}
            <div id="infobox">
                <h1>{_T string="Account registered!"}</h1>
                <p>
    {if $pref_mail_method == constant('Galette\Core\GaletteMail::METHOD_DISABLED') or $member->email eq ""}
                    {_T string="Your subscription has been registered."}
    {else}
                    {_T string="Your subscription has been registered, you will receive a recapitulative email soon (remember to check your spam box ;) )."}
    {/if}
                    <br/>{_T string="You'll be redirected to the login page in a few seconds"}
                </p>
            </div>
{else}
            <p>{_T string="NB : The mandatory fields are in"} <span class="required">{_T string="red"}</span></p>
    {if !$self_adh}
            <div>
        {if $member->hasParent()}
                <strong>{_T string="Attached to:"}
                <a href="{path_for name="member" data=["id" => $member->parent->id]}">{$member->parent->sfullname}</a></strong><br/>
            {if $login->isAdmin() or $login->isStaff() or $login->id eq $member->parent->id}
                <label for="detach_parent">{_T string="Detach?"}</label>
                <input type="checkbox" name="detach_parent" id="detach_parent" value="1"/>
            {/if}
        {else if ($login->isAdmin() or $login->isStaff()) and !$member->hasChildren()}
            <a href="#" class="button" id="btnattach">{_T string="Attach member"}</a>
        {/if}
            </div>
    {/if}

    {* Main form entries*}
    {include file="forms_types.tpl"}
    {* Dynamic entries *}
    {include file="edit_dynamic_fields.tpl" object=$member}

    {if $pref_mail_method neq constant('Galette\Core\GaletteMail::METHOD_DISABLED') and (!$self_adh and ($login->isAdmin() or $login->isStaff()))}
                    <p>
                        <label for="mail_confirm">
        {if $member->id}
                            {_T string="Notify member his account has been modified"}
        {else}
                            {_T string="Notify member his account has been created"}
        {/if}
                        </label>
                        <input type="checkbox" name="mail_confirm" id="mail_confirm" value="1" {if isset($smarty.post.mail_confirm) and $smarty.post.mail_confirm != ""}checked="checked"{/if}/>
                        <br/><span class="exemple">
        {if $member->id}
                            {_T string="Member will be notified by mail his account has been modified."}
        {else}
                            {_T string="Member will receive his username and password by email, if he has an address."}
        {/if}
                        </span>
                    </p>
    {/if}
        </div>
        <div class="button-container">
            <input type="submit" name="valid" id="btnsave" value="{_T string="Save"}"/>

            {foreach item=entry from=$hidden_elements}
                {if $entry->field_id neq 'mdp_adh'}
                    {assign var="title" value=null}
                    {assign var="tip" value=null}
                    {assign var="size" value=null}
                    {assign var="propname" value=$entry->propname}
                    {if $entry->field_id eq 'parent_id' }
                        {if $member->$propname}
                            {assign var="value" value=$member->$propname->id}
                        {else}
                            {assign var="value" value=""}
                        {/if}
                    {else}
                        {assign var="value" value=$member->$propname}
                    {/if}
                    {assign var="checked" value=null}
                    {assign var="example" value=null}

                    {if $value neq '' or $entry->field_id eq 'parent_id'}
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
{/if}
{/block}

{block name="javascripts"}
        <script type="text/javascript">
            $(function() {
                $('#is_company').change(function(){
                    //console.log(this.checked);
                    $('#company_field').toggleClass('hidden');
                });

                _collapsibleFieldsets();

                $('#ddn_adh').datepicker({
                    changeMonth: true,
                    changeYear: true,
                    showOn: 'button',
                    buttonImage: '{base_url}/{$template_subdir}images/calendar.png',
                    buttonImageOnly: true,
                    maxDate: '-0d',
                    yearRange: 'c-100:c+0',
                    buttonText: '{_T string="Select a date" escape="js"}'
                });
                $('#date_crea_adh').datepicker({
                    changeMonth: true,
                    changeYear: true,
                    showOn: 'button',
                    buttonImage: '{base_url}/{$template_subdir}images/calendar.png',
                    buttonImageOnly: true,
                    maxDate: '-0d',
                    yearRange: 'c-10:c+0',
                    buttonText: '{_T string="Select a date" escape="js"}'
                });

{if !$self_adh and !$head_redirect}
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
                            alert("{_T string="An error occured displaying groups interface :(" escape="js"}");
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
                            var _li = '<li id="group_' + _gid + '">' + _gname + '</li>';
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
                {* Members popup *}
                var _btnattach_mapping = function(){
                    $('#btnattach').click(function(){
                        _mode = ($(this).attr('id') == 'btnusers_small') ? 'members' : 'managers';
                        var _persons = $('input[name="' + _mode + '[]"]').map(function() {
                            return $(this).val();
                        }).get();
                        $.ajax({
                            url: '{path_for name="ajaxMembers"}',
                            type: "POST",
                            data: {
                                from: 'attach',
                                id_adh: {if isset($member->id) and $member->id neq ''}{$member->id}{else}'new'{/if}
                            },
                            {include file="js_loader.tpl"},
                            success: function(res){
                                _members_dialog(res, _mode);
                            },
                            error: function() {
                                alert("{_T string="An error occured displaying members interface :(" escape="js"}");
                            }
                        });
                        return false;
                    });
                }
                _btnattach_mapping();

                var _members_dialog = function(res, mode){
                    var _title = '{_T string="Attached member selection" escape="js"}';
                    var _el = $('<div id="members_list" title="' + _title  + '"> </div>');
                    _el.appendTo('body').dialog({
                        modal: true,
                        hide: 'fold',
                        width: '60%',
                        height: 400,
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
                    _members_ajax_mapper(res);
                }

                var _members_ajax_mapper = function(res){
                    $('#members_list').append(res);


                    $('#members_list tbody').find('a').each(function(){
                        $(this).click(function(){
                            var _id = this.href.match(/.*\/(\d+)$/)[1];
                            $('#parent_id').attr('value', _id);
                            var _parent_name;
                            if ($('#parent_name').length > 0) {
                                _parent_name = $('#parent_name');
                            } else {
                                _parent_name = $('<div id="parent_name"/>');
                                $('#btnattach').after(_parent_name);
                            }
                            _parent_name.html($(this).html());

                            //remove required attribute on address and mail fields if member has a parent
                            var _parentfields = '';
        {if $parent_fields|@count gt 0}
            {foreach item=req from=$parent_fields}
                            _parentfields += '#{$req}';
                {if !$req@last}
                            _parentfields += ',';
                {/if}
            {/foreach}
        {/if}
                            $(_parentfields).removeAttr('required');

                            $('#members_list').dialog('close');
                            return false;
                        }).attr('title', '{_T string="Click to choose this member as parent"}');
                    });
                    //Remap links
                    $('#members_list .pages a').click(function(){
                        var gid = $('#the_id').val();

                        $.ajax({
                            url: this.href,
                            type: "POST",
                            data: {
                                from: 'attach',
                                id_adh: {if isset($member->id) and $member->id neq ''}{$member->id}{else}'new'{/if}
                            },
                            {include file="js_loader.tpl"},
                            success: function(res){
                                $('#members_list').empty();
                                _members_ajax_mapper(res);
                            },
                            error: function() {
                                alert("{_T string="An error occured displaying members interface :(" escape="js"}");
                            }
                        });
                        return false;
                    });
                }
    {/if}

    {if !$self_adh and $member->hasParent()}
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
