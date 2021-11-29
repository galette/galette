{extends file="page.tpl"}

{block name="content"}
    <form action="{path_for name="editTitle" data=["id" => $title->id]}" method="post">
        <div class="bigtable">
            <fieldset class="cssform" id="general">
            <p>
                <label for="short_label" class="bline">{_T string="Short form:"}</label>
                <input type="text" name="short_label" id="short_label" value="{$title->short}" />
            </p>
            <p>
                <label for="long_label" class="bline">{_T string="Long form:"}</label>
                <input type="text" name="long_label" id="long_label" value="{$title->long}" />
            </p>

        </div>
            <div class="button-container">
                <button type="submit" class="action">
                    <i class="fas fa-save fa-fw"></i> {_T string="Save"}
                </button>
                <input type="submit" name="cancel" value="{_T string="Cancel"}"/>
                <input type="hidden" name="id" id="id" value="{$title->id}"/>
                {include file="forms_types/csrf.tpl"}
            </div>
     </form>
{/block}
