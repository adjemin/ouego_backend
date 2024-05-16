<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table" id="engin-pictures-table">
            <thead>
            <tr>
                <th>Engin Id</th>
                <th>Url</th>
                <th colspan="3">Action</th>
            </tr>
            </thead>
            <tbody>
            @foreach($enginPictures as $enginPicture)
                <tr>
                    <td>{{ $enginPicture->engin_id }}</td>
                    <td>{{ $enginPicture->url }}</td>
                    <td  style="width: 120px">
                        {!! Form::open(['route' => ['enginPictures.destroy', $enginPicture->id], 'method' => 'delete']) !!}
                        <div class='btn-group'>
                            <a href="{{ route('enginPictures.show', [$enginPicture->id]) }}"
                               class='btn btn-default btn-xs'>
                                <i class="far fa-eye"></i>
                            </a>
                            <a href="{{ route('enginPictures.edit', [$enginPicture->id]) }}"
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
            @include('adminlte-templates::common.paginate', ['records' => $enginPictures])
        </div>
    </div>
</div>
