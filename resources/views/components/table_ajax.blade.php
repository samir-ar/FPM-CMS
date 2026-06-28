@extends($layout)

@section('header_resources')

    <style>
        	th{
		white-space: nowrap;
	}
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

        .last-elelction-controller{
                width: 200px;
                color: white;
        padding: 10px;
        background-color: #3c8dbc;
        align-items: center;
        display: inline-flex;
        justify-content: space-between;
        }

        @if(isset($districts))
        .dropbtn {
        background-color: #04AA6D;
        color: white;
        padding: 16px;
        font-size: 16px;
        border: none;
        cursor: pointer;
        }

        .dropbtn:hover, .dropbtn:focus {
        background-color: #3e8e41;
        }

        #myInput {
        box-sizing: border-box;
        background-image: url('searchicon.png');
        background-position: 14px 12px;
        background-repeat: no-repeat;
        font-size: 16px;
        padding: 14px 20px 12px 45px;
        border: none;
        border-bottom: 1px solid #ddd;
        }

        #myInput:focus {outline: 3px solid #ddd;}

        .dropdown {
        position: relative;
        display: inline-block;
        }

        .dropdown-content {
        display: none;
        position: absolute;
        background-color: #f6f6f6;
        min-width: 230px;
        overflow: auto;
        border: 1px solid #ddd;
        z-index: 1;
        }

        .dropdown-content a {
        color: black;
        padding: 12px 16px;
        text-decoration: none;
        display: block;
        }

        .dropdown a:hover {background-color: #ddd;}

        .show {display: block;}

 

        @endif
    </style>
@endsection

@section('footer_resources')
    <script>

        var columns = '{!! $columns !!}';
        console.log('{{$action}}');
        $(function () {
            var dataTable = $('#example1').DataTable({
                processing: true,
                serverSide: true,
                
                @if(Request::get('district'))
                    ajax: "{{ $action.'?district='.Request::get('district')}}",
                @elseif(Request::get('type'))
                    ajax: "{{ $action.'?type='.Request::get('type')}}",
                @endif

                columns: JSON.parse(columns),
                order: [[ 0, "desc" ]],
            });
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
        <div class="col-xs-12">
            <div class="box" >
                <div class="box-header">
                    <h3 class="box-title">{{$table_title}}</h3>

                    @if (isset($generateInternalElectionReport))
                        <a href='{{route("admin.internal-election.export",request('id'))}}' role="button" class="btn btn-primary">Export</a>
                    @endif


                    @if (isset($showPublishInternalElectionButton))
                        <div class='last-elelction-controller'> 
                            <b>Last Election</b>
                            @if ($row)
                                <button  id="{{$row->id}}" class="publish btn {{ (($row->is_active)?'btn-success':'')}}"> publish </button>
                                @endif
                                </div>
                                <a class="btn btn-danger" href="{{ route('admin.internal-election-votes.reset') }}"> DELETE ALL VOTES </a>
                    @endif

                    @if(isset($districts))
                        <div class="dropdown">
                            <button onclick="toggleDropdown()" class=" btn btn-success">Districts <span class="fa fa-angle-down"></span></button>
                            
                            <div id="myDropdown" class="dropdown-content">
                                <input type="text" placeholder="Search.." id="myInput" onkeyup="filterFunction()">
                                <a href="?all">الكل</a>
                                @foreach($districts as $district)
                                    <a href="?district={{$district->name}}">{{$district->name}}</a>
                                @endforeach
                            </div>
                        </div>
                
                        <script>
                            function toggleDropdown() {
                            document.getElementById("myDropdown").classList.toggle("show");
                            }
                
                            function filterFunction() {
                            var input, filter, ul, li, a, i;
                            input = document.getElementById("myInput");
                            filter = input.value.toUpperCase();
                            div = document.getElementById("myDropdown");
                            a = div.getElementsByTagName("a");
                            for (i = 0; i < a.length; i++) {
                                txtValue = a[i].textContent || a[i].innerText;
                                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                                a[i].style.display = "";
                                } else {
                                a[i].style.display = "none";
                                }
                            }
                            }
                        </script>
        @endif
                    @if(isset($custom_btn))
                        <div class="custom-btns">
                            {!! $custom_btn !!}
                        </div>
                    @endif
                    @if(isset($custom_btn1))
                        <div class="custom-btns">
                            {!! $custom_btn1 !!}
                        </div>
                    @endif
                </div>
                <!-- /.box-header -->
                <div class="box-body" style="overflow-x: scroll;">
                    <table id="example1" class="table table-bordered table-striped" >
                        <thead>
                        <tr>
                            @foreach($headers as $header)
                                <th>{{$header}}</th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody>

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




