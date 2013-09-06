{if !isset($mode) or !$mode eq 'ajax'}
        <h1 id="titre">{_T string="Mailing preview"}</h1>
{/if}
        <section id="mailing_header" class="ajax_mailing_preview">
            <header class="ui-state-active ui-corner-top">{_T string="Headers"}</header>
            <dl>
                <dt>{_T string="From:"}</dt>
                <dd>{$mailing_sender}</dd>
                <dt>{_T string="To:"}</dt>
                <dd>
{foreach from=$recipients item=recipient}
                    <a href="mailto:{$recipient->email}">{$recipient->sname} &lt;{$recipient->email}&gt;</a>, 
{/foreach}
                </dd>
                <dt>{_T string="Subject:"}</dt>
                <dd>{$mailing->subject}</dd>
                <dt>{_T string="Attachments:"}</dt>
                <dd>
{if isset($attachments) }
    {foreach from=$attachments item=attachment}
                    <span class="attached">{$attachment}</span>
    {/foreach}
{/if}
{if isset($attachments_files) }
    {foreach from=$attachments_files item=attachment}
                    <span class="attached">{$attachment->getFileName()}</span>
    {/foreach}
{/if}
                </dd>
            </dl>
        </section>
        <section id="mailing_preview" class="ajax_mailing_preview">
            <header class="ui-state-active ui-corner-top">{_T string="Mail body"}</header>
            <div>
            {if $mailing->html}
                    {$mailing->message}
            {else}
                        <pre>{$mailing->message|escape}</pre>
            {/if}
            </div>
        </section>