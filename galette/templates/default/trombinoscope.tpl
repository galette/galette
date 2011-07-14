        <div id="main_logo">
            <img src="{$galette_base_path}picture.php?logo=true" width="{$logo->getOptimalWidth()}" height="{$logo->getOptimalHeight()}" alt="[ Galette ]" />
        </div>
		<h1 id="titre">{_T string="Trombinoscope"}</h1>
		<ul class="menu m_subscribe">
			<li id="backhome"><a href="../index.php">{_T string="Back to login page"}</a></li>
			<li id="loginpage"><a href="../login.php">{_T string="Login"}</a></li>
			<li id="memberslist"><a href="liste_membres.php">{_T string="Members list"}</a></li>
		</ul>
{foreach from=$members item=member}
		<div class="trombino">
			<img src="{$galette_base_path}picture.php?id_adh={$member->id}&amp;rand={$time}" height="{$member->picture->getOptimalHeight()}" width="{$member->picture->getOptimalWidth()}" alt="{$member->sfullname}{if $member->nickname ne ''} ({$member->nickname|htmlspecialchars}){/if}"/>
			<br/>{$member->sfullname}{if $member->nickname ne ''} ({$member->nickname|htmlspecialchars}){/if}
		</div>
{foreachelse}
		<div id="infobox">{_T string="No member to show"}</div>
{/foreach}