<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table" id="product-types-table">
            <thead>
            <tr>
                <th>Product Id</th>
                <th>Name</th>
                <th>Slug</th>
                <th>Description</th>
                <th colspan="3">Action</th>
            </tr>
            </thead>
            <tbody>
            @foreach($productTypes as $productType)
                <tr>
                    <td>{{ $productType->product_id }}</td>
                    <td>{{ $productType->name }}</td>
                    <td>{{ $productType->slug }}</td>
                    <td>{{ $productType->description }}</td>
                    <td  style="width: 120px">
                        {!! Form::open(['route' => ['productTypes.destroy', $productType->id], 'method' => 'delete']) !!}
                        <div class='btn-group'>
                            <a href="{{ route('productTypes.show', [$productType->id]) }}"
                               class='btn btn-default btn-xs'>
                                <i class="far fa-eye"></i>
                            </a>
                            <a href="{{ route('productTypes.edit', [$productType->id]) }}"
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
            @include('adminlte-templates::common.paginate', ['records' => $productTypes])
        </div>
    </div>
</div>
