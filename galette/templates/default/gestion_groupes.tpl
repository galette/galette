		<table id="listing">
			<thead>
				<tr>
					<th class="listing small_head">#</th>
					<th class="listing left username_row">
                        {_T string="Name"}
					</th>
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
					<td><a href="voir_adherent.php?id_adh={$owner->id}">{$owner->sname}</a></td>
					<td class="nowrap">{$group->getCreationDate()|date_format:"%a %d/%m/%Y - %R"}</td>
					<td class="center nowrap actions_row">
						<a href="ajouter_groupe.php?id_adh={$group->getId()}">
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
                            <img src="{$template_subdir}images/icon-trash.png" alt="{_T string="[del]"}" width="16" height="16"/>
                        </a>
					</td>
				</tr>
{foreachelse}
				<tr><td colspan="5" class="emptylist">{_T string="No groups has been stored in the database yet."}</td></tr>
{/foreach}
			</tbody>
		</table>