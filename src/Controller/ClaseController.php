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
use Knp\Component\Pager\PaginatorInterface; // ✅ IMPORTANTE: Añadido para la paginación

final class ClaseController extends AbstractController
{
#[Route('/clase', name: 'app_clase')]
public function index(EntityManagerInterface $em): Response
{
    // Obtener todas las clases sin filtros ni paginación
    $clases = $em->getRepository(Clase::class)->createQueryBuilder('c')
        ->leftJoin('c.profesor', 'p')
        ->leftJoin('p.usuario', 'u')
        ->addSelect('p', 'u')
        ->addOrderBy('c.fecha', 'ASC')
        ->addOrderBy('c.hora', 'ASC')
        ->getQuery()
        ->getResult();

    return $this->render('clase/index.html.twig', [
        'clases' => $clases,
        'titulo' => 'Listado de clases',
    ]);
}

#[Route('/clase/nueva', name: 'clase_nueva')]
public function nueva(Request $request, EntityManagerInterface $em): Response
{
    $clase = new Clase();

    // Detectar si es profesor y obtener su entidad Profesor (si existe)
    $esProfesor = $this->isGranted('ROLE_PROFESOR');
    $profesorActual = null;
    if ($esProfesor && \method_exists($this->getUser(), 'getProfesor')) {
        $profesorActual = $this->getUser()->getProfesor();
        if ($profesorActual) {
            // Precargar en la entidad para que el form ya lo tenga
            $clase->setProfesor($profesorActual);
        }
    }

    // Builder base
    $builder = $this->createFormBuilder($clase)
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
        ]);

    // Campo PROFESOR con opciones según rol
    if ($this->isGranted('ROLE_ADMIN')) {
        // Admin: selector completo
        $builder->add('profesor', EntityType::class, [
            'class' => Profesor::class,
            'choice_label' => function (Profesor $profesor) {
                $u = $profesor->getUsuario();
                return $u ? $u->getNombre() . ' ' . $u->getApellido1() : 'Sin nombre';
            },
            'label' => 'Profesor',
            'placeholder' => 'Selecciona un profesor',
            'attr' => ['class' => 'form-select'],
        ]);
    } else {
        // Profesor (u otros roles): limitar a su propio registro si existe
        $builder->add('profesor', EntityType::class, [
            'class' => Profesor::class,
            'choices' => $profesorActual ? [$profesorActual] : [],
            'data' => $profesorActual, // precarga
            'choice_label' => function (Profesor $profesor) {
                $u = $profesor->getUsuario();
                return $u ? $u->getNombre() . ' ' . $u->getApellido1() : 'Sin nombre';
            },
            'label' => 'Profesor',
            'placeholder' => $profesorActual ? false : 'Sin profesor asociado',
            'attr' => ['class' => 'form-select'],
        ]);
    }

    $builder->add('guardar', SubmitType::class, [
        'label' => 'Guardar clase',
        'attr' => ['class' => 'btn btn-primary mt-3'],
    ]);

    $form = $builder->getForm();
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        // Blindaje: si es profesor, forzar su propio profesor
        if ($esProfesor) {
            if (!$profesorActual) {
                $this->addFlash('danger', 'No se encontró la ficha de profesor asociada al usuario.');
                return $this->redirectToRoute('app_clase');
            }
            $clase->setProfesor($profesorActual);
        }

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

    // --- Contexto de rol/profesor actual ---
    $esProfesor = $this->isGranted('ROLE_PROFESOR');
    $profesorActual = null;
    if ($esProfesor && \method_exists($this->getUser(), 'getProfesor')) {
        $profesorActual = $this->getUser()->getProfesor();
    }

    // Si es profesor, NO permitir editar clases de otro profesor
    if ($esProfesor) {
        if (!$profesorActual || ($clase->getProfesor() && $clase->getProfesor()->getId() !== $profesorActual->getId())) {
            throw $this->createAccessDeniedException('No puedes editar una clase de otro profesor.');
        }
    }

    // --- Construcción del formulario ---
    $builder = $this->createFormBuilder($clase)
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
        ]);

    if ($this->isGranted('ROLE_ADMIN')) {
        // Admin: selector completo
        $builder->add('profesor', EntityType::class, [
            'class' => Profesor::class,
            'choice_label' => function (Profesor $profesor) {
                $u = $profesor->getUsuario();
                return $u ? $u->getNombre() . ' ' . $u->getApellido1() : 'Sin nombre';
            },
            'label' => 'Profesor',
            'placeholder' => 'Selecciona un profesor',
            'attr' => ['class' => 'form-select'],
        ]);
    } else {
        // Profesor: limitar a su propio registro y precargar
        $builder->add('profesor', EntityType::class, [
            'class' => Profesor::class,
            'choices' => $profesorActual ? [$profesorActual] : [],
            'data' => $profesorActual ?: $clase->getProfesor(),
            'choice_label' => function (Profesor $profesor) {
                $u = $profesor->getUsuario();
                return $u ? $u->getNombre() . ' ' . $u->getApellido1() : 'Sin nombre';
            },
            'label' => 'Profesor',
            'placeholder' => $profesorActual ? false : 'Sin profesor asociado',
            'attr' => ['class' => 'form-select'],
        ]);
    }

    $builder->add('guardar', SubmitType::class, [
        'label' => 'Guardar cambios',
        'attr' => ['class' => 'btn btn-primary mt-3'],
    ]);

    $form = $builder->getForm();
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        // Blindaje: si es profesor, forzar su propio Profesor
        if ($esProfesor) {
            if (!$profesorActual) {
                $this->addFlash('danger', 'No se encontró la ficha de profesor asociada al usuario.');
                return $this->redirectToRoute('app_clase');
            }
            $clase->setProfesor($profesorActual);
        }

        $em->flush();
        return $this->redirectToRoute('app_clase');
    }

    return $this->render('clase/editar.html.twig', [
        'form' => $form->createView(),
        'titulo' => 'Editar clase',
    ]);
}

    #[Route('/clase/{id}', name: 'clase_visualizar')]
    public function visualizar(int $id, EntityManagerInterface $em): Response
    {
        $clase = $em->getRepository(Clase::class)->find($id);

        if (!$clase) {
            throw $this->createNotFoundException('Clase no encontrada');
        }

        return $this->render('clase/visualizar.html.twig', [
            'clase' => $clase,
            'titulo' => 'Visualizar clase',
        ]);
    }

    #[Route('/clase/{id}/eliminar', name: 'clase_eliminar', methods: ['POST'])]
    public function eliminar(int $id, EntityManagerInterface $em): Response
    {
        $clase = $em->getRepository(Clase::class)->find($id);

        if (!$clase) {
            throw $this->createNotFoundException('Clase no encontrada');
        }

        $em->remove($clase);
        $em->flush();

        return $this->redirectToRoute('app_clase');
    }
}
