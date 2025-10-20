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
use Knp\Component\Pager\PaginatorInterface;

class UsuarioController extends AbstractController
{
    #[Route('/usuario', name: 'app_usuario')]
    public function index(EntityManagerInterface $em, Request $request, PaginatorInterface $paginator): Response
    {
        // Opciones permitidas y valor por defecto
        $perPageOptions = [10, 25, 50, 100];
        $perPage = (int) $request->query->get('per_page', 10);
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 10;
        }

        $page = max(1, $request->query->getInt('page', 1));

        // Query base (puedes añadir filtros aquí si los quieres)
        $qb = $em->getRepository(Usuario::class)->createQueryBuilder('u')
            ->orderBy('u.id', 'ASC');

        $pagination = $paginator->paginate(
            $qb->getQuery(),
            $page,
            $perPage
        );

        return $this->render('usuario/index.html.twig', [
            'usuarios' => $pagination,
            'titulo' => 'GymBro - Usuarios',
            'per_page' => $perPage,
            'perPageOptions' => $perPageOptions,
        ]);
    }

    #[Route('/usuario/nuevo', name: 'usuario_nuevo')]
    public function nuevo(Request $request, EntityManagerInterface $em): Response
    {
        $usuario = new Usuario();

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
            ->add('telefono', TextType::class, [
                'label' => 'Teléfono',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('direccion', TextType::class, [
                'label' => 'Dirección',
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

        if ($form->isSubmitted() && $form->isValid()) {
            $tipo = $form->get('tipo')->getData();

            $em->persist($usuario);
            $em->flush();

            if ($tipo === 'alumno') {
                $fechaNacimiento = $request->request->get('fechaNacimiento');
                $peso = $request->request->get('peso');
                $altura = $request->request->get('altura');
                $sexo = $request->request->get('sexo');

                if (!$fechaNacimiento || !$peso || !$altura || !$sexo) {
                    $this->addFlash('error', 'Todos los campos de alumno son obligatorios.');
                    return $this->redirectToRoute('usuario_nuevo');
                }

                $alumno = new Alumno();
                $alumno->setFechaNacimiento(new \DateTime($fechaNacimiento));
                $alumno->setPeso((int)$peso);
                $alumno->setAltura((int)$altura);
                $alumno->setSexo($sexo);
                $alumno->setUsuario($usuario);

                $em->persist($alumno);
            } elseif ($tipo === 'profesor') {
                $especialidad = $request->request->get('especialidad');
                if (!$especialidad) {
                    $this->addFlash('error', 'La especialidad es obligatoria para el profesor.');
                    return $this->redirectToRoute('usuario_nuevo');
                }
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

            return $this->redirectToRoute('app_usuario');
        }

        return $this->render('usuario/nuevo.html.twig', [
            'form' => $form->createView(),
            'titulo' => 'Nuevo usuario',
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
            ->add('telefono', TextType::class, [
                'label' => 'Teléfono',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('direccion', TextType::class, [
                'label' => 'Dirección',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('guardar', SubmitType::class, [
                'label' => 'Guardar cambios',
                'attr' => ['class' => 'btn btn-primary mt-3'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Actualiza los datos adicionales según el tipo
            if ($usuario->getTipo() === 'alumno' && $usuario->getAlumno()) {
                $alumno = $usuario->getAlumno();
                $fechaNacimiento = $request->request->get('fechaNacimiento');
                $peso = $request->request->get('peso');
                $altura = $request->request->get('altura');
                $sexo = $request->request->get('sexo');
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
            return $this->redirectToRoute('app_usuario');
        }

        return $this->render('usuario/editar.html.twig', [
            'form' => $form->createView(),
            'titulo' => 'Editar usuario',
            'usuario' => $usuario, // <-- Añade esta línea
        ]);
    }

    #[Route('/usuario/{id}/eliminar', name: 'usuario_eliminar', methods: ['POST'])]
    public function eliminar(int $id, EntityManagerInterface $em): Response
    {
        $usuario = $em->getRepository(Usuario::class)->find($id);

        if (!$usuario) {
            throw $this->createNotFoundException('Usuario no encontrado');
        }

        // Eliminar registro asociado según el tipo
        if ($usuario->getTipo() === 'alumno' && method_exists($usuario, 'getAlumno')) {
            $alumno = $usuario->getAlumno();
            if ($alumno) {
                $em->remove($alumno);
            }
        } elseif ($usuario->getTipo() === 'profesor' && method_exists($usuario, 'getProfesor')) {
            $profesor = $usuario->getProfesor();
            if ($profesor) {
                $em->remove($profesor);
            }
        } elseif ($usuario->getTipo() === 'administrador' && method_exists($usuario, 'getAdministrador')) {
            $admin = $usuario->getAdministrador();
            if ($admin) {
                $em->remove($admin);
            }
        }

        $em->remove($usuario);
        $em->flush();

        return $this->redirectToRoute('app_usuario');
    }
}
