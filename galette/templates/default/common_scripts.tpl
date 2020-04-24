        <script type="text/javascript">
            $(function(){
                $.datepicker.setDefaults($.datepicker.regional['{$galette_lang}']);
    {if $galette_lang eq 'en'}
                $.datepicker.setDefaults({
                    dateFormat: 'yy-mm-dd'
                });
    {/if}

    {if $autocomplete}
                $('#ville_adh, #lieu_naissance').autocomplete({
                    source: function (request, response) {
                        $.post('{path_for name="suggestTown"}', request, response);
                    },
                    minLength: 2
                });
                $('#pays_adh').autocomplete({
                    source: function (request, response) {
                        $.post('{path_for name="suggestCountry"}', request, response);
                    },
                    minLength: 2
                });
    {/if}

    {if isset($renew_telemetry)}
        {include file="telemetry.tpl" part="jsdialog"}
    {/if}
            });
        </script>
    {if $color_picker}
        <script type="text/javascript" src="{base_url}/assets/js/galette-farbtastic.bundle.min.js"></script>
    {/if}
    {if $require_charts}
        <script type="text/javascript" src="{base_url}/assets/js/galette-jqplot.bundle.min.js"></script>
    {/if}
    {if $require_tree}
        <script type="text/javascript" src="{base_url}/assets/js/galette-jstree.bundle.min.js"></script>
    {/if}
    {if $require_mass}
        <script type="text/javascript" src="{base_url}/{$scripts_dir}mass_changes.js"></script>
    {/if}
    {if $html_editor}
        <script type="text/javascript" src="{base_url}/{$jquery_dir}markitup-{$jquery_markitup_version}/jquery.markitup.js"></script>
        <script type="text/javascript" src="{base_url}/{$jquery_dir}markitup-{$jquery_markitup_version}/sets/html/set-{$galette_lang}.js"></script>
        <script language="javascript">
            function toggleMailingEditor(id) {
                if(!$('#mailing_html').attr('checked')){
                    $('#mailing_html').attr('checked', true);
                }

                $('input#html_editor_active').attr('value', '1');
                {* While it is not possible to deactivate markItUp, we remove completly the functionnality *}
                $('#toggle_editor').remove();
                $('#mailing_corps').markItUp(galetteSettings);
            }
        {if $html_editor_active eq 1}
            $(function(){
                {* While it is not possible to deactivate markItUp, we remove completly the functionnality *}
                $('#toggle_editor').remove();
                $('#mailing_corps').markItUp(galetteSettings);
            });
        {/if}
        </script>
    {/if}
    {assign var="localjstracking" value="`$_CURRENT_THEME_PATH`tracking.js"}
    {if file_exists($localjstracking)}
        <script type="text/javascript" src="{base_url}/{$template_subdir}/tracking.js"></script>
    {/if}
