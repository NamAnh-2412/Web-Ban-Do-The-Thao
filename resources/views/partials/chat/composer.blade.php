@php
    /** @var \Illuminate\Support\Collection|array $productOptions */
    /** @var \Illuminate\Support\Collection|array $orderOptions */
    $productOptions = collect($productOptions ?? []);
    $orderOptions = collect($orderOptions ?? []);
    $submitLabel = $submitLabel ?? 'Gửi tin nhắn';
    $buttonClass = $buttonClass ?? 'btn btn-success';
@endphp

<form method="post" action="{{ $action }}" class="chat-composer" data-chat-composer>
    @csrf
    <input type="hidden" name="attachment_type" value="{{ old('attachment_type') }}" data-attach-type>
    <input type="hidden" name="attachment_id" value="{{ old('attachment_id') }}" data-attach-id>

    <div class="chat-composer-toolbar mb-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-open-picker="product">Sản phẩm</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-open-picker="order">Đơn hàng</button>
    </div>

    <div class="chat-attach-chip d-none mb-2" data-attach-chip>
        <span data-attach-chip-label></span>
        <button type="button" class="chat-attach-chip-remove" data-attach-clear aria-label="Gỡ đính kèm">&times;</button>
    </div>

    <label class="form-label" for="body">{{ $label ?? 'Nội dung' }}</label>
    <textarea
        id="body"
        name="body"
        class="form-control mb-3 @error('body') is-invalid @enderror"
        rows="3"
        maxlength="2000"
        placeholder="Nhập tin nhắn..."
        data-chat-body
    >{{ old('body') }}</textarea>
    @error('body')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @error('attachment_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @error('attachment_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

    <button class="{{ $buttonClass }}" type="submit">{{ $submitLabel }}</button>
</form>

{{-- Pickers outside the form to avoid nested dialog submit quirks --}}
<dialog class="chat-picker" data-picker="product">
    <div class="chat-picker-panel">
        <div class="chat-picker-head">
            <strong>Chọn sản phẩm</strong>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-close-picker>Đóng</button>
        </div>
        <input type="search" class="form-control mb-2" placeholder="Lọc theo tên..." data-picker-filter>
        <div class="chat-picker-list">
            @forelse ($productOptions as $option)
                <button
                    type="button"
                    class="chat-picker-item"
                    data-pick
                    data-type="product"
                    data-id="{{ $option['id'] }}"
                    data-label="{{ $option['title'] }}"
                    data-search="{{ \Illuminate\Support\Str::lower($option['title'].' '.($option['subtitle'] ?? '')) }}"
                >
                    @if (! empty($option['image']))
                        <img src="{{ $option['image'] }}" alt="" class="chat-picker-thumb">
                    @endif
                    <span>
                        <span class="d-block fw-semibold">{{ $option['title'] }}</span>
                        @if (! empty($option['subtitle']))
                            <span class="small text-secondary">{{ $option['subtitle'] }}</span>
                        @endif
                    </span>
                </button>
            @empty
                <p class="text-secondary mb-0 p-2">Chưa có sản phẩm để đính kèm.</p>
            @endforelse
        </div>
    </div>
</dialog>

<dialog class="chat-picker" data-picker="order">
    <div class="chat-picker-panel">
        <div class="chat-picker-head">
            <strong>Chọn đơn hàng</strong>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-close-picker>Đóng</button>
        </div>
        <input type="search" class="form-control mb-2" placeholder="Lọc theo mã đơn..." data-picker-filter>
        <div class="chat-picker-list">
            @forelse ($orderOptions as $option)
                <button
                    type="button"
                    class="chat-picker-item"
                    data-pick
                    data-type="order"
                    data-id="{{ $option['id'] }}"
                    data-label="{{ $option['title'] }}"
                    data-search="{{ \Illuminate\Support\Str::lower($option['title'].' '.($option['subtitle'] ?? '')) }}"
                >
                    <span>
                        <span class="d-block fw-semibold">{{ $option['title'] }}</span>
                        @if (! empty($option['subtitle']))
                            <span class="small text-secondary">{{ $option['subtitle'] }}</span>
                        @endif
                    </span>
                </button>
            @empty
                <p class="text-secondary mb-0 p-2">Chưa có đơn hàng để đính kèm.</p>
            @endforelse
        </div>
    </div>
</dialog>
