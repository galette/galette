{extends file="page.tpl"}

{block name="content"}
    <form action="{path_for name="editPaymentType" data=["id" => $ptype->id]}" method="post">
        <div class="bigtable">
            <fieldset class="cssform" id="general">
            <p>
                <label for="name" class="bline tooltip" title="Original string for name">{_T string="Name:"}</label>
                <span class="tip">{_T string="Original string for name, that will be used for translations."}</span>
                <input type="text" name="name" id="name" value="{$ptype->name}" />
            </p>
        </div>
        <div class="button-container">
            <button type="submit" class="action">
                <i class="fas fa-save fa-fw"></i> {_T string="Save"}
            </button>
            <input type="submit" name="cancel" value="{_T string="Cancel"}"/>
            <input type="hidden" name="id" id="id" value="{$ptype->id}"/>
            {include file="forms_types/csrf.tpl"}
        </div>
     </form>
{/block}
