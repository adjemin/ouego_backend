<!-- Invoice Id Field -->
<div class="col-sm-12">
    {!! Form::label('invoice_id', 'Invoice Id:') !!}
    <p>{{ $payment->invoice_id }}</p>
</div>

<!-- Payment Method Code Field -->
<div class="col-sm-12">
    {!! Form::label('payment_method_code', 'Payment Method Code:') !!}
    <p>{{ $payment->payment_method_code }}</p>
</div>

<!-- Payment Reference Field -->
<div class="col-sm-12">
    {!! Form::label('payment_reference', 'Payment Reference:') !!}
    <p>{{ $payment->payment_reference }}</p>
</div>

<!-- Amount Field -->
<div class="col-sm-12">
    {!! Form::label('amount', 'Amount:') !!}
    <p>{{ $payment->amount }}</p>
</div>

<!-- Currency Code Field -->
<div class="col-sm-12">
    {!! Form::label('currency_code', 'Currency Code:') !!}
    <p>{{ $payment->currency_code }}</p>
</div>

<!-- User Id Field -->
<div class="col-sm-12">
    {!! Form::label('user_id', 'User Id:') !!}
    <p>{{ $payment->user_id }}</p>
</div>

<!-- Status Field -->
<div class="col-sm-12">
    {!! Form::label('status', 'Status:') !!}
    <p>{{ $payment->status }}</p>
</div>

<!-- Is Waiting Field -->
<div class="col-sm-12">
    {!! Form::label('is_waiting', 'Is Waiting:') !!}
    <p>{{ $payment->is_waiting }}</p>
</div>

<!-- Is Completed Field -->
<div class="col-sm-12">
    {!! Form::label('is_completed', 'Is Completed:') !!}
    <p>{{ $payment->is_completed }}</p>
</div>

<!-- Payment Gateway Trans Id Field -->
<div class="col-sm-12">
    {!! Form::label('payment_gateway_trans_id', 'Payment Gateway Trans Id:') !!}
    <p>{{ $payment->payment_gateway_trans_id }}</p>
</div>

<!-- Payment Gateway Custom Field -->
<div class="col-sm-12">
    {!! Form::label('payment_gateway_custom', 'Payment Gateway Custom:') !!}
    <p>{{ $payment->payment_gateway_custom }}</p>
</div>

<!-- Payment Gateway Currency Field -->
<div class="col-sm-12">
    {!! Form::label('payment_gateway_currency', 'Payment Gateway Currency:') !!}
    <p>{{ $payment->payment_gateway_currency }}</p>
</div>

<!-- Payment Gateway Amount Field -->
<div class="col-sm-12">
    {!! Form::label('payment_gateway_amount', 'Payment Gateway Amount:') !!}
    <p>{{ $payment->payment_gateway_amount }}</p>
</div>

<!-- Payment Gateway Payment Date Field -->
<div class="col-sm-12">
    {!! Form::label('payment_gateway_payment_date', 'Payment Gateway Payment Date:') !!}
    <p>{{ $payment->payment_gateway_payment_date }}</p>
</div>

<!-- Payment Gateway Error Message Field -->
<div class="col-sm-12">
    {!! Form::label('payment_gateway_error_message', 'Payment Gateway Error Message:') !!}
    <p>{{ $payment->payment_gateway_error_message }}</p>
</div>

<!-- Payment Gateway Payment Method Field -->
<div class="col-sm-12">
    {!! Form::label('payment_gateway_payment_method', 'Payment Gateway Payment Method:') !!}
    <p>{{ $payment->payment_gateway_payment_method }}</p>
</div>

<!-- Payment Gateway Buyer Name Field -->
<div class="col-sm-12">
    {!! Form::label('payment_gateway_buyer_name', 'Payment Gateway Buyer Name:') !!}
    <p>{{ $payment->payment_gateway_buyer_name }}</p>
</div>

<!-- Payment Gateway Buyer Reference Field -->
<div class="col-sm-12">
    {!! Form::label('payment_gateway_buyer_reference', 'Payment Gateway Buyer Reference:') !!}
    <p>{{ $payment->payment_gateway_buyer_reference }}</p>
</div>

<!-- Payment Gateway Trans Status Field -->
<div class="col-sm-12">
    {!! Form::label('payment_gateway_trans_status', 'Payment Gateway Trans Status:') !!}
    <p>{{ $payment->payment_gateway_trans_status }}</p>
</div>

<!-- Payment Gateway Designation Field -->
<div class="col-sm-12">
    {!! Form::label('payment_gateway_designation', 'Payment Gateway Designation:') !!}
    <p>{{ $payment->payment_gateway_designation }}</p>
</div>

<!-- Created At Field -->
<div class="col-sm-12">
    {!! Form::label('created_at', 'Created At:') !!}
    <p>{{ $payment->created_at }}</p>
</div>

<!-- Updated At Field -->
<div class="col-sm-12">
    {!! Form::label('updated_at', 'Updated At:') !!}
    <p>{{ $payment->updated_at }}</p>
</div>

