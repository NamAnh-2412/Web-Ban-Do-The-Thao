@if ($categoryId)
    <input type="hidden" name="category_id" value="{{ $categoryId }}">
@endif
@if ($q !== '')
    <input type="hidden" name="q" value="{{ $q }}">
@endif
