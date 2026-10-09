@once('asset-css-'.$file)
    @push('styles')
        <link rel="stylesheet" href="{{ asset($file) }}?v={{ filemtime(public_path($file)) }}">
    @endpush
@endonce
