<?php

namespace PHPPM\Tests\Fixtures\Symfony\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GetController extends Controller
{
    /**
     * @Route("/get")
     */
    public function __invoke()
    {
        return new Response('Success', 200);
    }
}
