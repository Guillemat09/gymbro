<?php

namespace App\Controller;

use App\Entity\Usuario;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;

class UsuarioController extends AbstractController
{
    #[Route('/usuario', name: 'app_usuario')]
    public function index(EntityManagerInterface $em): Response
    {
        $usuarios = $em->getRepository(Usuario::class)->findAll();

        return $this->render('usuario/index.html.twig', [
            'usuarios' => $usuarios,
            'titulo' => 'GymBro - Usuarios',
        ]);
    }
}
