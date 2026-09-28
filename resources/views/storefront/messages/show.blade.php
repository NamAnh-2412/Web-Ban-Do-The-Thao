@extends('storefront.layouts.app')
@include('partials.css', ['file' => 'css/storefront/chat.css'])

@section('title', 'Tin nhắn')

@section('content')
    <div class="policy-card col-lg-8 mx-auto">
        <h1 class="h4 mb-1">Tin nhắn với cửa hàng</h1>
        <p class="text-secondary mb-4">Một hội thoại cho tài khoản của bạn. Nhân viên sẽ trả lời tại đây.</p>

        <div class="chat-thread mb-4">
            @forelse ($conversation->messages as $message)
                @php $mine = $message->user_id === auth()->id(); @endphp
                <div class="d-flex mb-3 {{ $mine ? 'justify-content-end' : 'justify-content-start' }}">
                    <div class="chat-bubble {{ $mine ? 'chat-bubble-mine' : 'chat-bubble-store' }}">
                        <div class="small fw-semibold mb-1">{{ $mine ? 'Bạn' : 'Cửa hàng' }}</div>
                        <div class="chat-body">{{ $message->body }}</div>
                        <div class="small opacity-75 mt-1">{{ $message->created_at->format('d/m/Y H:i') }}</div>
                    </div>
                </div>
            @empty
                <p class="text-secondary text-center mb-0">Chưa có tin nhắn. Hỏi cửa hàng về đơn, thuê hoặc giao hàng.</p>
            @endforelse
        </div>

        <form method="post" action="{{ route('messages.store') }}">
            @csrf
            <label class="form-label" for="body">Nội dung</label>
            <textarea id="body" name="body" class="form-control mb-3 @error('body') is-invalid @enderror" rows="3" maxlength="2000" required placeholder="Nhập tin nhắn...">{{ old('body') }}</textarea>
            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <button class="btn btn-success" type="submit">Gửi tin nhắn</button>
        </form>
    </div>
@endsection
