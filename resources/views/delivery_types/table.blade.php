<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table" id="delivery-types-table">
            <thead>
            <tr>
                <th>Name</th>
                <th>Icon</th>
                <th>Slug</th>
                <th>Is Active</th>
                <th colspan="3">Action</th>
            </tr>
            </thead>
            <tbody>
            @foreach($deliveryTypes as $deliveryType)
                <tr>
                    <td>{{ $deliveryType->name }}</td>
                    <td>{{ $deliveryType->icon }}</td>
                    <td>{{ $deliveryType->slug }}</td>
                    <td>{{ $deliveryType->is_active }}</td>
                    <td  style="width: 120px">
                        {!! Form::open(['route' => ['deliveryTypes.destroy', $deliveryType->id], 'method' => 'delete']) !!}
                        <div class='btn-group'>
                            <a href="{{ route('deliveryTypes.show', [$deliveryType->id]) }}"
                               class='btn btn-default btn-xs'>
                                <i class="far fa-eye"></i>
                            </a>
                            <a href="{{ route('deliveryTypes.edit', [$deliveryType->id]) }}"
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
            @include('adminlte-templates::common.paginate', ['records' => $deliveryTypes])
        </div>
    </div>
</div>
