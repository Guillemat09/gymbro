<?php

namespace App\Entity;

use App\Repository\EjercicioRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EjercicioRepository::class)]
class Ejercicio
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(length: 255)]
    private ?string $descripcion = null;

    #[ORM\Column(length: 70)]
    private ?string $dificultad = null;

    #[ORM\Column(length: 255)]
    private ?string $musculo_principal = null;

    #[ORM\Column]
    private ?int $repeticiones = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(string $descripcion): static
    {
        $this->descripcion = $descripcion;

        return $this;
    }

    public function getDificultad(): ?string
    {
        return $this->dificultad;
    }

    public function setDificultad(string $dificultad): static
    {
        $this->dificultad = $dificultad;

        return $this;
    }

    public function getMusculoPrincipal(): ?string
    {
        return $this->musculo_principal;
    }

    public function setMusculoPrincipal(string $musculo_principal): static
    {
        $this->musculo_principal = $musculo_principal;

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
