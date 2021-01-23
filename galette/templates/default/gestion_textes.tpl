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
        </div>
        </form>
        <div id="legende{$cur_ref}" class="texts_legend" title="{_T string="Existing variables"}">
            <h1>{_T string="Existing variables"}</h1>
            <table>
                {foreach from=$texts->getLegend() item=legend}
                    <tr>
                        <th colspan="4">
                            {$legend.title}
                        </th>
                    </tr>
                    {foreach from=$legend.patterns item=pattern name=patternloop}
                        {if $smarty.foreach.patternloop.index % 2 == 0}
                            <tr>
                        {/if}
                        <th><tt>{$pattern.pattern|trim:'/'}</tt></th>
                        <td class="back">
                            {if isset($pattern.title)}{$pattern.title}{/if}
                        </td>
                        {if $smarty.foreach.patternloop.index % 2 != 0}
                            </tr>
                        {/if}
                    {/foreach}
                {/foreach}
            </table>
        </div>
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


                $('fieldset').prepend('<a id="btnlegend" class="tab-button tooltip action" title="{_T string="Show existing variables"}"><i class="fas fa-info-circle fa-2x"></i> <span class="sr-only">{_T string="Show existing variables" escape="js"}</span></a>');
                $('#legende{$cur_ref} h1').remove();
                $('#legende{$cur_ref}').dialog({
                    autoOpen: false,
                    modal: true,
                    hide: 'fold',
                    width: '40%',
                    create: function (event, ui) {
                        if ($(window ).width() < 767) {
                            $(this).dialog('option', {
                                    'width': '95%',
                                    'draggable': false
                            });
                        }
                    }
                }).dialog('close');

                $('#btnlegend').click(function(){
                    $('#legende{$cur_ref}').dialog('open');
                        return false;
                });
            });
        </script>
{/block}
