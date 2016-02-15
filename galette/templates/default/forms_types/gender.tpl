{extends file="forms_types/input.tpl"}

{block name="component"}
    <p class="radios">
        {block name="label"}
            <span class="bline">{$label}</span>
        {/block}

        {block name="element"}
            <input type="radio" name="sexe_adh" id="gender_nc" value="{Galette\Entity\Adherent::NC}"{if !$member->isMan() and !$member->isWoman()} checked="checked"{/if}{if isset($disabled.sexe_adh)} {$disabled.sexe_adh}{/if}/>
            <label for="gender_nc">{_T string="Unspecified"}</label>
            <input type="radio" name="sexe_adh" id="gender_man" value="{Galette\Entity\Adherent::MAN}"{if $member->isMan()} checked="checked"{/if}{if isset($disabled.sexe_adh)} {$disabled.sexe_adh}{/if}/>
            <label for="gender_man">{_T string="Man"}</label>
            <input type="radio" name="sexe_adh" id="gender_woman" value="{Galette\Entity\Adherent::WOMAN}"{if $member->isWoman()} checked="checked"{/if}{if isset($disabled.sexe_adh)} {$disabled.sexe_adh}{/if}/>
            <label for="gender_woman">{_T string="Woman"}</label>
        {/block}
    </p>
{/block}
