<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Chat\Models\Conversation;
use App\Domain\Chat\Services\ChatService;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function index(): View
    {
        return view('admin.messages.index', [
            'conversations' => $this->chat->inbox(),
        ]);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $conversation->load([
            'user',
            'messages' => fn ($query) => $query->orderBy('id')->with('user'),
        ]);
        $this->chat->markReadFor($conversation, $request->user());

        return view('admin.messages.show', compact('conversation'));
    }

    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ], [
            'body.required' => 'Vui lòng nhập nội dung tin nhắn.',
            'body.max' => 'Tin nhắn tối đa 2000 ký tự.',
        ]);

        $this->chat->post($conversation, $request->user(), $data['body']);

        return redirect()
            ->route('admin.messages.show', $conversation)
            ->with('success', 'Đã gửi tin nhắn tới khách hàng.');
    }

    public function start(User $user): RedirectResponse
    {
        if (! $user->isCustomer()) {
            return back()->with('error', 'Chỉ nhắn tin với khách hàng.');
        }

        $conversation = $this->chat->forCustomer($user);

        return redirect()->route('admin.messages.show', $conversation);
    }
}
