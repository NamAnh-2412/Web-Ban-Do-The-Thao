@extends('storefront.layouts.app')
@include('partials.css', ['file' => 'css/storefront/chat.css'])
@include('partials.js', ['file' => 'js/storefront/chat-composer.js'])

@section('title', 'Tin nhắn')

@section('content')
    <div class="policy-card col-lg-8 mx-auto">
        <h1 class="h4 mb-1">Tin nhắn với cửa hàng</h1>
        <p class="text-secondary mb-4">Một hội thoại cho tài khoản của bạn. Có thể đính kèm sản phẩm hoặc đơn hàng để cửa hàng hỗ trợ nhanh hơn.</p>

        <div class="chat-thread mb-4">
            @forelse ($conversation->messages as $message)
                @php $mine = $message->user_id === auth()->id(); @endphp
                <div class="d-flex mb-3 {{ $mine ? 'justify-content-end' : 'justify-content-start' }}">
                    <div class="chat-bubble {{ $mine ? 'chat-bubble-mine' : 'chat-bubble-store' }}">
                        <div class="small fw-semibold mb-1">{{ $mine ? 'Bạn' : 'Cửa hàng' }}</div>
                        @if ($message->body !== '')
                            <div class="chat-body">{{ $message->body }}</div>
                        @endif
                        @if ($message->hasAttachment())
                            @include('partials.chat.attachment-card', [
                                'payload' => $message->attachment_payload,
                                'mine' => $mine,
                            ])
                        @endif
                        <div class="small opacity-75 mt-1">{{ $message->created_at->format('d/m/Y H:i') }}</div>
                    </div>
                </div>
            @empty
                <p class="text-secondary text-center mb-0">Chưa có tin nhắn. Hỏi cửa hàng về đơn, thuê hoặc giao hàng — hoặc đính kèm sản phẩm/đơn để được hỗ trợ.</p>
            @endforelse
        </div>

        @include('partials.chat.composer', [
            'action' => route('messages.store'),
            'productOptions' => $productOptions,
            'orderOptions' => $orderOptions,
            'submitLabel' => 'Gửi tin nhắn',
            'buttonClass' => 'btn btn-success',
            'label' => 'Nội dung',
        ])
    </div>
@endsection
