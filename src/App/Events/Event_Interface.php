<?php
declare(strict_types=1);

namespace Gimli\Events;

interface Event_Interface {
    public function execute(string $event_name, array $args = []): void;
    
    public function validate(array $args): bool;
    
    public function getRequiredParameters(): array;
    
    public function getOptionalParameters(): array;
}