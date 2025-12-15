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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use App\Entity\Alumno;
use App\Entity\Profesor;
use App\Entity\Administrador;
use App\Entity\Clase;
use App\Entity\Reserva;
use Knp\Component\Pager\PaginatorInterface;

class UsuarioController extends AbstractController
{
  #[Route('/usuario', name: 'app_usuario')]
    public function index(EntityManagerInterface $em, Request $request, PaginatorInterface $paginator): Response
    {
        // Tamaños por página permitidos y valor por defecto
        $perPageOptions = [10, 25, 50, 100];
        $perPage = (int) $request->query->get('per_page', 10);
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 10;
        }
        $page = max(1, $request->query->getInt('page', 1));

        // ===== Filtros =====
        $q    = trim((string) $request->query->get('q', ''));           // texto libre: nombre, apellidos, email
        $tipo = (string) $request->query->get('tipo', '');              // alumno|profesor|administrador|''

        // ===== Query base =====
        $qb = $em->getRepository(Usuario::class)->createQueryBuilder('u');

        if ($q !== '') {
            $qb->andWhere('LOWER(u.nombre) LIKE :q OR LOWER(u.apellido1) LIKE :q OR LOWER(u.apellido2) LIKE :q OR LOWER(u.email) LIKE :q')
               ->setParameter('q', '%'.mb_strtolower($q).'%');
        }
        if ($tipo !== '') {
            $qb->andWhere('u.tipo = :tipo')->setParameter('tipo', $tipo);
        }

        // Orden por defecto (KnpPaginator lo podrá sobrescribir con ?sort=&direction=)
        $qb->orderBy('u.id', 'ASC');

        // Paginación (pasa el QB directamente)
        $pagination = $paginator->paginate(
            $qb,
            $page,
            $perPage
        );

        return $this->render('usuario/index.html.twig', [
            'usuarios'        => $pagination,
            'titulo'          => 'GymBro - Usuarios',
            'per_page'        => $perPage,
            'perPageOptions'  => $perPageOptions,
            'q'               => $q,
            'tipo'            => $tipo,
        ]);
    }

    #[Route('/usuario/nuevo', name: 'usuario_nuevo')]
    public function nuevo(Request $request, EntityManagerInterface $em): Response
    {
        $usuario = new Usuario();
        $errores = [];

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
            ->add('tipo', ChoiceType::class, [
                'label' => 'Tipo de usuario',
                'choices' => [
                    'Alumno' => 'alumno',
                    'Profesor' => 'profesor',
                    'Administrador' => 'administrador',
                ],
                'expanded' => false,
                'multiple' => false,
                'attr' => ['class' => 'form-select'],
                'required' => true,
            ])
            ->add('guardar', SubmitType::class, [
                'label' => 'Guardar usuario',
                'attr' => ['class' => 'btn btn-primary mt-3'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $nombre = $form->get('nombre')->getData();
            $apellido1 = $form->get('apellido1')->getData();
            $apellido2 = $form->get('apellido2')->getData();
            $password = $form->get('password')->getData();
            $tipo = $form->get('tipo')->getData();

            if (mb_strlen(trim($nombre)) < 3) {
                $errores['nombre'] = 'El nombre debe tener al menos 3 caracteres.';
            }
            if (mb_strlen(trim($apellido1)) < 3) {
                $errores['apellido1'] = 'El primer apellido debe tener al menos 3 caracteres.';
            }
            if ($apellido2 !== null && $apellido2 !== '' && mb_strlen(trim($apellido2)) < 3) {
                $errores['apellido2'] = 'El segundo apellido debe tener al menos 3 caracteres si se indica.';
            }
            if (mb_strlen(trim($password)) < 4) {
                $errores['password'] = 'La contraseña debe tener al menos 4 caracteres.';
            }

            // Validación de asociación obligatoria según tipo
            if ($tipo === 'alumno') {
                $fechaNacimiento = $request->request->get('fechaNacimiento');
                $peso = $request->request->get('peso');
                $altura = $request->request->get('altura');
                $sexo = $request->request->get('sexo');

                // Validar fecha de nacimiento
                if (!$fechaNacimiento) {
                    $errores['fechaNacimiento'] = 'La fecha de nacimiento es obligatoria.';
                } else {
                    $fechaNacimientoObj = \DateTime::createFromFormat('Y-m-d', $fechaNacimiento);
                    if (!$fechaNacimientoObj) {
                        $errores['fechaNacimiento'] = 'La fecha de nacimiento no es válida.';
                    } elseif ($fechaNacimientoObj >= new \DateTime('-10 years')) {
                        $errores['fechaNacimiento'] = 'El alumno debe tener al menos 10 años.';
                    }
                }

                // Validar peso
                if (!$peso || !is_numeric($peso)) {
                    $errores['peso'] = 'El peso es obligatorio y debe ser un número.';
                } elseif ($peso < 40 || $peso > 200) {
                    $errores['peso'] = 'El peso debe estar entre 40 y 200 kg.';
                }

                // Validar altura
                if (!$altura || !is_numeric($altura)) {
                    $errores['altura'] = 'La altura es obligatoria y debe ser un número.';
                } elseif ($altura < 50 || $altura > 300) {
                    $errores['altura'] = 'La altura debe estar entre 50 cm y 300 cm.';
                }
            } elseif ($tipo === 'profesor') {
                $especialidad = $request->request->get('especialidad');
                if (!$especialidad) {
                    $errores['profesor'] = 'Debes introducir la especialidad para un profesor.';
                }
            } elseif ($tipo === 'administrador') {
                // No hay campos extra obligatorios, pero se debe crear el registro admin
            } else {
                $errores['tipo'] = 'Debes seleccionar un tipo de usuario válido.';
            }

            if ($form->isValid() && !$errores) {
                $em->persist($usuario);
                $em->flush();

                if ($tipo === 'alumno') {
                    $alumno = new Alumno();
                    $alumno->setFechaNacimiento(new \DateTime($fechaNacimiento));
                    $alumno->setPeso((int)$peso);
                    $alumno->setAltura((int)$altura);
                    $alumno->setSexo($sexo);
                    $alumno->setUsuario($usuario);
                    $em->persist($alumno);
                } elseif ($tipo === 'profesor') {
                    $profesor = new Profesor();
                    $profesor->setEspecialidad($especialidad);
                    $profesor->setUsuario($usuario);
                    $em->persist($profesor);
                } elseif ($tipo === 'administrador') {
                    $activo = $request->request->get('activo') === 'on' ? true : false;
                    $admin = new Administrador();
                    $admin->setActivo($activo);
                    $admin->setUsuario($usuario);
                    $em->persist($admin);
                }

                $em->flush();

                // Mensaje flash de éxito
                $this->addFlash('success', 'Usuario creado exitosamente.');

                return $this->redirectToRoute('app_usuario');
            }
        }

        return $this->render('usuario/nuevo.html.twig', [
            'form' => $form->createView(),
            'titulo' => 'Nuevo usuario',
            'errores' => $errores,
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
        $errores = [];

        if (!$usuario) {
            throw $this->createNotFoundException('Usuario no encontrado');
        }

        $form = $this->createFormBuilder($usuario)
            ->add('email', EmailType::class, [
                'label' => 'Email',
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
            ->add('password', PasswordType::class, [
                'label' => 'Contraseña',
                'required' => false,
                'mapped' => false,
                'empty_data' => '',
                'attr' => ['class' => 'form-control', 'autocomplete' => 'new-password'],
            ])
            ->add('guardar', SubmitType::class, [
                'label' => 'Guardar cambios',
                'attr' => ['class' => 'btn btn-primary mt-3'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $nombre = $form->get('nombre')->getData();
            $apellido1 = $form->get('apellido1')->getData();
            $apellido2 = $form->get('apellido2')->getData();
            $password = $form->get('password')->getData();

            if (mb_strlen(trim($nombre)) < 3) {
                $errores['nombre'] = 'El nombre debe tener al menos 3 caracteres.';
            }
            if (mb_strlen(trim($apellido1)) < 3) {
                $errores['apellido1'] = 'El primer apellido debe tener al menos 3 caracteres.';
            }
            if ($apellido2 !== null && $apellido2 !== '' && mb_strlen(trim($apellido2)) < 3) {
                $errores['apellido2'] = 'El segundo apellido debe tener al menos 3 caracteres si se indica.';
            }
            if ($password !== null && $password !== '' && mb_strlen(trim($password)) < 4) {
                $errores['password'] = 'La contraseña debe tener al menos 4 caracteres.';
            }

            // Validaciones específicas para el tipo alumno
            if ($usuario->getTipo() === 'alumno') {
                $fechaNacimiento = $request->request->get('fechaNacimiento');
                $peso = $request->request->get('peso');
                $altura = $request->request->get('altura');
                $sexo = $request->request->get('sexo');

                // Validar fecha de nacimiento
                if (!$fechaNacimiento) {
                    $errores['fechaNacimiento'] = 'La fecha de nacimiento es obligatoria.';
                } else {
                    $fechaNacimientoObj = \DateTime::createFromFormat('Y-m-d', $fechaNacimiento);
                    if (!$fechaNacimientoObj) {
                        $errores['fechaNacimiento'] = 'La fecha de nacimiento no es válida.';
                    } elseif ($fechaNacimientoObj >= new \DateTime('-10 years')) {
                        $errores['fechaNacimiento'] = 'El alumno debe tener al menos 10 años.';
                    }
                }

                // Validar peso
                if (!$peso || !is_numeric($peso)) {
                    $errores['peso'] = 'El peso es obligatorio y debe ser un número.';
                } elseif ($peso < 40 || $peso > 200) {
                    $errores['peso'] = 'El peso debe estar entre 40 y 200 kg.';
                }

                // Validar altura
                if (!$altura || !is_numeric($altura)) {
                    $errores['altura'] = 'La altura es obligatoria y debe ser un número.';
                } elseif ($altura < 50 || $altura > 300) {
                    $errores['altura'] = 'La altura debe estar entre 50 cm y 300 cm.';
                }
            }

            if ($form->isValid() && !$errores) {
                if (!empty($password)) {
                    $usuario->setPassword($password); // No se hashea aquí, pero debería hacerse
                }

                // Actualiza los datos adicionales según el tipo
                if ($usuario->getTipo() === 'alumno' && $usuario->getAlumno()) {
                    $alumno = $usuario->getAlumno();
                    if ($fechaNacimiento) $alumno->setFechaNacimiento(new \DateTime($fechaNacimiento));
                    if ($peso) $alumno->setPeso((int)$peso);
                    if ($altura) $alumno->setAltura((int)$altura);
                    if ($sexo) $alumno->setSexo($sexo);
                } elseif ($usuario->getTipo() === 'profesor' && $usuario->getProfesor()) {
                    $profesor = $usuario->getProfesor();
                    $especialidad = $request->request->get('especialidad');
                    if ($especialidad) $profesor->setEspecialidad($especialidad);
                } elseif ($usuario->getTipo() === 'administrador' && $usuario->getAdministrador()) {
                    $admin = $usuario->getAdministrador();
                    $activo = $request->request->get('activo') === 'on' ? true : false;
                    $admin->setActivo($activo);
                }

                $em->flush();

                // Mensaje flash de éxito
                $this->addFlash('success', 'Usuario editado exitosamente.');

                return $this->redirectToRoute('app_usuario');
            }
        }

        return $this->render('usuario/editar.html.twig', [
            'form' => $form->createView(),
            'titulo' => 'Editar usuario',
            'usuario' => $usuario,
            'errores' => $errores,
        ]);
    }

#[Route('/usuario/{id}/eliminar', name: 'usuario_eliminar', methods: ['POST'])]
public function eliminar(int $id, EntityManagerInterface $em): Response
{
    $usuario = $em->getRepository(Usuario::class)->find($id);

    if (!$usuario) {
        throw $this->createNotFoundException('Usuario no encontrado');
    }

    // ============================================
    // 🔍 1. COMPROBAR SI EL USUARIO ESTÁ ASOCIADO A CLASES
    // ============================================

    // Caso PROFESOR → revisar clases donde es profesor
    if ($usuario->getTipo() === 'profesor' && method_exists($usuario, 'getProfesor')) {
        $profesor = $usuario->getProfesor();
        
        if ($profesor) {
            $clases = $em->getRepository(Clase::class)->findBy(['profesor' => $profesor]);

            if (count($clases) > 0) {
                $this->addFlash('danger', 
                    'No se puede eliminar este usuario porque está asociado a clases como profesor.'
                );
                return $this->redirectToRoute('app_usuario');
            }
        }
    }

    // Caso ALUMNO → revisar reservas
    if ($usuario->getTipo() === 'alumno' && method_exists($usuario, 'getAlumno')) {
        $alumno = $usuario->getAlumno();
        
        if ($alumno) {
            $reservas = $em->getRepository(Reserva::class)->findBy(['alumno' => $alumno]);

            if (count($reservas) > 0) {
                $this->addFlash('danger', 
                    'No se puede eliminar este usuario porque tiene reservas en clases.'
                );
                return $this->redirectToRoute('app_usuario');
            }
        }
    }

    // ============================================
    // 🔥 2. ELIMINAR REGISTROS ASOCIADOS SEGÚN EL TIPO
    // ============================================

    if ($usuario->getTipo() === 'alumno' && method_exists($usuario, 'getAlumno')) {
        if ($alumno = $usuario->getAlumno()) {
            $em->remove($alumno);
        }
    } 
    elseif ($usuario->getTipo() === 'profesor' && method_exists($usuario, 'getProfesor')) {
        if ($profesor = $usuario->getProfesor()) {
            $em->remove($profesor);
        }
    }
    elseif ($usuario->getTipo() === 'administrador' && method_exists($usuario, 'getAdministrador')) {
        if ($admin = $usuario->getAdministrador()) {
            $em->remove($admin);
        }
    }

    // ============================================
    // ✔ 3. ELIMINAR USUARIO
    // ============================================
    $em->remove($usuario);
    $em->flush();

    // Mensaje de éxito
    $this->addFlash('success', 'Usuario eliminado correctamente.');

    return $this->redirectToRoute('app_usuario');
}

}
