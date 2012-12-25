{if !$mode eq 'ajax'}
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