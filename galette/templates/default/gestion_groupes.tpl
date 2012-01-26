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
        <div class="center">
            <a href="gestion_groupes.php?new" id="btnadd" class="button">{_T string="New group"}</a>
        </div>
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
<script type="text/javascript">
    $(function() {ldelim}
        var _mode;
        {* Tree stuff *}
        $('#groups_tree').jstree({ldelim}
{if $groups|@count > 0}
            'core': {ldelim}
                'initially_open': [{foreach item=g from=$groups}'group_{$g->getId()}',{/foreach}]
            {rdelim},
{/if}
            'themes': {ldelim}
                'url': '{$template_subdir}/jstree/style.css'
            {rdelim},
            'unique' : {ldelim}
                'error_callback': function (n, p, f) {ldelim}
                    alert("Duplicate node `" + n + "` with function `" + f + "`!");
                {rdelim}
            {rdelim},
            'ui': {ldelim}
                'select_limit': 1,
                'initially_select': 'group_{$group->getId()}'
            {rdelim},
            'plugins': [ 'themes', 'html_data', 'dnd', 'ui' ]
        {rdelim}).bind("move_node.jstree", function (e, data) {ldelim}
            var _gid = data.rslt.o.attr('id').substring(6);
            var _to = data.rslt.np.attr('id').substring(6);
            $.ajax({ldelim}
                url: 'ajax_group.php',
                type: "POST",
                data: {ldelim}id_group: _gid, ajax: true, reorder: true, to: _to{rdelim},
                datatype: 'json',
                {include file="js_loader.tpl"},
                success: function(res){ldelim}
                    var _res = jQuery.parseJSON(res);
                    if ( _res.success == false ) {ldelim}
                        alert("{_T string="Missing destination group" escape="js"}");
                        {* TODO: revert preceding move so the tree is ok with database *}
                    {rdelim}
                {rdelim},
                error: function() {ldelim}
                    {* TODO: revert preceding move so the tree is ok with database *}
                    alert("{_T string="An error occured reordering groups :(" escape="js"}");
                {rdelim}
            {rdelim});
        {rdelim}).delegate(
            'a',
            'click',
            function (event) {ldelim}
                event.preventDefault();
                if ( $('#errorbox') ) {ldelim}
                    $('#errorbox').remove();
                {rdelim}
                if ( $(this).attr('href') != '#' ) {ldelim}
                    var _gid = $(this).parent('li').attr('id').substring(6);
                    $.ajax({ldelim}
                        url: 'ajax_group.php',
                        type: "POST",
                        data: {ldelim}id_group: _gid, ajax: true{rdelim},
                        {include file="js_loader.tpl"},
                        success: function(res){ldelim}
                            $('#group_infos_wrapper').empty().append(res);
                            $('#group_infos_wrapper input:submit, #group_infos_wrapper .button').button();
                            _btnuser_mapping();
                        {rdelim},
                        error: function() {ldelim}
                            alert("{_T string="An error occured loading selected group :(" escape="js"}");
                        {rdelim}
                    {rdelim});
                {rdelim}
            {rdelim}
        );

        {* New group *}
        $('#btnadd').click(function(){ldelim}
        var _href = $(this).attr('href');
            var _el = $('<div id="add_group" class="center" title="{_T string="Add a new group"}"><label for="new_group_name">{_T string="Name:"}</label><input type="text" name="new_group_name" id="new_group_name" required/></div>');
            _el.appendTo('body').dialog({ldelim}
                modal: true,
                hide: 'fold',
                buttons: {ldelim}
                    "{_T string="Create" escape="js"}": function() {ldelim}
                        var _name = $('#new_group_name').val();
                        if ( _name != '' ) {ldelim}
                            //check uniqueness
                            $.ajax({ldelim}
                                url: 'ajax_unique_group.php',
                                type: "POST",
                                data: {ldelim}ajax: true, gname: _name{rdelim},
                                {include file="js_loader.tpl"},
                                success: function(res){ldelim}
                                    var _res = jQuery.parseJSON(res);
                                    if ( _res.success == false ) {ldelim}
                                        alert('{_T string="The group name you have requested already exits in the database."}');
                                    {rdelim} else {ldelim}
                                        $(location).attr('href', _href + '&group_name=' + _name);
                                    {rdelim}
                                {rdelim},
                                error: function() {ldelim}
                                    alert("{_T string="An error occured checking name uniqueness :(" escape="js"}");
                                {rdelim}
                            });
                        {rdelim} else {ldelim}
                            alert('{_T string="Pleade provide a group name" escape="js"}');
                        {rdelim}
                    {rdelim}
                {rdelim},
                close: function(event, ui){ldelim}
                    _el.remove();
                {rdelim}
            {rdelim});
            return false;
        {rdelim});

        {* Members popup *}
        var _btnuser_mapping = function(){ldelim}
            $('#btnusers_small, #btnmanagers_small').click(function(){ldelim}
                _mode = ($(this).attr('id') == 'btnusers_small') ? 'members' : 'managers';
                var _persons = $('input[name="' + _mode + '[]"]').map(function(){ldelim}return $(this).val();{rdelim}).get();
                $.ajax({ldelim}
                    url: 'ajax_members.php',
                    type: "POST",
                    data: {ldelim}ajax: true, multiple: true, from: 'groups', gid: $('#id_group').val(), mode: _mode, members: _persons{rdelim},
                    {include file="js_loader.tpl"},
                    success: function(res){ldelim}
                        _members_dialog(res, _mode);
                    {rdelim},
                    error: function() {ldelim}
                        alert("{_T string="An error occured displaying members interface :(" escape="js"}");
                    {rdelim}
                });
                return false;
            {rdelim});
        {rdelim}
        _btnuser_mapping();

        var _members_dialog = function(res, mode){ldelim}
            var _title = '{_T string="Group members selection" escape="js"}';
            if ( mode == 'managers' ) {ldelim}
                _title = '{_T string="Group managers selection" escape="js"}';
            {rdelim}
            var _el = $('<div id="members_list" title="' + _title  + '"> </div>');
            _el.appendTo('body').dialog({ldelim}
                modal: true,
                hide: 'fold',
                width: '80%',
                height: 500,
                close: function(event, ui){ldelim}
                    _el.remove();
                {rdelim}
            {rdelim});
            _members_ajax_mapper(res, $('#group_id').val(), mode);

        {rdelim}

        var _members_ajax_mapper = function(res, gid, mode){ldelim}
            $('#members_list').append(res);
            $('#selected_members ul').css(
                'max-height',
                $('#members_list').innerHeight() - $('#btnvalid').outerHeight() - $('#selected_members header').outerHeight() - 65 // -65 to fix display; do not know why
            );
            $('#btnvalid').button().click(function(){ldelim}
                //store entities in the original page so they can be saved
                var _container;
                if ( mode == 'managers' ) {ldelim}
                    _container = $('#group_managers');
                {rdelim} else {ldelim}
                    _container = $('#group_members');
                {rdelim}
                var _persons = new Array();
                $('li[id^="member_"]').each(function(){ldelim}
                    _persons[_persons.length] = this.id.substring(7, this.id.length);
                {rdelim});
                $('#members_list').dialog("close");

                $.ajax({ldelim}
                    url: 'ajax_group_members.php',
                    type: "POST",
                    data: {ldelim}persons: _persons, person_mode: mode{rdelim},
                    {include file="js_loader.tpl"},
                    success: function(res){ldelim}
                        _container.find('table.listing').remove();
                        _container.children('div').append(res);
                    {rdelim},
                    error: function() {ldelim}
                        alert("{_T string="An error occured displaying members interface :(" escape="js"}");
                    {rdelim}
                {rdelim});
            {rdelim});
            //Remap links
            var _none = $('#none_selected').clone();
            $('li[id^="member_"]').click(function(){ldelim}
                $(this).remove();
                if ( $('#selected_members ul li').length == 0 ) {ldelim}
                    $('#selected_members ul').append(_none);
                {rdelim}
            {rdelim});
            $('#members_list #listing tbody a').click(function(){ldelim}
                var _mid = this.href.substring(this.href.indexOf('?')+8);
                var _mname = $(this).text();
                $('#none_selected').remove()
                if ( $('#member_' + _mid).length == 0 ) {ldelim}
                    var _li = '<li id="member_' + _mid + '">' + _mname + '</li>';
                    $('#selected_members ul').append(_li);
                    $('#member_' + _mid).click(function(){ldelim}
                        $(this).remove();
                        if ( $('#selected_members ul li').length == 0 ) {ldelim}
                            $('#selected_members ul').append(_none);
                        {rdelim}
                    {rdelim});
                {rdelim}
                return false;
            {rdelim});

            $('#members_list .pages a').click(function(){ldelim}
                var _page = this.href.substring(this.href.indexOf('?')+6);
                var gid = $('#the_id').val();
                var _members = new Array();
                $('li[id^="member_"]').each(function(){ldelim}
                    _members[_members.length] = this.id.substring(7, this.id.length);
                {rdelim});

                $.ajax({ldelim}
                    url: 'ajax_members.php',
                    type: "POST",
                    data: {ldelim}ajax: true, from: 'groups', gid: gid, members: _members, page: _page, mode: _mode{rdelim},
                    {include file="js_loader.tpl"},
                    success: function(res){ldelim}
                        $('#members_list').empty();
                        _members_ajax_mapper(res, gid, _mode);
                    {rdelim},
                    error: function() {ldelim}
                        alert("{_T string="An error occured displaying members interface :(" escape="js"}");
                    {rdelim}
                });
                return false;
            {rdelim});
        {rdelim}
    {rdelim});
</script>
