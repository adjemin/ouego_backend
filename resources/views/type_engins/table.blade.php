<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table" id="type-engins-table">
            <thead>
            <tr>
                <th>Ability</th>
                <th>Usages</th>
                <th>Name</th>
                <th>Slug</th>
                <th>Models</th>
                <th>Services</th>
                <th colspan="3">Action</th>
            </tr>
            </thead>
            <tbody>
            @foreach($typeEngins as $typeEngin)
                <tr>
                    <td>{{ $typeEngin->ability }}</td>
                    <td>{{ $typeEngin->usages }}</td>
                    <td>{{ $typeEngin->name }}</td>
                    <td>{{ $typeEngin->slug }}</td>
                    <td>{{ $typeEngin->models }}</td>
                    <td>{{ $typeEngin->services }}</td>
                    <td  style="width: 120px">
                        {!! Form::open(['route' => ['typeEngins.destroy', $typeEngin->id], 'method' => 'delete']) !!}
                        <div class='btn-group'>
                            <a href="{{ route('typeEngins.show', [$typeEngin->id]) }}"
                               class='btn btn-default btn-xs'>
                                <i class="far fa-eye"></i>
                            </a>
                            <a href="{{ route('typeEngins.edit', [$typeEngin->id]) }}"
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
            @include('adminlte-templates::common.paginate', ['records' => $typeEngins])
        </div>
    </div>
</div>
