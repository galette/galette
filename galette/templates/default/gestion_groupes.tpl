<div class="panels">
    <aside id="groups_list">
        <header class="ui-state-default ui-state-active">
            {_T string="Groups"}
        </header>
        <div id="groups_tree">
            <ul>
{foreach item=g from=$groups_root}
    {include file="group_tree_item.tpl" item=$g}
{/foreach}
            </ul>
        </div>
{if $login->isAdmin() or $login->isStaff()}
        <div class="center">
            <a href="gestion_groupes.php?new" id="btnadd" class="button">{_T string="New group"}</a>
        </div>
{/if}
    </aside>
    <section id="group_infos">
        <header class="ui-state-default ui-state-active">
            {_T string="Group informations"}
        </header>
        <div id="group_infos_wrapper">
            {include file="group.tpl" group=$group groups=$groups}
        </div>
    </section>
</div>
<form action="gestion_groupes.php" method="POST">
    <div class="button-container">
        <input type="submit" name="pdf" value="{_T string="Export as PDF"}" title="{_T string="Export all groups and their members as PDF"}"/>
    </div>
</form>
<script type="text/javascript">
    $(function() {
        var _mode;
        {* Tree stuff *}
        $('#groups_tree').jstree({
{if $groups|@count > 0}
            'core': {
                'initially_open': [{foreach item=g from=$groups}'group_{$g->getId()}',{/foreach}]
            },
{/if}
            'themes': {
                'url': '{$template_subdir}/jstree/style.css'
            },
            'unique' : {
                'error_callback': function (n, p, f) {
                    alert("Duplicate node `" + n + "` with function `" + f + "`!");
                }
            },
            'ui': {
                'select_limit': 1,
                'initially_select': 'group_{$group->getId()}'
            },
            'plugins': [ 'themes', 'html_data', 'dnd', 'ui' ]
        }).bind("move_node.jstree", function (e, data) {
            var _gid = data.rslt.o.attr('id').substring(6);
            var _to = data.rslt.np.attr('id').substring(6);
            $.ajax({
                url: 'ajax_group.php',
                type: "POST",
                data: {
                    id_group: _gid,
                    ajax: true,
                    reorder: true,
                    to: _to
                },
                datatype: 'json',
                {include file="js_loader.tpl"},
                success: function(res){
                    var _res = jQuery.parseJSON(res);
                    if ( _res.success == false ) {
                        alert("{_T string="Missing destination group" escape="js"}");
                        {* TODO: revert preceding move so the tree is ok with database *}
                    }
                },
                error: function() {
                    {* TODO: revert preceding move so the tree is ok with database *}
                    alert("{_T string="An error occured reordering groups :(" escape="js"}");
                }
            });
        }).delegate(
            'a',
            'click',
            function (event) {
                event.preventDefault();
                if ( $('#errorbox') ) {
                    $('#errorbox').remove();
                }
                if ( $(this).attr('href') != '#' ) {
                    var _gid = $(this).parent('li').attr('id').substring(6);
                    $.ajax({
                        url: 'ajax_group.php',
                        type: "POST",
                        data: {
                            id_group: _gid,
                            ajax: true
                        },
                        {include file="js_loader.tpl"},
                        success: function(res){
                            $('#group_infos_wrapper').empty().append(res);
                            $('#group_infos_wrapper input:submit, #group_infos_wrapper .button').button();
                            _btnuser_mapping();
                        },
                        error: function() {
                            alert("{_T string="An error occured loading selected group :(" escape="js"}");
                        }
                    });
                }
            }
        );

        {* New group *}
        $('#btnadd').click(function(){
        var _href = $(this).attr('href');
            var _el = $('<div id="add_group" class="center" title="{_T string="Add a new group"}"><label for="new_group_name">{_T string="Name:"}</label><input type="text" name="new_group_name" id="new_group_name" required/></div>');
            _el.appendTo('body').dialog({
                modal: true,
                hide: 'fold',
                buttons: {
                    "{_T string="Create" escape="js"}": function() {
                        var _name = $('#new_group_name').val();
                        if ( _name != '' ) {
                            //check uniqueness
                            $.ajax({
                                url: 'ajax_unique_group.php',
                                type: "POST",
                                data: {
                                    ajax: true,
                                    gname: _name
                                },
                                {include file="js_loader.tpl"},
                                success: function(res){
                                    var _res = jQuery.parseJSON(res);
                                    if ( _res.success == false ) {
                                        alert('{_T string="The group name you have requested already exits in the database."}');
                                    } else {
                                        $(location).attr('href', _href + '&group_name=' + _name);
                                    }
                                },
                                error: function() {
                                    alert("{_T string="An error occured checking name uniqueness :(" escape="js"}");
                                }
                            });
                        } else {
                            alert('{_T string="Pleade provide a group name" escape="js"}');
                        }
                    }
                },
                close: function(event, ui){
                    _el.remove();
                }
            });
            return false;
        });

        {* Members popup *}
        var _btnuser_mapping = function(){
            $('#btnusers_small, #btnmanagers_small').click(function(){
                _mode = ($(this).attr('id') == 'btnusers_small') ? 'members' : 'managers';
                var _persons = $('input[name="' + _mode + '[]"]').map(function() {
                    return $(this).val();
                }).get();
                $.ajax({
                    url: 'ajax_members.php',
                    type: "POST",
                    data: {
                        ajax: true,
                        multiple: true,
                        from: 'groups',
                        gid: $('#id_group').val(),
                        mode: _mode,
                        members: _persons
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
        _btnuser_mapping();

        var _members_dialog = function(res, mode){
            var _title = '{_T string="Group members selection" escape="js"}';
            if ( mode == 'managers' ) {
                _title = '{_T string="Group managers selection" escape="js"}';
            }
            var _el = $('<div id="members_list" title="' + _title  + '"> </div>');
            _el.appendTo('body').dialog({
                modal: true,
                hide: 'fold',
                width: '80%',
                height: 550,
                close: function(event, ui){
                    _el.remove();
                }
            });
            _members_ajax_mapper(res, $('#group_id').val(), mode);

        }

        var _members_ajax_mapper = function(res, gid, mode){
            $('#members_list').append(res);
            $('#selected_members ul').css(
                'max-height',
                $('#members_list').innerHeight() - $('#btnvalid').outerHeight() - $('#selected_members header').outerHeight() - 65 // -65 to fix display; do not know why
            );
            $('#btnvalid').button().click(function(){
                //store entities in the original page so they can be saved
                var _container;
                if ( mode == 'managers' ) {
                    _container = $('#group_managers');
                } else {
                    _container = $('#group_members');
                }
                var _persons = new Array();
                $('li[id^="member_"]').each(function(){
                    _persons[_persons.length] = this.id.substring(7, this.id.length);
                });
                $('#members_list').dialog("close");

                $.ajax({
                    url: 'ajax_group_members.php',
                    type: "POST",
                    data: {
                        persons: _persons,
                        person_mode: mode
                    },
                    {include file="js_loader.tpl"},
                    success: function(res){
                        _container.find('table.listing').remove();
                        _container.children('div').append(res);
                    },
                    error: function() {
                        alert("{_T string="An error occured displaying members interface :(" escape="js"}");
                    }
                });
            });
            //Remap links
            var _none = $('#none_selected').clone();
            $('li[id^="member_"]').click(function(){
                $(this).remove();
                if ( $('#selected_members ul li').length == 0 ) {
                    $('#selected_members ul').append(_none);
                }
            });
            $('#members_list #listing tbody a').click(function(){
                var _mid = this.href.substring(this.href.indexOf('?')+8);
                var _mname = $(this).text();
                $('#none_selected').remove()
                if ( $('#member_' + _mid).length == 0 ) {
                    var _li = '<li id="member_' + _mid + '">' + _mname + '</li>';
                    $('#selected_members ul').append(_li);
                    $('#member_' + _mid).click(function(){
                        $(this).remove();
                        if ( $('#selected_members ul li').length == 0 ) {
                            $('#selected_members ul').append(_none);
                        }
                    });
                }
                return false;
            });

            $('#members_list .pages a').click(function(){
                var _page = this.href.substring(this.href.indexOf('?')+6);
                var gid = $('#the_id').val();
                var _members = new Array();
                $('li[id^="member_"]').each(function(){
                    _members[_members.length] = this.id.substring(7, this.id.length);
                });

                $.ajax({
                    url: 'ajax_members.php',
                    type: "POST",
                    data: {
                        ajax: true,
                        from: 'groups',
                        gid: gid,
                        members: _members,
                        page: _page,
                        mode: _mode
                    },
                    {include file="js_loader.tpl"},
                    success: function(res){
                        $('#members_list').empty();
                        _members_ajax_mapper(res, gid, _mode);
                    },
                    error: function() {
                        alert("{_T string="An error occured displaying members interface :(" escape="js"}");
                    }
                });
                return false;
            });
        }
    });
</script>
