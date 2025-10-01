<?php

namespace App\Controller;

use App\Entity\Usuario;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

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

    #[Route('/usuario/nuevo', name: 'usuario_nuevo')]
    public function nuevo(Request $request, EntityManagerInterface $em): Response
    {
        $usuario = new Usuario();

        $form = $this->createFormBuilder($usuario)
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Contraseña',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('nombre', TextType::class, [
                'label' => 'Nombre',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('apellido1', TextType::class, [
                'label' => 'Primer apellido',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('apellido2', TextType::class, [
                'label' => 'Segundo apellido',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('telefono', TextType::class, [
                'label' => 'Teléfono',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('direccion', TextType::class, [
                'label' => 'Dirección',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('guardar', SubmitType::class, [
                'label' => 'Guardar usuario',
                'attr' => ['class' => 'btn btn-primary mt-3'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($usuario);
            $em->flush();

            return $this->redirectToRoute('app_usuario');
        }

        return $this->render('usuario/nuevo.html.twig', [
            'form' => $form->createView(),
            'titulo' => 'Nuevo usuario',
        ]);
    }

    #[Route('/usuario/{id}', name: 'usuario_visualizar')]
    public function visualizar(int $id, EntityManagerInterface $em): Response
    {
        $usuario = $em->getRepository(Usuario::class)->find($id);

        if (!$usuario) {
            throw $this->createNotFoundException('Usuario no encontrado');
        }

        return $this->render('usuario/visualizar.html.twig', [
            'usuario' => $usuario,
            'titulo' => 'Visualizar usuario',
        ]);
    }

    #[Route('/usuario/{id}/editar', name: 'usuario_editar')]
    public function editar(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $usuario = $em->getRepository(Usuario::class)->find($id);

        if (!$usuario) {
            throw $this->createNotFoundException('Usuario no encontrado');
        }

        $form = $this->createFormBuilder($usuario)
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['class' => 'form-control'],
            ])
            // Se elimina el campo de contraseña
            ->add('nombre', TextType::class, [
                'label' => 'Nombre',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('apellido1', TextType::class, [
                'label' => 'Primer apellido',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('apellido2', TextType::class, [
                'label' => 'Segundo apellido',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('telefono', TextType::class, [
                'label' => 'Teléfono',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('direccion', TextType::class, [
                'label' => 'Dirección',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('guardar', SubmitType::class, [
                'label' => 'Guardar cambios',
                'attr' => ['class' => 'btn btn-primary mt-3'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // La contraseña se mantiene igual, no se modifica
            $em->flush();
            return $this->redirectToRoute('app_usuario');
        }

        return $this->render('usuario/editar.html.twig', [
            'form' => $form->createView(),
            'titulo' => 'Editar usuario',
        ]);
    }

    #[Route('/usuario/{id}/eliminar', name: 'usuario_eliminar', methods: ['POST'])]
    public function eliminar(int $id, EntityManagerInterface $em): Response
    {
        $usuario = $em->getRepository(Usuario::class)->find($id);

        if (!$usuario) {
            throw $this->createNotFoundException('Usuario no encontrado');
        }

        $em->remove($usuario);
        $em->flush();

        return $this->redirectToRoute('app_usuario');
    }
}
