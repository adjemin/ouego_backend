<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table" id="services-table">
            <thead>
            <tr>
                <th>Name</th>
                <th>Image</th>
                <th>Description</th>
                <th>Is Active</th>
                <th>Slug</th>
                <th colspan="3">Action</th>
            </tr>
            </thead>
            <tbody>
            @foreach($services as $service)
                <tr>
                    <td>{{ $service->name }}</td>
                    <td>{{ $service->image }}</td>
                    <td>{{ $service->description }}</td>
                    <td>{{ $service->is_active }}</td>
                    <td>{{ $service->slug }}</td>
                    <td  style="width: 120px">
                        {!! Form::open(['route' => ['services.destroy', $service->id], 'method' => 'delete']) !!}
                        <div class='btn-group'>
                            <a href="{{ route('services.show', [$service->id]) }}"
                               class='btn btn-default btn-xs'>
                                <i class="far fa-eye"></i>
                            </a>
                            <a href="{{ route('services.edit', [$service->id]) }}"
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
            @include('adminlte-templates::common.paginate', ['records' => $services])
        </div>
    </div>
</div>
