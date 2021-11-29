{extends file="page.tpl"}

{block name="content"}
        <div id="listfilter">
        <form action="{path_for name="changeText"}" method="post" enctype="multipart/form-data">
                <strong>{_T string="Choose an entry"}</strong><br/>
                <label for="sel_lang">{_T string="Language:"}</label>
                <select name="sel_lang" id="sel_lang" class="lang">
                    {foreach item=langue from=$langlist}
                        <option value="{$langue->getID()}" {if $cur_lang eq $langue->getID()}selected="selected"{/if}>{$langue->getName()}</option>
                    {/foreach}
                </select>

                <label for="sel_ref">{_T string="Reference:"}</label>
                <select name="sel_ref" id="sel_ref">
                    {foreach item=ref from=$reflist}
                        <option value="{$ref.tref}" {if $cur_ref eq $ref.tref}selected="selected"{/if} >{$ref.tcomment}</option>
                    {/foreach}
                </select>
                <noscript> <span><input type="submit" value="{_T string="Change"}" /></span></noscript>
            {include file="forms_types/csrf.tpl"}
        </form>
        </div>

        <form action="{path_for name="texts"}" method="post" enctype="multipart/form-data">
        <div class="bigtable">
            <fieldset class="cssform" id="{$mtxt->tlang}">
                <legend class="ui-state-active ui-corner-top">{$mtxt->tcomment}</legend>
                <p>
                    <label for="tsubject" class="bline">{_T string="Email Subject"}</label> 
                    <input type="text" name="text_subject" id="tsubject" value="{$mtxt->tsubject}" maxlength="255" size="32"/> <span class="exemple">{_T string="(Max 255 characters)"}</span>
                </p>
                <p>
                    <label id="body_label" for="text_body" class="bline vtop">{_T string="Email Body:"}</label>
                    <textarea name="text_body" id="text_body" cols="64" rows="15">{$mtxt->tbody}</textarea>
                </p>
            </fieldset>
        </div>
        <div class="button-container">
            <input type="hidden" name="cur_lang"  value="{$cur_lang}"/>
            <input type="hidden" name="cur_ref" value="{$cur_ref}"/>
            <input type="hidden" name="valid" id="valid" value="1"/>
            <button type="submit" class="action">
                <i class="fas fa-save fa-fw"></i> {_T string="Save"}
            </button>
            {include file="forms_types/csrf.tpl"}
        </div>
        </form>
        {include file="replacements_legend.tpl" legends=$texts->getLegend() cur_ref=$cur_ref}
{/block}

{block name="javascripts"}
        <script type="text/javascript">
            $(function() {
                $('#sel_ref, #sel_lang').change(function() {
                    $(':input[type="submit"]').attr('disabled', 'disabled');
                    //Change the input[@id='value'] ; we do not want to validate, but to change lang/ref
                    $('#valid').attr('value', (this.id === 'sel_lang') ? 'change_lang' : 'change_text');
                    this.form.submit();
                });

                _addLegenButton('fieldset > p:nth-child(2)');
                _handleLegend();
            });
        </script>
{/block}
