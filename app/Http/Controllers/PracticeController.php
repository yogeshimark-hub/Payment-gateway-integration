<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
 use App\Services\Practice\SimpleGreetingService;
 use App\Services\Practice\BoundGreetingService;

 use App\Facades\greetingWish;


class PracticeController extends Controller
{

    public function __construct(protected SimpleGreetingService $service){}
    

    public function simple()
    {
        $message = $this->service->greet('Sahil');

        return response($message);
    }

    // public function bound(BoundGreetingService $bound)
    public function bound()
  {
    //   $message = $bound->greet('Sahil');
      $message = greetingWish::greet('hello how are you ???');
      $message2 = greetingWish::getalreay();

      return response([$message,$message2]);
  }


  
}
