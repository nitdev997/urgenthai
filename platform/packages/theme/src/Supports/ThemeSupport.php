<?php

namespace Botble\Theme\Supports;

use Botble\Base\Facades\Html;
use Botble\Base\Forms\Fields\NumberField;
use Botble\Base\Forms\Fields\TextField;
use Botble\Shortcode\Compilers\Shortcode;
use Botble\Shortcode\Forms\ShortcodeForm;
use Illuminate\Support\Str;

class ThemeSupport
{
    public static function registerYoutubeShortcode(string $viewPath = null): void
    {
        $viewPath = $viewPath ?: 'packages/theme::shortcodes';

        shortcode()
            ->register(
                'youtube-video',
                __('YouTube video'),
                __('Add YouTube video'),
                function ($shortcode) use ($viewPath) {
                    $url = Youtube::getYoutubeVideoEmbedURL($shortcode->content);
                    $width = $shortcode->width;
                    $height = $shortcode->height;

                    return view($viewPath . '.youtube', compact('url', 'width', 'height'))
                        ->render();
                }
            )
            ->setPreviewImage('youtube-video', asset('vendor/core/packages/theme/images/ui-blocks/youtube-video.jpg'))
            ->setAdminConfig('youtube-video', function ($attributes, $content) {
                return ShortcodeForm::createFromArray($attributes)
                    ->add('url', TextField::class, [
                        'label' => __('YouTube URL'),
                        'attr' => [
                            'placeholder' => 'https://www.youtube.com/watch?v=SlPhMPnQ58k ',
                            'data-shortcode-attribute' => 'content',
                        ],
                        'value' => $content,
                    ])
                    ->add('width', NumberField::class, [
                        'label' => __('Width'),
                    ])
                    ->add('height', NumberField::class, [
                        'label' => __('Height'),
                    ]);
            });
    }

    public static function registerGoogleMapsShortcode(string $viewPath = null): void
    {
        $viewPath = $viewPath ?: 'packages/theme::shortcodes';

        shortcode()
            ->register(
                'google-map',
                __('Google Maps'),
                __('Add Google Maps iframe'),
                function (Shortcode $shortcode) use ($viewPath) {
                    $address = $shortcode->content;

                    if (! $address) {
                        return '';
                    }

                    $width = $shortcode->width ?: '100%';
                    $height = $shortcode->height ?: '500';

                    return view($viewPath . '.google-map', compact('address', 'width', 'height'))
                        ->render();
                }
            )
            ->setPreviewImage('google-map', asset('vendor/core/packages/theme/images/ui-blocks/google-map.jpg'))
            ->setAdminConfig('google-map', function (array $attributes, string|null $content) {
                return ShortcodeForm::createFromArray($attributes)
                    ->add('address', 'textarea', [
                        'label' => __('Address'),
                        'attr' => [
                            'data-shortcode-attribute' => 'content',
                            'placeholder' => '24 Roberts Street, SA73, Chester',
                            'rows' => 3,
                        ],
                        'value' => $content,
                    ])
                    ->add('width', NumberField::class, [
                        'label' => __('Width'),
                    ])
                    ->add('height', NumberField::class, [
                        'label' => __('Height'),
                        'default_value' => 500,
                    ]);
            });
    }

    public static function getCustomJS(string $location): string
    {
        $js = setting('custom_' . $location . '_js');

        if (empty($js)) {
            return '';
        }

        if ((! Str::contains($js, '<script') || ! Str::contains($js, '</script>')) && ! Str::contains(
            $js,
            '<noscript'
        ) && ! Str::contains($js, '</noscript>')) {
            $js = Html::tag('script', $js);
        }

        return $js;
    }

    public static function getCustomHtml(string $location): string
    {
        $html = setting('custom_' . $location . '_html');

        if (empty($html)) {
            return '';
        }

        return $html;
    }

    public static function insertBlockAfterTopHtmlTags(string|null $block, string|null $html): string|null
    {
        if (! $block || ! $html) {
            return $html;
        }

        preg_match_all('/^<([a-z]+)([^>]+)*(?:>(.*)<\/\1>|\s+\/>)$/sm', $html, $matches);

        if (empty($matches[0])) {
            return $html;
        }

        $parsedHtml = '';

        foreach ($matches[0] as $blockItem) {
            $parsedHtml .= Str::replaceLast('</', $block . '</', $blockItem);
        }

        return $parsedHtml;
    }
}
