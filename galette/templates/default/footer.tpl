        <footer class="ui basic segment">
            <div class="row">
                <nav class="ui horizontal bulleted link list">
                    <a href="https://galette.eu" class="item">
                        <i class="icon globe europe"></i>
                        {_T string="Website"}
                    </a>
                    <a href="https://doc.galette.eu" class="item">
                        <i class="icon book"></i>
                       {_T string="Documentation"}
                    </a>
                    <a href="https://twitter.com/galette_soft" class="item">
                        <i class="icon twitter"></i>
                        @galette_soft
                    </a>
                    <a href="https://framapiaf.org/@galette" class="item">
                        <i class="icon mastodon"></i>
                        @galette
                    </a>
                </nav>
            </div>
            <div class="row">
                <nav class="ui horizontal bulleted link list">
                    <a id="copyright" href="https://galette.eu/" class="item">
                        <i class="icon cookie bite"></i>
                        Galette {$smarty.const.GALETTE_DISPLAY_VERSION}
                    </a>
{if $login->isLogged() &&  ($login->isAdmin() or $login->isStaff())}
                    <a id="sysinfos" href="{path_for name="sysinfos"}" class="item">
                        <i class="icon cogs"></i>
                        {_T string="System information"}
                    </a>
{/if}
                </nav>
            </div>
{* Display footer line, if it does exists *}
{if $preferences->pref_footer neq ''}
            <div class="row">
                <div class="ui padded grid">
                    <div class="ui sixteen wide column">
                        {$preferences->pref_footer}
                    </div>
                </div>
            </div>
{/if}
        </footer>
