@extends('layouts.admin')
@section('title', 'Tồn kho')
@section('content')
    @php
        $na = '—';
        $rentalCols = 3 + ($showInspecting ? 1 : 0) + ($showMaintenance ? 1 : 0);
        $saleCols = 3;
        $colspan = 3 + $rentalCols + $saleCols;
    @endphp
    <h1 class="page-title">Tồn kho</h1>
    <p class="page-subtitle mb-4">Mỗi dòng là một phân loại (SKU). Cột <strong>Thuê</strong> đếm từng món; cột <strong>Bán</strong> là số lượng — hai nhóm độc lập, bán không làm giảm món thuê. Dấu — nghĩa là hình thức đó không dùng.</p>

    <div class="admin-card p-4 mb-4">
        <h5>Cập nhật số lượng</h5>
        <p class="small text-muted mb-3">SKU <strong>chỉ bán</strong>: điền tồn bán. <strong>Chỉ thuê</strong>: điền số món thuê. <strong>Bán và thuê</strong>: điền cả hai — không dùng chung một số.</p>
        <form class="row g-2 align-items-end" method="post" action="{{ route('admin.inventory.stocks') }}">
            @csrf
            <div class="col-md-4">
                <label class="form-label">Phân loại</label>
                <select name="product_variant_id" class="form-select" required @disabled($rows->isEmpty())>
                    @foreach ($rows as $row)
                        <option value="{{ $row['variant']->id }}">{{ $row['variant']->sku }} — {{ $row['variant']->product?->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tồn bán</label>
                <input type="number" min="0" name="quantity_on_hand" class="form-control" placeholder="Bán">
            </div>
            <div class="col-md-2">
                <label class="form-label">Số món thuê</label>
                <input type="number" min="0" name="rental_item_count" class="form-control" placeholder="Thuê">
            </div>
            <div class="col-md-2"><button class="btn btn-success" @disabled($rows->isEmpty())>Lưu số lượng</button></div>
        </form>
    </div>

    <div class="admin-card">
        <div class="table-responsive">
            <table class="table admin-table mb-0">
                <thead>
                    <tr>
                        <th rowspan="2" class="align-middle">Mã SKU</th>
                        <th rowspan="2" class="align-middle">Sản phẩm</th>
                        <th rowspan="2" class="align-middle">Hình thức</th>
                        <th class="text-center inventory-group" colspan="{{ $rentalCols }}">Thuê</th>
                        <th class="text-center inventory-group" colspan="{{ $saleCols }}">Bán</th>
                    </tr>
                    <tr>
                        <th class="text-end">Tổng món</th>
                        <th class="text-end">Sẵn sàng</th>
                        <th class="text-end">Đang thuê</th>
                        @if ($showInspecting)
                            <th class="text-end">Đang kiểm</th>
                        @endif
                        @if ($showMaintenance)
                            <th class="text-end">Bảo trì</th>
                        @endif
                        <th class="text-end">Tồn kho</th>
                        <th class="text-end">Đang giữ</th>
                        <th class="text-end">Đã bán</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['variant']->sku }}</td>
                        <td>{{ $row['variant']->product?->name }}</td>
                        <td>{{ $row['mode']?->label() ?? $na }}</td>
                        <td class="text-end">{{ $row['is_rental'] ? $row['rental_total'] : $na }}</td>
                        <td class="text-end">{{ $row['is_rental'] ? $row['available'] : $na }}</td>
                        <td class="text-end">{{ $row['is_rental'] ? $row['rented'] : $na }}</td>
                        @if ($showInspecting)
                            <td class="text-end">{{ $row['is_rental'] ? $row['inspecting'] : $na }}</td>
                        @endif
                        @if ($showMaintenance)
                            <td class="text-end">{{ $row['is_rental'] ? $row['maintenance'] : $na }}</td>
                        @endif
                        <td class="text-end">{{ $row['is_sale'] ? $row['on_hand'] : $na }}</td>
                        <td class="text-end">{{ $row['is_sale'] ? $row['reserved'] : $na }}</td>
                        <td class="text-end">{{ $row['is_sale'] ? $row['sold'] : $na }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $colspan }}" class="text-muted py-4 text-center">Chưa có phân loại sản phẩm.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
