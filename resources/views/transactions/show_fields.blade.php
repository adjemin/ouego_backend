<!-- User Id Field -->
<div class="col-sm-12">
    {!! Form::label('user_id', 'User Id:') !!}
    <p>{{ $transaction->user_id }}</p>
</div>

<!-- User Source Field -->
<div class="col-sm-12">
    {!! Form::label('user_source', 'User Source:') !!}
    <p>{{ $transaction->user_source }}</p>
</div>

<!-- Type Field -->
<div class="col-sm-12">
    {!! Form::label('type', 'Type:') !!}
    <p>{{ $transaction->type }}</p>
</div>

<!-- Currency Code Field -->
<div class="col-sm-12">
    {!! Form::label('currency_code', 'Currency Code:') !!}
    <p>{{ $transaction->currency_code }}</p>
</div>

<!-- Amount Field -->
<div class="col-sm-12">
    {!! Form::label('amount', 'Amount:') !!}
    <p>{{ $transaction->amount }}</p>
</div>

<!-- Is In Field -->
<div class="col-sm-12">
    {!! Form::label('is_in', 'Is In:') !!}
    <p>{{ $transaction->is_in }}</p>
</div>

<!-- Order Id Field -->
<div class="col-sm-12">
    {!! Form::label('order_id', 'Order Id:') !!}
    <p>{{ $transaction->order_id }}</p>
</div>

<!-- Created At Field -->
<div class="col-sm-12">
    {!! Form::label('created_at', 'Created At:') !!}
    <p>{{ $transaction->created_at }}</p>
</div>

<!-- Updated At Field -->
<div class="col-sm-12">
    {!! Form::label('updated_at', 'Updated At:') !!}
    <p>{{ $transaction->updated_at }}</p>
</div>

