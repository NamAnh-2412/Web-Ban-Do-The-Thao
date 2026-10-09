@php
    /** @var \App\Domain\Product\Models\Sport|null $sport */
    $sport = $sport ?? null;
@endphp

<div class="mb-3">
    <label class="form-label" for="name">Tên môn <span class="text-danger">*</span></label>
    <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $sport?->name) }}" required>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label" for="headline">Tiêu đề trên banner</label>
    <input
        class="form-control @error('headline') is-invalid @enderror"
        id="headline"
        name="headline"
        value="{{ old('headline', $sport?->headline) }}"
        maxlength="255"
        placeholder="Ví dụ: Bộ Sưu Tập Bóng Đá - Mùa Giải Mới Đã Về"
    >
    @error('headline')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text">Để trống thì trang chủ hiện “Bộ sưu tập + tên môn”.</div>
</div>

<div class="mb-3">
    <label class="form-label" for="description">Mô tả ngắn</label>
    <textarea
        class="form-control @error('description') is-invalid @enderror"
        id="description"
        name="description"
        rows="3"
        maxlength="500"
        placeholder="Ví dụ: Khám phá trang phục thi đấu chính hãng"
    >{{ old('description', $sport?->description) }}</textarea>
    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label" for="image">Ảnh banner môn</label>
    @if ($sport?->imageSrc())
        <div class="mb-2">
            <img src="{{ $sport->imageSrc() }}" alt="{{ $sport->name }}" class="border rounded" style="max-width:220px;max-height:140px;object-fit:cover">
        </div>
    @endif
    <input
        class="form-control @error('image') is-invalid @enderror"
        id="image"
        name="image"
        type="file"
        accept="image/*"
    >
    @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text">Ảnh ngang khoảng 1200×700 px, tối đa 5MB. Có ảnh thì môn hiện trong carousel trang chủ.</div>
</div>
