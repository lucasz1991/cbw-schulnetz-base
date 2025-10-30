<div  class="w-full relative bg-gray-100 py-8 pb-12"  wire:loading.class="cursor-wait">
    @section('title')
        {{ __('Nachrichten') }}
    @endsection
    <x-slot name="header">   
                <h1 class="font-semibold text-2xl text-gray-800 leading-tight flex items-center">
                     Nachrichten 
                     <svg xmlns="http://www.w3.org/2000/svg" width="80px" class="fill-[#000] ml-10 stroke-2 inline opacity-30" viewBox="0 0 512 512" stroke-width="106">
                         <g>
                             <g>
                                 <g>
                                     <g>
                                         <path d="M479.568,412.096H33.987c-15,0-27.209-12.209-27.209-27.209V130.003c0-15,12.209-27.209,27.209-27.209h445.581      
                                         c15,0,27.209,12.209,27.209,27.209v255C506.661,399.886,494.568,412.096,479.568,412.096z 
                                         M33.987,114.189      
                                         c-8.721,0-15.814,7.093-15.814,15.814v255c0,8.721,7.093,15.814,15.814,15.814h445.581c8.721,0,15.814-7.093,15.814-15.814v-255      
                                         c0-8.721-7.093-15.814-15.814-15.814C479.568,114.189,33.987,114.189,33.987,114.189z"/>
                                     </g>
                                     <g>
                                         <path d="M256.894,300.933c-5.93,0-11.86-1.977-16.744-5.93l-41.977-33.14L16.313,118.491c-2.442-1.977-2.907-5.581-0.93-8.023      
                                         c1.977-2.442,5.581-2.907,8.023-0.93l181.86,143.372l42.093,33.14c5.698,4.535,13.721,4.535,19.535,0l41.977-33.14      
                                         l181.628-143.372c2.442-1.977,6.047-1.512,8.023,0.93c1.977-2.442,1.512,6.047-0.93,8.023l-181.86,143.372l-41.977,33.14      
                                         C268.755,299.072,262.708,300.933,256.894,300.933z"/>
                                     </g>
                                 </g>
                             </g>
                         </g>
                     </svg>
                </h1>
        </x-slot>
    <div class="container mx-auto">
        <div class="bg-white  shadow-lg rounded-lg p-6"> 
            <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
            <p><span class="text-lg font-medium">Hier erhalten Sie alle wichtigen Informationen von der CBW-Verwaltung.</span><br> Neue Mitteilungen und aktuelle Hinweise werden Ihnen direkt angezeigt, damit Sie jederzeit bestens informiert sind. Schauen Sie regelmäßig in Ihr Postfach, um keine wichtigen Neuigkeiten zu verpassen.</p>            </div>
        <div class="mt-10 space-y-5">
            <div class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0 ">
                <div class="w-full md:w-1/2">
                    <form class="flex items-center">
                        <label for="simple-search" class="sr-only">Search</label>
                        <div class="relative w-full">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg aria-hidden="true" class="w-5 h-5 text-gray-500" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <input type="text" id="simple-search" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2" placeholder="Search" required="">
                        </div>
                    </form>
                </div>
            </div>
            <div class="">
                <table class="w-full text-sm text-left text-gray-500 table-fixed">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th scope="col" class="py-3 px-6 w-4/12">Von</th>
                            <th scope="col" class="py-3 px-6 w-4/12">Betreff</th>
                            <th scope="col" class="py-3 px-6 w-5/12">Nachricht</th>
                            <th scope="col" class="py-3 px-6 w-2/12">Datum</th>
                            <th scope="col" class="py-3 px-6 w-1/12"></th>
                        </tr>
                    </thead>
<tbody>
  @forelse($messages as $message)
    @php
      $isAdmin      = optional($message->sender)->role === 'admin';
      $senderName   = $isAdmin ? 'CBW Team' : ($message->sender->name ?? 'Unbekannt');
      $senderAvatar = $isAdmin
          ? asset('site-images/icon.png')  // passe deinen Pfad an
          : ($message->sender->profile_photo_url ?? asset('images/avatar-fallback.png'));
      $isUnread     = (int)$message->status === 1;
    @endphp

    <tr
      wire:key="msg-{{ $message->id }}"
      class="group border-b cursor-pointer hover:bg-blue-50 {{ $isUnread ? 'bg-blue-50/70' : '' }}"
      @click="$wire.showMessage({{ $message->id }})"
      role="button"
      aria-label="Nachricht öffnen"
    >
      {{-- Von --}}
      <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap truncate">
        <span class="inline-flex items-center gap-2">
          <img src="{{ $senderAvatar }}" class="w-5 h-5 rounded-full object-cover" alt="">
          <span class="{{ $isUnread ? 'font-semibold' : '' }}">{{ $senderName }}</span>
          @if($isUnread)
            <span class="ml-1 inline-block w-1.5 h-1.5 rounded-full bg-blue-500" aria-hidden="true"></span>
          @endif
        </span>
      </td>

      {{-- Betreff --}}
      <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap truncate">
        <span class="{{ $isUnread ? 'font-semibold' : '' }}">
          {{ $message->subject }}
        </span>
      </td>

      {{-- Nachricht (Snippet + optional Paperclip) --}}
      <td class="px-4 py-3">
        <div class="flex items-center gap-2 min-w-0">
          @if($message->files_count > 0)
            <svg class="w-4 h-4 text-gray-500 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <path d="M18.364 5.636a5 5 0 010 7.071l-7.071 7.071a5 5 0 11-7.071-7.071l6-6a3 3 0 114.243 4.243l-6 6a1 1 0 11-1.414-1.414l6-6a1 1 0 10-1.414-1.414l-6 6a3 3 0 104.243 4.243l7.071-7.071a3 3 0 10-4.243-4.243l-6 6" />
            </svg>
          @endif
          <span class="truncate">
            {{ \Illuminate\Support\Str::limit(strip_tags($message->message), 100) }}
          </span>
        </div>
      </td>

      {{-- Datum mit Tooltip --}}
      <td class="px-4 py-3" title="{{ $message->created_at->format('d.m.Y H:i') }}">
        {{ $message->created_at->diffForHumans() }}
      </td>

      {{-- Aktionen (Dropdown) --}}
      <td class="px-3 py-3 relative" x-data="{ open:false }">
        <button
          type="button"
          @click.stop="open = !open"
          class="inline-flex items-center p-1.5 rounded hover:bg-gray-100 text-gray-600"
          aria-haspopup="true"
          aria-expanded="false"
          title="Optionen"
        >
          <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0zm6-2a2 2 0 100 4 2 2 0 000-4z"/>
          </svg>
        </button>

        <div
          x-cloak
          x-show="open"
          @click.outside="open = false"
          x-transition
          class="absolute right-0 mt-2 z-20 w-44 bg-white rounded shadow border"
          role="menu"
        >
          <button
            type="button"
            @click.stop="$wire.showMessage({{ $message->id }}); open=false;"
            class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50"
            role="menuitem"
          >
            Anzeigen
          </button>
          <button
            type="button"
            @click.stop="open=false"
            class="w-full text-left px-3 py-2 text-sm text-red-600 hover:bg-red-50"
            role="menuitem"
          >
            Löschen
          </button>
        </div>
      </td>
    </tr>
  @empty
    <tr>
      <td class="border-b border-gray-200 px-6 py-4" colspan="5">
        Keine Nachrichten gefunden.
      </td>
    </tr>
  @endforelse
</tbody>

                </table>
            </div> 
                @if ($messages->hasMorePages())
                    <div class="text-center mt-10"
                    x-data="{ isClicked: false }" 
                    @click="isClicked = true; setTimeout(() => isClicked = false,100)">
                        <button :style="isClicked ? 'transform:scale(0.9)' : 'transform:scale(1)'" wire:click="loadMore" class=" transition-all duration-100 transform py-2.5 px-5 me-2 mb-2 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100">
                            Weitere Nachrichten laden
                        </button>
                    </div>
                @endif
            </div> 
        </div>
            <x-ui.messages.message-show-modal
                model="showMessageModal"
                :message="$selectedMessage"
            />
    </div>
</div> 