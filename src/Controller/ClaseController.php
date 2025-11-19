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
use Knp\Component\Pager\PaginatorInterface;

final class ClaseController extends AbstractController
{
    /** Busca el Profesor vinculado al usuario autenticado (si existe) */
    private function getProfesorActual(EntityManagerInterface $em): ?Profesor
    {
        $user = $this->getUser();
        if (!$user) return null;
        return $em->getRepository(Profesor::class)->findOneBy(['usuario' => $user]);
    }

    /**
     * Garantiza que exista una fila Profesor para el usuario con ROLE_PROFESOR.
     * Si no existe, la crea con valores por defecto válidos para campos NOT NULL.
     */
    private function ensureProfesorActual(EntityManagerInterface $em): ?Profesor
    {
        $user = $this->getUser();
        if (!$user) {
            return null;
        }

        $repo = $em->getRepository(Profesor::class);
        $profesor = $repo->findOneBy(['usuario' => $user]);
        if ($profesor) {
            return $profesor;
        }

        if ($this->isGranted('ROLE_PROFESOR')) {
            $profesor = new Profesor();
            $profesor->setUsuario($user);

            // 🔴 IMPORTANTE: Rellenar campos NOT NULL con valores por defecto
            // Tu error indica que 'especialidad' no puede ser null
            if (method_exists($profesor, 'setEspecialidad')) {
                $profesor->setEspecialidad('General'); // <= valor por defecto seguro
            }

            // Si tu entidad tiene otros NOT NULL (p.ej. 'activo', 'telefono', etc.),
            // añade aquí sus valores por defecto, siempre protegidos con method_exists:
            // if (method_exists($profesor, 'setActivo')) { $profesor->setActivo(true); }
            // if (method_exists($profesor, 'setTelefono')) { $profesor->setTelefono(''); }

            $em->persist($profesor);
            $em->flush();

            return $profesor;
        }

        return null;
    }

    /** Manda a flash todos los errores del form (útil cuando “no hace nada”) */
    private function flashFormErrors($form): void
    {
        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('danger', $error->getMessage());
        }
    }

   #[Route('/clase', name: 'app_clase', methods: ['GET'])]
public function index(EntityManagerInterface $em, Request $request, PaginatorInterface $paginator): Response
{
    // Tamaños por página permitidos y valor por defecto
    $perPageOptions = [10, 25, 50, 100];
    $perPage = (int) $request->query->get('per_page', 10);
    if (!in_array($perPage, $perPageOptions, true)) {
        $perPage = 10;
    }
    $page = max(1, $request->query->getInt('page', 1));

    // 🔎 Filtros
    $q        = trim($request->query->get('q', ''));           // nombre de clase
    $profesor = trim($request->query->get('profesor', ''));    // nombre/apellidos profesor

    // Query base
    $qb = $em->getRepository(Clase::class)->createQueryBuilder('c')
        ->leftJoin('c.profesor', 'p')->addSelect('p')
        ->leftJoin('p.usuario', 'u')->addSelect('u')
        ->addOrderBy('c.fecha', 'ASC')
        ->addOrderBy('c.hora', 'ASC');

    // 📌 Filtro por nombre de clase
    if ($q !== '') {
        $qb->andWhere('c.nombre LIKE :q')
           ->setParameter('q', '%'.$q.'%');
    }

    // 📌 Filtro por profesor (nombre o apellidos)
    if ($profesor !== '') {
        $qb->andWhere('u.nombre LIKE :profesor 
                       OR u.apellido1 LIKE :profesor 
                       OR u.apellido2 LIKE :profesor 
                       OR CONCAT(u.nombre, \' \', u.apellido1) LIKE :profesor')
           ->setParameter('profesor', '%'.$profesor.'%');
    }

    // Paginación
    $pagination = $paginator->paginate(
        $qb,
        $page,
        $perPage
    );

    return $this->render('clase/index.html.twig', [
        'clases'         => $pagination,
        'titulo'         => 'Listado de clases',
        'per_page'       => $perPage,
        'perPageOptions' => $perPageOptions,
        // 👇 Pasamos los filtros a la vista
        'q'              => $q,
        'profesor'       => $profesor,
    ]);
}


    #[Route('/clase/nueva', name: 'clase_nueva', methods: ['GET','POST'])]
    public function nueva(Request $request, EntityManagerInterface $em): Response
    {
        $clase = new Clase();

        $esProfesor = $this->isGranted('ROLE_PROFESOR')  && !$this->isGranted('ROLE_ADMIN') ;
        // Garantiza que exista Profesor si eres ROLE_PROFESOR (y lo crea con defaults)
        $profesorActual = $this->ensureProfesorActual($em);

        if ($esProfesor && $profesorActual) {
            $clase->setProfesor($profesorActual);
        }

        $builder = $this->createFormBuilder($clase)
            ->add('nombre', TextType::class, [
                'label' => 'Nombre de la clase',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('fecha', DateType::class, [
                'label' => 'Fecha',
                'widget' => 'single_text',
                'input'  => 'datetime',   // \DateTimeInterface en la entidad
                'attr' => ['class' => 'form-control'],
            ])
            ->add('hora', TimeType::class, [
                'label' => 'Hora',
                'widget' => 'single_text',
                'input'  => 'datetime',   // \DateTimeInterface en la entidad
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
            // Solo profesores con usuario asociado
            $profesoresValidos = $em->getRepository(Profesor::class)
                ->createQueryBuilder('p')
                ->leftJoin('p.usuario', 'u')
                ->addSelect('u')
                ->where('u.id IS NOT NULL')
                ->getQuery()
                ->getResult();

            $builder->add('profesor', EntityType::class, [
                'class' => Profesor::class,
                'choices' => $profesoresValidos,
                'choice_label' => function (Profesor $profesor) {
                    $u = $profesor->getUsuario();
                    return $u ? trim(($u->getNombre() ?? '').' '.($u->getApellido1() ?? '')) : 'Sin nombre';
                },
                'label' => 'Profesor',
                'placeholder' => 'Selecciona un profesor',
                'attr' => ['class' => 'form-select'],
                'required' => true,
            ]);
        } else {
            // Si es profesor, solo puede verse a sí mismo
            $choices = [];
            if ($profesorActual) { $choices[] = $profesorActual; }
            $builder->add('profesor', EntityType::class, [
                'class' => Profesor::class,
                'choices' => $choices,
                'data' => $profesorActual,
                'choice_label' => function (Profesor $profesor) {
                    $u = $profesor->getUsuario();
                    return $u ? trim(($u->getNombre() ?? '').' '.($u->getApellido1() ?? '')) : 'Sin nombre';
                },
                'label' => 'Profesor',
                'placeholder' => false,
                'attr' => ['class' => 'form-select'],
                'required' => true,
            ]);
        }

        $builder->add('guardar', SubmitType::class, [
            'label' => 'Guardar clase',
            'attr' => ['class' => 'btn btn-primary mt-3'],
        ]);

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($esProfesor) {
                // Asegura de nuevo (por si cambió algo)
                $profesorActual = $this->ensureProfesorActual($em);
                $clase->setProfesor($profesorActual);
            }
            // Si es admin, se respeta el profesor elegido en el formulario

            $em->persist($clase);
            $em->flush();

            $this->addFlash('success', 'Clase creada correctamente.');
            return $this->redirectToRoute('app_clase');
        }

        return $this->render('clase/nueva.html.twig', [
            'form' => $form->createView(),
            'titulo' => 'Nueva clase',
        ]);
    }

    #[Route('/clase/{id}/editar', name: 'clase_editar', requirements: ['id' => '\d+'], methods: ['GET','POST'])]
    public function editar(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $clase = $em->getRepository(Clase::class)->find($id);
        if (!$clase) {
            throw $this->createNotFoundException('Clase no encontrada');
        }

        $esProfesor = $this->isGranted('ROLE_PROFESOR');
        // Garantiza que exista Profesor si eres ROLE_PROFESOR (con defaults)
        $profesorActual = $this->ensureProfesorActual($em);

        // Si es profesor (no admin), debe ser dueño de la clase
        if ($esProfesor && !$this->isGranted('ROLE_ADMIN')) {
            if (!$profesorActual || $clase->getProfesor()?->getId() !== $profesorActual->getId()) {
                throw $this->createAccessDeniedException('No puedes editar una clase de otro profesor.');
            }
        }

        $builder = $this->createFormBuilder($clase)
            ->add('nombre', TextType::class, [
                'label' => 'Nombre de la clase',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('fecha', DateType::class, [
                'label' => 'Fecha',
                'widget' => 'single_text',
                'input'  => 'datetime',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('hora', TimeType::class, [
                'label' => 'Hora',
                'widget' => 'single_text',
                'input'  => 'datetime',
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
            $builder->add('profesor', EntityType::class, [
                'class' => Profesor::class,
                'choice_label' => function (Profesor $profesor) {
                    $u = $profesor->getUsuario();
                    return $u ? trim(($u->getNombre() ?? '').' '.($u->getApellido1() ?? '')) : 'Sin nombre';
                },
                'label' => 'Profesor',
                'placeholder' => 'Selecciona un profesor',
                'attr' => ['class' => 'form-select'],
                'required' => true,
            ]);
        } else {
            // Choices: su propio profesor y (por robustez) el ya asociado a la clase
            $choices = [];
            if ($profesorActual) { $choices[] = $profesorActual; }
            if ($clase->getProfesor() && (!$profesorActual || $clase->getProfesor()->getId() !== $profesorActual->getId())) {
                $choices[] = $clase->getProfesor();
            }

            $builder->add('profesor', EntityType::class, [
                'class' => Profesor::class,
                'choices' => $choices,
                'data' => $profesorActual ?: $clase->getProfesor(),
                'choice_label' => function (Profesor $profesor) {
                    $u = $profesor->getUsuario();
                    return $u ? trim(($u->getNombre() ?? '').' '.($u->getApellido1() ?? '')) : 'Sin nombre';
                },
                'label' => 'Profesor',
                'placeholder' => $profesorActual ? false : 'Sin profesor asociado',
                'attr' => ['class' => 'form-select'],
                'required' => false,
            ]);
        }

        $builder->add('guardar', SubmitType::class, [
            'label' => 'Guardar cambios',
            'attr' => ['class' => 'btn btn-primary mt-3'],
        ]);

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($esProfesor && !$this->isGranted('ROLE_ADMIN')) {
                // Solo si es profesor y NO admin, fuerza su propio profesor
                $profesorActual = $this->ensureProfesorActual($em);
                $clase->setProfesor($profesorActual);
            }

            $em->flush();
            $this->addFlash('success', 'Clase actualizada correctamente.');
            return $this->redirectToRoute('app_clase');
        }

        // El mensaje flash solo debe estar dentro del bloque de submit+validación
        return $this->render('clase/editar.html.twig', [
            'form' => $form->createView(),
            'titulo' => 'Editar clase',
            'clase' => $clase,
        ]);
    }

    #[Route('/clase/{id}', name: 'clase_visualizar', requirements: ['id' => '\d+'], methods: ['GET'])]
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

    #[Route('/clase/{id}/eliminar', name: 'clase_eliminar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function eliminar(Request $request, int $id, EntityManagerInterface $em): Response
    {
        $clase = $em->getRepository(Clase::class)->find($id);
        if (!$clase) {
            throw $this->createNotFoundException('Clase no encontrada');
        }

        $token = $request->request->get('_token');
        if ($token !== null && !$this->isCsrfTokenValid('eliminar_clase_'.$clase->getId(), $token)) {
            $this->addFlash('danger', 'Token CSRF inválido.');
            return $this->redirectToRoute('app_clase');
        }

        $em->remove($clase);
        $em->flush();

        $this->addFlash('success', 'Clase eliminada.');
        return $this->redirectToRoute('app_clase');
    }
}
