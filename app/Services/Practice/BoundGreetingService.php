<?php
namespace App\Services\Practice;

  class BoundGreetingService
  {
    //   public function __construct(
    //       private string $prefix,
    //       private string $suffix,
    //   ) {}

      public function greet(string $name): string
      {
          return " {$name}! ";
      }


    public function getalreay()
    {
        return 'alreay here';
    }


  }

