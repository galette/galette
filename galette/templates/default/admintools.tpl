{extends file="page.tpl"}
{block name="content"}
    <form action="{path_for name="doAdminTools"}" method="post">
        <div class="errorbox">
            <p>{_T string="Make sure you've done a backup of the database before using any of the following tools!"}</p>
        </div>
        <fieldset class="galette_form" id="general">
            <legend>{_T string="Select actions(s)"}</legend>
            <p>
                <label for="inittexts" class="tooltip" title="{_T string="Reset all emails contents to their default values"}">{_T string="Reset emails contents"}</label>
                <span class="tip">{_T string="Reset all emails contents to their default values"}</span>
                <input type="checkbox" name="inittexts" id="inittexts"/>
                <span class="exemple">{_T string="(all existing values will be removed)"}</span>
            </p>
            <p>
                <label for="initfields" class="tooltip" title="{_T string="Reset all emails contents to their default values"}">{_T string="Reset fields configuration"}</label>
                <span class="tip"> {_T string="Reset all emails contents to their default values"}<br/>{_T string="This includes fields positions, order, visibility, access levels and mandatory marks."}</span>
                <input type="checkbox" name="initfields" id="initfields"/>
                    <span class="exemple">{_T string="(all existing values will be removed)"}</span>
            </p>
            <p>
                <label for="initpdfmodels" class="tooltip" title="{_T string="Reset all PDF models to their default values"}">{_T string="Reinitialize PDF models"}</label>
                <span class="tip">{_T string="Reset all PDF models to their default values"}</span>
                <input type="checkbox" name="initpdfmodels" id="initpdfmodels"/>
                    <span class="exemple">{_T string="(all existing values will be removed)"}</span>
            </p>
            <p>
                <label for="emptylogins" class="tooltip" title="{_T string="Fill all empty login and passwords"}">{_T string="Generate empty logins and passwords"}</label>
                <span class="tip">{_T string="Fill all empty login and passwords"}</span>
                <input type="checkbox" name="emptylogins" id="emptylogins"/>
            </p>
        </fieldset>
        <div class="button-container">
            <button type="submit" class="action">
                <i class="fas fa-database" aria-hidden="true"></i>
                {_T string="Go"}
            </button>
            {include file="forms_types/csrf.tpl"}
        </div>
    </form>
{/block}
