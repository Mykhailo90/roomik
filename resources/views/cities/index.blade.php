@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-lg-12 margin-tb">
            <div class="pull-left">
                <h2>Cities</h2>
            </div>
            <div class="pull-right">
                <a class="btn btn-success" href="{{ route('cities.create') }}"> Create New City</a>
            </div>
        </div>
    </div>

    @if ($message = Session::get('success'))
        <div class="alert alert-success">
            <p>{{ $message }}</p>
        </div>
    @endif

    <table class="table table-bordered">
        <tr>
            <th>Id</th>
            <th>Country</th>
            <th>Region</th>
            <th>City</th>

            <th width="280px">Action</th>
        </tr>
        @foreach ($cities as $city)
            <tr>
                <td>{{ ++$i }}</td>
                <td>{{ $city->country }}</td>
                <td>{{ $city->region }}</td>
                <td>{{ $city->city }}</td>
                <td>
                    <form action="{{ route('cities.destroy',$city->id) }}" method="POST">

                        <a class="btn btn-info" href="{{ route('cities.show',$city->id) }}">Show</a>

                        <a class="btn btn-primary" href="{{ route('cities.edit',$city->id) }}">Edit</a>

                        @csrf
                        @method('DELETE')

                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
        @endforeach
    </table>

    {!! $cities->links() !!}

@endsection