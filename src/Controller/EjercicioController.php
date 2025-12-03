<?php

namespace App\Controller;

use App\Entity\Ejercicio;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Knp\Component\Pager\PaginatorInterface;

final class EjercicioController extends AbstractController
{
    #[Route('/ejercicio', name: 'app_ejercicio')]
    public function index(EntityManagerInterface $em, Request $request, PaginatorInterface $paginator): Response
    {
        $perPageOptions = [10, 25, 50, 100];
        $perPage = (int) $request->query->get('per_page', 10);
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 10;
        }
        $page = max(1, $request->query->getInt('page', 1));

        $q = trim((string) $request->query->get('q', ''));

        $qb = $em->getRepository(Ejercicio::class)->createQueryBuilder('e');
        if ($q !== '') {
            $qb->andWhere('LOWER(e.nombre) LIKE :q OR LOWER(e.descripcion) LIKE :q OR LOWER(e.musculo_principal) LIKE :q')
               ->setParameter('q', '%'.mb_strtolower($q).'%');
        }
        $qb->orderBy('e.id', 'ASC');

        $pagination = $paginator->paginate(
            $qb,
            $page,
            $perPage
        );

        return $this->render('ejercicio/index.html.twig', [
            'ejercicios'      => $pagination,
            'titulo'          => 'Lista de Ejercicios',
            'per_page'        => $perPage,
            'perPageOptions'  => $perPageOptions,
            'q'               => $q,
        ]);
    }

    #[Route('/ejercicio/nuevo', name: 'ejercicio_nuevo')]
    public function nuevo(Request $request, EntityManagerInterface $em): Response
    {
        $ejercicio = new Ejercicio();

        $form = $this->createFormBuilder($ejercicio)
            ->add('nombre', TextType::class, [
                'label' => 'Nombre',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('descripcion', TextType::class, [
                'label' => 'Descripción',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('dificultad', TextType::class, [
                'label' => 'Dificultad',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('musculo_principal', TextType::class, [
                'label' => 'Músculo principal',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('repeticiones', IntegerType::class, [
                'label' => 'Repeticiones',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('guardar', SubmitType::class, [
                'label' => 'Guardar ejercicio',
                'attr' => ['class' => 'btn btn-primary mt-3'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($ejercicio);
            $em->flush();
            $this->addFlash('success', 'Ejercicio creado correctamente.');

            return $this->redirectToRoute('app_ejercicio');
        }

        return $this->render('ejercicio/nuevo.html.twig', [
            'form' => $form->createView(),
            'titulo' => 'Nuevo ejercicio',
        ]);
    }

    #[Route('/ejercicio/{id}/editar', name: 'ejercicio_editar')]
    public function editar(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $ejercicio = $em->getRepository(Ejercicio::class)->find($id);

        if (!$ejercicio) {
            throw $this->createNotFoundException('Ejercicio no encontrado');
        }

        $form = $this->createFormBuilder($ejercicio)
            ->add('nombre', TextType::class, [
                'label' => 'Nombre',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('descripcion', TextType::class, [
                'label' => 'Descripción',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('dificultad', TextType::class, [
                'label' => 'Dificultad',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('musculo_principal', TextType::class, [
                'label' => 'Músculo principal',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('repeticiones', IntegerType::class, [
                'label' => 'Repeticiones',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('guardar', SubmitType::class, [
                'label' => 'Guardar cambios',
                'attr' => ['class' => 'btn btn-primary mt-3'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Ejercicio modificado correctamente.');

            return $this->redirectToRoute('app_ejercicio');
        }

        return $this->render('ejercicio/editar.html.twig', [
            'form' => $form->createView(),
            'titulo' => 'Editar ejercicio',
        ]);
    }

    #[Route('/ejercicio/{id}', name: 'ejercicio_visualizar')]
    public function visualizar(int $id, EntityManagerInterface $em): Response
    {
        $ejercicio = $em->getRepository(Ejercicio::class)->find($id);

        if (!$ejercicio) {
            throw $this->createNotFoundException('Ejercicio no encontrado');
        }

        return $this->render('ejercicio/visualizar.html.twig', [
            'ejercicio' => $ejercicio,
            'titulo' => 'Visualizar ejercicio',
        ]);
    }

    #[Route('/ejercicio/{id}/eliminar', name: 'ejercicio_eliminar', methods: ['POST'])]
    public function eliminar(int $id, EntityManagerInterface $em): Response
    {
        $ejercicio = $em->getRepository(Ejercicio::class)->find($id);

        if (!$ejercicio) {
            throw $this->createNotFoundException('Ejercicio no encontrado');
        }

        $em->remove($ejercicio);
        $em->flush();
        $this->addFlash('success', 'Ejercicio eliminado correctamente.');

        return $this->redirectToRoute('app_ejercicio');
    }
}
