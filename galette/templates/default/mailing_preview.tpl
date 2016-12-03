{if $mode eq 'ajax'}
    {assign var="extend" value='ajax.tpl'}
{else}
    {assign var="extend" value='page.tpl'}
{/if}
{extends file=$extend}
{block name="content"}
        <section id="mailing_header" class="ajax_mailing_preview">
            <header class="ui-state-active ui-corner-top">{_T string="Headers"}</header>
            <dl>
                <dt>{_T string="From:"}</dt>
                <dd>{$sender}</dd>
                <dt>{_T string="To:"}</dt>
                <dd>
    {foreach from=$recipients item=recipient}
        {if $recipient->email eq null and $recipient->hasParent()}
            {assign var="email" value=$recipient->parent->email}
        {else }
            {assign var="email" value=$recipient->email}
        {/if}
                    <a href="mailto:{$email}">{$recipient->sname} &lt;{$email}&gt;</a>, 
    {/foreach}
                </dd>
                <dt>{_T string="Subject:"}</dt>
                <dd>{$mailing->subject}</dd>
                <dt>{_T string="Attachments:"}</dt>
                <dd>
    {foreach from=$attachments item=attachment}
                    <span class="attached">{$attachment}</span>
    {foreachelse}
                    -
    {/foreach}
                </dd>
            </dl>
        </section>
        <section id="mailing_preview" class="ajax_mailing_preview">
            <header class="ui-state-active ui-corner-top">{_T string="Mail body"}</header>
            <div>
    {if $mailing->html}
                {$mailing->message}
    {else}
                <pre>{$mailing->wrapped_message|escape}</pre>
    {/if}
            </div>
        </section>
{/block}
