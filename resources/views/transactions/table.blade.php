<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table" id="transactions-table">
            <thead>
            <tr>
                <th>User Id</th>
                <th>User Source</th>
                <th>Type</th>
                <th>Currency Code</th>
                <th>Amount</th>
                <th>Is In</th>
                <th>Order Id</th>
                <th colspan="3">Action</th>
            </tr>
            </thead>
            <tbody>
            @foreach($transactions as $transaction)
                <tr>
                    <td>{{ $transaction->user_id }}</td>
                    <td>{{ $transaction->user_source }}</td>
                    <td>{{ $transaction->type }}</td>
                    <td>{{ $transaction->currency_code }}</td>
                    <td>{{ $transaction->amount }}</td>
                    <td>{{ $transaction->is_in }}</td>
                    <td>{{ $transaction->order_id }}</td>
                    <td  style="width: 120px">
                        {!! Form::open(['route' => ['transactions.destroy', $transaction->id], 'method' => 'delete']) !!}
                        <div class='btn-group'>
                            <a href="{{ route('transactions.show', [$transaction->id]) }}"
                               class='btn btn-default btn-xs'>
                                <i class="far fa-eye"></i>
                            </a>
                            <a href="{{ route('transactions.edit', [$transaction->id]) }}"
                               class='btn btn-default btn-xs'>
                                <i class="far fa-edit"></i>
                            </a>
                            {!! Form::button('<i class="far fa-trash-alt"></i>', ['type' => 'submit', 'class' => 'btn btn-danger btn-xs', 'onclick' => "return confirm('Are you sure?')"]) !!}
                        </div>
                        {!! Form::close() !!}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="card-footer clearfix">
        <div class="float-right">
            @include('adminlte-templates::common.paginate', ['records' => $transactions])
        </div>
    </div>
</div>
