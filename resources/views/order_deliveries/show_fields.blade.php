<!-- Order Id Field -->
<div class="col-sm-12">
    {!! Form::label('order_id', 'Order Id:') !!}
    <p>{{ $orderDelivery->order_id }}</p>
</div>

<!-- Location Name Field -->
<div class="col-sm-12">
    {!! Form::label('location_name', 'Location Name:') !!}
    <p>{{ $orderDelivery->location_name }}</p>
</div>

<!-- Location Latitude Field -->
<div class="col-sm-12">
    {!! Form::label('location_latitude', 'Location Latitude:') !!}
    <p>{{ $orderDelivery->location_latitude }}</p>
</div>

<!-- Location Longitude Field -->
<div class="col-sm-12">
    {!! Form::label('location_longitude', 'Location Longitude:') !!}
    <p>{{ $orderDelivery->location_longitude }}</p>
</div>

<!-- Comment Field -->
<div class="col-sm-12">
    {!! Form::label('comment', 'Comment:') !!}
    <p>{{ $orderDelivery->comment }}</p>
</div>

<!-- Created At Field -->
<div class="col-sm-12">
    {!! Form::label('created_at', 'Created At:') !!}
    <p>{{ $orderDelivery->created_at }}</p>
</div>

<!-- Updated At Field -->
<div class="col-sm-12">
    {!! Form::label('updated_at', 'Updated At:') !!}
    <p>{{ $orderDelivery->updated_at }}</p>
</div>

