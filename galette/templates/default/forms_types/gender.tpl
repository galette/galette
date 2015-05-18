{extends file="forms_types/input.tpl"}

{block name="component"}
    <p class="radios">
        {block name="label"}
            <span class="bline">{$label}</span>
        {/block}

        {block name="element"}
            <input type="radio" name="sexe_adh" id="gender_nc" value="{php}echo Galette\Entity\Adherent::NC;{/php}"{if !$member->isMan() and !$member->isWoman()} checked="checked"{/if}{if isset($disabled.sexe_adh)} disabled="disabled"{/if}/>
            <label for="gender_nc">{_T string="Unspecified"}</label>
            <input type="radio" name="sexe_adh" id="gender_man" value="{php}echo Galette\Entity\Adherent::MAN;{/php}"{if $member->isMan()} checked="checked"{/if}{if isset($disabled.sexe_adh)} disabled="disabled"{/if}/>
            <label for="gender_man">{_T string="Man"}</label>
            <input type="radio" name="sexe_adh" id="gender_woman" value="{php}echo Galette\Entity\Adherent::WOMAN;{/php}"{if $member->isWoman()} checked="checked"{/if}{if isset($disabled.sexe_adh)} disabled="disabled"{/if}/>
            <label for="gender_woman">{_T string="Woman"}</label>
        {/block}
    </p>
{/block}
