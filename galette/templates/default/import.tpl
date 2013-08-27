        <p class="center">
            <a class="button" id="preferences" href="import_model.php">{_T string="Configure import model"}</a>
        </p>
        <form class="form" action="import.php" method="post" enctype="multipart/form-data">
            <fieldset>
                <legend class="ui-state-active ui-corner-top">{_T string="Existing files"}</legend>
                <div class="warningbox">
                    {_T string="Warning: Don't forget to backup your current database."}
                </div>
                <div>
{if $existing|@count gt 0}
                    <p>{_T string="The following files seems ready to import on the disk:"}</p>
                    <table class="listing">
                        <thead>
                            <tr>
                                <th>{_T string="Name"}</th>
                                <th>{_T string="Date"}</th>
                                <th>{_T string="Size"}</th>
                                <th class="actions_row"></th>
                            </tr>
                        </thead>
                        <tbody>
    {foreach item=import from=$existing name=existing_list}
                            <tr class="{if $smarty.foreach.existing_list.iteration % 2 eq 0}even{else}odd{/if}">
                                <td>
                                    <input type="radio" name="import_file" id="file{$smarty.foreach.existing_list.iteration}" value="{$import.name}"{if isset($import_file) and $import_file eq $import.name} checked="checked"{/if}/>
                                    <label for="file{$smarty.foreach.existing_list.iteration}">{$import.name}</label> (<a href="get_import.php?file={$import.name}">{_T string="see"}</a>)
                                </td>
                                <td>
                                    {$import.date}
                                </td>
                                <td>
                                    {$import.size}
                                </td>
                                <td class="actions_row">
                                    <a href="import.php?sup={$import.name}" title="{_T string="Remove '%file' from disk" pattern="/%file/" replace=$import.name}"><img src="{$template_subdir}images/delete.png" alt="{_T string="Delete"}"/></a>
                                </td>
                            </tr>
    {/foreach}
                        </tbody>
                    </table>
                    <div class="button-container">
                        <label for="dryrun" title="{_T string="Run the import process, but do *not* store anything in the database"}">{_T string="Dry run"}</label>
                        <input type="checkbox" name="dryrun" id="dryrun" value="1"{if isset($dryrun) and $dryrun eq true} checked="checked"{/if}/><br/>
                        <input type="submit" name="import" id="import" value="{_T string="Import"}"/>
                    </div>
{else}
                    <p>{_T string="No import file actually exists."}<br/>{_T string="Use upload form below to send a new file on server, or copy it directly in the imports directory."}</p>
{/if}
                </div>
            </fieldset>

            <fieldset>
                <legend class="ui-state-active ui-corner-top">{_T string="Upload new file"}</legend>
                <div>
                    <p>
                        <label for="new_file">{_T string="Select a file:"}</label>
                        <input class="labelalign" type="file" name="new_file" accept="text/csv" id="new_file"/>
                    </p>
                    <div class="button-container">
                        <input type="submit" name="upload" value="{_T string="Upload file"}" id="upload"/>
                    </div>
                </div>
            </fieldset>
        </form>

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
        </script>
