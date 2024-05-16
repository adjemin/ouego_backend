<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table" id="payments-table">
            <thead>
            <tr>
                <th>Invoice Id</th>
                <th>Payment Method Code</th>
                <th>Payment Reference</th>
                <th>Amount</th>
                <th>Currency Code</th>
                <th>User Id</th>
                <th>Status</th>
                <th>Is Waiting</th>
                <th>Is Completed</th>
                <th>Payment Gateway Trans Id</th>
                <th>Payment Gateway Custom</th>
                <th>Payment Gateway Currency</th>
                <th>Payment Gateway Amount</th>
                <th>Payment Gateway Payment Date</th>
                <th>Payment Gateway Error Message</th>
                <th>Payment Gateway Payment Method</th>
                <th>Payment Gateway Buyer Name</th>
                <th>Payment Gateway Buyer Reference</th>
                <th>Payment Gateway Trans Status</th>
                <th>Payment Gateway Designation</th>
                <th colspan="3">Action</th>
            </tr>
            </thead>
            <tbody>
            @foreach($payments as $payment)
                <tr>
                    <td>{{ $payment->invoice_id }}</td>
                    <td>{{ $payment->payment_method_code }}</td>
                    <td>{{ $payment->payment_reference }}</td>
                    <td>{{ $payment->amount }}</td>
                    <td>{{ $payment->currency_code }}</td>
                    <td>{{ $payment->user_id }}</td>
                    <td>{{ $payment->status }}</td>
                    <td>{{ $payment->is_waiting }}</td>
                    <td>{{ $payment->is_completed }}</td>
                    <td>{{ $payment->payment_gateway_trans_id }}</td>
                    <td>{{ $payment->payment_gateway_custom }}</td>
                    <td>{{ $payment->payment_gateway_currency }}</td>
                    <td>{{ $payment->payment_gateway_amount }}</td>
                    <td>{{ $payment->payment_gateway_payment_date }}</td>
                    <td>{{ $payment->payment_gateway_error_message }}</td>
                    <td>{{ $payment->payment_gateway_payment_method }}</td>
                    <td>{{ $payment->payment_gateway_buyer_name }}</td>
                    <td>{{ $payment->payment_gateway_buyer_reference }}</td>
                    <td>{{ $payment->payment_gateway_trans_status }}</td>
                    <td>{{ $payment->payment_gateway_designation }}</td>
                    <td  style="width: 120px">
                        {!! Form::open(['route' => ['payments.destroy', $payment->id], 'method' => 'delete']) !!}
                        <div class='btn-group'>
                            <a href="{{ route('payments.show', [$payment->id]) }}"
                               class='btn btn-default btn-xs'>
                                <i class="far fa-eye"></i>
                            </a>
                            <a href="{{ route('payments.edit', [$payment->id]) }}"
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
            @include('adminlte-templates::common.paginate', ['records' => $payments])
        </div>
    </div>
</div>
