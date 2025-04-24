<?php

namespace Tests\Fixtures;

use Predis\Client;

class MockClientRedis extends Client
{
    public function __construct($parameters = null, $options = null)
    {
        parent::__construct($parameters, $options);
    }

    public function rpush(string $key, array $values): int
    {
        return 1;
    }
}