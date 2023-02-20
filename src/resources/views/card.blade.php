<div class="row">
    <div class="col-md-12">
        @php \Actions::do_action('pre_sagePay_checkout_form',$gateway) @endphp
        <h5>Enter your card details</h5>
        <form id="payment-form" action="{{ url($action) }}" method="post">
            @csrf
            <div class="row">
                <div class="col-md-12">
                    {!! CoralsForm::text('payment_details[number]','SagePay::attributes.card_number',true,'',['maxlength'=>16,'id'=>'sagePay_number']) !!}
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    {!! CoralsForm::select('payment_details[expiryMonth]', 'SagePay::attributes.expMonth', \Payments::expiryMonth(), true,now()->format('m')) !!}
                </div>
                <div class="col-md-4">
                    {!! CoralsForm::select('payment_details[expiryYear]', 'SagePay::attributes.expYear', \Payments::expiryYear(), true,now()->format('Y')) !!}
                </div>
                <div class="col-md-4">
                    {!! CoralsForm::text('payment_details[cvv]','SagePay::attributes.cvv', true,'',['placeholder'=>"CCV", 'maxlength'=>4,'id'=>'sagePay_cvv']) !!}
                </div>
            </div>
        </form>
    </div>
</div>

<script type="text/javascript">
    $('#payment-form').on('submit', function (event) {
        event.preventDefault();

        $form = $('#payment-form');
        $form.find('input[type=text]').empty();
        $form.append("<input type='hidden' name='checkoutToken' value='SagePay'/>");
        $form.append("<input type='hidden' name='gateway' value='SagePay'/>");
        ajax_form($form);
    });
</script>
