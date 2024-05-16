<!-- Name Field -->
<div class="form-group col-sm-6">
    {!! Form::label('name', 'Name:') !!}
    {!! Form::text('name', null, ['class' => 'form-control']) !!}
</div>

<!-- Phone Field -->
<div class="form-group col-sm-6">
    {!! Form::label('phone', 'Phone:') !!}
    {!! Form::text('phone', null, ['class' => 'form-control']) !!}
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

<!-- Is Active Field -->
<div class="form-group col-sm-6">
    {!! Form::label('is_active', 'Is Active:') !!}
    {!! Form::text('is_active', null, ['class' => 'form-control']) !!}
</div>

<!-- Products Field -->
<div class="form-group col-sm-6">
    {!! Form::label('products', 'Products:') !!}
    {!! Form::text('products', null, ['class' => 'form-control']) !!}
</div>