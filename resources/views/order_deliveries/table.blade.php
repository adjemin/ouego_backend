<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table" id="order-deliveries-table">
            <thead>
            <tr>
                <th>Order Id</th>
                <th>Location Name</th>
                <th>Location Latitude</th>
                <th>Location Longitude</th>
                <th>Comment</th>
                <th colspan="3">Action</th>
            </tr>
            </thead>
            <tbody>
            @foreach($orderDeliveries as $orderDelivery)
                <tr>
                    <td>{{ $orderDelivery->order_id }}</td>
                    <td>{{ $orderDelivery->location_name }}</td>
                    <td>{{ $orderDelivery->location_latitude }}</td>
                    <td>{{ $orderDelivery->location_longitude }}</td>
                    <td>{{ $orderDelivery->comment }}</td>
                    <td  style="width: 120px">
                        {!! Form::open(['route' => ['orderDeliveries.destroy', $orderDelivery->id], 'method' => 'delete']) !!}
                        <div class='btn-group'>
                            <a href="{{ route('orderDeliveries.show', [$orderDelivery->id]) }}"
                               class='btn btn-default btn-xs'>
                                <i class="far fa-eye"></i>
                            </a>
                            <a href="{{ route('orderDeliveries.edit', [$orderDelivery->id]) }}"
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
            @include('adminlte-templates::common.paginate', ['records' => $orderDeliveries])
        </div>
    </div>
</div>
