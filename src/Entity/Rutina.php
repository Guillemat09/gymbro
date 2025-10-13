<?php

namespace App\Entity;

use App\Repository\RutinaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RutinaRepository::class)]
class Rutina
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nombre = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Alumno $alumno = null;

    /**
     * @var Collection<int, RutinaEjercicios>
     */
    #[ORM\OneToMany(targetEntity: RutinaEjercicios::class, mappedBy: 'rutina')]
    private Collection $rutinaEjercicios;

    public function __construct()
    {
        $this->rutinaEjercicios = new ArrayCollection();
    }

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

    public function getAlumno(): ?Alumno
    {
        return $this->alumno;
    }

    public function setAlumno(?Alumno $alumno): static
    {
        $this->alumno = $alumno;

        return $this;
    }

    /**
     * @return Collection<int, RutinaEjercicios>
     */
    public function getRutinaEjercicios(): Collection
    {
        return $this->rutinaEjercicios;
    }

    public function addRutinaEjercicio(RutinaEjercicios $rutinaEjercicio): static
    {
        if (!$this->rutinaEjercicios->contains($rutinaEjercicio)) {
            $this->rutinaEjercicios->add($rutinaEjercicio);
            $rutinaEjercicio->setRutina($this);
        }

        return $this;
    }

    public function removeRutinaEjercicio(RutinaEjercicios $rutinaEjercicio): static
    {
        if ($this->rutinaEjercicios->removeElement($rutinaEjercicio)) {
            // set the owning side to null (unless already changed)
            if ($rutinaEjercicio->getRutina() === $this) {
                $rutinaEjercicio->setRutina(null);
            }
        }

        return $this;
    }
}
