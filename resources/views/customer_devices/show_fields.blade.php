<!-- Customer Id Field -->
<div class="col-sm-12">
    {!! Form::label('customer_id', 'Customer Id:') !!}
    <p>{{ $customerDevice->customer_id }}</p>
</div>

<!-- Firebase Id Field -->
<div class="col-sm-12">
    {!! Form::label('firebase_id', 'Firebase Id:') !!}
    <p>{{ $customerDevice->firebase_id }}</p>
</div>

<!-- Created At Field -->
<div class="col-sm-12">
    {!! Form::label('created_at', 'Created At:') !!}
    <p>{{ $customerDevice->created_at }}</p>
</div>

<!-- Updated At Field -->
<div class="col-sm-12">
    {!! Form::label('updated_at', 'Updated At:') !!}
    <p>{{ $customerDevice->updated_at }}</p>
</div>

