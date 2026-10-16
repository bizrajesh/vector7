{{-- Floating chatbot panel (spec 17.9 marketplace / 17.10 workspace). Vars: $bot ('market'|'workspace'), $historyUrl, $sendUrl, optional $consentUrl, $title --}}
<div class="fixed bottom-4 right-4 z-50 print:hidden" data-chat data-bot="{{ $bot }}" data-history="{{ $historyUrl }}" data-send="{{ $sendUrl }}" @isset($consentUrl) data-consent="{{ $consentUrl }}" @endisset>
    <div class="mb-3 hidden w-[min(24rem,calc(100vw-2rem))] h-[min(36rem,calc(100vh-7rem))] flex-col overflow-hidden rounded-2xl bg-white shadow-lift ring-1 ring-navy-50" data-chat-panel role="dialog" aria-label="{{ $title }}">
        <div class="flex items-center justify-between bg-navy px-4 py-3 text-white">
            <p class="font-bold"><x-icon name="sparkles" class="mr-1 inline h-4 w-4" /> <span data-chat-name>{{ $title }}</span></p>
            <button type="button" class="rounded p-1 hover:bg-white/10" data-chat-close aria-label="Close chat"><x-icon name="x" class="h-5 w-5" /></button>
        </div>
        <div class="flex-1 space-y-3 overflow-y-auto bg-page p-3 text-sm" data-chat-log aria-live="polite"></div>
        <form class="flex gap-2 border-t border-navy-50 p-2" data-chat-form>
            <label class="sr-only" for="chat_{{ $bot }}">Message</label>
            <input id="chat_{{ $bot }}" class="input flex-1" maxlength="1000" autocomplete="off" placeholder="{{ $bot === 'market' ? 'e.g. 2 BHK flat in Trichy under 50 lakh' : 'Ask about your leads, plots, dues or how to…' }}" required>
            <button class="btn-primary btn-sm" aria-label="Send"><x-icon name="arrow-right" class="h-4 w-4" /></button>
        </form>
        <p class="px-3 pb-2 text-[11px] text-muted">{{ $bot === 'market' ? 'AI answers can be wrong — check details with the owner. Chats are kept for 90 days.' : 'Read-only answers from your workspace. Nothing changes until you confirm.' }}</p>
    </div>
    @if ($bot === 'market')
        <button type="button" class="ml-auto flex items-center gap-2 rounded-full bg-navy px-4 py-3 font-semibold text-white shadow-lift hover:bg-navy-700" data-chat-open aria-expanded="false"><x-icon name="chat" class="h-5 w-5" /> <span class="hidden sm:inline">Ask {{ \App\Services\AppSettings::get('ai.bot_name', 'Vee') }}</span></button>
    @endif
</div>
