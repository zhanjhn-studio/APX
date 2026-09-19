<?php
declare(strict_types=1);
namespace App\Core;

/**
 * 轻量校验器。规则以竖线分隔的字符串声明：
 * required|email|min:3|max:32|length:6|numeric|int|in:a,b|same:field|confirm|username|url
 */
class Validator
{
    private array $errors = [];
    private array $data = [];

    public function validate(array $data, array $rules): self
    {
        $this->data = $data;
        $this->rulesCache = $rules;
        $this->errors = [];
        foreach ($rules as $field => $ruleStr) {
            $value = $data[$field] ?? null;
            foreach (explode('|', (string) $ruleStr) as $rule) {
                if ($rule === '') {
                    continue;
                }
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                if (!self::check($name, $value, $param, $field)) {
                    $this->errors[$field] = self::error($name, $field, $param);
                    break;
                }
            }
        }
        return $this;
    }

    private function check(string $name, $value, ?string $param, string $field): bool
    {
        $required = in_array('required', explode('|', $this->ruleFor($field)), true);
        $empty = $value === null || $value === '' || (is_string($value) && trim($value) === '');

        if ($name !== 'required' && !$required && $empty) {
            return true; // 非必填且为空，跳过其余规则
        }

        switch ($name) {
            case 'required':
                return !$empty;
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) !== false;
            case 'numeric':
                return is_numeric($value);
            case 'int':
                return ctype_digit((string) $value) || (is_numeric($value) && (int) $value == $value);
            case 'min':
                return mb_strlen((string) $value, 'UTF-8') >= (int) $param;
            case 'max':
                return mb_strlen((string) $value, 'UTF-8') <= (int) $param;
            case 'length':
                return mb_strlen((string) $value, 'UTF-8') === (int) $param;
            case 'username':
                return (bool) preg_match('/^[a-zA-Z0-9_]{3,32}$/', (string) $value);
            case 'in':
                return in_array((string) $value, explode(',', (string) $param), true);
            case 'same':
                return ($value ?? '') === ($this->data[$param] ?? '');
            case 'confirm':
                return ($value ?? '') === ($this->data[$field . '_confirm'] ?? '');
            default:
                return true;
        }
    }

    private function ruleFor(string $field): string
    {
        return $this->rulesCache[$field] ?? '';
    }

    private array $rulesCache = [];

    public function setRules(array $rules): self
    {
        $this->rulesCache = $rules;
        return $this;
    }

    private function error(string $name, string $field, ?string $param): string
    {
        $messages = [
            'required' => 'validation.required',
            'email'    => 'validation.email',
            'url'      => 'validation.url',
            'numeric'  => 'validation.numeric',
            'int'      => 'validation.int',
            'min'      => 'validation.min',
            'max'      => 'validation.max',
            'length'   => 'validation.length',
            'username' => 'validation.username',
            'in'       => 'validation.in',
            'same'     => 'validation.same',
            'confirm'  => 'validation.confirm',
        ];
        return $messages[$name] ?? ('validation.' . $name);
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
