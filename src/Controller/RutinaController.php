<?php

namespace App\Controller;

use App\Entity\Alumno;
use App\Entity\Ejercicio;
use App\Entity\Rutina;
use App\Entity\RutinaEjercicios; // ← si tu clase es singular, cámbialo a RutinaEjercicio
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
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
    /** Devuelve el Alumno vinculado al usuario autenticado (si existe) */
    private function getAlumnoActual(EntityManagerInterface $em): ?Alumno
    {
        $user = $this->getUser();
        if (!$user) return null;
        return $em->getRepository(Alumno::class)->findOneBy(['usuario' => $user]);
    }

    #[Route('/rutina', name: 'app_rutina', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em, PaginatorInterface $paginator): Response
    {
        $q        = trim((string) $request->query->get('q', ''));
        $alumnoId = (int) ($request->query->get('alumno_id', 0));
        $page     = max(1, (int) $request->query->get('page', 1));
        $perPage  = (int) $request->query->get('per_page', 10);
        $perPage  = \in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $qb = $em->getRepository(Rutina::class)->createQueryBuilder('r')
            ->leftJoin('r.alumno', 'a')->addSelect('a')
            ->leftJoin('a.usuario', 'u')->addSelect('u');

        // Admin y Profesor ven TODO; Alumno solo las suyas
        $esAdminLike    = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_ADMINISTRADOR') || $this->isGranted('ROLE_SUPER_ADMIN');
        $esProfesorLike = $this->isGranted('ROLE_PROFESOR') || $this->isGranted('ROLE_TEACHER');

        if (!$esAdminLike && !$esProfesorLike && $this->isGranted('ROLE_ALUMNO')) {
            $qb->andWhere('u = :user')->setParameter('user', $this->getUser());
        }

        if ($alumnoId > 0) {
            $qb->andWhere('a.id = :aid')->setParameter('aid', $alumnoId);
        }

        if ($q !== '') {
            $qb->andWhere('(r.nombre LIKE :q OR u.nombre LIKE :q OR u.apellido1 LIKE :q OR u.apellido2 LIKE :q)')
               ->setParameter('q', "%{$q}%");
        }

        $sort    = (string) $request->query->get('sort', 'r.nombre');
        $dir     = strtolower((string) $request->query->get('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowed = ['r.nombre', 'u.nombre'];
        if (!\in_array($sort, $allowed, true)) { $sort = 'r.nombre'; }

        $pagination = $paginator->paginate(
            $qb,
            $page,
            $perPage,
            [
                'defaultSortFieldName' => $sort,
                'defaultSortDirection' => $dir,
                'wrap-queries' => true,
            ]
        );

        $alumnos = $em->getRepository(Alumno::class)->createQueryBuilder('al')
            ->leftJoin('al.usuario', 'uu')->addSelect('uu')
            ->orderBy('uu.nombre', 'ASC')
            ->getQuery()->getResult();

        return $this->render('rutina/index.html.twig', [
            'titulo'         => 'Listado de rutinas',
            'rutinas'        => $pagination,
            'q'              => $q,
            'alumno_id'      => $alumnoId,
            'alumnos'        => $alumnos,
            'per_page'       => $perPage,
            'perPageOptions' => [10, 25, 50, 100],
        ]);
    }

    #[Route('/rutina/nueva', name: 'rutina_nueva', methods: ['GET','POST'])]
    public function nueva(Request $request, EntityManagerInterface $em): Response
    {
        $rutina = new Rutina();

        $form = $this->createFormBuilder($rutina)
            ->add('alumno', EntityType::class, [
                'class' => Alumno::class,
                'choice_label' => function (Alumno $alumno) {
                    $u = $alumno->getUsuario();
                    return $u ? trim(($u->getNombre() ?? '').' '.($u->getApellido1() ?? '').' '.($u->getApellido2() ?? '')) : 'Sin nombre';
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
                        $re = new RutinaEjercicios();
                        $re->setEjercicio($ejercicio);
                        $re->setRepeticiones((int) $ejData['repeticiones']);
                        $re->setOrden($orden++);
                        $ejerciciosRutina[] = $re;
                    }
                }
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Sólo si es ALUMNO forzamos su propia ficha; admin/profesor pueden elegir cualquier alumno del combo
            if ($this->isGranted('ROLE_ALUMNO') && !$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_PROFESOR')) {
                $alumnoActual = $this->getAlumnoActual($em);
                if (!$alumnoActual) {
                    $this->addFlash('danger', 'No se encontró la ficha del alumno asociada al usuario.');
                    return $this->redirectToRoute('app_rutina');
                }
                $rutina->setAlumno($alumnoActual);
            }

            $em->persist($rutina);
            foreach ($ejerciciosRutina as $re) {
                $re->setRutina($rutina);
                $em->persist($re);
            }
            $em->flush();

            return $this->redirectToRoute('app_rutina');
        }

        $ejercicios = $em->getRepository(Ejercicio::class)->findAll();

        return $this->render('rutina/nueva.html.twig', [
            'form' => $form->createView(),
            'titulo' => 'Nueva rutina',
            'ejercicios' => $ejercicios,
            'ejerciciosRutina' => $ejerciciosRutina,
        ]);
    }

    #[Route('/rutina/{id}/editar', name: 'rutina_editar', requirements: ['id' => '\d+'], methods: ['GET','POST'])]
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
                    $u = $alumno->getUsuario();
                    return $u ? trim(($u->getNombre() ?? '').' '.($u->getApellido1() ?? '').' '.($u->getApellido2() ?? '')) : 'Sin nombre';
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

        // Carga / procesamiento de ejercicios
        $ejerciciosRutina = [];
        if ($request->isMethod('POST')) {
            $ejerciciosData = $request->request->all('ejercicios');
            $orden = 1;
            foreach ($ejerciciosData ?? [] as $ejData) {
                if (!empty($ejData['ejercicio']) && !empty($ejData['repeticiones'])) {
                    $ejercicio = $em->getRepository(Ejercicio::class)->find($ejData['ejercicio']);
                    if ($ejercicio) {
                        $re = new RutinaEjercicios();
                        $re->setEjercicio($ejercicio);
                        $re->setRepeticiones((int) $ejData['repeticiones']);
                        $re->setOrden($orden++);
                        $ejerciciosRutina[] = $re;
                    }
                }
            }
        } else {
            foreach ($rutina->getRutinaEjercicios() as $re) {
                $ejerciciosRutina[] = $re;
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Si es ALUMNO, sólo puede editar sus propias rutinas y se fuerza su alumno
            if ($this->isGranted('ROLE_ALUMNO') && !$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_PROFESOR')) {
                $alumnoActual = $this->getAlumnoActual($em);
                if (!$alumnoActual) {
                    $this->addFlash('danger', 'No se encontró la ficha del alumno asociada al usuario.');
                    return $this->redirectToRoute('app_rutina');
                }
                if ($rutina->getAlumno() && $rutina->getAlumno()->getId() !== $alumnoActual->getId()) {
                    throw $this->createAccessDeniedException('No puedes editar una rutina de otro alumno.');
                }
                $rutina->setAlumno($alumnoActual);
            }

            // Reemplazar ejercicios (simple)
            foreach ($rutina->getRutinaEjercicios() as $re) {
                $em->remove($re);
            }
            $em->flush();

            foreach ($ejerciciosRutina as $re) {
                $re->setRutina($rutina);
                $em->persist($re);
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

    #[Route('/rutina/{id}', name: 'rutina_visualizar', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function visualizar(int $id, EntityManagerInterface $em): Response
    {
        $rutina = $em->getRepository(Rutina::class)->find($id);
        if (!$rutina) {
            throw $this->createNotFoundException('Rutina no encontrada');
        }

        $ejerciciosRutina = $rutina->getRutinaEjercicios()->toArray();
        usort($ejerciciosRutina, fn($a, $b) => $a->getOrden() <=> $b->getOrden());

        return $this->render('rutina/visualizar.html.twig', [
            'rutina' => $rutina,
            'ejerciciosRutina' => $ejerciciosRutina,
            'titulo' => 'Visualizar rutina',
        ]);
    }

    #[Route('/rutina/{id}/eliminar', name: 'rutina_eliminar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function eliminar(Request $request, int $id, EntityManagerInterface $em): Response
    {
        $rutina = $em->getRepository(Rutina::class)->find($id);
        if (!$rutina) {
            throw $this->createNotFoundException('Rutina no encontrada');
        }

        // (Opcional) CSRF
        $token = $request->request->get('_token');
        if ($token !== null && !$this->isCsrfTokenValid('eliminar_rutina_'.$rutina->getId(), $token)) {
            $this->addFlash('danger', 'Token CSRF inválido.');
            return $this->redirectToRoute('app_rutina');
        }

        foreach ($rutina->getRutinaEjercicios() as $re) {
            $em->remove($re);
        }
        $em->remove($rutina);
        $em->flush();

        return $this->redirectToRoute('app_rutina');
    }
}
