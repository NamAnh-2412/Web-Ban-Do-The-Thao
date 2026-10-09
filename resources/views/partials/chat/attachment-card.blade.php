@php
    $payload = is_array($payload ?? null) ? $payload : [];
    $kind = (string) ($payload['kind'] ?? '');
    $kindEnum = \App\Domain\Chat\Enums\ChatAttachmentType::tryFrom($kind);
    $title = (string) ($payload['title'] ?? 'Đính kèm');
    $subtitle = (string) ($payload['subtitle'] ?? '');
    $priceLabel = (string) ($payload['price_label'] ?? '');
    $image = $payload['image'] ?? null;
    $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
    $url = (string) ($payload['url'] ?? '#');
    if ($kindEnum === \App\Domain\Chat\Enums\ChatAttachmentType::Order && request()->routeIs('admin.*') && ! empty($payload['id'])) {
        $url = route('admin.orders.show', $payload['id']);
    }
    $tone = $mine ?? false;
@endphp
<div class="chat-attach {{ $tone ? 'chat-attach-mine' : 'chat-attach-store' }}">
    @if ($image)
        <div class="chat-attach-media">
            <img src="{{ $image }}" alt="" loading="lazy">
        </div>
    @endif
    <div class="chat-attach-body">
        <div class="chat-attach-kind">{{ $kindEnum?->label() ?? 'Đính kèm' }}</div>
        <div class="chat-attach-title">{{ $title }}</div>
        @if ($subtitle !== '')
            <div class="chat-attach-sub">{{ $subtitle }}</div>
        @endif
        @if ($priceLabel !== '')
            <div class="chat-attach-price">{{ $priceLabel }}</div>
        @endif
        @if ($items !== [])
            <div class="chat-attach-items">{{ implode(' · ', array_slice($items, 0, 3)) }}</div>
        @endif
        @if ($url !== '' && $url !== '#')
            <a class="chat-attach-link" href="{{ $url }}">Xem chi tiết</a>
        @endif
    </div>
</div>
