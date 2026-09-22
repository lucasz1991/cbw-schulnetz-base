@once
    @php
        $flatpickrPath = is_file(public_path('adminresources/flatpickr/flatpickr.min.js')) ? 'adminresources/flatpickr' : 'build/libs/flatpickr';
        $pickerVersion = max(filemtime(public_path('ui/date-picker.js')), filemtime(public_path('ui/date-picker.css')));
    @endphp
    <link rel="stylesheet" href="{{ asset($flatpickrPath.'/flatpickr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('ui/date-picker.css').'?v='.$pickerVersion }}">
    <script src="{{ asset($flatpickrPath.'/flatpickr.min.js') }}"></script>
    <script src="{{ asset($flatpickrPath.'/l10n/de.js') }}"></script>
    <script src="{{ asset('ui/date-picker.js').'?v='.$pickerVersion }}"></script>
@endonce
