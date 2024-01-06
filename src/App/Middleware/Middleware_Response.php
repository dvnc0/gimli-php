<?php
declare(strict_types=1);

namespace Gimli\Middleware;

class Middleware_Response {
    public bool $success;
    public string $message;
    public string $forward;
    public function __construct(bool $success, string $message = '', string $forward = '') {
        $this->success = $success;
        $this->message = $message;
        $this->forward = $forward;
    }
}