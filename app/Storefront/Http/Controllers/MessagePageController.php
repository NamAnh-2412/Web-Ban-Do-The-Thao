<?php

namespace App\Storefront\Http\Controllers;

use App\Domain\Chat\Services\ChatService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessagePageController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function show(Request $request): View
    {
        $conversation = $this->chat->forCustomer($request->user());
        $this->chat->markReadFor($conversation, $request->user());
        $conversation->load(['messages' => fn ($query) => $query->orderBy('id')->with('user')]);

        return view('storefront.messages.show', compact('conversation'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ], [
            'body.required' => 'Vui lòng nhập nội dung tin nhắn.',
            'body.max' => 'Tin nhắn tối đa 2000 ký tự.',
        ]);

        $conversation = $this->chat->forCustomer($request->user());
        $this->chat->post($conversation, $request->user(), $data['body']);

        return redirect()
            ->route('messages.show')
            ->with('status', 'Đã gửi tin nhắn tới cửa hàng.');
    }
}
