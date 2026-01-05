<footer x-data="{ screenWidth: window.innerWidth }" x-resize="screenWidth = $width"
    class="footer bg-cover bg-center border-t border-gray-300 bg-white">

    <div class="bg-secondary tracking-wide px-8 py-12">
        <div class="container mx-auto">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-4 gap-x-6 gap-y-10">

                {{-- Logo / Brand --}}
                <div>
                    <a href='/' class="block h-auto bg-white w-max p-2.5 rounded-xl shadow-sm">
                        <x-application-logo />
                    </a>

                    <p class="text-white/90 text-sm mt-5 leading-6 max-w-xs">
                        Dein zentraler Zugang für Kurse, Berichtshefte, Anträge und Kommunikation.
                    </p>
                </div>
 
                @auth
                    {{-- 1) Übersicht --}}
                    <div x-data="{ open: false }">
                        <h4 class="text-white font-semibold text-lg relative max-sm:cursor-pointer"
                            @click="open = !open">
                            Übersicht
                            <svg xmlns="http://www.w3.org/2000/svg" width="16px" height="16px"
                                :class="open ? 'transform rotate-180' : ''"
                                class="sm:hidden transition-all ease-in duration-200 absolute right-0 top-1 fill-white/80"
                                viewBox="0 0 24 24">
                                <path
                                    d="M12 16a1 1 0 0 1-.71-.29l-6-6a1 1 0 0 1 1.42-1.42l5.29 5.3 5.29-5.29a1 1 0 0 1 1.41 1.41l-6 6a1 1 0 0 1-.7.29z">
                                </path>
                            </svg>
                        </h4>

                        <div x-show="open || screenWidth >= 768" x-collapse.duration.1000ms @click.away="open = false">
                            <ul class="mt-6 space-y-4">
                                <li>
                                    <a href="/user/dashboard" wire:navigate
                                       class="group inline-flex items-center gap-2 text-white/90 hover:text-white text-sm transition">
                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-white/10 group-hover:bg-white/15 transition">
                                            <i class="fal fa-tachometer-alt text-white"></i>
                                        </span>
                                        Dashboard
                                    </a>
                                </li>
                                <li>
                                    <a href="/user/profile" wire:navigate
                                       class="group inline-flex items-center gap-2 text-white/90 hover:text-white text-sm transition">
                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-white/10 group-hover:bg-white/15 transition">
                                            <i class="fal fa-user-circle text-white"></i>
                                        </span>
                                        Profil
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    {{-- 2) Kommunikation & Dokumentation --}}
                    <div x-data="{ open: false }">
                        <h4 class="text-white font-semibold text-lg relative max-sm:cursor-pointer"
                            @click="open = !open">
                            Kommunikation
                            <svg xmlns="http://www.w3.org/2000/svg" width="16px" height="16px"
                                :class="open ? 'transform rotate-180' : ''"
                                class="sm:hidden transition-all ease-in duration-200 absolute right-0 top-1 fill-white/80"
                                viewBox="0 0 24 24">
                                <path
                                    d="M12 16a1 1 0 0 1-.71-.29l-6-6a1 1 0 0 1 1.42-1.42l5.29 5.3 5.29-5.29a1 1 0 0 1 1.41 1.41l-6 6a1 1 0 0 1-.7.29z">
                                </path>
                            </svg>
                        </h4>

                        <div x-show="open || screenWidth >= 768" x-collapse.duration.1000ms @click.away="open = false">
                            <ul class="mt-6 space-y-4">
                                <li>
                                    <a href="/user/messages" wire:navigate
                                       class="group inline-flex items-center gap-2 text-white/90 hover:text-white text-sm transition">
                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-white/10 group-hover:bg-white/15 transition">
                                            <i class="fal fa-comments text-white"></i>
                                        </span>
                                        Nachrichten
                                    </a>
                                </li>

                                <li>
                                    <a href="/user/reportbook" wire:navigate
                                       class="group inline-flex items-center gap-2 text-white/90 hover:text-white text-sm transition">
                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-white/10 group-hover:bg-white/15 transition">
                                            <i class="fal fa-book-reader text-white"></i>
                                        </span>
                                        Berichtsheft
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    {{-- 3) Support & Anträge --}}
                    <div x-data="{ open: false }">
                        <h4 class="text-white font-semibold text-lg relative max-sm:cursor-pointer"
                            @click="open = !open">
                            Support
                            <svg xmlns="http://www.w3.org/2000/svg" width="16px" height="16px"
                                :class="open ? 'transform rotate-180' : ''"
                                class="sm:hidden transition-all ease-in duration-200 absolute right-0 top-1 fill-white/80"
                                viewBox="0 0 24 24">
                                <path
                                    d="M12 16a1 1 0 0 1-.71-.29l-6-6a1 1 0 0 1 1.42-1.42l5.29 5.3 5.29-5.29a1 1 0 0 1 1.41 1.41l-6 6a1 1 0 0 1-.7.29z">
                                </path>
                            </svg>
                        </h4>

                        <div x-show="open || screenWidth >= 768" x-collapse.duration.1000ms @click.away="open = false">
                            <ul class="mt-6 space-y-4">
                                <li>
                                    <a href="/user/user-requests" wire:navigate
                                       class="group inline-flex items-center gap-2 text-white/90 hover:text-white text-sm transition">
                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-white/10 group-hover:bg-white/15 transition">
                                            <i class="fal fa-file-signature text-white"></i>
                                        </span>
                                        Anträge
                                    </a>
                                </li>
                                <li>
                                    <a href="/user/faqs" wire:navigate
                                       class="group inline-flex items-center gap-2 text-white/90 hover:text-white text-sm transition">
                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-white/10 group-hover:bg-white/15 transition">
                                            <i class="fal fa-question-circle text-white"></i>
                                        </span>
                                        FAQ
                                    </a>
                                </li>
                                <li>
                                    <a href="/user/contact" wire:navigate
                                       class="group inline-flex items-center gap-2 text-white/90 hover:text-white text-sm transition">
                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-white/10 group-hover:bg-white/15 transition">
                                            <i class="fal fa-envelope-open-text text-white"></i>
                                        </span>
                                        Kontakt
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                @endauth

            </div>

            <hr class="container mx-auto my-10 border-gray-400/70" />

            {{-- Bottom Bar (Layout wie Ursprung) --}}
            <div class="container mx-auto flex flex-wrap max-md:flex-col gap-4 items-center">
<div>
    <ul class="md:flex md:space-x-6 max-md:space-y-2 max-sm:grid max-sm:grid-cols-3">

        {{-- Facebook --}}
        <li class="flex justify-center">
            <a
                href=""
                target="_blank"
                aria-label="Facebook"
                class="flex items-center justify-center
                       w-10 h-10
                       rounded-full
                       bg-white/10 hover:bg-white/15
                       transition
                       shrink-0"
            >
                <i class="fab fa-facebook-f text-white text-lg leading-none"></i>
                <span class="sr-only">Facebook</span>
            </a>
        </li>

        {{-- Instagram --}}
        <li class="flex justify-center">
            <a
                href=""
                target="_blank"
                aria-label="Instagram"
                class="flex items-center justify-center
                       w-10 h-10
                       rounded-full
                       bg-white/10 hover:bg-white/15
                       transition
                       shrink-0"
            >
                <i class="fab fa-instagram text-white text-lg leading-none"></i>
                <span class="sr-only">Instagram</span>
            </a>
        </li>

    </ul>
</div>


                <p class="text-white/90 text-sm md:ml-auto">
                    &copy; {{ date("Y") }} CBW College Berufliche Weiterbildung GmbH. Alle Rechte vorbehalten.
                </p>
            </div>
        </div>
    </div>
</footer>
