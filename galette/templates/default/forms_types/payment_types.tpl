<p>
    <label class="bline" for="type_paiement_cotis">{_T string="Payment type:"}</label>
    <select name="type_paiement_cotis" id="type_paiement_cotis">
        <option value="{Galette\Entity\Contribution::PAYMENT_CASH}"{if $contribution->payment_type eq constant('Galette\Entity\Contribution::PAYMENT_CASH')} selected="selected"{/if}>{_T string="Cash"}</option>
        <option value="{Galette\Entity\Contribution::PAYMENT_CREDITCARD}"{if $contribution->payment_type eq constant('Galette\Entity\Contribution::PAYMENT_CREDITCARD')} selected="selected"{/if}>{_T string="Credit card"}</option>
        <option value="{Galette\Entity\Contribution::PAYMENT_CHECK}"{if $contribution->payment_type eq constant('Galette\Entity\Contribution::PAYMENT_CHECK')} selected="selected"{/if}>{_T string="Check"}</option>
        <option value="{Galette\Entity\Contribution::PAYMENT_TRANSFER}"{if $contribution->payment_type eq constant('Galette\Entity\Contribution::PAYMENT_TRANSFER')} selected="selected"{/if}>{_T string="Transfer"}</option>
        <option value="{Galette\Entity\Contribution::PAYMENT_PAYPAL}"{if $contribution->payment_type eq constant('Galette\Entity\Contribution::PAYMENT_PAYPAL')} selected="selected"{/if}>{_T string="Paypal"}</option>
        <option value="{Galette\Entity\Contribution::PAYMENT_OTHER}"{if $contribution->payment_type eq constant('Galette\Entity\Contribution::PAYMENT_OTHER')} selected="selected"{/if}>{_T string="Other"}</option>
    </select>
</p>
