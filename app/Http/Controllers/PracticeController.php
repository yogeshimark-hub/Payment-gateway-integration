<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
 use App\Services\Practice\SimpleGreetingService;
 use App\Services\Practice\BoundGreetingService;

 use App\Facades\greetingWish;
 use App\Traits\HelpingMethods;
use App\Facades\HelpingFunction;
use App\Contracts\HelpingInterface;

class PracticeController extends Controller
{
    use HelpingMethods;
    public function __construct(protected SimpleGreetingService $service,protected HelpingInterface $interfacemethod){}
    

    public function simple()
    {
        $message = $this->service->greet('Sahil');

        return response($message);
    }

    // public function bound(BoundGreetingService $bound)
    public function bound()
  {
    //   $message = $bound->greet('Sahil');
      // $message = greetingWish::greet('hello how are you ???');
      // $message2 = greetingWish::getalreay();
      // $message3 = $this->helpingMethod();
      $message4= HelpingFunction::interfacegetalreayFacadecall('call facade for using interface method');
      // $mesasge5 = $this->interfacemethod->interfacegreet('called this interfarce method');
      // $message6 = $this->interfacemethod->interfacegetalreay();

      return response([$message4]);
  }


  
}
