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
    public function index(EntityManagerInterface $em, Request $request, PaginatorInterface $paginator): Response
    {
        // Tamaños por página permitidos
        $perPageOptions = [10, 25, 50, 100];
        $perPage = (int) $request->query->get('per_page', 10);
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 10;
        }
        $page = max(1, $request->query->getInt('page', 1));

        // ===== Filtros =====
        $q            = trim((string) $request->query->get('q', '')); // texto libre: nombre, lugar, profesor
        $fechaDesdeQ  = (string) $request->query->get('fecha_desde', '');
        $fechaHastaQ  = (string) $request->query->get('fecha_hasta', '');
        $profesorId   = $request->query->getInt('profesor_id', 0);

        $fechaDesde = null;
        $fechaHasta = null;
        try {
            if ($fechaDesdeQ !== '') {
                $fechaDesde = new \DateTimeImmutable($fechaDesdeQ);
            }
            if ($fechaHastaQ !== '') {
                $fechaHasta = (new \DateTimeImmutable($fechaHastaQ))->setTime(23, 59, 59);
            }
        } catch (\Exception $e) {
            // Si hay un formato inválido, simplemente ignoramos el filtro
        }

        // ===== Query base =====
        $qb = $em->getRepository(Clase::class)->createQueryBuilder('c')
            ->leftJoin('c.profesor', 'p')
            ->leftJoin('p.usuario', 'u')
            ->addSelect('p', 'u');

        if ($q !== '') {
            $qb->andWhere('LOWER(c.nombre) LIKE :q OR LOWER(c.lugar) LIKE :q OR LOWER(u.nombre) LIKE :q OR LOWER(u.apellido1) LIKE :q OR LOWER(u.apellido2) LIKE :q')
               ->setParameter('q', '%'.mb_strtolower($q).'%');
        }

        if ($profesorId > 0) {
            $qb->andWhere('p.id = :profesorId')->setParameter('profesorId', $profesorId);
        }

        if ($fechaDesde) {
            $qb->andWhere('c.fecha >= :fd')->setParameter('fd', $fechaDesde);
        }

        if ($fechaHasta) {
            $qb->andWhere('c.fecha <= :fh')->setParameter('fh', $fechaHasta);
        }

        // Orden por defecto (KnpPaginator lo podrá sobrescribir con ?sort=&direction=)
        $qb->addOrderBy('c.fecha', 'ASC')->addOrderBy('c.hora', 'ASC');

        // ===== Paginación =====
        $pagination = $paginator->paginate(
            $qb,
            $page,
            $perPage
        );

        // Para el filtro de profesores (selector desplegable)
        $profesores = $em->getRepository(Profesor::class)->createQueryBuilder('pr')
            ->leftJoin('pr.usuario', 'uu')->addSelect('uu')
            ->orderBy('uu.nombre', 'ASC')
            ->getQuery()->getResult();

        return $this->render('clase/index.html.twig', [
            'clases'          => $pagination,
            'titulo'          => 'Listado de clases',
            'per_page'        => $perPage,
            'perPageOptions'  => $perPageOptions,
            'q'               => $q,
            'fecha_desde'     => $fechaDesdeQ,
            'fecha_hasta'     => $fechaHastaQ,
            'profesor_id'     => $profesorId,
            'profesores'      => $profesores,
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
