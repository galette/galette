{if $login->isSuperAdmin()}
    <div class="ui small red inverted center aligned segment" title="{_T string="You are actually logged-in as superadmin. Some functionnalities may not be available since this is *not* a regular member."}">
        <i class="ui user shield icon"></i>
        <strong>{_T string="Superadmin"}</strong>
    </div>
{/if}
{if $GALETTE_MODE eq 'DEMO'}
        <div class="ui small orange inverted center aligned segment" title="{_T string="This application runs under DEMO mode, all features may not be available."}">
            <strong>{_T string="Demonstration"}</strong>
        </div>
{/if}
