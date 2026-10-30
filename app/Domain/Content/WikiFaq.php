<?php

namespace App\Domain\Content;

use Illuminate\Validation\ValidationException;

final class WikiFaq
{
    public static function validation(): array
    {
        return [
            'faqs' => ['nullable', 'array', 'list', 'max:10'],
            'faqs.*' => ['required', 'array:question,answer'],
            'faqs.*.question' => ['present', 'nullable', 'string', 'max:160'],
            'faqs.*.answer' => ['present', 'nullable', 'string', 'max:2000'],
        ];
    }

    /** Normalize already validated plain text; an empty pair removes a row. */
    public static function normalize(?array $rows): array
    {
        $faqs = [];
        foreach ($rows ?? [] as $index => $row) {
            $question = trim($row['question'] ?? '');
            $answer = trim($row['answer'] ?? '');
            if ($question === '' && $answer === '') {
                continue;
            }
            if ($question === '' || $answer === '') {
                $field = $question === '' ? 'question' : 'answer';
                throw ValidationException::withMessages(['faqs.'.$index.'.'.$field => __('Complete both the question and answer, or leave both empty.')]);
            }
            $faqs[] = ['question' => $question, 'answer' => $answer];
        }

        return $faqs;
    }

    /** Imported malformed rows must not become markup or break a public page. */
    public static function entries(?array $rows): array
    {
        return array_values(array_filter(array_slice($rows ?? [], 0, 10), static fn ($row) => is_array($row)
            && is_string($row['question'] ?? null) && trim($row['question']) !== '' && mb_strlen($row['question']) <= 160
            && is_string($row['answer'] ?? null) && trim($row['answer']) !== '' && mb_strlen($row['answer']) <= 2000));
    }
}
