        <form action="{path_for name="pdfModels"}" method="post" enctype="multipart/form-data" class="form ui">
            <fieldset class="cssform">
                <div class="ui basic segment">
{if $model->id neq 1}
                    <div class="inline field">
                        <label for="title_{$model->id}">{_T string="Title"}</label>
                        <input type="text" name="model_title" id="title_{$model->id}" class="large" value="{$model->title|escape}"/>
                    </div>
                    <div class="inline field">
                        <label for="subtitle_{$model->id}">{_T string="Subtitle"}</label>
                        <input type="text" name="model_subtitle" id="subtitle_{$model->id}" class="large" value="{$model->subtitle|escape}"/>
                    </div>
{/if}
                    <div class="field">
                        <label for="header_{$model->id}">{_T string="Header"}</label>
                        <textarea name="model_header" id="header_{$model->id}">{$model->header}</textarea>
                    </div>
                    <div class="field">
                        <label for="footer_{$model->id}">{_T string="Footer"}</label>
                        <textarea name="model_footer" id="footer_{$model->id}">{$model->footer}</textarea>
                    </div>
{if $model->id neq 1}
                    <div class="field">
                        <label for="body_{$model->id}">{_T string="Body"}</label>
                        <textarea name="model_body" id="body_{$model->id}">{$model->body}</textarea>
                    </div>
{/if}
                    <div class="field">
                        <label for="styles_{$model->id}">{_T string="CSS styles"}</label>
                        <textarea name="model_styles" id="styles_{$model->id}">{$model->styles}</textarea>
                    </div>
{if $model->id gt 4}
                    <div class="field">
                        <label for="type_{$model->id}">{_T string="Type"}</label>
                        <select name="model_type" id="type_{$model->id}" class="ui dropdown nochosen" required>
                            <option value="">{_T string="Select"}</option>
                            <option value="{Galette\Entity\PdfModel::INVOICE_MODEL}">{_T string="Invoice"}</option>
                            <option value="{Galette\Entity\PdfModel::RECEIPT_MODEL}">{_T string="Receipt"}</option>
                            <option value="{Galette\Entity\PdfModel::ADHESION_FORM_MODEL}">{_T string="Adhesion Form"}</option>
                        </select>
                    </div>
{/if}
                </div>
            </fieldset>
            <div class="ui basic center aligned segment">
                <input type="hidden" name="store" value="true"/>
                <input type="hidden" name="model_id" value="{$model->id}"/>
{if $model->id lte 4}
                <input type="hidden" name="model_type" value="{$model->type}"/>
{/if}
                <button type="submit" class="ui labeled icon button action">
                    <i class="save icon"></i> {_T string="Save"}
                </button>
                {include file="forms_types/csrf.tpl"}
            </div>
        </form>
        {include file="replacements_legend.tpl" legends=$model->getLegend() cur_ref=$model->id}