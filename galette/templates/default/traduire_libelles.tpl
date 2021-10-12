{extends file="page.tpl"}

{block name="content"}
{if isset($trans) && $trans|@count > 0}
            <div clasis="bigtable">
    {if $exists}
                <form action="{path_for name="dynamicTranslations"}" method="get" enctype="multipart/form-data" id="select_orig">
                    <p class="right">
                        <label for="text_orig">{_T string="Choose label to translate"}</label>
                        <select name="text_orig" id="text_orig">
                            {html_options values=$orig output=$orig selected=$text_orig}
                        </select>
                        <noscript> <span><input type="submit" value="{_T string="Change"}" /></span></noscript>
                    </p>
                </form>
    {/if}

                <form action="{path_for name="editDynamicTranslation"}" method="post" enctype="multipart/form-data">
    {if not $exists}
                <p class="right">
                    <span>{_T string="Original text: '%s'" pattern='/%s/' replace=$text_orig|escape}</span>
                    <input type="hidden" name="new" value="true"/>
                </p>
    {/if}
                <fieldset class="cssform">
                    <legend class="ui-state-active ui-corner-top">{_T string="Translation of '%s' label" pattern="/%s/" replace=$text_orig|escape}</legend>
{section name="lang" loop=$trans}
                    <p>
                        <label for="text_trans_{$trans[lang].key}" class="bline">{$trans[lang].name}</label>
                        <input type="text" name="text_trans_{$trans[lang].key}" id="text_trans_{$trans[lang].key}" value="{if $trans[lang].text}{$trans[lang].text|escape}{/if}"/>
                        <input type=hidden name="text_orig" value="{$text_orig|escape}"/>
                    </p>
{/section}
                </fieldset>
            </div>
            <div class="button-container">
                <button type="submit" name="trans" class="action">
                    <i class="fas fa-save fa-fw"></i> {_T string="Save"}
                </button>
            </div>
        </form>
{else}
        <p>{_T string="No fields to translate."}</p>
{/if}
{/block}

{block name="javascripts"}
    {if $exists}
    <script type="text/javascript">
        $('#text_orig').change(function(e) {
            e.preventDefault();
            var _selected  = $('#text_orig option:selected').val();
            var _form = $('#select_orig');
            _form.attr('action', _form.attr('action') + '/' + _selected)
            $('#select_orig').submit();
        });
    </script>
    {/if}
{/block}
