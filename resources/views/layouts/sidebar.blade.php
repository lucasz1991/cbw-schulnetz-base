<!-- ========== Left Sidebar Start ========== -->
<div class="fixed bottom-0 z-10 h-screen ltr:border-r rtl:border-l vertical-menu rtl:right-0 ltr:left-0 top-[70px] pt-3 bg-slate-50 border-gray-50 print:hidden ">

    <div data-simplebar class="h-full">
        <!--- Sidemenu -->
        <div class="metismenu pb-10 pt-2.5" id="sidebar-menu">
            <!-- Left Menu Start -->
            <ul id="side-menu">
                <li class="px-5 py-3 text-xs font-medium text-gray-500 cursor-default leading-[18px] group-data-[sidebar-size=sm]:hidden block" data-key="t-menu">Menu</li>

                <li>
                    <a href="{{ route('dashboard') }}" class="block py-2.5 px-6 text-sm font-medium text-gray-600 transition-all duration-150 ease-linear hover:text-blue-500 space-x-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="#545a6d33" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-home"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                        <span data-key="t-dashboard"> Dashboard</span>
                    </a>
                </li>

                <li class="px-5 py-3 text-xs font-medium text-gray-500 cursor-default leading-[18px] group-data-[sidebar-size=sm]:hidden block" data-key="t-menu">Management</li>


                <li>
                    <a href="{{ route('tutor.courses') }}" class="block py-2.5 px-6 text-sm font-medium text-gray-600 transition-all duration-150 ease-linear hover:text-blue-500  space-x-2">
                        <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" class="css-i6dzq1"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>                        
                        <span data-key="t-config"> Meine Kurse</span>
                    </a> 
                </li>

                <li>
                    <a href="{{ route('tutor.messages') }}" class="block py-2.5 px-6 text-sm font-medium text-gray-600 transition-all duration-150 ease-linear hover:text-blue-500  space-x-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="#545a6d33" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-mail"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        <span data-key="t-webcontentmanager">Nachrichten</span>
                    </a>
                </li>

            </ul>
        </div>
        <!-- Sidebar -->
    </div>
</div>
<!-- Left Sidebar End -->