<!-- Name Field -->
<div class="col-sm-12">
    {!! Form::label('name', 'Name:') !!}
    <p>{{ $carrier->name }}</p>
</div>

<!-- Phone Field -->
<div class="col-sm-12">
    {!! Form::label('phone', 'Phone:') !!}
    <p>{{ $carrier->phone }}</p>
</div>

<!-- Location Latitude Field -->
<div class="col-sm-12">
    {!! Form::label('location_latitude', 'Location Latitude:') !!}
    <p>{{ $carrier->location_latitude }}</p>
</div>

<!-- Location Longitude Field -->
<div class="col-sm-12">
    {!! Form::label('location_longitude', 'Location Longitude:') !!}
    <p>{{ $carrier->location_longitude }}</p>
</div>

<!-- Is Active Field -->
<div class="col-sm-12">
    {!! Form::label('is_active', 'Is Active:') !!}
    <p>{{ $carrier->is_active }}</p>
</div>

<!-- Products Field -->
<div class="col-sm-12">
    {!! Form::label('products', 'Products:') !!}
    <p>{{ $carrier->products }}</p>
</div>

<!-- Created At Field -->
<div class="col-sm-12">
    {!! Form::label('created_at', 'Created At:') !!}
    <p>{{ $carrier->created_at }}</p>
</div>

<!-- Updated At Field -->
<div class="col-sm-12">
    {!! Form::label('updated_at', 'Updated At:') !!}
    <p>{{ $carrier->updated_at }}</p>
</div>

