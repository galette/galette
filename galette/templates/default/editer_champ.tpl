{if $mode eq 'ajax'}
    {assign var="extend" value='ajax.tpl'}
{else}
    {assign var="extend" value='page.tpl'}
{/if}
{extends file=$extend}

{block name="content"}
{if $action == "edit"}
    <form action="{path_for name="doEditDynamicField" data=["form_name" => $form_name, "id" => $df->getId()]}" method="post" class="ui form">
        <div class="ui segment">
            <div class="ui tiny header">
                {_T string="Edit field %field" pattern="/%field/" replace=$df->getName()}
            </div>
            <div class="active content field">
                <div class="inline field">
                    <label for="field_name">{_T string="Name:"}</label>
                    <input type="text" name="field_name" id="field_name" value="{$df->getName(false)}"{if not $df|is_a:'Galette\DynamicFields\Separator'} required="required"{/if}/>
                </div>
                <div class="inline field">
                    <label for="field_perm">{_T string="Permissions:"}</label>
                    <select name="field_perm" id="field_perm" class="ui dropdown nochosen">
                        {html_options options=$perm_names selected=$df->getPerm()}
                    </select>
                </div>
{if $df->hasData()}
                <div class="inline field">
                    <label for="field_required">{_T string="Required:"}</label>
                    <select name="field_required" id="field_required" class="ui dropdown nochosen">
                        <option value="0" {if !$df->isRequired()}selected="selected"{/if}>{_T string="No"}</option>
                        <option value="1" {if $df->isRequired()}selected="selected"{/if}>{_T string="Yes"}</option>
                    </select>
                </div>
{/if}
{if $df->hasWidth()}
                <div class="inline field">
                    <label for="field_width">{_T string="Width:"}</label>
                    <input type="text" name="field_width" id="field_width" value="{$df->getWidth()}" size="3"/>
                </div>
{/if}
{if $df->hasHeight()}
                <div class="inline field">
                    <label for="field_height">{_T string="Height:"}</label>
                    <input type="text" name="field_height" id="field_height" value="{$df->getHeight()}" size="3"/>
                </div>
{/if}
{if $df->hasSize()}
                <div class="inline field">
                    <label for="field_size">{_T string="Size:"}</label>
                    <input type="text" name="field_size" id="field_size" value="{$df->getSize()}" size="3"/>
    {if $df|is_a:'Galette\DynamicFields\File'}
                    <span class="exemple">{_T string="Maximum file size, in Ko."}</span>
    {else}
                    <span class="exemple">{_T string="Maximum number of characters."}</span>
    {/if}
                </div>
{/if}
{if $df->isMultiValued()}
                <div class="inline field">
                    <label for="field_repeat">{_T string="Repeat:"}</label>
                    <input type="text" name="field_repeat" id="field_repeat" value="{$df->getRepeat()}" size="3"/>
                    <span class="exemple">{_T string="Number of values or zero if infinite."}</span>
                </div>
{/if}
{if $df->hasFixedValues()}
                <div class="inline field">
                    <label for="fixed_values">{_T string="Values:"}</label>
                    <textarea name="fixed_values" id="fixed_values" cols="20" rows="6">{$df->getValues(true)}</textarea>
                    <br/><span class="exemple">{_T string="Choice list (one entry per line)."}</span>
                </div>
{/if}
                <div>
                    <label for="field_information">{_T string="Information:"}</label>
                    <span class="tip">{_T string="Extra information displayed along with dynamic field."}</span>
                    <textarea name="field_information" id="field_information" cols="20" rows="6">{$df->getInformation()}</textarea>
                </div>
            </div>
        </div>

        <div class="button-container">
            <button type="submit" class="ui labeled icon button action">
                <i class="save icon"></i> {_T string="Save"}
            </button>
            {include file="forms_types/csrf.tpl"}
        </div>
     </form>
{elseif $action == "add"}
    <form action="{path_for name="doAddDynamicField" data=["form_name" => $form_name]}" method="post" enctype="multipart/form-data" title="{_T string="New dynamic field"}" class="ui form">
    {if $mode neq 'ajax'}
        <div class="ui segment">
            <div class="ui tiny header">
                {_T string="New dynamic field"}
            </div>
            <div class="active content field">
    {else}
        <div class="cssform">
    {/if}
                <div class="inline field">
                    <label for="field_name">{_T string="Field name"}</label>
                    <input size="40" type="text" name="field_name" id="field_name" value="{if isset($df)}{$df->getName()}{/if}"/>
                </div>
                <div class="inline field">
                    <label for="field_perm">{_T string="Visibility"}</label>
                    <select name="field_perm" id="field_perm" class="ui dropdown nochosen">
                        {assign var="perm" value=0}
                        {if isset($df)}
                            {assign var="perm" value=$df->getPerm()}
                        {/if}
                        {html_options options=$perm_names selected=$perm}
                    </select>
                </div>
                <div class="inline field">
                    <label for="field_type">{_T string="Type"}</label>
                    <select name="field_type" id="field_type" class="ui dropdown nochosen">
                        {assign var="type" value=0}
                        {if isset($df)}
                            {assign var="type" value=$df->getType()}
                        {/if}
                        {html_options options=$field_type_names selected=$type}
                    </select>
                </div>
                <div class="inline field">
                    <label for="field_required">{_T string="Required"}</label>
                    <select name="field_required" id="field_required" class="ui dropdown nochosen">
                        <option value="0"{if not isset($df) or not $df->isRequired()} selected="selected"{/if}>{_T string="No"}</option>
                        <option value="1"{if isset($df) and $df->isRequired()} selected="selected"{/if}>{_T string="Yes"}</option>
                    </select>
                    {include file="forms_types/csrf.tpl"}
                </div>
    {if $mode neq 'ajax'}
            </div>
    {/if}
        </div>
        <div class="button-container">
            <button type="submit" name="valid" class="ui labeled icon button action">
                <i class="plus icon"></i> {_T string="Add"}
            </button>
            <input type="hidden" name="form_name" id="form_name" value="{$form_name}"/>
        </div>
    </form>
{/if}
{/block}

{block name="javascripts"}
    <script>
        $('#field_information').summernote({
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
        $('#field_information').summernote('focus');

    </script>
{/block}
