<?php

namespace PHPPM\Tests\Fixtures\Symfony\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GetController
{
    /**
     * @Route("/get")
     */
    public function __invoke()
    {
        return new Response('Success', 200);
    }
}
