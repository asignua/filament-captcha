@if ($field->shouldRender())
    <x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
        <x-filament-captcha::widget
            :state-path="$getStatePath()"
            :driver="$field->getDriver()"
            :action="$field->getCaptchaAction()"
            :theme="$field->getTheme()"
            :size="$field->getSize()"
            :locale="$field->getLocale()"
        />
    </x-dynamic-component>
@endif
