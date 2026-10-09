<?php

namespace App\Storefront\Http\Controllers;

use App\Domain\Chat\Enums\ChatAttachmentType;
use App\Domain\Chat\Services\ChatService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MessagePageController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        $conversation = $this->chat->forCustomer($user);
        $this->chat->markReadFor($conversation, $user);
        $conversation->load(['messages' => fn ($query) => $query->orderBy('id')->with('user')]);

        return view('storefront.messages.show', [
            'conversation' => $conversation,
            'productOptions' => $this->chat->productPickerOptions(),
            'orderOptions' => $this->chat->orderPickerOptions($user->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'attachment_type' => $request->filled('attachment_type') ? $request->input('attachment_type') : null,
            'attachment_id' => $request->filled('attachment_id') ? $request->input('attachment_id') : null,
        ]);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'attachment_type' => ['nullable', 'string', Rule::enum(ChatAttachmentType::class)],
            'attachment_id' => ['nullable', 'integer', 'min:1', 'required_with:attachment_type'],
        ], [
            'body.max' => 'Tin nhắn tối đa 2000 ký tự.',
            'attachment_id.required_with' => 'Vui lòng chọn mục đính kèm.',
        ]);

        $conversation = $this->chat->forCustomer($request->user());
        $this->chat->post(
            $conversation,
            $request->user(),
            (string) ($data['body'] ?? ''),
            $data['attachment_type'] ?? null,
            isset($data['attachment_id']) ? (int) $data['attachment_id'] : null,
        );

        return redirect()
            ->route('messages.show')
            ->with('status', 'Đã gửi tin nhắn tới cửa hàng.');
    }
}
