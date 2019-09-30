{extends file="page.tpl"}
{block name="content"}
    <form action="{path_for name="doAdminTools"}" method="post" class="ui form">
        <div class="ui orange message">{_T string="Make sure you've done a backup of the database before using any of the following tools!"}</div>
        <div class="ui segment">
            <div class="ui tiny header">
                {_T string="Select actions(s)"}
            </div>
            <div class="active content field">
                <div class="field">
                    <label for="inittexts" title="{_T string="Reset all emails contents to their default values"}">{_T string="Reset emails contents"}</label>
                    <input type="checkbox" name="inittexts" id="inittexts"/>
                    <i class="circular inverted primary small icon info tooltip" data-html="{_T string="Reset all emails contents to their default values"}"></i>
                    <span class="exemple">{_T string="(all existing values will be removed)"}</span>
                </div>
                <div class="field">
                    <label for="initfields" title="{_T string="Reset all emails contents to their default values"}">{_T string="Reset fields configuration"}</label>
                    <input type="checkbox" name="initfields" id="initfields"/>
                    <i class="circular inverted primary small icon info tooltip" data-html="{_T string="Reset all emails contents to their default values"}<br/>{_T string="This includes fields positions, order, visibility, access levels and mandatory marks."}"></i>
                    <span class="exemple">{_T string="(all existing values will be removed)"}</span>
                </div>
                <div class="field">
                    <label for="initpdfmodels" title="{_T string="Reset all PDF models to their default values"}">{_T string="Reinitialize PDF models"}</label>
                    <span class="tip"></span>
                    <input type="checkbox" name="initpdfmodels" id="initpdfmodels"/>
                    <i class="circular inverted primary small icon info tooltip" data-html="{_T string="Reset all PDF models to their default values"}"></i>
                    <span class="exemple">{_T string="(all existing values will be removed)"}</span>
                </div>
                <div class="field">
                    <label for="emptylogins" title="{_T string="Fill all empty login and passwords"}">{_T string="Generate empty logins and passwords"}</label>
                    <input type="checkbox" name="emptylogins" id="emptylogins"/>
                    <i class="circular inverted primary small icon info tooltip" data-html="{_T string="Fill all empty login and passwords"}"></i>
                </div>
            </div>
        </div>
        <div class="button-container">
            <button type="submit" class="ui labeled icon button action">
                <i class="database icon" aria-hidden="true"></i>
                {_T string="Go"}
            </button>
            {include file="forms_types/csrf.tpl"}
        </div>
    </form>
{/block}
