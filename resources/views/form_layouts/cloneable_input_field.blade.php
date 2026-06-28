<style>
    .cloneable_field{
        display: none;
    }

    .cloneable_input_field{
        height: 70px;
    }
</style>


<div class="" style="width:100%; display: table;">
    @foreach($defaults  as $default)
       {!! $default !!}
    @endforeach
</div>

<div class="cloneable_field cloneable_input_field">
    <div class="" style="width:100%; display: table;">
        @foreach($fields as $field)
            {!! $field !!}
        @endforeach
    </div>
</div>

<div class="cloneable_fields_container">
    <div class="cloneable_fields_container_inner">

    </div>
    <div class="col-md-12">
        <button type="button" class="btn btn-danger" id="cloneable_fields_btn">Add Column</button>
    </div>
    <input type="hidden" id="cloneable_fields_index" val="1">
</div>
