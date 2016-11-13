{if $mode eq 'ajax'}
    {assign var="extend" value='ajax.tpl'}
{else}
    {assign var="extend" value='page.tpl'}
{/if}
{extends file=$extend}

{block name="content"}
{if $action == {_T string="edit" domain="routes"}}
    <form action="{path_for name="doEditDynamicField" data=["action" => $action, "form" => $form_name, "id" => $df->getId()]}" method="post">
        <fieldset class="cssform">
            <legend class="ui-state-active ui-corner-top">{_T string="Edit field %field" pattern="/%field/" replace=$df->getName()}</legend>
            <p>
                <label for="field_name" class="bline">{_T string="Name:"}</label>
                <input type="text" name="field_name" id="field_name" value="{$df->getName()}"/>
            </p>
            <p>
                <label for="field_perm" class="bline">{_T string="Visibility:"}</label>
                <select name="field_perm" id="field_perm">
                    <option value="{$perm_all}" {if $df->getPerm() eq constant('Galette\Entity\DynamicFields::PERM_ALL')}selected="selected"{/if}>{$perm_names[$perm_all]}</option>
                    <option value="{$perm_staff}" {if $df->getPerm() eq constant('Galette\Entity\DynamicFields::PERM_STAFF')}selected="selected"{/if}>{$perm_names[$perm_staff]}</option>
                    <option value="{$perm_admin}" {if $df->getPerm() eq constant('Galette\Entity\DynamicFields::PERM_ADM')}selected="selected"{/if}>{$perm_names[$perm_admin]}</option>
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
        {if $df|is_a:'Galette\DynamicFieldsTypes\File'}
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
                <textarea name="fixed_values" id="fixed_values" cols="20" rows="6">{$df->getValues()}</textarea>
                <br/><span class="exemple">{_T string="Choice list (one entry per line)."}</span>
            </p>
{/if}
            <div class="button-container">
                <input type="submit" name="valid" value="{_T string="Save"}" id="btnsave"/>
            </div>
        </fieldset>
     </form>
{elseif $action == {_T string="add" domain="routes"}}
    <form action="{path_for name="doEditDynamicField" data=["form" => $form_name, "action" => {_T string="add" domain="routes"}]}" method="post" enctype="multipart/form-data" title="{_T string="New dynamic field"}">
    {if $mode neq 'ajax'}
        <fieldset class="cssform">
            <legend class="ui-state-active ui-corner-top">{_T string="New dynamic field"}</legend>
    {else}
        <div class="cssform">
    {/if}
            <p>
                <label for="field_name" class="bline">{_T string="Field name"}</label>
                <input size="40" type="text" name="field_name" id="field_name"/>
            </p>
            <p>
                <label for="field_perm" class="bline">{_T string="Visibility"}</label>
                <select name="field_perm" id="field_perm">
                    {html_options options=$perm_names selected="0"}
                </select>
            </p>
            <p>
                <label for="field_type" class="bline">{_T string="Type"}</label>
                <select name="field_type" id="field_type">
                    {html_options options=$field_type_names selected="0"}
                </select>
            </p>
            <p>
                <label for="field_required" class="bline">{_T string="Required"}</label>
                <select name="field_required" id="field_required">
                    <option value="0">{_T string="No"}</option>
                    <option value="1">{_T string="Yes"}</option>
                </select>
            </p>
            <div class="center">
                <input type="submit" name="valid" id="btnadd" value="{_T string="Add"}"/>
                <input type="hidden" name="form" id="formname" value="{$form_name}"/>
            </div>
    {if $mode neq 'ajax'}
        </fieldset>
    {else}
        </div>
    {/if}
    </form>
{/if}
{/block}
