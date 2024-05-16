<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table" id="customers-table">
            <thead>
            <tr>
                <th>First Name</th>
                <th>Last Time</th>
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
                <th>Last Location Name</th>
                <th>Country Code</th>
                <th>Is Phone Verified</th>
                <th>Is Email Verified</th>
                <th>Email</th>
                <th colspan="3">Action</th>
            </tr>
            </thead>
            <tbody>
            @foreach($customers as $customer)
                <tr>
                    <td>{{ $customer->first_name }}</td>
                    <td>{{ $customer->last_time }}</td>
                    <td>{{ $customer->name }}</td>
                    <td>{{ $customer->dialing_code }}</td>
                    <td>{{ $customer->phone_number }}</td>
                    <td>{{ $customer->phone }}</td>
                    <td>{{ $customer->photo_url }}</td>
                    <td>{{ $customer->is_active }}</td>
                    <td>{{ $customer->current_balance }}</td>
                    <td>{{ $customer->old_balance }}</td>
                    <td>{{ $customer->last_location_latitude }}</td>
                    <td>{{ $customer->last_location_longitude }}</td>
                    <td>{{ $customer->last_location_name }}</td>
                    <td>{{ $customer->country_code }}</td>
                    <td>{{ $customer->is_phone_verified }}</td>
                    <td>{{ $customer->is_email_verified }}</td>
                    <td>{{ $customer->email }}</td>
                    <td  style="width: 120px">
                        {!! Form::open(['route' => ['customers.destroy', $customer->id], 'method' => 'delete']) !!}
                        <div class='btn-group'>
                            <a href="{{ route('customers.show', [$customer->id]) }}"
                               class='btn btn-default btn-xs'>
                                <i class="far fa-eye"></i>
                            </a>
                            <a href="{{ route('customers.edit', [$customer->id]) }}"
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
            @include('adminlte-templates::common.paginate', ['records' => $customers])
        </div>
    </div>
</div>
