<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table" id="carriers-table">
            <thead>
            <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Location Latitude</th>
                <th>Location Longitude</th>
                <th>Is Active</th>
                <th>Products</th>
                <th colspan="3">Action</th>
            </tr>
            </thead>
            <tbody>
            @foreach($carriers as $carrier)
                <tr>
                    <td>{{ $carrier->name }}</td>
                    <td>{{ $carrier->phone }}</td>
                    <td>{{ $carrier->location_latitude }}</td>
                    <td>{{ $carrier->location_longitude }}</td>
                    <td>{{ $carrier->is_active }}</td>
                    <td>{{ $carrier->products }}</td>
                    <td  style="width: 120px">
                        {!! Form::open(['route' => ['carriers.destroy', $carrier->id], 'method' => 'delete']) !!}
                        <div class='btn-group'>
                            <a href="{{ route('carriers.show', [$carrier->id]) }}"
                               class='btn btn-default btn-xs'>
                                <i class="far fa-eye"></i>
                            </a>
                            <a href="{{ route('carriers.edit', [$carrier->id]) }}"
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
            @include('adminlte-templates::common.paginate', ['records' => $carriers])
        </div>
    </div>
</div>
