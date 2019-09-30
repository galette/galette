{extends file="forms_types/input.tpl"}

{block name="component"}
    <div class="grouped fields">
        {block name="label"}
            <label>
            {if $masschange}
                {* Add a checkbox for fields to change on mass edition *}
                {* FIXME: use SUI capacitie to display that well *}
                <input type="checkbox" name="mass_{$entry->field_id}" class="mass_checkbox"/>
            {/if}
                {$label}
            </label>
        {/block}

        {block name="element"}
        <div class="field inline">
            <input type="radio" name="sexe_adh" id="gender_nc" value="{Galette\Entity\Adherent::NC}"{if !$member->isMan() and !$member->isWoman()} checked="checked"{/if}{if isset($disabled) and $disabled == true} disabled="disabled"{/if}/>
            <label for="gender_nc">{_T string="Unspecified"}</label>
        </div>
        <div class="field inline">
            <input type="radio" name="sexe_adh" id="gender_man" value="{Galette\Entity\Adherent::MAN}"{if $member->isMan()} checked="checked"{/if}{if isset($disabled) and $disabled == true} disabled="disabled"{/if}/>
            <label for="gender_man">{_T string="Man"}</label>
        </div>
        <div class="field inline">
            <input type="radio" name="sexe_adh" id="gender_woman" value="{Galette\Entity\Adherent::WOMAN}"{if $member->isWoman()} checked="checked"{/if}{if isset($disabled) and $disabled == true} disabled="disabled"{/if}/>
            <label for="gender_woman">{_T string="Woman"}</label>
        </div>
        {/block}
    </div>
{/block}
