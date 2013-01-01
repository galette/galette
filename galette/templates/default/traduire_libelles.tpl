{if $trans|@count > 0}
        <form action="traduire_libelles.php" method="post" enctype="multipart/form-data">
            <div clasis="bigtable">
                <p class="right">
    {if $exists}
                    <label for="text_orig">{_T string="Choose label to translate"}</label>
                    <select name="text_orig" id="text_orig">
                        {html_options values=$orig output=$orig selected=$text_orig}
                    </select>
                    <noscript> <span><input type="submit" value="{_T string="Change"}" /></span></noscript>
    {else}
                    <span>{_T string="Original text: '%s'" pattern='/%s/' replace=$text_orig}</span>
                    <input type=hidden name="text_orig" value="{$text_orig}"/>
                    <input type="hidden" name="new" value="true"/>
    {/if}
                </p>
                <fieldset class="cssform">
                    <legend class="ui-state-active ui-corner-top">{_T string="Translation of '%s' label" pattern="/%s/" replace=$text_orig}</legend>
{section name="lang" loop=$trans}
                    <p>
                        <label for="text_trans_{$trans[lang].key}" class="bline">{$trans[lang].name}</label>
                        <input type="text" name="text_trans_{$trans[lang].key}" id="text_trans_{$trans[lang].key}" value="{$trans[lang].text|escape}"/>
                    </p>
{/section}
                </fieldset>
            </div>
            <div class="button-container">
                <input type="submit" name="trans" value="{_T string="Save"}"/>
            </div>
        </form>
        <script type="text/javascript">
            $('#text_orig').change(function() {
                this.form.submit();
            });
        </script>
{else}
        <p>{_T string="No fields to translate."}</p>
{/if}
