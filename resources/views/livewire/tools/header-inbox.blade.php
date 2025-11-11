<div class="relative" wire:poll.60s="loadInbox">
  <x-dropdown align="right" width="md">
    {{-- Trigger --}}
    <x-slot name="trigger">
      <button type="button" class="block" aria-haspopup="true">
        <span class="relative">
          <svg xmlns="http://www.w3.org/2000/svg" width="30px" class="fill-[#333] hover:fill-[#077bff] stroke-2 inline" viewBox="0 0 512 512" stroke-width="106">
            <g><g><g><g>
              <path d="M479.568,412.096H33.987c-15,0-27.209-12.209-27.209-27.209V130.003c0-15,12.209-27.209,27.209-27.209h445.581 c15,0,27.209,12.209,27.209,27.209v255C506.661,399.886,494.568,412.096,479.568,412.096z M33.987,114.189 c-8.721,0-15.814,7.093-15.814,15.814v255c0,8.721,7.093,15.814,15.814,15.814h445.581c8.721,0,15.814-7.093,15.814-15.814v-255 c0-8.721-7.093-15.814-15.814-15.814C479.568,114.189,33.987,114.189,33.987,114.189z"/>
              <path d="M256.894,300.933c-5.93,0-11.86-1.977-16.744-5.93l-41.977-33.14L16.313,118.491c-2.442-1.977-2.907-5.581-0.93-8.023 c1.977-2.442,5.581-2.907,8.023-0.93l181.86,143.372l42.093,33.14c5.698,4.535,13.721,4.535,19.535,0l41.977-33.14 l181.628-143.372c2.442-1.977,6.047-1.512,8.023,0.93c1.977-2.442,1.512,6.047-0.93,8.023l-181.86,143.372l-41.977,33.14 C268.755,299.072,262.708,300.933,256.894,300.933z"/>
            </g></g></g></g>
          </svg>

          @if($unreadMessagesCount >= 1)
            <span class="absolute right-[-9px] -ml-1 top-[-5px] rounded-full bg-red-400 px-1.5 py-0.2 text-xs text-white">
              {{ $unreadMessagesCount }}
            </span>
          @endif
        </span>
      </button>
    </x-slot>

    {{-- Content --}}
    <x-slot name="content">
      <div class="w-[24.5rem] max-w-[calc(100vw-2rem)] text-[0.8125rem]/5 text-slate-900 divide-y divide-slate-200">
        @forelse($receivedMessages as $message)
          @php
            $isAdmin      = optional($message->sender)->role === 'admin';
            $senderName   = $isAdmin ? 'CBW Team' : ($message->sender->name ?? 'Unbekannt');
            $senderAvatar = $isAdmin
                ? asset('site-images/icon.png')
                : ($message->sender->profile_photo_url ?? asset('images/avatar-fallback.png'));
            $isUnread     = (int) $message->status === 1;
          @endphp

          <button
            type="button"
            class="w-full flex items-center gap-3 p-3 hover:bg-slate-50 text-left {{ $isUnread ? 'bg-blue-50' : '' }}"
            x-on:click="$wire.showMessage({{ $message->id }}); $dispatch('close')"
          >
            <img src="{{ $senderAvatar }}" class="w-8 h-8 rounded-full object-cover" alt="">
            <div class="flex-auto min-w-0">
              <div class="flex items-center gap-2">
                <div class="font-medium truncate {{ $isUnread ? 'font-semibold' : '' }}">{{ $senderName }}</div>
                <div class="text-[11px] text-slate-500" title="{{ $message->created_at->format('d.m.Y H:i') }}">
                  {{ $message->created_at->diffForHumans() }}
                </div>
              </div>
              <div class="truncate text-slate-900 {{ $isUnread ? 'font-medium' : '' }}">{{ $message->subject }}</div>
              <div class="mt-0.5 flex items-center gap-2 text-slate-700">
                @if($message->files_count > 0)
                  <svg class="w-4 h-4 text-gray-500 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M18.364 5.636a5 5 0 010 7.071l-7.071 7.071a5 5 0 11-7.071-7.071l6-6a3 3 0 114.243 4.243l-6 6a1 1 0 11-1.414-1.414l6-6a1 1 0 10-1.414-1.414l-6 6a3 3 0 104.243 4.243l7.071-7.071a3 3 0 10-4.243-4.243l-6 6" />
                  </svg>
                @endif
                <span class="truncate">{{ \Illuminate\Support\Str::limit(strip_tags($message->message), 60) }}</span>
              </div>
            </div>
          </button>
        @empty
          <div class="p-4 text-center text-slate-700">Keine Nachrichten</div>
        @endforelse

        <div class="p-3">
          <a href="{{ route('messages') }}"
             class="block rounded-md px-4 py-2 text-center font-medium ring-1 ring-slate-700/10 hover:bg-slate-50">
            Alle Nachrichten ansehen
          </a>
        </div>
      </div>
    </x-slot>
  </x-dropdown>

  {{-- Modal – bleibt unverändert --}}
  <x-ui.messages.message-show-modal
      model="showMessageModal"
      :message="$selectedMessage"
  />
</div>
