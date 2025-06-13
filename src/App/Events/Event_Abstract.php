<?php
declare(strict_types=1);

namespace Gimli\Events;

abstract class Event_Abstract implements Event_Interface {
    public function validate(array $args): bool {
        return true;
    }

    public function getRequiredParameters(): array {
        return [];
    }

    public function getOptionalParameters(): array {
        return [];
    }
}