{extends file="page.tpl"}
{block name="content"}
    <form action="{path_for name="doFakeData"}" method="post" enctype="multipart/form-data">
        <div class="bigtable">
        <fieldset class="galette_form" id="core">
            <legend>{_T string="Core data"}</legend>
            <div>
                <p>
                    <label for="number_members">{_T string="Number of members:"}</label>
                    <input type="number" name="number_members" id="number_members" value="{$number_members}" min="0" max="100"/>
                </p>
                <p>
                    <label for="number_groups">{_T string="Number of groups:"}</label>
                    <input type="number" name="number_groups" id="number_groups" value="{$number_groups}" min="0" max="10"/>
                </p>
                <p>
                    <label for="number_contrib" class="tooltip" title="{_T string="Maximum number of contributions to generate for reach member"}">{_T string="Number of contributions:"}</label>
                    <span class="tip">{_T string="Maximum number of contributions to generate for reach member"}</span>
                    <input type="number" name="number_contrib" id="number_contrib" value="{$number_contrib}" min="0" max="10"/>
                </p>
                <p>
                    <label for="number_transactions">{_T string="Number of transactions:"}</label>
                    <input type="number" name="number_transactions" id="number_transactions" value="{$number_transactions}" min="0" max="5"/>
                </p>
            </div>
        </fieldset>
        <!--fieldset class="galette_form" id="core">
            <legend>{_T string="Dynamic fields"}</legend>
            <div>
                <p class="center">{_T string="Generate some predefined dynamic fields"}</p>
                <p>
                    <label for="dynamic_fields_adh">{_T string="Members"}</label>
                    <input type="checkbox" name="dynamic_fields_adh" id="dynamic_fields_adh" value="1" checked="checked"/>
                </p>
                <p>
                    <label for="dynamic_fields_contrib">{_T string="Contributions"}</label>
                    <input type="checkbox" name="dynamic_fields_contrib" id="dynamic_fields_contrib" value="1" checked="checked"/>
                </p>
                <p>
                    <label for="dynamic_fields_trans">{_T string="Transactions"}</label>
                    <input type="checkbox" name="dynamic_fields_trans" id="dynamic_fields_trans" value="1" checked="checked"/>
                </p>
            </div>
        </fieldset-->
        </div>
        <div class="button-container">
            <input type="submit" name="generate" id="btntools" class="button" value="{_T string="Generate"}"/>
        </div>
    </form>
{/block}

{block name="javascripts"}
    <script type="text/javascript">
        $(function() {
            _collapsibleFieldsets();
        });
    </script>
{/block}
