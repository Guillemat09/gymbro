<?php
// src/Controller/ReservaController.php
namespace App\Controller;

use App\Entity\Reserva;
use App\Entity\Alumno;
use App\Entity\Clase;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/reserva')]
class ReservaController extends AbstractController
{
    #[Route('/', name: 'app_reserva', methods: ['GET'])]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        PaginatorInterface $paginator
    ): Response {
        $qb = $em->createQueryBuilder()
            ->select('r, c, a, au, p, pu')
            ->from(Reserva::class, 'r')
            ->leftJoin('r.clase', 'c')
            ->leftJoin('r.alumno', 'a')
            ->leftJoin('a.usuario', 'au')
            ->leftJoin('c.profesor', 'p')
            ->leftJoin('p.usuario', 'pu');

        // Si es ALUMNO, restringir SIEMPRE a sus reservas
        if ($this->isGranted('ROLE_ALUMNO') && !$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_PROFESOR')) {
            $usuario = $this->getUser();
            $alumno  = method_exists($usuario, 'getAlumno') ? $usuario->getAlumno() : null;

            if (!$alumno) {
                $qb->andWhere('1 = 0');
                $this->addFlash('danger', 'Tu usuario no está vinculado a un alumno.');
            } else {
                $qb->andWhere('r.alumno = :alumnoActual')->setParameter('alumnoActual', $alumno);
            }
        }

        // Búsqueda libre opcional (?q=)
        $q = trim((string) $request->query->get('q', ''));
        if ($q !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(c.nombre) LIKE :q',
                    'LOWER(pu.nombre) LIKE :q',
                    'LOWER(au.nombre) LIKE :q'
                )
            )->setParameter('q', '%'.mb_strtolower($q).'%');
        }

        // Paginación
        $page    = max(1, (int) $request->query->get('page', 1));
        $perPage = max(1, min(100, (int) $request->query->get('per_page', 10)));

        $reservas = $paginator->paginate(
            $qb, // QueryBuilder directamente
            $page,
            $perPage,
            ['distinct' => true]
        );

        return $this->render('reserva/index.html.twig', [
            'reservas'    => $reservas,
            'q'           => $q,
            'per_page'    => $perPage,
            'currentSort' => $request->query->get('sort'),
            'currentDir'  => $request->query->get('direction'),
        ]);
    }

    #[Route('/nueva', name: 'reserva_nueva', methods: ['GET', 'POST'])]
    public function nueva(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $reserva = new Reserva();

        // Solo ADMIN/PROFESOR pueden escoger el alumno en el formulario
        $includeAlumno = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_PROFESOR');

        // ======= Formulario construido en el controlador =======
        $builder = $this->createFormBuilder($reserva);

        if ($includeAlumno) {
            $builder->add('alumno', EntityType::class, [
                'class' => Alumno::class,
                'choice_label' => function (Alumno $a) {
                    $u = $a->getUsuario();
                    if ($u) {
                        $nombre = trim(($u->getNombre() ?? '').' '.($u->getApellido1() ?? ''));
                        return $nombre !== '' ? $nombre : $u->getEmail();
                    }
                    return 'Alumno sin usuario';
                },
                'placeholder' => '— Selecciona alumno —',
                'required'    => true,
                'attr'        => ['class' => 'form-select'],
            ]);
        }

        $builder->add('clase', EntityType::class, [
            'class' => Clase::class,
            'choice_label' => 'nombre',
            // data-* para que la plantilla muestre profesor/fecha de la clase
            'choice_attr' => function (Clase $c) {
                $prof  = $c->getProfesor()?->getUsuario()?->getNombre() ?? 'Sin profesor';
                $fecha = $c->getFecha()?->format('d/m/Y') ?? 'Sin fecha';
                return [
                    'data-profesor' => $prof,
                    'data-fecha'    => $fecha,
                ];
            },
            'placeholder' => '— Selecciona clase —',
            'required'    => true,
            'attr'        => ['class' => 'form-select', 'id' => 'clase-select'],
        ]);

        // Si quieres un botón generado por Symfony (tu vista ya tiene el botón, así que es opcional):
        // $builder->add('guardar', SubmitType::class, ['label' => 'Guardar reserva']);

        $form = $builder->getForm();
        // ======================================================

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Alumno: siempre su propia ficha, ignorar cualquier otro alumno
            if ($this->isGranted('ROLE_ALUMNO') && !$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_PROFESOR')) {
                $usuario = $this->getUser();
                $alumno  = method_exists($usuario, 'getAlumno') ? $usuario->getAlumno() : null;

                if (!$alumno) {
                    $this->addFlash('danger', 'Tu usuario no está vinculado a un alumno.');
                    return $this->redirectToRoute('app_reserva');
                }
                $reserva->setAlumno($alumno);
            }

            // Aquí puedes validar plazas disponibles, duplicados, etc.
            $reserva->setFecha(new \DateTime());
            $em->persist($reserva);
            $em->flush();

            $this->addFlash('success', 'Reserva creada correctamente.');
            return $this->redirectToRoute('app_reserva');
        }

        return $this->render('reserva/nueva.html.twig', [
            'form'   => $form->createView(),
            'titulo' => 'Nueva reserva',
        ]);
    }

    #[Route('/{id}', name: 'reserva_visualizar', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function mostrar(Reserva $reserva): Response
    {
        // Alumno solo puede ver sus reservas
        if ($this->isGranted('ROLE_ALUMNO') && !$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_PROFESOR')) {
            $usuario = $this->getUser();
            $alumno  = method_exists($usuario, 'getAlumno') ? $usuario->getAlumno() : null;

            if (!$alumno || $reserva->getAlumno()?->getId() !== $alumno->getId()) {
                throw $this->createAccessDeniedException('No puedes acceder a reservas de otros alumnos.');
            }
        }

        return $this->render('reserva/visualizar.html.twig', [
            'reserva' => $reserva,
            'titulo'  => 'Detalle de la reserva',
        ]);
    }

    #[Route('/{id}/eliminar', name: 'reserva_eliminar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function eliminar(
        Request $request,
        Reserva $reserva,
        EntityManagerInterface $em
    ): Response {
        // Alumno solo puede borrar sus reservas
        if ($this->isGranted('ROLE_ALUMNO') && !$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_PROFESOR')) {
            $usuario = $this->getUser();
            $alumno  = method_exists($usuario, 'getAlumno') ? $usuario->getAlumno() : null;

            if (!$alumno || $reserva->getAlumno()?->getId() !== $alumno->getId()) {
                throw $this->createAccessDeniedException('No puedes eliminar reservas de otros alumnos.');
            }
        }

        if ($this->isCsrfTokenValid('eliminar_reserva_'.$reserva->getId(), $request->request->get('_token'))) {
            $em->remove($reserva);
            $em->flush();
            $this->addFlash('success', 'Reserva eliminada.');
        } else {
            $this->addFlash('danger', 'Token CSRF inválido.');
        }

        return $this->redirectToRoute('app_reserva');
    }

   #[Route('/reservas/calendario', name: 'reserva_calendario')]
    public function calendario(Request $request, EntityManagerInterface $em): Response
    {
        // Año/mes a visualizar (por defecto, el actual)
        $now   = new \DateTimeImmutable('today');
        $year  = max(1, (int) $request->query->get('year', (int) $now->format('Y')));
        $month = max(1, min(12, (int) $request->query->get('month', (int) $now->format('n')))); // 1..12

        $monthStart = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
        $monthEnd   = $monthStart->modify('first day of next month');

        // “Futuras”: desde hoy o desde el inicio del mes (el que sea más tarde)
        $from = $monthStart < $now ? $now : $monthStart;

        // Cargar clases de ese rango, junto con profesor y reservas (para contar)
        $qb = $em->getRepository(Clase::class)->createQueryBuilder('c')
            ->leftJoin('c.profesor', 'p')->addSelect('p')
            ->leftJoin('c.reservas', 'r')->addSelect('r')
            ->where('c.fecha >= :from AND c.fecha < :end')
            ->setParameter('from', $from)
            ->setParameter('end',  $monthEnd)
            ->orderBy('c.fecha', 'ASC')
            ->addOrderBy('c.hora', 'ASC');

        $clases = $qb->getQuery()->getResult();

        // Normalizamos a un array “plano” que el front entiende fácil
        $payload = array_map(function (Clase $c) {
            $fecha = $c->getFecha(); // DATE_MUTABLE (día)
            $hora  = $c->getHora();  // TIME_MUTABLE (hora)
            $limite   = (int) ($c->getLimite() ?? 0);
            $enrolled = $c->getReservas()->count();

            $teacher = '';
            if ($c->getProfesor()) {
                $prof = $c->getProfesor();
                $teacher = (string) $prof->getUsuario()->getNombre();
            }

            return [
                'id'        => $c->getId(),
                'date'      => $fecha ? $fecha->format('Y-m-d') : null,
                'time'      => $hora ? $hora->format('H:i') : null,
                'name'      => (string) $c->getNombre(),
                'teacher'   => $teacher,
                'capacity'  => $limite,
                'enrolled'  => $enrolled,
                'is_full'   => $limite > 0 ? ($enrolled >= $limite) : false,
                'duration'  => $c->getDuracion(),
                'place'     => $c->getLugar(),
            ];
        }, $clases);

        // Filtramos por seguridad si no hubiera fecha
        $payload = array_values(array_filter($payload, fn($e) => $e['date'] !== null));

        return $this->render('reserva/calendario.html.twig', [
            'titulo'     => 'Calendario de reservas',
            'year'       => $year,
            'month'      => $month, // 1..12
            'clases'     => $payload,
        ]);
    }

 #[Route('/reservas/api/clases', name: 'api_reservas_clases', methods: ['GET'])]
    public function clasesMes(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $year  = (int) $request->query->get('year');
        $month = (int) $request->query->get('month'); // 1..12

        if ($year < 2000 || $month < 1 || $month > 12) {
            return $this->json(['error' => 'Parámetros inválidos'], 400);
        }

        // Primer día del mes / primer día del mes siguiente
        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
        $end   = $start->modify('first day of next month');

        // Campo de fecha: c.fecha (DATE_MUTABLE)
        $qb = $em->createQueryBuilder()
            ->select('c', 'p') // precarga profesor para evitar N+1 si lo necesitas luego
            ->from(Clase::class, 'c')
            ->leftJoin('c.profesor', 'p')
            ->where('c.fecha >= :start AND c.fecha < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('c.fecha', 'ASC')
            ->addOrderBy('c.hora', 'ASC');

        $clases = $qb->getQuery()->getResult();

        // Respuesta para pintar chips del calendario (por día)
        $data = array_map(function (Clase $c) {
            $fecha = $c->getFecha(); // \DateTime (solo fecha)
            $hora  = $c->getHora();  // \DateTime (solo hora)

            return [
                'id'   => $c->getId(),
                'date' => $fecha ? $fecha->format('Y-m-d') : null,
                'name' => (string) $c->getNombre(),
                'time' => $hora ? $hora->format('H:i') : null,
            ];
        }, $clases);

        // Filtra por si alguna clase no tuviera fecha (no debería pasar)
        $data = array_values(array_filter($data, fn ($e) => $e['date'] !== null));

        return $this->json($data);
    }

    #[Route('/reservas/api/clase/{id}', name: 'api_reservas_clase_detalle', methods: ['GET'])]
    public function claseDetalle(int $id, EntityManagerInterface $em): JsonResponse
    {
        /** @var Clase|null $c */
        $c = $em->getRepository(Clase::class)->find($id);
        if (!$c) {
            return $this->json(['error' => 'No encontrada'], 404);
        }

        $fecha = $c->getFecha(); // \DateTime (fecha)
        $hora  = $c->getHora();  // \DateTime (hora)
        $limite = (int) ($c->getLimite() ?? 0);

        // Profesor puede tener getNombre(); si no, usa __toString()
        $prof = $c->getProfesor();
        $teacher = '';
        if ($prof) {
            if (method_exists($prof, 'getNombre')) {
                $teacher = (string) $prof->getUsuario()->getNombre();
            } else {
                $teacher = (string) $prof;
            }
        }

        // Numero de reservas apuntadas
        $enrolled = $c->getReservas()->count();

        $data = [
            'id'        => $c->getId(),
            'name'      => (string) $c->getNombre(),
            'teacher'   => $teacher,
            'date'      => $fecha ? $fecha->format('Y-m-d') : null,
            'time'      => $hora ? $hora->format('H:i') : null,
            'capacity'  => $limite,
            'enrolled'  => $enrolled,
            'is_full'   => $limite > 0 ? ($enrolled >= $limite) : false,
            // Campos extra útiles que sí tienes en la entidad:
            'duration'  => $c->getDuracion(),
            'place'     => $c->getLugar(),
        ];

        return $this->json($data);
    }
}
