	<h1 id="titre">{_T string="Member Profile"}</h1>
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
	<div class="bigtable">
		<ul id="details_menu">
{if ($pref_card_self eq 1) or ($smarty.session.admin_status eq 1)}
			<li>
				<a href="carte_adherent.php?id_adh={$member->id}" id="btn_membercard">{_T string="Generate Member Card"}</a>
			</li>
{/if}
			<li>
				<a href="ajouter_adherent.php?id_adh={$member->id}" id="btn_edit">{_T string="Modification"}</a>
			</li>
			<li>
				<a href="gestion_contributions.php?id_adh={$member->id}" id="btn_contrib">{_T string="View contributions"}</a>
			</li>
{if $smarty.session.admin_status eq 1}
			<li>
				<a href="ajouter_contribution.php?id_adh={$member->id}" id="btn_addcontrib">{_T string="Add a contribution"}</a>
			</li>
{/if}
		</ul>

		<table class="details">
			<caption>{_T string="Identity:"}</caption>
			<tr>
				<th>{_T string="Name:"}</th>
				<td>{$member->spoliteness} {$member->name} {$member->surname}</td>
				<td rowspan="5" class="photo"><img src="picture.php?id_adh={$member->id}&amp;rand={$time}" class="picture" width="{$member->picture->getOptimalWidth()}" height="{$member->picture->getOptimalHeight()}" alt="{_T string="Picture"}"/></td>
			</tr>
			<tr>
				<th>{_T string="Nickname:"}</th>
				<td>{$member->nickname}</td>
			</tr> 
			<tr> 
				<th>{_T string="birth date:"}</th>
				<td>{$member->birthdate}</td>
			</tr>
			<tr>
				<th>{_T string="Profession:"}</th>
				<td>{$member->job}</td>
			</tr>
			<tr>
				<th>{_T string="Language:"}</th>
				<td><img src="{$pref_lang_img}" alt=""/> {$pref_lang}</td>
			</tr>
		</table>

		<table class="details">
			<caption>{_T string="Galette-related data:"}</caption>
			<tr>
				<th>{_T string="Status:"}</th>
				<td>{$member->sstatus}</td>
			</tr>
			<tr>
				<th>{_T string="Be visible in the<br /> members list :"}</th>
				<td>{$member->sappears_in_list}</td>
			</tr>
{if $smarty.session.admin_status eq 1}
			<tr>
				<th>{_T string="Account:"}</th>
				<td>{$member->sactive}</td>
			</tr>
			<tr>
				<th>{_T string="Galette Admin:"}</th>
				<td>{$member->sadmin}</td>
			</tr>
			<tr>
				<th>{_T string="Freed of dues:"}</th>
				<td>{$member->sdue_free}</td>
			</tr>
{/if}
			<tr>
				<th>{_T string="Username:"}</th>
				<td>{$member->login}</td>
			</tr>
{if $smarty.session.admin_status eq 1}
			<tr>
				<th>{_T string="Creation date:"}</th>
				<td>{$member->creation_date}</td>
			</tr>
			<tr>
				<th>{_T string="Other informations (admin):"}</th>
				<td>{$member->others_infos_admin|nl2br}</td>
			</tr>
{/if}
			<tr>
				<th>{_T string="Other informations:"}</th>
				<td>{$member->others_infos|nl2br}</td>
			</tr>
		</table>

		<table class="details">
			<caption>{_T string="Contact information:"}</caption>
			<tr>
				<th>{_T string="Address:"}</th> 
				<td>
					{$member->adress}
{if $member->adresse2_adh ne ''}
					<br/>{$member->adress2}
{/if}
				</td>
			</tr>
			<tr>
				<th>{_T string="Zip Code:"}</th>
				<td>{$member->zipcode}</td>
			</tr>
			<tr>
				<th>{_T string="City:"}</th>
				<td>{$member->town}</td>
			</tr>
			<tr>
				<th>{_T string="Country:"}</th>
				<td>{$member->country}</td>
			</tr>
			<tr>
				<th>{_T string="Phone:"}</th>
				<td>{$member->phone}</td>
			</tr>
			<tr>
				<th>{_T string="Mobile phone:"}</th>
				<td>{$member->gsm}</td>
			</tr>
			<tr>
				<th>{_T string="E-Mail:"}</th>
				<td>
{if $member->email ne ''}					
					<a href="mailto:{$member->email}">{$member->email}</a>
{/if}
				</td>
			</tr>
			<tr>
				<th>{_T string="Website:"}</th>
				<td>
{if $member->website ne ''}
					<a href="{$member->website}">{$member->website}</a>
{/if}						
				</td>
			</tr>
			<tr>
				<th>{_T string="ICQ:"}</th>
				<td>{$member->icq}</td>
			</tr>
			<tr>
				<th>{_T string="Jabber:"}</th>
				<td>{$member->jabber}</td>
			</tr>
			<tr>
				<th>{_T string="MSN:"}</th>
				<td>
{if $member->msn ne ''}
					<a href="mailto:{$member->msn}">{$member->msn}</a>
{/if}
				</td>
			</tr>
			<tr>
				<th>{_T string="Id GNUpg (GPG):"}</th>
				<td>{$member->gpgid}</td>
			</tr>
			<tr>
				<th>{_T string="fingerprint:"}</th>
				<td>{$member->fingerprint}</td>
			</tr>
		</table>

{include file="display_dynamic_fields.tpl" is_form=false}
	</div>
