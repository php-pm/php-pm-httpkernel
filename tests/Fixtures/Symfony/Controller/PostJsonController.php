<?php

namespace PHPPM\Tests\Fixtures\Symfony\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PostJsonController extends Controller
{
    /**
     * @Route("/json", methods={"POST"})
     */
    public function __invoke(Request $request)
    {
        $body = json_decode($request->getContent(), true);
        if ($request->getContent() == null || !$body) {
            throw new \Exception('Invalid JSON body');
        }

        return new Response('Received JSON: '.$request->getContent(), 201);
    }
}
