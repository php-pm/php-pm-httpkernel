<?php

namespace PHPPM\Tests\Fixtures\Symfony\Controller;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class StreamedController
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
