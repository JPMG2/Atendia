<?php

declare(strict_types=1);

namespace App\Classes\Main;

/**
 * Composes the simulated WhatsApp conversation the previews paint: the
 * wizard rail builds it from the in-progress answers, the dashboard
 * simulator from the persisted offer — one builder, one voice.
 */
class AssistantPreview
{
    /**
     * Values are escaped HERE: the phone paints raw HTML.
     *
     * @param  list<string>  $services
     * @param  list<string>  $products
     * @return list<array{type: string, who: string, html: string}>
     */
    public static function messages(string $businessName, array $services = [], array $products = [], bool $connected = false): array
    {
        if ($businessName === '') {
            return [];
        }

        $business = e($businessName);

        $messages = [
            ['type' => 'in', 'who' => __('wizard.phone.client'), 'html' => __('wizard.phone.q_open')],
            [
                'type' => 'out',
                'who' => __('wizard.phone.assistant_of', ['business' => $business]),
                'html' => __('wizard.phone.a_open', ['business' => $business]),
            ],
        ];

        if ($services !== []) {
            $list = '<b>'.implode('</b>, <b>', array_map(e(...), $services)).'</b>';

            // The name rides into the question EXACTLY as loaded: lowering it
            // read as the system mangling a proper noun ("Yuca" → "yuca").
            $messages[] = ['type' => 'in', 'who' => __('wizard.phone.client'), 'html' => __('wizard.phone.q_service', ['service' => e((string) end($services))])];
            $messages[] = ['type' => 'out', 'who' => __('wizard.phone.assistant'), 'html' => __('wizard.phone.a_service', ['business' => $business, 'services' => $list])];
        }

        if ($products !== []) {
            // Always something the business actually loaded — a manual pill
            // or the sheet's own first row, never a canned demo product.
            $messages[] = ['type' => 'in', 'who' => __('wizard.phone.client'), 'html' => __('wizard.phone.q_product_named', ['product' => e((string) end($products))])];
            $messages[] = ['type' => 'out', 'who' => __('wizard.phone.assistant'), 'html' => __('wizard.phone.a_product')];
        }

        if ($connected) {
            $messages[] = ['type' => 'out', 'who' => __('wizard.phone.assistant'), 'html' => __('wizard.phone.connected')];
        }

        return $messages;
    }
}
