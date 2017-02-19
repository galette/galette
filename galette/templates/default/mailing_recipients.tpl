    {assign var='count' value=$mailing->recipients|@count}
    {assign var='count_unreachables' value=$mailing->unreachables|@count}
    {if $count > 0}
        {if $mailing->current_step eq constant('Galette\Core\Mailing::STEP_SENT')}
                <p>{_T string="Your message has been sent to <strong>%s members</strong>" pattern="/%s/" replace=$count}</p>
        {else}
                <p id="recipients_count">{_T string="You are about to send an e-mail to <strong>%s members</strong>" pattern="/%s/" replace=$count}</p>
        {/if}
    {else}
        {if $count_unreachables > 0}
                <p id="recipients_count"><strong>{_T string="None of the selected members has an email address."}</strong></p>
         {else}
                <p id="recipients_count"><strong>{_T string="No member selected (yet)."}</strong></p>
         {/if}
    {/if}
    {if $count_unreachables > 0}
                <p id="unreachables_count">
                    <strong>{$count_unreachables} {if $count_unreachables != 1}{_T string="unreachable members:"}{else}{_T string="unreachable member:"}{/if}</strong><br/>
                    {_T string="Some members you have selected have no e-mail address. However, you can generate envelope labels to contact them by snail mail."}
                    <br/><a id="btnlabels" class="button" href="{path_for name="pdf-members-labels"}?from=mailing">{_T string="Generate labels"}</a>
                </p>
    {/if}
