@php
    $outcome = $outcome ?? ['deposit_amount' => 0, 'fees_total' => 0, 'refund_suggested' => 0, 'extra_due' => 0];
    $incidents = $incidents ?? collect();
    $compact = ! empty($compact);
@endphp
@if ($incidents->isNotEmpty() || (float) $outcome['fees_total'] > 0)
    <div class="{{ $compact ? 'small' : '' }}">
        <p class="fw-semibold mb-2">Đền bù / trừ cọc</p>
        <ul class="list-unstyled mb-2">
            @foreach ($incidents as $incident)
                <li class="mb-1">
                    {{ $incident->type->label() }}
                    · {{ number_format((float) $incident->fee_amount, 0, ',', '.') }}đ
                    @if ($incident->description)
                        — {{ $incident->description }}
                    @endif
                </li>
            @endforeach
        </ul>
        <div>Cọc: {{ number_format((float) $outcome['deposit_amount'], 0, ',', '.') }}đ</div>
        <div>Phí sự cố: {{ number_format((float) $outcome['fees_total'], 0, ',', '.') }}đ</div>
        @if ((float) $outcome['extra_due'] > 0)
            <div class="text-danger">Khách còn đền thêm {{ number_format((float) $outcome['extra_due'], 0, ',', '.') }}đ (phí vượt cọc).</div>
        @else
            <div>Hoàn cọc còn lại: <strong>{{ number_format((float) $outcome['refund_suggested'], 0, ',', '.') }}đ</strong></div>
        @endif
    </div>
@endif
