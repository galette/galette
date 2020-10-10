{if $mode eq 'ajax'}
    {assign var="extend" value='ajax.tpl'}
{else}
    {assign var="extend" value='page.tpl'}
{/if}
{extends file=$extend}

{block name="content"}
{if $action == "edit"}
    <form action="{path_for name="doEditDynamicField" data=["form_name" => $form_name, "id" => $df->getId()]}" method="post">
        <fieldset class="cssform">
            <legend class="ui-state-active ui-corner-top">{_T string="Edit field %field" pattern="/%field/" replace=$df->getName()}</legend>
            <p>
                <label for="field_name" class="bline">{_T string="Name:"}</label>
                <input type="text" name="field_name" id="field_name" value="{$df->getName(false)}"{if not $df|is_a:'Galette\DynamicFields\Separator'} required="required"{/if}/>
            </p>
            <p>
                <label for="field_perm" class="bline">{_T string="Permissions:"}</label>
                <select name="field_perm" id="field_perm">
                    {html_options options=$perm_names selected=$df->getPerm()}
                </select>
            </p>
{if $df->hasData()}
            <p>
                <label for="field_required" class="bline">{_T string="Required:"}</label>
                <select name="field_required" id="field_required">
                    <option value="0" {if !$df->isRequired()}selected="selected"{/if}>{_T string="No"}</option>
                    <option value="1" {if $df->isRequired()}selected="selected"{/if}>{_T string="Yes"}</option>
                </select>
            </p>
{/if}
{if $df->hasWidth()}
            <p>
                <label for="field_width" class="bline">{_T string="Width:"}</label>
                <input type="text" name="field_width" id="field_width" value="{$df->getWidth()}" size="3"/>
            </p>
{/if}
{if $df->hasHeight()}
            <p>
                <label for="field_height" class="bline">{_T string="Height:"}</label>
                <input type="text" name="field_height" id="field_height" value="{$df->getHeight()}" size="3"/>
            </p>
{/if}
{if $df->hasSize()}
            <p>
                <label for="field_size" class="bline">{_T string="Size:"}</label>
                <input type="text" name="field_size" id="field_size" value="{$df->getSize()}" size="3"/>
        {if $df|is_a:'Galette\DynamicFields\File'}
                <span class="exemple">{_T string="Maximum file size, in Ko."}</span>
        {else}
                <span class="exemple">{_T string="Maximum number of characters."}</span>
        {/if}
            </p>
{/if}
{if $df->isMultiValued()}
            <p>
                <label for="field_repeat" class="bline">{_T string="Repeat:"}</label>
                <input type="text" name="field_repeat" id="field_repeat" value="{$df->getRepeat()}" size="3"/>
                <span class="exemple">{_T string="Number of values or zero if infinite."}</span>
            </p>
{/if}
{if $df->hasFixedValues()}
            <p>
                <label for="fixed_values" class="bline">{_T string="Values:"}</label>
                <textarea name="fixed_values" id="fixed_values" cols="20" rows="6">{$df->getValues(true)}</textarea>
                <br/><span class="exemple">{_T string="Choice list (one entry per line)."}</span>
            </p>
{/if}
        </fieldset>
            <div class="button-container">
                <button type="submit" class="action">
                    <i class="fas fa-save fa-fw"></i> {_T string="Save"}
                </button>
            </div>

     </form>
{elseif $action == "add"}
    <form action="{path_for name="doAddDynamicField" data=["form_name" => $form_name]}" method="post" enctype="multipart/form-data" title="{_T string="New dynamic field"}">
    {if $mode neq 'ajax'}
        <fieldset class="cssform">
            <legend class="ui-state-active ui-corner-top">{_T string="New dynamic field"}</legend>
    {else}
        <div class="cssform">
    {/if}
            <p>
                <label for="field_name" class="bline">{_T string="Field name"}</label>
                <input size="40" type="text" name="field_name" id="field_name" value="{if isset($df)}{$df->getName()}{/if}"/>
            </p>
            <p>
                <label for="field_perm" class="bline">{_T string="Visibility"}</label>
                <select name="field_perm" id="field_perm">
                    {assign var="perm" value=0}
                    {if isset($df)}
                        {assign var="perm" value=$df->getPerm()}
                    {/if}
                    {html_options options=$perm_names selected=$perm}
                </select>
            </p>
            <p>
                <label for="field_type" class="bline">{_T string="Type"}</label>
                <select name="field_type" id="field_type">
                    {assign var="type" value=0}
                    {if isset($df)}
                        {assign var="type" value=$df->getType()}
                    {/if}
                    {html_options options=$field_type_names selected=$type}
                </select>
            </p>
            <p>
                <label for="field_required" class="bline">{_T string="Required"}</label>
                <select name="field_required" id="field_required">
                    <option value="0"{if not isset($df) or not $df->isRequired()} selected="selected"{/if}>{_T string="No"}</option>
                    <option value="1"{if isset($df) and $df->isRequired()} selected="selected"{/if}>{_T string="Yes"}</option>
                </select>
            </p>
            <div class="center">
                <button type="submit" name="valid">
                    <i class="fas fa-plus"></i>
                    {_T string="Add"}
                </button>
                <input type="hidden" name="form_name" id="form_name" value="{$form_name}"/>
            </div>
    {if $mode neq 'ajax'}
        </fieldset>
    {else}
        </div>
    {/if}
    </form>
{/if}
{/block}
