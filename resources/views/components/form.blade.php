@extends($layout)

@section('content')

<script type="text/javascript">
	function sendAddAnotherFormFlag(e){
		//e.preventDefault();
		document.getElementById('sendAnotherFormFlag').value = 'true';
		document.getElementById('form').submit();
	}
</script>

<style>

	@if(isset($style))
	@foreach ($style as $rule )
	{{$rule}}
	@endforeach
	@endif
	</style>

	@php
	@endphp

	<form class="form" id="form" action="{{$form_action}}" method='POST' enctype="multipart/form-data">
	@csrf

	@php
	//dd($boxes);
	@endphp

	@foreach($boxes as $box)
	<div class="{{$box['wrapper-class']}}">
	<div class="box {{$box['class']}}">
	<div class="box-header with-border">
	<h3 class="box-title">{{$box['box-header']}}</h3>

						<!--
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                            <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-remove"></i></button>
                        </div>
                        -->
					</div>

					<div class="box-body">
						<div>
							@foreach($box['form_fields'] as $field)
								{!!$field!!}
							@endforeach
							</div>
							<!-- /.row -->
							</div>
							</div>

							</div>
							@endforeach

							@if($method == 'update')
							<input type="hidden" name="_method" value="PUT">
							@endif

							<div class="col-md-12">
							<button type="submit" class="btn btn-primary float-right">Submit</button>
							@if(isset($add_another_record))
							<span onClick="sendAddAnotherFormFlag()" class="btn btn-primary float-right">Save And Add New</span>
							@endif
							</div>
							<input id='sendAnotherFormFlag' type="hidden" name="submitAnotherOne" />
	</form>
@endsection
