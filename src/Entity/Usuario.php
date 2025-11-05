<?php

namespace App\Entity;

use App\Repository\UsuarioRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: UsuarioRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'Ya existe una cuenta con este email.')]
class Usuario implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    // JSON de roles. Se combinará con el rol derivado por "tipo"
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    // Contraseña en texto plano (según tu configuración de 'plaintext')
    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nombre = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $apellido1 = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $apellido2 = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $tipo = null;

    // =================== RELACIONES BI-DIRECCIONALES ===================

    #[ORM\OneToOne(mappedBy: 'usuario', targetEntity: Alumno::class, cascade: ['persist', 'remove'])]
    private ?Alumno $alumno = null;

    #[ORM\OneToOne(mappedBy: 'usuario', targetEntity: Profesor::class, cascade: ['persist', 'remove'])]
    private ?Profesor $profesor = null;

    #[ORM\OneToOne(mappedBy: 'usuario', targetEntity: Administrador::class, cascade: ['persist', 'remove'])]
    private ?Administrador $administrador = null;

    // ============================ MÉTODOS ==============================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower(trim($email));
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /** @deprecated Mantener por compatibilidad con código antiguo */
    public function getUsername(): string
    {
        return $this->getUserIdentifier();
    }

    /**
     * Devuelve los roles combinando:
     * - roles guardados en JSON (si existieran)
     * - rol derivado del campo "tipo"
     * - ROLE_USER siempre
     */
    public function getRoles(): array
    {
        $roles = $this->roles ?? [];

        switch (strtolower((string) $this->tipo)) {
            case 'administrador':
            case 'admin':
                $roles[] = 'ROLE_ADMIN';
                break;
            case 'profesor':
                $roles[] = 'ROLE_PROFESOR';
                break;
            case 'alumno':
                $roles[] = 'ROLE_ALUMNO';
                break;
        }

        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): self
    {
        $this->roles = array_values(array_unique($roles));
        return $this;
    }

    // Contraseña en texto plano (no se cifra con 'plaintext')
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
        // No hay datos sensibles temporales que limpiar
    }

    // ========== CAMPOS ADICIONALES ==========

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(?string $nombre): self
    {
        $this->nombre = $nombre;
        return $this;
    }

    public function getApellido1(): ?string
    {
        return $this->apellido1;
    }

    public function setApellido1(?string $apellido1): self
    {
        $this->apellido1 = $apellido1;
        return $this;
    }

    public function getApellido2(): ?string
    {
        return $this->apellido2;
    }

    public function setApellido2(?string $apellido2): self
    {
        $this->apellido2 = $apellido2;
        return $this;
    }

    public function getTipo(): ?string
    {
        return $this->tipo;
    }

    public function setTipo(?string $tipo): self
    {
        $this->tipo = $tipo ? mb_strtolower(trim($tipo)) : null;
        return $this;
    }

    // =================== GETTERS/SETTERS RELACIONES ===================

    public function getAlumno(): ?Alumno
    {
        return $this->alumno;
    }

    public function setAlumno(?Alumno $alumno): self
    {

        $this->alumno = $alumno;

        // Mantener sincronizada la otra cara
        if ($alumno && $alumno->getUsuario() !== $this) {
            $alumno->setUsuario($this);
        }

        return $this;
    }

    public function getProfesor(): ?Profesor
    {
        return $this->profesor;
    }

    public function setProfesor(?Profesor $profesor): self
    {


        $this->profesor = $profesor;

        if ($profesor && $profesor->getUsuario() !== $this) {
            $profesor->setUsuario($this);
        }

        return $this;
    }

    public function getAdministrador(): ?Administrador
    {
        return $this->administrador;
    }

    public function setAdministrador(?Administrador $administrador): self
    {

        $this->administrador = $administrador;

        if ($administrador && $administrador->getUsuario() !== $this) {
            $administrador->setUsuario($this);
        }

        return $this;
    }
}
