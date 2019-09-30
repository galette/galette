{extends file="page.tpl"}

{block name="content"}
    <form action="{path_for name="editPaymentType" data=["id" => $ptype->id]}" method="post" class="ui form">
        <div class="ui segment" id="general">
            <div class="field inline">
                <label for="name" class="" title="{_T string="Original string for name, that will be used for translations."}">{_T string="Name:"}</label>
                <div class="ui right corner labeled input">
                    <div class="ui corner label">
                        <i class="circular inverted primary link icon info tooltip" data-html="{_T string="Original string for name, that will be used for translations."}"></i>
                    </div>
                    <input type="text" name="name" id="name" value="{$ptype->name}" />
                </div>
            </div>
        </div>
        <div class="button-container">
            <button type="submit" class="ui labeled icon button action">
                <i class="save icon"></i> {_T string="Save"}
            </button>
            <input type="submit" name="cancel" value="{_T string="Cancel"}" class="ui button"/>
            <input type="hidden" name="id" id="id" value="{$ptype->id}"/>
            {include file="forms_types/csrf.tpl"}
        </div>
     </form>
{/block}
