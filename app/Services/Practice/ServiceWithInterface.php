<?php
namespace App\Services\Practice;
use App\Contracts\HelpingInterface;

  class ServiceWithInterface implements HelpingInterface
  {
    //   public function __construct(
    //       private string $prefix,
    //       private string $suffix,
    //   ) {}

      public function interfacegreet(string $name): string
      {
          return " interface call you {$name}! ";
      }


    public function interfacegetalreay()
    {
        return 'interface call this method is called successfully';
    }

    public function interfacegetalreayFacadecall()
    {
        return 'interface call this method is called successfully by facade';
    }

  }