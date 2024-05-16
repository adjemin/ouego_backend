<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table" id="customer-devices-table">
            <thead>
            <tr>
                <th>Customer Id</th>
                <th>Firebase Id</th>
                <th colspan="3">Action</th>
            </tr>
            </thead>
            <tbody>
            @foreach($customerDevices as $customerDevice)
                <tr>
                    <td>{{ $customerDevice->customer_id }}</td>
                    <td>{{ $customerDevice->firebase_id }}</td>
                    <td  style="width: 120px">
                        {!! Form::open(['route' => ['customerDevices.destroy', $customerDevice->id], 'method' => 'delete']) !!}
                        <div class='btn-group'>
                            <a href="{{ route('customerDevices.show', [$customerDevice->id]) }}"
                               class='btn btn-default btn-xs'>
                                <i class="far fa-eye"></i>
                            </a>
                            <a href="{{ route('customerDevices.edit', [$customerDevice->id]) }}"
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
            @include('adminlte-templates::common.paginate', ['records' => $customerDevices])
        </div>
    </div>
</div>
