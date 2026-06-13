<?php

namespace App\Domain\Storefront\Templates;

class PageTemplateRegistry
{
    /**
     * @return list<PageTemplate>
     */
    public static function all(): array
    {
        return [
            new HomeTemplate,
            new ContactTemplate,
            new LegalTemplate,
            new AboutTemplate,
            new FlexibleTemplate,
        ];
    }

    public static function find(?string $key): ?PageTemplate
    {
        foreach (self::all() as $template) {
            if ($template->key() === $key) {
                return $template;
            }
        }

        return null;
    }

    /**
     * All template keys, including singletons.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_map(fn (PageTemplate $template) => $template->key(), self::all());
    }

    /**
     * Keys selectable when creating a page (excludes singletons like home).
     *
     * @return list<string>
     */
    public static function creatableKeys(): array
    {
        return array_values(array_map(
            fn (PageTemplate $template) => $template->key(),
            array_filter(self::all(), fn (PageTemplate $template) => ! $template->isSingleton()),
        ));
    }

    /**
     * Options for the admin template picker.
     *
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_values(array_map(
            fn (PageTemplate $template) => [
                'key' => $template->key(),
                'label' => $template->label(),
                'description' => $template->description(),
            ],
            array_filter(self::all(), fn (PageTemplate $template) => ! $template->isSingleton()),
        ));
    }
}
