<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table" id="orders-table">
            <thead>
            <tr>
                <th>Customer Id</th>
                <th>Driver Id</th>
                <th>Services</th>
                <th>Status</th>
                <th>Comment</th>
                <th>Order Date</th>
                <th>Is Started</th>
                <th>Is Running</th>
                <th>Is Waiting</th>
                <th>Is Completed</th>
                <th>Completion Time</th>
                <th>Start Time</th>
                <th>Acceptation Time</th>
                <th>Expected Arrival At</th>
                <th>Rating Id</th>
                <th>Rating</th>
                <th>Rating Note</th>
                <th>Order Price</th>
                <th>Currency Code</th>
                <th>Payment Method Code</th>
                <th>Delivery Type Code</th>
                <th>Is Location</th>
                <th>Is Product</th>
                <th>Is Ride</th>
                <th colspan="3">Action</th>
            </tr>
            </thead>
            <tbody>
            @foreach($orders as $order)
                <tr>
                    <td>{{ $order->customer_id }}</td>
                    <td>{{ $order->driver_id }}</td>
                    <td>{{ $order->services }}</td>
                    <td>{{ $order->status }}</td>
                    <td>{{ $order->comment }}</td>
                    <td>{{ $order->order_date }}</td>
                    <td>{{ $order->is_started }}</td>
                    <td>{{ $order->is_running }}</td>
                    <td>{{ $order->is_waiting }}</td>
                    <td>{{ $order->is_completed }}</td>
                    <td>{{ $order->completion_time }}</td>
                    <td>{{ $order->start_time }}</td>
                    <td>{{ $order->acceptation_time }}</td>
                    <td>{{ $order->expected_arrival_at }}</td>
                    <td>{{ $order->rating_id }}</td>
                    <td>{{ $order->rating }}</td>
                    <td>{{ $order->rating_note }}</td>
                    <td>{{ $order->order_price }}</td>
                    <td>{{ $order->currency_code }}</td>
                    <td>{{ $order->payment_method_code }}</td>
                    <td>{{ $order->delivery_type_code }}</td>
                    <td>{{ $order->is_location }}</td>
                    <td>{{ $order->is_product }}</td>
                    <td>{{ $order->is_ride }}</td>
                    <td  style="width: 120px">
                        {!! Form::open(['route' => ['orders.destroy', $order->id], 'method' => 'delete']) !!}
                        <div class='btn-group'>
                            <a href="{{ route('orders.show', [$order->id]) }}"
                               class='btn btn-default btn-xs'>
                                <i class="far fa-eye"></i>
                            </a>
                            <a href="{{ route('orders.edit', [$order->id]) }}"
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
            @include('adminlte-templates::common.paginate', ['records' => $orders])
        </div>
    </div>
</div>
