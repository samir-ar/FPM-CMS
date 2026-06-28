@extends($layout)

@section('header_resources')
	<style>
		.edit-link{
			margin-right: 10px;
		}
		.edit-link i, .delete-link i{
			font-size: 16px;
		}

		.custom-btns{
			display: table;
			float: right;
		}

	</style>
@endsection

@section('footer_resources')
	<script>
		$(function () {
			var sort = @php echo (isset($sort) ? $sort : 'null') @endphp;

			var options = {};



			if(sort != null)
				options.aaSorting = [[sort, 'desc']];

			else
				options.aaSorting = [];


			var dataTable = $('#example1').DataTable(options);
		});


		$(document).ready(function(){
			$('body').on('click', '.delete-link', function() {
				var action = $(this).attr('id');
				$('#deleteModal').find('form').attr('action', action);
			});
		});
	</script>

	@if(isset($scripts))
		@foreach($scripts as $script)
			<script src="{{url($script)}}"></script>
		@endforeach
	@endif
@endsection

@section('content')
	<div class="row">
		<div class="col-xs-12" style="margin-bottom: 20px;">
			@if(isset($table_btns))
				<div class="table-btns" style="float:right;">
					{!! $table_btns !!}
				</div>
			@endif


		</div>
		<div class="col-xs-12">
			<div class="box">
				<div class="box-header">
					<h3 class="box-title">{{$table_title}}</h3>

					@if(isset($custom_btn))
						<div class="custom-btns">
							{!! $custom_btn !!}
						</div>
					@endif

				</div>
				<!-- /.box-header -->
				<div class="box-body">
					<table id="example1" class="table table-bordered table-striped">
						<thead>
						<tr>
							@foreach($headers as $header)
								<th>{{$header}}</th>
							@endforeach
						</tr>
						</thead>
						<tbody>
						@foreach($rows as $row)
							<tr>
								@foreach($row as $col)
									<td>{!! $col !!}</td>
								@endforeach
							</tr>
						@endforeach
						</tbody>
						<tfoot>
						<tr>
							@foreach($headers as $header)
								<th>{{$header}}</th>
							@endforeach
						</tr>
						</tfoot>
					</table>
				</div>
				<!-- /.box-body -->
			</div>
		</div>

	</div>
@endsection




