{extends file="page.tpl"}

{block name="content"}
        <div class="ui segment">
            <form action="{path_for name="changeText"}" method="post" enctype="multipart/form-data" class="ui form">
                <div class="ui tiny header">
                    {_T string="Choose an entry"}
                </div>
                <div class="fields">
                    <div class="inline field">
                        <label for="sel_lang">{_T string="Language:"}</label>
                        <select name="sel_lang" id="sel_lang" class="ui dropdown nochosen lang">
                            {foreach item=langue from=$langlist}
                                <option value="{$langue->getID()}" {if $cur_lang eq $langue->getID()}selected="selected"{/if}>{$langue->getName()}</option>
                            {/foreach}
                        </select>
                    </div>
                    <div class="inline field">
                        <label for="sel_ref">{_T string="Reference:"}</label>
                        <select name="sel_ref" id="sel_ref" class="ui dropdown nochosen">
                            {foreach item=ref from=$reflist}
                                <option value="{$ref.tref}" {if $cur_ref eq $ref.tref}selected="selected"{/if} >{$ref.tcomment}</option>
                            {/foreach}
                        </select>
                        <noscript> <span><input type="submit" value="{_T string="Change"}" /></span></noscript>
                    </div>
                    {include file="forms_types/csrf.tpl"}
                </div>
            </form>
        </div>

        <div class="ui segment">
            <form action="{path_for name="texts"}" method="post" enctype="multipart/form-data" class="ui form">
                <div class="ui tiny header">
                    {$mtxt->tcomment}
                    <a id="btnlegend" class="tooltip action" title="{_T string="Show existing variables"}"><i class="circular inverted primary small icon info tooltip"></i><span class="sr-only">{_T string="Show existing variables" escape="js"}</span></a>
                </div>
                <div class="active content field">
                    <div class="field inline">
                        <label for="tsubject">{_T string="Email Subject"}</label>
                        <input type="text" name="text_subject" id="tsubject" value="{$mtxt->tsubject}" maxlength="255" size="32"/> <span class="exemple">{_T string="(Max 255 characters)"}</span>
                    </div>
                    <div class="field">
                        <label id="body_label" for="text_body">{_T string="Email Body:"}</label>
                        <textarea name="text_body" id="text_body" cols="64" rows="15">{$mtxt->tbody}</textarea>
                    </div>
                </div>
                <div class="button-container">
                    <input type="hidden" name="cur_lang"  value="{$cur_lang}"/>
                    <input type="hidden" name="cur_ref" value="{$cur_ref}"/>
                    <input type="hidden" name="valid" id="valid" value="1"/>
                    <button type="submit" class="ui labeled icon button action">
                        <i class="save icon"></i> {_T string="Save"}
                    </button>
                    {include file="forms_types/csrf.tpl"}
                </div>
            </form>
        </div>
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
