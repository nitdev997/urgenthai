<x-core::form.field
    :showLabel="$showLabel"
    :showField="$showField"
    :options="$options"
    :name="$name"
    :prepend="$prepend ?? null"
    :append="$append ?? null"
    :showError="$showError"
    :nameKey="$nameKey"
>
    {!! Form::customRadio(
        $name,
        $options['choices'] ?? $options['values'],
        $options['value'] ?? $options['selected'] ?? null,
        $options['attr'],
        $options['default_value'],
    ) !!}
</x-core::form.field>
