<?php

namespace PHPPM\Tests\Fixtures\Symfony\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class StreamedController extends Controller
{
    /**
     * @Route("/streamed")
     */
    public function __invoke()
    {
        return new StreamedResponse(function () {
            echo 'streamed data';
        }, 200);
    }
}
