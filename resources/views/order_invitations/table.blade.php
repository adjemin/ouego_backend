<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table" id="order-invitations-table">
            <thead>
            <tr>
                <th>Driver Id</th>
                <th>Order Id</th>
                <th>Is Waiting Acceptation</th>
                <th>Acceptation Time</th>
                <th>Rejection Time</th>
                <th>Latitude</th>
                <th>Longitude</th>
                <th colspan="3">Action</th>
            </tr>
            </thead>
            <tbody>
            @foreach($orderInvitations as $orderInvitation)
                <tr>
                    <td>{{ $orderInvitation->driver_id }}</td>
                    <td>{{ $orderInvitation->order_id }}</td>
                    <td>{{ $orderInvitation->is_waiting_acceptation }}</td>
                    <td>{{ $orderInvitation->acceptation_time }}</td>
                    <td>{{ $orderInvitation->rejection_time }}</td>
                    <td>{{ $orderInvitation->latitude }}</td>
                    <td>{{ $orderInvitation->longitude }}</td>
                    <td  style="width: 120px">
                        {!! Form::open(['route' => ['orderInvitations.destroy', $orderInvitation->id], 'method' => 'delete']) !!}
                        <div class='btn-group'>
                            <a href="{{ route('orderInvitations.show', [$orderInvitation->id]) }}"
                               class='btn btn-default btn-xs'>
                                <i class="far fa-eye"></i>
                            </a>
                            <a href="{{ route('orderInvitations.edit', [$orderInvitation->id]) }}"
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
            @include('adminlte-templates::common.paginate', ['records' => $orderInvitations])
        </div>
    </div>
</div>
