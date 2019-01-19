<?php

namespace PHPPM\Tests\Fixtures\Symfony\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UploadController extends Controller
{
    /**
     * @Route("/upload", methods={"POST"})
     */
    public function __invoke(Request $request)
    {
        $mappedFileNames = array_map(function ($f) {
            if (!isset($f)) {
                return 'NULL';
            }
            return $f->getClientOriginalName();
        }, $request->files->all());

        return new Response('Uploaded files: '.implode(',', $mappedFileNames), 201);
    }
}
