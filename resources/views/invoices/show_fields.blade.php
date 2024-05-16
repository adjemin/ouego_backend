<!-- Order Id Field -->
<div class="col-sm-12">
    {!! Form::label('order_id', 'Order Id:') !!}
    <p>{{ $invoice->order_id }}</p>
</div>

<!-- Customer Id Field -->
<div class="col-sm-12">
    {!! Form::label('customer_id', 'Customer Id:') !!}
    <p>{{ $invoice->customer_id }}</p>
</div>

<!-- Reference Field -->
<div class="col-sm-12">
    {!! Form::label('reference', 'Reference:') !!}
    <p>{{ $invoice->reference }}</p>
</div>

<!-- Subtotal Field -->
<div class="col-sm-12">
    {!! Form::label('subtotal', 'Subtotal:') !!}
    <p>{{ $invoice->subtotal }}</p>
</div>

<!-- Tax Field -->
<div class="col-sm-12">
    {!! Form::label('tax', 'Tax:') !!}
    <p>{{ $invoice->tax }}</p>
</div>

<!-- Fees Delivery Field -->
<div class="col-sm-12">
    {!! Form::label('fees_delivery', 'Fees Delivery:') !!}
    <p>{{ $invoice->fees_delivery }}</p>
</div>

<!-- Total Field -->
<div class="col-sm-12">
    {!! Form::label('total', 'Total:') !!}
    <p>{{ $invoice->total }}</p>
</div>

<!-- Status Field -->
<div class="col-sm-12">
    {!! Form::label('status', 'Status:') !!}
    <p>{{ $invoice->status }}</p>
</div>

<!-- Is Paid By Customer Field -->
<div class="col-sm-12">
    {!! Form::label('is_paid_by_customer', 'Is Paid By Customer:') !!}
    <p>{{ $invoice->is_paid_by_customer }}</p>
</div>

<!-- Currency Code Field -->
<div class="col-sm-12">
    {!! Form::label('currency_code', 'Currency Code:') !!}
    <p>{{ $invoice->currency_code }}</p>
</div>

<!-- Driver Due Field -->
<div class="col-sm-12">
    {!! Form::label('driver_due', 'Driver Due:') !!}
    <p>{{ $invoice->driver_due }}</p>
</div>

<!-- Service Due Field -->
<div class="col-sm-12">
    {!! Form::label('service_due', 'Service Due:') !!}
    <p>{{ $invoice->service_due }}</p>
</div>

<!-- Discount Field -->
<div class="col-sm-12">
    {!! Form::label('discount', 'Discount:') !!}
    <p>{{ $invoice->discount }}</p>
</div>

<!-- Coupon Field -->
<div class="col-sm-12">
    {!! Form::label('coupon', 'Coupon:') !!}
    <p>{{ $invoice->coupon }}</p>
</div>

<!-- Created At Field -->
<div class="col-sm-12">
    {!! Form::label('created_at', 'Created At:') !!}
    <p>{{ $invoice->created_at }}</p>
</div>

<!-- Updated At Field -->
<div class="col-sm-12">
    {!! Form::label('updated_at', 'Updated At:') !!}
    <p>{{ $invoice->updated_at }}</p>
</div>

