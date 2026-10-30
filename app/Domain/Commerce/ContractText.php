<?php

namespace App\Domain\Commerce;

use App\Support\Money;

/** Rendered once at capture; downloads and notifications use the stored bytes. */
final class ContractText
{
    public static function render(array $snapshot, string $recordedAt): string
    {
        $locale = $snapshot['locale'];
        $label = static fn (string $key) => __($key, [], $locale);
        $review = $snapshot['review'];
        $currency = $review['currency'];
        $money = static fn (int $minor) => Money::formatForLocale($minor, $currency, $locale);
        $lines = [$label('Order confirmation'), $snapshot['order_public_id'], $recordedAt, '',
            $label('Country or territory').': '.$snapshot['country'].($snapshot['subdivision'] !== '' ? '-'.$snapshot['subdivision'] : ''),
            $label('Document language').': '.$locale, ''];
        foreach ($snapshot['trader'] as $value) {
            if ($value !== '') {
                $lines[] = $value;
            }
        }
        $lines[] = '';
        foreach ($review['lines'] as $line) {
            $lines[] = $line['name'].' / '.$label('Quantity').': '.$line['quantity'];
            $lines[] = $label('Unit price').': '.$money($line['unit_price_minor']);
            $lines[] = $label('Total').': '.$money($line['total_minor']);
            if ($line['duration_days'] !== null) {
                $lines[] = __('Duration per unit: :days days', ['days' => $line['duration_days']], $locale);
            }
            if ($line['summary'] !== '') {
                $lines[] = $line['summary'];
            }
            if ($line['description_markdown'] !== '') {
                $lines[] = $line['description_markdown'];
            }
            $lines[] = '';
        }
        if ($review['context'] === 'topup') {
            $lines[] = $label('Balance top-up');
            if (($review['bonus_minor'] ?? 0) > 0) {
                $lines[] = $label('Bonus credit').': '.$money($review['bonus_minor']);
            }
        }
        if (isset($review['gift_recipient']['name'])) {
            $lines[] = $label('Gift for').': '.$review['gift_recipient']['name'];
        }
        if ($review['minecraft_target']) {
            $lines[] = $label('Minecraft delivery account').': '.$review['minecraft_target']['username'].' / '.$review['minecraft_target']['edition'];
        }
        $lines[] = $label('Subtotal').': '.$money($review['subtotal_minor']);
        if ($review['discount_minor'] > 0) {
            $lines[] = $label('Discount').': '.$money($review['discount_minor']);
        }
        $lines[] = $label('Payment method').': '.$review['method'];
        $lines[] = $label('Total payable').': '.$money($review['total_minor']);
        $lines[] = '';
        foreach ($snapshot['documents'] as $document) {
            $lines[] = $document['title'];
            $lines[] = __('Published version :number', ['number' => $document['version']], $locale).' / '.$document['locale'];
            $lines[] = $label($document['action'] === 'accepted' ? 'Accepted' : 'Provided for information').': '.$document['recorded_at'];
            $lines[] = $document['body_markdown'];
            $lines[] = '';
        }

        return implode("\n", $lines)."\n";
    }
}
