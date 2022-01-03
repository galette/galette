{extends file="page.tpl"}

{block name="content"}
        <div class="ui basic horizontal segments">
            <div class="ui basic fitted segment">
                <a
                    href="{path_for name="importModel"}"
                    class="ui icon button"
                >
                    <i class="cogs icon" aria-hidden="true"></i>
                    {_T string="Configure import model"}
                </a>
            </div>
        </div>
            <div class="ui basic fitted segment">
                <div class="ui styled fluid accordion row">
                    <div class="active title">
                        <i class="icon dropdown"></i>
                        {_T string="Existing files"}
                    </div>
                    <div class="active content field">
                        <form class="ui form" action="{path_for name="doImport"}" method="post">
                            <div class="ui yellow message">
                                {_T string="Warning: Don't forget to backup your current database."}
                            </div>
{if $existing|@count gt 0}
                            <p>{_T string="The following files seems ready to import on the disk:"}</p>
                            <table class="listing ui celled table">
                                <thead>
                                    <tr>
                                        <th>{_T string="Name"}</th>
                                        <th>{_T string="Date"}</th>
                                        <th>{_T string="Size"}</th>
                                        <th class="actions_row">{_T string="Actions"}</th>
                                    </tr>
                                </thead>
                                <tbody>
    {foreach item=import from=$existing name=existing_list}
                                    <tr class="{if $smarty.foreach.existing_list.iteration % 2 eq 0}even{else}odd{/if}">
                                        <td data-scope="row">
                                            <input type="radio" name="import_file" id="file{$smarty.foreach.existing_list.iteration}" value="{$import.name}"{if isset($import_file) and $import_file eq $import.name} checked="checked"{/if}/>
                                            <label for="file{$smarty.foreach.existing_list.iteration}">{$import.name}</label> (<a href="{path_for name="getCsv" data=["type" => "import", "file" => $import.name]}">{_T string="see"}</a>)
                                        </td>
                                        <td data-title="{_T string="Date"}">
                                            {$import.date}
                                        </td>
                                        <td data-title="{_T string="Size"}">
                                            {$import.size}
                                        </td>
                                        <td class="actions_row">
                                            <a
                                                href="{path_for name="removeCsv" data=["type" => "import", "file" => $import.name]}"
                                                class="delete tooltip"
                                            >
                                                <i class="ui trash red icon"></i>
                                                <span class="sr-only">{_T string="Remove '%file' from disk" pattern="/%file/" replace=$import.name}</span>
                                            </a>
                                        </td>
                                    </tr>
    {/foreach}
                                </tbody>
                            </table>
                            <div class="button-container">
                                <label for="dryrun" title="{_T string="Run the import process, but do *not* store anything in the database"}">{_T string="Dry run"}</label>
                                <input type="checkbox" name="dryrun" id="dryrun" value="1"{if isset($dryrun) and $dryrun eq true} checked="checked"{/if}/>
                                <button type="submit" name="import" id="import" class="ui labeled icon button">
                                    <i class="ui file import blue icon"></i>
                                    {_T string="Import"}
                                </button>
                                {include file="forms_types/csrf.tpl"}
                            </div>
{else}
                            <p>{_T string="No import file actually exists."}<br/>{_T string="Use upload form below to send a new file on server, or copy it directly in the imports directory."}</p>
{/if}
                        </form>
                    </div>
                </div>
            </div>
            <div class="ui basic fitted segment">
                <div class="ui styled fluid accordion row">
                    <div class="active title">
                        <i class="icon dropdown"></i>
                        {_T string="Upload new file"}
                    </div>
                    <div class="active content field">
                        <form class="ui form" action="{path_for name="uploadImportFile"}" method="post" enctype="multipart/form-data">
                            <div class="field">
                                <label for="new_file">{_T string="Select a file:"}</label>
                                <input class="labelalign" type="file" name="new_file" accept="text/csv" id="new_file"/>
                            </div>
                            <div class="button-container">
                                <button type="submit" name="upload" id="upload" class="ui labeled icon button">
                                    <i class="upload blue icon" aria-hidd="true"></i>
                                    {_T string="Upload file"}
                                </button>
                                {include file="forms_types/csrf.tpl"}
                            </div>
                        </form>
                    </div>
                </div>
            </div>
{/block}

{block name="javascripts"}
        <script type="text/javascript">
            $(function() {
                _collapsibleFieldsets();
                //bind import click to check if one is selected
                $('#import').on('click', function(){
                    if ( $('input[name=import_file]:checked').length > 0 ) {
                        return true;
                    } else {
                        var _el = $('<div id="pleaseselect" title="{_T string="No file selected" escape="js"}">{_T string="Please make sure to select one file to import." escape="js"}</div>');
                        _el.appendTo('body').dialog({
                            modal: true,
                            buttons: {
                                Ok: function() {
                                    $(this).dialog( "close" );
                                }
                            },
                            close: function(event, ui){
                                _el.remove();
                            }
                        });
                        return false;
                    }
                });

                $('#upload').click(function(){
                    var _selected = $('#new_file')[0].files.length;
                    if ( _selected == 0 ) {
                         var _el = $('<div id="pleaseupload" title="{_T string="No file to upload" escape="js"}">{_T string="Please make sure to select one file to upload." escape="js"}</div>');
                        _el.appendTo('body').dialog({
                            modal: true,
                            buttons: {
                                Ok: function() {
                                    $(this).dialog( "close" );
                                }
                            },
                            close: function(event, ui){
                                _el.remove();
                            }
                        });
                        return false;
                    } else {
                        return true;
                    }
                });
            });
            {include file="js_removal.tpl"}
        </script>
{/block}
