<x-core::form.text-input
    :label="trans('plugins/ecommerce::setting.payment_method_cod_minimum_amount', ['currency' => get_application_currency()->title])"
    type="number"
    name="payment_cod_minimum_amount"
    :value="setting('payment_cod_minimum_amount', 0)"
/>
