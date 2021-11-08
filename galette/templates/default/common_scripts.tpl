        <script type="text/javascript">
            function csrfSafeMethod(method) {
                // these HTTP methods do not require CSRF protection
                return (/^(GET|HEAD|OPTIONS|TRACE)$/.test(method));
            }

            $(function(){
                $.ajaxPrefilter(function(options, originalOptions, jqXHR){
                    if (options.type.toLowerCase() === "post") {
                        // initialize `data` to empty string if it does not exist
                        options.data = options.data || "";

                        // add leading ampersand if `data` is non-empty
                        options.data += options.data?"&":"";

                        // add csrf
                        options.data += encodeURIComponent("{$csrf_name_key}") + "=" + encodeURIComponent("{$csrf_name}") + "&" + encodeURIComponent("{$csrf_value_key}") + "=" + encodeURIComponent("{$csrf_value}")
                    }
                });

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
        <script src="{base_url}/assets/js/lang/summernote-{$i18n->getID()|replace:'_':'-'}.min.js"></script>
        <script language="javascript">
            function activateMailingEditor(id) {
                if(!$('#mailing_html').attr('checked')){
                    $('#mailing_html').attr('checked', true);
                }

                $('input#html_editor_active').attr('value', '1');
                $('#activate_editor').remove();
                $('#summernote_toggler').html('<a href="javascript:deactivateMailingEditor(\'mailing_corps\');" id="deactivate_editor">{_T string="Deactivate HTML editor"}</a>');

                $('#mailing_corps').summernote({
                    lang: '{$i18n->getID()|replace:'_':'-'}',
                    height: 240,
                    toolbar: [
                        ['style', ['style']],
                        ['font', ['bold', 'italic', 'strikethrough', 'clear']],
                        ['para', ['ul', 'ol', 'paragraph']],
                        ['insert', ['link', 'picture']],
                        ['view', ['codeview', 'help']]
                    ],
                    styleTags: [
                        'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'
                    ]
                });
                $('#mailing_corps').summernote('focus');
            }
            function deactivateMailingEditor(id) {
                $('#mailing_corps').summernote('destroy');
                $('#deactivate_editor').remove();
                $('#summernote_toggler').html('<a href="javascript:activateMailingEditor(\'mailing_corps\');" id="activate_editor">{_T string="Activate HTML editor"}</a>');
            }
        {if $html_editor_active eq 1}
            $(function(){
                $('#activate_editor').remove();
                $('#summernote_toggler').html('<a href="javascript:deactivateMailingEditor(\'mailing_corps\');" id="deactivate_editor">{_T string="Deactivate HTML editor"}</a>');

                $('#mailing_corps').summernote({
                    height: 240,
                    toolbar: [
                        ['style', ['style']],
                        ['font', ['bold', 'italic', 'strikethrough', 'clear']],
                        ['para', ['ul', 'ol', 'paragraph']],
                        ['insert', ['link', 'picture']],
                        ['view', ['codeview', 'help']]
                    ],
                    styleTags: [
                        'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'
                    ]
                });
                $('#mailing_corps').summernote('focus');
            });
        {/if}
        </script>
    {/if}
    {assign var="localjstracking" value="`$_CURRENT_THEME_PATH`tracking.js"}
    {if file_exists($localjstracking)}
        <script type="text/javascript" src="{base_url}/{$template_subdir}/tracking.js"></script>
    {/if}
