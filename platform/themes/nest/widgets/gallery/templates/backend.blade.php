@if (is_plugin_active('simple-slider'))
    <div class="mb-3">
        <label for="widget-name">{{ __('Name') }}</label>
        <input type="text" id="widget-name" class="form-control" name="name" value="{{ $config['name'] }}">
    </div>
    <div class="mb-3">
        <label for="widget_slider_id">{{ __('Select simple slider') }}</label>
        {!! Form::customSelect('slider_key', $sliders, $config['slider_key']) !!}
    </div>
@endif
