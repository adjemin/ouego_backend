<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table" id="product-engin-relations-table">
            <thead>
            <tr>
                <th>Product Id</th>
                <th>Type Engin Id</th>
                <th colspan="3">Action</th>
            </tr>
            </thead>
            <tbody>
            @foreach($productEnginRelations as $productEnginRelation)
                <tr>
                    <td>{{ $productEnginRelation->product_id }}</td>
                    <td>{{ $productEnginRelation->type_engin_id }}</td>
                    <td  style="width: 120px">
                        {!! Form::open(['route' => ['productEnginRelations.destroy', $productEnginRelation->id], 'method' => 'delete']) !!}
                        <div class='btn-group'>
                            <a href="{{ route('productEnginRelations.show', [$productEnginRelation->id]) }}"
                               class='btn btn-default btn-xs'>
                                <i class="far fa-eye"></i>
                            </a>
                            <a href="{{ route('productEnginRelations.edit', [$productEnginRelation->id]) }}"
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
            @include('adminlte-templates::common.paginate', ['records' => $productEnginRelations])
        </div>
    </div>
</div>
