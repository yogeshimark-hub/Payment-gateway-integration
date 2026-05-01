<?php

namespace App\Services\Practice;

class SimpleGreetingService
{
    public function greet(string $name): string
    {
        return "Hello, {$name}! (from SimpleGreetingService — no binding)";
    }


    public function greet2(string $name): string
    {
        return "Hello, {$name}! (from SimpleGreetingService — no binding)";
    }
}
