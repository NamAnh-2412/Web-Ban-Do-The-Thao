@once('asset-css-'.$file)
    @push('styles')
        <link rel="stylesheet" href="{{ asset($file) }}">
    @endpush
@endonce
