<!-- Driver Id Field -->
<div class="col-sm-12">
    {!! Form::label('driver_id', 'Driver Id:') !!}
    <p>{{ $orderInvitation->driver_id }}</p>
</div>

<!-- Order Id Field -->
<div class="col-sm-12">
    {!! Form::label('order_id', 'Order Id:') !!}
    <p>{{ $orderInvitation->order_id }}</p>
</div>

<!-- Is Waiting Acceptation Field -->
<div class="col-sm-12">
    {!! Form::label('is_waiting_acceptation', 'Is Waiting Acceptation:') !!}
    <p>{{ $orderInvitation->is_waiting_acceptation }}</p>
</div>

<!-- Acceptation Time Field -->
<div class="col-sm-12">
    {!! Form::label('acceptation_time', 'Acceptation Time:') !!}
    <p>{{ $orderInvitation->acceptation_time }}</p>
</div>

<!-- Rejection Time Field -->
<div class="col-sm-12">
    {!! Form::label('rejection_time', 'Rejection Time:') !!}
    <p>{{ $orderInvitation->rejection_time }}</p>
</div>

<!-- Latitude Field -->
<div class="col-sm-12">
    {!! Form::label('latitude', 'Latitude:') !!}
    <p>{{ $orderInvitation->latitude }}</p>
</div>

<!-- Longitude Field -->
<div class="col-sm-12">
    {!! Form::label('longitude', 'Longitude:') !!}
    <p>{{ $orderInvitation->longitude }}</p>
</div>

<!-- Created At Field -->
<div class="col-sm-12">
    {!! Form::label('created_at', 'Created At:') !!}
    <p>{{ $orderInvitation->created_at }}</p>
</div>

<!-- Updated At Field -->
<div class="col-sm-12">
    {!! Form::label('updated_at', 'Updated At:') !!}
    <p>{{ $orderInvitation->updated_at }}</p>
</div>

