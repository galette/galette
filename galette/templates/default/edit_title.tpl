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
                <input type="submit" value="{_T string="Save"}" />
                <input type="submit" name="cancel" value="{_T string="Cancel"}"/>
                <input type="hidden" name="id" id="id" value="{$title->id}"/>
            </div>
     </form>
{/block}
