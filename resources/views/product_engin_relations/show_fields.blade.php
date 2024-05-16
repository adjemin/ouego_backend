<!-- Product Id Field -->
<div class="col-sm-12">
    {!! Form::label('product_id', 'Product Id:') !!}
    <p>{{ $productEnginRelation->product_id }}</p>
</div>

<!-- Type Engin Id Field -->
<div class="col-sm-12">
    {!! Form::label('type_engin_id', 'Type Engin Id:') !!}
    <p>{{ $productEnginRelation->type_engin_id }}</p>
</div>

<!-- Created At Field -->
<div class="col-sm-12">
    {!! Form::label('created_at', 'Created At:') !!}
    <p>{{ $productEnginRelation->created_at }}</p>
</div>

<!-- Updated At Field -->
<div class="col-sm-12">
    {!! Form::label('updated_at', 'Updated At:') !!}
    <p>{{ $productEnginRelation->updated_at }}</p>
</div>

