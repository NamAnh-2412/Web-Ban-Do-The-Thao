@extends('layouts.admin')
@include('partials.css', ['file' => 'css/admin/chat.css'])
@include('partials.js', ['file' => 'js/storefront/chat-composer.js'])
@section('title', 'Hội thoại với '.($conversation->user->name ?? 'khách hàng'))
@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="page-title mb-0">{{ $conversation->user->name ?? 'Khách hàng' }}</h1>
            <p class="page-subtitle mb-0">{{ $conversation->user->email ?? '' }}</p>
        </div>
        <a href="{{ route('admin.messages.index') }}" class="btn btn-outline-secondary">Danh sách</a>
    </div>

    <div class="admin-card p-4">
        <div class="chat-thread mb-4">
            @forelse ($conversation->messages as $message)
                @php $fromStore = $message->isFromStore(); @endphp
                <div class="d-flex mb-3 {{ $fromStore ? 'justify-content-end' : 'justify-content-start' }}">
                    <div class="chat-bubble {{ $fromStore ? 'chat-bubble-mine' : 'chat-bubble-store' }}">
                        <div class="small fw-semibold mb-1">{{ $fromStore ? 'Cửa hàng' : ($conversation->user->name ?? 'Khách') }}</div>
                        @if ($message->body !== '')
                            <div class="chat-body">{{ $message->body }}</div>
                        @endif
                        @if ($message->hasAttachment())
                            @include('partials.chat.attachment-card', [
                                'payload' => $message->attachment_payload,
                                'mine' => $fromStore,
                            ])
                        @endif
                        <div class="small opacity-75 mt-1">{{ $message->created_at->format('d/m/Y H:i') }}</div>
                    </div>
                </div>
            @empty
                <p class="text-muted text-center mb-0">Chưa có tin nhắn. Hãy gửi tin đầu tiên tới khách hàng.</p>
            @endforelse
        </div>

        @include('partials.chat.composer', [
            'action' => route('admin.messages.store', $conversation),
            'productOptions' => $productOptions,
            'orderOptions' => $orderOptions,
            'submitLabel' => 'Gửi',
            'buttonClass' => 'btn btn-admin-primary',
            'label' => 'Trả lời',
        ])
    </div>
@endsection
