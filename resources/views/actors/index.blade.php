@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-lg-12 margin-tb">
            <div class="pull-left">
                <h2>Actors</h2>
            </div>
            <div class="pull-right">
                <a class="btn btn-success" href="{{ route('actors.create') }}"> Create New Actor</a>
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
            <th>Name</th>
            <th>Email</th>
            <th width="280px">Action</th>
        </tr>
        @foreach ($actors as $actor)
            <tr>
                <td>{{ ++$i }}</td>
                <td>{{ $actor->firstName }}</td>
                <td>{{ $actor->email }}</td>
                <td>
                    <form action="{{ route('actors.destroy',$actor->id) }}" method="POST">

                        <a class="btn btn-info" href="{{ route('actors.show',$actor->id) }}">Show</a>

                        <a class="btn btn-primary" href="{{ route('actors.edit',$actor->id) }}">Edit</a>

                        @csrf
                        @method('DELETE')

                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
        @endforeach
    </table>

    {!! $actors->links() !!}

@endsection