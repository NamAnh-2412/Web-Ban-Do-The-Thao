@extends('layouts.admin')
@section('title', 'Tồn kho')
@section('content')
    @php
        $na = '—';
        $rentalCols = 3 + ($showInspecting ? 1 : 0) + ($showMaintenance ? 1 : 0);
        $saleCols = 3;
        $colspan = 4 + $rentalCols + $saleCols;
    @endphp
    <h1 class="page-title">Tồn kho</h1>
    <p class="page-subtitle mb-3">Mỗi dòng là một phân loại (SKU). Cột <strong>Thuê</strong> đếm từng món; cột <strong>Bán</strong> là số lượng — hai nhóm độc lập, bán không làm giảm món thuê. Dấu — nghĩa là hình thức đó không dùng. <strong>Tạm khóa</strong> chỉ giấu sản phẩm khỏi cửa hàng, không tắt vĩnh viễn và không xóa.</p>

    <div class="d-flex flex-wrap gap-2 mb-4">
        @foreach (['' => 'Tất cả', 'sale' => 'Chỉ bán', 'rental' => 'Chỉ thuê', 'both' => 'Bán và thuê'] as $value => $label)
            <a
                href="{{ $value === '' ? route('admin.inventory.index') : route('admin.inventory.index', ['offer_mode' => $value]) }}"
                class="btn btn-sm {{ $offerMode === $value ? 'btn-success' : 'btn-outline-secondary' }}"
            >{{ $label }}</a>
        @endforeach
    </div>

    @if (auth()->user()->isOwnerAdmin())
        <div class="admin-card p-4 mb-4">
            <h5>Phiếu nhập chờ duyệt</h5>
            <p class="small text-muted mb-3">Nhân viên gửi số lượng hàng về. Bấm duyệt thì số đó mới cộng vào kho.</p>
            @if ($receipts->isEmpty())
                <p class="small text-muted mb-0">Không có phiếu chờ duyệt.</p>
            @else
                <div class="table-responsive">
                    <table class="table admin-table mb-0">
                        <thead>
                            <tr>
                                <th>Nhân viên</th>
                                <th>Sản phẩm trong phiếu</th>
                                <th>Ghi chú</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($receipts as $receipt)
                            <tr>
                                <td>{{ $receipt->creator?->name }}</td>
                                <td>
                                    @foreach ($receipt->lines as $line)
                                        <div>
                                            {{ $line->variant?->sku }} — {{ $line->variant?->product?->name }}
                                            @if ($line->sale_quantity > 0) +{{ $line->sale_quantity }} bán @endif
                                            @if ($line->rental_quantity > 0) +{{ $line->rental_quantity }} thuê @endif
                                        </div>
                                    @endforeach
                                </td>
                                <td>{{ $receipt->note ?: $na }}</td>
                                <td class="text-end text-nowrap">
                                    <form class="d-inline" method="post" action="{{ route('admin.inventory.receipts.approve', $receipt) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-success">Duyệt</button>
                                    </form>
                                    <form class="d-inline" method="post" action="{{ route('admin.inventory.receipts.reject', $receipt) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger">Từ chối</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        <div class="admin-card p-4 mb-4">
        <h5>Cập nhật số lượng</h5>
        <p class="small text-muted mb-3">Đây là chỗ sửa số kho sau khi sản phẩm đã tạo. SKU <strong>chỉ bán</strong> chỉ nhận tồn bán. <strong>Chỉ thuê</strong> chỉ nhận số món thuê. <strong>Bán và thuê</strong>: mỗi ô sửa đúng nhóm của nó; ô để trống thì nhóm đó giữ nguyên.</p>
        <form class="row g-2 align-items-end" method="post" action="{{ route('admin.inventory.stocks') }}" data-inventory-stock-form>
            @csrf
            <div class="col-md-4">
                <label class="form-label">Phân loại</label>
                <select name="product_variant_id" class="form-select" required @disabled($rows->isEmpty())>
                    @foreach ($rows as $row)
                        <option value="{{ $row['variant']->id }}" data-mode="{{ $row['mode']?->value }}">{{ $row['variant']->sku }} — {{ $row['variant']->product?->name }}</option>
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
            <p class="col-12 small text-muted mb-0" data-stock-hint></p>
        </form>
        @include('partials.js', ['file' => 'js/admin/inventory-form.js'])
        </div>
    @else
        <div class="admin-card p-4 mb-4">
            <h5>Phiếu nhập kho</h5>
            <p class="small text-muted mb-3">Một phiếu gồm nhiều sản phẩm. Số lượng chỉ cộng vào kho sau khi quản trị duyệt cả phiếu.</p>
            <form method="post" action="{{ route('admin.inventory.receipts.store') }}" data-stock-receipt-form>
                @csrf
                <div id="receipt-lines" class="d-flex flex-column gap-2 mb-3">
                    <div class="receipt-line row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Phân loại</label>
                            <select name="lines[0][product_variant_id]" class="form-select" required @disabled($rows->isEmpty())>
                                @foreach ($rows as $row)
                                    <option value="{{ $row['variant']->id }}" data-mode="{{ $row['mode']?->value }}">{{ $row['variant']->sku }} — {{ $row['variant']->product?->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Nhập bán</label>
                            <input type="number" min="0" name="lines[0][sale_quantity]" class="form-control" placeholder="Thêm bán">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Nhập thuê</label>
                            <input type="number" min="0" name="lines[0][rental_quantity]" class="form-control" placeholder="Thêm thuê">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-danger remove-receipt-line" type="button">Xóa dòng</button>
                        </div>
                    </div>
                </div>
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">Ghi chú</label>
                        <input type="text" name="note" class="form-control" maxlength="255" placeholder="Tùy chọn">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-secondary" type="button" id="add-receipt-line" @disabled($rows->isEmpty())>Thêm sản phẩm</button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-success" @disabled($rows->isEmpty())>Gửi phiếu</button>
                    </div>
                    <p class="col-12 small text-muted mb-0">Mỗi dòng một sản phẩm. Ô không dùng cho hình thức đó sẽ bị khóa.</p>
                </div>
                <template id="receipt-line-template">
                    <div class="receipt-line row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Phân loại</label>
                            <select data-name="product_variant_id" class="form-select" required>
                                @foreach ($rows as $row)
                                    <option value="{{ $row['variant']->id }}" data-mode="{{ $row['mode']?->value }}">{{ $row['variant']->sku }} — {{ $row['variant']->product?->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Nhập bán</label>
                            <input type="number" min="0" data-name="sale_quantity" class="form-control" placeholder="Thêm bán">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Nhập thuê</label>
                            <input type="number" min="0" data-name="rental_quantity" class="form-control" placeholder="Thêm thuê">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-danger remove-receipt-line" type="button">Xóa dòng</button>
                        </div>
                    </div>
                </template>
            </form>
            @include('partials.js', ['file' => 'js/admin/inventory-form.js'])
        </div>
        <div class="admin-card mb-4">
            <div class="table-responsive">
                <table class="table admin-table mb-0">
                    <thead>
                        <tr>
                            <th>Sản phẩm trong phiếu</th>
                            <th>Trạng thái</th>
                            <th>Gửi lúc</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($receipts as $receipt)
                        <tr>
                            <td>
                                @foreach ($receipt->lines as $line)
                                    <div>
                                        {{ $line->variant?->sku }} — {{ $line->variant?->product?->name }}
                                        @if ($line->sale_quantity > 0) +{{ $line->sale_quantity }} bán @endif
                                        @if ($line->rental_quantity > 0) +{{ $line->rental_quantity }} thuê @endif
                                    </div>
                                @endforeach
                            </td>
                            <td>{{ $receipt->status->label() }}</td>
                            <td>{{ $receipt->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-muted py-4 text-center">Chưa có phiếu nhập kho.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="admin-card">
        <div class="table-responsive">
            <table class="table admin-table mb-0">
                <thead>
                    <tr>
                        <th rowspan="2" class="align-middle">Mã SKU</th>
                        <th rowspan="2" class="align-middle">Sản phẩm</th>
                        <th rowspan="2" class="align-middle">Hình thức</th>
                        <th rowspan="2" class="align-middle">Bày bán</th>
                        <th class="text-center inventory-group" colspan="{{ $rentalCols }}">Thuê</th>
                        <th class="text-center inventory-group" colspan="{{ $saleCols }}">Bán</th>
                    </tr>
                    <tr>
                        <th class="text-end" title="Tổng số cái vật lý dùng để cho thuê">Số món thuê</th>
                        <th class="text-end" title="Còn trống, khách đặt thuê được">Sẵn sàng</th>
                        <th class="text-end" title="Đã giao cho khách, chưa trả">Đang thuê</th>
                        @if ($showInspecting)
                            <th class="text-end">Đang kiểm</th>
                        @endif
                        @if ($showMaintenance)
                            <th class="text-end">Bảo trì</th>
                        @endif
                        <th class="text-end" title="Số lượng còn trong kho bán, không tính món thuê">Tồn bán</th>
                        <th class="text-end" title="Đơn đã khóa, khách khác không mua được">Giữ chỗ</th>
                        <th class="text-end" title="Đã bán trên đơn đã thanh toán, đang xử lý hoặc hoàn tất">Đã bán</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['variant']->sku }}</td>
                        <td>{{ $row['variant']->product?->name }}</td>
                        <td>{{ $row['mode']?->label() ?? $na }}</td>
                        <td>@include('admin.inventory._suspend', ['product' => $row['variant']->product])</td>
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
