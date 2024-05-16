<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table" id="drivers-table">
            <thead>
            <tr>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Name</th>
                <th>Dialing Code</th>
                <th>Phone Number</th>
                <th>Phone</th>
                <th>Photo Url</th>
                <th>Is Active</th>
                <th>Current Balance</th>
                <th>Old Balance</th>
                <th>Last Location Latitude</th>
                <th>Last Location Longitude</th>
                <th>Services</th>
                <th>Driver License Docs</th>
                <th colspan="3">Action</th>
            </tr>
            </thead>
            <tbody>
            @foreach($drivers as $driver)
                <tr>
                    <td>{{ $driver->first_name }}</td>
                    <td>{{ $driver->last_name }}</td>
                    <td>{{ $driver->name }}</td>
                    <td>{{ $driver->dialing_code }}</td>
                    <td>{{ $driver->phone_number }}</td>
                    <td>{{ $driver->phone }}</td>
                    <td>{{ $driver->photo_url }}</td>
                    <td>{{ $driver->is_active }}</td>
                    <td>{{ $driver->current_balance }}</td>
                    <td>{{ $driver->old_balance }}</td>
                    <td>{{ $driver->last_location_latitude }}</td>
                    <td>{{ $driver->last_location_longitude }}</td>
                    <td>{{ $driver->services }}</td>
                    <td>{{ $driver->driver_license_docs }}</td>
                    <td  style="width: 120px">
                        {!! Form::open(['route' => ['drivers.destroy', $driver->id], 'method' => 'delete']) !!}
                        <div class='btn-group'>
                            <a href="{{ route('drivers.show', [$driver->id]) }}"
                               class='btn btn-default btn-xs'>
                                <i class="far fa-eye"></i>
                            </a>
                            <a href="{{ route('drivers.edit', [$driver->id]) }}"
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
            @include('adminlte-templates::common.paginate', ['records' => $drivers])
        </div>
    </div>
</div>
