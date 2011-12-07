		<table id="listing">
			<thead>
				<tr>
					<th class="listing small_head">#</th>
					<th class="listing left username_row">
                        {_T string="Name"}
					</th>
					<th class="listing small_head">{_T string="Members"}</th>
                    <th class="listing left username_row">
                        {_T string="Owner"}
                    </th>
					<th class="listing left date_row">
                        {_T string="Creation date"}
					</th>
                    <th class="listing"></th>
				</tr>
			</thead>
			<tbody>
{foreach from=$groups item=group name=eachgroup}
    {assign var="owner" value=$group->getOwner()}
				<tr class="tbl_line_{if $smarty.foreach.eachgroup.iteration % 2 eq 0}even{else}odd{/if}">
					<td class="center">{$smarty.foreach.eachgroup.iteration}</td>
					<td>{$group->getName()}</td>
                    <td class="right membercount" id="count_{$group->getId()}" title="{_T string="View/manage %groupname members" pattern="/%groupname/" replace=$group->getName()}">{$group->getMemberCount()}</td>
					<td><a href="voir_adherent.php?id_adh={$owner->id}">{$owner->sname}</a></td>
					<td class="nowrap">{$group->getCreationDate()|date_format:"%a %d/%m/%Y - %R"}</td>
					<td class="center nowrap actions_row">
						<a href="ajouter_groupe.php?id_group={$group->getId()}">
                            <img
                                src="{$template_subdir}images/icon-edit.png"
                                alt="{_T string="[mod]"}"
                                width="16"
                                height="16"
                                title="{_T string="%groupname: edit informations" pattern="/%groupname/" replace=$group->getName()}"
                                />
                        </a>
						<a
                            onclick="return confirm('{_T string="Do you really want to delete this group from the base?"|escape:"javascript"}')"
                            href="?sup={$group->getId()}">
                            <img src="{$template_subdir}images/icon-trash.png" alt="{_T string="[del]"}" width="16" height="16" title="{_T string="Delete group '%groupname'" pattern="/groupname/" replace=$group->getName()}"/>
                        </a>
					</td>
				</tr>
{foreachelse}
				<tr><td colspan="6" class="emptylist">{_T string="No groups has been stored in the database yet."}</td></tr>
{/foreach}
			</tbody>
		</table>
        <div class="center">
            <a class="button" id="btnadd" href="ajouter_groupe.php">{_T string="Add new group"}</a>
        </div>
<script type="text/javascript">
    $(function() {ldelim}
        {* Members popup *}
        $('.membercount').click(function(){ldelim}
            var gid = this.id.substring(6);
            $.ajax({ldelim}
                url: 'ajax_members.php',
                type: "POST",
                data: {ldelim}ajax: true, from: 'groups', gid: gid{rdelim},
                {include file="js_loader.tpl"},
                success: function(res){ldelim}
                    _members_dialog(res, gid);
                {rdelim},
                error: function() {ldelim}
                    alert("{_T string="An error occured displaying members interface :(" escape="js"}");
                {rdelim}
            });
            return false;
        {rdelim});

        var _members_dialog = function(res, gid){ldelim}
            var _el = $('<div id="members_list" title="{_T string="Members selection" escape="js"}"> </div>');
            _el.appendTo('body').dialog({ldelim}
                modal: true,
                hide: 'fold',
                width: '80%',
                height: 500,
                close: function(event, ui){ldelim}
                    _el.remove();
                {rdelim}
            {rdelim});
            _members_ajax_mapper(res, gid);
        {rdelim}

        var _members_ajax_mapper = function(res, gid){ldelim}
            $('#members_list').append(res);
            $('#btnvalid').button().click(function(){ldelim}
                //first, let's store new recipients in mailing object
                var _members = new Array();
                $('li[id^="member_"]').each(function(){ldelim}
                    _members[_members.length] = this.id.substring(7, this.id.length);
                {rdelim});
                $.ajax({ldelim}
                    url: 'ajax_group_members.php',
                    type: "POST",
                    data: {ldelim}members: _members, gid: gid{rdelim},
                    {include file="js_loader.tpl"},
                    success: function(res){ldelim}
                        $('#count_' + gid).text(res);
                        $('#members_list').dialog("close");
                    {rdelim},
                    error: function() {ldelim}
                        alert("{_T string="An error occured displaying members interface :(" escape="js"}");
                    {rdelim}
                });
            {rdelim});
            //Remap links
            var _none = $('#none_selected').clone();
            $('li[id^="member_"]').click(function(){ldelim}
                $(this).remove();
                if ( $('#selected_members ul li').length == 0 ) {ldelim}
                    $('#selected_members ul').append(_none);
                {rdelim}
            {rdelim});
            $('#members_list #listing a').click(function(){ldelim}
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

        {rdelim}
    {rdelim});
</script>