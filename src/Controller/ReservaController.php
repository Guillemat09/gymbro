<?php

namespace App\Controller;

use App\Entity\Alumno;
use App\Entity\Clase;
use App\Entity\Profesor;
use App\Entity\Reserva;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

final class ReservaController extends AbstractController
{
        #[Route('/reserva', name: 'app_reserva')]
    public function index(
        EntityManagerInterface $em,
        Request $request,
        \Knp\Component\Pager\PaginatorInterface $paginator // ✅ Paginador con namespace absoluto (no falla nunca)
    ): Response {
        // Tamaños de página permitidos
        $perPageOptions = [10, 25, 50, 100];
        $perPage = (int) $request->query->get('per_page', 10);
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 10;
        }
        $page = max(1, $request->query->getInt('page', 1));

        // ===== Filtros =====
        $q                   = trim((string) $request->query->get('q', '')); // alumno, profesor o clase
        $fechaReservaDesdeQ  = (string) $request->query->get('fecha_reserva_desde', '');
        $fechaReservaHastaQ  = (string) $request->query->get('fecha_reserva_hasta', '');
        $fechaClaseDesdeQ    = (string) $request->query->get('fecha_clase_desde', '');
        $fechaClaseHastaQ    = (string) $request->query->get('fecha_clase_hasta', '');
        $profesorId          = $request->query->getInt('profesor_id', 0);

        $fechaReservaDesde = null;
        $fechaReservaHasta = null;
        $fechaClaseDesde   = null;
        $fechaClaseHasta   = null;

        try {
            if ($fechaReservaDesdeQ !== '') {
                $fechaReservaDesde = new \DateTimeImmutable($fechaReservaDesdeQ);
            }
            if ($fechaReservaHastaQ !== '') {
                $fechaReservaHasta = (new \DateTimeImmutable($fechaReservaHastaQ))->setTime(23, 59, 59);
            }
            if ($fechaClaseDesdeQ !== '') {
                $fechaClaseDesde = new \DateTimeImmutable($fechaClaseDesdeQ);
            }
            if ($fechaClaseHastaQ !== '') {
                $fechaClaseHasta = (new \DateTimeImmutable($fechaClaseHastaQ))->setTime(23, 59, 59);
            }
        } catch (\Exception $e) {
            // Si hay error de formato en alguna fecha, se ignora el filtro
        }

        // ===== Query base =====
        $qb = $em->getRepository(Reserva::class)->createQueryBuilder('r')
            ->leftJoin('r.alumno', 'a')
            ->leftJoin('a.usuario', 'au')
            ->leftJoin('r.clase', 'c')
            ->leftJoin('c.profesor', 'p')
            ->leftJoin('p.usuario', 'pu')
            ->addSelect('a', 'au', 'c', 'p', 'pu');

        if ($q !== '') {
            $qb->andWhere('LOWER(au.nombre) LIKE :q
                        OR LOWER(au.apellido1) LIKE :q
                        OR LOWER(au.apellido2) LIKE :q
                        OR LOWER(au.email) LIKE :q
                        OR LOWER(pu.nombre) LIKE :q
                        OR LOWER(pu.apellido1) LIKE :q
                        OR LOWER(pu.apellido2) LIKE :q
                        OR LOWER(c.nombre) LIKE :q')
               ->setParameter('q', '%'.mb_strtolower($q).'%');
        }

        if ($profesorId > 0) {
            $qb->andWhere('p.id = :profesorId')->setParameter('profesorId', $profesorId);
        }

        if ($fechaReservaDesde) {
            $qb->andWhere('r.fecha >= :frd')->setParameter('frd', $fechaReservaDesde);
        }
        if ($fechaReservaHasta) {
            $qb->andWhere('r.fecha <= :frh')->setParameter('frh', $fechaReservaHasta);
        }
        if ($fechaClaseDesde) {
            $qb->andWhere('c.fecha >= :fcd')->setParameter('fcd', $fechaClaseDesde);
        }
        if ($fechaClaseHasta) {
            $qb->andWhere('c.fecha <= :fch')->setParameter('fch', $fechaClaseHasta);
        }

        // Orden por defecto
        $qb->addOrderBy('r.fecha', 'DESC');

        // ===== Paginación =====
        $pagination = $paginator->paginate(
            $qb,
            $page,
            $perPage
        );

        // Selector de profesores (para filtros)
        $profesores = $em->getRepository(Profesor::class)->createQueryBuilder('pr')
            ->leftJoin('pr.usuario', 'uu')->addSelect('uu')
            ->orderBy('uu.nombre', 'ASC')
            ->getQuery()->getResult();

        return $this->render('reserva/index.html.twig', [
            'reservas'             => $pagination,
            'titulo'               => 'Listado de reservas',
            'per_page'             => $perPage,
            'perPageOptions'       => $perPageOptions,
            'q'                    => $q,
            'fecha_reserva_desde'  => $fechaReservaDesdeQ,
            'fecha_reserva_hasta'  => $fechaReservaHastaQ,
            'fecha_clase_desde'    => $fechaClaseDesdeQ,
            'fecha_clase_hasta'    => $fechaClaseHastaQ,
            'profesor_id'          => $profesorId,
            'profesores'           => $profesores,
        ]);
    }
    #[Route('/reserva/nueva', name: 'reserva_nueva')]
    public function nueva(Request $request, EntityManagerInterface $em): Response
    {
        $reserva = new Reserva();

        // Clases futuras ordenadas por fecha ascendente
        $clasesFuturas = $em->getRepository(Clase::class)->createQueryBuilder('c')
            ->where('c.fecha > :hoy')
            ->setParameter('hoy', new \DateTime('today'))
            ->orderBy('c.fecha', 'ASC')
            ->getQuery()
            ->getResult();

        $form = $this->createFormBuilder($reserva)
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
            ->add('clase', EntityType::class, [
                'class' => Clase::class,
                'choices' => $clasesFuturas,
                'choice_label' => function (Clase $clase) {
                    $plazasLibres = $clase->getLimite() - $clase->getReservas()->count();
                    if ($plazasLibres > 0) {
                        return $clase->getNombre() . ' (' . $clase->getFecha()->format('d/m/Y') . ') - ' . $plazasLibres . ' plazas libres';
                    } else {
                        return $clase->getNombre() . ' (' . $clase->getFecha()->format('d/m/Y') . ') - COMPLETO';
                    }
                },
                'label' => 'Clase',
                'placeholder' => 'Selecciona una clase',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('guardar', SubmitType::class, [
                'label' => 'Guardar reserva',
                'attr' => ['class' => 'btn btn-primary mt-3'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $alumno = $form->get('alumno')->getData();
            $clase = $form->get('clase')->getData();

            // Comprobar si el alumno ya tiene reserva en esa clase
            $reservaExistente = $em->getRepository(Reserva::class)->findOneBy([
                'alumno' => $alumno,
                'clase' => $clase,
            ]);
            if ($reservaExistente) {
                $this->addFlash('danger', 'El alumno ya tiene una reserva en esa clase.');
            } elseif ($clase->getReservas()->count() >= $clase->getLimite()) {
                $this->addFlash('danger', 'La clase está completa. No se pueden hacer más reservas.');
            } else {
                $reserva->setFecha(new \DateTime()); // Asignar fecha actual del sistema
                $em->persist($reserva);
                $em->flush();
                return $this->redirectToRoute('app_reserva');
            }
        }

        return $this->render('reserva/nueva.html.twig', [
            'form' => $form->createView(),
            'titulo' => 'Nueva reserva',
        ]);
    }

    #[Route('/reserva/{id}/eliminar', name: 'reserva_eliminar', methods: ['POST'])]
    public function eliminar(int $id, EntityManagerInterface $em): Response
    {
        $reserva = $em->getRepository(Reserva::class)->find($id);

        if (!$reserva) {
            throw $this->createNotFoundException('Reserva no encontrada');
        }

        $em->remove($reserva);
        $em->flush();

        return $this->redirectToRoute('app_reserva');
    }

    #[Route('/reserva/{id}', name: 'reserva_visualizar')]
    public function visualizar(int $id, EntityManagerInterface $em): Response
    {
        $reserva = $em->getRepository(Reserva::class)->find($id);

        if (!$reserva) {
            throw $this->createNotFoundException('Reserva no encontrada');
        }

        return $this->render('reserva/visualizar.html.twig', [
            'reserva' => $reserva,
            'titulo' => 'Visualizar reserva',
        ]);
    }
}
