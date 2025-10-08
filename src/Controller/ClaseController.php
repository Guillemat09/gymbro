<?php

namespace App\Controller;

use App\Entity\Profesor;
use App\Entity\Clase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

final class ClaseController extends AbstractController
{
    #[Route('/clase', name: 'app_clase')]
    public function index(EntityManagerInterface $em): Response
    {
        $clases = $em->getRepository(Clase::class)->findAll();

        return $this->render('clase/index.html.twig', [
            'clases' => $clases,
            'titulo' => 'Listado de clases',
        ]);
    }

    #[Route('/clase/nueva', name: 'clase_nueva')]
    public function nueva(Request $request, EntityManagerInterface $em): Response
    {
        $clase = new Clase();

        $form = $this->createFormBuilder($clase)
            ->add('nombre', TextType::class, [
                'label' => 'Nombre de la clase',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('fecha', DateType::class, [
                'label' => 'Fecha',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('hora', TimeType::class, [
                'label' => 'Hora',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('duracion', IntegerType::class, [
                'label' => 'Duración (minutos)',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('lugar', TextType::class, [
                'label' => 'Lugar',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('limite', IntegerType::class, [
                'label' => 'Límite de alumnos',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('profesor', EntityType::class, [
                'class' => Profesor::class,
                'choice_label' => function (Profesor $profesor) {
                    $usuario = $profesor->getUsuario();
                    return $usuario ? $usuario->getNombre() . ' ' . $usuario->getApellido1() : 'Sin nombre';
                },
                'label' => 'Profesor',
                'placeholder' => 'Selecciona un profesor',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('guardar', SubmitType::class, [
                'label' => 'Guardar clase',
                'attr' => ['class' => 'btn btn-primary mt-3'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($clase);
            $em->flush();

            return $this->redirectToRoute('app_clase');
        }

        return $this->render('clase/nueva.html.twig', [
            'form' => $form->createView(),
            'titulo' => 'Nueva clase',
        ]);
    }

    #[Route('/clase/{id}/editar', name: 'clase_editar')]
    public function editar(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $clase = $em->getRepository(Clase::class)->find($id);

        if (!$clase) {
            throw $this->createNotFoundException('Clase no encontrada');
        }

        $form = $this->createFormBuilder($clase)
            ->add('nombre', TextType::class, [
                'label' => 'Nombre de la clase',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('fecha', DateType::class, [
                'label' => 'Fecha',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('hora', TimeType::class, [
                'label' => 'Hora',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('duracion', IntegerType::class, [
                'label' => 'Duración (minutos)',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('lugar', TextType::class, [
                'label' => 'Lugar',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('limite', IntegerType::class, [
                'label' => 'Límite de alumnos',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('profesor', EntityType::class, [
                'class' => Profesor::class,
                'choice_label' => function (Profesor $profesor) {
                    $usuario = $profesor->getUsuario();
                    return $usuario ? $usuario->getNombre() . ' ' . $usuario->getApellido1() : 'Sin nombre';
                },
                'label' => 'Profesor',
                'placeholder' => 'Selecciona un profesor',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('guardar', SubmitType::class, [
                'label' => 'Guardar cambios',
                'attr' => ['class' => 'btn btn-primary mt-3'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute('app_clase');
        }

        return $this->render('clase/editar.html.twig', [
            'form' => $form->createView(),
            'titulo' => 'Editar clase',
        ]);
    }
}
