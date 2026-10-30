<?php

namespace App\Domain\Content;

use App\Models\WikiArticle;
use App\Models\WikiCategory;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

final class WikiReaderRules
{
    private const POLICIES = ['public', 'members', 'roles'];

    public function validation(): array
    {
        return [
            'reader_access' => ['sometimes', 'required', 'string', Rule::in(self::POLICIES)],
            'reader_roles' => ['nullable', 'array', 'max:32'],
            'reader_roles.*' => ['required', 'string', 'max:255', 'distinct', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }

    /** Resolve omitted legacy fields only after the current record has been locked. */
    public function normalize(array $data, WikiArticle|WikiCategory|null $record): array
    {
        $access = $data['reader_access'] ?? $record->reader_access ?? 'public';
        if (! in_array($access, self::POLICIES, true)) {
            throw ValidationException::withMessages(['reader_access' => __('Choose a valid reader access rule.')]);
        }
        $roles = array_key_exists('reader_roles', $data) ? ($data['reader_roles'] ?? [])
            : (array_key_exists('reader_access', $data) ? [] : ($record->reader_roles ?? []));
        if ($access !== 'roles') {
            $roles = [];
        } elseif ($roles === []) {
            throw ValidationException::withMessages(['reader_roles' => __('Choose at least one reader role.')]);
        }
        if (! is_array($roles) || count($roles) > 32) {
            throw ValidationException::withMessages(['reader_roles' => __('Choose valid reader roles.')]);
        }
        foreach ($roles as $role) {
            if (! is_string($role)) {
                throw ValidationException::withMessages(['reader_roles' => __('Choose valid reader roles.')]);
            }
        }
        $roles = array_values(array_unique($roles));
        sort($roles, SORT_STRING);
        $data['reader_access'] = $access;
        $data['reader_roles'] = $roles;

        return $data;
    }

    public function fields(WikiArticle|WikiCategory|null $record): array
    {
        $roles = Role::where('guard_name', 'web')->orderBy('name')->pluck('name')->mapWithKeys(fn (string $name) => [$name => __(Str::headline($name))])->all();

        return [
            'reader_access' => [
                'label' => __('Readers'), 'type' => 'select', 'value' => $record->reader_access ?? 'public', 'required' => true,
                'options' => ['' => 'Choose access', 'public' => 'Everyone', 'members' => 'Verified members', 'roles' => 'Selected roles (verified accounts)'],
                'hint' => __('Category and parent page restrictions also apply. Content managers can still edit and preview content in administration.'),
            ],
            'reader_roles' => [
                'label' => __('Reader roles'), 'type' => 'checkboxes', 'value' => $record->reader_roles ?? [], 'options' => $roles,
                'hint' => __('Choose roles when using Selected roles. Access rules protect the page; linked images and files retain their own permissions.'),
            ],
        ];
    }

    public function summary(WikiArticle|WikiCategory|null $record): array
    {
        return ['access' => $record->reader_access ?? 'public', 'roles' => $record->reader_roles ?? []];
    }
}
