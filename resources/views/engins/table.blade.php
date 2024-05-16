<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table" id="engins-table">
            <thead>
            <tr>
                <th>Immatriculation</th>
                <th>Numero Carte Grise</th>
                <th>Brand</th>
                <th>Serie</th>
                <th>Type Engin</th>
                <th>Carrosserie</th>
                <th>Color</th>
                <th>Nombre Essieux</th>
                <th>Nombre Roues</th>
                <th>Oil</th>
                <th>Usages</th>
                <th>Ability Tonne</th>
                <th>Ptac Tonne</th>
                <th>Poids Vide</th>
                <th>Charge Utile</th>
                <th>Puissance Fiscale</th>
                <th>Cylindree</th>
                <th>Date Mise En Production</th>
                <th>Date Edition</th>
                <th>Nom Proprietaire</th>
                <th colspan="3">Action</th>
            </tr>
            </thead>
            <tbody>
            @foreach($engins as $engin)
                <tr>
                    <td>{{ $engin->immatriculation }}</td>
                    <td>{{ $engin->numero_carte_grise }}</td>
                    <td>{{ $engin->brand }}</td>
                    <td>{{ $engin->serie }}</td>
                    <td>{{ $engin->type_engin }}</td>
                    <td>{{ $engin->carrosserie }}</td>
                    <td>{{ $engin->color }}</td>
                    <td>{{ $engin->nombre_essieux }}</td>
                    <td>{{ $engin->nombre_roues }}</td>
                    <td>{{ $engin->oil }}</td>
                    <td>{{ $engin->usages }}</td>
                    <td>{{ $engin->ability_tonne }}</td>
                    <td>{{ $engin->ptac_tonne }}</td>
                    <td>{{ $engin->poids_vide }}</td>
                    <td>{{ $engin->charge_utile }}</td>
                    <td>{{ $engin->puissance_fiscale }}</td>
                    <td>{{ $engin->cylindree }}</td>
                    <td>{{ $engin->date_mise_en_production }}</td>
                    <td>{{ $engin->date_edition }}</td>
                    <td>{{ $engin->nom_proprietaire }}</td>
                    <td  style="width: 120px">
                        {!! Form::open(['route' => ['engins.destroy', $engin->id], 'method' => 'delete']) !!}
                        <div class='btn-group'>
                            <a href="{{ route('engins.show', [$engin->id]) }}"
                               class='btn btn-default btn-xs'>
                                <i class="far fa-eye"></i>
                            </a>
                            <a href="{{ route('engins.edit', [$engin->id]) }}"
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
            @include('adminlte-templates::common.paginate', ['records' => $engins])
        </div>
    </div>
</div>
