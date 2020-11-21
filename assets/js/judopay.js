jQuery(function ($) {
    $(document.body).on('updated_checkout', function () {
        maestro_validate();
    });
    $(document.body).on('change', '#judopay-card-number', function () {
        maestro_validate();
    });

    function maestro_validate() {
        if ($('#judopay-card-number').hasClass('maestro')) {
            if ($('#judopay-maestro-startDate').length < 1) {
                $('#wc-judopay-cc-form').append(`
                    <p id="judopay-maestro-startDate" class="form-row form-row-first woocommerce-validated">
                        <label for="judopay-card-startDate">Start Day (MM/YY)&nbsp;<span class="required">*</span></label>
                        <input id="judopay-card-startDate" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="MM / YY" name="judopay-card-startDate">
                    </p>
                `);
            }
            if ($('#judopay-maestro-issueNumber').length < 1) {
                $('#wc-judopay-cc-form').append(`
                    <p id="judopay-maestro-issueNumber" class="form-row form-row-last woocommerce-validated">
                        <label for="judopay-card-issueNumber">Issue Number&nbsp;<span class="required">*</span></label>
                        <input id="judopay-card-issueNumber" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="2" name="judopay-card-issueNumber" style="width:100px">
                    </p>
                `);
            }
            $( '.wc-credit-card-form-card-expiry' ).payment( 'formatCardExpiry' );
            $( '.wc-credit-card-form-card-cvc' ).payment( 'formatCardCVC' );
        } else {
            $('#judopay-maestro-startDate').remove();
            $('#judopay-maestro-issueNumber').remove();
        }
    }
});
