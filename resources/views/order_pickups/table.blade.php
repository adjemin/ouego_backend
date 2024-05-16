<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table" id="order-pickups-table">
            <thead>
            <tr>
                <th>Order Id</th>
                <th>Location Name</th>
                <th>Location Latitude</th>
                <th>Location Longitude</th>
                <th colspan="3">Action</th>
            </tr>
            </thead>
            <tbody>
            @foreach($orderPickups as $orderPickup)
                <tr>
                    <td>{{ $orderPickup->order_id }}</td>
                    <td>{{ $orderPickup->location_name }}</td>
                    <td>{{ $orderPickup->location_latitude }}</td>
                    <td>{{ $orderPickup->location_longitude }}</td>
                    <td  style="width: 120px">
                        {!! Form::open(['route' => ['orderPickups.destroy', $orderPickup->id], 'method' => 'delete']) !!}
                        <div class='btn-group'>
                            <a href="{{ route('orderPickups.show', [$orderPickup->id]) }}"
                               class='btn btn-default btn-xs'>
                                <i class="far fa-eye"></i>
                            </a>
                            <a href="{{ route('orderPickups.edit', [$orderPickup->id]) }}"
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
            @include('adminlte-templates::common.paginate', ['records' => $orderPickups])
        </div>
    </div>
</div>
