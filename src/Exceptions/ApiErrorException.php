<?php

namespace Bayarcash\Exceptions;

/**
 * Raised for every error status without its own exception (401, 403, 409, 5xx,
 * a 504 page from a proxy, ...). getMessage() is the API's raw body, unchanged;
 * getCode() and status() are the HTTP status. It extends \Exception, so an
 * existing `catch (\Exception $e)` still catches it.
 */
class ApiErrorException extends \Exception
{
    /**
     * The raw response body.
     *
     * @var string
     */
    public $body;

    /**
     * The decoded JSON body, or [] when the body is not a JSON object.
     *
     * @var array
     */
    public $errors = [];

    public function __construct(int $status, string $body, ?\Throwable $previous = null)
    {
        $this->body = $body;

        $decoded = json_decode($body, true);
        $this->errors = is_array($decoded) ? $decoded : [];

        parent::__construct($body, $status, $previous);
    }

    /** The HTTP status. */
    public function status(): int
    {
        return $this->getCode();
    }

    /** The raw response body. */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * The decoded JSON body, or [] when the body is not a JSON object.
     *
     * @return array
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * `error.code` of the recurring error envelope `{"error": {"code", "message", ...}}`
     * (e.g. `save_card_refused`, `upstream_conflict`), or null.
     */
    public function errorCode(): ?string
    {
        if (isset($this->errors['error']) && is_array($this->errors['error']) && isset($this->errors['error']['code'])) {
            return (string) $this->errors['error']['code'];
        }

        return null;
    }
}
