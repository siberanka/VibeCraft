<?php

namespace App\Domain\Commerce;

use App\Domain\Minecraft\PlayerName;

/**
 * Product command templates are written by trusted staff, but the values substituted
 * into them come from players. Only typed placeholders with strict formats exist —
 * free text (product names, notes, user input) can never reach a console command.
 */
final class CommandTemplate
{
    public const PLACEHOLDERS = ['player', 'uuid', 'uuid_nodash', 'quantity', 'order', 'days'];

    /** @return list<string> validation errors (empty = valid) */
    public static function validate(string $template): array
    {
        $errors = [];
        if (trim($template) === '') {
            $errors[] = __('Command is empty.');
        }
        if (mb_strlen($template) > 255) {
            $errors[] = __('Command is longer than 255 characters.');
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $template)) {
            $errors[] = __('Command must be a single line without control characters.');
        }
        if (str_starts_with(ltrim($template), '/')) {
            $errors[] = __('Write the command without the leading slash.');
        }
        preg_match_all('/\{([^}]*)\}/', $template, $m);
        foreach ($m[1] as $name) {
            if (! in_array($name, self::PLACEHOLDERS, true)) {
                $errors[] = __('Unknown placeholder {:name}.', ['name' => $name]);
            }
        }

        return $errors;
    }

    /**
     * @param  array{player?: ?string, uuid?: ?string, quantity?: int, order?: string, days?: ?int}  $context
     */
    public static function render(string $template, array $context): string
    {
        $errors = self::validate($template);
        if ($errors !== []) {
            throw new CommerceException(implode(' ', $errors));
        }

        $player = (string) ($context['player'] ?? '');
        $uuid = (string) ($context['uuid'] ?? '');

        $values = [
            'player' => $player,
            'uuid' => $uuid,
            'uuid_nodash' => str_replace('-', '', $uuid),
            'quantity' => (string) max(1, (int) ($context['quantity'] ?? 1)),
            'order' => (string) ($context['order'] ?? ''),
            'days' => (string) max(0, (int) ($context['days'] ?? 0)),
        ];

        if (str_contains($template, '{player}') && ! PlayerName::isValid($player)) {
            throw new CommerceException(__('Player name is not valid for command delivery.'));
        }
        if ((str_contains($template, '{uuid}') || str_contains($template, '{uuid_nodash}')) && ! PlayerName::isValidUuid($uuid)) {
            throw new CommerceException(__('Player UUID is not valid for command delivery.'));
        }
        if (! preg_match('/^[0-9A-Za-z]*$/', $values['order'])) {
            throw new CommerceException('Invalid order reference.');
        }

        return preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => $values[$m[1]], $template);
    }
}
