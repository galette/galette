        <footer>
            <nav>
                <ul>
                    <li>
                        <a href="https://galette.eu"><i class="fas fa-globe-europe"></i> {_T string="Website"}</a>
                    </li>
                    <li>
                        <a href="https://galette.eu/documentation/"><i class="fas fa-book"></i> {_T string="Documentation"}</a>
                    </li>
                    <li>
                        <a href="https://twitter.com/galette_soft">
                            <i class="fab fa-twitter"></i> @galette_soft
                        </a>
                    </li>
                    <li>
                        <a href="https://framapiaf.org/@galette">
                            <i class="fab fa-mastodon"></i> @galette
                        </a>
                    </li>
                </ul>
            </nav>
            <a id="copyright" href="https://galette.eu/"><i class="fas fa-cookie-bite"></i> Galette {$GALETTE_VERSION}</a>
{if $login->isLogged() &&  ($login->isAdmin() or $login->isStaff())}
            <a id="sysinfos" href="{path_for name="sysinfos"}"><i class="fas fa-cogs"></i> {_T string="System informations"}</a>
{/if}

{* Display footer line, if it does exists *}
{if $preferences->pref_footer neq ''}
    {$preferences->pref_footer}
{/if}
        </footer>
