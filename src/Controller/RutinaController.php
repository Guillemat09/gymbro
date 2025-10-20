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
    public function index(
        EntityManagerInterface $em,
        Request $request,
        \Knp\Component\Pager\PaginatorInterface $paginator // FQCN => sin problemas de autowiring
    ): Response {
        // Tamaños por página
        $perPageOptions = [10, 25, 50, 100];
        $perPage = (int) $request->query->get('per_page', 10);
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 10;
        }
        $page = max(1, $request->query->getInt('page', 1));

        // ===== Filtros =====
        $q        = trim((string) $request->query->get('q', '')); // texto: nombre de rutina o alumno
        $alumnoId = $request->query->getInt('alumno_id', 0);

        // ===== Query base =====
        $qb = $em->getRepository(Rutina::class)->createQueryBuilder('r')
            ->leftJoin('r.alumno', 'a')
            ->leftJoin('a.usuario', 'u')
            ->addSelect('a', 'u');

        if ($q !== '') {
            $qb->andWhere('LOWER(r.nombre) LIKE :q
                        OR LOWER(u.nombre) LIKE :q
                        OR LOWER(u.apellido1) LIKE :q
                        OR LOWER(u.apellido2) LIKE :q
                        OR LOWER(u.email) LIKE :q')
               ->setParameter('q', '%'.mb_strtolower($q).'%');
        }

        if ($alumnoId > 0) {
            $qb->andWhere('a.id = :alumnoId')->setParameter('alumnoId', $alumnoId);
        }

        // Orden por defecto (sobrescribible por ?sort=&direction=)
        $qb->addOrderBy('r.id', 'DESC');

        // Paginación
        $pagination = $paginator->paginate(
            $qb,
            $page,
            $perPage
        );

        // Selector de alumnos para filtros
        $alumnos = $em->getRepository(Alumno::class)->createQueryBuilder('al')
            ->leftJoin('al.usuario', 'uu')->addSelect('uu')
            ->orderBy('uu.nombre', 'ASC')
            ->getQuery()->getResult();

        return $this->render('rutina/index.html.twig', [
            'rutinas'        => $pagination,
            'titulo'         => 'Listado de rutinas',
            'per_page'       => $perPage,
            'perPageOptions' => $perPageOptions,
            'q'              => $q,
            'alumno_id'      => $alumnoId,
            'alumnos'        => $alumnos,
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
                    return $usuario ? $usuario->getNombre() . ' ' . $usuario->getApellido1() . ' ' . ($usuario->getApellido2() ?? '') : 'Sin nombre';
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
                        $rutinaEjercicio->setRepeticiones((int) $ejData['repeticiones']);
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

    #[Route('/rutina/{id}/editar', name: 'rutina_editar')]
    public function editar(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $rutina = $em->getRepository(Rutina::class)->find($id);

        if (!$rutina) {
            throw $this->createNotFoundException('Rutina no encontrada');
        }

        $form = $this->createFormBuilder($rutina)
            ->add('alumno', EntityType::class, [
                'class' => Alumno::class,
                'choice_label' => function (Alumno $alumno) {
                    $usuario = $alumno->getUsuario();
                    return $usuario ? $usuario->getNombre() . ' ' . $usuario->getApellido1() . ' ' . ($usuario->getApellido2() ?? '') : 'Sin nombre';
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
                'label' => 'Guardar cambios',
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
                        $rutinaEjercicio->setRepeticiones((int) $ejData['repeticiones']);
                        $rutinaEjercicio->setOrden($orden++);
                        $ejerciciosRutina[] = $rutinaEjercicio;
                    }
                }
            }
        } else {
            // Cargar ejercicios actuales de la rutina
            foreach ($rutina->getRutinaEjercicios() as $rutinaEjercicio) {
                $ejerciciosRutina[] = $rutinaEjercicio;
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Eliminar ejercicios anteriores
            foreach ($rutina->getRutinaEjercicios() as $rutinaEjercicio) {
                $em->remove($rutinaEjercicio);
            }
            $em->flush();

            // Añadir los nuevos ejercicios
            foreach ($ejerciciosRutina as $rutinaEjercicio) {
                $rutinaEjercicio->setRutina($rutina);
                $em->persist($rutinaEjercicio);
            }
            $em->flush();

            return $this->redirectToRoute('app_rutina');
        }

        $ejercicios = $em->getRepository(Ejercicio::class)->findAll();

        return $this->render('rutina/editar.html.twig', [
            'form' => $form->createView(),
            'titulo' => 'Editar rutina',
            'ejercicios' => $ejercicios,
            'ejerciciosRutina' => $ejerciciosRutina,
        ]);
    }

    #[Route('/rutina/{id}', name: 'rutina_visualizar')]
    public function visualizar(int $id, EntityManagerInterface $em): Response
    {
        $rutina = $em->getRepository(Rutina::class)->find($id);

        if (!$rutina) {
            throw $this->createNotFoundException('Rutina no encontrada');
        }

        // Obtener ejercicios ordenados
        $ejerciciosRutina = $rutina->getRutinaEjercicios()->toArray();
        usort($ejerciciosRutina, fn($a, $b) => $a->getOrden() <=> $b->getOrden());

        return $this->render('rutina/visualizar.html.twig', [
            'rutina' => $rutina,
            'ejerciciosRutina' => $ejerciciosRutina,
            'titulo' => 'Visualizar rutina',
        ]);
    }

    #[Route('/rutina/{id}/eliminar', name: 'rutina_eliminar', methods: ['POST'])]
    public function eliminar(int $id, EntityManagerInterface $em): Response
    {
        $rutina = $em->getRepository(Rutina::class)->find($id);

        if (!$rutina) {
            throw $this->createNotFoundException('Rutina no encontrada');
        }

        // Eliminar los registros asociados en RutinaEjercicios
        foreach ($rutina->getRutinaEjercicios() as $rutinaEjercicio) {
            $em->remove($rutinaEjercicio);
        }

        $em->remove($rutina);
        $em->flush();

        return $this->redirectToRoute('app_rutina');
    }
}
