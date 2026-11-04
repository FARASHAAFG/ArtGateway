<?php

declare(strict_types=1);

namespace App\Helpers;

class RequestValidator
{
    private array $input;
    private array $validated = [];
    private array $errors = [];

    public function __construct(array $input)
    {
        $this->input = $input;
    }

    // Validate a string
    public function string(
        string $field,
        bool $required = false,
        int $maxLength = 255
    ): self {

        if (!array_key_exists($field, $this->input)) {
            if ($required) {
                $this->errors[$field] = "$field is required.";
            }

            return $this;
        }

        $value = $this->input[$field];

        if (!is_string($value)) {
            $this->errors[$field] = "$field must be a string.";
            return $this;
        }

        $value = trim($value);

        if ($value === '') {
            $this->errors[$field] = "$field cannot be empty.";
            return $this;
        }

        if (strlen($value) > $maxLength) {
            $this->errors[$field] =
                "$field cannot exceed $maxLength characters.";

            return $this;
        }

        $this->validated[$field] = $value;

        return $this;
    }

    // Validate an integer
    public function integer(
        string $field,
        bool $required = false,
        ?int $min = null,
        ?int $max = null
    ): self {

        if (!array_key_exists($field, $this->input)) {
            if ($required) {
                $this->errors[$field] = "$field is required.";
            }

            return $this;
        }

        $value = $this->input[$field];

        if (
            (!is_string($value) && !is_int($value)) ||
            filter_var($value, FILTER_VALIDATE_INT) === false
        ) {
            $this->errors[$field] = "$field must be an integer.";
            return $this;
        }

        $value = (int) $value;

        if ($min !== null && $value < $min) {
            $this->errors[$field] = "$field must be at least $min.";
            return $this;
        }

        if ($max !== null && $value > $max) {
            $this->errors[$field] = "$field cannot exceed $max.";
            return $this;
        }

        $this->validated[$field] = $value;

        return $this;
    }

    // Validate against an allowed list of values
    public function oneOf(
        string $field,
        array $allowed,
        ?string $default = null
    ): self {

        if (!array_key_exists($field, $this->input)) {
            if ($default !== null) {
                $this->validated[$field] = $default;
            }

            return $this;
        }

        $value = $this->input[$field];

        if (
            !is_string($value) ||
            !in_array(strtolower($value), $allowed, true)
        ) {
            $this->errors[$field] =
                "$field must be one of: " . implode(', ', $allowed);

            return $this;
        }

        $this->validated[$field] = strtolower($value);

        return $this;
    }

    // Return validated values
    public function getValidated(): array
    {
        return $this->validated;
    }

    // Return validation errors
    public function getErrors(): array
    {
        return $this->errors;
    }

    // Check whether validation failed
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
}