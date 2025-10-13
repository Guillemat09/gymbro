<?php

namespace App\Controller;

use App\Entity\Alumno;
use App\Entity\Ejercicio;
use App\Entity\RutinaEjercicios;
use App\Entity\Rutina;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

final class RutinaController extends AbstractController
{
    #[Route('/rutina', name: 'app_rutina')]
    public function index(EntityManagerInterface $em): Response
    {
        $rutinas = $em->getRepository(Rutina::class)->findAll();

        return $this->render('rutina/index.html.twig', [
            'rutinas' => $rutinas,
            'titulo' => 'Listado de rutinas',
        ]);
    }

    #[Route('/rutina/nueva', name: 'rutina_nueva')]
    public function nueva(Request $request, EntityManagerInterface $em): Response
    {
        $rutina = new Rutina();

        $form = $this->createFormBuilder($rutina)
            ->add('alumno', EntityType::class, [
                'class' => Alumno::class,
                'choice_label' => function (Alumno $alumno) {
                    $usuario = $alumno->getUsuario();
                    return $usuario ? $usuario->getNombre() . ' ' . $usuario->getApellido1() . ' ' . $usuario->getApellido2() : 'Sin nombre';
                },
                'label' => 'Alumno',
                'placeholder' => 'Selecciona un alumno',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('nombre', TextType::class, [
                'label' => 'Nombre de la rutina',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('guardar', SubmitType::class, [
                'label' => 'Guardar rutina',
                'attr' => ['class' => 'btn btn-primary mt-3'],
            ])
            ->getForm();

        $form->handleRequest($request);

        // Procesar ejercicios añadidos manualmente
        $ejerciciosRutina = [];
        if ($request->isMethod('POST')) {
            $ejerciciosData = $request->request->all('ejercicios');
            $orden = 1;
            foreach ($ejerciciosData ?? [] as $ejData) {
                if (!empty($ejData['ejercicio']) && !empty($ejData['repeticiones'])) {
                    $ejercicio = $em->getRepository(Ejercicio::class)->find($ejData['ejercicio']);
                    if ($ejercicio) {
                        $rutinaEjercicio = new RutinaEjercicios();
                        $rutinaEjercicio->setEjercicio($ejercicio);
                        $rutinaEjercicio->setRepeticiones((int)$ejData['repeticiones']);
                        $rutinaEjercicio->setOrden($orden++);
                        $ejerciciosRutina[] = $rutinaEjercicio;
                    }
                }
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($rutina);
            foreach ($ejerciciosRutina as $rutinaEjercicio) {
                $rutinaEjercicio->setRutina($rutina);
                $em->persist($rutinaEjercicio);
            }
            $em->flush();

            return $this->redirectToRoute('app_rutina');
        }

        // Obtener todos los ejercicios para el desplegable
        $ejercicios = $em->getRepository(Ejercicio::class)->findAll();

        return $this->render('rutina/nueva.html.twig', [
            'form' => $form->createView(),
            'titulo' => 'Nueva rutina',
            'ejercicios' => $ejercicios,
            'ejerciciosRutina' => $ejerciciosRutina,
        ]);
    }
}
