<?php

namespace App\Entity;

use App\Repository\RutinaEjerciciosRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RutinaEjerciciosRepository::class)]
class RutinaEjercicios
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'rutinaEjercicios')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Rutina $rutina = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Ejercicio $ejercicio = null;

    #[ORM\Column]
    private ?int $orden = null;

    #[ORM\Column]
    private ?int $repeticiones = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRutina(): ?Rutina
    {
        return $this->rutina;
    }

    public function setRutina(?Rutina $rutina): static
    {
        $this->rutina = $rutina;

        return $this;
    }

    public function getEjercicio(): ?Ejercicio
    {
        return $this->ejercicio;
    }

    public function setEjercicio(?Ejercicio $ejercicio): static
    {
        $this->ejercicio = $ejercicio;

        return $this;
    }

    public function getOrden(): ?int
    {
        return $this->orden;
    }

    public function setOrden(int $orden): static
    {
        $this->orden = $orden;

        return $this;
    }

    public function getRepeticiones(): ?int
    {
        return $this->repeticiones;
    }

    public function setRepeticiones(int $repeticiones): static
    {
        $this->repeticiones = $repeticiones;

        return $this;
    }
}
