<!-- Order Id Field -->
<div class="form-group col-sm-6">
    {!! Form::label('order_id', 'Order Id:') !!}
    {!! Form::text('order_id', null, ['class' => 'form-control']) !!}
</div>

<!-- Location Name Field -->
<div class="form-group col-sm-6">
    {!! Form::label('location_name', 'Location Name:') !!}
    {!! Form::text('location_name', null, ['class' => 'form-control']) !!}
</div>

<!-- Location Latitude Field -->
<div class="form-group col-sm-6">
    {!! Form::label('location_latitude', 'Location Latitude:') !!}
    {!! Form::text('location_latitude', null, ['class' => 'form-control']) !!}
</div>

<!-- Location Longitude Field -->
<div class="form-group col-sm-6">
    {!! Form::label('location_longitude', 'Location Longitude:') !!}
    {!! Form::text('location_longitude', null, ['class' => 'form-control']) !!}
</div>

<!-- Comment Field -->
<div class="form-group col-sm-6">
    {!! Form::label('comment', 'Comment:') !!}
    {!! Form::text('comment', null, ['class' => 'form-control']) !!}
</div>