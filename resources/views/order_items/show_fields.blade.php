<!-- Order Id Field -->
<div class="col-sm-12">
    {!! Form::label('order_id', 'Order Id:') !!}
    <p>{{ $orderItem->order_id }}</p>
</div>

<!-- Service Slug Field -->
<div class="col-sm-12">
    {!! Form::label('service_slug', 'Service Slug:') !!}
    <p>{{ $orderItem->service_slug }}</p>
</div>

<!-- Meta Data Field -->
<div class="col-sm-12">
    {!! Form::label('meta_data', 'Meta Data:') !!}
    <p>{{ $orderItem->meta_data }}</p>
</div>

<!-- Quantity Field -->
<div class="col-sm-12">
    {!! Form::label('quantity', 'Quantity:') !!}
    <p>{{ $orderItem->quantity }}</p>
</div>

<!-- Quantity Unity Field -->
<div class="col-sm-12">
    {!! Form::label('quantity_unity', 'Quantity Unity:') !!}
    <p>{{ $orderItem->quantity_unity }}</p>
</div>

<!-- Unit Price Field -->
<div class="col-sm-12">
    {!! Form::label('unit_price', 'Unit Price:') !!}
    <p>{{ $orderItem->unit_price }}</p>
</div>

<!-- Total Amount Field -->
<div class="col-sm-12">
    {!! Form::label('total_amount', 'Total Amount:') !!}
    <p>{{ $orderItem->total_amount }}</p>
</div>

<!-- Currency Field -->
<div class="col-sm-12">
    {!! Form::label('currency', 'Currency:') !!}
    <p>{{ $orderItem->currency }}</p>
</div>

<!-- Created At Field -->
<div class="col-sm-12">
    {!! Form::label('created_at', 'Created At:') !!}
    <p>{{ $orderItem->created_at }}</p>
</div>

<!-- Updated At Field -->
<div class="col-sm-12">
    {!! Form::label('updated_at', 'Updated At:') !!}
    <p>{{ $orderItem->updated_at }}</p>
</div>

