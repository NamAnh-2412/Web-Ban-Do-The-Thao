@once('asset-js-'.$file)
    @push('scripts')
        <script src="{{ asset($file) }}"></script>
    @endpush
@endonce
