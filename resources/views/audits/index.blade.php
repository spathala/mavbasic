@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <div><h4>{{ $heading }}</h4></div>
                    </div>
                    <div class="panel-body">
                        @if (count($audits) > 0)
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped mav-datatable">
                                    <thead> <!-- Table Headings -->
                                    <th>Object ID</th><th>Object Type</th><th>Activity</th><th>User</th><th>Before</th><th>After</th>
                                    <th>Created Date</th>
                                    </thead>
                                    <tbody> <!-- Table Body -->
                                    @foreach ($audits as $current)
                                        <tr>
                                            <td class="table-text"><div><a href="{{ url('/audits/'.$current->id) }}">{{ $current->auditable_id }}</a></div></td>
                                            <td class="table-text"><div>{{ $current->auditable_type }}</div></td>
                                            <td class="table-text"><div>{{ $current->activity }}</div></td>
                                            <td class="table-text"><div>{{ $current->user->name }}</div></td>
                                            <td class="table-text"><div>{{ $current->before }}</div></td>
                                            <td class="table-text" style="width: 100px;"><div>{{ $current->after }}</div></td>
                                            <td class="table-text"><div>{{ $current->created_at }}</div></td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="panel-body"><h4>No Audit Records found</h4></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('footer')
    <style>
        .table td { border: 0px !important; }
        .tooltip-inner { white-space:pre-wrap; max-width: 400px; }
    </style>

    <script>
        $(document).ready(function() {
            $('table.cds-datatable').on( 'draw.dt', function () {
                $('tr').tooltip({html: true, placement: 'auto' });
            } );
            $('tr').tooltip({html: true, placement: 'auto' });
        } );
    </script>
@endsection