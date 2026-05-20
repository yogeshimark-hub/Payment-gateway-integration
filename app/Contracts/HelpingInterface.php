<?php

namespace App\Contracts;

interface HelpingInterface
{
    public function interfacegreet(string $name): string;
    public function interfacegetalreay();
    public function interfacegetalreayFacadecall();
}
