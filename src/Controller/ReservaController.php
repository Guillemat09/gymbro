<?php

namespace App\Controller;

use App\Entity\Alumno;
use App\Entity\Clase;
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
    public function index(EntityManagerInterface $em): Response
    {
        $reservas = $em->getRepository(Reserva::class)->findAll();

        return $this->render('reserva/index.html.twig', [
            'reservas' => $reservas,
            'titulo' => 'Listado de reservas',
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
}
