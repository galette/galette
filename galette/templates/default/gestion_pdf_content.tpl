        <form action="gestion_pdf.php" method="post" enctype="multipart/form-data">
            <fieldset class="cssform">
                <legend>{$model->name}</legend>
{if $model->id neq 1}
                <p>
                    <label class="bline" for="title_{$model->id}">{_T string="Title"}</label>
                    <input type="text" name="model_title" id="title_{$model->id}" value="{$model->title|escape}"/>
                </p>
                <p>
                    <label class="bline" for="subtitle_{$model->id}">{_T string="Subtitle"}</label>
                    <input type="text" name="model_subtitle" id="subtitle_{$model->id}" value="{$model->subtitle|escape}"/>
                </p>
{/if}
                <p>
                    <label class="bline" for="header_{$model->id}">{_T string="Header"}</label>
                    <textarea name="model_header" id="header_{$model->id}">{$model->header}</textarea>
                </p>
                <p>
                    <label class="bline" for="footer_{$model->id}">{_T string="Footer"}</label>
                    <textarea name="model_footer" id="footer_{$model->id}">{$model->footer}</textarea>
                </p>
{if $model->id neq 1}
                <p>
                    <label class="bline" for="body_{$model->id}">{_T string="Body"}</label>
                    <textarea name="model_body" id="body_{$model->id}">{$model->body}</textarea>
                </p>
{/if}
                <p>
                    <label class="bline" for="styles_{$model->id}">{_T string="CSS styles"}</label>
                    <textarea name="model_styles" id="styles_{$model->id}">{$model->styles}</textarea>
                </p>
{if $model->id gt 3}
                <p>
                    <label class="bline" for="type_{$model->id}">{_T string="Type"}</label>
                    <select name="model_type" id="type_{$model->id}" required>
                        <option value="">{_T string="Select"}</option>
                        <option value="{php}echo Galette\Entity\PdfModel::INVOICE_MODEL;{/php}">{_T string="Invoice"}</option>
                        <option value="{php}echo Galette\Entity\PdfModel::RECEIPT_MODEL;{/php}">{_T string="Receipt"}</option>
                    </select>
                </p>
{/if}
            </fieldset>
            <div class="button-container">
                <input type="hidden" name="store" value="true"/>
                <input type="hidden" name="model_id" value="{$model->id}"/>
{if $model->id lte 3}
                <input type="hidden" name="model_type" value="{$model->type}"/>
{/if}
                <input type="submit" id="btnsave" value="{_T string="Save"}"/>
            </div>
        </form>
