{if !isset($classname)}
    {assign var="classname" value="bline"}
{/if}
<p>
    <label class="{$classname}" for="{$varname}">{_T string="Payment type:"}</label>
    <select name="{$varname}" id="{$varname}">
        <option value="{Galette\Entity\Contribution::PAYMENT_CASH}"{if $current eq constant('Galette\Entity\Contribution::PAYMENT_CASH')} selected="selected"{/if}>{_T string="Cash"}</option>
        <option value="{Galette\Entity\Contribution::PAYMENT_CREDITCARD}"{if $current eq constant('Galette\Entity\Contribution::PAYMENT_CREDITCARD')} selected="selected"{/if}>{_T string="Credit card"}</option>
        <option value="{Galette\Entity\Contribution::PAYMENT_CHECK}"{if $current eq constant('Galette\Entity\Contribution::PAYMENT_CHECK')} selected="selected"{/if}>{_T string="Check"}</option>
        <option value="{Galette\Entity\Contribution::PAYMENT_TRANSFER}"{if $current eq constant('Galette\Entity\Contribution::PAYMENT_TRANSFER')} selected="selected"{/if}>{_T string="Transfer"}</option>
        <option value="{Galette\Entity\Contribution::PAYMENT_PAYPAL}"{if $current eq constant('Galette\Entity\Contribution::PAYMENT_PAYPAL')} selected="selected"{/if}>{_T string="Paypal"}</option>
        <option value="{Galette\Entity\Contribution::PAYMENT_OTHER}"{if $current eq constant('Galette\Entity\Contribution::PAYMENT_OTHER')} selected="selected"{/if}>{_T string="Other"}</option>
    </select>
</p>
