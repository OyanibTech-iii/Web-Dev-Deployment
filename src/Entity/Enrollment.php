<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use App\Enum\Status;
use App\Repository\EnrollmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Delete()
    ],
    normalizationContext: [
        'groups' => ['enrollment:read']
    ],
    denormalizationContext: [
        'groups' => ['enrollment:write']
    ]
)]


#[ORM\Entity(repositoryClass: EnrollmentRepository::class)]
class Enrollment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]

    #[Groups(['enrollment:read'])]

    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'enrollments')]
    #[Groups(['enrollment:read', 'enrollment:write'])]

    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'enrollments')]
    #[Groups(['enrollment:read', 'enrollment:write'])]

    private ?Course $course = null;

    #[ORM\Column(enumType: Status::class)]
    #[Groups(['enrollment:read', 'enrollment:write'])]

    private ?Status $status = null;

    #[ORM\Column]
    #[Groups(['enrollment:read', 'enrollment:write'])]

    private ?\DateTimeImmutable $enrolledAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['enrollment:read', 'enrollment:write'])]

    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['enrollment:read', 'enrollment:write'])]

    private bool $courseTaken = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): static
    {
        $this->course = $course;

        return $this;
    }

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getEnrolledAt(): ?\DateTimeImmutable
    {
        return $this->enrolledAt;
    }

    public function setEnrolledAt(\DateTimeImmutable $enrolledAt): static
    {
        $this->enrolledAt = $enrolledAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function isCourseTaken(): bool
    {
        return $this->courseTaken;
    }

    public function setCourseTaken(bool $courseTaken): static
    {
        $this->courseTaken = $courseTaken;

        return $this;
    }
}
