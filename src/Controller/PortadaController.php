<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PortadaController extends AbstractController
{
    #[Route('/', name: 'app_portada')]
    public function index(): Response
    {
        return $this->render('portada/index.html.twig');
    }
}
