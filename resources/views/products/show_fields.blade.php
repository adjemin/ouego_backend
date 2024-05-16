<!-- Name Field -->
<div class="col-sm-12">
    {!! Form::label('name', 'Name:') !!}
    <p>{{ $product->name }}</p>
</div>

<!-- Slug Field -->
<div class="col-sm-12">
    {!! Form::label('slug', 'Slug:') !!}
    <p>{{ $product->slug }}</p>
</div>

<!-- Price Field -->
<div class="col-sm-12">
    {!! Form::label('price', 'Price:') !!}
    <p>{{ $product->price }}</p>
</div>

<!-- Per Field -->
<div class="col-sm-12">
    {!! Form::label('per', 'Per:') !!}
    <p>{{ $product->per }}</p>
</div>

<!-- Pricing Title Field -->
<div class="col-sm-12">
    {!! Form::label('pricing_title', 'Pricing Title:') !!}
    <p>{{ $product->pricing_title }}</p>
</div>

<!-- Description Field -->
<div class="col-sm-12">
    {!! Form::label('description', 'Description:') !!}
    <p>{{ $product->description }}</p>
</div>

<!-- Color Field -->
<div class="col-sm-12">
    {!! Form::label('color', 'Color:') !!}
    <p>{{ $product->color }}</p>
</div>

<!-- Icon Field -->
<div class="col-sm-12">
    {!! Form::label('icon', 'Icon:') !!}
    <p>{{ $product->icon }}</p>
</div>

<!-- Product Types Field -->
<div class="col-sm-12">
    {!! Form::label('product_types', 'Product Types:') !!}
    <p>{{ $product->product_types }}</p>
</div>

<!-- Currency Code Field -->
<div class="col-sm-12">
    {!! Form::label('currency_code', 'Currency Code:') !!}
    <p>{{ $product->currency_code }}</p>
</div>

<!-- Tonne Options Field -->
<div class="col-sm-12">
    {!! Form::label('tonne_options', 'Tonne Options:') !!}
    <p>{{ $product->tonne_options }}</p>
</div>

<!-- Created At Field -->
<div class="col-sm-12">
    {!! Form::label('created_at', 'Created At:') !!}
    <p>{{ $product->created_at }}</p>
</div>

<!-- Updated At Field -->
<div class="col-sm-12">
    {!! Form::label('updated_at', 'Updated At:') !!}
    <p>{{ $product->updated_at }}</p>
</div>

