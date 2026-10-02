@extends('layouts.admin')
@section('title', 'Môn thể thao')
@section('content')
    <div class="d-flex justify-content-between mb-4">
        <div><h1 class="page-title">Môn thể thao</h1></div>
        <a href="{{ route('admin.sports.create') }}" class="btn btn-admin-primary">Thêm môn</a>
    </div>
    <div class="admin-card">
        <table class="table admin-table">
            <thead><tr><th>Tên</th><th>Slug</th><th></th></tr></thead>
            <tbody>
            @foreach ($sports as $sport)
                <tr>
                    <td>{{ $sport->name }}</td>
                    <td class="text-muted">{{ $sport->slug }}</td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.sports.edit', $sport) }}"><i class="fas fa-pen"></i></a>
                        <form class="d-inline" method="post" action="{{ route('admin.sports.destroy', $sport) }}" onsubmit="return confirm('Xóa môn này?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
