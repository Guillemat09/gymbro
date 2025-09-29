<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UsuarioController extends AbstractController
{
    #[Route('/usuario', name: 'app_usuario')]
    public function index(): Response
    {
        return $this->render('usuario/index.html.twig', [
            'titulo' => 'GymBro Fitness Center',
            'descripcion' => 'El mejor gimnasio de la ciudad, equipado con la última tecnología y entrenadores profesionales para ayudarte a alcanzar tus objetivos.',
        ]);
    }
}
