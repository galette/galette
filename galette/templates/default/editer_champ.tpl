    <form action="editer_champ.php" method="post">
        <fieldset class="cssform">
            <legend class="ui-state-active ui-corner-top">{_T string="Edit field %field" pattern="/%field/" replace=$data.name}</legend>
            <p>
                <label for="field_name" class="bline">{_T string="Name:"}</label>
                <input type="text" name="field_name" id="field_name" value="{$data.name}"/>
            </p>
            <p>
                <label for="field_perm" class="bline">{_T string="Visibility:"}</label>
                <select name="field_perm" id="field_perm">
                    <option value="{$perm_all}" {if $data.perm == $perm_all}selected="selected"{/if}>{$perm_names[$perm_all]}</option>
                    <option value="{$perm_admin}" {if $data.perm == $perm_admin}selected="selected"{/if}>{$perm_names[$perm_admin]}</option>
                </select>
            </p>
{if !$properties.no_data}
            <p>
                <label for="field_required" class="bline">{_T string="Required:"}</label>
                <select name="field_required" id="field_required">
                    <option value="0" {if $data.required == 0}selected="selected"{/if}>{_T string="No"}</option>
                    <option value="1" {if $data.required == 1}selected="selected"{/if}>{_T string="Yes"}</option>
                </select>
            </p>
{/if}
{if $properties.with_width}
            <p>
                <label for="field_width" class="bline">{_T string="Width:"}</label>
                <input type="text" name="field_width" id="field_width" value="{$data.width}" size="3"/>
            </p>
{/if}
{if $properties.with_height}
            <p>
                <label for="field_height" class="bline">{_T string="Height:"}</label>
                <input type="text" name="field_height" id="field_height" value="{$data.height}" size="3"/>
            </p>
{/if}
{if $properties.with_size}
            <p>
                <label for="field_size" class="bline">{_T string="Size:"}</label>
                <input type="text" name="field_size" id="field_size" value="{$data.size}" size="3"/>
                <span class="exemple">{_T string="Maximum number of characters."}</span>
            </p>
{/if}
{if $properties.multi_valued}
            <p>
                <label for="field_repeat" class="bline">{_T string="Repeat:"}</label>
                <input type="text" name="field_repeat" id="field_repeat" value="{$data.repeat}" size="3"/>
                <span class="exemple">{_T string="Number of values or zero if infinite."}</span>
            </p>
{/if}
{if $properties.fixed_values}
            <p>
                <label for="fixed_values" class="bline">{_T string="Values:"}</label>
                <textarea name="fixed_values" id="fixed_values" cols="20" rows="6">{$data.fixed_values}</textarea>
                <br/><span class="exemple">{_T string="Choice list (one entry per line)."}</span>
            </p>
{/if}
            <div class="button-container">
                <input type="submit" name="valid" value="{_T string="Save"}" id="btnsave"/>
                <input type="submit" name="cancel" value="{_T string="Cancel"}" id="btncancel"/>
                <input type="hidden" name="form" value="{$form_name}"/>
                <input type="hidden" name="id" value="{$data.id}"/>
            </div>
        </fieldset>
     </form>
