<?php

class testgen {
    public function testGetGenerator(): \Generator
    {
        for ($i = 0; $i < 10; $i++) {
            yield $i;
        }
    }

    public function testGetArray(): array
    {
        return [1, 2, 3];
    }
}

$test = new testgen();
var_dump(gettype($test->testGetArray()));
var_dump(get_class($test->testGetGenerator()));

