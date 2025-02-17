@for ($i = 1; $i <= ($total ?? 5); $i++)
    <label class="form-label">Ad {{ $i }}</label>
    <select name="ads_{{ $i }}" class="form-select">
        <option value="">{{ __('-- select --') }}</option>
        @foreach($ads as $ad)
            <option value="{{ $ad->key }}" @if ($ad->key == Arr::get($attributes, 'ads_' . $i)) selected @endif>{{ $ad->name }}</option>
        @endforeach
    </select>
@endfor
