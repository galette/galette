{extends file="page.tpl"}

{block name="content"}
{if isset($trans) && $trans|@count > 0}
    {if $exists}
        <div class="ui top attached header">
            {_T string="Choose label to translate"}
        </div>
        <div class="ui bottom attached segment">
            <form action="{path_for name="dynamicTranslations"}" method="get" enctype="multipart/form-data" id="select_orig" class="ui form">
                <div class="field inline">
                    <select name="text_orig" id="text_orig" class="ui dropdown nochosen">
                        {html_options values=$orig output=$orig selected=$text_orig}
                    </select>
                    <noscript> <span><input type="submit" value="{_T string="Change"}" class="ui button" /></span></noscript>
                    {include file="forms_types/csrf.tpl"}
                </div>
            </form>
        </div>
    {/if}

        <form action="{path_for name="editDynamicTranslation"}" method="post" enctype="multipart/form-data" class="ui equal width form">
    {if not $exists}
            <div class="field">
                <label>{_T string="Original text: '%s'" pattern='/%s/' replace=$text_orig|escape}</label>
                <input type="hidden" name="new" value="true"/>
            </div>
    {/if}
            <div class="ui top attached header">
                {_T string="Translation of '%s' label" pattern="/%s/" replace=$text_orig|escape}
            </div>
            <div class="ui bottom attached segment">
                <div class="active content field">
                    <table class="ui striped table">
{section name="lang" loop=$trans}
                        <tr>
                            <td class="three wide"><label for="text_trans_{$trans[lang].key}">{$trans[lang].name}</label></td>
                            <td class="thirteen wide">
                                <input type="text" name="text_trans_{$trans[lang].key}" id="text_trans_{$trans[lang].key}" value="{if $trans[lang].text}{$trans[lang].text|escape}{/if}"/>
                                <input type=hidden name="text_orig" value="{$text_orig|escape}"/>
                            </td>
                        </tr>
{/section}
                    </table>
                </div>
            </div>
            <div class="ui basic center aligned segment">
                <button type="submit" name="trans" class="ui labeled icon primary button action">
                    <i class="save icon"></i> {_T string="Save"}
                </button>
                {include file="forms_types/csrf.tpl"}
            </div>
        </form>
{else}
        <p>{_T string="No fields to translate."}</p>
{/if}
{/block}

{block name="javascripts"}
{if isset($trans) && $trans|@count > 0}
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
{/if}
{/block}
