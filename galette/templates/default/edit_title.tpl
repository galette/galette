{extends file="page.tpl"}

{block name="content"}
    <form action="{path_for name="editTitle" data=["id" => $title->id]}" method="post" class="ui form">
        <div class="ui segment" id="general">
            <div class="field inline">
                <label for="short_label">{_T string="Short form:"}</label>
                <input type="text" name="short_label" id="short_label" value="{$title->short}" />
            </div>
            <div class="field inline">
                <label for="long_label">{_T string="Long form:"}</label>
                <input type="text" name="long_label" id="long_label" value="{$title->long}" />
            </div>
        </div>
        <div class="button-container">
            <button type="submit" class="ui labeled icon primary button action">
                <i class="save icon"></i> {_T string="Save"}
            </button>
            <input type="submit" name="cancel" value="{_T string="Cancel"}" class="ui button" />
            <input type="hidden" name="id" id="id" value="{$title->id}"/>
                {include file="forms_types/csrf.tpl"}
        </div>
     </form>
{/block}
