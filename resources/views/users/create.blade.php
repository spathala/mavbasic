@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <div class="pull-right"> <!-- Action Button Section -->
                        @include ('common._action', ['CRUD_Action' => 'Create', 'resource' => 'users'])
                    </div>
                    <div><h4>{{ $heading }}</h4></div>
                </div>
                <div class="panel-body">
                    {!! Form::open(['class' => 'form-horizontal', 'route' => 'users.store', 'onsubmit' => 'return validateOnSave();']) !!}
                    @include('common.errors')
                    @include('common.flash')

                    @include ('users.partial', ['CRUD_Action' => 'Create'])
                    {!! Form::close() !!}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer')
<script>
    $(document).ready(function($) {
        $('select').select2();
    });

    // ToDo: update validations for this view
    function validateOnSave() {
        var rc = true;
        if ($("select#sb_id").val() === "") {
            alert("Please select a Type");
            rc = false;
        } else if ($("input#x_number").val() === "") {
            alert("Please input a X-number");
            rc = false;
        }
        return rc;
    }
</script>
@endsection